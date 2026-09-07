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
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;

/**
 * Stores and resolves when the user last read incoming tutor messages (user preference per course).
 */
class tutor_read_state_service {
    /** @var string User preference: Unix time of the latest incoming message the user has read. */
    public const PREF_LAST_READ_PREFIX = 'block_dixeo_tutor_lastread_';

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

        return self::latest_incoming_time_from_messages(is_array($messages) ? $messages : []);
    }

    /**
     * Latest assistant message Unix time in a chronologically sorted message batch (newest at end).
     *
     * @param array $messages
     * @return int
     */
    public static function latest_incoming_time_from_messages(array $messages): int {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (strtolower((string) ($messages[$i]['role'] ?? '')) !== tutor_message::ROLE_ASSISTANT) {
                continue;
            }
            return self::normalize_timestamp((int) ($messages[$i]['time'] ?? 0));
        }
        return 0;
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
