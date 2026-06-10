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
 * Submit practice quiz review context to the tutor API.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\job_ownership;
use block_dixeo_tutor\service\practice_quiz_context_service;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service for practice quiz review submission.
 */
class submit_quiz_context extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'pageurl' => new external_value(PARAM_URL, 'Current page URL', VALUE_DEFAULT, ''),
            'title' => new external_value(PARAM_TEXT, 'Quiz title', VALUE_DEFAULT, ''),
            'total' => new external_value(PARAM_INT, 'Total questions', VALUE_DEFAULT, 0),
            'questionsjson' => new external_value(PARAM_RAW, 'JSON array of simplequiz2 questions'),
            'bestattemptjson' => new external_value(PARAM_RAW, 'JSON best attempt state'),
            'exitscore' => new external_value(PARAM_INT, 'Exit attempt score', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid
     * @param string $pageurl
     * @param string $title
     * @param int $total
     * @param string $questionsjson
     * @param string $bestattemptjson
     * @param int $exitscore
     * @return array
     */
    public static function execute(
        int $courseid,
        string $pageurl = '',
        string $title = '',
        int $total = 0,
        string $questionsjson = '',
        string $bestattemptjson = '',
        int $exitscore = 0
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'pageurl' => $pageurl,
            'title' => $title,
            'total' => $total,
            'questionsjson' => $questionsjson,
            'bestattemptjson' => $bestattemptjson,
            'exitscore' => $exitscore,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        $service = new practice_quiz_context_service();
        $result = $service->submit_review(
            (int) $params['courseid'],
            (int) $USER->id,
            [
                'title' => $params['title'],
                'total' => $params['total'],
                'questionsjson' => $params['questionsjson'],
                'bestattemptjson' => $params['bestattemptjson'],
                'exitscore' => $params['exitscore'],
            ],
            $params['pageurl']
        );

        if ($result === null) {
            return ['success' => false, 'jobid' => ''];
        }

        $arr = $result->to_array();
        $jobid = (string) ($arr['jobid'] ?? '');
        if ($jobid !== '') {
            job_ownership::register((int) $USER->id, (int) $params['courseid'], $jobid);
        }
        return [
            'success' => true,
            'jobid' => $jobid,
        ];
    }

    /**
     * Describe the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether submit succeeded'),
            'jobid' => new external_value(PARAM_RAW, 'Tutor job UUID', VALUE_OPTIONAL),
        ]);
    }
}
