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
 * Submit practice quiz generation job.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\client_response;
use block_dixeo_tutor\job_ownership;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;

/**
 * Web service: start practice quiz generation for the tutor.
 */
class submit_practice_quiz extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'scope' => new external_value(PARAM_ALPHA, 'Scope: course, section, or activity'),
            'sectionnum' => new external_value(PARAM_INT, 'Section number when scope is section', VALUE_DEFAULT, 0),
            'cmid' => new external_value(PARAM_INT, 'Course module id when scope is activity', VALUE_DEFAULT, 0),
            'count' => new external_value(PARAM_INT, 'Number of questions (3-10)', VALUE_DEFAULT, 5),
            'difficulty' => new external_value(PARAM_ALPHA, 'Difficulty: easy, medium, hard', VALUE_DEFAULT, 'medium'),
            'topictitle' => new external_value(PARAM_TEXT, 'Human-readable topic label'),
            'language' => new external_value(PARAM_LANG, 'Output language for generated content', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid
     * @param string $scope
     * @param int $sectionnum
     * @param int $cmid
     * @param int $count
     * @param string $difficulty
     * @param string $topictitle
     * @param string $language
     * @return array
     */
    public static function execute(
        int $courseid,
        string $scope,
        int $sectionnum = 0,
        int $cmid = 0,
        int $count = 5,
        string $difficulty = 'medium',
        string $topictitle = '',
        string $language = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'scope' => $scope,
            'sectionnum' => $sectionnum,
            'cmid' => $cmid,
            'count' => $count,
            'difficulty' => $difficulty,
            'topictitle' => $topictitle,
            'language' => $language,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        try {
            $service = service_factory::get_practice_quiz_service();
            $result = $service->submit_from_setup(
                (int) $params['courseid'],
                $params['scope'],
                (int) $params['sectionnum'],
                (int) $params['cmid'],
                (int) $params['count'],
                $params['difficulty'],
                $params['topictitle'],
                $params['language']
            );

            $payload = $result->to_array();
            if (!empty($payload['jobid'])) {
                job_ownership::register((int) $USER->id, (int) $params['courseid'], (string) $payload['jobid']);
            }
            return client_response::sanitize_send_message($payload);
        } catch (api_exception $e) {
            return client_response::job_submit_error($e);
        }
    }

    /**
     * Describe the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'completed' => new external_value(PARAM_BOOL, 'Whether completed'),
            'jobid' => new external_value(PARAM_RAW, 'Job UUID'),
            'status' => new external_value(PARAM_ALPHA, 'Status', VALUE_OPTIONAL),
            'progress' => new external_value(PARAM_INT, 'Progress', VALUE_OPTIONAL),
            'errormessage' => new external_value(PARAM_RAW, 'Error message', VALUE_OPTIONAL),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT, 'Error code', VALUE_OPTIONAL),
        ]);
    }
}
