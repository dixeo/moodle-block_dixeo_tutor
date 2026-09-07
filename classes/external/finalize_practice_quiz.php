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
 * Finalize a practice quiz job into simplequiz2 JSON.
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
 * Web service: transform completed job into practice quiz questions.
 */
class finalize_practice_quiz extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'jobid' => new external_value(PARAM_RAW, 'Completed job UUID'),
            'topictitle' => new external_value(PARAM_TEXT, 'Fallback topic title', VALUE_DEFAULT, ''),
            'expectedcount' => new external_value(PARAM_INT, 'Expected question count from setup', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid
     * @param string $jobid
     * @param string $topictitle
     * @param int $expectedcount
     * @return array
     */
    public static function execute(int $courseid, string $jobid, string $topictitle = '', int $expectedcount = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'jobid' => $jobid,
            'topictitle' => $topictitle,
            'expectedcount' => $expectedcount,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        job_ownership::require_valid_jobid($params['jobid']);
        job_ownership::require_owned((int) $USER->id, (int) $params['courseid'], $params['jobid']);

        try {
            $service = service_factory::get_practice_quiz_service();
            $expected = $params['expectedcount'] > 0 ? (int) $params['expectedcount'] : null;

            return $service->finalize_from_job(
                $params['jobid'],
                $params['topictitle'],
                $expected,
                (int) $params['courseid']
            );
        } catch (api_exception $e) {
            return client_response::finalize_practice_quiz_error($e);
        }
    }

    /**
     * Describe the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success flag'),
            'error' => new external_value(PARAM_TEXT, 'Error message'),
            'title' => new external_value(PARAM_TEXT, 'Quiz title'),
            'questions' => new external_value(PARAM_RAW, 'JSON array of simplequiz2 questions'),
        ]);
    }
}
