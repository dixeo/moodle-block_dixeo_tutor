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
 * Web service to flush queued proactive tutor context.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\response_factory;
use block_dixeo_tutor\service\tutor_proactive_context_service;

/**
 * Flush pending proactive context for the current user in a course.
 */
class flush_pending_context extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
            'pageurl' => new external_value(PARAM_URL, 'The current page URL', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Flush queued proactive lines and submit to the tutor API.
     *
     * @param int $courseid
     * @param string $pageurl
     * @return array
     */
    public static function execute(int $courseid, string $pageurl = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'pageurl' => $pageurl,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        $pagecontext = !empty($params['pageurl']) ? $params['pageurl'] : '';

        $service = new tutor_proactive_context_service();
        try {
            $result = $service->flush($USER->id, $params['courseid'], $pagecontext, true);
        } catch (api_exception $e) {
            return response_factory::job_error($e);
        }

        if ($result === null) {
            return [
                'flushed' => false,
                'completed' => true,
                'jobid' => '',
                'progress' => 0,
            ];
        }

        $payload = $result->to_array();
        $payload['flushed'] = true;
        return $payload;
    }

    /**
     * Describe the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'flushed' => new external_value(PARAM_BOOL, 'Whether a queued message was sent', VALUE_DEFAULT, false),
            'completed' => new external_value(PARAM_BOOL, 'Whether the operation has completed'),
            'jobid' => new external_value(PARAM_RAW, 'The job UUID', VALUE_DEFAULT, ''),
            'result' => new external_single_structure([
                'data' => new external_single_structure([
                    'name' => new external_value(PARAM_RAW, 'Generated name', VALUE_OPTIONAL),
                    'content' => new external_value(PARAM_RAW, 'Generated content', VALUE_OPTIONAL),
                ], 'Result data', VALUE_OPTIONAL),
            ], 'The result data', VALUE_OPTIONAL),
            'creditsused' => new external_value(PARAM_INT, 'Credits consumed', VALUE_OPTIONAL),
            'status' => new external_value(PARAM_ALPHA, 'Current status', VALUE_OPTIONAL),
            'progress' => new external_value(PARAM_INT, 'Progress percentage (0-100)', VALUE_DEFAULT, 0),
            'errormessage' => new external_value(PARAM_RAW, 'Error message if failed', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT, 'Error code if failed', VALUE_OPTIONAL),
        ]);
    }
}
