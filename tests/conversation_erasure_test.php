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
 * Tests for the durable erasure of tutor conversations (DIXEO-TUTOR-PRIV-001).
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\task\erase_conversations;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * An erasure Moodle cannot retry is an erasure that may never happen.
 *
 * @covers \block_dixeo_tutor\task\erase_conversations
 * @covers \block_dixeo_tutor\observer\conversation_cleanup_observer
 */
final class conversation_erasure_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Queueing is inert on an unconfigured site, so every other test needs a key.
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

    public function test_execute_erases_the_queued_scope(): void {
        $this->mock_tutor_service()
            ->expects($this->once())
            ->method('delete_conversations')
            ->with(42, 7)
            ->willReturn(3);

        $trace = $this->execute_task($this->task_for(42, 7));

        $this->assertStringContainsString('erased 3 conversation(s)', $trace);
    }

    public function test_execute_erases_a_whole_course_when_no_user_is_queued(): void {
        $this->mock_tutor_service()
            ->expects($this->once())
            ->method('delete_conversations')
            ->with(42, null)
            ->willReturn(9);

        $this->execute_task($this->task_for(42, null));
    }

    public function test_execute_erases_a_user_everywhere_when_no_course_is_queued(): void {
        $this->mock_tutor_service()
            ->expects($this->once())
            ->method('delete_conversations')
            ->with(null, 7)
            ->willReturn(2);

        $this->execute_task($this->task_for(null, 7));
    }

    public function test_execute_lets_api_failures_propagate_so_the_runner_retries(): void {
        $this->mock_tutor_service()
            ->method('delete_conversations')
            ->willThrowException(new api_exception('connection_error', 'Failed to connect to Dixeo API', 0));

        $this->expectException(api_exception::class);
        $this->execute_task($this->task_for(42, 7));
    }

    public function test_queue_stores_the_scope_as_custom_data(): void {
        erase_conversations::queue(42, 7);

        $tasks = \core\task\manager::get_adhoc_tasks(erase_conversations::class);
        $this->assertCount(1, $tasks);

        $task = reset($tasks);
        $data = $task->get_custom_data();
        $this->assertSame(42, (int) $data->courseid);
        $this->assertSame(7, (int) $data->userid);

        // The default budget of 12 attempts gives up after ~2.4 days of outage.
        $this->assertSame(30, $task->get_attempts_available());
    }

    public function test_queue_is_inert_without_an_api_key(): void {
        set_config('api_key', '', 'local_dixeo');

        erase_conversations::queue(42, 7);
        erase_conversations::queue(42, null);

        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(erase_conversations::class));
    }

    public function test_course_deletion_queues_nothing_without_an_api_key(): void {
        $this->setAdminUser();
        set_config('api_key', '', 'local_dixeo');
        $course = $this->getDataGenerator()->create_course();

        delete_course($course, false);

        // Otherwise every course deletion on an unpaired site leaves a task that can
        // only fail its way through the whole retry budget.
        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(erase_conversations::class));
    }

    public function test_queue_does_not_pile_up_duplicates_for_the_same_scope(): void {
        erase_conversations::queue(42, null);
        erase_conversations::queue(42, null);
        erase_conversations::queue(42, 7);

        $this->assertCount(2, \core\task\manager::get_adhoc_tasks(erase_conversations::class));
    }

    public function test_the_task_is_named_from_a_language_string(): void {
        $this->assertSame(
            get_string('task_erase_conversations', 'block_dixeo_tutor'),
            (new erase_conversations())->get_name()
        );
        $this->assertStringNotContainsString('[[', (new erase_conversations())->get_name());
    }

    public function test_course_deletion_queues_the_erasure_of_the_whole_course(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        // Course deletion must not wait on the API: the observer only queues.
        $this->mock_tutor_service()->expects($this->never())->method('delete_conversations');

        delete_course($course, false);

        $tasks = \core\task\manager::get_adhoc_tasks(erase_conversations::class);
        $this->assertCount(1, $tasks);

        $data = reset($tasks)->get_custom_data();
        $this->assertSame((int) $course->id, (int) $data->courseid);
        $this->assertNull($data->userid);
    }

    public function test_user_deletion_queues_the_erasure_of_that_user_everywhere(): void {
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();

        // User deletion must not wait on the API: the observer only queues.
        $this->mock_tutor_service()->expects($this->never())->method('delete_conversations');

        delete_user($user);

        $tasks = \core\task\manager::get_adhoc_tasks(erase_conversations::class);
        $this->assertCount(1, $tasks);

        $data = reset($tasks)->get_custom_data();
        $this->assertNull($data->courseid);
        $this->assertSame((int) $user->id, (int) $data->userid);
    }

    public function test_user_deletion_queues_nothing_without_an_api_key(): void {
        $this->setAdminUser();
        set_config('api_key', '', 'local_dixeo');
        $user = $this->getDataGenerator()->create_user();

        delete_user($user);

        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(erase_conversations::class));
    }
}
