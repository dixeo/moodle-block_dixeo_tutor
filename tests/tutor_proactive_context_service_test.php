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

use block_dixeo_tutor\service\tutor_proactive_context_service;
use local_dixeo\dto\operation_result;
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
     * Create a course_viewed event for tests.
     *
     * @param int $courseid
     * @param int $userid
     * @return \core\event\course_viewed
     */
    private function create_course_viewed_event(int $courseid, int $userid): \core\event\course_viewed {
        $this->setUser($userid);
        $context = \context_course::instance($courseid);
        return \core\event\course_viewed::create([
            'context' => $context,
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
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
     * Set last proactive course view timestamp.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $timestamp
     * @return void
     */
    private function set_last_proactive_course_view(int $userid, int $courseid, int $timestamp): void {
        set_user_preference(
            tutor_proactive_context_service::PREF_LAST_PROACTIVE_PREFIX . $courseid,
            $timestamp,
            $userid
        );
    }

    public function test_first_course_view_appends_welcome(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->service->handle_course_viewed($this->create_course_viewed_event((int) $course->id, (int) $user->id));

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertNotNull($record);
        $this->assertStringNotContainsString('<proactive-context', $record->message);
        $this->assertStringContainsString($user->firstname, $record->message);
        $this->assertStringContainsString('first time opening this course', $record->message);
    }

    public function test_second_view_within_24h_does_not_append_return_line(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->service->handle_course_viewed($this->create_course_viewed_event((int) $course->id, (int) $user->id));

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertNotNull($record);
        $firstmessage = $record->message;

        // First view already set the preference to now; no second line within 24h.

        $this->service->handle_course_viewed($this->create_course_viewed_event((int) $course->id, (int) $user->id));

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertEquals($firstmessage, $record->message);
        $this->assertStringNotContainsString('returning to this course', $record->message);
    }

    public function test_return_after_24h_appends_welcome_back_line(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->service->handle_course_viewed($this->create_course_viewed_event((int) $course->id, (int) $user->id));

        $this->set_last_proactive_course_view((int) $user->id, (int) $course->id, time() - (25 * 3600));

        $this->service->handle_course_viewed($this->create_course_viewed_event((int) $course->id, (int) $user->id));

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertStringContainsString('returning to this course', $record->message);
        $this->assertStringContainsString('Welcome me back', $record->message);
    }

    public function test_flush_clears_message_and_submits(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $DB->insert_record(tutor_proactive_context_service::TABLE, (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'message' => 'Line one',
            'timemodified' => $now,
        ]);

        $mock = $this->getMockBuilder(tutor_service::class)
            ->onlyMethods(['submit_message'])
            ->getMock();
        $expectedpayload = '<proactive-context source="system">' . "\n"
            . 'Line one' . "\n"
            . '</proactive-context>';
        $mock->expects($this->once())
            ->method('submit_message')
            ->with((int) $course->id, (int) $user->id, $expectedpayload, '')
            ->willReturn(operation_result::pending('test-job-id', 'pending', 0));
        service_factory::set_test_tutor_service($mock);

        $result = $this->service->flush((int) $user->id, (int) $course->id);
        $this->assertNotNull($result);

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertSame('', trim((string) $record->message));
    }

    public function test_should_flush_false_on_excluded_quiz_page(): void {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $this->setUser($user);
        $context = \context_module::instance($quiz->cmid);
        $PAGE->set_url(new \moodle_url('/mod/quiz/view.php', ['id' => $quiz->cmid]));
        $PAGE->set_course($course);
        $PAGE->set_context($context);
        $PAGE->set_pagetype('mod-quiz-view');
        $PAGE->blocks->load_blocks();

        $this->assertFalse($this->service->should_flush_immediately((int) $user->id, (int) $course->id));
        $this->assertFalse(\block_dixeo_tutor::is_tutor_available_on_page($PAGE, (int) $user->id));
    }

    public function test_quiz_graded_queues_without_flush_on_excluded_page(): void {
        global $CFG, $PAGE;

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

        $this->setUser($user);
        $context = \context_module::instance($quiz->cmid);
        $PAGE->set_url(new \moodle_url('/mod/quiz/view.php', ['id' => $quiz->cmid]));
        $PAGE->set_course($course);
        $PAGE->set_context($context);
        $PAGE->set_pagetype('mod-quiz-view');
        $PAGE->blocks->load_blocks();

        $mock = $this->getMockBuilder(tutor_service::class)
            ->onlyMethods(['submit_message'])
            ->getMock();
        $mock->expects($this->never())->method('submit_message');
        service_factory::set_test_tutor_service($mock);

        $event = \core\event\user_graded::create_from_grade($grade);
        $this->service->handle_user_graded($event);

        $record = $this->get_pending_record((int) $user->id, (int) $course->id);
        $this->assertNotNull($record);
        $this->assertStringNotContainsString('<proactive-context', $record->message);
        $this->assertStringContainsString('Quiz 1', $record->message);
        $this->assertStringContainsString('80', $record->message);
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
}
