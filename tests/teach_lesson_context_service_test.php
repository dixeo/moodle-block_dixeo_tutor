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
 * Tests for {@see \block_dixeo_tutor\service\teach_lesson_context_service}.
 *
 * @package    block_dixeo_tutor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_context_schema;
use block_dixeo_tutor\service\teach_lesson_context_service;
use local_dixeo\dto\operation_result;
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * Tests for teach lesson context service.
 *
 * @covers \block_dixeo_tutor\service\teach_lesson_context_service
 */
final class teach_lesson_context_service_test extends \advanced_testcase {
    /** @var teach_lesson_context_service */
    private teach_lesson_context_service $service;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        block_load_class('dixeo_tutor');
        $this->service = new teach_lesson_context_service();
        service_factory::reset();
    }

    public function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    public function test_build_lesson_context_shape(): void {
        $context = $this->service->build_lesson_context([
            'title' => 'Photosynthesis',
            'introhtml' => '<p>Intro</p>',
            'contenthtml' => '<p>Main lesson body</p>',
        ]);

        $this->assertNotNull($context);
        $this->assertSame(tutor_context_schema::SCHEMA_CUSTOM_LESSON, $context['schema']);
        $this->assertSame(1, $context['version']);
        $this->assertSame('Photosynthesis', $context['title']);
        $this->assertSame('<p>Intro</p>', $context['introhtml']);
        $this->assertSame('<p>Main lesson body</p>', $context['contenthtml']);
    }

    public function test_build_lesson_context_requires_content(): void {
        $this->assertNull($this->service->build_lesson_context([
            'title' => 'Empty',
            'introhtml' => '<p>Intro only</p>',
            'contenthtml' => '',
        ]));
    }

    public function test_build_lesson_context_strips_intro_when_too_large(): void {
        $large = str_repeat('x', 20000);
        $context = $this->service->build_lesson_context([
            'title' => 'Big lesson',
            'introhtml' => $large,
            'contenthtml' => '<p>Core</p>',
        ]);

        $this->assertNotNull($context);
        $this->assertSame('', $context['introhtml']);
        $this->assertSame('<p>Core</p>', $context['contenthtml']);
    }

    public function test_submit_lesson_uses_teach_mode_and_no_reply(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

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
                        && ($msg->context['schema'] ?? '') === tutor_context_schema::SCHEMA_CUSTOM_LESSON
                        && $msg->message === 'My lesson'
                        && $msg->instructions === null
                        && $msg->requireresponse === false;
                }),
                tutor_message::MODE_TEACH
            )
            ->willReturn(operation_result::pending('lesson-job-id', 'pending', 0));
        service_factory::set_test_tutor_service($mock);

        $result = $this->service->submit_lesson((int) $course->id, (int) $user->id, [
            'title' => 'My lesson',
            'introhtml' => '',
            'contenthtml' => '<p>Body</p>',
        ]);

        $this->assertNotNull($result);
        $this->assertSame('lesson-job-id', $result->jobid);
    }
}
