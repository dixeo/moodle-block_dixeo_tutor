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
 * Web service to submit a tutor message.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\client_response;
use block_dixeo_tutor\event\message_sent;
use block_dixeo_tutor\job_ownership;
use block_dixeo_tutor\page_context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;

/**
 * External function to submit a tutor message.
 */
class send_message extends external_api {
    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
            'message' => new external_value(PARAM_RAW, 'The user message'),
            'pageurl' => new external_value(PARAM_URL, 'The current page URL', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Submit a tutor message (non-blocking).
     * Returns immediately with job_id. Use get_job_status to poll.
     *
     * @param int $courseid The course ID.
     * @param string $message The user message.
     * @param string $pageurl The current page URL.
     * @return array The pending operation result with job_id.
     */
    public static function execute(int $courseid, string $message, string $pageurl = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'message' => $message,
            'pageurl' => $pageurl,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        // Server-side message validation.
        $message = trim($params['message']);
        if (empty($message)) {
            throw new \invalid_parameter_exception('Message cannot be empty');
        }
        if (strlen($message) > 2000) {
            throw new \invalid_parameter_exception('Message cannot exceed 2000 characters');
        }
        $params['message'] = $message;

        // Restrict page context to this Moodle site and drop query/fragment; never trust raw client URLs.
        $pagecontext = page_context::sanitize_pageurl($params['pageurl'] ?? '', (int) $params['courseid']);

        try {
            $service = service_factory::get_tutor_service();
            $result = $service->submit_message(
                $params['courseid'],
                $USER->id,
                $params['message'],
                $pagecontext
            );

            $payload = $result->to_array();
            if (!empty($payload['jobid'])) {
                job_ownership::register((int) $USER->id, (int) $params['courseid'], (string) $payload['jobid']);
                message_sent::create_for_course(
                    (int) $params['courseid'],
                    (int) $USER->id,
                    (string) $payload['jobid']
                )->trigger();
            }

            return client_response::sanitize_send_message($payload);
        } catch (api_exception $e) {
            return client_response::send_message_error($e);
        }
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'completed' => new external_value(PARAM_BOOL, 'Whether the operation has completed'),
            'jobid' => new external_value(PARAM_RAW, 'The job UUID'),
            'result' => new external_single_structure([
                'data' => new external_single_structure([
                    'name' => new external_value(PARAM_RAW, 'Generated name', VALUE_OPTIONAL),
                    'content' => new external_value(PARAM_RAW, 'Generated content', VALUE_OPTIONAL),
                ], 'Result data', VALUE_OPTIONAL),
            ], 'The result data', VALUE_OPTIONAL),
            'creditsused' => new external_value(PARAM_INT, 'Credits consumed', VALUE_OPTIONAL),
            'status' => new external_value(PARAM_ALPHA, 'Current status', VALUE_OPTIONAL),
            'progress' => new external_value(PARAM_INT, 'Progress percentage (0-100)'),
            'errormessage' => new external_value(PARAM_TEXT, 'Error message if failed', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT, 'Error code if failed', VALUE_OPTIONAL),
        ]);
    }
}
