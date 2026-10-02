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
 * Practice quiz tutor review messages.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\dto\tutor_message;

/**
 * Builds and submits practice-quiz review context to the tutor API.
 */
class practice_quiz_context_service {
    /** @var int Largest raw questions, attempt, or intro string that will be decoded. */
    public const MAX_INPUT_JSON_BYTES = 65536;

    /** @var int Maximum JSON nesting accepted by json_decode. */
    public const MAX_JSON_DEPTH = 16;

    /** @var int Maximum questions, and the maximum length of any array in the payload. */
    public const MAX_QUESTIONS = 100;

    /** @var int Maximum answers on one question. */
    public const MAX_ANSWERS = 20;

    /** @var int Maximum bytes of one string field inside the decoded payload. */
    public const MAX_STRING_BYTES = 32000;

    /**
     * Submit a practice quiz review to the tutor.
     *
     * @param int $courseid
     * @param int $userid
     * @param array $payload title, questionsjson, bestattemptjson, exitscore, total, introhtml.
     * @return operation_result|null
     */
    public function submit_review(
        int $courseid,
        int $userid,
        array $payload
    ): ?operation_result {
        $proactive = new tutor_proactive_context_service();
        if (!$proactive->can_use_tutor($userid, $courseid)) {
            return null;
        }

        $context = $this->build_review_context($payload, $courseid);
        if ($context === null) {
            return null;
        }

        $instructions = isset($context['instructions']) ? (string) $context['instructions'] : null;
        unset($context['instructions']);

        $title = trim((string) ($payload['title'] ?? ''));
        $visiblemessage = $title !== ''
            ? $title
            : get_string('practice_quiz_default_title', 'local_dixeo');

        try {
            return service_factory::get_tutor_service()->submit(
                $courseid,
                $userid,
                tutor_message::system(
                    $context,
                    $visiblemessage,
                    $instructions,
                    true
                ),
                tutor_message::MODE_QUIZ
            );
        } catch (api_exception $e) {
            debugging('practice_quiz_context submit failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Build structured review context from client payload.
     *
     * @param array $payload Must include title, questionsjson, bestattemptjson; optional exitscore, total, introhtml.
     * @param int $courseid Course ID for feedback formatting context.
     * @return array|null Review context object or null when invalid.
     */
    public function build_review_context(array $payload, int $courseid = 0): ?array {
        $questionsjson = (string) ($payload['questionsjson'] ?? '');
        $bestjson = (string) ($payload['bestattemptjson'] ?? '');
        $introhtml = (string) ($payload['introhtml'] ?? '');

        if (
            strlen($questionsjson) > self::MAX_INPUT_JSON_BYTES
                || strlen($bestjson) > self::MAX_INPUT_JSON_BYTES
                || strlen($introhtml) > self::MAX_INPUT_JSON_BYTES
        ) {
            return null;
        }

        $questions = json_decode($questionsjson, true, self::MAX_JSON_DEPTH);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions) || $questions === []) {
            return null;
        }

        if ($bestjson === '') {
            $bestattempt = [];
        } else {
            $bestattempt = json_decode($bestjson, true, self::MAX_JSON_DEPTH);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($bestattempt)) {
                return null;
            }
        }

        if (!$this->review_structure_is_bounded($questions, $bestattempt)) {
            return null;
        }

        $total = (int) ($payload['total'] ?? count($questions));
        $exitscore = (int) ($payload['exitscore'] ?? ($bestattempt['score'] ?? 0));
        $title = (string) ($payload['title'] ?? '');
        $bestscore = (int) ($bestattempt['score'] ?? 0);

        $context = null;
        if ($courseid > 0) {
            $context = \context_course::instance($courseid);
        }

        $review = practice_quiz_review_builder::build(
            $questions,
            $bestattempt,
            [
                'score' => $exitscore,
                'total' => $total,
            ],
            $title,
            $context,
            $questionsjson,
            trim($introhtml)
        );

        $review['instructions'] = get_string('quiz_review_ai_instructions', 'block_dixeo_tutor', (object) [
            'title' => $title,
            'score' => $bestscore,
            'total' => $total,
        ]);

        return $this->shrink_review_context($review);
    }

    /**
     * Whether the decoded quiz stays within question, answer, string, and nesting caps.
     *
     * @param array $questions Decoded questions.
     * @param array $bestattempt Decoded best-attempt state.
     * @return bool
     */
    private function review_structure_is_bounded(array $questions, array $bestattempt): bool {
        if (count($questions) > self::MAX_QUESTIONS) {
            return false;
        }

        foreach ($questions as $question) {
            if (!is_array($question)) {
                return false;
            }
            $answers = $question['answers'] ?? [];
            if (is_array($answers) && count($answers) > self::MAX_ANSWERS) {
                return false;
            }
        }

        if (!$this->value_is_bounded($questions, 0) || !$this->value_is_bounded($bestattempt, 0)) {
            return false;
        }

        return true;
    }

    /**
     * Whether a decoded value stays within array length, string size, and depth.
     *
     * @param mixed $value Decoded JSON value.
     * @param int $depth Current nesting depth.
     * @return bool
     */
    private function value_is_bounded($value, int $depth): bool {
        if ($depth > self::MAX_JSON_DEPTH) {
            return false;
        }
        if (is_string($value)) {
            return strlen($value) <= self::MAX_STRING_BYTES;
        }
        if (!is_array($value)) {
            return true;
        }
        if (count($value) > self::MAX_QUESTIONS) {
            return false;
        }
        foreach ($value as $item) {
            if (!$this->value_is_bounded($item, $depth + 1)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Progressively shrink review context until encoded JSON fits the limit.
     * Order: full payload → strip feedbackHtml → drop summary questions → strip introhtml → drop questionsJson.
     *
     * @param array $review Review payload from {@see practice_quiz_review_builder::build()}.
     * @return array|null
     */
    private function shrink_review_context(array $review): ?array {
        if ($this->fits_context($review)) {
            return $review;
        }

        $nofeedbackhtml = $review;
        foreach ($nofeedbackhtml['questions'] as $i => $item) {
            unset($nofeedbackhtml['questions'][$i]['feedbackHtml']);
        }
        if ($this->fits_context($nofeedbackhtml)) {
            return $nofeedbackhtml;
        }

        $nosummary = $nofeedbackhtml;
        $nosummary['questions'] = [];
        if ($this->fits_context($nosummary)) {
            return $nosummary;
        }

        $nointro = $nosummary;
        $nointro['introhtml'] = '';
        if ($this->fits_context($nointro)) {
            return $nointro;
        }

        $noretake = $nointro;
        unset($noretake['questionsJson']);
        if ($this->fits_context($noretake)) {
            return $noretake;
        }

        return null;
    }

    /**
     * Whether the review payload fits the context size limit.
     *
     * @param array $review Review context without instructions.
     * @return bool
     */
    private function fits_context(array $review): bool {
        $json = tutor_context_size_helper::encode_context($review, true);
        if ($json !== null && tutor_context_size_helper::context_fits($json)) {
            return true;
        }

        $json = tutor_context_size_helper::encode_context($review);
        return $json !== null && tutor_context_size_helper::context_fits($json);
    }
}
