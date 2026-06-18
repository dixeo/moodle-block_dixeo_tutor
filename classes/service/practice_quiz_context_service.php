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
    /**
     * Submit a practice quiz review to the tutor.
     *
     * @param int $courseid
     * @param int $userid
     * @param array $payload title, questionsjson, bestattemptjson, exitscore, total.
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
     * @param array $payload Must include title, questionsjson, bestattemptjson; optional exitscore, total.
     * @param int $courseid Course ID for feedback formatting context.
     * @return array|null Review context object or null when invalid.
     */
    public function build_review_context(array $payload, int $courseid = 0): ?array {
        $questionsjson = $payload['questionsjson'] ?? '';
        $bestjson = $payload['bestattemptjson'] ?? '';

        $questions = json_decode($questionsjson, true);
        $bestattempt = json_decode($bestjson, true);

        if (!is_array($questions) || $questions === []) {
            return null;
        }
        if (!is_array($bestattempt)) {
            $bestattempt = [];
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
            $context
        );

        $review['instructions'] = get_string('quiz_review_ai_instructions', 'block_dixeo_tutor', (object) [
            'title' => $title,
            'score' => $bestscore,
            'total' => $total,
        ]);

        return $this->shrink_review_context($review);
    }

    /**
     * Progressively shrink review context until encoded JSON fits the limit.
     *
     * @param array $review Review payload from {@see practice_quiz_review_builder::build()}.
     * @return array|null
     */
    private function shrink_review_context(array $review): ?array {
        $json = tutor_context_size_helper::encode_context($review, true);
        if ($json !== null && tutor_context_size_helper::context_fits($json)) {
            return $review;
        }

        $json = tutor_context_size_helper::encode_context($review);
        if ($json !== null && tutor_context_size_helper::context_fits($json)) {
            return $review;
        }

        foreach ($review['questions'] as $i => $item) {
            unset($review['questions'][$i]['feedbackHtml']);
        }
        $json = tutor_context_size_helper::encode_context($review);
        if ($json !== null && tutor_context_size_helper::context_fits($json)) {
            return $review;
        }

        $review['questions'] = [];
        $json = tutor_context_size_helper::encode_context($review);

        return ($json !== null && tutor_context_size_helper::context_fits($json)) ? $review : null;
    }
}
