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
     * @param string $pageurl
     * @return operation_result|null
     */
    public function submit_review(
        int $courseid,
        int $userid,
        array $payload,
        string $pageurl = ''
    ): ?operation_result {
        $proactive = new tutor_proactive_context_service();
        if (!$proactive->can_use_tutor($userid, $courseid)) {
            return null;
        }

        $message = $this->build_review_message($payload);
        if ($message === '') {
            return null;
        }

        try {
            return service_factory::get_tutor_service()->submit_message(
                $courseid,
                $userid,
                $message,
                $pageurl
            );
        } catch (api_exception $e) {
            debugging('practice_quiz_context submit failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Build a wrapped practice-quiz-review message from client payload.
     *
     * @param array $payload Must include title, questionsjson, bestattemptjson; optional exitscore, total.
     * @return string
     */
    public function build_review_message(array $payload): string {
        $questionsjson = $payload['questionsjson'] ?? '';
        $bestjson = $payload['bestattemptjson'] ?? '';

        $questions = json_decode($questionsjson, true);
        $bestattempt = json_decode($bestjson, true);

        if (!is_array($questions) || $questions === []) {
            return '';
        }
        if (!is_array($bestattempt)) {
            $bestattempt = [];
        }

        $total = (int) ($payload['total'] ?? count($questions));
        $exitscore = (int) ($payload['exitscore'] ?? ($bestattempt['score'] ?? 0));
        $title = (string) ($payload['title'] ?? '');
        $bestscore = (int) ($bestattempt['score'] ?? 0);

        $review = practice_quiz_review_builder::build(
            $questions,
            $bestattempt,
            [
                'score' => $exitscore,
                'total' => $total,
            ],
            $title
        );

        $review['instructions'] = get_string('quiz_review_ai_instructions', 'block_dixeo_tutor', (object) [
            'title' => $title,
            'score' => $bestscore,
            'total' => $total,
        ]);

        $json = json_encode($review, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            return '';
        }

        return tutor_message_helper::wrap_practice_quiz_review($json);
    }
}
