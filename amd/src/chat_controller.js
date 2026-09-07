define([
    'core/str',
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/errors',
    'block_dixeo_tutor/reconciliation_service',
    'block_dixeo_tutor/system_message_display',
    'block_dixeo_tutor/text_utils'
], function(str, constants, errors, ReconciliationService, systemMessageDisplay, textUtils) {
    'use strict';

    return class ChatController {
        /**
         * Constructs the controller.
         * @param {ChatState} state The application state manager.
         * @param {ChatUI} ui The UI manager.
         * @param {ChatAPI} api The API service.
         * @param {Object|null} modeController Tutor mode controller.
         */
        constructor(state, ui, api, modeController = null) {
            this.state = state;
            this.ui = ui;
            this.api = api;
            this.modeController = modeController;
            this.reconciler = new ReconciliationService(ui, state);
            this.pendingTempId = null;
            this.replyPollTimeoutId = null;
            this.tempIdCounter = 0;
            this.connectionRetryTimeoutId = null;
            this.connectionRetryDelay = 1000;
            this.maxConnectionRetryDelay = 30000;
            this.isConnectionLost = false;
            this._loadingOlder = false;
            // Event handler references stored for cleanup in destroy().
            this._onOffline = null;
            this._onOnline = null;
            this._onBeforeUnload = null;
            this._onStorageChange = null;
        }

        /**
         * Initializes the controller, binds events, and checks the initial state.
         */
        initialize() {
            this._bindEvents();
            this._bindNetworkEvents();
            this._bindPageUnload();
            this._bindStorageEvents();

            if (!navigator.onLine) {
                this._handleConnectionLoss();
                return;
            }

            this._initSequence();
        }

        /**
         * Flush proactive context, load conversation, restore session state.
         * @private
         */
        async _initSequence() {
            try {
                await this._flushPendingContext();
                await this._checkInitialState();
                this._restoreSession();
                this._syncPendingUi();
            } catch (e) {
                if (e instanceof errors.NetworkError || e instanceof errors.TimeoutError) {
                    this._handleConnectionLoss();
                } else {
                    this.ui.appendErrorMessage('Failed to initialize chat.');
                }
            }
        }

        /**
         * Messages to render in the chat (proactive context hidden unless developer debug is on).
         * @param {Array<object>} messages Raw messages from the API.
         * @returns {Array<object>}
         * @private
         */
        _messagesForDisplay(messages) {
            return systemMessageDisplay.filterMessagesForDisplay(messages);
        }

        /**
         * Notify listeners when a new non-proactive assistant message arrived.
         * @param {{lastincomingtime?: number}} conversationData Conversation API response (full or delta).
         * @private
         */
        _dispatchAssistantRepliedForMessages(conversationData) {
            const lastIncomingTime = parseInt(conversationData?.lastincomingtime, 10) || 0;
            if (lastIncomingTime <= 0) {
                return;
            }
            window.dispatchEvent(new CustomEvent(constants.events.ASSISTANT_REPLIED, {
                detail: {lastIncomingTime},
            }));
        }

        /**
         * Publish latest incoming (assistant) message time for unread mark-read on tutor open.
         * @param {{lastincomingtime?: number}} conversationData Conversation API response (full or delta).
         * @private
         */
        _emitConversationSynced(conversationData) {
            const lastIncomingTime = parseInt(conversationData?.lastincomingtime, 10) || 0;
            window.dispatchEvent(new CustomEvent(constants.events.CONVERSATION_SYNCED, {
                detail: {lastIncomingTime},
            }));
        }

        /**
         * Keep loading UI in sync when a reply job is in flight (proactive flush, restore, etc.).
         * @private
         */
        _syncPendingUi() {
            if (!this.state.isPending()) {
                return;
            }
            this.ui.disableInput();
            this.ui.showPendingIndicator();
        }

        /**
         * Submit queued proactive context lines when the tutor UI loads.
         * @private
         */
        async _flushPendingContext() {
            if (this.modeController?.isPersisting()) {
                return;
            }

            const response = await this.api.flushPendingContext(
                this.state.getCourseId(),
                window.location.href
            );

            if (!response.flushed || !response.jobid) {
                return;
            }

            this.state.setPending(true);
            this.ui.disableInput();
            this.ui.showPendingIndicator();
            this.state.savePollState({
                isPending: true,
                jobId: response.jobid,
                timestamp: Date.now(),
                fromProactiveFlush: true,
            });
            this._pollForJobCompletion(response.jobid);
        }

        /**
         * Binds listeners to events from the UI.
         * @private
         */
        _bindEvents() {
            this.ui.on(constants.events.SEND_MESSAGE, (message) => this.handleSendMessage(message));
            this.ui.on(constants.events.RETRY_SEND_MESSAGE, (message) => this.handleSendMessage(message));
            this.ui.on(constants.events.LOAD_OLDER_MESSAGES, () => this._handleLoadOlderMessages());
        }

        /**
         * Binds browser network event listeners for offline/online detection.
         * Stores references for cleanup in destroy().
         * @private
         */
        _bindNetworkEvents() {
            this._onOffline = () => {
                this._handleConnectionLoss();
            };

            this._onOnline = () => {
                if (this.isConnectionLost) {
                    this.isConnectionLost = false;
                    this.ui.hideConnectionLost();
                    this.connectionRetryDelay = 1000;

                    // Resume reply polling if we were waiting for a response.
                    if (this.state.isPending()) {
                        const pollState = this.state.getPollState();
                        if (pollState && pollState.jobId) {
                            this._pollForJobCompletion(pollState.jobId);
                        } else {
                            this._pollForReply();
                        }
                    }
                }
            };

            window.addEventListener('offline', this._onOffline);
            window.addEventListener('online', this._onOnline);
        }

        /**
         * Sets up cleanup handler for when the page is unloaded.
         * Ensures polling state is preserved for page reloads.
         * @private
         */
        _bindPageUnload() {
            this._onBeforeUnload = () => {
                if (this.replyPollTimeoutId) {
                    const existingState = this.state.getPollState();
                    this.state.savePollState({
                        isPending: true,
                        jobId: existingState?.jobId || null,
                        timestamp: existingState?.timestamp || Date.now()
                    });
                }
            };

            window.addEventListener('beforeunload', this._onBeforeUnload);
        }

        /**
         * Binds localStorage events for cross-tab poll synchronization.
         * The 'storage' event fires only in OTHER tabs when localStorage changes.
         * Draft text is never stored in localStorage (memory only).
         * @private
         */
        _bindStorageEvents() {
            const pollingKey = this.state.getPollingStorageKey();
            this._onStorageChange = (event) => {
                if (event.key === pollingKey && event.newValue === null && this.state.isPending()) {
                    this._onCrossTabReplyReceived();
                }
            };
            window.addEventListener('storage', this._onStorageChange);
        }

        /**
         * Handles reply received in another tab.
         * Reloads conversation to display the new messages.
         * @private
         */
        async _onCrossTabReplyReceived() {
            const wasAwaitingReply = this.state.isPending();
            this._stopPolling();
            this.state.setPending(false);
            this.state.clearDraft();

            try {
                const data = await this.api.loadConversation(this.state.getCourseId());
                await this._handleInitialState(data);
                if (wasAwaitingReply) {
                    const messages = this._messagesForDisplay(data.messages);
                    const last = messages.length ? messages[messages.length - 1] : null;
                    if (last && last.role === 'assistant') {
                        window.dispatchEvent(new CustomEvent(constants.events.ASSISTANT_REPLIED));
                    }
                }
            } catch (e) {
                // Sync failed, but still restore UI so user isn't stuck.
                this.ui.enableInput();
            }
        }

        /**
         * Fetches the initial state from the server.
         * @private
         */
        async _checkInitialState() {
            if (!navigator.onLine) {
                this._handleConnectionLoss();
                return;
            }

            try {
                const conversationData = await this.api.loadConversation(this.state.getCourseId());
                await this._handleInitialState(conversationData);
            } catch (e) {
                if (e instanceof errors.NetworkError || e instanceof errors.TimeoutError) {
                    this._handleConnectionLoss();
                } else {
                    this.ui.appendErrorMessage('Failed to initialize chat.');
                }
            }
        }

        /**
         * Processes the server state and renders the conversation history.
         * @param {object} conversationData The response from loadConversation.
         * @private
         */
        async _handleInitialState(conversationData) {
            const rawMessages = Array.isArray(conversationData.messages) ? conversationData.messages : [];
            const messages = this._messagesForDisplay(rawMessages);

            this._updatePaginationState(rawMessages);

            await this.ui.renderMessageHistory(messages);

            if (messages.length) {
                this.state.setLastRenderedId(messages[messages.length - 1].id);
            }

            this.ui.syncLoadOlderControl(this.state.getHasMoreOlder());

            this._emitConversationSynced(conversationData);

            if (!this.state.isPending()) {
                this.ui.enableInput();
            }
        }

        /**
         * Track oldest-loaded cursor and whether more older pages exist.
         * @param {Array<object>} rawMessages Unfiltered API batch.
         * @private
         */
        _updatePaginationState(rawMessages) {
            const pageSize = constants.ui.MESSAGE_PAGE_SIZE;
            this.state.setHistoryOffset(0);
            if (!rawMessages.length) {
                this.state.setOldestLoadedId(null);
                this.state.setHasMoreOlder(false);
                return;
            }
            this.state.setOldestLoadedId(rawMessages[0].id);
            this.state.setHasMoreOlder(rawMessages.length === pageSize);
        }

        /**
         * Loads and prepends the next page of older messages.
         * @private
         */
        async _handleLoadOlderMessages() {
            if (this._loadingOlder || !this.state.getHasMoreOlder()) {
                return;
            }

            const oldestId = this.state.getOldestLoadedId();
            if (!oldestId) {
                return;
            }

            const nextOffset = this.state.getHistoryOffset() + constants.ui.MESSAGE_PAGE_SIZE;

            this._loadingOlder = true;
            this.ui.setLoadOlderLoading(true);

            try {
                const data = await this.api.loadConversation(
                    this.state.getCourseId(),
                    null,
                    nextOffset
                );
                const rawBatch = Array.isArray(data.messages) ? data.messages : [];
                const displayBatch = this._messagesForDisplay(rawBatch)
                    .filter((msg) => msg.id && !this.ui.hasMessage(msg.id));

                await this.ui.prependMessages(displayBatch);

                if (rawBatch.length) {
                    this.state.setHistoryOffset(nextOffset);
                    this.state.setOldestLoadedId(rawBatch[0].id);
                }
                this.state.setHasMoreOlder(
                    rawBatch.length === constants.ui.MESSAGE_PAGE_SIZE && displayBatch.length > 0
                );
                this.ui.syncLoadOlderControl(this.state.getHasMoreOlder());
            } catch (e) {
                if (e instanceof errors.NetworkError || e instanceof errors.TimeoutError) {
                    this.ui.appendErrorMessage(await str.get_string('error_network', 'block_dixeo_tutor'));
                } else {
                    this.ui.appendErrorMessage(await str.get_string('unknownerror', 'block_dixeo_tutor'));
                }
                this.ui.syncLoadOlderControl(this.state.getHasMoreOlder());
            } finally {
                this._loadingOlder = false;
                this.ui.setLoadOlderLoading(false);
            }
        }

        /**
         * Handles the user sending a message via async job flow.
         * @param {string} message The content entered by the user.
         */
        async handleSendMessage(message) {
            if (!message?.trim()) {
                return;
            }

            if (this.modeController?.isPersisting()) {
                return;
            }

            if (!navigator.onLine) {
                this._handleConnectionLoss();
                return;
            }

            // 1. Create an optimistic bubble with a negative temp ID.
            const tempId = --this.tempIdCounter;
            const timestamp = Math.floor(Date.now() / 1000);
            this.ui.appendMessage({id: tempId, role: 'user', content: message, time: timestamp});

            this.pendingTempId = tempId;
            this.state.setDraft(message);

            this.state.setPending(true);
            this.ui.disableInput();
            this.ui.showPendingIndicator();

            this.state.savePollState({isPending: true, timestamp: Date.now()});

            try {
                const response = await this.api.sendMessage(
                    this.state.getCourseId(),
                    message,
                    window.location.href
                );

                if (response.errormessage) {
                    this.state.setPending(false);
                    this.ui.enableInput();
                    const errorMsg = await str.get_string('error_apierror', 'block_dixeo_tutor');
                    this.ui.appendErrorMessage(errorMsg);
                    return;
                }

                // Store jobid and start polling.
                const jobId = response.jobid;
                this.state.savePollState({isPending: true, jobId: jobId, timestamp: Date.now()});
                this._pollForJobCompletion(jobId);

            } catch (err) {
                let errorMsg;
                if (err instanceof errors.TimeoutError) {
                    errorMsg = await str.get_string('error_timeout', 'block_dixeo_tutor');
                } else if (err instanceof errors.NetworkError) {
                    errorMsg = await str.get_string('error_network', 'block_dixeo_tutor');
                } else {
                    errorMsg = await str.get_string('errorsendmessage', 'block_dixeo_tutor');
                }

                // Remove optimistic bubble since send failed.
                if (this.pendingTempId) {
                    this.ui.removeMessage(this.pendingTempId);
                    this.pendingTempId = null;
                }

                this.state.clearDraft();
                this.state.clearPollState();
                this.state.setPending(false);
                this.ui.enableInput();
                this.ui.appendErrorWithRetry(message, errorMsg);
            }
        }

        /**
         * Polls for job completion, then fetches conversation delta.
         * @param {string} jobId The job UUID to poll.
         * @private
         */
        async _pollForJobCompletion(jobId) {
            if (this.replyPollTimeoutId) {
                clearTimeout(this.replyPollTimeoutId);
                this.replyPollTimeoutId = null;
            }

            const pollState = this.state.getPollState();
            if (pollState && pollState.timestamp) {
                const pollAge = Date.now() - pollState.timestamp;
                if (pollAge >= constants.polling.TIMEOUT_MS) {
                    this.state.clearPollState();
                    this._handlePollingTimeout();
                    return;
                }
            }

            this.replyPollTimeoutId = setTimeout(async() => {
                if (!navigator.onLine) {
                    this._handleConnectionLoss();
                    return;
                }

                try {
                    const jobStatus = await this.api.pollJobStatus(jobId, this.state.getCourseId());

                    if (jobStatus.status === 'completed') {
                        const pollState = this.state.getPollState();
                        const fromProactiveFlush = !!(pollState && pollState.fromProactiveFlush);

                        const delta = await this.api.loadConversation(
                            this.state.getCourseId(),
                            this.state.getLastRenderedId()
                        );
                        const rawDelta = Array.isArray(delta.messages) ? delta.messages : [];
                        const deltaMessages = this._messagesForDisplay(rawDelta);

                        this.pendingTempId = this.reconciler.reconcile(deltaMessages, this.pendingTempId);
                        this._emitConversationSynced(delta);
                        this._dispatchAssistantRepliedForMessages(delta);
                        if (fromProactiveFlush) {
                            window.dispatchEvent(new CustomEvent(constants.events.PROACTIVE_REPLY_READY, {
                                detail: {
                                    lastIncomingTime: parseInt(delta.lastincomingtime, 10) || 0,
                                },
                            }));
                        }
                        this.state.clearDraft();
                        this.state.clearPollState();
                        this.state.setPending(false);
                        this.ui.hidePendingIndicator();
                        this.ui.enableInput();

                    } else if (jobStatus.status === 'failed') {
                        const errorMsg = await str.get_string('errorsendmessage', 'block_dixeo_tutor');

                        if (this.pendingTempId) {
                            this.ui.removeMessage(this.pendingTempId);
                            this.pendingTempId = null;
                        }

                        this.state.clearDraft();
                        this.state.clearPollState();
                        this.state.setPending(false);
                        this.ui.hidePendingIndicator();
                        this.ui.enableInput();
                        this.ui.appendErrorMessage(errorMsg);

                    } else {
                        // Still processing — continue polling.
                        this._pollForJobCompletion(jobId);
                    }

                } catch (e) {
                    if (e instanceof errors.NetworkError || e instanceof errors.TimeoutError) {
                        setTimeout(() => this._pollForJobCompletion(jobId), constants.polling.REPLY_INTERVAL_MS * 2);
                    } else {
                        this.state.clearPollState();
                        this.state.setPending(false);
                        this.ui.enableInput();
                        str.get_string('unknownerror', 'block_dixeo_tutor').then(msg => {
                            this.ui.appendErrorMessage(msg);
                            return null;
                        }).catch(() => {
                            // Ignore string load failures.
                        });
                    }
                }
            }, constants.polling.REPLY_INTERVAL_MS);
        }

        /**
         * Periodically checks the conversation history for new messages.
         * Fallback when no jobId is available.
         * @private
         */
        async _pollForReply() {
            if (this.replyPollTimeoutId) {
                clearTimeout(this.replyPollTimeoutId);
                this.replyPollTimeoutId = null;
            }

            const pollState = this.state.getPollState();
            if (pollState && pollState.timestamp) {
                const pollAge = Date.now() - pollState.timestamp;
                if (pollAge >= constants.polling.TIMEOUT_MS) {
                    this.state.clearPollState();
                    this._handlePollingTimeout();
                    return;
                }
            }

            this.replyPollTimeoutId = setTimeout(async() => {
                if (!navigator.onLine) {
                    this._handleConnectionLoss();
                    return;
                }

                try {
                    const data = await this.api.loadConversation(
                        this.state.getCourseId(),
                        this.state.getLastRenderedId()
                    );

                    const rawMessages = Array.isArray(data.messages) ? data.messages : [];
                    const visibleMessages = this._messagesForDisplay(rawMessages);
                    this.pendingTempId = this.reconciler.reconcile(visibleMessages, this.pendingTempId);
                    this._emitConversationSynced(data);

                    if (visibleMessages.length > 0) {
                        const lastMessage = visibleMessages[visibleMessages.length - 1];
                        if (lastMessage.role === 'assistant') {
                            this._dispatchAssistantRepliedForMessages(data);
                            this.state.clearPollState();
                            this.state.clearDraft();
                            this.state.setPending(false);
                            this.ui.hidePendingIndicator();
                            this.ui.enableInput();
                            return;
                        }
                    }

                    this.state.savePollState({
                        isPending: true,
                        timestamp: pollState ? pollState.timestamp : Date.now()
                    });

                    this._pollForReply();

                } catch (e) {
                    if (e instanceof errors.NetworkError || e instanceof errors.TimeoutError) {
                        setTimeout(() => this._pollForReply(), constants.polling.REPLY_INTERVAL_MS * 2);
                    } else {
                        this.state.clearPollState();
                        this.state.setPending(false);
                        this.ui.enableInput();
                        str.get_string('unknownerror', 'block_dixeo_tutor').then(msg => {
                            this.ui.appendErrorMessage(msg);
                            return null;
                        }).catch(() => {
                            // Ignore string load failures.
                        });
                    }
                }
            }, constants.polling.REPLY_INTERVAL_MS);
        }

        /**
         * Stops reply polling.
         * @private
         */
        _stopPolling() {
            if (this.replyPollTimeoutId) {
                clearTimeout(this.replyPollTimeoutId);
                this.replyPollTimeoutId = null;
            }
        }

        /**
         * Restores same-tab session state: in-memory draft (if any) and poll checkpoint.
         * @private
         */
        _restoreSession() {
            const savedPollState = this.state.getPollState();
            const messages = this.ui.getRenderedMessages();

            const draft = this.state.getDraft();
            if (draft && draft.trim()) {
                const draftContent = draft.trim();
                const draftIsDuplicate = this._isDraftAlreadyRendered(draftContent, messages);

                if (!draftIsDuplicate) {
                    const tempId = --this.tempIdCounter;
                    this.pendingTempId = tempId;
                    this.ui.appendMessage({
                        id: tempId,
                        role: 'user',
                        content: draft,
                        time: Math.floor(Date.now() / 1000)
                    }, false);

                    this.state.setPending(true);
                    this.ui.disableInput();
                    this.ui.showPendingIndicator();
                } else {
                    this.state.clearDraft();
                }
            }

            // Resume polling if it was active before page reload (skip if flush already started polling).
            if (savedPollState && savedPollState.isPending && !this.replyPollTimeoutId) {
                const currentMessages = this.ui.getRenderedMessages();
                const currentLastMsg = currentMessages.length ? currentMessages[currentMessages.length - 1] : null;

                if (currentLastMsg && currentLastMsg.role === 'user') {
                    const pollAge = Date.now() - savedPollState.timestamp;
                    if (pollAge < constants.polling.TIMEOUT_MS) {
                        this.state.setPending(true);
                        this.ui.disableInput();
                        this.ui.showPendingIndicator();

                        // Resume job polling or conversation polling.
                        if (savedPollState.jobId) {
                            this._pollForJobCompletion(savedPollState.jobId);
                        } else {
                            this._pollForReply();
                        }
                    } else {
                        this.state.clearPollState();
                        this._handlePollingTimeout();
                    }
                } else {
                    this.state.clearPollState();
                }
            }
        }

        /**
         * Checks if a draft message is already rendered in the UI.
         * @param {string} draftContent The draft message content to check.
         * @param {Array<object>} messages Array of currently rendered messages.
         * @returns {boolean} True if the draft is already rendered.
         * @private
         */
        _isDraftAlreadyRendered(draftContent, messages) {
            if (!messages || messages.length === 0) {
                return false;
            }

            const recentUserMessages = messages
                .filter(msg => msg.role === 'user')
                .slice(-3);

            return recentUserMessages.some(msg =>
                this.ui.getMessageContent(msg.id)?.trim() === draftContent
            );
        }

        /**
         * Handles polling timeout by showing appropriate UI and clearing state.
         * @private
         */
        _handlePollingTimeout() {
            this._showTimeoutMessage();
            this.state.setPending(false);
            this.ui.hidePendingIndicator();
            this.ui.enableInput();
        }

        /**
         * Shows a timeout message to the user with a retry option.
         * @private
         */
        async _showTimeoutMessage() {
            const timeoutMsg = await str.get_string('timeout_message', 'block_dixeo_tutor');
            const checkUpdatesText = await str.get_string('check_for_updates', 'block_dixeo_tutor');

            const wrapper = document.createElement('div');
            wrapper.innerHTML = `
                <div class="d-flex justify-content-center mb-2">
                    <div class="dixeo-tutor-message dixeo-tutor-message-system alert alert-warning d-inline-flex flex-column">
                        <div>${textUtils.escapeHtml(timeoutMsg)}</div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2">
                            <i class="icon fa fa-refresh" aria-hidden="true"></i> ${textUtils.escapeHtml(checkUpdatesText)}
                        </button>
                    </div>
                </div>`.trim();
            const timeoutNode = wrapper.firstChild;

            timeoutNode.querySelector('button').addEventListener('click', async() => {
                timeoutNode.remove();

                try {
                    const data = await this.api.loadConversation(
                        this.state.getCourseId(),
                        this.state.getLastRenderedId()
                    );

                    const rawMessages = Array.isArray(data.messages) ? data.messages : [];
                    this.pendingTempId = this.reconciler.reconcile(
                        this._messagesForDisplay(rawMessages),
                        this.pendingTempId
                    );
                    const currentMessages = this.ui.getRenderedMessages();
                    const lastMessage = currentMessages.length ? currentMessages[currentMessages.length - 1] : null;

                    if (lastMessage && lastMessage.role === 'user') {
                        this.state.setPending(true);
                        this.ui.disableInput();
                        this.ui.showPendingIndicator();
                        // Resume polling with a fresh timestamp.
                        this.state.savePollState({isPending: true, timestamp: Date.now()});
                        this._pollForReply();
                    }
                } catch (error) {
                    const errorMsg = await str.get_string('error_check_updates', 'block_dixeo_tutor');
                    this.ui.appendErrorMessage(errorMsg);
                }
            });

            this.ui.dom.messagesContainer.appendChild(timeoutNode);
            this.ui.scrollToBottom();
        }

        /**
         * Handles connection loss by showing UI and starting retry mechanism.
         * @private
         */
        _handleConnectionLoss() {
            if (!this.isConnectionLost) {
                this.isConnectionLost = true;
                this.ui.showConnectionLost();
            }

            this._startConnectionRetry();
        }

        /**
         * Starts connection retry with exponential backoff.
         * @private
         */
        _startConnectionRetry() {
            this._clearConnectionRetry();

            this.connectionRetryTimeoutId = setTimeout(async() => {
                if (!navigator.onLine) {
                    this.connectionRetryDelay = Math.min(
                        this.connectionRetryDelay * constants.polling.BACKOFF_FACTOR,
                        this.maxConnectionRetryDelay
                    );
                    this._startConnectionRetry();
                    return;
                }

                try {
                    // Use loadConversation as a connectivity probe.
                    await this.api.loadConversation(this.state.getCourseId());

                    this.isConnectionLost = false;
                    this.ui.hideConnectionLost();
                    this.connectionRetryDelay = 1000;

                    // Resume reply polling if needed.
                    if (this.state.isPending()) {
                        const pollState = this.state.getPollState();
                        if (pollState && pollState.jobId) {
                            this._pollForJobCompletion(pollState.jobId);
                        } else {
                            this._pollForReply();
                        }
                    }
                } catch (error) {
                    this.connectionRetryDelay = Math.min(
                        this.connectionRetryDelay * constants.polling.BACKOFF_FACTOR,
                        this.maxConnectionRetryDelay
                    );
                    this._startConnectionRetry();
                }
            }, this.connectionRetryDelay);
        }

        /**
         * Clears connection retry timeout.
         * @private
         */
        _clearConnectionRetry() {
            if (this.connectionRetryTimeoutId) {
                clearTimeout(this.connectionRetryTimeoutId);
                this.connectionRetryTimeoutId = null;
            }
        }

        /**
         * Cleanup method to prevent memory leaks.
         */
        destroy() {
            this._stopPolling();
            this._clearConnectionRetry();

            if (this._onOffline) {
                window.removeEventListener('offline', this._onOffline);
            }
            if (this._onOnline) {
                window.removeEventListener('online', this._onOnline);
            }
            if (this._onBeforeUnload) {
                window.removeEventListener('beforeunload', this._onBeforeUnload);
            }
            if (this._onStorageChange) {
                window.removeEventListener('storage', this._onStorageChange);
            }

            this.ui.removeAllListeners();

            this.state = null;
            this.ui = null;
            this.api = null;
            this.reconciler = null;
            this.pendingTempId = null;
            this.replyPollTimeoutId = null;
            this.connectionRetryTimeoutId = null;
            this._onOffline = null;
            this._onOnline = null;
            this._onBeforeUnload = null;
            this._onStorageChange = null;
        }
    };
});
