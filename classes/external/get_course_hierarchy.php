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
 * Web service: course hierarchy for tutor setup topic selection.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns course / section / activity tree for practice quiz and teach-me setup panels.
 */
class get_course_hierarchy extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $CFG, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        // Block plugin classes are not autoloaded in webservice context.
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/dixeo_tutor/block_dixeo_tutor.php');

        $hierarchy = \block_dixeo_tutor::build_practice_quiz_hierarchy((int) $params['courseid'], $PAGE);

        return [
            'course' => $hierarchy['course'],
            'sections' => $hierarchy['sections'],
        ];
    }

    /**
     * Describe the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'course' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Course name'),
            ]),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'num' => new external_value(PARAM_INT, 'Section number'),
                    'name' => new external_value(PARAM_TEXT, 'Section name'),
                    'activities' => new external_multiple_structure(
                        new external_single_structure([
                            'cmid' => new external_value(PARAM_INT, 'Course module id'),
                            'name' => new external_value(PARAM_TEXT, 'Activity name'),
                        ])
                    ),
                ])
            ),
        ]);
    }
}
