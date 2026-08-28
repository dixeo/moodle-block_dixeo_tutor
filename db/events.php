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
 * Event observers for the Dixeo Student Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_viewed',
        'callback' => '\block_dixeo_tutor\observer\proactive_context_observer::course_viewed',
        'internal' => true,
        'priority' => 0,
    ],
    [
        'eventname' => '\core\event\course_completed',
        'callback' => '\block_dixeo_tutor\observer\proactive_context_observer::course_completed',
        'internal' => true,
        'priority' => 0,
    ],
    [
        'eventname' => '\core\event\user_graded',
        'callback' => '\block_dixeo_tutor\observer\proactive_context_observer::user_graded',
        'internal' => true,
        'priority' => 0,
    ],
    // Conversations outlive the course they belong to unless the API is told to drop them.
    // Internal: the observer only queues a task, so it belongs in the deletion transaction
    // and must be rolled back with it. The remote call happens later, from the task.
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\block_dixeo_tutor\observer\conversation_cleanup_observer::course_deleted',
        'internal' => true,
    ],
    // A user deleted outside a GDPR request never reaches the privacy provider, so
    // nothing else would tell the API to drop their conversations.
    // Internal: same reason as above, the observer only queues a task.
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\block_dixeo_tutor\observer\conversation_cleanup_observer::user_deleted',
        'internal' => true,
    ],
];
