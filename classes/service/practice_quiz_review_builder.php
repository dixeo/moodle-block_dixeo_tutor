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
 * Build structured practice quiz review payloads for the tutor.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

use mod_simplequiz2\question_grading_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/simplequiz2/lib.php');

/**
 * Assembles versioned JSON review data from questions and best-attempt state.
 */
class practice_quiz_review_builder {
    /** @var int Current practice quiz review context version. */
    public const VERSION = 2;

    /**
     * Build the practice quiz review payload (v2 with full quiz JSON and attempt state).
     *
     * @param array $questions Decoded simplequiz2 question objects (indexed).
     * @param array $bestattempt Best attempt with score, total, answerResults, selectedAnswerIds.
     * @param array $exitattempt Exit attempt with score and total.
     * @param string $title Quiz title.
     * @param \context|null $context Course context for formatting feedback HTML.
     * @param string $questionsjson Raw questions JSON for retake and tutor context.
     * @return array Review payload array (not yet JSON-encoded).
     */
    public static function build(
        array $questions,
        array $bestattempt,
        array $exitattempt,
        string $title,
        ?\context $context = null,
        string $questionsjson = ''
    ): array {
        $bestscore = (int) ($bestattempt['score'] ?? 0);
        $besttotal = (int) ($bestattempt['total'] ?? count($questions));
        $exitscore = (int) ($exitattempt['score'] ?? 0);
        $exittotal = (int) ($exitattempt['total'] ?? $besttotal);

        $selectedids = $bestattempt['selectedAnswerIds'] ?? [];
        $answerresults = $bestattempt['answerResults'] ?? [];

        $items = [];
        foreach ($questions as $index => $rawquestion) {
            $question = \simplequiz2_normalize_question(is_object($rawquestion) ? $rawquestion : (object) $rawquestion);
            $ids = [];
            if (isset($selectedids[$index]) && is_array($selectedids[$index])) {
                $ids = array_map('intval', $selectedids[$index]);
            }

            $csv = $ids === [] ? '' : implode(',', $ids);
            $grading = question_grading_service::grade_question($question, $csv);
            $outcome = \simplequiz2_feedback_outcome_from_grading(
                $grading['iscorrect'],
                $grading['haspartialcorrect']
            );
            $rawfeedback = \simplequiz2_get_raw_feedback_for_outcome($question, $outcome);
            $feedbackplain = self::html_to_plain($rawfeedback);
            $feedbackhtml = self::format_feedback_html($rawfeedback, $context);

            $iscorrect = isset($answerresults[$index])
                ? (bool) $answerresults[$index]
                : $grading['iscorrect'];

            $items[] = [
                'index' => (int) $index,
                'question' => self::html_to_plain($question->text ?? ''),
                'isCorrect' => $iscorrect,
                'selected' => self::answer_texts_for_ids($question, $ids),
                'correct' => self::correct_answer_texts($question),
                'feedback' => $feedbackplain,
                'feedbackHtml' => $feedbackhtml,
            ];
        }

        return [
            'schema' => tutor_context_schema::SCHEMA_PRACTICE_QUIZ_REVIEW,
            'version' => self::VERSION,
            'title' => $title,
            'questionsJson' => $questionsjson,
            'bestAttempt' => self::normalize_best_attempt_state($bestattempt, $besttotal),
            'exitAttempt' => [
                'score' => $exitscore,
                'total' => $exittotal,
            ],
            'questions' => $items,
        ];
    }

    /**
     * Normalize best-attempt state for review building.
     *
     * @param array $bestattempt Raw best-attempt state from the embed player.
     * @param int $defaulttotal Fallback total when missing from attempt state.
     * @return array{score: int, total: int, answerResults: array, selectedAnswerIds: array}
     */
    private static function normalize_best_attempt_state(array $bestattempt, int $defaulttotal): array {
        return [
            'score' => (int) ($bestattempt['score'] ?? 0),
            'total' => (int) ($bestattempt['total'] ?? $defaulttotal),
            'answerResults' => $bestattempt['answerResults'] ?? [],
            'selectedAnswerIds' => $bestattempt['selectedAnswerIds'] ?? [],
        ];
    }

    /**
     * Resolve answer texts for the given answer ids.
     *
     * @param object $question Normalized question.
     * @param int[] $ids Selected answer indices.
     * @return string[]
     */
    private static function answer_texts_for_ids(object $question, array $ids): array {
        $texts = [];
        foreach ($ids as $id) {
            $text = self::answer_text_at_index($question, (int) $id);
            if ($text !== '') {
                $texts[] = $text;
            }
        }
        return $texts;
    }

    /**
     * Collect correct answer texts for a question.
     *
     * @param object $question Normalized question.
     * @return string[]
     */
    private static function correct_answer_texts(object $question): array {
        $texts = [];
        foreach ($question->answers as $answer) {
            if (is_array($answer)) {
                $answer = (object) $answer;
            }
            if ((int) ($answer->iscorrect ?? 0) === 1) {
                $plain = self::html_to_plain($answer->text ?? '');
                if ($plain !== '') {
                    $texts[] = $plain;
                }
            }
        }
        return $texts;
    }

    /**
     * Return the answer text at the given index.
     *
     * @param object $question Normalized question.
     * @param int $index Answer index.
     * @return string
     */
    private static function answer_text_at_index(object $question, int $index): string {
        if (!isset($question->answers[$index])) {
            return '';
        }
        $answer = $question->answers[$index];
        if (is_array($answer)) {
            $answer = (object) $answer;
        }
        return self::html_to_plain($answer->text ?? '');
    }

    /**
     * Convert HTML to plain text.
     *
     * @param string $html HTML or plain text.
     * @return string Plain text.
     */
    private static function html_to_plain(string $html): string {
        return trim(html_to_text($html, 0, false));
    }

    /**
     * Format stored feedback HTML for safe display in the review panel.
     *
     * @param string $rawfeedback Stored feedback HTML.
     * @param \context|null $context Course context for format_text filters.
     * @return string
     */
    private static function format_feedback_html(string $rawfeedback, ?\context $context): string {
        if (self::is_feedback_empty($rawfeedback)) {
            return '';
        }
        if ($context === null) {
            return trim($rawfeedback);
        }

        return trim(format_text($rawfeedback, FORMAT_HTML, [
            'noclean' => true,
            'para' => false,
            'filter' => true,
            'context' => $context,
        ]));
    }

    /**
     * Whether feedback text is empty after cleanup.
     *
     * @param string $rawfeedback Stored feedback HTML.
     * @return bool
     */
    private static function is_feedback_empty(string $rawfeedback): bool {
        if (trim($rawfeedback) === '') {
            return true;
        }
        if (class_exists(\mod_simplequiz2\util\editor_content::class)) {
            return \mod_simplequiz2\util\editor_content::is_empty($rawfeedback);
        }
        return false;
    }
}
