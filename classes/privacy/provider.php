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
 * Privacy provider for tutor conversations.
 *
 * The block owns no database table and no file area: conversations live in the Dixeo
 * API, keyed by course, user and site namespace. Every method below is a translation
 * from Moodle privacy vocabulary to {@see tutor_service}, which owns the protocol.
 *
 * Erasure additionally queues {@see erase_conversations} whenever the API is out of
 * reach, because core has no retry of its own for a failed privacy callback. On a site
 * with no API key every method is inert: nothing was ever sent, so nothing is held.
 *
 * An export cannot be replayed that way, because the archive is assembled only once, so
 * the read paths settle for visibility instead: {@see self::report_failure()}.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the type of personal data transferred by this plugin.
     *
     * @param collection $collection The privacy metadata collection.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
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
        if ($courseid === 0 || !self::is_configured()) {
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
        if (!self::is_configured()) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        $service = self::service();
        $reported = false;

        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::course_id($context);
            if ($courseid === 0) {
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
        if ($courseid === 0 || !self::is_configured()) {
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
        if (!self::is_configured()) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        $failure = null;

        foreach ($contextlist->get_contexts() as $context) {
            $courseid = self::course_id($context);
            if ($courseid === 0) {
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
        if ($courseid === 0 || !self::is_configured()) {
            return;
        }

        $failure = null;
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
