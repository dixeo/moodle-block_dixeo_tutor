/**
 * Shared custom-lesson helpers (no teach_lesson_view dependency).
 *
 * @module     block_dixeo_tutor/custom_lesson_utils
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'block_dixeo_tutor/text_utils',
], function(textUtils) {
    'use strict';

    /** Must match {@see \block_dixeo_tutor\service\tutor_context_schema::SCHEMA_CUSTOM_LESSON}. */
    const SCHEMA_CUSTOM_LESSON = 'custom_lesson';

    /**
     * @param {object} lesson
     * @returns {object}
     */
    function lessonFromPayload(lesson) {
        return {
            schema: SCHEMA_CUSTOM_LESSON,
            version: 1,
            title: lesson.title || '',
            introhtml: lesson.introhtml || '',
            contenthtml: lesson.contenthtml || '',
        };
    }

    /**
     * @param {object} data Parsed lesson context.
     * @returns {string}
     */
    function previewText(data) {
        const plain = textUtils.htmlToPlain(data.introhtml || '');
        return textUtils.truncateAtWordBoundary(plain, 200);
    }

    /**
     * @param {object} data Parsed lesson context.
     * @returns {string}
     */
    function ttsText(data) {
        const intro = textUtils.htmlToPlain(data.introhtml || '');
        const content = textUtils.htmlToPlain(data.contenthtml || '');
        return [intro, content].filter(Boolean).join(' ').trim();
    }

    /**
     * Stable TTS group id so card and lesson panel share playback state.
     *
     * @param {object} data Parsed lesson context.
     * @returns {string}
     */
    function lessonTtsGroupId(data) {
        const text = ttsText(data);
        return 'custom-lesson:' + text.length + ':' + text.substring(0, 200);
    }

    return {
        SCHEMA_CUSTOM_LESSON,
        lessonFromPayload,
        previewText,
        ttsText,
        lessonTtsGroupId,
    };
});
