define([
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/system_message_display'
], function(constants, systemMessageDisplay) {
    'use strict';

    /** @type {number} Seconds of clock difference tolerated between Moodle and the API. */
    const NOTICE_CLOCK_SKEW = 300;

    /**
     * ReconciliationService manages message reconciliation between optimistic UI updates and server state.
     * This service handles the complex logic of merging new messages from the server with the UI,
     * particularly dealing with optimistic user message bubbles that need to be replaced with
     * canonical server messages.
     */
    return class ReconciliationService {
        /**
         * Constructs the reconciliation service.
         * @param {ChatUI} ui The UI manager instance.
         * @param {ChatState} state The application state manager.
         */
        constructor(ui, state) {
            this.ui = ui;
            this.state = state;
            // Local notice bubbles awaiting their stored copy: temporary id → notice.
            this.localNotices = new Map();
        }

        /**
         * Remember a notice shown locally, so its stored copy takes over the bubble instead of showing twice.
         * @param {number} tempId Temporary (negative) id of the bubble.
         * @param {{content: string, time: number}} notice Notice as rendered by the server.
         */
        trackLocalNotice(tempId, notice) {
            this.localNotices.set(tempId, notice);
        }

        /**
         * Whether a server message is the stored copy of a notice: same text, stored after it was shown.
         * The previous identical greeting is at least a day older, so a few minutes absorb any clock
         * difference between Moodle and the API.
         * @param {object} msg Message from the server.
         * @param {{content: string, time: number}} notice
         * @returns {boolean}
         */
        isNoticeCopy(msg, notice) {
            return msg.role === 'assistant'
                && String(msg.content || '').trim() === String(notice.content).trim()
                && (parseInt(msg.time, 10) || 0) >= notice.time - NOTICE_CLOCK_SKEW;
        }

        /**
         * Merges received messages with rendered ones, handling optimistic updates.
         * This is the main entry point for reconciling server messages with the UI state.
         * @param {Array<object>} messages Raw array of messages from the backend.
         * @param {number|null} pendingTempId The temporary ID of optimistic message awaiting replacement.
         * @returns {number|null} Updated pendingTempId value after reconciliation.
         */
        reconcile(messages, pendingTempId) {
            const canonicalMessages = Array.isArray(messages) ? messages : [];
            let updatedPendingTempId = pendingTempId;

            canonicalMessages.forEach(msg => {
                const optimisticResult = this._processOptimisticMessage(msg, updatedPendingTempId);
                if (optimisticResult.wasOptimistic) {
                    updatedPendingTempId = optimisticResult.updatedTempId;
                    return;
                }
                if (this._adoptLocalNotice(msg)) {
                    return;
                }
                this._appendNewMessage(msg);
            });

            this._updateConversationState(canonicalMessages);
            return updatedPendingTempId;
        }

        /**
         * Process optimistic message updates by replacing temporary bubbles with canonical ones.
         * @private
         * @param {object} msg Message to process from server.
         * @param {number|null} pendingTempId Current pending temporary ID.
         * @returns {object} Result object with wasOptimistic flag and updated temp ID.
         */
        _processOptimisticMessage(msg, pendingTempId) {
            // Only user messages can resolve optimistic updates.
            if (pendingTempId === null || msg.role !== 'user') {
                return {wasOptimistic: false, updatedTempId: pendingTempId};
            }

            const updated = this.ui.updateMessageId(pendingTempId, msg);
            if (updated) {
                this.state.setLastRenderedId(msg.id);
                return {wasOptimistic: true, updatedTempId: null};
            }

            return {wasOptimistic: false, updatedTempId: pendingTempId};
        }

        /**
         * Give a local notice bubble the id of its stored copy, which then is not rendered again.
         * @private
         * @param {object} msg Message from the server.
         * @returns {boolean} True when the message was a local notice's stored copy.
         */
        _adoptLocalNotice(msg) {
            const entry = [...this.localNotices].find(([, notice]) => this.isNoticeCopy(msg, notice));
            if (!entry) {
                return false;
            }
            this.localNotices.delete(entry[0]);
            if (!this.ui.updateMessageId(entry[0], msg)) {
                return false;
            }
            this.state.setLastRenderedId(msg.id);
            return true;
        }

        /**
         * Append new message if not already rendered in the UI.
         * @private
         * @param {object} msg Message to append to the conversation.
         */
        _appendNewMessage(msg) {
            const isNew = !this.ui.hasMessage(msg.id);
            if (isNew) {
                this.ui.appendMessage(msg);
                if (String(msg.role || '').toLowerCase() === 'assistant'
                        && !systemMessageDisplay.isProactiveMessage(msg)) {
                    window.dispatchEvent(new CustomEvent(constants.events.ASSISTANT_REPLIED, {
                        detail: {
                            lastIncomingTime: parseInt(msg.time, 10) || 0,
                        },
                    }));
                }
            }
            // Always track even if already rendered — keeps lastRenderedId current.
            this.state.setLastRenderedId(msg.id);
        }

        /**
         * Update conversation state based on reconciled messages.
         * Handles cleanup tasks like clearing drafts when assistant responds.
         * @private
         * @param {Array<object>} messages Canonical messages from server.
         */
        _updateConversationState(messages) {
            if (!messages.length) {
                return;
            }

            const lastMessage = messages[messages.length - 1];
            if (String(lastMessage.role || '').toLowerCase() === 'assistant') {
                // Assistant replied — safe to discard draft and polling checkpoint.
                this.state.clearDraft();
                this.state.clearPollState();
            }
        }
    };
});
