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
 * Block-owned tutor message context vocabulary and builders (write path).
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

/**
 * Schema constants and context object builders for block_dixeo_tutor submit paths.
 */
class tutor_context_schema {
    public const SCHEMA_PAGE = 'page';
    public const SCHEMA_PROACTIVE = 'proactive';
    public const SCHEMA_PRACTICE_QUIZ_REVIEW = 'practice_quiz_review';

    /**
     * Return known context schema identifiers.
     *
     * @return string[]
     */
    public static function schemas(): array {
        return [
            self::SCHEMA_PAGE,
            self::SCHEMA_PROACTIVE,
            self::SCHEMA_PRACTICE_QUIZ_REVIEW,
        ];
    }

    /**
     * Build page context for user chat messages.
     *
     * @param string $pageurl
     * @return array
     */
    public static function page_context(string $pageurl = ''): array {
        return [
            'schema' => self::SCHEMA_PAGE,
            'version' => 1,
            'url' => $pageurl,
        ];
    }

    /**
     * Build proactive system context from queued structured events.
     *
     * @param array $events Queued proactive events.
     * @param int|null $userid Optional learner id.
     * @param int|null $courseid Optional course id.
     * @param int|null $time Optional flush timestamp.
     * @return array
     */
    public static function proactive_context(
        array $events,
        ?int $userid = null,
        ?int $courseid = null,
        ?int $time = null
    ): array {
        $context = [
            'schema' => self::SCHEMA_PROACTIVE,
            'version' => 1,
            'events' => $events,
        ];

        if ($userid !== null && $userid > 0) {
            $context['userid'] = $userid;
        }
        if ($courseid !== null && $courseid > 0) {
            $context['courseid'] = $courseid;
        }
        if ($time !== null && $time > 0) {
            $context['time'] = $time;
        }

        return $context;
    }
}
