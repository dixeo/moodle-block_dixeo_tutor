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
 * Web service to get tutor conversation history.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\external;

use block_dixeo_tutor\event\conversation_viewed;
use block_dixeo_tutor\service\tutor_message_read_mapper;
use block_dixeo_tutor\service\tutor_message_format_service;
use block_dixeo_tutor\service\tutor_read_state_service;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\api\exception\api_exception;
use local_dixeo\external\service_factory;

/**
 * External function to get conversation history.
 */
class get_conversation extends external_api {
    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
            'sinceid' => new external_value(PARAM_ALPHANUMEXT, 'Message ID cursor for newer messages', VALUE_DEFAULT, ''),
            'offset' => new external_value(PARAM_INT, 'Offset for loading older message pages', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid
     * @param string $sinceid
     * @param int $offset
     * @return array
     */
    public static function execute(int $courseid, string $sinceid = '', int $offset = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sinceid' => $sinceid,
            'offset' => $offset,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        try {
            $service = service_factory::get_tutor_service();
            $messages = $service->get_conversation(
                $params['courseid'],
                $USER->id,
                $params['sinceid'],
                50,
                $params['offset']
            );

            $messages = tutor_message_read_mapper::normalize_messages($messages);
            $lastincomingtime = tutor_read_state_service::latest_incoming_time_from_messages($messages);
            $messages = tutor_message_format_service::add_display_html($messages, $context);

            foreach ($messages as $i => $msg) {
                if (isset($msg['context']) && is_array($msg['context'])) {
                    $messages[$i]['context'] = json_encode($msg['context']);
                }
            }

            conversation_viewed::create_for_course(
                (int) $params['courseid'],
                (int) $USER->id,
                count($messages),
                (string) $params['sinceid']
            )->trigger();

            return [
                'messages' => $messages,
                'lastincomingtime' => $lastincomingtime,
            ];
        } catch (api_exception $e) {
            debugging(
                'Tutor get_conversation failed: ' . $e->get_error_code(),
                DEBUG_DEVELOPER
            );
            return [
                'messages' => [],
                'lastincomingtime' => 0,
            ];
        }
    }

    /**
     * Define the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_RAW, 'Message UUID'),
                    'role' => new external_value(PARAM_ALPHA, 'Message role (user, assistant, or system)'),
                    'content' => new external_value(PARAM_RAW, 'Message content'),
                    'contenthtml' => new external_value(PARAM_RAW, 'Filtered HTML for display', VALUE_DEFAULT, ''),
                    'time' => new external_value(PARAM_INT, 'Unix timestamp'),
                    'context' => new external_value(PARAM_RAW, 'Message context JSON object', VALUE_DEFAULT, ''),
                ])
            ),
            'lastincomingtime' => new external_value(
                PARAM_INT,
                'Unix time of the latest assistant message in this batch',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }
}
