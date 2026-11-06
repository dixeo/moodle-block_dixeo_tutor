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
     * Call it right after require_capability(). Erasing a conversation, cancelling a job and
     * reading a job's status are deliberately not gated: a learner must always be able to delete
     * their data and stop or check work already sent.
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
}
