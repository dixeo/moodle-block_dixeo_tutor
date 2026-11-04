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
 * Server-side rules deciding whether the tutor may run for a learner in a course.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor;

use local_dixeo\external\service_factory;
use local_dixeo\service\consent_service;

/**
 * One place for the rules the block UI applies, so web services, observers and tasks enforce them too.
 *
 * Hiding the block is not enough: every AJAX function can be called directly with a sesskey,
 * and observers and adhoc tasks run without any page at all.
 */
class tutor_access {
    /** @var string Generation scope covering the whole course. */
    public const SCOPE_COURSE = 'course';

    /** @var string Generation scope covering one section. */
    public const SCOPE_SECTION = 'section';

    /** @var string Generation scope covering one activity. */
    public const SCOPE_ACTIVITY = 'activity';

    /**
     * Whether Dixeo and the tutor are both turned on for the course.
     *
     * @param \context_course $context Course context.
     * @return bool
     */
    public static function is_usable(\context_course $context): bool {
        $config = service_factory::get_settings_config();
        return $config->enabled_for($context)
            && $config->get('block_dixeo_tutor', 'tutor_enabled', $context)->get_value() === '1';
    }

    /**
     * Stop a web service call when the tutor must not run for this user in this course.
     *
     * Call it right after require_capability(). Erasing a conversation is deliberately not gated:
     * a learner must always be able to delete their data.
     *
     * @param \context_course $context Course context.
     * @param int $userid Acting user.
     * @throws \moodle_exception When Dixeo or the tutor is off, or the current Terms of Use are not accepted.
     */
    public static function require_usable(\context_course $context, int $userid): void {
        service_factory::get_settings_config()->require_enabled($context);
        if (!self::is_usable($context)) {
            throw new \moodle_exception('disabled_tutor_course', 'block_dixeo_tutor');
        }
        (new consent_service())->require_accepted($userid, $context, 'consentrequired', 'block_dixeo_tutor');
    }

    /**
     * Whether data about this learner may be sent to Dixeo from a background path (observer or task).
     *
     * The local_dixeo consent rule comes first (real user, existing course with Dixeo on, current
     * Terms of Use accepted), so guests and missing courses stop there. The tutor then adds its own
     * rules: the tutor is on for the course and the learner has the tutor capability.
     *
     * @param int $userid Learner.
     * @param int $courseid Course.
     * @return bool
     */
    public static function may_send_for(int $userid, int $courseid): bool {
        if (!(new consent_service())->may_process_for($userid, $courseid)) {
            return false;
        }
        $context = \context_course::instance($courseid);
        return self::is_usable($context) && has_capability('block/dixeo_tutor:talktotutor', $context, $userid);
    }

    /**
     * Check a Quiz or Teach generation scope against what the current user may see in the course.
     *
     * Mirrors the setup hierarchy ({@see \block_dixeo_tutor::build_practice_quiz_hierarchy()}): only
     * sections and activities that are visible and available to the user, outside section 0, and
     * activities whose type is not excluded from topics. The ids that do not belong to the scope
     * are returned as 0, so a missing id can never fall back to another scope downstream.
     *
     * @param int $courseid Course id.
     * @param string $scope course, section or activity.
     * @param int $sectionnum Section number for the section scope.
     * @param int $cmid Course module id for the activity scope.
     * @return array{0: string, 1: int, 2: int} Normalised scope, section number and cmid.
     * @throws \invalid_parameter_exception When the scope is unknown.
     * @throws \moodle_exception When the section or activity is not available, without saying whether it exists.
     */
    public static function require_topic_visible(int $courseid, string $scope, int $sectionnum, int $cmid): array {
        global $CFG;

        $scope = strtolower(trim($scope));
        if (!in_array($scope, [self::SCOPE_COURSE, self::SCOPE_SECTION, self::SCOPE_ACTIVITY], true)) {
            throw new \invalid_parameter_exception('Invalid scope');
        }
        if ($scope === self::SCOPE_COURSE) {
            return [$scope, 0, 0];
        }

        $modinfo = get_fast_modinfo($courseid);

        if ($scope === self::SCOPE_SECTION) {
            $section = $sectionnum > 0 ? $modinfo->get_section_info($sectionnum, IGNORE_MISSING) : null;
            if (!$section || !$section->uservisible) {
                throw new \moodle_exception('error_topic_unavailable', 'block_dixeo_tutor');
            }
            return [$scope, (int) $section->section, 0];
        }

        // Block plugin classes are not autoloaded in web service requests.
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/dixeo_tutor/block_dixeo_tutor.php');

        $cm = null;
        if ($cmid > 0) {
            try {
                $cm = $modinfo->get_cm($cmid);
            } catch (\moodle_exception $e) {
                $cm = null;
            }
        }
        if (
            !$cm
            || !$cm->uservisible
            || (int) $cm->sectionnum === 0
            || \block_dixeo_tutor::is_quiz_topic_excluded($cm->modname, \context_course::instance($courseid))
        ) {
            throw new \moodle_exception('error_topic_unavailable', 'block_dixeo_tutor');
        }
        return [$scope, 0, (int) $cm->id];
    }
}
