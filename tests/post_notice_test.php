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
 * Tests for the post_notice adhoc task.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_context_schema;
use block_dixeo_tutor\task\post_notice;
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * Quiz and completion notices stored in the tutor conversation.
 *
 * @covers \block_dixeo_tutor\task\post_notice
 */
final class post_notice_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('api_key', 'phpunit-api-key', 'local_dixeo');
    }

    public function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * The single queued post_notice task.
     *
     * @return post_notice
     */
    private function queued_task(): post_notice {
        $tasks = \core\task\manager::get_adhoc_tasks(post_notice::class);
        $this->assertCount(1, $tasks);
        return reset($tasks);
    }

    public function test_stores_the_notice_as_a_tutor_message_with_its_event(): void {
        $course = $this->getDataGenerator()->create_course(['shortname' => 'BIO101']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Cleo']);
        $event = ['type' => 'course_completed', 'time' => 1];
        post_notice::queue((int) $user->id, (int) $course->id, $event);
        $task = $this->queued_task();

        $mock = $this->getMockBuilder(tutor_service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['add_message'])
            ->getMock();
        $mock->expects($this->once())
            ->method('add_message')
            ->with(
                (int) $course->id,
                (int) $user->id,
                $this->callback(static function (tutor_message $message) use ($event): bool {
                    return $message->role === tutor_message::ROLE_ASSISTANT
                        && !$message->requireresponse
                        && str_contains($message->message, 'Cleo')
                        && $message->context['schema'] === tutor_context_schema::SCHEMA_PROACTIVE
                        && $message->context['events'] === [$event];
                }),
                $task->get_custom_data()->key
            );
        service_factory::set_test_tutor_service($mock);

        $task->execute();
    }

    public function test_stores_the_text_already_shown_as_is(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $event = ['type' => 'return_visit', 'time' => 1];
        post_notice::queue((int) $user->id, (int) $course->id, $event, 'Hey! Ready to keep going?');

        $mock = $this->getMockBuilder(tutor_service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['add_message'])
            ->getMock();
        $mock->expects($this->once())
            ->method('add_message')
            ->with(
                (int) $course->id,
                (int) $user->id,
                $this->callback(static fn(tutor_message $message): bool => $message->message === 'Hey! Ready to keep going?'),
                $this->anything()
            );
        service_factory::set_test_tutor_service($mock);

        $this->queued_task()->execute();
    }

    public function test_skips_a_deleted_learner(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        post_notice::queue((int) $user->id, (int) $course->id, ['type' => 'course_completed', 'time' => 1]);
        delete_user($user);

        $mock = $this->getMockBuilder(tutor_service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['add_message'])
            ->getMock();
        $mock->expects($this->never())->method('add_message');
        service_factory::set_test_tutor_service($mock);

        $this->queued_task()->execute();
    }
}
