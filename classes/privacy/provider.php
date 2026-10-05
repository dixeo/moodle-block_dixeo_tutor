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

use block_dixeo_tutor\event\privacy_request_failed;
use block_dixeo_tutor\service\tutor_mode_service;
use block_dixeo_tutor\service\tutor_proactive_context_service;
use block_dixeo_tutor\service\tutor_read_state_service;
use block_dixeo_tutor\task\erase_conversations;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_service;

/**
 * Privacy provider for tutor conversations and local pending context.
 *
 * Conversations live in the Dixeo API (via {@see tutor_service}). Queued proactive
 * context may also sit in block_dixeo_tutor_pending until flushed. Request
 * methods translate Moodle privacy vocabulary to the tutor API protocol.
 *
 * Erasure additionally queues {@see erase_conversations} whenever the API is out of
 * reach, because core has no retry of its own for a failed privacy callback. On a site
 * with no API key every conversation method is inert: nothing was ever sent.
 *
 * An export cannot be replayed that way, because the archive is assembled only once, so
 * the read paths settle for visibility instead: {@see self::report_failure()}.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
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
            ],
            'privacy:metadata:externalpurpose'
        );

        $collection->add_user_preference(
            tutor_read_state_service::PREF_LAST_READ_PREFIX,
            'privacy:metadata:lastread'
        );

        $collection->add_user_preference(
            tutor_mode_service::PREF_MODE_PREFIX,
            'privacy:metadata:tutormode'
        );

        $collection->add_user_preference(
            tutor_mode_service::PREF_LAST_ACTIVITY_PREFIX,
            'privacy:metadata:tutormodeactivity'
        );

        $collection->add_user_preference(
            tutor_proactive_context_service::PREF_LAST_PROACTIVE_PREFIX,
            'privacy:metadata:lastproactive'
        );

        return $collection;
    }

    /**
     * Get the course contexts in which the user holds a tutor conversation.
     *
     * @param int $userid The user ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        self::add_local_contexts($contextlist, $userid);
        if (!self::is_configured()) {
            return $contextlist;
        }

        try {
            $conversations = self::service()->list_conversations(null, $userid);
        } catch (api_exception $e) {
            // Shared by export and erasure, and erasing on what may be an export request
            // would be destructive: report the outage and hand back nothing.
            self::report_failure($e, $userid);
            return $contextlist;
        }

        $courseids = array_filter(array_unique(array_column($conversations, 'courseid')));
        if (empty($courseids)) {
            return $contextlist;
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $params['contextlevel'] = CONTEXT_COURSE;

        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
              WHERE ctx.contextlevel = :contextlevel AND ctx.instanceid {$insql}",
            $params
        );

        return $contextlist;
    }

    /**
     * Get the users holding a tutor conversation in the given course context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $courseid = self::course_id($userlist->get_context());
        if ($courseid === 0) {
            return;
        }

        self::add_local_users($userlist, $courseid);
        if (!self::is_configured()) {
            return;
        }

        try {
            $conversations = self::service()->list_conversations($courseid);
        } catch (api_exception $e) {
            // No single subject here, so the notification names none.
            self::report_failure($e, 0);
            return;
        }

        $userlist->add_users(array_filter(array_unique(array_column($conversations, 'userid'))));
    }

    /**
     * Export the user's tutor conversation for each approved course context.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        $configured = self::is_configured();
        $service = $configured ? self::service() : null;
        $reported = false;

        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::course_id($context);
            if ($courseid === 0) {
                continue;
            }

            self::export_pending($context, $userid, $courseid);
            self::export_proactive_preference($context, $userid, $courseid);
            if (!$configured) {
                continue;
            }

            try {
                $messages = $service->export_conversation($courseid, $userid);
            } catch (api_exception $e) {
                // One outage fails every course, so warn once and keep exporting the
                // courses the API can still answer for.
                if (!$reported) {
                    self::report_failure($e, $userid);
                    $reported = true;
                }
                continue;
            }

            if (empty($messages)) {
                continue;
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:path:conversation', 'block_dixeo_tutor')],
                (object) ['messages' => array_map(self::export_message(...), $messages)]
            );
        }
    }

    /**
     * Erase every user's tutor conversation in the given course context.
     *
     * @param \context $context The context to purge.
     * @throws api_exception When the API is unreachable, once the retry is queued.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        $courseid = self::course_id($context);
        if ($courseid === 0) {
            return;
        }

        self::delete_pending($courseid, null);
        self::delete_proactive_preference($courseid, null);
        if (!self::is_configured()) {
            return;
        }

        $failure = self::erase($courseid, null);
        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Erase the user's tutor conversation in each approved course context.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @throws api_exception When the API is unreachable, once the retries are queued.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        $failure = null;

        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::course_id($context);
            if ($courseid === 0) {
                continue;
            }

            self::delete_pending($courseid, $userid);
            self::delete_proactive_preference($courseid, $userid);
            if (!self::is_configured()) {
                continue;
            }

            // Every course is attempted even after a failure, so each one gets its own
            // retry queued rather than only the courses before the first outage.
            $error = self::erase($courseid, $userid);
            $failure ??= $error;
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Erase the approved users' tutor conversations in a course context.
     *
     * @param approved_userlist $userlist The approved users.
     * @throws api_exception When the API is unreachable, once the retries are queued.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $courseid = self::course_id($userlist->get_context());
        if ($courseid === 0) {
            return;
        }

        $failure = null;
        foreach ($userlist->get_userids() as $userid) {
            self::delete_pending($courseid, (int) $userid);
            self::delete_proactive_preference($courseid, (int) $userid);
        }
        if (!self::is_configured()) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $error = self::erase($courseid, (int) $userid);
            $failure ??= $error;
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Erase one scope, queueing an adhoc retry when the API cannot be reached.
     *
     * {@see \core_privacy\manager} catches everything a provider throws, logs it as
     * debugging and carries on marking the request complete. Without the queued task
     * the user would be told their data is gone while it still sits in the API, with
     * nothing left to replay the call.
     *
     * @param int|null $courseid The course to erase, or null for every course.
     * @param int|null $userid The user to erase, or null for every user.
     * @return api_exception|null The failure to re-throw once every scope is handled.
     */
    private static function erase(?int $courseid, ?int $userid): ?api_exception {
        try {
            self::service()->delete_conversations($courseid, $userid);
            return null;
        } catch (api_exception $e) {
            erase_conversations::queue($courseid, $userid);
            return $e;
        }
    }

    /**
     * Make an unreachable API visible on the read paths, where no retry is possible.
     *
     * {@see \core_privacy\manager} logs whatever a provider throws as debugging and still
     * marks the request complete, and an export archive is assembled only once. Without
     * this the officer would read the outage as "this user holds no tutor data".
     *
     * @param api_exception $e The API failure.
     * @param int $userid The subject of the request, or 0 when it targets a whole context.
     */
    private static function report_failure(api_exception $e, int $userid): void {
        privacy_request_failed::create_for_user($userid, $e->get_error_code())->trigger();

        $user = $userid > 0 ? \core_user::get_user($userid) : false;
        $subject = get_string('privacyfailure_subject', 'block_dixeo_tutor');
        $body = get_string(
            'privacyfailure_body',
            'block_dixeo_tutor',
            $user ? fullname($user) : get_string('unknownuser')
        );

        foreach (get_admins() as $admin) {
            $message = new \core\message\message();
            $message->component = 'block_dixeo_tutor';
            $message->name = 'privacyfailure';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $admin;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = text_to_html($body);
            $message->smallmessage = $subject;
            $message->notification = 1;
            message_send($message);
        }

        debugging('Dixeo tutor privacy request failed: ' . $e->getMessage(), DEBUG_NORMAL);
    }

    /**
     * Shape a message for the privacy export.
     *
     * @param array $message Message with id, role, content and time keys.
     * @return \stdClass Exportable message.
     */
    private static function export_message(array $message): \stdClass {
        return (object) [
            'role' => (string) ($message['role'] ?? ''),
            'content' => (string) ($message['content'] ?? ''),
            'time' => transform::datetime((int) ($message['time'] ?? 0)),
        ];
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

        $prefix = tutor_proactive_context_service::PREF_LAST_PROACTIVE_PREFIX;
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
            $courseid = (int) substr($pref->name, strlen($prefix));
            if ($courseid > 0) {
                $contextlist->add_from_sql(
                    'SELECT id FROM {context} WHERE id = :contextid',
                    ['contextid' => \context_course::instance($courseid)->id]
                );
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

        $userlist->add_from_sql(
            'userid',
            "SELECT userid
               FROM {user_preferences}
              WHERE name = :name",
            ['name' => tutor_proactive_context_service::PREF_LAST_PROACTIVE_PREFIX . $courseid]
        );
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
     * Export the last-proactive timestamp stored for this user and course.
     *
     * @param \context $context Course context.
     * @param int $userid User id.
     * @param int $courseid Course id.
     */
    private static function export_proactive_preference(\context $context, int $userid, int $courseid): void {
        $name = tutor_proactive_context_service::PREF_LAST_PROACTIVE_PREFIX . $courseid;
        $value = get_user_preferences($name, null, $userid);
        if ($value === null) {
            return;
        }

        writer::with_context($context)->export_user_preference(
            'block_dixeo_tutor',
            $name,
            transform::datetime((int) $value),
            get_string('privacy:metadata:lastproactive', 'block_dixeo_tutor')
        );
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
     * Remove the last-proactive preference for a course.
     *
     * @param int $courseid Course id.
     * @param int|null $userid User id, or null for every user.
     */
    private static function delete_proactive_preference(int $courseid, ?int $userid): void {
        global $DB;

        $name = tutor_proactive_context_service::PREF_LAST_PROACTIVE_PREFIX . $courseid;
        if ($userid === null) {
            $DB->delete_records('user_preferences', ['name' => $name]);
            return;
        }

        unset_user_preference($name, $userid);
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

    /**
     * Get the tutor service owning the Dixeo conversation protocol.
     *
     * @return tutor_service The service instance.
     */
    private static function service(): tutor_service {
        return service_factory::get_tutor_service();
    }

    /**
     * Whether this site has an API relationship with Dixeo at all.
     *
     * Without a key nothing was ever sent, so there is nothing to find, export or
     * erase, and calling the API would only raise an authentication error.
     *
     * @return bool True when the Dixeo API is configured.
     */
    private static function is_configured(): bool {
        return service_factory::get_client()->is_configured();
    }
}
