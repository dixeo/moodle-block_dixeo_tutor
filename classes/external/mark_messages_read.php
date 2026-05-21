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
 * Web service to mark tutor messages as read.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\service\tutor_read_state_service;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Persists last-read state using the latest incoming message in the conversation.
 */
class mark_messages_read extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
            'lastincomingtime' => new external_value(
                PARAM_INT,
                'Unix time of the latest incoming message seen (0 = resolve from conversation)',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid
     * @param int $lastincomingtime
     * @return array
     */
    public static function execute(int $courseid, int $lastincomingtime = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'lastincomingtime' => $lastincomingtime,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        $service = new tutor_read_state_service();
        if ($params['lastincomingtime'] > 0) {
            $lastread = $service->mark_read_up_to($USER->id, $params['courseid'], $params['lastincomingtime']);
        } else {
            $lastread = $service->mark_all_read($USER->id, $params['courseid']);
        }

        return [
            'hasunread' => false,
            'lastread' => $lastread,
        ];
    }

    /**
     * Describe the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'hasunread' => new external_value(PARAM_BOOL, 'Whether there are unread incoming messages'),
            'lastread' => new external_value(PARAM_INT, 'Unix time of last read incoming message', VALUE_DEFAULT, 0),
        ]);
    }
}
