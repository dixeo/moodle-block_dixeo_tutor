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
 * Per-user, per-course tutor mode preference.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

use local_dixeo\dto\tutor_message;

/**
 * Stores the selected tutor mode (normal, guide, quiz, teach) per course.
 */
class tutor_mode_service {
    /** @var string User preference prefix for tutor mode per course. */
    public const PREF_MODE_PREFIX = 'block_dixeo_tutor_mode_';

    /** @var string User preference prefix for last special-mode message time per course. */
    public const PREF_LAST_ACTIVITY_PREFIX = 'block_dixeo_tutor_modeactivity_';

    /** @var int Seconds after the last guide/teach/quiz message before mode returns to standard. */
    public const MODE_TTL = HOURSECS;

    /**
     * Get the tutor mode for a user in a course.
     *
     * Guide, teach, and quiz expire back to standard one hour after the last message
     * (or last mode selection if no message has been recorded yet).
     *
     * @param int $userid
     * @param int $courseid
     * @return string Normalized mode.
     */
    public function get_mode(int $userid, int $courseid): string {
        $raw = get_user_preferences(self::PREF_MODE_PREFIX . $courseid, tutor_message::MODE_NORMAL, $userid);
        $mode = tutor_message::normalize_mode((string) $raw);
        $mode = tutor_mode_policy::coerce_mode($mode, tutor_mode_policy::is_quiz_runtime_available());
        return $this->expire_mode_if_stale($userid, $courseid, $mode);
    }

    /**
     * Set the tutor mode for a user in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $mode
     * @return string Stored normalized mode.
     */
    public function set_mode(int $userid, int $courseid, string $mode): string {
        $mode = tutor_message::normalize_mode($mode);
        tutor_mode_policy::require_mode_available($mode, tutor_mode_policy::is_quiz_runtime_available());
        set_user_preference(self::PREF_MODE_PREFIX . $courseid, $mode, $userid);
        if ($this->is_expirable_mode($mode)) {
            $this->touch_activity($userid, $courseid);
        }
        return $mode;
    }

    /**
     * Record that a message (or equivalent chat activity) happened in the current course.
     *
     * @param int $userid
     * @param int $courseid
     */
    public function touch_activity(int $userid, int $courseid): void {
        set_user_preference(self::PREF_LAST_ACTIVITY_PREFIX . $courseid, time(), $userid);
    }

    /**
     * Last recorded special-mode activity time.
     *
     * @param int $userid
     * @param int $courseid
     * @return int Unix timestamp, or 0 if never recorded.
     */
    public function get_last_activity(int $userid, int $courseid): int {
        return (int) get_user_preferences(self::PREF_LAST_ACTIVITY_PREFIX . $courseid, 0, $userid);
    }

    /**
     * Reset stale guide/teach/quiz preferences to standard.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $mode Already coerced mode.
     * @return string
     */
    public function expire_mode_if_stale(int $userid, int $courseid, string $mode): string {
        if (!$this->is_expirable_mode($mode)) {
            return $mode;
        }

        $last = $this->get_last_activity($userid, $courseid);
        if ($last <= 0) {
            $this->touch_activity($userid, $courseid);
            return $mode;
        }
        if ((time() - $last) <= self::MODE_TTL) {
            return $mode;
        }

        set_user_preference(self::PREF_MODE_PREFIX . $courseid, tutor_message::MODE_NORMAL, $userid);
        return tutor_message::MODE_NORMAL;
    }

    /**
     * Whether the mode times out after idle.
     *
     * @param string $mode
     * @return bool
     */
    public function is_expirable_mode(string $mode): bool {
        return $mode === tutor_message::MODE_GUIDE
            || $mode === tutor_message::MODE_QUIZ
            || $mode === tutor_message::MODE_TEACH;
    }
}
