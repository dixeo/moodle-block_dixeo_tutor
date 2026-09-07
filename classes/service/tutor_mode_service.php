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

    /**
     * Get the tutor mode for a user in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return string Normalized mode.
     */
    public function get_mode(int $userid, int $courseid): string {
        $raw = get_user_preferences(self::PREF_MODE_PREFIX . $courseid, tutor_message::MODE_NORMAL, $userid);
        return tutor_message::normalize_mode((string) $raw);
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
        set_user_preference(self::PREF_MODE_PREFIX . $courseid, $mode, $userid);
        return $mode;
    }
}
