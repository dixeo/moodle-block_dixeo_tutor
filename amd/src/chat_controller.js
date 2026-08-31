define([
    'core/str',
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/errors',
    'block_dixeo_tutor/reconciliation_service',
    'block_dixeo_tutor/system_message_display',
    'block_dixeo_tutor/guide_session_context',
    'block_dixeo_tutor/text_utils',
], function(str, constants, errors, ReconciliationService, systemMessageDisplay, guideSessionContext, textUtils) {
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
            this.replyPollJobId = null;
            this.replyPollAttempt = 0;
            this.tempIdCounter = 0;
            this.connectionRetryTimeoutId = null;
            this.connectionRetryDelay = 1000;
            this.maxConnectionRetryDelay = 30000;
            this.isConnectionLost = false;
            this._loadingOlder = false;
            this._ensuringVisible = false;
            this._loadingAllOlder = false;
            this._initialHistoryReady = false;
            this._flushingPending = false;
            this._guideOutboundProvider = null;
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
         * Load the conversation, then flush proactive context and restore session state.
         * @private
         */
        async _initSequence() {
            try {
                await this._checkInitialState();
                this._flushPendingContext().catch(() => {
                    // A failed proactive flush must not leave the tutor unusable.
                });
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
         * Provide active guide session fields for outbound user messages.
         *
         * @param {function(): object|null} fn
         */
        setGuideOutboundProvider(fn) {
            this._guideOutboundProvider = typeof fn === 'function' ? fn : null;
        }

        /**
         * Emit guide assistant metadata parsed from the latest assistant delta.
         *
         * @param {Array<object>} deltaMessages
         * @param {object|null} pollState
         * @private
         */
        _dispatchGuideAssistantContext(deltaMessages, pollState) {
            if (!Array.isArray(deltaMessages) || !deltaMessages.length) {
                return;
            }
            const lastAssistant = [...deltaMessages].reverse().find((msg) => {
                return String(msg.role || '').toLowerCase() === 'assistant';
            });
            if (!lastAssistant) {
                return;
            }
            const parsed = guideSessionContext.parseAssistantContext(lastAssistant);
            if (!parsed) {
                return;
            }
            window.dispatchEvent(new CustomEvent(constants.events.GUIDE_ASSISTANT_CONTEXT, {
                detail: Object.assign({}, parsed, {
                    fromGuideStart: !!(pollState && pollState.fromGuideStart),
                    messageTime: lastAssistant.time || 0,
                }),
            }));
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
         * Course module id from the tutor root when on an activity page.
         * @returns {number}
         * @private
         */
        _getCurrentCmid() {
            const root = this.ui?.dom?.container || document.getElementById('dixeo-tutor');
            if (!root) {
                return 0;
            }
            return parseInt(root.dataset.currentCmid, 10) || 0;
        }

        /**
         * Submit queued proactive context (welcome, Guide me start, etc.).
         *
         * @param {string} [mode] Current tutor mode after a preference save, if known.
         */
        flushPendingAfterModePersist(mode) {
            if (mode !== 'guide') {
                return;
            }
            this._flushPendingContext();
        }

        /**
         * Submit queued proactive context lines when the tutor UI loads.
         * @private
         */
        async _flushPendingContext() {
            if (this._flushingPending) {
                return;
            }
            if (this.modeController?.isPersisting()) {
                return;
            }
            if (this.state.isPending()) {
                return;
            }

            this._flushingPending = true;
            try {
                const response = await this.api.flushPendingContext(
                    this.state.getCourseId(),
                    window.location.href,
                    this._getCurrentCmid()
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
            } finally {
                this._flushingPending = false;
            }
        }

        /**
         * Drain leftover queued events after a reply job finishes.
         * @private
         */
        _drainPendingContext() {
            this._flushPendingContext();
        }

        /**
         * Binds listeners to events from the UI.
         * @private
         */
        _bindEvents() {
            this.ui.on(constants.events.SEND_MESSAGE, (message) => this.handleSendMessage(message));
            this.ui.on(constants.events.RETRY_SEND_MESSAGE, (message) => this.handleSendMessage(message));
            this.ui.on(constants.events.LOAD_OLDER_MESSAGES, () => this._handleLoadOlderMessages());
            this.ui.on(constants.events.ENSURE_VISIBLE_TRANSCRIPT, () => {
                this._ensureVisibleStandardTranscript();
            });
            this.ui.on(constants.events.DELETE_CONVERSATION, () => this.handleDeleteConversation());
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
                if (!this.state.isPending() || !this.replyPollTimeoutId) {
                    return;
                }
                const existingState = this.state.getPollState();
                this.state.savePollState({
                    isPending: true,
                    jobId: existingState?.jobId || null,
                    timestamp: existingState?.timestamp || Date.now()
                });
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
            this.ui.noteInitialHistoryPainted();

            if (messages.length) {
                this.state.setLastRenderedId(messages[messages.length - 1].id);
            }

            this.ui.syncLoadOlderControl(this.state.getHasMoreOlder());

            this._initialHistoryReady = true;
            this._emitConversationSynced(conversationData);

            await this._ensureVisibleStandardTranscript();

            const last = messages.length ? messages[messages.length - 1] : null;
            const waitingForReply = !!(last && String(last.role).toLowerCase() === 'user');
            if (!this.state.isPending()) {
                if (!waitingForReply) {
                    this.state.clearPollState();
                }
                this.ui.enableInput();
            }
        }

        /**
         * In standard view, keep loading older pages while the viewport is empty
         * (e.g. the newest page is only guide-lane turns).
         *
         * @private
         */
        async _ensureVisibleStandardTranscript() {
            if (this._ensuringVisible) {
                return;
            }
            if (typeof this.ui.getMessageView === 'function'
                    && this.ui.getMessageView() !== 'standard') {
                return;
            }
            if (typeof this.ui.hasVisibleTranscriptContent === 'function'
                    && this.ui.hasVisibleTranscriptContent()) {
                return;
            }
            if (!this.state.getHasMoreOlder()) {
                return;
            }

            this._ensuringVisible = true;
            try {
                let pages = 0;
                const maxPages = 50;
                while (pages < maxPages
                        && this.ui.getMessageView() === 'standard'
                        && !this.ui.hasVisibleTranscriptContent()
                        && this.state.getHasMoreOlder()) {
                    pages += 1;
                    const beforeOldest = this.state.getOldestLoadedId();
                    await this._handleLoadOlderMessages();
                    // Stop if pagination did not advance (avoid infinite loops).
                    if (this.state.getOldestLoadedId() === beforeOldest) {
                        break;
                    }
                }
            } finally {
                this._ensuringVisible = false;
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
         *
         * @param {{silent?: boolean}} [options]
         * @private
         */
        async _handleLoadOlderMessages(options) {
            const opts = options || {};
            const silent = !!opts.silent;
            if (this._loadingOlder || !this.state.getHasMoreOlder()) {
                return;
            }

            const oldestId = this.state.getOldestLoadedId();
            if (!oldestId) {
                return;
            }

            const nextOffset = this.state.getHistoryOffset() + constants.ui.MESSAGE_PAGE_SIZE;

            this._loadingOlder = true;
            if (!silent) {
                this.ui.setLoadOlderLoading(true);
            }

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
                // Keep paging while the API returns a full page, even if every row
                // was filtered from display (guide/system-only batches).
                this.state.setHasMoreOlder(rawBatch.length === constants.ui.MESSAGE_PAGE_SIZE);
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
                if (!silent) {
                    this.ui.setLoadOlderLoading(false);
                } else {
                    this.ui.syncLoadOlderControl(this.state.getHasMoreOlder());
                }
            }
        }

        /**
         * Whether the first conversation history fetch has finished painting.
         *
         * @returns {boolean}
         */
        isInitialHistoryReady() {
            return !!this._initialHistoryReady;
        }

        /**
         * Silently page through older history until nothing remains (used by guide review).
         *
         * @returns {Promise<void>}
         */
        async loadAllOlderMessages() {
            if (this._loadingAllOlder) {
                return;
            }
            this._loadingAllOlder = true;
            try {
                let pages = 0;
                const maxPages = 100;
                while (pages < maxPages && this.state.getHasMoreOlder()) {
                    pages += 1;
                    const beforeOldest = this.state.getOldestLoadedId();
                    await this._handleLoadOlderMessages({silent: true});
                    if (this.state.getOldestLoadedId() === beforeOldest) {
                        break;
                    }
                }
            } finally {
                this._loadingAllOlder = false;
                this.ui.syncLoadOlderControl(this.state.getHasMoreOlder());
            }
        }

        /**
         * Silently load older pages until predicate() is true or history is exhausted.
         *
         * @param {function(): boolean} predicate
         * @param {{maxPages?: number}} [options]
         * @returns {Promise<boolean>} True when predicate became true.
         */
        async loadOlderUntil(predicate, options) {
            const opts = options || {};
            const maxPages = typeof opts.maxPages === 'number' ? opts.maxPages : 100;
            if (typeof predicate !== 'function') {
                return false;
            }
            if (predicate()) {
                return true;
            }
            if (this._loadingAllOlder) {
                return predicate();
            }
            this._loadingAllOlder = true;
            try {
                let pages = 0;
                while (pages < maxPages && this.state.getHasMoreOlder() && !predicate()) {
                    pages += 1;
                    const beforeOldest = this.state.getOldestLoadedId();
                    await this._handleLoadOlderMessages({silent: true});
                    if (this.state.getOldestLoadedId() === beforeOldest) {
                        break;
                    }
                }
                return !!predicate();
            } finally {
                this._loadingAllOlder = false;
                this.ui.syncLoadOlderControl(this.state.getHasMoreOlder());
            }
        }

        /**
         * Erases the user's conversation, then rebuilds an empty transcript.
         *
         * The UI is only cleared once the server confirms the erasure: wiping the
         * transcript on a failed call would show data as gone while it still exists.
         */
        async handleDeleteConversation() {
            try {
                await this.api.deleteConversation(this.state.getCourseId());
            } catch (e) {
                const message = await str.get_string('deleteconversationfailed', 'block_dixeo_tutor');
                this.ui.appendErrorMessage(message);
                return;
            }

            this._stopPolling();
            this.pendingTempId = null;
            this.state.setPending(false);
            this.state.setLastRenderedId(null);
            this.state.clearAll();

            // Same path as a first load with no history: the transcript is wiped and
            // rebuilt with the welcome message, and the input is re-enabled.
            await this._handleInitialState({messages: []});

            // The erase button is disabled by the rebuild, so it cannot keep the focus
            // the confirmation modal hands back to it.
            this.ui.focusInput();
        }

        /**
         * Handles the user sending a message via async job flow.
         * @param {string} message The content entered by the user.
         */
        async handleSendMessage(message) {
            if (!message?.trim()) {
                return;
            }

            if (this.modeController && typeof this.modeController.isMessagingLocked === 'function'
                    && this.modeController.isMessagingLocked()) {
                return;
            }

            if (!navigator.onLine) {
                this._handleConnectionLoss();
                return;
            }

            // 1. Create an optimistic bubble with a negative temp ID.
            const tempId = --this.tempIdCounter;
            const timestamp = Math.floor(Date.now() / 1000);
            const guideSession = this._guideOutboundProvider ? this._guideOutboundProvider() : null;
            const optimistic = {id: tempId, role: 'user', content: message, time: timestamp};
            if (guideSession && guideSession.title && guideSession.description) {
                optimistic.context = {
                    schema: 'guide_session',
                    version: 1,
                    title: guideSession.title,
                    description: guideSession.description,
                };
            }
            this.ui.appendMessage(optimistic);

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
                    window.location.href,
                    this._getCurrentCmid(),
                    guideSession
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
                if (this.modeController && typeof this.modeController.noteActivity === 'function') {
                    this.modeController.noteActivity();
                }
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

            if (this.replyPollJobId !== jobId) {
                this.replyPollJobId = jobId;
                this.replyPollAttempt = 0;
            }
            const pollDelay = Math.min(
                constants.polling.REPLY_INTERVAL_MS,
                Math.round(
                    constants.polling.FIRST_REPLY_INTERVAL_MS
                    * Math.pow(constants.polling.BACKOFF_FACTOR, this.replyPollAttempt)
                )
            );
            this.replyPollAttempt++;

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

                        this._dispatchGuideAssistantContext(deltaMessages, pollState);

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
                        this._stopPolling();
                        this.state.clearDraft();
                        this.state.clearPollState();
                        this.state.setPending(false);
                        this.ui.hidePendingIndicator();
                        this.ui.enableInput();
                        this._drainPendingContext();

                    } else if (jobStatus.status === 'failed') {
                        const errorMsg = await str.get_string('errorsendmessage', 'block_dixeo_tutor');

                        if (this.pendingTempId) {
                            this.ui.removeMessage(this.pendingTempId);
                            this.pendingTempId = null;
                        }

                        this._stopPolling();
                        this.state.clearDraft();
                        this.state.clearPollState();
                        this.state.setPending(false);
                        this.ui.hidePendingIndicator();
                        this.ui.enableInput();
                        this.ui.appendErrorMessage(errorMsg);
                        this._drainPendingContext();

                    } else {
                        // Still processing — keep polling with a widening interval.
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
            }, pollDelay);
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
                            this._stopPolling();
                            this.state.clearPollState();
                            this.state.clearDraft();
                            this.state.setPending(false);
                            this.ui.hidePendingIndicator();
                            this.ui.enableInput();
                            return;
                        }
                    }

                    const rendered = this.ui.getRenderedMessages();
                    const lastRendered = rendered.length ? rendered[rendered.length - 1] : null;
                    if (!lastRendered || lastRendered.role !== 'user') {
                        this._stopPolling();
                        this.state.clearPollState();
                        this.state.setPending(false);
                        this.ui.hidePendingIndicator();
                        this.ui.enableInput();
                        return;
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
            this.replyPollJobId = null;
            this.replyPollAttempt = 0;
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
