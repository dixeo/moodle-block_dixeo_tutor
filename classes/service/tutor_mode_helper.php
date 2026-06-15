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
 * Export tutor mode choicedropdown for the tutor header.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

use core\output\choicelist;
use core\output\local\dropdown\status;
use local_dixeo\dto\tutor_message;

/**
 * Builds Mustache context for the tutor mode selector (core choicedropdown pattern).
 */
class tutor_mode_helper {
    /**
     * Return tutor mode definitions for the selector.
     *
     * @return array
     */
    private static function mode_definitions(): array {
        return [
            [
                'id' => tutor_message::MODE_NORMAL,
                'label' => 'modenormal',
                'description' => 'modenormal_desc',
            ],
            [
                'id' => tutor_message::MODE_GUIDE,
                'label' => 'modeguide',
                'description' => 'modeguide_desc',
            ],
            [
                'id' => tutor_message::MODE_QUIZ,
                'label' => 'modequiz',
                'description' => 'modequiz_desc',
            ],
            [
                'id' => tutor_message::MODE_TEACH,
                'label' => 'modeteach',
                'description' => 'modeteach_desc',
            ],
        ];
    }

    /**
     * Build a choicelist of tutor modes.
     *
     * @param string $selectedmode Currently selected tutor mode.
     * @param bool $quizavailable Whether quiz mode may be offered.
     * @return choicelist
     */
    public static function build_mode_choicelist(string $selectedmode, bool $quizavailable = true): choicelist {
        $selectedmode = tutor_message::normalize_mode($selectedmode);
        $choicelist = new choicelist();

        foreach (self::mode_definitions() as $mode) {
            if ($mode['id'] === tutor_message::MODE_QUIZ && !$quizavailable) {
                continue;
            }
            $definition = [
                'description' => get_string($mode['description'], 'block_dixeo_tutor'),
            ];
            if ($mode['id'] === $selectedmode) {
                $definition['selected'] = true;
            }
            $choicelist->add_option(
                $mode['id'],
                get_string($mode['label'], 'block_dixeo_tutor'),
                $definition
            );
        }

        return $choicelist;
    }

    /**
     * Export mode selector template data.
     *
     * @param \core\output\renderer_base $output Output renderer.
     * @param string $selectedmode Currently selected tutor mode.
     * @param bool $quizavailable Whether quiz mode may be offered.
     * @return array
     */
    public static function export_mode_selector(
        \core\output\renderer_base $output,
        string $selectedmode,
        bool $quizavailable = true
    ): array {
        $selectedmode = tutor_message::normalize_mode($selectedmode);
        if (!$quizavailable && $selectedmode === tutor_message::MODE_QUIZ) {
            $selectedmode = tutor_message::MODE_NORMAL;
        }
        $choicelist = self::build_mode_choicelist($selectedmode, $quizavailable);

        $dialog = new status(
            $choicelist->get_selected_content($output),
            $choicelist,
            [
                'extras' => ['data-form-controls' => 'tutormode'],
                'buttonsync' => true,
                'updatestatus' => true,
                'dialogwidth' => status::WIDTH['small'],
            ]
        );

        return [
            'id' => 'tutormode',
            'name' => 'tutormode',
            'select' => $choicelist->export_for_template($output),
            'dropdown' => $dialog->export_for_template($output),
            'currentmode' => $selectedmode,
        ];
    }
}
