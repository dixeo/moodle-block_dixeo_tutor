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
 * Submit practice quiz lifecycle context to the tutor API.
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
 * Web service for quiz context events.
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
            'event' => new external_value(PARAM_ALPHA, 'Event: started, answered, completed, cancelled, restarted'),
            'pageurl' => new external_value(PARAM_URL, 'Current page URL', VALUE_DEFAULT, ''),
            'title' => new external_value(PARAM_TEXT, 'Quiz title', VALUE_DEFAULT, ''),
            'count' => new external_value(PARAM_INT, 'Question count (started event)', VALUE_DEFAULT, 0),
            'question' => new external_value(PARAM_RAW, 'Question text', VALUE_DEFAULT, ''),
            'chosen' => new external_value(PARAM_RAW, 'Chosen answer', VALUE_DEFAULT, ''),
            'correct' => new external_value(PARAM_RAW, 'Correct answer', VALUE_DEFAULT, ''),
            'iscorrect' => new external_value(PARAM_BOOL, 'Whether answer was correct', VALUE_DEFAULT, false),
            'index' => new external_value(PARAM_INT, 'Question index (0-based)', VALUE_DEFAULT, 0),
            'total' => new external_value(PARAM_INT, 'Total questions', VALUE_DEFAULT, 0),
            'score' => new external_value(PARAM_INT, 'Score achieved', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid
     * @param string $event
     * @param string $pageurl
     * @param string $title
     * @param int $count
     * @param string $question
     * @param string $chosen
     * @param string $correct
     * @param bool $iscorrect
     * @param int $index
     * @param int $total
     * @param int $score
     * @return array
     */
    public static function execute(
        int $courseid,
        string $event,
        string $pageurl = '',
        string $title = '',
        int $count = 0,
        string $question = '',
        string $chosen = '',
        string $correct = '',
        bool $iscorrect = false,
        int $index = 0,
        int $total = 0,
        int $score = 0
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'event' => $event,
            'pageurl' => $pageurl,
            'title' => $title,
            'count' => $count,
            'question' => $question,
            'chosen' => $chosen,
            'correct' => $correct,
            'iscorrect' => $iscorrect,
            'index' => $index,
            'total' => $total,
            'score' => $score,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('block/dixeo_tutor:talktotutor', $context);

        $service = new practice_quiz_context_service();
        $result = $service->submit_event(
            (int) $params['courseid'],
            (int) $USER->id,
            $params['event'],
            [
                'title' => $params['title'],
                'count' => $params['count'],
                'question' => $params['question'],
                'chosen' => $params['chosen'],
                'correct' => $params['correct'],
                'iscorrect' => $params['iscorrect'],
                'index' => $params['index'],
                'total' => $params['total'],
                'score' => $params['score'],
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
