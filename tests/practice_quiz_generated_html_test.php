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
 * Tests that the practice-quiz finalize external returns the generation service result.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\external\finalize_practice_quiz;
use block_dixeo_tutor\service\tutor_mode_policy;
use local_dixeo\external\service_factory;
use local_dixeo\service\consent_service;
use local_dixeo\service\practice_quiz_service;

/**
 * Generated questions are bounded and cleaned once by local_dixeo, then passed through as they are.
 *
 * @covers \block_dixeo_tutor\external\finalize_practice_quiz
 */
final class practice_quiz_generated_html_test extends \advanced_testcase {
    /** @var string Valid UUID for mocked jobs. */
    private const JOB_ID = '5f38d9aa-f40c-4992-9727-982f050ff9fd';

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
     * One generated question as local_dixeo returns it.
     *
     * @return array
     */
    private function question(): array {
        return [
            'text' => '<p>Question</p>',
            'correctfeedback' => 'Right',
            'partiallycorrectfeedback' => 'Partly',
            'incorrectfeedback' => 'Wrong',
            'answers' => [
                ['text' => 'Yes', 'iscorrect' => 1],
                ['text' => 'No', 'iscorrect' => 0],
            ],
        ];
    }

    /**
     * The external hands the service result to the browser unchanged; local_dixeo bounds and cleans it.
     */
    public function test_finalize_practice_quiz_returns_the_service_payload(): void {
        if (!tutor_mode_policy::is_quiz_runtime_available()) {
            $this->markTestSkipped('mod_simplequiz2 is not installed at a supported version.');
        }

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        (new consent_service())->accept((int) $student->id);

        $payload = [
            'success' => true,
            'error' => '',
            'title' => 'Quiz',
            'introhtml' => '<p>Intro</p>',
            'questions' => json_encode([$this->question(), $this->question()]),
        ];
        $service = $this->createMock(practice_quiz_service::class);
        $service->method('finalize_from_job')->willReturn($payload);
        service_factory::set_test_practice_quiz_service($service);

        $this->setUser($student);
        job_ownership::register((int) $student->id, (int) $course->id, self::JOB_ID);
        $result = finalize_practice_quiz::execute((int) $course->id, self::JOB_ID, 'Topic', 2);

        $this->assertSame($payload, $result);
    }
}
