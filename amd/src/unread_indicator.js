define([
    'block_dixeo_tutor/chat_api',
    'block_dixeo_tutor/constants'
], function(ChatAPI, constants) {
    'use strict';

    const BADGE_CLASS = 'dixeo-tutor-unread-badge';
    const SELECTOR_TOGGLE_BUTTONS = '#dixeo-chat-toggle-btn, .dixeo-tutor-popup-btn';
    /** @see theme_boost/drawers */
    const DRAWER_SHOWN_EVENT = 'theme_boost/drawers:shown';

    /**
     * @param {Element} button
     */
    function ensureBadgeOnButton(button) {
        if (!button.classList.contains('dixeo-tutor-unread-anchor')) {
            button.classList.add('dixeo-tutor-unread-anchor');
        }
        if (!button.querySelector('.' + BADGE_CLASS)) {
            const badge = document.createElement('span');
            badge.className = BADGE_CLASS;
            badge.setAttribute('aria-hidden', 'true');
            button.appendChild(badge);
        }
    }

    /**
     * @param {boolean} visible
     */
    function setBadgeVisible(visible) {
        document.querySelectorAll(SELECTOR_TOGGLE_BUTTONS).forEach((button) => {
            ensureBadgeOnButton(button);
            const badge = button.querySelector('.' + BADGE_CLASS);
            if (badge) {
                badge.classList.toggle('dixeo-tutor-unread-badge--visible', visible);
            }
        });
    }

    /**
     * Whether the tutor block is visible in the open right drawer.
     * @returns {boolean}
     */
    function isTutorVisibleInRightDrawer() {
        const drawer = document.querySelector('.drawer.drawer-right');
        if (!drawer || !drawer.classList.contains('show')) {
            return false;
        }
        const block = document.querySelector('#block-region-side-pre .block_dixeo_tutor');
        return !!(block && !block.classList.contains('d-none'));
    }

    /**
     * @param {{isOpen: function}|null} popupApi
     * @returns {boolean}
     */
    function isTutorUIOpen(popupApi) {
        if (popupApi && popupApi.isOpen()) {
            return true;
        }
        return isTutorVisibleInRightDrawer();
    }

    return {
        /**
         * Binds unread badge UI (CSS/HTML already in theme + block styles).
         *
         * @param {number} courseid
         * @param {number} lastReadWatermark Last-read incoming watermark from server (Unix seconds).
         * @param {{isOpen: function}|null} popupApi Popup API when in popup mode.
         * @returns {{onTutorOpened: function, markReadUpTo: function}}
         */
        init: function(courseid, lastReadWatermark, popupApi) {
            const api = new ChatAPI();
            let unread = false;
            let readWatermark = parseInt(lastReadWatermark, 10) || 0;
            /** Latest incoming (assistant) message Unix time from last conversation sync. */
            let syncedLastIncomingTime = 0;
            /** One-shot listener to mark read after conversation loads when tutor opened early. */
            let pendingMarkOnSync = null;

            document.querySelectorAll(SELECTOR_TOGGLE_BUTTONS).forEach((button) => {
                ensureBadgeOnButton(button);
            });
            setBadgeVisible(unread);

            /**
             * @param {number} incomingTime
             * @returns {boolean}
             */
            function isIncomingUnread(incomingTime) {
                const time = parseInt(incomingTime, 10) || 0;
                return time > 0 && time > readWatermark;
            }

            /**
             * @param {number} incomingTime
             */
            function trackIncomingTime(incomingTime) {
                const time = parseInt(incomingTime, 10) || 0;
                if (time > syncedLastIncomingTime) {
                    syncedLastIncomingTime = time;
                }
                return time;
            }

            /**
             * @param {number} explicitTime Optional message time from a proactive reply.
             */
            function resolveMarkTime(explicitTime) {
                const explicit = parseInt(explicitTime, 10) || 0;
                return Math.max(explicit, syncedLastIncomingTime);
            }

            /**
             * @param {number} lastIncomingTime Unix time of latest incoming message seen (0 = server resolves).
             */
            function persistRead(lastIncomingTime) {
                const markTime = resolveMarkTime(lastIncomingTime);
                api.markMessagesRead(courseid, markTime).then((result) => {
                    const stored = parseInt(result?.lastread, 10) || 0;
                    if (stored > readWatermark) {
                        readWatermark = stored;
                    }
                    return undefined;
                }).catch(() => {
                    // Badge is already hidden; preference will sync on next successful call.
                });
            }

            /**
             * Schedule mark-read once conversation sync provides incoming times (or immediately if ready).
             * @param {number} [lastIncomingTime]
             */
            function scheduleMarkRead(lastIncomingTime = 0) {
                const explicit = parseInt(lastIncomingTime, 10) || 0;
                const markTime = resolveMarkTime(explicit);
                if (markTime > 0) {
                    persistRead(explicit);
                    return;
                }
                if (pendingMarkOnSync) {
                    return;
                }
                pendingMarkOnSync = function() {
                    if (resolveMarkTime(explicit) > 0) {
                        persistRead(explicit);
                    } else {
                        persistRead(0);
                    }
                };
            }

            /**
             * Show badge when a new unread assistant reply arrives while the tutor is closed.
             * @param {number} incomingTime
             */
            function evaluateUnread(incomingTime) {
                const time = trackIncomingTime(incomingTime);
                if (time <= 0) {
                    return;
                }
                if (!isTutorUIOpen(popupApi) && isIncomingUnread(time)) {
                    unread = true;
                    setBadgeVisible(true);
                }
            }

            /**
             * @param {number} lastIncomingTime
             */
            function onConversationSynced(lastIncomingTime) {
                evaluateUnread(lastIncomingTime);
                if (pendingMarkOnSync) {
                    pendingMarkOnSync();
                    pendingMarkOnSync = null;
                }
            }

            /**
             * Hide badge and persist read state on the server.
             * @param {number} [lastIncomingTime]
             */
            function onTutorOpened(lastIncomingTime = 0) {
                setBadgeVisible(false);
                unread = false;
                scheduleMarkRead(lastIncomingTime);
            }

            window.addEventListener(constants.events.CONVERSATION_SYNCED, function(e) {
                onConversationSynced(e.detail?.lastIncomingTime);
            });

            window.addEventListener(constants.events.ASSISTANT_REPLIED, function(e) {
                evaluateUnread(e.detail?.lastIncomingTime);
            });

            document.addEventListener(DRAWER_SHOWN_EVENT, function(e) {
                const drawer = e.target;
                if (!drawer || !drawer.classList.contains('drawer-right')) {
                    return;
                }
                if (isTutorVisibleInRightDrawer()) {
                    onTutorOpened();
                }
            });

            return {
                onTutorOpened: () => onTutorOpened(),
                markReadUpTo: (lastIncomingTime) => onTutorOpened(lastIncomingTime),
            };
        },
    };
});
