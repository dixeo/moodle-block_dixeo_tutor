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
 * Event observers for block_dixeo_tutor proactive context.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
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
];
