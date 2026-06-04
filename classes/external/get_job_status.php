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
 * Web service to get the status of a tutor job.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\client_response;
use block_dixeo_tutor\job_ownership;
use block_dixeo_tutor\job_status_audit;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;

/**
 * External function to get tutor job status.
 */
class get_job_status extends external_api {
    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
            'jobid' => new external_value(PARAM_RAW, 'The job UUID'),
        ]);
    }

    /**
     * Get the current status of a tutor job.
     *
     * @param int $courseid The course ID.
     * @param string $jobid The job UUID.
     * @return array The job status.
     */
    public static function execute(int $courseid, string $jobid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'jobid' => $jobid,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        // Harden jobid and bind it to the session that issued it (defense-in-depth vs IDOR).
        job_ownership::require_valid_jobid($params['jobid']);
        job_ownership::require_owned((int) $USER->id, (int) $params['courseid'], $params['jobid']);

        try {
            $service = service_factory::get_job_service();
            $status = $service->get_job_status($params['jobid'], (int) $params['courseid']);

            job_status_audit::maybe_emit_terminal_viewed(
                (int) $params['courseid'],
                (int) $USER->id,
                $params['jobid'],
                $status->status
            );

            return client_response::sanitize_job_status($status->to_array());
        } catch (api_exception $e) {
            return client_response::job_status_error($params['jobid'], $e);
        }
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'jobid' => new external_value(PARAM_RAW, 'The job UUID'),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Job type', VALUE_OPTIONAL),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Current status'),
            'progress' => new external_value(PARAM_INT, 'Progress percentage (0-100)'),
            'createdat' => new external_value(PARAM_INT, 'Creation timestamp'),
            'updatedat' => new external_value(PARAM_INT, 'Last update timestamp', VALUE_OPTIONAL),
            'completedat' => new external_value(PARAM_INT, 'Completion timestamp', VALUE_OPTIONAL),
            'result' => new external_single_structure([
                'reply' => new external_value(PARAM_RAW, 'The assistant reply', VALUE_OPTIONAL),
            ], 'Result data', VALUE_OPTIONAL),
            'creditsused' => new external_value(PARAM_INT, 'Credits consumed', VALUE_OPTIONAL),
            'error' => new external_single_structure([
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Error type', VALUE_OPTIONAL),
                'title' => new external_value(PARAM_TEXT, 'Error title', VALUE_OPTIONAL),
                'status' => new external_value(PARAM_INT, 'HTTP status code', VALUE_OPTIONAL),
                'detail' => new external_value(PARAM_TEXT, 'Error detail', VALUE_OPTIONAL),
            ], 'Error details (sanitized for clients)', VALUE_OPTIONAL),
            'processingtimeseconds' => new external_value(PARAM_FLOAT, 'Processing time', VALUE_OPTIONAL),
        ]);
    }
}
