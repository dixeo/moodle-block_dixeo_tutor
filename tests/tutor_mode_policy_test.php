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
 * Tests for {@see \block_dixeo_tutor\service\tutor_mode_policy}.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use advanced_testcase;
use block_dixeo_tutor\service\tutor_mode_policy;
use local_dixeo\dto\tutor_message;

/**
 * Tests for tutor mode policy.
 *
 * @covers \block_dixeo_tutor\service\tutor_mode_policy
 */
final class tutor_mode_policy_test extends advanced_testcase {
    /**
     * @var string|null
     */
    private ?string $originalconfig = null;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->originalconfig = get_config('block_dixeo_tutor', tutor_mode_policy::CONFIG_ENABLED_MODES);
    }

    protected function tearDown(): void {
        if ($this->originalconfig === false || $this->originalconfig === null) {
            unset_config(tutor_mode_policy::CONFIG_ENABLED_MODES, 'block_dixeo_tutor');
        } else {
            set_config(tutor_mode_policy::CONFIG_ENABLED_MODES, $this->originalconfig, 'block_dixeo_tutor');
        }
        parent::tearDown();
    }

    public function test_default_optional_modes_include_all(): void {
        unset_config(tutor_mode_policy::CONFIG_ENABLED_MODES, 'block_dixeo_tutor');

        $this->assertSame(
            tutor_mode_policy::optional_modes(),
            tutor_mode_policy::get_configured_optional_modes()
        );
    }

    public function test_empty_config_returns_no_optional_modes(): void {
        set_config(tutor_mode_policy::CONFIG_ENABLED_MODES, '', 'block_dixeo_tutor');

        $this->assertSame([], tutor_mode_policy::get_configured_optional_modes());
        $this->assertSame([tutor_message::MODE_NORMAL], tutor_mode_policy::get_available_modes(true));
        $this->assertFalse(tutor_mode_policy::should_show_mode_selector(true));
    }

    public function test_subset_config_is_respected(): void {
        set_config(tutor_mode_policy::CONFIG_ENABLED_MODES, 'guide,teach', 'block_dixeo_tutor');

        $this->assertSame(
            [tutor_message::MODE_GUIDE, tutor_message::MODE_TEACH],
            tutor_mode_policy::get_configured_optional_modes()
        );
        $this->assertTrue(tutor_mode_policy::is_mode_available(tutor_message::MODE_GUIDE, true));
        $this->assertFalse(tutor_mode_policy::is_mode_available(tutor_message::MODE_QUIZ, true));
    }

    public function test_quiz_excluded_when_runtime_unavailable(): void {
        set_config(tutor_mode_policy::CONFIG_ENABLED_MODES, 'quiz', 'block_dixeo_tutor');

        $this->assertFalse(tutor_mode_policy::is_mode_available(tutor_message::MODE_QUIZ, false));
        $this->assertFalse(tutor_mode_policy::should_show_mode_selector(false));
    }

    public function test_coerce_mode_falls_back_to_normal(): void {
        set_config(tutor_mode_policy::CONFIG_ENABLED_MODES, 'guide', 'block_dixeo_tutor');

        $this->assertSame(
            tutor_message::MODE_NORMAL,
            tutor_mode_policy::coerce_mode(tutor_message::MODE_TEACH, true)
        );
    }

    public function test_simplequiz2_version_requires_embed_player_release(): void {
        $older = 2026032811.0;
        $minimum = (float) tutor_mode_policy::MIN_SIMPLEQUIZ2_VERSION;

        $this->assertFalse(tutor_mode_policy::simplequiz2_version_satisfies($older, $older));
        $this->assertFalse(tutor_mode_policy::simplequiz2_version_satisfies($minimum, $older));
        $this->assertFalse(tutor_mode_policy::simplequiz2_version_satisfies($older, $minimum));
        $this->assertTrue(tutor_mode_policy::simplequiz2_version_satisfies($minimum, $minimum));
        $this->assertTrue(tutor_mode_policy::simplequiz2_version_satisfies($minimum + 1, $minimum + 1));
    }

    public function test_quiz_runtime_follows_installed_simplequiz2_version(): void {
        $info = \core_plugin_manager::instance()->get_plugin_info('mod_simplequiz2');
        if ($info === null) {
            $this->assertFalse(tutor_mode_policy::is_quiz_runtime_available());
            return;
        }

        $expected = tutor_mode_policy::simplequiz2_version_satisfies(
            (float) $info->versiondisk,
            (float) $info->versiondb
        );
        $this->assertSame($expected, tutor_mode_policy::is_quiz_runtime_available());

        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/dixeo_tutor/block_dixeo_tutor.php');
        $this->assertSame($expected, \block_dixeo_tutor::is_simplequiz2_available());
    }

    public function test_require_mode_available_throws_for_disabled_mode(): void {
        set_config(tutor_mode_policy::CONFIG_ENABLED_MODES, 'guide', 'block_dixeo_tutor');

        $this->expectException(\invalid_parameter_exception::class);
        tutor_mode_policy::require_mode_available(tutor_message::MODE_QUIZ, true);
    }
}
