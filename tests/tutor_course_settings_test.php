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

namespace block_dixeo_tutor;

use block_dixeo_tutor\service\tutor_mode_policy;
use local_dixeo\dto\tutor_message;

/**
 * Course overrides for the tutor settings, including the retained on/off switch.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tutor_course_settings_test extends \advanced_testcase {
    /**
     * New courses copy the Dixeo default. A later change to that default leaves them alone.
     */
    public function test_new_course_keeps_its_own_tutor_switch(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $config = \local_dixeo\external\service_factory::get_settings_config();

        $first = $this->getDataGenerator()->create_course();
        $firstcontext = \context_course::instance($first->id);
        $this->assertSame('1', $config->get('block_dixeo_tutor', 'tutor_enabled', $firstcontext)->get_value());

        $config->set('block_dixeo_tutor', 'tutor_enabled_default', \context_system::instance(), '0');
        $second = $this->getDataGenerator()->create_course();
        $secondcontext = \context_course::instance($second->id);
        $this->assertSame('0', $config->get('block_dixeo_tutor', 'tutor_enabled', $secondcontext)->get_value());
        $this->assertSame('1', $config->get('block_dixeo_tutor', 'tutor_enabled', $firstcontext)->get_value());

        $config->set('block_dixeo_tutor', 'tutor_enabled', $secondcontext, '0');
        $config->set('block_dixeo_tutor', 'tutor_enabled_default', \context_system::instance(), '1');
        $this->assertSame('0', $config->get('block_dixeo_tutor', 'tutor_enabled', $secondcontext)->get_value());
    }

    /**
     * Display mode, enabled modes, and excluded modules can be overridden for one course.
     */
    public function test_course_overrides_display_modes_and_exclusions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $config = \local_dixeo\external\service_factory::get_settings_config();

        $this->assertSame('popup', $config->get('block_dixeo_tutor', 'displaymode', $context)->get_value());
        $config->set('block_dixeo_tutor', 'displaymode', $context, 'drawer');
        $this->assertSame('drawer', $config->get('block_dixeo_tutor', 'displaymode', $context)->get_value());

        $config->set('block_dixeo_tutor', 'enabledmodes', $context, 'teach,guide');
        $this->assertSame(
            [tutor_message::MODE_GUIDE, tutor_message::MODE_TEACH],
            tutor_mode_policy::get_configured_optional_modes((int) $course->id)
        );
        $this->assertSame(
            [tutor_message::MODE_GUIDE, tutor_message::MODE_QUIZ, tutor_message::MODE_TEACH],
            tutor_mode_policy::get_configured_optional_modes()
        );

        $config->set('block_dixeo_tutor', 'excludedmodules', $context, 'page');
        $this->assertSame(['page'], \block_dixeo_tutor::get_excluded_modules($context));
        $this->assertSame(['quiz', 'simplequiz2'], \block_dixeo_tutor::get_excluded_modules());
    }

    /**
     * Upgrade stores tutor on for a course that has no row yet.
     */
    public function test_upgrade_stores_tutor_on_for_existing_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $DB->delete_records('local_dixeo_setting', [
            'component' => 'block_dixeo_tutor',
            'name' => 'tutor_enabled',
            'contextid' => $context->id,
        ]);

        global $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        set_config('version', 2026100700, 'block_dixeo_tutor');
        xmldb_block_dixeo_tutor_upgrade(2026100700);
        unset($CFG->upgraderunning);

        $config = \local_dixeo\external\service_factory::get_settings_config();
        $this->assertSame('1', $config->get('block_dixeo_tutor', 'tutor_enabled', $context)->get_value());
        $stored = $DB->get_field('local_dixeo_setting', 'value', [
            'component' => 'block_dixeo_tutor',
            'name' => 'tutor_enabled',
            'contextid' => $context->id,
        ]);
        $this->assertSame('1', $stored);
    }
}
