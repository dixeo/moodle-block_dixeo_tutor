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
 * Time a learner last deleted their tutor conversation in a course.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

/**
 * Keeps work queued before a "Delete my conversation" from recreating the conversation afterwards.
 *
 * Notices wait in the adhoc task queue and Guide me prompts in the pending table. Anything that
 * was queued at or before the deletion time is dropped instead of being sent.
 */
class tutor_erasure_marker {
    /** @var string User preference prefix: time of the last conversation deletion (per course). */
    public const PREF_ERASED_AT_PREFIX = 'block_dixeo_tutor_erasedat_';

    /**
     * Record that the learner deleted their conversation in the course now.
     *
     * @param int $userid
     * @param int $courseid
     */
    public static function mark(int $userid, int $courseid): void {
        set_user_preference(self::PREF_ERASED_AT_PREFIX . $courseid, time(), $userid);
    }

    /**
     * Time of the last deletion, or 0 when the learner never deleted the conversation.
     *
     * @param int $userid
     * @param int $courseid
     * @return int
     */
    public static function erased_at(int $userid, int $courseid): int {
        return (int) get_user_preferences(self::PREF_ERASED_AT_PREFIX . $courseid, 0, $userid);
    }

    /**
     * Whether something queued at the given time predates (or coincides with) the last deletion.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $queuedat Time the item was queued; 0 when unknown.
     * @return bool
     */
    public static function is_superseded(int $userid, int $courseid, int $queuedat): bool {
        $erasedat = self::erased_at($userid, $courseid);
        return $erasedat > 0 && $queuedat <= $erasedat;
    }
}
