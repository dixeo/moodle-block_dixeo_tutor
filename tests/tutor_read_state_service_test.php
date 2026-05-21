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

    public function test_resolve_page_state_bootstraps_unset_preference_as_read(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->mock_conversation([
            ['id' => '1', 'role' => 'assistant', 'content' => 'Hello', 'time' => 2000],
        ]);

        $state = $this->service->resolve_page_state((int) $user->id, (int) $course->id);

        $this->assertFalse($state['hasunread']);
        $this->assertSame(2001, $state['lastread']);
        $this->assertSame(2001, $this->service->get_last_read((int) $user->id, (int) $course->id));
    }

    public function test_resolve_page_state_has_unread_when_latest_incoming_is_newer(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->mock_conversation([
            ['id' => '1', 'role' => 'assistant', 'content' => 'Hello', 'time' => 3000],
        ]);

        $this->service->set_last_read((int) $user->id, (int) $course->id, 1000);

        $state = $this->service->resolve_page_state((int) $user->id, (int) $course->id);

        $this->assertTrue($state['hasunread']);
        $this->assertSame(1000, $state['lastread']);
    }

    public function test_resolve_page_state_no_unread_when_up_to_date(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->mock_conversation([
            ['id' => '1', 'role' => 'assistant', 'content' => 'Hello', 'time' => 2000],
        ]);

        $this->service->set_last_read((int) $user->id, (int) $course->id, 2001);

        $state = $this->service->resolve_page_state((int) $user->id, (int) $course->id);

        $this->assertFalse($state['hasunread']);
    }

    public function test_mark_all_read_updates_preference_to_latest_incoming(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->mock_conversation([
            ['id' => '1', 'role' => 'assistant', 'content' => 'Hello', 'time' => 4000],
        ]);

        $this->service->set_last_read((int) $user->id, (int) $course->id, 1000);
        $stored = $this->service->mark_all_read((int) $user->id, (int) $course->id);

        $this->assertSame(4001, $stored);
        $state = $this->service->resolve_page_state((int) $user->id, (int) $course->id);
        $this->assertFalse($state['hasunread']);
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
            ['id' => '1', 'role' => 'assistant', 'content' => 'Hello', 'time' => 3500],
        ]);

        $this->service->set_last_read((int) $user->id, (int) $course->id, 1000);
        $stored = $this->service->mark_read_up_to((int) $user->id, (int) $course->id, 0);

        $this->assertSame(3501, $stored);
        $state = $this->service->resolve_page_state((int) $user->id, (int) $course->id);
        $this->assertFalse($state['hasunread']);
    }

    public function test_user_messages_are_never_treated_as_unread(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->mock_conversation([
            ['id' => '1', 'role' => 'assistant', 'content' => 'Hello', 'time' => 2000],
            ['id' => '2', 'role' => 'user', 'content' => 'My reply', 'time' => 9000],
        ]);

        $this->service->set_last_read((int) $user->id, (int) $course->id, 2001);

        $state = $this->service->resolve_page_state((int) $user->id, (int) $course->id);

        $this->assertFalse($state['hasunread']);
    }

    public function test_proactive_context_messages_are_not_counted_as_incoming(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->mock_conversation([
            [
                'id' => '1',
                'role' => 'assistant',
                'content' => '<proactive-context source="system">ctx</proactive-context>',
                'time' => 5000,
            ],
        ]);

        $state = $this->service->resolve_page_state((int) $user->id, (int) $course->id);

        $this->assertFalse($state['hasunread']);
        $this->assertSame(0, $state['lastread']);
    }
}
