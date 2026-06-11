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
 * Helper for wrapping tutor system-context messages.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

/**
 * Shared message wrapping for proactive and practice-quiz context.
 */
class tutor_message_helper {
    /** @var int Maximum message length accepted by tutor send_message. */
    public const MAX_MESSAGE_LENGTH = 2000;

    /** @var int Maximum length for practice quiz review messages. */
    public const MAX_PRACTICE_QUIZ_REVIEW_LENGTH = 16000;

    /** @var string Wrapper tag (must match proactive_context.js filter). */
    public const CONTEXT_TAG = 'proactive-context';

    /** @var string Wrapper tag for practice quiz review (must match practice_quiz_review.js). */
    public const REVIEW_TAG = 'practice-quiz-review';

    /**
     * Wrap inner text for tutor API submission (hidden from chat UI).
     *
     * @param string $message Inner context text.
     * @return string
     */
    public static function wrap_system_context(string $message): string {
        $wrapperopen = '<' . self::CONTEXT_TAG . ' source="system">' . "\n";
        $wrapperclose = "\n" . '</' . self::CONTEXT_TAG . '>';
        $maxinner = self::MAX_MESSAGE_LENGTH - strlen($wrapperopen) - strlen($wrapperclose);
        if ($maxinner > 0 && strlen($message) > $maxinner) {
            $message = \core_text::substr($message, 0, $maxinner);
        }

        return $wrapperopen . $message . $wrapperclose;
    }

    /**
     * Whether a JSON payload fits within the review message limit once wrapped.
     *
     * @param string $json JSON payload candidate.
     * @return bool
     */
    public static function practice_quiz_review_fits(string $json): bool {
        $wrapperopen = '<' . self::REVIEW_TAG . ' version="1">' . "\n";
        $wrapperclose = "\n" . '</' . self::REVIEW_TAG . '>';
        return strlen($wrapperopen . $json . $wrapperclose) <= self::MAX_PRACTICE_QUIZ_REVIEW_LENGTH;
    }

    /**
     * Wrap a JSON practice quiz review for tutor API submission (visible in chat until rendered).
     * Never truncates: cutting mid-JSON would corrupt the payload and break the
     * client renderer. Callers must size the payload via {@see practice_quiz_review_fits()}.
     *
     * @param string $json Pretty-printed or compact JSON payload.
     * @return string
     */
    public static function wrap_practice_quiz_review(string $json): string {
        $wrapperopen = '<' . self::REVIEW_TAG . ' version="1">' . "\n";
        $wrapperclose = "\n" . '</' . self::REVIEW_TAG . '>';

        return $wrapperopen . $json . $wrapperclose;
    }
}
