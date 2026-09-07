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
 * Session-scoped deduplication for terminal tutor job-status audit events.
 *
 * Stored in an application cache (keyed by PHP session id) rather than $SESSION,
 * like {@see job_ownership}, so get_job_status can run with a read-only session
 * and stop serializing every poll on the session lock.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use block_dixeo_tutor\event\job_status_viewed;
use local_dixeo\dto\job_status;

/**
 * Emits job_status_viewed once per owned job when polling reaches a terminal hub status.
 */
class job_status_audit {
    /** @var int How long an emitted marker is retained (seconds). Aligns with job_ownership. */
    public const TTL_SECONDS = 15 * MINSECS;

    /**
     * Hub statuses that end client polling for a tutor job.
     *
     * @return string[]
     */
    public static function terminal_statuses(): array {
        return [
            job_status::STATUS_COMPLETED,
            job_status::STATUS_FAILED,
            'cancelled',
        ];
    }

    /**
     * Whether a hub status is terminal for tutor polling.
     *
     * @param string $status Status code from the hub.
     * @return bool
     */
    public static function is_terminal_status(string $status): bool {
        return in_array($status, self::terminal_statuses(), true);
    }

    /**
     * Emit job_status_viewed once per job when a terminal status is first observed.
     *
     * @param int $courseid Course id.
     * @param int $userid Acting user id.
     * @param string $jobid Remote job UUID.
     * @param string $status Current hub status.
     * @return void
     */
    public static function maybe_emit_terminal_viewed(int $courseid, int $userid, string $jobid, string $status): void {
        if (!self::is_terminal_status($status)) {
            return;
        }

        if (self::has_emitted($userid, $courseid, $jobid)) {
            return;
        }

        job_status_viewed::create_for_course($courseid, $userid, $jobid, $status)->trigger();
        self::mark_emitted($userid, $courseid, $jobid, $status);
    }

    /**
     * Whether this session already logged a terminal status view for the job.
     *
     * @param int $userid Moodle user id.
     * @param int $courseid Course id.
     * @param string $jobid Job UUID.
     * @return bool
     */
    public static function has_emitted(int $userid, int $courseid, string $jobid): bool {
        return self::cache()->get(self::cache_key($userid, $courseid, $jobid)) !== false;
    }

    /**
     * Record that a terminal status audit event was emitted for the job.
     *
     * @param int $userid Moodle user id.
     * @param int $courseid Course id.
     * @param string $jobid Job UUID.
     * @param string $status Terminal status that was logged.
     * @return void
     */
    public static function mark_emitted(int $userid, int $courseid, string $jobid, string $status): void {
        if ($userid < 1 || $courseid < 1 || $jobid === '') {
            return;
        }

        self::cache()->set(self::cache_key($userid, $courseid, $jobid), $status);
    }

    /**
     * Forget the emitted marker for a job (test support and re-audit after cancellation).
     *
     * @param int $userid Moodle user id.
     * @param int $courseid Course id.
     * @param string $jobid Job UUID.
     * @return void
     */
    public static function forget(int $userid, int $courseid, string $jobid): void {
        self::cache()->delete(self::cache_key($userid, $courseid, $jobid));
    }

    /**
     * Application cache for emitted terminal-status markers.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make('block_dixeo_tutor', 'terminal_status_audit');
    }

    /**
     * Cache key scoped to user, course, job, and PHP session.
     *
     * @param int $userid Moodle user id.
     * @param int $courseid Course id.
     * @param string $jobid Job UUID.
     * @return string
     */
    private static function cache_key(int $userid, int $courseid, string $jobid): string {
        // Simplekeys allows only a-zA-Z0-9_ (no UUID hyphens).
        $sid = preg_replace('/[^a-zA-Z0-9_]/', '', session_id() ?: 'nosess') ?: 'nosess';
        $job = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $jobid) ?? '');
        return $userid . '_' . $courseid . '_' . $job . '_' . $sid;
    }
}
