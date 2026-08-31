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

use block_dixeo_tutor\service\tutor_context_schema;
use block_dixeo_tutor\service\tutor_message_read_mapper;

/**
 * Applies format_text (including activity name auto-linking) to tutor messages.
 */
class tutor_message_format_service {
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
            $messages[$i]['contenthtml'] = self::format_message($msg, $content, $context);
        }

        return $messages;
    }

    /**
     * Format one message body for display (markdown + Moodle filters).
     *
     * @param array $msg Normalized message row.
     * @param string $content Visible message text.
     * @param \context $context Course context for filters.
     * @return string Filtered HTML, or empty when formatting should be skipped.
     */
    public static function format_message(array $msg, string $content, \context $context): string {
        if ($content === '' || self::should_skip_formatting($msg)) {
            return '';
        }

        $html = format_text($content, FORMAT_MARKDOWN, [
            'context' => $context,
            'filter' => true,
            'para' => false,
            'overflowdiv' => false,
        ]);

        return $html;
    }

    /**
     * Whether message formatting should be skipped.
     *
     * @param array $msg Normalized message row.
     * @return bool
     */
    public static function should_skip_formatting(array $msg): bool {
        if (strtolower((string) ($msg['role'] ?? '')) !== 'system') {
            return false;
        }

        $schema = tutor_message_read_mapper::schema_from_message($msg);
        return in_array($schema, [
            tutor_context_schema::SCHEMA_PROACTIVE,
            tutor_context_schema::SCHEMA_PRACTICE_QUIZ_REVIEW,
            tutor_context_schema::SCHEMA_CUSTOM_LESSON,
        ], true);
    }
}
