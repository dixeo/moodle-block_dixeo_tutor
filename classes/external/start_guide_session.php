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
 * Web service to start a Guide me session from setup submit.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\client_response;
use block_dixeo_tutor\job_ownership;
use block_dixeo_tutor\page_context;
use block_dixeo_tutor\service\tutor_mode_service;
use block_dixeo_tutor\service\tutor_proactive_context_service;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\tutor_message;

/**
 * Queue guide_started with the learner prompt and flush to the API.
 */
class start_guide_session extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
            'userprompt' => new external_value(PARAM_RAW, 'What the learner wants guidance on'),
            'pageurl' => new external_value(PARAM_URL, 'The current page URL', VALUE_DEFAULT, ''),
            'cmid' => new external_value(PARAM_INT, 'Course module id when on an activity page', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid
     * @param string $userprompt
     * @param string $pageurl
     * @param int $cmid
     * @return array
     */
    public static function execute(int $courseid, string $userprompt, string $pageurl = '', int $cmid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userprompt' => $userprompt,
            'pageurl' => $pageurl,
            'cmid' => $cmid,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        $prompt = trim($params['userprompt']);
        if ($prompt === '') {
            throw new \invalid_parameter_exception('Guide prompt cannot be empty');
        }
        if (strlen($prompt) > 2000) {
            throw new \invalid_parameter_exception('Guide prompt cannot exceed 2000 characters');
        }

        $userid = (int) $USER->id;
        $modeservice = new tutor_mode_service();
        $modeservice->set_mode($userid, $params['courseid'], tutor_message::MODE_GUIDE);

        $proactive = new tutor_proactive_context_service();
        $proactive->queue_guide_started($userid, $params['courseid'], $prompt);

        page_context::sanitize_pageurl($params['pageurl'] ?? '', (int) $params['courseid']);
        $sanitizedcmid = \local_dixeo\service\tutor_usage_recorder::sanitize_cmid(
            (int) $params['courseid'],
            (int) $params['cmid']
        );

        try {
            $result = $proactive->flush(
                $userid,
                (int) $params['courseid'],
                page_context::sanitize_pageurl($params['pageurl'] ?? '', (int) $params['courseid']),
                $sanitizedcmid
            );
        } catch (api_exception $e) {
            return client_response::send_message_error($e);
        }

        if ($result === null) {
            return [
                'flushed' => false,
                'completed' => true,
                'jobid' => '',
                'progress' => 100,
            ];
        }

        $payload = $result->to_array();
        if (!empty($payload['jobid'])) {
            job_ownership::register($userid, (int) $params['courseid'], (string) $payload['jobid']);
        }

        return [
            'flushed' => true,
            'completed' => !empty($payload['completed']),
            'jobid' => (string) ($payload['jobid'] ?? ''),
            'progress' => (int) ($payload['progress'] ?? 0),
            'errormessage' => $payload['errormessage'] ?? null,
            'errorcode' => $payload['errorcode'] ?? null,
        ];
    }

    /**
     * Describe return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'flushed' => new external_value(PARAM_BOOL, 'Whether a proactive job was submitted'),
            'completed' => new external_value(PARAM_BOOL, 'Whether the job completed synchronously'),
            'jobid' => new external_value(PARAM_RAW, 'Job UUID when submitted'),
            'progress' => new external_value(PARAM_INT, 'Progress percentage'),
            'errormessage' => new external_value(PARAM_TEXT, 'Error message if failed', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT, 'Error code if failed', VALUE_OPTIONAL),
        ]);
    }
}
