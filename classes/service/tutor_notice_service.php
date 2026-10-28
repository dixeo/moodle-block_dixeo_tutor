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
 * Local tutor notices: greeting, course completed, quiz graded.
 *
 * Notices come from language strings, never from the API: they cost no credit and stay out
 * of the conversation history. They are taken when the tutor renders, so the greeting is
 * only counted once the learner can see it.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

/**
 * Collects the notices due on this page view and renders each one from a random string variant.
 */
class tutor_notice_service {
    /** @var string User preference prefix: last time the learner was greeted (per course). */
    public const PREF_LAST_GREETING_PREFIX = 'block_dixeo_tutor_lastproactive_';

    /** @var int Minimum gap between two greetings in a course. */
    private const GREETING_GAP = DAYSECS;

    /** @var array<string, int> Number of string variants per notice key (notice_<key>_<n>). */
    private const VARIANTS = [
        'firstvisit' => 3,
        'returnvisit' => 4,
        'coursecompleted' => 3,
        'quizgraded_high' => 3,
        'quizgraded_mid' => 3,
        'quizgraded_low' => 3,
    ];

    /**
     * Notices to show on this page view, oldest first, removed from the queue.
     *
     * @param int $userid
     * @param int $courseid
     * @return array[] List of {content: string, contenthtml: string}.
     */
    public function take_notices(int $userid, int $courseid): array {
        $notices = (new tutor_proactive_context_service())->take_notices($userid, $courseid);
        $greeting = $this->take_greeting($userid, $courseid);
        if ($greeting !== null) {
            array_unshift($notices, $greeting);
        }
        if ($notices === []) {
            return [];
        }

        $context = \context_course::instance($courseid);
        $name = s($this->get_first_name($userid));
        $rendered = [];
        foreach ($notices as $notice) {
            $html = $this->render($notice, $name, $context);
            if ($html !== null) {
                $rendered[] = [
                    'content' => trim(\core_text::entities_to_utf8(strip_tags($html))),
                    'contenthtml' => \html_writer::tag('p', $html),
                ];
            }
        }
        return $rendered;
    }

    /**
     * Greeting notice when the learner was never greeted in this course or not for a day.
     *
     * @param int $userid
     * @param int $courseid
     * @return array|null
     */
    private function take_greeting(int $userid, int $courseid): ?array {
        $now = time();
        $prefname = self::PREF_LAST_GREETING_PREFIX . $courseid;
        $lastgreeting = (int) get_user_preferences($prefname, 0, $userid);
        if ($lastgreeting > 0 && ($now - $lastgreeting) < self::GREETING_GAP) {
            return null;
        }

        set_user_preference($prefname, $now, $userid);
        return ['type' => $lastgreeting === 0 ? 'first_visit' : 'return_visit'];
    }

    /**
     * Fill a random variant of the notice string. Every placeholder value is HTML-escaped.
     *
     * @param array $notice Queued event or greeting.
     * @param string $name Escaped learner name.
     * @param \context_course $context
     * @return string|null HTML, or null for an unknown type.
     */
    private function render(array $notice, string $name, \context_course $context): ?string {
        $a = (object) ['name' => $name];

        switch ($notice['type'] ?? '') {
            case 'first_visit':
                $key = 'firstvisit';
                break;
            case 'return_visit':
                $key = 'returnvisit';
                break;
            case 'course_completed':
                $key = 'coursecompleted';
                $a->course = format_string(get_course($context->instanceid)->shortname, true, ['context' => $context]);
                break;
            case 'quiz_graded':
                $grade = (float) ($notice['grade'] ?? 0);
                $maxgrade = (float) ($notice['maxgrade'] ?? 0);
                $percent = $maxgrade > 0 ? 100 * $grade / $maxgrade : 0;
                $key = 'quizgraded_' . ($percent >= 80 ? 'high' : ($percent >= 50 ? 'mid' : 'low'));
                $a->quiz = format_string((string) ($notice['quizname'] ?? ''), true, ['context' => $context]);
                $a->grade = s(format_float($grade, 2, true, true));
                $a->maxgrade = s(format_float($maxgrade, 2, true, true));
                break;
            default:
                return null;
        }

        return get_string('notice_' . $key . '_' . random_int(1, self::VARIANTS[$key]), 'block_dixeo_tutor', $a);
    }

    /**
     * Learner first name, else full name, else username.
     *
     * @param int $userid
     * @return string
     */
    private function get_first_name(int $userid): string {
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $firstname = trim((string) ($user->firstname ?? ''));
        if ($firstname !== '') {
            return $firstname;
        }
        $fullname = trim(fullname($user));
        return $fullname !== '' ? $fullname : (string) $user->username;
    }
}
