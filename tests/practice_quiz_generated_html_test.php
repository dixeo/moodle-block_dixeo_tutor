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
 * Tests that generated practice-quiz HTML is cleaned before it reaches the browser.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\external\finalize_practice_quiz;
use block_dixeo_tutor\service\practice_quiz_context_service;
use block_dixeo_tutor\service\tutor_mode_policy;
use local_dixeo\external\service_factory;
use local_dixeo\service\consent_service;
use local_dixeo\service\practice_quiz_service;

/**
 * Generated question, answer and feedback HTML is purified server-side.
 *
 * @covers \block_dixeo_tutor\service\practice_quiz_context_service::clean_generated_questions
 * @covers \block_dixeo_tutor\external\finalize_practice_quiz
 */
final class practice_quiz_generated_html_test extends \advanced_testcase {
    /** @var string Valid UUID for mocked jobs. */
    private const JOB_ID = '5f38d9aa-f40c-4992-9727-982f050ff9fd';

    /** @var string Markup with an allowed paragraph and two script vectors. */
    private const HOSTILE = '<p>ok</p><img src="x" onerror="alert(1)"><script>x</script>';

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
     * One generated question with hostile markup in every HTML field.
     *
     * @return array
     */
    private function hostile_question(): array {
        return [
            'text' => self::HOSTILE,
            'correctfeedback' => self::HOSTILE,
            'partiallycorrectfeedback' => self::HOSTILE,
            'incorrectfeedback' => self::HOSTILE,
            'answers' => [
                ['text' => self::HOSTILE, 'iscorrect' => 1],
                ['text' => 'Plain', 'iscorrect' => 0],
            ],
        ];
    }

    /**
     * Assert every HTML field of the cleaned questions is free of script vectors.
     *
     * @param array $questions Decoded cleaned questions.
     */
    private function assert_clean(array $questions): void {
        $fields = ['text', 'correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];
        foreach ($questions as $question) {
            $values = array_map(fn($f) => $question[$f], $fields);
            foreach ($question['answers'] as $answer) {
                $values[] = $answer['text'];
            }
            foreach ($values as $value) {
                $this->assertStringNotContainsStringIgnoringCase('<script', $value);
                $this->assertStringNotContainsStringIgnoringCase('onerror', $value);
            }
        }
    }

    public function test_hostile_markup_is_removed_from_every_html_field(): void {
        $cleaned = json_decode(practice_quiz_context_service::clean_generated_questions(
            json_encode([$this->hostile_question()])
        ), true);

        $this->assertCount(1, $cleaned);
        $this->assert_clean($cleaned);
        foreach (['text', 'correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'] as $field) {
            $this->assertStringContainsString('<p>ok</p>', $cleaned[0][$field]);
        }
        $this->assertStringContainsString('<p>ok</p>', $cleaned[0]['answers'][0]['text']);
        $this->assertSame('Plain', $cleaned[0]['answers'][1]['text']);
        $this->assertSame(1, $cleaned[0]['answers'][0]['iscorrect']);
        $this->assertSame(0, $cleaned[0]['answers'][1]['iscorrect']);
    }

    public function test_malformed_items_are_dropped_or_emptied(): void {
        $cleaned = json_decode(practice_quiz_context_service::clean_generated_questions(json_encode([
            'not a question',
            ['text' => ['nested'], 'correctfeedback' => 5, 'answers' => [['text' => null, 'iscorrect' => 1]]],
        ])), true);

        $this->assertCount(1, $cleaned);
        $this->assertSame('', $cleaned[0]['text']);
        $this->assertSame('', $cleaned[0]['correctfeedback']);
        $this->assertSame('', $cleaned[0]['answers'][0]['text']);

        $this->assertSame('[]', practice_quiz_context_service::clean_generated_questions('not json'));
    }

    public function test_finalize_practice_quiz_returns_cleaned_questions(): void {
        if (!tutor_mode_policy::is_quiz_runtime_available()) {
            $this->markTestSkipped('mod_simplequiz2 is not installed at a supported version.');
        }

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        (new consent_service())->accept((int) $student->id);

        $service = $this->createMock(practice_quiz_service::class);
        $service->method('finalize_from_job')->willReturn([
            'success' => true,
            'error' => '',
            'title' => 'Quiz',
            'introhtml' => '',
            'questions' => json_encode([$this->hostile_question(), $this->hostile_question()]),
        ]);
        service_factory::set_test_practice_quiz_service($service);

        $this->setUser($student);
        job_ownership::register((int) $student->id, (int) $course->id, self::JOB_ID);
        $result = finalize_practice_quiz::execute((int) $course->id, self::JOB_ID, 'Topic', 2);

        $this->assertTrue($result['success']);
        $questions = json_decode($result['questions'], true);
        $this->assertCount(2, $questions);
        $this->assert_clean($questions);
    }
}
