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
 * Event observers erasing tutor data left behind by a deleted course or user.
 *
 * The privacy provider reaches conversations through the course context; once the
 * course is gone there is no context left to walk, so the API copy would survive
 * for ever. Erasing is queued rather than called inline: deletion must not wait on
 * a remote call, and a failed one has to be retried. Queueing is a plain database
 * write, which is why the observers are registered as internal.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\observer;

use block_dixeo_tutor\privacy\provider;
use block_dixeo_tutor\service\tutor_proactive_context_service;
use block_dixeo_tutor\task\erase_conversations;
use core\event\course_deleted;
use core\event\user_deleted;

/**
 * Observer keeping the Dixeo API free of conversations Moodle can no longer reach.
 */
class conversation_cleanup_observer {
    /**
     * Queue the erasure of every conversation held for the deleted course.
     *
     * @param course_deleted $event The event.
     */
    public static function course_deleted(course_deleted $event): void {
        global $DB;

        $courseid = (int) $event->objectid;
        // Local rows of a deleted course have no context left for the privacy API to find them by.
        $DB->delete_records(tutor_proactive_context_service::TABLE, ['courseid' => $courseid]);
        foreach (array_keys(provider::COURSE_PREFERENCES) as $prefix) {
            $DB->delete_records('user_preferences', ['name' => $prefix . $courseid]);
        }

        erase_conversations::queue($courseid, null);
    }

    /**
     * Queue the erasure of every conversation held for the deleted user.
     *
     * The privacy provider only runs for GDPR requests; an account deleted straight
     * from the user admin never reaches it, so its API copy would live on until the
     * retention purge.
     *
     * @param user_deleted $event The event.
     */
    public static function user_deleted(user_deleted $event): void {
        global $DB;

        // Core removes the user's preferences; the queued Guide me prompts are this block's to remove.
        $DB->delete_records(tutor_proactive_context_service::TABLE, ['userid' => (int) $event->objectid]);

        erase_conversations::queue(null, (int) $event->objectid);
    }
}
