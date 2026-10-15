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

namespace block_dixeo_tutor\observer;

/**
 * Copies the tutor new-course default onto a course when it is created.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_observer {
    /**
     * Store the current new-course default on the new course.
     *
     * The Dixeo setting authorises the write, so the course creator does not need
     * moodle/course:update. Does nothing when Dixeo is off for the course.
     * A later change to the default does not rewrite this row.
     *
     * @param \core\event\course_created $event
     * @return void
     */
    public static function course_created(\core\event\course_created $event): void {
        $courseid = (int) $event->courseid;
        if ($courseid <= SITEID) {
            return;
        }

        $config = \local_dixeo\external\service_factory::get_settings_config();
        $context = \context_course::instance($courseid);
        if (!$config->enabled_for($context)) {
            return;
        }

        $default = $config->get('block_dixeo_tutor', 'tutor_enabled_default', \context_system::instance())->get_value();
        $config->set(
            'block_dixeo_tutor',
            'tutor_enabled',
            $context,
            $default === '1' ? '1' : '0',
            false
        );
    }
}
