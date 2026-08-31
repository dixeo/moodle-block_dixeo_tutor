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
 * Adhoc task erasing tutor conversations held by the Dixeo API.
 *
 * Erasure must survive an unreachable API: core swallows whatever a privacy callback
 * throws and marks the request complete, and a deleted course takes its contexts with
 * it, so neither path can be retried by Moodle. Queueing the erasure hands the retry
 * to the adhoc runner.
 *
 * That retry is finite. The runner doubles the faildelay from 60s up to a 24h cap and
 * spends one attempt per failure; the default budget of 12 attempts is exhausted after
 * about 2.4 days, so {@see queue()} raises it to {@see RETRY_ATTEMPTS}, which spans
 * roughly 20 days of outage. Past that the task stops being picked up, stays visible as
 * failed in the task admin, and core purges it 4 weeks after its first run.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\task;

use core\task\adhoc_task;
use core\task\manager;
use local_dixeo\external\service_factory;

/**
 * Erase the conversations of a course, of a user, or of a single user in a course.
 */
class erase_conversations extends adhoc_task {
    /** @var int Failed runs to allow, covering roughly 20 days of API outage. */
    private const RETRY_ATTEMPTS = 30;

    /**
     * Get the task name for display.
     *
     * @return string The task name.
     */
    public function get_name(): string {
        return get_string('task_erase_conversations', 'block_dixeo_tutor');
    }

    /**
     * Queue an erasure for the given scope.
     *
     * Queueing checks for an existing task so a course erasure retried by several
     * privacy requests does not pile up duplicates.
     *
     * @param int|null $courseid The course to erase, or null for every course.
     * @param int|null $userid The user to erase, or null for every user.
     */
    public static function queue(?int $courseid, ?int $userid): void {
        // Without a key the site never sent anything, and the task would spend every
        // attempt on an authentication error it can only lose.
        if (!service_factory::get_client()->is_configured()) {
            return;
        }

        $task = new self();
        $task->set_custom_data((object) ['courseid' => $courseid, 'userid' => $userid]);
        $task->set_attempts_available(self::RETRY_ATTEMPTS);

        manager::queue_adhoc_task($task, true);
    }

    /**
     * Erase the conversations of the queued scope.
     *
     * Failures are deliberately left to propagate: the adhoc runner reschedules the
     * task, which is the only thing that turns a failed erasure into a retried one.
     *
     * @throws \local_dixeo\api\exception\api_exception When the API refuses or is down.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $courseid = isset($data->courseid) ? (int) $data->courseid : null;
        $userid = isset($data->userid) ? (int) $data->userid : null;

        $deleted = service_factory::get_tutor_service()->delete_conversations($courseid, $userid);

        mtrace("erase_conversations: erased {$deleted} conversation(s) " .
            '(courseid=' . ($courseid ?? 'all') . ', userid=' . ($userid ?? 'all') . ')');
    }
}
