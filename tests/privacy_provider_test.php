<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the Dixeo Tutor privacy provider.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\event\conversation_deleted;
use block_dixeo_tutor\event\privacy_request_failed;
use block_dixeo_tutor\external\delete_conversation;
use block_dixeo_tutor\privacy\provider;
use block_dixeo_tutor\task\erase_conversations;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * Conversations live in the Dixeo API, so the provider is judged on the calls it makes.
 *
 * @covers \block_dixeo_tutor\privacy\provider
 * @covers \block_dixeo_tutor\external\delete_conversation
 * @covers \block_dixeo_tutor\event\conversation_deleted
 * @covers \block_dixeo_tutor\event\privacy_request_failed
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // The provider is inert on an unconfigured site, so every other test needs a key.
        set_config('api_key', 'phpunit-api-key', 'local_dixeo');
    }

    public function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Register a mocked tutor service in place of the real one.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject&tutor_service The mock.
     */
    private function mock_tutor_service(): \PHPUnit\Framework\MockObject\MockObject {
        $service = $this->createMock(tutor_service::class);
        service_factory::set_test_tutor_service($service);
        return $service;
    }

    /**
     * Create a course with an enrolled student.
     *
     * @return array{0: \stdClass, 1: \stdClass} Course and user.
     */
    private function create_course_and_student(): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        return [$course, $user];
    }

    /**
     * Build the exception the client raises when the Dixeo API cannot be reached.
     *
     * @return api_exception The outage.
     */
    private function api_outage(): api_exception {
        return new api_exception('connection_error', 'Failed to connect to Dixeo API', 0);
    }

    /**
     * List the scopes of every queued erasure task.
     *
     * @return array<int, array{0: int|null, 1: int|null}> Course and user of each task.
     */
    private function queued_erasure_scopes(): array {
        $scopes = [];

        foreach (\core\task\manager::get_adhoc_tasks(erase_conversations::class) as $task) {
            $data = $task->get_custom_data();
            $scopes[] = [
                isset($data->courseid) ? (int) $data->courseid : null,
                isset($data->userid) ? (int) $data->userid : null,
            ];
        }

        return $scopes;
    }

    /**
     * Collect the outage events raised during the redirected window.
     *
     * @param \phpunit_event_sink $sink The redirected events.
     * @return array<int, privacy_request_failed> The privacy failure events.
     */
    private function failure_events(\phpunit_event_sink $sink): array {
        return array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof privacy_request_failed
        ));
    }

    /**
     * Collect the admin notifications raised during the redirected window.
     *
     * @param \phpunit_message_sink $sink The redirected messages.
     * @return array<int, \stdClass> The privacy failure notifications.
     */
    private function failure_notifications(\phpunit_message_sink $sink): array {
        return array_values(array_filter(
            $sink->get_messages(),
            static fn($message) => $message->component === 'block_dixeo_tutor'
                && $message->eventtype === 'privacyfailure'
        ));
    }

    public function test_get_metadata_declares_the_dixeo_api_as_an_external_location(): void {
        $items = provider::get_metadata(new collection('block_dixeo_tutor'))->get_collection();
        $this->assertNotEmpty($items);

        $locations = array_filter(
            $items,
            static fn($item) => $item instanceof \core_privacy\local\metadata\types\external_location
                && $item->get_name() === 'dixeo_api'
        );
        $this->assertCount(1, $locations);

        $location = reset($locations);
        $fields = $location->get_privacy_fields();
        $this->assertArrayHasKey('userid', $fields);
        $this->assertArrayHasKey('courseid', $fields);
        $this->assertArrayHasKey('message', $fields);
        $this->assertArrayHasKey('pageurl', $fields);

        // A metadata declaration nobody can read is not a declaration.
        foreach (array_merge(array_values($fields), [$location->get_summary()]) as $identifier) {
            $this->assertTrue(
                get_string_manager()->string_exists($identifier, 'block_dixeo_tutor'),
                "Missing language string '{$identifier}'"
            );
            $this->assertNotEmpty(get_string($identifier, 'block_dixeo_tutor'));
        }
    }

    public function test_the_provider_is_inert_without_an_api_key(): void {
        [$course, $user] = $this->create_course_and_student();
        $context = \context_course::instance($course->id);
        set_config('api_key', '', 'local_dixeo');

        // No key means no conversation was ever sent: the API must not even be called.
        $service = $this->mock_tutor_service();
        $service->expects($this->never())->method('list_conversations');
        $service->expects($this->never())->method('export_conversation');
        $service->expects($this->never())->method('delete_conversations');

        $this->assertCount(0, provider::get_contexts_for_userid((int) $user->id)->get_contexts());

        $userlist = new userlist($context, 'block_dixeo_tutor');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());

        $contextlist = new approved_contextlist($user, 'block_dixeo_tutor', [$context->id]);
        provider::export_user_data($contextlist);
        $this->assertFalse(writer::with_context($context)->has_any_data());

        provider::delete_data_for_user($contextlist);
        provider::delete_data_for_all_users_in_context($context);
        provider::delete_data_for_users(new approved_userlist(
            $context,
            'block_dixeo_tutor',
            [(int) $user->id]
        ));

        // A queued task would retry against a key that does not exist, for ever.
        $this->assertSame([], $this->queued_erasure_scopes());
    }

    public function test_get_contexts_for_userid_maps_conversations_to_course_contexts(): void {
        [$course, $user] = $this->create_course_and_student();
        $othercourse = $this->getDataGenerator()->create_course();

        $service = $this->mock_tutor_service();
        $service->expects($this->once())
            ->method('list_conversations')
            ->with(null, (int) $user->id)
            ->willReturn([
                ['courseid' => (int) $course->id, 'userid' => (int) $user->id],
                ['courseid' => (int) $course->id, 'userid' => (int) $user->id],
            ]);

        $contexts = provider::get_contexts_for_userid((int) $user->id)->get_contexts();

        $this->assertCount(1, $contexts);
        $this->assertEquals(\context_course::instance($course->id)->id, reset($contexts)->id);
        $this->assertNotEquals(\context_course::instance($othercourse->id)->id, reset($contexts)->id);
    }

    public function test_get_contexts_for_userid_without_conversation_is_empty(): void {
        [, $user] = $this->create_course_and_student();

        $this->mock_tutor_service()->method('list_conversations')->willReturn([]);

        $this->assertCount(0, provider::get_contexts_for_userid((int) $user->id)->get_contexts());
    }

    public function test_get_contexts_for_userid_reports_an_unreachable_api(): void {
        [, $user] = $this->create_course_and_student();

        $this->mock_tutor_service()
            ->method('list_conversations')
            ->willThrowException($this->api_outage());

        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();

        // Core reads an empty contextlist as "this user holds no tutor data", so the
        // outage has to surface somewhere the officer will see it.
        $this->assertCount(0, provider::get_contexts_for_userid((int) $user->id)->get_contexts());
        $this->assertDebuggingCalled();

        $failures = $this->failure_events($events);
        $this->assertCount(1, $failures);
        $this->assertEquals(\context_system::instance()->id, (int) $failures[0]->contextid);
        $this->assertEquals((int) $user->id, (int) $failures[0]->relateduserid);
        $this->assertSame('connection_error', $failures[0]->other['errorcode']);

        $this->assertEqualsCanonicalizing(
            array_map('intval', array_keys(get_admins())),
            array_map('intval', array_column($this->failure_notifications($messages), 'useridto'))
        );

        // This path serves exports too: erasing here would destroy what was asked for.
        $this->assertSame([], $this->queued_erasure_scopes());
    }

    public function test_get_users_in_context_lists_the_api_users(): void {
        [$course, $user] = $this->create_course_and_student();
        $context = \context_course::instance($course->id);

        $service = $this->mock_tutor_service();
        $service->expects($this->once())
            ->method('list_conversations')
            ->with((int) $course->id)
            ->willReturn([['courseid' => (int) $course->id, 'userid' => (int) $user->id]]);

        $userlist = new userlist($context, 'block_dixeo_tutor');
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing([(int) $user->id], $userlist->get_userids());
    }

    public function test_get_users_in_context_ignores_non_course_contexts(): void {
        $service = $this->mock_tutor_service();
        $service->expects($this->never())->method('list_conversations');

        $userlist = new userlist(\context_system::instance(), 'block_dixeo_tutor');
        provider::get_users_in_context($userlist);

        $this->assertEmpty($userlist->get_userids());
    }

    public function test_get_users_in_context_reports_an_unreachable_api(): void {
        [$course] = $this->create_course_and_student();

        $this->mock_tutor_service()
            ->method('list_conversations')
            ->willThrowException($this->api_outage());

        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();

        $userlist = new userlist(\context_course::instance($course->id), 'block_dixeo_tutor');
        provider::get_users_in_context($userlist);
        $this->assertDebuggingCalled();

        $this->assertEmpty($userlist->get_userids());

        // The scope is a course, not a person, so the event names no related user.
        $failures = $this->failure_events($events);
        $this->assertCount(1, $failures);
        $this->assertNull($failures[0]->relateduserid);
        $this->assertNotEmpty($this->failure_notifications($messages));
    }

    public function test_export_user_data_writes_the_whole_conversation(): void {
        [$course, $user] = $this->create_course_and_student();
        $context = \context_course::instance($course->id);

        $service = $this->mock_tutor_service();
        $service->expects($this->once())
            ->method('export_conversation')
            ->with((int) $course->id, (int) $user->id)
            ->willReturn([
                ['id' => 'msg-1', 'role' => 'user', 'content' => 'How do I revise?', 'time' => 1750000000],
                ['id' => 'msg-2', 'role' => 'assistant', 'content' => 'Start with unit 1.', 'time' => 1750000060],
            ]);

        provider::export_user_data(new approved_contextlist(
            $user,
            'block_dixeo_tutor',
            [$context->id]
        ));

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $exported = $writer->get_data([get_string('privacy:path:conversation', 'block_dixeo_tutor')]);
        $this->assertCount(2, $exported->messages);
        $this->assertSame('user', $exported->messages[0]->role);
        $this->assertSame('How do I revise?', $exported->messages[0]->content);
        $this->assertSame('assistant', $exported->messages[1]->role);
    }

    public function test_export_user_data_writes_nothing_when_there_is_no_conversation(): void {
        [$course, $user] = $this->create_course_and_student();
        $context = \context_course::instance($course->id);

        $this->mock_tutor_service()->method('export_conversation')->willReturn([]);

        provider::export_user_data(new approved_contextlist($user, 'block_dixeo_tutor', [$context->id]));

        $this->assertFalse(writer::with_context($context)->has_any_data());
    }

    public function test_export_user_data_reports_an_unreachable_api_once(): void {
        [$course, $user] = $this->create_course_and_student();
        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $othercourse->id, 'student');

        $this->mock_tutor_service()
            ->method('export_conversation')
            ->willThrowException($this->api_outage());

        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();

        // The archive is assembled once, so there is nothing to re-throw to: the export
        // must survive the outage rather than abort halfway through.
        provider::export_user_data(new approved_contextlist($user, 'block_dixeo_tutor', [
            \context_course::instance($course->id)->id,
            \context_course::instance($othercourse->id)->id,
        ]));

        // A single debugging call for two failed courses: the officer is warned once.
        $this->assertDebuggingCalled();

        $this->assertFalse(writer::with_context(\context_course::instance($course->id))->has_any_data());
        $this->assertCount(1, $this->failure_events($events));
        $this->assertCount(count(get_admins()), $this->failure_notifications($messages));
    }

    public function test_delete_data_for_user_targets_that_user_in_that_course(): void {
        [$course, $user] = $this->create_course_and_student();

        $service = $this->mock_tutor_service();
        $service->expects($this->once())
            ->method('delete_conversations')
            ->with((int) $course->id, (int) $user->id)
            ->willReturn(1);

        provider::delete_data_for_user(new approved_contextlist(
            $user,
            'block_dixeo_tutor',
            [\context_course::instance($course->id)->id]
        ));
    }

    public function test_delete_data_for_all_users_in_context_targets_the_whole_course(): void {
        [$course] = $this->create_course_and_student();

        $service = $this->mock_tutor_service();
        $service->expects($this->once())
            ->method('delete_conversations')
            ->with((int) $course->id, null)
            ->willReturn(4);

        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));
    }

    public function test_delete_data_for_all_users_in_context_ignores_non_course_contexts(): void {
        $service = $this->mock_tutor_service();
        $service->expects($this->never())->method('delete_conversations');

        provider::delete_data_for_all_users_in_context(\context_system::instance());
    }

    public function test_delete_data_for_users_targets_each_approved_user(): void {
        [$course, $user] = $this->create_course_and_student();
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $scopes = [];
        $service = $this->mock_tutor_service();
        $service->expects($this->exactly(2))
            ->method('delete_conversations')
            ->willReturnCallback(function(?int $courseid, ?int $userid) use (&$scopes): int {
                $scopes[] = [$courseid, $userid];
                return 1;
            });

        provider::delete_data_for_users(new approved_userlist(
            \context_course::instance($course->id),
            'block_dixeo_tutor',
            [(int) $user->id, (int) $other->id]
        ));

        $this->assertEqualsCanonicalizing(
            [[(int) $course->id, (int) $user->id], [(int) $course->id, (int) $other->id]],
            $scopes
        );
    }

    public function test_delete_data_for_user_queues_a_retry_when_the_api_is_unreachable(): void {
        [$course, $user] = $this->create_course_and_student();

        $this->mock_tutor_service()
            ->method('delete_conversations')
            ->willThrowException($this->api_outage());

        try {
            provider::delete_data_for_user(new approved_contextlist(
                $user,
                'block_dixeo_tutor',
                [\context_course::instance($course->id)->id]
            ));
            $this->fail('The failure must reach core so it is logged as debugging.');
        } catch (api_exception $e) {
            $this->assertSame('connection_error', $e->get_error_code());
        }

        $this->assertEqualsCanonicalizing(
            [[(int) $course->id, (int) $user->id]],
            $this->queued_erasure_scopes()
        );
    }

    public function test_delete_data_for_all_users_in_context_queues_a_retry_when_the_api_is_unreachable(): void {
        [$course] = $this->create_course_and_student();

        $this->mock_tutor_service()
            ->method('delete_conversations')
            ->willThrowException($this->api_outage());

        $this->expectException(api_exception::class);

        try {
            provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));
        } finally {
            $this->assertEqualsCanonicalizing([[(int) $course->id, null]], $this->queued_erasure_scopes());
        }
    }

    public function test_delete_data_for_users_queues_a_retry_per_user_when_the_api_is_unreachable(): void {
        [$course, $user] = $this->create_course_and_student();
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->mock_tutor_service()
            ->method('delete_conversations')
            ->willThrowException($this->api_outage());

        $this->expectException(api_exception::class);

        try {
            provider::delete_data_for_users(new approved_userlist(
                \context_course::instance($course->id),
                'block_dixeo_tutor',
                [(int) $user->id, (int) $other->id]
            ));
        } finally {
            // Every approved user is queued, not only the one that failed first.
            $this->assertEqualsCanonicalizing(
                [[(int) $course->id, (int) $user->id], [(int) $course->id, (int) $other->id]],
                $this->queued_erasure_scopes()
            );
        }
    }

    public function test_a_successful_erasure_queues_nothing(): void {
        [$course, $user] = $this->create_course_and_student();

        $this->mock_tutor_service()->method('delete_conversations')->willReturn(1);

        provider::delete_data_for_user(new approved_contextlist(
            $user,
            'block_dixeo_tutor',
            [\context_course::instance($course->id)->id]
        ));

        $this->assertSame([], $this->queued_erasure_scopes());
    }

    public function test_delete_conversation_web_service_erases_and_logs_evidence(): void {
        [$course, $user] = $this->create_course_and_student();
        $this->setUser($user);

        $service = $this->mock_tutor_service();
        $service->expects($this->once())
            ->method('delete_conversations')
            ->with((int) $course->id, (int) $user->id)
            ->willReturn(1);

        $sink = $this->redirectEvents();
        $result = delete_conversation::execute((int) $course->id);

        $this->assertSame(1, $result['deleted']);

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof conversation_deleted
        ));
        $this->assertCount(1, $events);
        $this->assertEquals((int) $course->id, (int) $events[0]->courseid);
        $this->assertEquals((int) $user->id, (int) $events[0]->userid);
        $this->assertSame(1, (int) $events[0]->other['deleted']);
        $this->assertArrayNotHasKey('message', $events[0]->other);
        $this->assertArrayNotHasKey('messages', $events[0]->other);
    }

    public function test_delete_conversation_web_service_requires_the_capability(): void {
        global $DB;

        [$course, $user] = $this->create_course_and_student();
        $this->setUser($user);

        // Enrolled, so the context validates: only the capability can stop the call.
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability(
            'block/dixeo_tutor:talktotutor',
            CAP_PROHIBIT,
            $studentroleid,
            \context_course::instance($course->id)->id,
            true
        );

        $this->mock_tutor_service()->expects($this->never())->method('delete_conversations');

        $this->expectException(\required_capability_exception::class);
        delete_conversation::execute((int) $course->id);
    }

    public function test_delete_conversation_web_service_requires_being_enrolled(): void {
        [$course] = $this->create_course_and_student();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->mock_tutor_service()->expects($this->never())->method('delete_conversations');

        $this->expectException(\require_login_exception::class);
        delete_conversation::execute((int) $course->id);
    }
}
