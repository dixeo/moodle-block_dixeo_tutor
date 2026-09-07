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
 * Settings for the Dixeo Tutor block.
 *
 * @package    block_dixeo_tutor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_dixeo\dto\tutor_message;

if ($ADMIN->fulltree) {
    // Display mode: in block drawer or in a popup window.
    $settings->add(new admin_setting_configselect(
        'block_dixeo_tutor/displaymode',
        get_string('setting_displaymode', 'block_dixeo_tutor'),
        get_string('setting_displaymode_desc', 'block_dixeo_tutor'),
        'popup',
        [
            'drawer' => get_string('setting_displaymode_drawer', 'block_dixeo_tutor'),
            'popup' => get_string('setting_displaymode_popup', 'block_dixeo_tutor'),
        ]
    ));

    $settings->add(new admin_setting_configmultiselect(
        'block_dixeo_tutor/enabledmodes',
        get_string('setting_enabledmodes', 'block_dixeo_tutor'),
        get_string('setting_enabledmodes_desc', 'block_dixeo_tutor'),
        [tutor_message::MODE_GUIDE, tutor_message::MODE_QUIZ, tutor_message::MODE_TEACH],
        [
            tutor_message::MODE_GUIDE => get_string('modeguide', 'block_dixeo_tutor'),
            tutor_message::MODE_QUIZ => get_string('modequiz', 'block_dixeo_tutor'),
            tutor_message::MODE_TEACH => get_string('modeteach', 'block_dixeo_tutor'),
        ]
    ));

    // Excluded module types (comma-separated).
    $settings->add(new admin_setting_configtextarea(
        'block_dixeo_tutor/excludedmodules',
        get_string('setting_excludedmodules', 'block_dixeo_tutor'),
        get_string('setting_excludedmodules_desc', 'block_dixeo_tutor'),
        'quiz,simplequiz2'
    ));
}
