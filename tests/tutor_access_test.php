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
use local_dixeo\external\service_factory;
use local_dixeo\service\consent_service;
use local_dixeo\service\tutor_service;

/**
 * Web services, observers and tasks enforce what the block UI shows.
 *
 * @covers \block_dixeo_tutor\tutor_access
 * @covers \block_dixeo_tutor\external\send_message
 * @covers \block_dixeo_tutor\external\start_guide_session
 */
final class tutor_access_test extends \advanced_testcase {
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
}
