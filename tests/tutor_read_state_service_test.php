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
 * Tests for {@see \block_dixeo_tutor\service\tutor_read_state_service}.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_read_state_service;
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * Tests for tutor read state service.
 *
 * @covers \block_dixeo_tutor\service\tutor_read_state_service
 */
final class tutor_read_state_service_test extends \advanced_testcase {
    /** @var tutor_read_state_service */
    private tutor_read_state_service $service;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->service = new tutor_read_state_service();
        service_factory::reset();
    }

    public function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Mock a tutor conversation for the test.
     *
     * @param array $messages
     */
    private function mock_conversation(array $messages): void {
        $mock = $this->getMockBuilder(tutor_service::class)
            ->onlyMethods(['get_conversation'])
            ->getMock();
        $mock->method('get_conversation')->willReturn($messages);
        service_factory::set_test_tutor_service($mock);
    }

    public function test_get_last_read_returns_zero_when_never_read(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->assertSame(0, $this->service->get_last_read((int) $user->id, (int) $course->id));
    }

    public function test_get_last_read_returns_stored_value(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->service->set_last_read((int) $user->id, (int) $course->id, 2001);

        $this->assertSame(2001, $this->service->get_last_read((int) $user->id, (int) $course->id));
    }

    public function test_latest_incoming_time_from_messages_uses_last_assistant(): void {
        $messages = [
            ['id' => '1', 'role' => tutor_message::ROLE_ASSISTANT, 'content' => 'Hello', 'time' => 2000],
            ['id' => '2', 'role' => tutor_message::ROLE_USER, 'content' => 'My reply', 'time' => 9000],
        ];

        $this->assertSame(2000, tutor_read_state_service::latest_incoming_time_from_messages($messages));
    }

    public function test_latest_incoming_time_from_messages_picks_newest_assistant(): void {
        $messages = [
            ['id' => '1', 'role' => tutor_message::ROLE_ASSISTANT, 'content' => 'First', 'time' => 2000],
            ['id' => '2', 'role' => tutor_message::ROLE_USER, 'content' => 'Reply', 'time' => 3000],
            ['id' => '3', 'role' => tutor_message::ROLE_ASSISTANT, 'content' => 'Second', 'time' => 4000],
        ];

        $this->assertSame(4000, tutor_read_state_service::latest_incoming_time_from_messages($messages));
    }

    public function test_latest_incoming_time_from_messages_ignores_system_messages(): void {
        $messages = [
            ['id' => '1', 'role' => tutor_message::ROLE_SYSTEM, 'content' => '', 'time' => 9000],
            ['id' => '2', 'role' => tutor_message::ROLE_ASSISTANT, 'content' => 'Hello', 'time' => 2000],
        ];

        $this->assertSame(2000, tutor_read_state_service::latest_incoming_time_from_messages($messages));
    }

    public function test_mark_all_read_updates_preference_to_latest_incoming(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->mock_conversation([
            ['id' => '1', 'role' => tutor_message::ROLE_ASSISTANT, 'content' => 'Hello', 'time' => 4000],
        ]);

        $this->service->set_last_read((int) $user->id, (int) $course->id, 1000);
        $stored = $this->service->mark_all_read((int) $user->id, (int) $course->id);

        $this->assertSame(4001, $stored);
        $this->assertSame(4001, $this->service->get_last_read((int) $user->id, (int) $course->id));
    }

    public function test_mark_read_up_to_uses_message_time_plus_one(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $stored = $this->service->mark_read_up_to((int) $user->id, (int) $course->id, 2500);

        $this->assertSame(2501, $stored);
    }

    public function test_mark_read_up_to_zero_falls_back_to_mark_all_read(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->mock_conversation([
            ['id' => '1', 'role' => tutor_message::ROLE_ASSISTANT, 'content' => 'Hello', 'time' => 3500],
        ]);

        $this->service->set_last_read((int) $user->id, (int) $course->id, 1000);
        $stored = $this->service->mark_read_up_to((int) $user->id, (int) $course->id, 0);

        $this->assertSame(3501, $stored);
        $this->assertSame(3501, $this->service->get_last_read((int) $user->id, (int) $course->id));
    }
}
