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
 * Tests for tutor Moodle events (DIXEO-TUTOR-SEC-005).
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\event\conversation_viewed;
use block_dixeo_tutor\event\job_status_viewed;
use block_dixeo_tutor\event\message_sent;
use block_dixeo_tutor\external\get_conversation;
use block_dixeo_tutor\external\get_job_status;
use block_dixeo_tutor\external\send_message;
use local_dixeo\dto\job_status;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\service\job_service;
use local_dixeo\service\tutor_service;

/**
 * Sensitive tutor actions must emit audit events without message content in other.
 *
 * @covers \block_dixeo_tutor\event\message_sent
 * @covers \block_dixeo_tutor\event\conversation_viewed
 * @covers \block_dixeo_tutor\event\job_status_viewed
 * @covers \block_dixeo_tutor\external\send_message
 * @covers \block_dixeo_tutor\external\get_conversation
 * @covers \block_dixeo_tutor\external\get_job_status
 * @covers \block_dixeo_tutor\job_status_audit
 */
final class tutor_events_test extends \advanced_testcase {
    /** @var string Valid UUID for mocked tutor jobs. */
    private const JOB_ID = '5f38d9aa-f40c-4992-9727-982f050ff9fd';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Assert event other contains identifiers only (no AI payload).
     *
     * @param \core\event\base $event
     */
    private function assert_minimal_tutor_other(\core\event\base $event): void {
        $this->assertArrayNotHasKey('message', $event->other);
        $this->assertArrayNotHasKey('content', $event->other);
        $this->assertArrayNotHasKey('reply', $event->other);
        $this->assertArrayNotHasKey('messages', $event->other);
    }

    /**
     * Create a course with an enrolled student and set current user.
     *
     * @return array{0: \stdClass, 1: \stdClass}
     */
    private function create_course_and_student(): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);
        return [$course, $user];
    }

    public function test_send_message_emits_message_sent(): void {
        [$course, $user] = $this->create_course_and_student();

        $tutorservice = $this->createMock(tutor_service::class);
        $tutorservice->method('submit')->willReturn(
            operation_result::pending(self::JOB_ID)
        );
        service_factory::set_test_tutor_service($tutorservice);

        $sink = $this->redirectEvents();
        $result = send_message::execute((int) $course->id, 'Secret tutor prompt');

        $this->assertFalse($result['completed']);
        $this->assertSame(self::JOB_ID, $result['jobid']);

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof message_sent
        ));
        $this->assertCount(1, $events);
        $this->assertEquals((int) $course->id, (int) $events[0]->courseid);
        $this->assertEquals((int) $user->id, (int) $events[0]->userid);
        $this->assertSame(self::JOB_ID, $events[0]->other['jobid']);
        $this->assert_minimal_tutor_other($events[0]);
        $this->assertStringNotContainsString('Secret', $events[0]->get_description());
    }

    public function test_get_conversation_emits_conversation_viewed(): void {
        [$course, $user] = $this->create_course_and_student();

        $tutorservice = $this->createMock(tutor_service::class);
        $tutorservice->method('get_conversation')->willReturn([
            [
                'id' => 'msg-1',
                'role' => 'user',
                'content' => 'Secret conversation text',
                'time' => time(),
            ],
            [
                'id' => 'msg-2',
                'role' => 'assistant',
                'content' => 'Secret assistant reply',
                'time' => time(),
            ],
        ]);
        service_factory::set_test_tutor_service($tutorservice);

        $sink = $this->redirectEvents();
        $result = get_conversation::execute((int) $course->id, 'msg-0');

        $this->assertCount(2, $result['messages']);

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof conversation_viewed
        ));
        $this->assertCount(1, $events);
        $this->assertEquals((int) $course->id, (int) $events[0]->courseid);
        $this->assertEquals((int) $user->id, (int) $events[0]->userid);
        $this->assertSame(2, (int) $events[0]->other['messagecount']);
        $this->assertSame('msg-0', $events[0]->other['sinceid']);
        $this->assert_minimal_tutor_other($events[0]);
        $this->assertStringNotContainsString('Secret', $events[0]->get_description());
    }

    public function test_get_job_status_does_not_emit_job_status_viewed_on_poll(): void {
        [$course, $user] = $this->create_course_and_student();

        job_ownership::register((int) $user->id, (int) $course->id, self::JOB_ID);

        $status = new job_status(
            jobid: self::JOB_ID,
            type: 'tutor_message',
            status: job_status::STATUS_PROCESSING,
            progress: 50,
            createdat: time(),
            result: ['reply' => 'Secret assistant reply']
        );

        $jobservice = $this->createMock(job_service::class);
        $jobservice->method('get_job_status')->willReturn($status);
        service_factory::set_test_job_service($jobservice);

        $sink = $this->redirectEvents();
        $result = get_job_status::execute((int) $course->id, self::JOB_ID);

        $this->assertSame(self::JOB_ID, $result['jobid']);
        $this->assertSame(job_status::STATUS_PROCESSING, $result['status']);

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof job_status_viewed
        ));
        $this->assertCount(0, $events, 'Polling must not emit job_status_viewed on every request.');
    }

    public function test_get_job_status_emits_job_status_viewed_once_when_completed(): void {
        [$course, $user] = $this->create_course_and_student();

        job_ownership::register((int) $user->id, (int) $course->id, self::JOB_ID);

        $status = new job_status(
            jobid: self::JOB_ID,
            type: 'tutor_message',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: ['reply' => 'Secret assistant reply']
        );

        $jobservice = $this->createMock(job_service::class);
        $jobservice->method('get_job_status')->willReturn($status);
        service_factory::set_test_job_service($jobservice);

        $sink = $this->redirectEvents();
        $first = get_job_status::execute((int) $course->id, self::JOB_ID);
        $second = get_job_status::execute((int) $course->id, self::JOB_ID);

        $this->assertSame(job_status::STATUS_COMPLETED, $first['status']);
        $this->assertSame(job_status::STATUS_COMPLETED, $second['status']);

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof job_status_viewed
        ));
        $this->assertCount(1, $events);
        $this->assertEquals((int) $course->id, (int) $events[0]->courseid);
        $this->assertEquals((int) $user->id, (int) $events[0]->userid);
        $this->assertSame(self::JOB_ID, $events[0]->other['jobid']);
        $this->assertSame(job_status::STATUS_COMPLETED, $events[0]->other['status']);
        $this->assert_minimal_tutor_other($events[0]);
        $this->assertStringNotContainsString('Secret', $events[0]->get_description());
    }

    public function test_get_job_status_emits_job_status_viewed_for_failed_and_cancelled(): void {
        [$course, $user] = $this->create_course_and_student();

        job_ownership::register((int) $user->id, (int) $course->id, self::JOB_ID);

        foreach (['failed', 'cancelled'] as $terminalstatus) {
            global $SESSION;
            unset($SESSION->block_dixeo_tutor_terminal_status_audit);

            $status = new job_status(
                jobid: self::JOB_ID,
                type: 'tutor_message',
                status: $terminalstatus,
                progress: 0,
                createdat: time()
            );

            $jobservice = $this->createMock(job_service::class);
            $jobservice->method('get_job_status')->willReturn($status);
            service_factory::set_test_job_service($jobservice);

            $sink = $this->redirectEvents();
            $result = get_job_status::execute((int) $course->id, self::JOB_ID);

            $this->assertSame($terminalstatus, $result['status']);

            $events = array_values(array_filter(
                $sink->get_events(),
                static fn($event) => $event instanceof job_status_viewed
            ));
            $this->assertCount(1, $events, "Expected one event for status {$terminalstatus}");
            $this->assertSame($terminalstatus, $events[0]->other['status']);
            $this->assert_minimal_tutor_other($events[0]);
        }
    }

    public function test_job_status_viewed_event_has_minimal_other(): void {
        [$course, $user] = $this->create_course_and_student();

        $sink = $this->redirectEvents();
        job_status_viewed::create_for_course(
            (int) $course->id,
            (int) $user->id,
            self::JOB_ID,
            job_status::STATUS_COMPLETED
        )->trigger();

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof job_status_viewed
        ));
        $this->assertCount(1, $events);
        $this->assertEquals((int) $course->id, (int) $events[0]->courseid);
        $this->assertEquals((int) $user->id, (int) $events[0]->userid);
        $this->assertSame(self::JOB_ID, $events[0]->other['jobid']);
        $this->assertSame(job_status::STATUS_COMPLETED, $events[0]->other['status']);
        $this->assert_minimal_tutor_other($events[0]);
        $this->assertStringNotContainsString('Secret', $events[0]->get_description());
    }
}
