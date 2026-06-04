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
 * Practice quiz tutor context messages.
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
 * Builds and submits practice-quiz lifecycle context to the tutor API.
 */
class practice_quiz_context_service {
    public const EVENT_STARTED = 'started';
    public const EVENT_ANSWERED = 'answered';
    public const EVENT_COMPLETED = 'completed';
    public const EVENT_CANCELLED = 'cancelled';
    public const EVENT_RESTARTED = 'restarted';

    /**
     * Submit a practice quiz context event to the tutor.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $event started|answered|completed|cancelled|restarted
     * @param array $payload Event-specific fields.
     * @param string $pageurl
     * @return operation_result|null
     */
    public function submit_event(
        int $courseid,
        int $userid,
        string $event,
        array $payload,
        string $pageurl = ''
    ): ?operation_result {
        $proactive = new tutor_proactive_context_service();
        if (!$proactive->can_use_tutor($userid, $courseid)) {
            return null;
        }

        $inner = $this->build_inner_message($event, $payload);
        if ($inner === '') {
            return null;
        }

        $message = tutor_message_helper::wrap_system_context($inner);

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
     * Build inner system-context instruction text.
     *
     * @param string $event
     * @param array $payload
     * @return string
     */
    public function build_inner_message(string $event, array $payload): string {
        switch ($event) {
            case self::EVENT_STARTED:
                return get_string('quiz_context_started', 'block_dixeo_tutor', (object) [
                    'title' => $payload['title'] ?? '',
                    'count' => (int) ($payload['count'] ?? 0),
                ]);
            case self::EVENT_ANSWERED:
                return get_string('quiz_context_answered', 'block_dixeo_tutor', (object) [
                    'question' => $payload['question'] ?? '',
                    'chosen' => $payload['chosen'] ?? '',
                    'correct' => $payload['correct'] ?? '',
                    'iscorrect' => !empty($payload['iscorrect']) ? 'yes' : 'no',
                    'index' => (int) ($payload['index'] ?? 0) + 1,
                    'total' => (int) ($payload['total'] ?? 0),
                ]);
            case self::EVENT_COMPLETED:
                return get_string('quiz_context_completed', 'block_dixeo_tutor', (object) [
                    'title' => $payload['title'] ?? '',
                    'score' => (int) ($payload['score'] ?? 0),
                    'total' => (int) ($payload['total'] ?? 0),
                ]);
            case self::EVENT_CANCELLED:
                return get_string('quiz_context_cancelled', 'block_dixeo_tutor', (object) [
                    'title' => $payload['title'] ?? '',
                ]);
            case self::EVENT_RESTARTED:
                return get_string('quiz_context_restarted', 'block_dixeo_tutor', (object) [
                    'title' => $payload['title'] ?? '',
                    'score' => (int) ($payload['score'] ?? 0),
                    'total' => (int) ($payload['total'] ?? 0),
                ]);
            default:
                return '';
        }
    }
}
