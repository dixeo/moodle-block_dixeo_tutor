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
 * Tests for {@see \block_dixeo_tutor\service\tutor_proactive_context_service}.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_context_schema;
use block_dixeo_tutor\service\tutor_mode_service;
use block_dixeo_tutor\service\tutor_proactive_context_service;
use local_dixeo\dto\operation_result;
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * Tests for proactive context service.
 *
 * @covers \block_dixeo_tutor\service\tutor_proactive_context_service
 */
final class tutor_proactive_context_service_test extends \advanced_testcase {
    /** @var tutor_proactive_context_service */
    private tutor_proactive_context_service $service;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        block_load_class('dixeo_tutor');
        $this->service = new tutor_proactive_context_service();
        service_factory::reset();
    }

    public function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Fetch the pending DB row for a user and course.
     *
     * @param int $userid
     * @param int $courseid
     * @return ?\stdClass
     */
    private function get_pending_record(int $userid, int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record(tutor_proactive_context_service::TABLE, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]) ?: null;
    }

    /**
     * Decode pending proactive events from storage.
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    private function decode_pending_events(int $userid, int $courseid): array {
        $record = $this->get_pending_record($userid, $courseid);
        if ($record === null) {
            return [];
        }
        return $this->service->decode_queue((string) ($record->message ?? ''));
    }

    public function test_course_view_queues_nothing_for_the_api(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        course_view(\context_course::instance((int) $course->id));

        $this->assertSame([], $this->decode_pending_events((int) $user->id, (int) $course->id));
    }

    public function test_flush_clears_message_and_submits(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $events = [['type' => 'guide_started', 'time' => $now]];
        $DB->insert_record(tutor_proactive_context_service::TABLE, (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'message' => json_encode($events),
            'timemodified' => $now,
        ]);

        $mock = $this->getMockBuilder(tutor_service::class)
            ->onlyMethods(['submit'])
            ->getMock();
        $mock->expects($this->once())
            ->method('submit')
            ->with(
                (int) $course->id,
                (int) $user->id,
                $this->callback(function (\local_dixeo\dto\tutor_message $msg) use ($events, $user, $course): bool {
                    return $msg->role === \local_dixeo\dto\tutor_message::ROLE_SYSTEM
                        && ($msg->context['schema'] ?? '') === tutor_context_schema::SCHEMA_PROACTIVE
                        && ($msg->context['version'] ?? null) === 1
                        && ($msg->context['events'] ?? null) === $events
                        && ($msg->context['userid'] ?? null) === (int) $user->id
                        && ($msg->context['courseid'] ?? null) === (int) $course->id
                        && $msg->message === ''
                        && ($msg->instructions === null || trim((string) $msg->instructions) === '')
                        && $msg->requireresponse === true;
                }),
                $this->anything()
            )
            ->willReturn(operation_result::pending('test-job-id', 'pending', 0));
        service_factory::set_test_tutor_service($mock);

        $result = $this->service->flush((int) $user->id, (int) $course->id);
        $this->assertNotNull($result);

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertSame('', trim((string) $record->message));
    }

    public function test_flush_sends_only_the_guide_opening_turn(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $notice = ['type' => 'course_completed', 'time' => time()];
        $DB->insert_record(tutor_proactive_context_service::TABLE, (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'message' => json_encode([$notice]),
            'timemodified' => time(),
        ]);

        $mock = $this->getMockBuilder(tutor_service::class)
            ->onlyMethods(['submit'])
            ->getMock();
        $mock->expects($this->never())->method('submit');
        service_factory::set_test_tutor_service($mock);

        $this->assertNull($this->service->flush((int) $user->id, (int) $course->id));
        $this->assertSame([$notice], $this->decode_pending_events((int) $user->id, (int) $course->id));
    }

    public function test_take_notices_keeps_the_guide_opening_turn(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $guide = ['type' => 'guide_started', 'time' => 1];
        $quiz = ['type' => 'quiz_graded', 'time' => 2, 'quizname' => 'Q', 'grade' => 5, 'maxgrade' => 10];
        $completed = ['type' => 'course_completed', 'time' => 3];
        $DB->insert_record(tutor_proactive_context_service::TABLE, (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'message' => json_encode([$guide, $quiz, $completed]),
            'timemodified' => time(),
        ]);

        $this->assertSame([$quiz, $completed], $this->service->take_notices((int) $user->id, (int) $course->id));
        $this->assertSame([$guide], $this->decode_pending_events((int) $user->id, (int) $course->id));
        $this->assertSame([], $this->service->take_notices((int) $user->id, (int) $course->id));
    }

    public function test_quiz_graded_queues_a_notice_without_calling_the_api(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $gradecategory = \grade_category::fetch_course_category($course->id);
        $gradeitem = new \grade_item([
            'courseid' => $course->id,
            'categoryid' => $gradecategory->id,
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
            'userid' => $user->id,
            'rawgrade' => 80,
            'finalgrade' => 80,
        ], false);
        $grade->insert();

        $mock = $this->getMockBuilder(tutor_service::class)
            ->onlyMethods(['submit'])
            ->getMock();
        $mock->expects($this->never())->method('submit');
        service_factory::set_test_tutor_service($mock);

        $event = \core\event\user_graded::create_from_grade($grade);
        $this->service->handle_user_graded($event);

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertNotNull($record);
        $events = $this->decode_pending_events((int) $user->id, (int) $course->id);
        $this->assertCount(1, $events);
        $this->assertSame('quiz_graded', $events[0]['type']);
        $this->assertSame('Quiz 1', $events[0]['quizname']);
        $this->assertEquals(80, $events[0]['grade']);
        $this->assertEquals(100, $events[0]['maxgrade']);
    }

    public function test_queue_guide_started_appends_once(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->service->queue_guide_started((int) $user->id, (int) $course->id, 'Help with algebra');
        $this->service->queue_guide_started((int) $user->id, (int) $course->id, 'Duplicate');

        $events = $this->decode_pending_events((int) $user->id, (int) $course->id);
        $this->assertCount(1, $events);
        $this->assertSame('guide_started', $events[0]['type']);
        $this->assertSame('Help with algebra', $events[0]['userPrompt']);
    }

    public function test_flush_guide_started_submits_in_guide_mode(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);

        (new tutor_mode_service())->set_mode((int) $user->id, (int) $course->id, tutor_message::MODE_GUIDE);
        $this->service->queue_guide_started((int) $user->id, (int) $course->id, 'Explain mitosis');

        $mock = $this->getMockBuilder(tutor_service::class)
            ->onlyMethods(['submit'])
            ->getMock();
        $mock->expects($this->once())
            ->method('submit')
            ->with(
                (int) $course->id,
                (int) $user->id,
                $this->callback(function (tutor_message $msg): bool {
                    return $msg->role === tutor_message::ROLE_SYSTEM
                        && ($msg->context['schema'] ?? '') === tutor_context_schema::SCHEMA_PROACTIVE
                        && $msg->requireresponse === true
                        && ($msg->instructions === null || trim((string) $msg->instructions) === '')
                        && ($msg->context['events'][0]['type'] ?? '') === 'guide_started'
                        && ($msg->context['events'][0]['userPrompt'] ?? '') === 'Explain mitosis';
                }),
                tutor_message::MODE_GUIDE,
                $this->anything()
            )
            ->willReturn(operation_result::pending('guide-job-id', 'pending', 0));
        service_factory::set_test_tutor_service($mock);

        $result = $this->service->flush((int) $user->id, (int) $course->id);
        $this->assertNotNull($result);
        $this->assertSame('', trim((string) $this->get_pending_record((int) $user->id, (int) $course->id)->message));
    }

    public function test_user_graded_ignores_assign_module(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $gradecategory = \grade_category::fetch_course_category($course->id);
        $gradeitem = new \grade_item([
            'courseid' => $course->id,
            'categoryid' => $gradecategory->id,
            'itemname' => 'Assign',
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => 100,
        ], false);
        $gradeitem->insert();

        $grade = new \grade_grade([
            'itemid' => $gradeitem->id,
            'userid' => $user->id,
            'rawgrade' => 80,
            'finalgrade' => 80,
        ], false);
        $grade->insert();

        $event = \core\event\user_graded::create_from_grade($grade);
        $this->service->handle_user_graded($event);

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertNull($record);
    }

    public function test_course_completed_queues_a_notice_without_calling_the_api(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $mock = $this->getMockBuilder(tutor_service::class)
            ->onlyMethods(['submit'])
            ->getMock();
        $mock->expects($this->never())->method('submit');
        service_factory::set_test_tutor_service($mock);

        $context = \context_course::instance((int) $course->id);
        $event = \core\event\course_completed::create([
            'objectid' => 1,
            'relateduserid' => $user->id,
            'context' => $context,
            'courseid' => $course->id,
            'other' => ['relateduserid' => $user->id],
        ]);
        $this->service->handle_course_completed($event);

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertNotNull($record);
        $events = $this->decode_pending_events((int) $user->id, (int) $course->id);
        $this->assertCount(1, $events);
        $this->assertSame('course_completed', $events[0]['type']);
    }
}
