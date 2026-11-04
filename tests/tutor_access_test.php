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
 * Server-side tutor access rules.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\external\delete_conversation;
use block_dixeo_tutor\external\send_message;
use block_dixeo_tutor\external\start_guide_session;
use block_dixeo_tutor\external\submit_practice_quiz;
use block_dixeo_tutor\external\submit_teach_lesson;
use block_dixeo_tutor\service\tutor_proactive_context_service;
use block_dixeo_tutor\task\post_notice;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\service\consent_service;
use local_dixeo\service\practice_quiz_service;
use local_dixeo\service\teach_lesson_service;
use local_dixeo\service\tutor_service;

/**
 * Web services, observers and tasks enforce what the block UI shows.
 *
 * @covers \block_dixeo_tutor\tutor_access
 * @covers \block_dixeo_tutor\external\submit_teach_lesson
 * @covers \block_dixeo_tutor\external\submit_practice_quiz
 * @covers \block_dixeo_tutor\external\send_message
 * @covers \block_dixeo_tutor\external\start_guide_session
 * @covers \block_dixeo_tutor\task\post_notice
 * @covers \block_dixeo_tutor\service\tutor_proactive_context_service
 */
final class tutor_access_test extends \advanced_testcase {
    /** @var string Valid UUID for mocked jobs. */
    private const JOB_ID = '5f38d9aa-f40c-4992-9727-982f050ff9fd';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('api_key', 'phpunit-api-key', 'local_dixeo');
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Course with two sections and a student who accepted the Terms of Use.
     *
     * @return array{0: \stdClass, 1: \stdClass}
     */
    private function course_and_student(): array {
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        (new consent_service())->accept((int) $student->id);
        return [$course, $student];
    }

    /**
     * Run a call and assert it fails with the given error code.
     *
     * @param callable $call
     * @param string $errorcode
     */
    private function assert_refused(callable $call, string $errorcode): void {
        try {
            $call();
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
            return;
        }
        $this->fail("Expected a moodle_exception with code {$errorcode}");
    }

    /**
     * Teach generation service that must never be reached.
     */
    private function forbid_teach_generation(): void {
        $service = $this->createMock(teach_lesson_service::class);
        $service->expects($this->never())->method('submit_from_setup');
        service_factory::set_test_teach_lesson_service($service);
    }

    /**
     * Quiz generation service that must never be reached.
     */
    private function forbid_quiz_generation(): void {
        $service = $this->createMock(practice_quiz_service::class);
        $service->expects($this->never())->method('submit_from_setup');
        service_factory::set_test_practice_quiz_service($service);
    }

    /**
     * Tutor service whose remote calls must never be reached.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject&tutor_service
     */
    private function forbid_tutor_calls(): \PHPUnit\Framework\MockObject\MockObject {
        $service = $this->createMock(tutor_service::class);
        $service->expects($this->never())->method('submit');
        $service->expects($this->never())->method('add_message');
        service_factory::set_test_tutor_service($service);
        return $service;
    }







    /**
     * The teach external forwards the scope with the activity types the tutor never offers as topics.
     */
    public function test_teach_lesson_passes_the_topic_exclusions(): void {
        [$course, $student] = $this->course_and_student();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);

        $service = $this->createMock(teach_lesson_service::class);
        $service->expects($this->once())
            ->method('submit_from_setup')
            ->with(
                (int) $course->id,
                'activity',
                2,
                (int) $page->cmid,
                'Topic',
                'Teach me',
                '',
                $this->callback(fn(array $excluded): bool => in_array('quiz', $excluded, true)
                    && in_array('h5pactivity', $excluded, true) && !in_array('page', $excluded, true))
            )
            ->willReturn(operation_result::pending(self::JOB_ID));
        service_factory::set_test_teach_lesson_service($service);

        $this->setUser($student);
        $result = submit_teach_lesson::execute((int) $course->id, 'activity', 2, (int) $page->cmid, 'Topic', 'Teach me');
        $this->assertSame(self::JOB_ID, $result['jobid']);
    }

    /**
     * The quiz external forwards the scope with the same topic exclusions.
     */
    public function test_practice_quiz_passes_the_topic_exclusions(): void {
        [$course, $student] = $this->course_and_student();

        $service = $this->createMock(practice_quiz_service::class);
        $service->expects($this->once())
            ->method('submit_from_setup')
            ->with(
                (int) $course->id,
                'section',
                1,
                0,
                5,
                'medium',
                'Topic',
                '',
                $this->callback(fn(array $excluded): bool => in_array('quiz', $excluded, true))
            )
            ->willReturn(operation_result::pending(self::JOB_ID));
        service_factory::set_test_practice_quiz_service($service);

        $this->setUser($student);
        $result = submit_practice_quiz::execute((int) $course->id, 'section', 1, 0, 5, 'medium', 'Topic');
        $this->assertSame(self::JOB_ID, $result['jobid']);
    }

    /**
     * A hidden activity is refused by the generation service before any remote call.
     */
    public function test_hidden_activity_is_refused_end_to_end(): void {
        [$course, $student] = $this->course_and_student();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        set_coursemodule_visible($page->cmid, 0);
        $client = $this->createMock(\local_dixeo\api\client::class);
        $client->expects($this->never())->method('post');
        service_factory::set_test_client($client);

        $this->setUser($student);
        $this->assert_refused(
            fn() => submit_teach_lesson::execute((int) $course->id, 'activity', 0, (int) $page->cmid, 'Topic', 'Teach me'),
            'error:scope_unavailable'
        );
    }

    public function test_oversized_learner_request_and_topic_are_refused(): void {
        [$course, $student] = $this->course_and_student();
        $this->forbid_teach_generation();
        $this->forbid_quiz_generation();

        $this->setUser($student);
        $calls = [
            fn() => submit_teach_lesson::execute((int) $course->id, 'course', 0, 0, 'Topic', str_repeat('a', 2001)),
            fn() => submit_teach_lesson::execute((int) $course->id, 'course', 0, 0, str_repeat('t', 256), 'Teach me'),
            fn() => submit_practice_quiz::execute((int) $course->id, 'course', 0, 0, 5, 'medium', str_repeat('t', 256)),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('An oversized field was accepted');
            } catch (\invalid_parameter_exception $e) {
                $this->assertSame('invalidparameter', $e->errorcode);
            }
        }
    }

    public function test_tutor_off_for_the_course_blocks_messages_and_guide_sessions(): void {
        [$course, $student] = $this->course_and_student();
        $this->setAdminUser();
        service_factory::get_settings_config()->set(
            'block_dixeo_tutor',
            'tutor_enabled',
            \context_course::instance((int) $course->id),
            '0'
        );
        $this->forbid_tutor_calls();

        $this->setUser($student);
        $this->assert_refused(fn() => send_message::execute((int) $course->id, 'Hello'), 'disabled_tutor_course');
        $this->assert_refused(fn() => start_guide_session::execute((int) $course->id, 'Help me'), 'disabled_tutor_course');
    }

    public function test_dixeo_off_for_the_course_blocks_messages_and_guide_sessions(): void {
        [$course, $student] = $this->course_and_student();
        $this->setAdminUser();
        service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance((int) $course->id),
            '0'
        );
        $this->forbid_tutor_calls();

        $this->setUser($student);
        $this->assert_refused(fn() => send_message::execute((int) $course->id, 'Hello'), 'error:dixeo_disabled');
        $this->assert_refused(fn() => start_guide_session::execute((int) $course->id, 'Help me'), 'error:dixeo_disabled');
    }

    public function test_conversation_can_still_be_deleted_when_the_tutor_is_off(): void {
        [$course, $student] = $this->course_and_student();
        $this->setAdminUser();
        service_factory::get_settings_config()->set(
            'block_dixeo_tutor',
            'tutor_enabled',
            \context_course::instance((int) $course->id),
            '0'
        );
        $service = $this->createMock(tutor_service::class);
        $service->expects($this->once())->method('delete_conversations')->willReturn(1);
        service_factory::set_test_tutor_service($service);

        $this->setUser($student);
        $this->assertSame(['deleted' => 1], delete_conversation::execute((int) $course->id));
    }

    public function test_messages_need_the_current_terms_of_use(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->forbid_tutor_calls();

        $this->setUser($student);
        $this->assert_refused(fn() => send_message::execute((int) $course->id, 'Hello'), 'consentrequired');
        $this->assert_refused(fn() => start_guide_session::execute((int) $course->id, 'Help me'), 'consentrequired');
    }

    public function test_quiz_grade_of_a_learner_without_terms_queues_no_notice(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $gradeitem = new \grade_item([
            'courseid' => $course->id,
            'categoryid' => \grade_category::fetch_course_category($course->id)->id,
            'itemname' => 'Quiz 1',
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => 100,
        ], false);
        $gradeitem->insert();
        $grade = new \grade_grade([
            'itemid' => $gradeitem->id,
            'userid' => $student->id,
            'rawgrade' => 80,
            'finalgrade' => 80,
        ], false);
        $grade->insert();

        (new tutor_proactive_context_service())->handle_user_graded(\core\event\user_graded::create_from_grade($grade));

        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(post_notice::class));
    }

    public function test_queued_notice_is_not_sent_once_the_terms_are_withdrawn(): void {
        [$course, $student] = $this->course_and_student();
        post_notice::queue((int) $student->id, (int) $course->id, ['type' => 'course_completed', 'time' => time()]);
        (new consent_service())->withdraw_terms((int) $student->id);
        $this->forbid_tutor_calls();

        $tasks = \core\task\manager::get_adhoc_tasks(post_notice::class);
        $this->assertCount(1, $tasks);
        reset($tasks)->execute();
    }

    public function test_queued_notice_is_not_sent_once_the_tutor_is_off(): void {
        [$course, $student] = $this->course_and_student();
        post_notice::queue((int) $student->id, (int) $course->id, ['type' => 'course_completed', 'time' => time()]);
        $this->setAdminUser();
        service_factory::get_settings_config()->set(
            'block_dixeo_tutor',
            'tutor_enabled',
            \context_course::instance((int) $course->id),
            '0'
        );
        $this->forbid_tutor_calls();

        $tasks = \core\task\manager::get_adhoc_tasks(post_notice::class);
        reset($tasks)->execute();
    }

    public function test_background_sends_require_the_capability(): void {
        [$course, $student] = $this->course_and_student();
        $this->assertTrue(tutor_access::may_send_for((int) $student->id, (int) $course->id));

        assign_capability(
            'block/dixeo_tutor:talktotutor',
            CAP_PROHIBIT,
            $this->get_student_roleid(),
            \context_course::instance((int) $course->id)->id,
            true
        );
        $this->assertFalse(tutor_access::may_send_for((int) $student->id, (int) $course->id));
        $this->assertFalse(tutor_access::may_send_for((int) $student->id, 999999));
    }

    /**
     * Id of the student role.
     *
     * @return int
     */
    private function get_student_roleid(): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
    }
}
