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
 * Web service definitions for the Dixeo Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_dixeo_tutor_send_message' => [
        'classname'   => '\block_dixeo_tutor\external\send_message',
        'description' => 'Submits a user message to the tutor and returns a job ID for polling.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'block_dixeo_tutor_get_job_status' => [
        'classname'   => '\block_dixeo_tutor\external\get_job_status',
        'description' => 'Gets the current status of a tutor job.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'block_dixeo_tutor_get_conversation' => [
        'classname'   => '\block_dixeo_tutor\external\get_conversation',
        'description' => 'Retrieves conversation history for the current user in a course.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'block_dixeo_tutor_flush_pending_context' => [
        'classname'   => '\block_dixeo_tutor\external\flush_pending_context',
        'description' => 'Flushes queued proactive tutor context and submits to the API.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'block_dixeo_tutor_mark_messages_read' => [
        'classname'   => '\block_dixeo_tutor\external\mark_messages_read',
        'description' => 'Marks tutor incoming messages as read up to the given timestamp.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'block_dixeo_tutor_get_quiz_hierarchy' => [
        'classname'   => '\block_dixeo_tutor\external\get_quiz_hierarchy',
        'description' => 'Course hierarchy for practice quiz topic selection',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'block_dixeo_tutor_submit_practice_quiz' => [
        'classname'   => '\block_dixeo_tutor\external\submit_practice_quiz',
        'description' => 'Submit ephemeral practice quiz generation job',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'block_dixeo_tutor_finalize_practice_quiz' => [
        'classname'   => '\block_dixeo_tutor\external\finalize_practice_quiz',
        'description' => 'Finalize practice quiz job into question JSON',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
    'block_dixeo_tutor_submit_quiz_context' => [
        'classname'   => '\block_dixeo_tutor\external\submit_quiz_context',
        'description' => 'Submit practice quiz lifecycle context to the tutor',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'block/dixeo_tutor:talktotutor',
    ],
];
