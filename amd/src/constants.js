define([], function() {
    'use strict';

    return Object.freeze({
        // Polling Configuration
        polling: Object.freeze({
            BACKOFF_FACTOR: 1.5,
            REPLY_INTERVAL_MS: 3000,
            STATE_EXPIRY_MS: 10 * 60 * 1000, // 10 minutes (aligned with TIMEOUT_MS)
            TIMEOUT_MS: 10 * 60 * 1000, // 10 minutes timeout for polling
        }),

        // UI Configuration
        ui: Object.freeze({
            TEXTAREA_MAX_HEIGHT: 120,
            TIME_FORMAT: {hour: '2-digit', minute: '2-digit'},
            MAX_MESSAGE_LENGTH: 2000,
            /** Px from bottom to consider "at bottom" for user-scrolled-up tracker. */
            SCROLL_BOTTOM_THRESHOLD: 20,
            /** Px from top to show the load-older control. */
            SCROLL_TOP_THRESHOLD: 20,
            /** Conversation page size (must match tutor_service default limit). */
            MESSAGE_PAGE_SIZE: 50,
        }),

        // DOM Selectors
        selectors: Object.freeze({
            INPUT_FIELD: 'dixeo-tutor-input',
        }),

        // Network Configuration
        network: Object.freeze({
            AJAX_TIMEOUT: 90000, // 90 seconds
            MAX_RETRIES: 3,
            RETRY_DELAY: 1000,
        }),

        // Accessibility
        a11y: Object.freeze({
            LIVE_REGION_DELAY: 100,
        }),

        // Events
        events: Object.freeze({
            SEND_MESSAGE: 'sendMessage',
            RETRY_SEND_MESSAGE: 'retrySendMessage',
            LOAD_OLDER_MESSAGES: 'loadOlderMessages',
            /** Window CustomEvent: new assistant message while tutor may be closed. */
            ASSISTANT_REPLIED: 'dixeo-tutor-assistant-replied',
            /** Window CustomEvent: proactive context flush completed with an assistant reply. */
            PROACTIVE_REPLY_READY: 'dixeo-tutor-proactive-reply-ready',
            /** Window CustomEvent: conversation snapshot updated (detail: {lastIncomingTime}). */
            CONVERSATION_SYNCED: 'dixeo-tutor-conversation-synced',
        }),
    });
});
