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

    /** @var string Session property that holds the registry. */
    private const SESSION_KEY = 'block_dixeo_tutor_owned_jobs';

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
        global $SESSION;

        if ($userid < 1 || $courseid < 1 || $jobid === '' || !self::is_valid_jobid($jobid)) {
            return;
        }

        // Copy + replace root key so Moodle session handlers detect a dirty write
        // (in-place nested mutation can stay in-request-only and fail the next AJAX poll).
        $registry = [];
        if (isset($SESSION->{self::SESSION_KEY}) && is_array($SESSION->{self::SESSION_KEY})) {
            $registry = $SESSION->{self::SESSION_KEY};
        }
        $SESSION->{self::SESSION_KEY} = $registry;
        self::prune_expired();

        $registry = $SESSION->{self::SESSION_KEY};
        if (!isset($registry[$userid]) || !is_array($registry[$userid])) {
            $registry[$userid] = [];
        }
        if (!isset($registry[$userid][$courseid]) || !is_array($registry[$userid][$courseid])) {
            $registry[$userid][$courseid] = [];
        }
        $registry[$userid][$courseid][$jobid] = time() + self::TTL_SECONDS;
        $SESSION->{self::SESSION_KEY} = $registry;
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
        global $SESSION;

        self::ensure_session_structure();
        self::prune_expired();

        $expiry = $SESSION->{self::SESSION_KEY}[$userid][$courseid][$jobid] ?? null;
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
     * Ensure the SESSION property exists as the expected nested array.
     */
    private static function ensure_session_structure(): void {
        global $SESSION;

        if (!isset($SESSION->{self::SESSION_KEY}) || !is_array($SESSION->{self::SESSION_KEY})) {
            $SESSION->{self::SESSION_KEY} = [];
        }
    }

    /**
     * Drop expired job registrations from the session.
     */
    private static function prune_expired(): void {
        global $SESSION;

        $now = time();
        $registry = &$SESSION->{self::SESSION_KEY};

        foreach ($registry as $userid => $courses) {
            if (!is_array($courses)) {
                unset($registry[$userid]);
                continue;
            }
            foreach ($courses as $courseid => $jobs) {
                if (!is_array($jobs)) {
                    unset($registry[$userid][$courseid]);
                    continue;
                }
                foreach ($jobs as $jobid => $expiry) {
                    if (!is_int($expiry) || $expiry < $now) {
                        unset($registry[$userid][$courseid][$jobid]);
                    }
                }
                if (empty($registry[$userid][$courseid])) {
                    unset($registry[$userid][$courseid]);
                }
            }
            if (empty($registry[$userid])) {
                unset($registry[$userid]);
            }
        }
    }
}
