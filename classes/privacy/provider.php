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
 * Privacy provider for the Dixeo Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\privacy;

use block_dixeo_tutor\service\tutor_erasure_marker;
use block_dixeo_tutor\service\tutor_mode_service;
use block_dixeo_tutor\service\tutor_notice_service;
use block_dixeo_tutor\service\tutor_proactive_context_service;
use block_dixeo_tutor\service\tutor_read_state_service;
use block_dixeo_tutor\task\post_notice;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the data this block stores in Moodle.
 *
 * Queued proactive context sits in block_dixeo_tutor_pending until flushed, and per-course
 * preferences record the learner's tutor state. Tutor conversations held by the Dixeo API are
 * found, exported and erased by local_dixeo's privacy provider, which owns that protocol; this
 * provider still declares the fields the tutor sends there.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Preference value exported as a date. */
    private const PREF_TIME = 'time';

    /** @var string Preference value exported as stored. */
    private const PREF_RAW = 'raw';

    /**
     * @var array<string, array{0: string, 1: string}> Per-course preferences (prefix + courseid):
     * metadata string and how the value is exported. Every prefix ends with an underscore, so
     * none is a LIKE prefix of another.
     */
    public const COURSE_PREFERENCES = [
        tutor_notice_service::PREF_LAST_GREETING_PREFIX => ['privacy:metadata:lastproactive', self::PREF_TIME],
        tutor_notice_service::PREF_LAST_MESSAGE_PREFIX => ['privacy:metadata:lastmessage', self::PREF_TIME],
        tutor_read_state_service::PREF_LAST_READ_PREFIX => ['privacy:metadata:lastread', self::PREF_TIME],
        tutor_mode_service::PREF_MODE_PREFIX => ['privacy:metadata:tutormode', self::PREF_RAW],
        tutor_mode_service::PREF_LAST_ACTIVITY_PREFIX => ['privacy:metadata:tutormodeactivity', self::PREF_TIME],
        tutor_erasure_marker::PREF_ERASED_AT_PREFIX => ['privacy:metadata:erasedat', self::PREF_TIME],
    ];

    /**
     * Describe stored and transmitted personal data.
     *
     * @param collection $collection The privacy metadata collection.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_dixeo_tutor_pending',
            [
                'userid' => 'privacy:metadata:pending_userid',
                'courseid' => 'privacy:metadata:pending_courseid',
                'message' => 'privacy:metadata:pending_message',
                'timemodified' => 'privacy:metadata:pending_timemodified',
            ],
            'privacy:metadata:pendingpurpose'
        );

        $collection->add_external_location_link(
            'dixeo_api',
            [
                'userid' => 'privacy:metadata:userid',
                'courseid' => 'privacy:metadata:courseid',
                'message' => 'privacy:metadata:message',
                'pageurl' => 'privacy:metadata:pageurl',
                'cmid' => 'privacy:metadata:cmid',
                'mode' => 'privacy:metadata:mode',
                'firstname' => 'privacy:metadata:firstname',
                'notice' => 'privacy:metadata:notice',
                'grades' => 'privacy:metadata:grades',
                'quizattempt' => 'privacy:metadata:quizattempt',
                'lessoncontent' => 'privacy:metadata:lessoncontent',
                'guideprompt' => 'privacy:metadata:guideprompt',
            ],
            'privacy:metadata:externalpurpose'
        );

        foreach (self::COURSE_PREFERENCES as $prefix => [$stringid]) {
            $collection->add_user_preference($prefix, $stringid);
        }

        return $collection;
    }

    /**
     * Get the course contexts in which the user has queued tutor context or course preferences.
     *
     * @param int $userid The user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        self::add_local_contexts($contextlist, $userid);
        return $contextlist;
    }

    /**
     * Get the users with queued tutor context or course preferences in the given course context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $courseid = self::course_id($userlist->get_context());
        if ($courseid === 0) {
            return;
        }

        self::add_local_users($userlist, $courseid);
    }

    /**
     * Export the user's queued tutor context and course preferences for each approved course context.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::course_id($context);
            if ($courseid === 0) {
                continue;
            }

            self::export_pending($context, $userid, $courseid);
            self::export_course_preferences($context, $userid, $courseid);
        }
    }

    /**
     * Erase every user's tutor data stored by this block in the given course context.
     *
     * @param \context $context The context to purge.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        $courseid = self::course_id($context);
        if ($courseid === 0) {
            return;
        }

        self::delete_pending($courseid, null);
        self::delete_course_preferences($courseid, null);
        post_notice::discard($courseid, null);
    }

    /**
     * Erase the user's tutor data stored by this block in each approved course context.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::course_id($context);
            if ($courseid === 0) {
                continue;
            }

            self::delete_pending($courseid, $userid);
            self::delete_course_preferences($courseid, $userid);
            post_notice::discard($courseid, $userid);
        }
    }

    /**
     * Erase the approved users' tutor data stored by this block in a course context.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $courseid = self::course_id($userlist->get_context());
        if ($courseid === 0) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            self::delete_pending($courseid, (int) $userid);
            self::delete_course_preferences($courseid, (int) $userid);
            post_notice::discard($courseid, (int) $userid);
        }
    }

    /**
     * Add course contexts that hold queued proactive data for a user.
     *
     * @param contextlist $contextlist Context list to populate.
     * @param int $userid User id.
     */
    private static function add_local_contexts(contextlist $contextlist, int $userid): void {
        global $DB;

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {" . tutor_proactive_context_service::TABLE . "} p
               JOIN {context} ctx ON ctx.instanceid = p.courseid AND ctx.contextlevel = :contextlevel
              WHERE p.userid = :userid",
            $params
        );

        foreach (array_keys(self::COURSE_PREFERENCES) as $prefix) {
            $prefs = $DB->get_records_select(
                'user_preferences',
                'userid = :userid AND ' . $DB->sql_like('name', ':prefix', false),
                [
                    'userid' => $userid,
                    'prefix' => $DB->sql_like_escape($prefix) . '%',
                ],
                '',
                'id, name'
            );
            foreach ($prefs as $pref) {
                $suffix = substr($pref->name, strlen($prefix));
                if (!ctype_digit($suffix) || (int) $suffix <= SITEID) {
                    continue;
                }
                $coursecontext = \context_course::instance((int) $suffix, IGNORE_MISSING);
                if ($coursecontext) {
                    $contextlist->add_from_sql(
                        'SELECT id FROM {context} WHERE id = :contextid',
                        ['contextid' => $coursecontext->id]
                    );
                }
            }
        }
    }

    /**
     * Add users who have queued proactive data in a course.
     *
     * @param userlist $userlist User list to populate.
     * @param int $courseid Course id.
     */
    private static function add_local_users(userlist $userlist, int $courseid): void {
        $userlist->add_from_sql(
            'userid',
            "SELECT userid
               FROM {" . tutor_proactive_context_service::TABLE . "}
              WHERE courseid = :courseid",
            ['courseid' => $courseid]
        );

        foreach (array_keys(self::COURSE_PREFERENCES) as $prefix) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid
                   FROM {user_preferences}
                  WHERE name = :name",
                ['name' => $prefix . $courseid]
            );
        }
    }

    /**
     * Export queued proactive context for one user in a course.
     *
     * @param \context $context Course context.
     * @param int $userid User id.
     * @param int $courseid Course id.
     */
    private static function export_pending(\context $context, int $userid, int $courseid): void {
        global $DB;

        $records = $DB->get_records(tutor_proactive_context_service::TABLE, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        if ($records === []) {
            return;
        }

        $queued = [];
        foreach ($records as $record) {
            $queued[] = (object) [
                'message' => (string) $record->message,
                'timemodified' => transform::datetime((int) $record->timemodified),
            ];
        }

        writer::with_context($context)->export_data(
            [get_string('privacy:path:pending', 'block_dixeo_tutor')],
            (object) ['queued' => $queued]
        );
    }

    /**
     * Export the per-course preferences stored for this user and course.
     *
     * @param \context $context Course context.
     * @param int $userid User id.
     * @param int $courseid Course id.
     */
    private static function export_course_preferences(\context $context, int $userid, int $courseid): void {
        foreach (self::COURSE_PREFERENCES as $prefix => [$stringid, $type]) {
            $name = $prefix . $courseid;
            $value = get_user_preferences($name, null, $userid);
            if ($value === null) {
                continue;
            }

            writer::with_context($context)->export_user_preference(
                'block_dixeo_tutor',
                $name,
                $type === self::PREF_TIME ? transform::datetime((int) $value) : (string) $value,
                get_string($stringid, 'block_dixeo_tutor')
            );
        }
    }

    /**
     * Delete queued proactive rows for a course, optionally limited to one user.
     *
     * @param int $courseid Course id.
     * @param int|null $userid User id, or null for every user in the course.
     */
    private static function delete_pending(int $courseid, ?int $userid): void {
        global $DB;

        $conditions = ['courseid' => $courseid];
        if ($userid !== null) {
            $conditions['userid'] = $userid;
        }
        $DB->delete_records(tutor_proactive_context_service::TABLE, $conditions);
    }

    /**
     * Remove the per-course preferences of a course.
     *
     * @param int $courseid Course id.
     * @param int|null $userid User id, or null for every user.
     */
    private static function delete_course_preferences(int $courseid, ?int $userid): void {
        global $DB;

        foreach (array_keys(self::COURSE_PREFERENCES) as $prefix) {
            $name = $prefix . $courseid;
            if ($userid === null) {
                $DB->delete_records('user_preferences', ['name' => $name]);
            } else {
                unset_user_preference($name, $userid);
            }
        }
    }

    /**
     * Resolve the course a context belongs to, if it is a course context.
     *
     * @param \context $context The context to inspect.
     * @return int The course ID, or 0 when the context is not a course.
     */
    private static function course_id(\context $context): int {
        return $context->contextlevel === CONTEXT_COURSE ? (int) $context->instanceid : 0;
    }
}
