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
 * Submit custom teach lesson context to the tutor API.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\job_ownership;
use block_dixeo_tutor\service\teach_lesson_context_service;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service for custom lesson context submission.
 */
class submit_teach_lesson_context extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'title' => new external_value(PARAM_TEXT, 'Lesson title', VALUE_DEFAULT, ''),
            'introhtml' => new external_value(PARAM_RAW, 'Formatted intro HTML', VALUE_DEFAULT, ''),
            'contenthtml' => new external_value(PARAM_RAW, 'Formatted lesson content HTML'),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid
     * @param string $title
     * @param string $introhtml
     * @param string $contenthtml
     * @return array
     */
    public static function execute(
        int $courseid,
        string $title = '',
        string $introhtml = '',
        string $contenthtml = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'title' => $title,
            'introhtml' => $introhtml,
            'contenthtml' => $contenthtml,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        $service = new teach_lesson_context_service();
        $result = $service->submit_lesson(
            (int) $params['courseid'],
            (int) $USER->id,
            [
                'title' => $params['title'],
                'introhtml' => $params['introhtml'],
                'contenthtml' => $params['contenthtml'],
            ]
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
