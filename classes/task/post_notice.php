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
 * Adhoc task storing a tutor notice (greeting, quiz result, course completion) in the learner's conversation.
 *
 * The notice is a canned text, never generated: the API stores it as a tutor message without
 * an AI call. It then shows in the tutor history like any reply, unread until opened, and the
 * tutor reads it back on later turns, so "what you suggested after the quiz" makes sense to it.
 * A failed call is retried by the adhoc runner with the same idempotency key, so the notice is
 * stored once.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\task;

use block_dixeo_tutor\service\tutor_context_schema;
use block_dixeo_tutor\service\tutor_notice_service;
use core\task\adhoc_task;
use core\task\manager;
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;

/**
 * Store one notice in the tutor conversation of a learner in a course.
 */
class post_notice extends adhoc_task {
    /**
     * Get the task name for display.
     *
     * @return string The task name.
     */
    public function get_name(): string {
        return get_string('task_post_notice', 'block_dixeo_tutor');
    }

    /**
     * Queue a notice for a learner. The task runs as the learner, so the notice is in their language.
     *
     * @param int $userid
     * @param int $courseid
     * @param array $event Event the notice is about: type, time and its data.
     * @param string|null $text Text already shown to the learner, stored as is; rendered by the task otherwise.
     */
    public static function queue(int $userid, int $courseid, array $event, ?string $text = null): void {
        if (!service_factory::get_client()->is_configured()) {
            return;
        }

        $task = new self();
        $task->set_userid($userid);
        $task->set_custom_data((object) [
            'courseid' => $courseid,
            'event' => $event,
            'text' => $text,
            'key' => \core\uuid::generate(),
        ]);
        manager::queue_adhoc_task($task);
    }

    /**
     * Render the notice and store it in the conversation.
     *
     * @throws \local_dixeo\api\exception\api_exception When the API refuses or is down, for a retry.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $userid = (int) $this->get_userid();
        $courseid = (int) $data->courseid;
        $event = (array) $data->event;

        // The course or the learner may be gone by the time the task runs.
        $course = $DB->get_record('course', ['id' => $courseid], 'id, lang');
        $user = \core_user::get_user($userid, 'id, deleted');
        if (!$course || !$user || $user->deleted) {
            return;
        }

        $text = $data->text ?? $this->render($event, $userid, $courseid, (string) $course->lang);
        if ($text === null) {
            return;
        }

        service_factory::get_tutor_service()->add_message(
            $courseid,
            $userid,
            tutor_message::notice($text, tutor_context_schema::proactive_context([$event], $userid, $courseid)),
            (string) $data->key
        );
    }

    /**
     * Notice text in the course language when it forces one, else in the learner's.
     *
     * @param array $event
     * @param int $userid
     * @param int $courseid
     * @param string $courselang
     * @return string|null
     */
    private function render(array $event, int $userid, int $courseid, string $courselang): ?string {
        $previouslang = force_current_language($courselang);
        try {
            return (new tutor_notice_service())->text($event, $userid, $courseid);
        } finally {
            force_current_language($previouslang);
        }
    }
}
