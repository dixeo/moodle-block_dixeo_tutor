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
 * Shared JSON size limits for tutor context payloads.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

/**
 * Encoded JSON length guard for tutor system context objects.
 */
class tutor_context_size_helper {
    /** @var int Maximum encoded JSON length for context objects submitted to the tutor API. */
    public const MAX_CONTEXT_JSON_LENGTH = 16000;

    /**
     * Whether encoded context JSON fits the size limit.
     *
     * @param string $json Encoded context JSON.
     * @return bool
     */
    public static function context_fits(string $json): bool {
        return strlen($json) <= self::MAX_CONTEXT_JSON_LENGTH;
    }

    /**
     * JSON-encode a context array when it fits the size limit.
     *
     * @param array $context Context object to encode.
     * @param bool $pretty Use pretty-printed JSON first.
     * @return string|null Encoded JSON or null on encode failure.
     */
    public static function encode_context(array $context, bool $pretty = false): ?string {
        $flags = JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        $json = json_encode($context, $flags);
        return $json === false ? null : $json;
    }
}
