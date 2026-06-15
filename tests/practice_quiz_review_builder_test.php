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
 * Tests for {@see \block_dixeo_tutor\service\practice_quiz_review_builder}.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\practice_quiz_review_builder;
use block_dixeo_tutor\service\practice_quiz_context_service;

/**
 * Tests for practice quiz review builder.
 *
 * @covers \block_dixeo_tutor\service\practice_quiz_review_builder
 * @covers \block_dixeo_tutor\service\practice_quiz_context_service
 */
final class practice_quiz_review_builder_test extends \advanced_testcase {
    /**
     * Build review payload from questions and best attempt.
     */
    public function test_build_review_payload(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $questions = [
            (object) [
                'text' => '<p>What is a cell?</p>',
                'correctfeedback' => '<p>Well done!</p>',
                'incorrectfeedback' => '<p>Not quite.</p>',
                'partiallycorrectfeedback' => '',
                'answers' => [
                    (object) ['text' => '<p>The basic unit of life</p>', 'iscorrect' => 1],
                    (object) ['text' => '<p>A type of tissue</p>', 'iscorrect' => 0],
                ],
            ],
            (object) [
                'text' => '<p>Which organelle holds DNA?</p>',
                'correctfeedback' => '',
                'incorrectfeedback' => '<p>DNA lives in the nucleus.</p>',
                'partiallycorrectfeedback' => '',
                'answers' => [
                    (object) ['text' => '<p>Ribosome</p>', 'iscorrect' => 0],
                    (object) ['text' => '<p>Nucleus</p>', 'iscorrect' => 1],
                ],
            ],
        ];

        $bestattempt = [
            'score' => 1,
            'total' => 2,
            'answerResults' => [true, false],
            'selectedAnswerIds' => [[0], [0]],
        ];

        $review = practice_quiz_review_builder::build(
            $questions,
            $bestattempt,
            ['score' => 0, 'total' => 2],
            'Cell biology',
            $context
        );

        $this->assertSame('practice_quiz_review', $review['schema']);
        $this->assertSame(1, $review['version']);
        $this->assertSame('Cell biology', $review['title']);
        $this->assertSame(1, $review['bestAttempt']['score']);
        $this->assertSame(2, $review['bestAttempt']['total']);
        $this->assertSame(0, $review['exitAttempt']['score']);
        $this->assertCount(2, $review['questions']);

        $this->assertTrue($review['questions'][0]['isCorrect']);
        $this->assertSame('What is a cell?', $review['questions'][0]['question']);
        $this->assertSame(['The basic unit of life'], $review['questions'][0]['selected']);
        $this->assertSame(['The basic unit of life'], $review['questions'][0]['correct']);
        $this->assertStringContainsString('Well done', $review['questions'][0]['feedback']);

        $this->assertFalse($review['questions'][1]['isCorrect']);
        $this->assertSame(['Ribosome'], $review['questions'][1]['selected']);
        $this->assertSame(['Nucleus'], $review['questions'][1]['correct']);
        $this->assertStringContainsString('nucleus', strtolower($review['questions'][1]['feedback']));
    }

    /**
     * Review message includes AI instructions for the tutor.
     */
    public function test_build_review_message_includes_instructions(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $questions = json_encode([
            (object) [
                'text' => 'Sample question?',
                'correctfeedback' => '',
                'incorrectfeedback' => '',
                'partiallycorrectfeedback' => '',
                'answers' => [
                    (object) ['text' => 'Yes', 'iscorrect' => 1],
                    (object) ['text' => 'No', 'iscorrect' => 0],
                ],
            ],
        ]);

        $service = new practice_quiz_context_service();
        $context = $service->build_review_context([
            'title' => 'Sample quiz',
            'questionsjson' => $questions,
            'bestattemptjson' => json_encode([
                'score' => 1,
                'total' => 1,
                'answerResults' => [true],
                'selectedAnswerIds' => [[0]],
            ]),
            'exitscore' => 1,
            'total' => 1,
        ], (int) $course->id);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('instructions', $context);
        $this->assertStringContainsString('Sample quiz', $context['instructions']);
        $this->assertStringContainsString('1/1', $context['instructions']);
    }

    /**
     * Review context object matches the practice quiz review schema.
     */
    public function test_build_review_context_schema(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $service = new practice_quiz_context_service();
        $context = $service->build_review_context([
            'title' => 'Sample quiz',
            'questionsjson' => json_encode([
                (object) [
                    'text' => 'Sample question?',
                    'correctfeedback' => '',
                    'incorrectfeedback' => '',
                    'partiallycorrectfeedback' => '',
                    'answers' => [
                        (object) ['text' => 'Yes', 'iscorrect' => 1],
                    ],
                ],
            ]),
            'bestattemptjson' => json_encode([
                'score' => 1,
                'total' => 1,
                'answerResults' => [true],
                'selectedAnswerIds' => [[0]],
            ]),
            'exitscore' => 1,
            'total' => 1,
        ], (int) $course->id);

        $this->assertSame('practice_quiz_review', $context['schema']);
        $this->assertSame(1, $context['version']);
    }
}
