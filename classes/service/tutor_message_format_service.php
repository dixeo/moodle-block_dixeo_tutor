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
 * Format tutor chat messages for display with Moodle text filters.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

/**
 * Applies format_text (including activity name auto-linking) to tutor messages.
 */
class tutor_message_format_service {
    /** @var string Wrapper tag for practice quiz review payloads. */
    private const REVIEW_TAG = 'practice-quiz-review';

    /** @var string Wrapper tag for proactive system context. */
    private const PROACTIVE_TAG = 'proactive-context';

    /**
     * Add a formatted HTML field to each message for chat display.
     *
     * @param array $messages Messages from the tutor API.
     * @param \context $context Course context for filters.
     * @return array Messages with contenthtml added when formatted.
     */
    public static function add_display_html(array $messages, \context $context): array {
        foreach ($messages as $i => $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $content = (string) ($msg['content'] ?? '');
            $messages[$i]['contenthtml'] = self::format_content($content, $context);
        }

        return $messages;
    }

    /**
     * Format one message body for display (markdown + Moodle filters).
     *
     * @param string $content Raw message content from the API.
     * @param \context $context Course context for filters.
     * @return string Filtered HTML, or empty when formatting should be skipped.
     */
    public static function format_content(string $content, \context $context): string {
        if ($content === '' || self::should_skip_formatting($content)) {
            return '';
        }

        return format_text($content, FORMAT_MARKDOWN, [
            'context' => $context,
            'filter' => true,
            'para' => false,
            'overflowdiv' => false,
        ]);
    }

    /**
     * Whether message formatting should be skipped.
     *
     * @param string $content Raw message content.
     * @return bool
     */
    private static function should_skip_formatting(string $content): bool {
        return str_contains($content, '<' . self::REVIEW_TAG)
            || str_contains($content, '<' . self::PROACTIVE_TAG);
    }
}
