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
use block_dixeo_tutor\external\delete_conversation;
use block_dixeo_tutor\privacy\provider;
use block_dixeo_tutor\service\tutor_erasure_marker;
use block_dixeo_tutor\service\tutor_mode_service;
use block_dixeo_tutor\service\tutor_read_state_service;
use block_dixeo_tutor\task\post_notice;
use block_dixeo_tutor\service\tutor_notice_service;
use block_dixeo_tutor\service\tutor_proactive_context_service;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * The provider handles the block's own data; remote conversations belong to local_dixeo.
 *
 * @covers \block_dixeo_tutor\privacy\provider
 * @covers \block_dixeo_tutor\external\delete_conversation
 * @covers \block_dixeo_tutor\event\conversation_deleted
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // A configured site: the provider must still leave remote conversations to local_dixeo.
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



















    /**
     * Every privacy call works on local data only and never reaches the tutor service.
     */
    public function test_the_provider_makes_no_remote_call(): void {
        [$course, $user] = $this->create_course_and_student();
        $context = \context_course::instance($course->id);
        $service = $this->mock_tutor_service();
        foreach (['list_conversations', 'export_conversation', 'delete_conversations', 'submit', 'add_message'] as $method) {
            $service->expects($this->never())->method($method);
        }

        provider::get_contexts_for_userid((int) $user->id);
        provider::get_users_in_context(new userlist($context, 'block_dixeo_tutor'));
        $approved = new approved_contextlist($user, 'block_dixeo_tutor', [$context->id]);
        provider::export_user_data($approved);
        provider::delete_data_for_user($approved);
        provider::delete_data_for_users(new approved_userlist($context, 'block_dixeo_tutor', [(int) $user->id]));
        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame([], \core\task\manager::get_adhoc_tasks(\local_dixeo\task\erase_tutor_conversations::class));
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

    /**
     * Queued proactive context and the last-proactive preference are exported and erased.
     */
    public function test_pending_context_and_preference_are_exported_and_erased(): void {
        global $DB;

        [$course, $user] = $this->create_course_and_student();
        $courseid = (int) $course->id;
        $userid = (int) $user->id;
        $context = \context_course::instance($courseid);

        $DB->insert_record(tutor_proactive_context_service::TABLE, (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'message' => 'Welcome back',
            'timemodified' => time(),
        ]);
        $preferencename = tutor_notice_service::PREF_LAST_GREETING_PREFIX . $courseid;
        set_user_preference($preferencename, time(), $userid);
        $messagepreference = tutor_notice_service::PREF_LAST_MESSAGE_PREFIX . $courseid;
        tutor_notice_service::record_learner_message($userid, $courseid);

        $service = $this->mock_tutor_service();
        $service->method('list_conversations')->willReturn([]);
        $service->method('export_conversation')->willReturn([]);

        $contexts = provider::get_contexts_for_userid($userid);
        $this->assertEquals([$context->id], array_values($contexts->get_contextids()));

        writer::reset();
        provider::export_user_data(new approved_contextlist($user, 'block_dixeo_tutor', [$context->id]));

        $pending = writer::with_context($context)->get_data([
            get_string('privacy:path:pending', 'block_dixeo_tutor'),
        ]);
        $this->assertSame('Welcome back', $pending->queued[0]->message);
        $this->assertNotNull(get_user_preferences($preferencename, null, $userid));

        provider::delete_data_for_user(new approved_contextlist($user, 'block_dixeo_tutor', [$context->id]));

        $this->assertFalse($DB->record_exists(tutor_proactive_context_service::TABLE, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]));
        $this->assertNull(get_user_preferences($preferencename, null, $userid));
        $this->assertNull(get_user_preferences($messagepreference, null, $userid));
    }


    /**
     * Every per-course preference is found, exported and erased per user, per user list and per context.
     */
    public function test_every_course_preference_is_found_exported_and_erased(): void {
        global $DB;

        [$course, $user] = $this->create_course_and_student();
        $userid = (int) $user->id;
        $courseid = (int) $course->id;
        $context = \context_course::instance($courseid);
        $now = time();
        $preferences = [
            tutor_notice_service::PREF_LAST_GREETING_PREFIX . $courseid => $now,
            tutor_notice_service::PREF_LAST_MESSAGE_PREFIX . $courseid => $now,
            tutor_read_state_service::PREF_LAST_READ_PREFIX . $courseid => $now,
            tutor_mode_service::PREF_MODE_PREFIX . $courseid => 'guide',
            tutor_mode_service::PREF_LAST_ACTIVITY_PREFIX . $courseid => $now,
            tutor_erasure_marker::PREF_ERASED_AT_PREFIX . $courseid => $now,
        ];
        $this->assertCount(count(provider::COURSE_PREFERENCES), $preferences);

        $service = $this->mock_tutor_service();
        $service->method('list_conversations')->willReturn([]);
        $service->method('export_conversation')->willReturn([]);
        $service->method('delete_conversations')->willReturn(0);

        foreach ($preferences as $name => $value) {
            // Each preference alone is enough to find the course.
            set_user_preference($name, $value, $userid);
            $this->assertEquals([$context->id], array_values(provider::get_contexts_for_userid($userid)->get_contextids()));
            $userlist = new userlist($context, 'block_dixeo_tutor');
            provider::get_users_in_context($userlist);
            $this->assertEquals([$userid], $userlist->get_userids());
            unset_user_preference($name, $userid);
        }

        foreach ($preferences as $name => $value) {
            set_user_preference($name, $value, $userid);
        }
        writer::reset();
        provider::export_user_data(new approved_contextlist($user, 'block_dixeo_tutor', [$context->id]));
        $exported = writer::with_context($context)->get_user_context_preferences('block_dixeo_tutor');
        foreach (array_keys($preferences) as $name) {
            $this->assertObjectHasProperty($name, $exported);
        }
        $this->assertSame('guide', $exported->{tutor_mode_service::PREF_MODE_PREFIX . $courseid}->value);

        provider::delete_data_for_user(new approved_contextlist($user, 'block_dixeo_tutor', [$context->id]));
        foreach (array_keys($preferences) as $name) {
            $this->assertNull(get_user_preferences($name, null, $userid));
        }

        foreach ($preferences as $name => $value) {
            set_user_preference($name, $value, $userid);
        }
        provider::delete_data_for_users(new approved_userlist($context, 'block_dixeo_tutor', [$userid]));
        foreach (array_keys($preferences) as $name) {
            $this->assertNull(get_user_preferences($name, null, $userid));
        }

        foreach ($preferences as $name => $value) {
            set_user_preference($name, $value, $userid);
        }
        provider::delete_data_for_all_users_in_context($context);
        foreach (array_keys($preferences) as $name) {
            $this->assertFalse($DB->record_exists('user_preferences', ['userid' => $userid, 'name' => $name]));
        }
    }

    /**
     * Mode and mode-activity prefixes do not shadow each other when courses are discovered.
     */
    public function test_a_preference_prefix_never_matches_another_one(): void {
        [$course, $user] = $this->create_course_and_student();
        set_user_preference(tutor_mode_service::PREF_LAST_ACTIVITY_PREFIX . $course->id, time(), $user->id);
        set_user_preference(tutor_mode_service::PREF_MODE_PREFIX . 'notanumber', 'guide', $user->id);
        $this->mock_tutor_service()->method('list_conversations')->willReturn([]);

        $contextids = provider::get_contexts_for_userid((int) $user->id)->get_contextids();
        $this->assertEquals([\context_course::instance($course->id)->id], array_values($contextids));
    }

    /**
     * Erasure also drops notices still waiting in the task queue, which carry the learner's name.
     */
    public function test_erasure_discards_queued_notices(): void {
        [$course, $user] = $this->create_course_and_student();
        $other = $this->getDataGenerator()->create_course();
        post_notice::queue((int) $user->id, (int) $course->id, ['type' => 'course_completed', 'time' => time()]);
        post_notice::queue((int) $user->id, (int) $other->id, ['type' => 'course_completed', 'time' => time()]);
        $this->mock_tutor_service()->method('delete_conversations')->willReturn(0);

        $context = \context_course::instance($course->id);
        provider::delete_data_for_user(new approved_contextlist($user, 'block_dixeo_tutor', [$context->id]));

        $remaining = \core\task\manager::get_adhoc_tasks(post_notice::class);
        $this->assertCount(1, $remaining);
        $this->assertSame((int) $other->id, (int) reset($remaining)->get_custom_data()->courseid);
    }

    /**
     * Everything sent to Dixeo is declared, with a readable string for each field.
     */
    public function test_metadata_declares_every_transferred_field(): void {
        $items = provider::get_metadata(new collection('block_dixeo_tutor'))->get_collection();
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\external_location && $item->get_name() === 'dixeo_api') {
                $fields = $item->get_privacy_fields();
            }
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $this->assertArrayHasKey('timemodified', $item->get_privacy_fields());
            }
        }
        foreach (['firstname', 'notice', 'grades', 'quizattempt', 'lessoncontent', 'guideprompt', 'cmid', 'mode'] as $field) {
            $this->assertArrayHasKey($field, $fields);
            $this->assertTrue(get_string_manager()->string_exists($fields[$field], 'block_dixeo_tutor'));
        }
    }
}
