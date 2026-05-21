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
 * Per-user, per-course tutor read state for unread indicators.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;

/**
 * Stores and resolves when the user last read incoming tutor messages (user preference per course).
 */
class tutor_read_state_service {
    /** @var string User preference: Unix time of the latest incoming message the user has read. */
    public const PREF_LAST_READ_PREFIX = 'block_dixeo_tutor_lastread_';

    /** Must match {@see tutor_proactive_context_service::PROACTIVE_CONTEXT_TAG}. */
    private const PROACTIVE_CONTEXT_TAG = 'proactive-context';

    /**
     * Resolve unread state for the tutor block page load (server-side).
     * Compares the latest incoming (assistant) message time with the stored preference.
     * User messages are never treated as unread.
     * When the preference has never been set, bootstraps to (latest message time + 1 second) so
     * existing conversations are treated as already read.
     *
     * @param int $userid
     * @param int $courseid
     * @return array{hasunread: bool, lastread: int}
     */
    public function resolve_page_state(int $userid, int $courseid): array {
        $latestincoming = $this->get_latest_incoming_message_time($userid, $courseid);

        if (!$this->preference_is_set($userid, $courseid)) {
            $lastread = $latestincoming > 0 ? $latestincoming + 1 : 0;
            if ($latestincoming > 0) {
                set_user_preference(self::PREF_LAST_READ_PREFIX . $courseid, $lastread, $userid);
            }
            return [
                'hasunread' => false,
                'lastread' => $lastread,
            ];
        }

        $lastread = $this->get_last_read($userid, $courseid);
        $hasunread = $latestincoming > 0 && $latestincoming > $lastread;

        return [
            'hasunread' => $hasunread,
            'lastread' => $lastread,
        ];
    }

    /**
     * Mark all current incoming messages as read (stores latest incoming message time).
     *
     * @param int $userid
     * @param int $courseid
     * @return int Stored last-read timestamp.
     */
    public function mark_all_read(int $userid, int $courseid): int {
        $latestincoming = $this->get_latest_incoming_message_time($userid, $courseid);
        return $this->mark_read_up_to($userid, $courseid, $latestincoming);
    }

    /**
     * Mark incoming messages read up to a known message time (stores time + 1 second watermark).
     *
     * @param int $userid
     * @param int $courseid
     * @param int $messagetime Unix time of the latest incoming message the user has seen.
     * @return int Stored last-read watermark.
     */
    public function mark_read_up_to(int $userid, int $courseid, int $messagetime): int {
        $messagetime = self::normalize_timestamp($messagetime);
        if ($messagetime <= 0) {
            return $this->mark_all_read($userid, $courseid);
        }
        return $this->set_last_read($userid, $courseid, $messagetime + 1);
    }

    /**
     * Last read timestamp for incoming messages in this course.
     *
     * @param int $userid
     * @param int $courseid
     * @return int Unix timestamp, or 0 if never read.
     */
    public function get_last_read(int $userid, int $courseid): int {
        return self::normalize_timestamp(
            (int) get_user_preferences(self::PREF_LAST_READ_PREFIX . $courseid, 0, $userid)
        );
    }

    /**
     * Store last read incoming-message time for this course (keeps the highest value).
     *
     * @param int $userid
     * @param int $courseid
     * @param int $timestamp Unix time of the latest incoming message the user has seen.
     * @return int The value stored after merge with any existing preference.
     */
    public function set_last_read(int $userid, int $courseid, int $timestamp): int {
        $timestamp = self::normalize_timestamp($timestamp);
        $current = $this->get_last_read($userid, $courseid);
        $stored = max($current, $timestamp);
        set_user_preference(self::PREF_LAST_READ_PREFIX . $courseid, $stored, $userid);
        return $stored;
    }

    /**
     * Unix time of the latest incoming (assistant) message in the conversation, or 0.
     *
     * @param int $userid
     * @param int $courseid
     * @return int
     */
    public function get_latest_incoming_message_time(int $userid, int $courseid): int {
        try {
            $messages = service_factory::get_tutor_service()->get_conversation($courseid, $userid);
        } catch (api_exception $e) {
            return 0;
        }

        if (!is_array($messages)) {
            return 0;
        }

        $latest = 0;
        foreach ($messages as $msg) {
            if (!$this->is_incoming_message($msg)) {
                continue;
            }
            $time = self::normalize_timestamp((int) ($msg['time'] ?? 0));
            if ($time > $latest) {
                $latest = $time;
            }
        }

        return $latest;
    }

    /**
     * Preference is set.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    private function preference_is_set(int $userid, int $courseid): bool {
        return get_user_preferences(self::PREF_LAST_READ_PREFIX . $courseid, null, $userid) !== null;
    }

    /**
     * Whether incoming message.
     *
     * @param array $msg Message with role and content keys.
     * @return bool
     */
    private function is_incoming_message(array $msg): bool {
        if (strtolower((string) ($msg['role'] ?? '')) !== 'assistant') {
            return false;
        }
        $content = (string) ($msg['content'] ?? '');
        $pattern = '/<' . preg_quote(self::PROACTIVE_CONTEXT_TAG, '/') . '[\s>]/i';
        return preg_match($pattern, $content) !== 1;
    }

    /**
     * Normalize Unix seconds; values that look like milliseconds are converted.
     *
     * @param int $timestamp
     * @return int
     */
    private static function normalize_timestamp(int $timestamp): int {
        if ($timestamp < 0) {
            return 0;
        }
        if ($timestamp > 9999999999) {
            return (int) floor($timestamp / 1000);
        }
        return $timestamp;
    }
}
