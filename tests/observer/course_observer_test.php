<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_dixeo_tutor\observer;

use local_dixeo\external\service_factory;
use local_dixeo\hook\course_edit_form;
use local_dixeo\settings\catalogue;
use local_dixeo\settings\resolver;

/**
 * The tutor switch saved from the course edit form is stored on the course.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_dixeo_tutor\observer\course_observer
 * @covers     \local_dixeo\hook\course_edit_form
 */
final class course_observer_test extends \advanced_testcase {
    public function test_saving_the_course_form_with_the_tutor_off_stores_off_on_the_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $name = course_edit_form::element_name(catalogue::load()['tutor_enabled']);

        $record = get_course($course->id);
        $record->{$name} = '0';
        update_course($record);

        $resolved = service_factory::get_settings_config()->get('block_dixeo_tutor', 'tutor_enabled', $context);
        $this->assertSame('0', $resolved->get_value());
        $this->assertSame(resolver::LAYER_COURSE, $resolved->get_source());
    }
}
