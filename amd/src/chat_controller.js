define([
    'core/str',
    'core/templates',
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/errors',
    'block_dixeo_tutor/reconciliation_service',
], function(str, Templates, constants, errors, ReconciliationService) {
    'use strict';

    return class ChatController {
        /**
         * Constructs the controller.
         * @param {ChatState} state The application state manager.
         * @param {ChatUI} ui The UI manager.
         * @param {ChatAPI} api The API service.
         */
        constructor(state, ui, api) {
            this.state = state;
            this.ui = ui;
            this.api = api;
            this.reconciler = new ReconciliationService(ui, state);
            this.pendingTempId = null;
            this.replyPollTimeoutId = null;
            this.tempIdCounter = 0;
            this.connectionRetryTimeoutId = null;
            this.connectionRetryDelay = 1000;
            this.maxConnectionRetryDelay = 30000;
            this.isConnectionLost = false;
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

            this._checkInitialState();
        }

        /**
         * Binds listeners to events from the UI.
         * @private
         */
        _bindEvents() {
            this.ui.on(constants.events.SEND_MESSAGE, (message) => this.handleSendMessage(message));
            this.ui.on(constants.events.RETRY_SEND_MESSAGE, (message) => this.handleSendMessage(message));
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
            this._stopPolling();
            this.state.setPending(false);
            this.state.clearDraft();

            try {
                const data = await this.api.loadConversation(this.state.getCourseId());
                await this._handleInitialState(data);
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
                this._restoreSession();
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
            const messages = Array.isArray(conversationData.messages) ? conversationData.messages : [];

            await this._ensureWelcomeMessage(messages);
            await this.ui.renderMessageHistory(messages);

            if (messages.length) {
                this.state.setLastRenderedId(messages[messages.length - 1].id);
            }

            // Enable input by default after loading.
            this.ui.enableInput();
        }

        /**
         * Handles the user sending a message via async job flow.
         * @param {string} message The content entered by the user.
         */
        async handleSendMessage(message) {
            if (!message?.trim()) {
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
                        const delta = await this.api.loadConversation(
                            this.state.getCourseId(),
                            this.state.getLastRenderedId()
                        );

                        this.pendingTempId = this.reconciler.reconcile(delta.messages, this.pendingTempId);

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

                    this.pendingTempId = this.reconciler.reconcile(data.messages, this.pendingTempId);

                    if (data.messages && data.messages.length > 0) {
                        const lastMessage = data.messages[data.messages.length - 1];
                        if (lastMessage.role === 'assistant') {
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
         * Ensures a welcome message is always the first message.
         * @param {Array<object>} messages The array of messages (modified in place).
         * @private
         */
        async _ensureWelcomeMessage(messages) {
            if (messages.length > 0 && messages[0].id === constants.messages.WELCOME_MESSAGE_ID) {
                return;
            }

            if (messages.some(m => m.id === constants.messages.WELCOME_MESSAGE_ID)) {
                return;
            }

            const welcome = await str.get_string('tutorpresentation', 'block_dixeo_tutor');

            const firstTimestamp = messages.length > 0
                ? messages[0].time
                : Math.floor(Date.now() / 1000);

            messages.unshift({
                id: constants.messages.WELCOME_MESSAGE_ID,
                role: 'assistant',
                content: welcome,
                time: firstTimestamp
            });
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

            // Resume polling if it was active before page reload.
            if (savedPollState && savedPollState.isPending) {
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
            const [timeoutMsg, checkUpdatesText] = await str.get_strings([
                {key: 'timeout_message', component: 'block_dixeo_tutor'},
                {key: 'check_for_updates', component: 'block_dixeo_tutor'},
            ]);

            const {html, js} = await Templates.renderForPromise('block_dixeo_tutor/timeout_message', {
                message: timeoutMsg,
                checklabel: checkUpdatesText,
            });

            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            const timeoutNode = wrapper.firstChild;

            timeoutNode.querySelector('.dixeo-tutor-timeout-retry').addEventListener('click', async() => {
                timeoutNode.remove();

                try {
                    const data = await this.api.loadConversation(
                        this.state.getCourseId(),
                        this.state.getLastRenderedId()
                    );

                    this.pendingTempId = this.reconciler.reconcile(data.messages, this.pendingTempId);

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

            if (js) {
                Templates.runTemplateJS(js);
            }

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
