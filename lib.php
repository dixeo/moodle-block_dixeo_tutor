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
 * Library functions for the Dixeo Student Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Dixeo settings owned by block_dixeo_tutor.
 *
 * The block admin page edits the Dixeo values. A course row in local_dixeo_setting overrides
 * display mode, enabled modes, and excluded modules. tutor_enabled is stored per course and
 * is not rewritten when the new-course default changes.
 *
 * @return array[]
 */
function block_dixeo_tutor_dixeo_settings(): array {
    return [
        [
            'key' => 'tutor_enabled',
            'shortname' => 'tutor_enabled',
            'component' => 'block_dixeo_tutor',
            'type' => 'bool',
            'default' => '1',
            'levels' => ['course'],
            'feature' => 'tutor',
            'plugingroup' => 'dixeo_tutor',
            'help' => 'tutor_enabled_help',
            'sensitive' => false,
            'retain' => true,
            'retainparent' => 'tutor_enabled_default',
        ],
        [
            'key' => 'displaymode',
            'shortname' => 'displaymode',
            'component' => 'block_dixeo_tutor',
            'type' => 'select',
            'default' => 'popup',
            'options' => ['drawer', 'popup'],
            'levels' => ['dixeo', 'course'],
            'feature' => 'tutor',
            'plugingroup' => 'dixeo_tutor',
            'help' => 'displaymode_help',
            'sensitive' => false,
        ],
        [
            'key' => 'enabledmodes',
            'shortname' => 'enabledmodes',
            'component' => 'block_dixeo_tutor',
            'type' => 'multiselect',
            'default' => 'guide,quiz,teach',
            'options' => ['guide', 'quiz', 'teach'],
            'levels' => ['dixeo', 'course'],
            'feature' => 'tutor',
            'plugingroup' => 'dixeo_tutor',
            'help' => 'enabledmodes_help',
            'sensitive' => false,
        ],
        [
            'key' => 'excludedmodules',
            'shortname' => 'excludedmodules',
            'component' => 'block_dixeo_tutor',
            'type' => 'textarea',
            'default' => 'quiz,simplequiz2',
            'levels' => ['dixeo', 'course'],
            'feature' => 'tutor',
            'plugingroup' => 'dixeo_tutor',
            'help' => 'excludedmodules_help',
            'sensitive' => false,
        ],
        [
            'key' => 'tutor_enabled_default',
            'shortname' => 'tutor_enabled_default',
            'component' => 'block_dixeo_tutor',
            'type' => 'bool',
            'default' => '1',
            'levels' => ['dixeo'],
            'feature' => 'tutor',
            'plugingroup' => 'dixeo_tutor',
            'help' => 'tutor_enabled_default_help',
            'sensitive' => false,
        ],
    ];
}
