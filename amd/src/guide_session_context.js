define([], function() {
    'use strict';

    /** Must match {@see \block_dixeo_tutor\service\tutor_context_schema::SCHEMA_GUIDE_ASSISTANT}. */
    const SCHEMA_GUIDE_ASSISTANT = 'guide_assistant';
    /** Must match {@see \block_dixeo_tutor\service\tutor_context_schema::SCHEMA_GUIDE_SESSION}. */
    const SCHEMA_GUIDE_SESSION = 'guide_session';

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
     * @param {object} message
     * @returns {{title: string, description: string}|null}
     */
    function parseSessionFields(message) {
        const schema = contextSchema(message);
        if (schema !== SCHEMA_GUIDE_ASSISTANT && schema !== SCHEMA_GUIDE_SESSION) {
            return null;
        }
        const ctx = message.context || {};
        const title = String(ctx.title || '').trim();
        const description = String(ctx.description || '').trim();
        if (!title || !description) {
            return null;
        }
        return {title: title, description: description};
    }

    /**
     * @param {object} message
     * @returns {{title: string, description: string, isUnderstood: boolean}|null}
     */
    function parseAssistantContext(message) {
        if (!message || String(message.role || '').toLowerCase() !== 'assistant') {
            return null;
        }
        if (contextSchema(message) !== SCHEMA_GUIDE_ASSISTANT) {
            return null;
        }
        const fields = parseSessionFields(message);
        if (!fields) {
            return null;
        }
        const ctx = message.context || {};
        return {
            title: fields.title,
            description: fields.description,
            isUnderstood: !!ctx.isUnderstood,
        };
    }

    /**
     * @param {object} message
     * @returns {boolean}
     */
    function isGuideLaneMessage(message) {
        return parseSessionFields(message) !== null;
    }

    return {
        SCHEMA_GUIDE_ASSISTANT,
        SCHEMA_GUIDE_SESSION,
        contextSchema,
        parseSessionFields,
        parseAssistantContext,
        isGuideLaneMessage,
    };
});
