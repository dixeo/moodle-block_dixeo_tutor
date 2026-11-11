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

namespace block_dixeo_tutor;

use local_dixeo\settings\catalogue;

/**
 * The tutor switch is a catalogue setting shown on the course edit form.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::block_dixeo_tutor_dixeo_settings
 */
final class catalogue_test extends \advanced_testcase {
    public function test_the_tutor_switch_is_shown_on_the_course_form(): void {
        $definition = catalogue::load()['tutor_enabled'];

        $this->assertSame('block_dixeo_tutor', $definition->get_component());
        $this->assertTrue($definition->shows_on_course_form());
    }

    public function test_no_admin_setting_string_remains(): void {
        foreach (['en', 'de', 'es', 'fr', 'it', 'pt'] as $lang) {
            $string = [];
            include(__DIR__ . '/../lang/' . $lang . '/block_dixeo_tutor.php');
            $this->assertSame([], preg_grep('/^setting_/', array_keys($string)), $lang);
        }
    }
}
