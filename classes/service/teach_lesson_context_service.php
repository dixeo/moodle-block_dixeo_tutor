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
 * Custom teach lesson tutor context messages.
 *
 * @package    block_dixeo_tutor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_dixeo_tutor\service;

use local_dixeo\api\exception\api_exception;
use local_dixeo\dto\operation_result;
use local_dixeo\dto\tutor_message;
use local_dixeo\external\service_factory;
use local_dixeo\service\tutor_context_cleaner;
use local_dixeo\util\input_limits;

/**
 * Builds and submits custom lesson context to the tutor API.
 */
class teach_lesson_context_service {
    /**
     * Submit a completed custom lesson to the tutor conversation.
     *
     * @param int $courseid
     * @param int $userid
     * @param array $payload title, introhtml, contenthtml.
     * @return operation_result|null
     */
    public function submit_lesson(int $courseid, int $userid, array $payload): ?operation_result {
        $proactive = new tutor_proactive_context_service();
        if (!$proactive->can_use_tutor($userid, $courseid)) {
            return null;
        }

        $context = $this->build_lesson_context($payload);
        if ($context === null) {
            debugging('teach_lesson_context submit skipped: context too large or invalid', DEBUG_DEVELOPER);
            return null;
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            $title = get_string('teach_lesson_default_title', 'local_dixeo');
        }

        try {
            return service_factory::get_tutor_service()->submit(
                $courseid,
                $userid,
                tutor_message::system(
                    $context,
                    $title,
                    null,
                    false
                ),
                tutor_message::MODE_TEACH
            );
        } catch (api_exception $e) {
            debugging('teach_lesson_context submit failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Build structured custom lesson context from client payload.
     *
     * Sizes are checked on the raw input, before any copy, cleaning or encoding. A title or body
     * that cannot fit the context is refused; an intro that cannot fit is dropped, as the context
     * would drop it anyway. The HTML comes back from the browser and is rendered unescaped when the
     * lesson is reopened from the conversation, so it is cleaned here instead of being trusted.
     *
     * @param array $payload Must include title, introhtml, contenthtml.
     * @return array|null Context object or null when invalid or too large.
     */
    public function build_lesson_context(array $payload): ?array {
        $rawtitle = (string) ($payload['title'] ?? '');
        $rawintro = (string) ($payload['introhtml'] ?? '');
        $rawcontent = (string) ($payload['contenthtml'] ?? '');
        if (
            strlen($rawtitle) > input_limits::MAX_TITLE_BYTES
            || strlen($rawcontent) > tutor_context_size_helper::MAX_CONTEXT_JSON_LENGTH
        ) {
            return null;
        }
        if (strlen($rawintro) > tutor_context_size_helper::MAX_CONTEXT_JSON_LENGTH) {
            $rawintro = '';
        }

        $title = trim($rawtitle);
        if ($title === '') {
            $title = get_string('teach_lesson_default_title', 'local_dixeo');
        }

        $context = tutor_context_cleaner::clean([
            'schema' => tutor_context_schema::SCHEMA_CUSTOM_LESSON,
            'version' => 1,
            'title' => $title,
            'introhtml' => trim($rawintro),
            'contenthtml' => trim($rawcontent),
        ]);
        $context['introhtml'] = trim($context['introhtml']);
        $context['contenthtml'] = trim($context['contenthtml']);
        if ($context['contenthtml'] === '') {
            return null;
        }

        return $this->shrink_lesson_context($context);
    }

    /**
     * Progressively shrink lesson context until encoded JSON fits the limit.
     *
     * @param array $context Lesson context payload.
     * @return array|null
     */
    private function shrink_lesson_context(array $context): ?array {
        $json = tutor_context_size_helper::encode_context($context, true);
        if ($json !== null && tutor_context_size_helper::context_fits($json)) {
            return $context;
        }

        $json = tutor_context_size_helper::encode_context($context);
        if ($json !== null && tutor_context_size_helper::context_fits($json)) {
            return $context;
        }

        $context['introhtml'] = '';
        $json = tutor_context_size_helper::encode_context($context);
        if ($json !== null && tutor_context_size_helper::context_fits($json)) {
            return $context;
        }

        return null;
    }
}
