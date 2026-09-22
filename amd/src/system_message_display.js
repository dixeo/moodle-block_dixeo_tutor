define([], function() {
    'use strict';

    const SCHEMA_PROACTIVE = 'proactive';
    const SCHEMA_PRACTICE_QUIZ_REVIEW = 'practice_quiz_review';
    const SCHEMA_CUSTOM_LESSON = 'custom_lesson';

    /**
     * @param {object} message
     * @returns {string}
     */
    function contextSchema(message) {
        if (!message) {
            return '';
        }
        const ctx = message.context;
        if (typeof ctx === 'object' && ctx !== null && ctx.schema) {
            return String(ctx.schema);
        }
        return '';
    }

    /**
     * Whether a message is a hidden proactive system message.
     *
     * @param {object} message
     * @returns {boolean}
     */
    function isProactiveMessage(message) {
        if (!message) {
            return false;
        }
        if (String(message.role || '').toLowerCase() !== 'system') {
            return false;
        }
        const schema = contextSchema(message);
        if (schema === SCHEMA_PROACTIVE) {
            return true;
        }
        // Legacy / incomplete payloads carry events without schema.
        const ctx = message.context;
        return typeof ctx === 'object' && ctx !== null && Array.isArray(ctx.events);
    }

    /**
     * Whether a message is a practice quiz review system message.
     *
     * @param {object} message
     * @returns {boolean}
     */
    function isPracticeQuizReviewMessage(message) {
        if (!message) {
            return false;
        }
        return String(message.role || '').toLowerCase() === 'system'
            && contextSchema(message) === SCHEMA_PRACTICE_QUIZ_REVIEW;
    }

    /**
     * System rows with no body that are not structured UI cards (quiz/lesson).
     *
     * @param {object} message
     * @returns {boolean}
     */
    function isEmptyShellSystemMessage(message) {
        if (!message || String(message.role || '').toLowerCase() !== 'system') {
            return false;
        }
        const schema = contextSchema(message);
        if (schema === SCHEMA_PRACTICE_QUIZ_REVIEW || schema === SCHEMA_CUSTOM_LESSON) {
            return false;
        }
        const content = String(message.content || '').trim();
        const contentHtml = String(message.contenthtml || '').trim();
        return content === '' && contentHtml === '';
    }

    /**
     * Whether the message should be omitted from the chat transcript.
     *
     * @param {object} message
     * @returns {boolean}
     */
    function isHiddenSystemMessage(message) {
        return isProactiveMessage(message) || isEmptyShellSystemMessage(message);
    }

    /**
     * Filter conversation messages for UI display.
     *
     * @param {Array<object>} messages Messages from the tutor API.
     * @returns {Array<object>}
     */
    function filterMessagesForDisplay(messages) {
        if (!Array.isArray(messages)) {
            return [];
        }
        return messages.filter((msg) => !isHiddenSystemMessage(msg));
    }

    return {
        SCHEMA_PROACTIVE,
        SCHEMA_PRACTICE_QUIZ_REVIEW,
        contextSchema,
        isProactiveMessage,
        isPracticeQuizReviewMessage,
        isEmptyShellSystemMessage,
        isHiddenSystemMessage,
        filterMessagesForDisplay,
    };
});
