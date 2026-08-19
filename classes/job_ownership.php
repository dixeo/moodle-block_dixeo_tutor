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
 * Session-scoped registry of tutor job IDs issued to the current user.
 *
 * Provides defense-in-depth ownership checks for get_job_status when
 * local_dixeo does not bind jobid to userid/courseid.
 *
 * Stored in an application cache (keyed by PHP session id) rather than $SESSION
 * so registrations survive mid-request \core\session\manager::write_close()
 * (e.g. during file sync before send_message returns).
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

/**
 * Tracks job IDs created via send_message for the current Moodle session.
 */
class job_ownership {
    /** @var int How long a registered job remains valid (seconds). Aligns with client poll timeout. */
    public const TTL_SECONDS = 15 * MINSECS;

    /**
     * Whether the string is a UUID (versions 1–5).
     *
     * @param string $jobid Candidate job identifier.
     * @return bool
     */
    public static function is_valid_jobid(string $jobid): bool {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $jobid
        );
    }

    /**
     * Validate jobid format or throw.
     *
     * @param string $jobid Candidate job identifier.
     * @throws \invalid_parameter_exception
     */
    public static function require_valid_jobid(string $jobid): void {
        if (!self::is_valid_jobid($jobid)) {
            throw new \invalid_parameter_exception('Invalid job ID format');
        }
    }

    /**
     * Remember a jobid issued to the current user for a course.
     *
     * @param int $userid Moodle user id.
     * @param int $courseid Course id.
     * @param string $jobid Job UUID from the API.
     */
    public static function register(int $userid, int $courseid, string $jobid): void {
        if ($userid < 1 || $courseid < 1 || $jobid === '' || !self::is_valid_jobid($jobid)) {
            return;
        }

        self::cache()->set(
            self::cache_key($userid, $courseid, $jobid),
            time() + self::TTL_SECONDS
        );
    }

    /**
     * Whether this session has registered the job for the user and course.
     *
     * @param int $userid Moodle user id.
     * @param int $courseid Course id.
     * @param string $jobid Job UUID.
     * @return bool
     */
    public static function is_owned(int $userid, int $courseid, string $jobid): bool {
        $expiry = self::cache()->get(self::cache_key($userid, $courseid, $jobid));
        return is_int($expiry) && $expiry >= time();
    }

    /**
     * Require ownership or throw a generic access error (no existence leak).
     *
     * @param int $userid Moodle user id.
     * @param int $courseid Course id.
     * @param string $jobid Job UUID.
     * @throws \moodle_exception
     */
    public static function require_owned(int $userid, int $courseid, string $jobid): void {
        if (!self::is_owned($userid, $courseid, $jobid)) {
            throw new \moodle_exception('error_job_access', 'block_dixeo_tutor');
        }
    }

    /**
     * Application cache for owned job markers.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make('block_dixeo_tutor', 'owned_jobs');
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
        $job = strtolower(str_replace('-', '', $jobid));
        return $userid . '_' . $courseid . '_' . $job . '_' . $sid;
    }
}
