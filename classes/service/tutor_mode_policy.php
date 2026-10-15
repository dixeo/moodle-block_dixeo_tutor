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
 * Site policy for which tutor modes are available.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

use local_dixeo\dto\tutor_message;
use local_dixeo\service\plugin_installation_service;

/**
 * Resolves configured and runtime-available tutor modes.
 */
class tutor_mode_policy {
    /** @var string Plugin config key for optional enabled modes. */
    public const CONFIG_ENABLED_MODES = 'enabledmodes';

    /**
     * mod_simplequiz2 version that defines mod_simplequiz2/embed_player
     * and the render_embed external function.
     *
     * Releases before this (1.0.0 / 2026032811) install the activity
     * but do not ship that AMD module.
     */
    public const MIN_SIMPLEQUIZ2_VERSION = 2026072900;

    /**
     * Optional modes that may be enabled via site admin settings.
     *
     * @return string[]
     */
    public static function optional_modes(): array {
        return [
            tutor_message::MODE_GUIDE,
            tutor_message::MODE_QUIZ,
            tutor_message::MODE_TEACH,
        ];
    }

    /**
     * Default optional modes when no admin setting exists (backward compatible).
     *
     * @return string[]
     */
    public static function default_optional_modes(): array {
        return self::optional_modes();
    }

    /**
     * Whether practice quiz can load mod_simplequiz2/embed_player.
     *
     * Requires mod_simplequiz2 to be installed, on disk, and upgraded at
     * {@see self::MIN_SIMPLEQUIZ2_VERSION} or newer.
     *
     * @return bool
     */
    public static function is_quiz_runtime_available(): bool {
        if (!plugin_installation_service::is_component_installed('mod_simplequiz2')) {
            return false;
        }

        $info = \core_plugin_manager::instance()->get_plugin_info('mod_simplequiz2');
        if ($info === null || $info->versiondisk === null || $info->versiondb === null) {
            return false;
        }

        return self::simplequiz2_version_satisfies((float) $info->versiondisk, (float) $info->versiondb);
    }

    /**
     * Whether disk and database versions are new enough for the embed player.
     *
     * @param float $versiondisk Version from mod/simplequiz2/version.php.
     * @param float $versiondb Version recorded after the plugin upgrade.
     * @return bool
     */
    public static function simplequiz2_version_satisfies(float $versiondisk, float $versiondb): bool {
        $minimum = (float) self::MIN_SIMPLEQUIZ2_VERSION;
        return $versiondisk >= $minimum && $versiondb >= $minimum;
    }

    /**
     * Read optional modes enabled for a course, or the Dixeo value when no course is given.
     *
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return string[]
     */
    public static function get_configured_optional_modes(int $courseid = 0): array {
        $context = $courseid > SITEID
            ? \context_course::instance($courseid)
            : \context_system::instance();
        $raw = \local_dixeo\external\service_factory::get_settings_config()
            ->get('block_dixeo_tutor', self::CONFIG_ENABLED_MODES, $context)
            ->get_value();
        if ($raw === null) {
            return self::default_optional_modes();
        }
        if ($raw === '') {
            return [];
        }

        $allowed = array_flip(self::optional_modes());
        $modes = [];
        foreach (explode(',', (string) $raw) as $mode) {
            $mode = tutor_message::normalize_mode($mode);
            if ($mode === tutor_message::MODE_NORMAL) {
                continue;
            }
            if (isset($allowed[$mode])) {
                $modes[] = $mode;
            }
        }

        return array_values(array_unique($modes));
    }

    /**
     * Modes available to the current user/session, including Standard.
     *
     * @param bool $quizavailable Whether practice quiz dependencies are installed.
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return string[]
     */
    public static function get_available_modes(bool $quizavailable = true, int $courseid = 0): array {
        $modes = [tutor_message::MODE_NORMAL];

        foreach (self::get_configured_optional_modes($courseid) as $mode) {
            if ($mode === tutor_message::MODE_QUIZ && !$quizavailable) {
                continue;
            }
            $modes[] = $mode;
        }

        return $modes;
    }

    /**
     * Whether a tutor mode is currently available.
     *
     * @param string $mode
     * @param bool $quizavailable
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return bool
     */
    public static function is_mode_available(string $mode, bool $quizavailable = true, int $courseid = 0): bool {
        $mode = tutor_message::normalize_mode($mode);
        return in_array($mode, self::get_available_modes($quizavailable, $courseid), true);
    }

    /**
     * Return the mode if available, otherwise Standard.
     *
     * @param string $mode
     * @param bool $quizavailable
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return string
     */
    public static function coerce_mode(string $mode, bool $quizavailable = true, int $courseid = 0): string {
        $mode = tutor_message::normalize_mode($mode);
        if (self::is_mode_available($mode, $quizavailable, $courseid)) {
            return $mode;
        }
        return tutor_message::MODE_NORMAL;
    }

    /**
     * Whether the mode selector should be shown (more than Standard alone).
     *
     * @param bool $quizavailable
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return bool
     */
    public static function should_show_mode_selector(bool $quizavailable = true, int $courseid = 0): bool {
        return count(self::get_available_modes($quizavailable, $courseid)) > 1;
    }

    /**
     * Assert that a mode is available; throw if not.
     *
     * @param string $mode
     * @param bool $quizavailable
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return void
     */
    public static function require_mode_available(string $mode, bool $quizavailable = true, int $courseid = 0): void {
        if (!self::is_mode_available($mode, $quizavailable, $courseid)) {
            throw new \invalid_parameter_exception(get_string('error_mode_not_available', 'block_dixeo_tutor'));
        }
    }

    /**
     * Assert that quiz mode is available.
     *
     * @param bool $quizavailable
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return void
     */
    public static function require_quiz_mode(bool $quizavailable = true, int $courseid = 0): void {
        self::require_mode_available(tutor_message::MODE_QUIZ, $quizavailable, $courseid);
    }

    /**
     * Assert that teach mode is available.
     *
     * @param bool $quizavailable
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return void
     */
    public static function require_teach_mode(bool $quizavailable = true, int $courseid = 0): void {
        self::require_mode_available(tutor_message::MODE_TEACH, $quizavailable, $courseid);
    }

    /**
     * Assert that quiz or teach mode is available (for hierarchy lookup).
     *
     * @param bool $quizavailable
     * @param int $courseid Course whose override applies. Zero reads the Dixeo value.
     * @return void
     */
    public static function require_quiz_or_teach_mode(bool $quizavailable = true, int $courseid = 0): void {
        if (
            self::is_mode_available(tutor_message::MODE_QUIZ, $quizavailable, $courseid)
            || self::is_mode_available(tutor_message::MODE_TEACH, $quizavailable, $courseid)
        ) {
            return;
        }
        throw new \invalid_parameter_exception(get_string('error_mode_not_available', 'block_dixeo_tutor'));
    }
}
