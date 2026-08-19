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
 * Normalize tutor conversation rows from the API for block display (read path).
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

/**
 * Maps API message context into block schema vocabulary before formatting/display.
 */
class tutor_message_read_mapper {
    /**
     * Read context.schema from a normalized context object.
     *
     * @param array|null $context
     * @return string
     */
    public static function context_schema(?array $context): string {
        if (is_array($context) && isset($context['schema'])) {
            return (string) $context['schema'];
        }

        return '';
    }

    /**
     * Detect the context schema from a message payload.
     *
     * @param array $message Normalized message row.
     * @return string
     */
    public static function schema_from_message(array $message): string {
        $context = $message['context'] ?? null;
        return is_array($context) ? self::context_schema($context) : '';
    }

    /**
     * Add schema/version to context objects that only carry legacy body/url keys.
     *
     * @param array $context
     * @return array
     */
    public static function ensure_schema(array $context): array {
        if (isset($context['schema'])) {
            return $context;
        }

        if (isset($context['body'])) {
            $context['schema'] = tutor_context_schema::SCHEMA_PROACTIVE;
            $context['version'] = 1;
            return $context;
        }

        if (array_key_exists('url', $context)) {
            return tutor_context_schema::page_context((string) $context['url']);
        }

        return $context;
    }

    /**
     * Ensure context objects include schema before display.
     *
     * @param array $messages
     * @return array
     */
    public static function normalize_messages(array $messages): array {
        foreach ($messages as $i => $msg) {
            if (!is_array($msg) || !isset($msg['context']) || !is_array($msg['context'])) {
                continue;
            }

            $messages[$i]['context'] = self::ensure_schema($msg['context']);
        }

        return $messages;
    }
}
