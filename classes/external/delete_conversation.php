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
 * Web service to erase the current user's tutor conversation.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\event\conversation_deleted;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\external\service_factory;

/**
 * External function to erase a tutor conversation.
 *
 * Always acts on the calling user: there is no user parameter, so the function
 * cannot be pointed at somebody else's conversation. Erasing another user's data
 * is the privacy provider's job, under Moodle's data request workflow.
 */
class delete_conversation extends external_api {
    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
        ]);
    }

    /**
     * Erase the current user's conversation in a course.
     *
     * API failures are deliberately not caught: reporting a successful erasure that
     * did not happen would be worse than surfacing the error to the user.
     *
     * @param int $courseid The course ID.
     * @return array Array with the number of conversations deleted.
     */
    public static function execute(int $courseid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        $deleted = service_factory::get_tutor_service()->delete_conversations(
            (int) $params['courseid'],
            (int) $USER->id
        );

        conversation_deleted::create_for_course(
            (int) $params['courseid'],
            (int) $USER->id,
            $deleted
        )->trigger();

        return ['deleted' => $deleted];
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_INT, 'Number of conversations deleted'),
        ]);
    }
}
