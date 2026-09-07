define([
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/system_message_display'
], function(constants, systemMessageDisplay) {
    'use strict';

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
