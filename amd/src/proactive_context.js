define([], function() {
    'use strict';

    /** Must match {@see \block_dixeo_tutor\service\tutor_proactive_context_service::PROACTIVE_CONTEXT_TAG}. */
    const PROACTIVE_CONTEXT_TAG = 'proactive-context';
    const PROACTIVE_CONTEXT_PATTERN = new RegExp('<' + PROACTIVE_CONTEXT_TAG + '[\\s>]', 'i');

    /**
     * Whether site-level developer debugging is enabled (Moodle $CFG->debugdeveloper).
     * @returns {boolean}
     */
    function isDeveloperDebugEnabled() {
        // Hardcode to false for now.
        // return !!(typeof window !== 'undefined' && window.M?.cfg?.developerdebug);
        return false;
    }

    /**
     * Whether a conversation message payload is wrapped proactive system context.
     * @param {object} message Message with content and/or text fields.
     * @returns {boolean}
     */
    function isProactiveContextMessage(message) {
        if (!message) {
            return false;
        }
        const content = message.content ?? message.text ?? '';
        return PROACTIVE_CONTEXT_PATTERN.test(String(content));
    }

    /**
     * Filter conversation messages for UI display.
     * Proactive context is hidden unless developer debugging is on for the site.
     * @param {Array<object>} messages Messages from the tutor API.
     * @returns {Array<object>}
     */
    function filterMessagesForDisplay(messages) {
        if (!Array.isArray(messages)) {
            return [];
        }
        if (isDeveloperDebugEnabled()) {
            return messages;
        }
        return messages.filter((msg) => !isProactiveContextMessage(msg));
    }

    return {
        PROACTIVE_CONTEXT_TAG,
        isDeveloperDebugEnabled,
        isProactiveContextMessage,
        filterMessagesForDisplay,
    };
});
