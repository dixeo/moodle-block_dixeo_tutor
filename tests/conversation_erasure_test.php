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
 * Tests for the erasure task kept for earlier queues and for the local clean-up on deletion.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\privacy\provider;
use block_dixeo_tutor\service\tutor_proactive_context_service;
use block_dixeo_tutor\task\erase_conversations;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * Earlier queued erasures still run, and deleting a course or user removes this block's local rows.
 *
 * @covers \block_dixeo_tutor\task\erase_conversations
 * @covers \block_dixeo_tutor\observer\conversation_cleanup_observer
 */
final class conversation_erasure_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // A configured site, so a queued erasure can run.
        set_config('api_key', 'phpunit-api-key', 'local_dixeo');
    }

    protected function tearDown(): void {
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
     * Run a task, keeping its mtrace output out of the test output.
     *
     * @param erase_conversations $task The task to run.
     * @return string The trace it produced.
     */
    private function execute_task(erase_conversations $task): string {
        ob_start();
        try {
            $task->execute();
        } finally {
            $trace = ob_get_clean();
        }

        return $trace;
    }

    /**
     * Build a task carrying the given scope.
     *
     * @param int|null $courseid The course to erase, or null for every course.
     * @param int|null $userid The user to erase, or null for every user.
     * @return erase_conversations The task.
     */
    private function task_for(?int $courseid, ?int $userid): erase_conversations {
        $task = new erase_conversations();
        $task->set_custom_data((object) ['courseid' => $courseid, 'userid' => $userid]);

        return $task;
    }

    /**
     * A task queued under this block's name runs the shared erasure.
     */
    public function test_execute_erases_the_queued_scope(): void {
        $this->mock_tutor_service()
            ->expects($this->once())
            ->method('delete_conversations')
            ->with(42, 7)
            ->willReturn(3);

        $trace = $this->execute_task($this->task_for(42, 7));

        $this->assertStringContainsString('erased 3 conversation(s)', $trace);
    }

    /**
     * The task keeps this block's name.
     */
    public function test_the_task_is_named_from_a_language_string(): void {
        $this->assertInstanceOf(\local_dixeo\task\erase_tutor_conversations::class, new erase_conversations());
        $this->assertSame(
            get_string('task_erase_conversations', 'block_dixeo_tutor'),
            (new erase_conversations())->get_name()
        );
        $this->assertStringNotContainsString('[[', (new erase_conversations())->get_name());
    }

    /**
     * Deleting a course or a user leaves the remote erasure to local_dixeo and queues nothing under this block.
     */
    public function test_deletion_queues_no_block_task(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->mock_tutor_service()->expects($this->never())->method('delete_conversations');

        delete_course($course, false);
        delete_user($user);

        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(erase_conversations::class));
    }

    /**
     * Deleting a course removes its queued prompts and course preferences.
     */
    public function test_course_deletion_removes_local_rows_and_preferences(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $DB->insert_record(tutor_proactive_context_service::TABLE, (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'message' => '[{"type":"guide_started","time":1}]',
            'timemodified' => time(),
        ]);
        foreach (array_keys(provider::COURSE_PREFERENCES) as $prefix) {
            set_user_preference($prefix . $course->id, '1', $user->id);
        }

        delete_course($course, false);

        $this->assertFalse($DB->record_exists(tutor_proactive_context_service::TABLE, ['courseid' => $course->id]));
        foreach (array_keys(provider::COURSE_PREFERENCES) as $prefix) {
            $this->assertFalse($DB->record_exists('user_preferences', ['name' => $prefix . $course->id]));
        }
    }

    /**
     * Deleting a user removes their queued prompts.
     */
    public function test_user_deletion_removes_local_rows(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $DB->insert_record(tutor_proactive_context_service::TABLE, (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'message' => '[{"type":"guide_started","time":1}]',
            'timemodified' => time(),
        ]);

        delete_user($user);

        $this->assertFalse($DB->record_exists(tutor_proactive_context_service::TABLE, ['userid' => $user->id]));
    }
}
