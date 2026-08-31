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
 * Event observers for proactive tutor context.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\observer;

use core\event\course_completed;
use core\event\course_viewed;
use core\event\user_graded;
use block_dixeo_tutor\service\tutor_proactive_context_service;

/**
 * Thin observer delegating to the block proactive context service.
 */
class proactive_context_observer {
    /**
     * Handle course viewed events.
     *
     * @param course_viewed $event
     * @return void
     */
    public static function course_viewed(course_viewed $event): void {
        (new tutor_proactive_context_service())->handle_course_viewed($event);
    }

    /**
     * Handle course completed events.
     *
     * @param course_completed $event
     * @return void
     */
    public static function course_completed(course_completed $event): void {
        (new tutor_proactive_context_service())->handle_course_completed($event);
    }

    /**
     * Handle user graded events.
     *
     * @param user_graded $event
     * @return void
     */
    public static function user_graded(user_graded $event): void {
        (new tutor_proactive_context_service())->handle_user_graded($event);
    }
}
