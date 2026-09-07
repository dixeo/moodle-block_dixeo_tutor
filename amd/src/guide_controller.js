define([
    'core/str',
    'block_dixeo_tutor/guide_setup',
    'block_dixeo_tutor/guide_api',
    'block_dixeo_tutor/guide_topic_panel',
    'block_dixeo_tutor/tutor_session_storage',
    'block_dixeo_tutor/constants',
], function(str, guideSetup, guideApi, guideTopicPanel, sessionStorage, constants) {
    'use strict';

    const STORAGE_MODE = 'guide';

    /**
     * Guide me (Socratic) orchestration for the tutor drawer.
     *
     * @param {Object} options
     * @param {number} options.courseid
     * @param {number} options.userid
     * @param {Object} options.ui ChatUI
     * @param {Object} options.state ChatState
     * @param {Object} options.chatController ChatController
     * @param {Object|null} options.modeController TutorModeController
     */
    const GuideController = function(options) {
        this.courseid = options.courseid;
        this.userid = options.userid;
        this.ui = options.ui;
        this.state = options.state;
        this.chatController = options.chatController;
        this.modeController = options.modeController || null;

        const root = document.getElementById('dixeo-tutor');
        this.guidePane = document.getElementById('dixeo-tutor-guide-pane');
        this.body = document.getElementById('dixeo-tutor-body');
        this.banner = document.querySelector('#dixeo-tutor .dixeo-guide-banner');
        this.currentCmid = root ? (parseInt(root.dataset.currentCmid, 10) || 0) : 0;

        this.active = false;
        this.sessionActive = false;
        this._isUnderstood = false;
        this._openSetupToken = 0;
        this._sessionToken = 0;
        this._pendingUserPrompt = '';
        this._sessionTitle = '';
        this._sessionDescription = '';
        this._sessionStartedAt = 0;
        this._reviewing = false;
        this._reviewSession = null;
        this._pendingReviewResume = null;

        this._wireUiHooks();
        this._bindGuideAssistantContext();
        this._bindConversationSynced();
        this.tryResumeFromStorage();
        this.restoreEndedSummaryCard();
    };

    GuideController.prototype._wireUiHooks = function() {
        if (!this.ui) {
            return;
        }
        if (typeof this.ui.setGuideCompletionHandlers === 'function') {
            this.ui.setGuideCompletionHandlers({
                shouldShow: () => this._shouldShowCompletionButtons(),
                onExit: () => this.exitGuide(),
                onRestart: () => this.restartGuide(),
            });
        }
        if (typeof this.ui.setGuideSummarySessionsProvider === 'function') {
            this.ui.setGuideSummarySessionsProvider(() => this.getEndedSummarySessions());
        }
        if (this.chatController && typeof this.chatController.setGuideOutboundProvider === 'function') {
            this.chatController.setGuideOutboundProvider(() => this.getOutboundContext());
        }
        guideTopicPanel.setReviewHandler((session, triggerEl) => {
            const row = triggerEl && typeof triggerEl.closest === 'function'
                ? triggerEl.closest('.dixeo-tutor-message-row')
                : null;
            if (this.ui && typeof this.ui.setReturnToMessageRow === 'function') {
                this.ui.setReturnToMessageRow(row);
            }
            this._enterReviewSession(session);
        });
        guideTopicPanel.setRestartHandler((session) => this.openSetupFromSession(session));
        if (this.ui && typeof this.ui.applyMessageView === 'function') {
            this.ui.applyMessageView();
        }
    };

    GuideController.prototype._shouldShowCompletionButtons = function() {
        return this.sessionActive
            && this._isUnderstood
            && this.modeController
            && this.modeController.currentMode === 'guide';
    };

    GuideController.prototype.getOutboundContext = function() {
        if (!this.sessionActive) {
            return null;
        }
        const saved = sessionStorage.load(STORAGE_MODE, this.userid, this.courseid);
        if (!saved || saved.phase !== 'active' || saved.timeEnded) {
            return null;
        }
        if (!saved.title || !saved.description) {
            return null;
        }
        return {
            title: saved.title,
            description: saved.description,
        };
    };

    /**
     * Ended guide sessions that should keep a review card in standard mode.
     *
     * @returns {Array<{title: string, description: string, startedAt: number, timeEnded: number}>}
     */
    GuideController.prototype.getEndedSummarySessions = function() {
        const saved = sessionStorage.load(STORAGE_MODE, this.userid, this.courseid);
        if (!saved) {
            return [];
        }
        const sessions = Array.isArray(saved.endedSessions) ? saved.endedSessions.slice() : [];
        if ((saved.phase === 'ended' || saved.phase === 'reviewing')
                && saved.title && saved.description) {
            const key = String(saved.title) + '\n' + String(saved.description || '');
            const exists = sessions.some(function(item) {
                return String(item.title) + '\n' + String(item.description || '') === key;
            });
            if (!exists) {
                sessions.push({
                    title: saved.title,
                    description: saved.description,
                    startedAt: saved.startedAt || 0,
                    timeEnded: saved.timeEnded || 0,
                });
            }
        }
        return sessions.filter(function(item) {
            return item && item.title && item.description;
        });
    };

    GuideController.prototype._setModeSelectorLocked = function(locked) {
        if (this.modeController && typeof this.modeController.setSelectorLocked === 'function') {
            this.modeController.setSelectorLocked(locked);
        }
    };

    GuideController.prototype.persistState = function(data) {
        sessionStorage.save(STORAGE_MODE, this.userid, this.courseid, data);
    };

    GuideController.prototype.clearStorage = function() {
        sessionStorage.clear(STORAGE_MODE, this.userid, this.courseid);
    };

    GuideController.prototype.hasPersistedSession = function() {
        return sessionStorage.hasActiveSession(STORAGE_MODE, this.userid, this.courseid);
    };

    GuideController.prototype.showGuidePane = function() {
        if (!this.guidePane) {
            return;
        }
        if (this.ui && typeof this.ui.preserveMessagesScroll === 'function') {
            this.ui.preserveMessagesScroll();
        }
        this.active = true;
        this.guidePane.classList.remove('d-none');
        this.guidePane.setAttribute('aria-hidden', 'false');
        if (this.body) {
            this.body.classList.add('dixeo-tutor-body--guide-active');
        }
        if (this.ui && typeof this.ui.setMessageView === 'function') {
            this.ui.setMessageView('guide', {
                startedAt: Math.floor(Date.now() / 1000),
            });
        }
        this._syncModeMessagingLock();
    };

    GuideController.prototype.closeGuidePane = function() {
        this.active = false;
        if (this.guidePane) {
            this.guidePane.innerHTML = '';
            this.guidePane.classList.add('d-none');
            this.guidePane.setAttribute('aria-hidden', 'true');
        }
        if (this.body) {
            this.body.classList.remove('dixeo-tutor-body--guide-active');
        }
        if (this._reviewing) {
            this._clearReviewChrome(false);
        }
        // Drop draft prompt when leaving setup so a later Guide me open starts blank.
        if (!this.sessionActive) {
            this._pendingUserPrompt = '';
        }
        const root = document.getElementById('dixeo-tutor');
        if (root && !this.sessionActive && !this._reviewing) {
            root.removeAttribute('data-guide-session');
        }
        // Leaving setup/review must restore the standard transcript (guide view hides all rows).
        if (!this.sessionActive
                && this.ui
                && typeof this.ui.setMessageView === 'function') {
            this.ui.setMessageView('standard');
        }
        if (this.ui && typeof this.ui.consumeInitialScrollPending === 'function') {
            this.ui.consumeInitialScrollPending(this.chatController);
        }
        this._syncModeMessagingLock();
    };

    GuideController.prototype._syncModeMessagingLock = function() {
        if (this.modeController && typeof this.modeController._syncMessagingLock === 'function') {
            this.modeController._syncMessagingLock();
        }
    };

    GuideController.prototype.updateBanner = function(topic) {
        if (!this.banner) {
            return;
        }
        const titleEl = this.banner.querySelector('[data-role="guide-topic-title"]');
        const descEl = this.banner.querySelector('[data-role="guide-topic-description"]');
        if (titleEl) {
            titleEl.textContent = topic?.title || '';
            titleEl.classList.toggle('d-none', !topic?.title);
        }
        if (descEl) {
            descEl.textContent = topic?.description || '';
            descEl.classList.toggle('d-none', !topic?.description);
        }
    };

    GuideController.prototype.clearBannerTopic = function() {
        this.updateBanner({title: '', description: ''});
    };

    GuideController.prototype._activateSession = function(topic, options) {
        const opts = options || {};
        this.sessionActive = true;
        this._reviewing = false;
        this._sessionTitle = topic.title || '';
        this._sessionDescription = topic.description || '';
        this._isUnderstood = false;
        this._pendingUserPrompt = '';
        this._sessionStartedAt = opts.startedAt || Math.floor(Date.now() / 1000);
        this._setModeSelectorLocked(false);
        this.closeGuidePane();
        this.updateBanner(topic);
        this.persistState({
            phase: 'active',
            title: topic.title,
            description: topic.description,
            startedAt: this._sessionStartedAt,
            timeEnded: null,
            isUnderstood: false,
            endedSessions: this.getEndedSummarySessions(),
        });
        const root = document.getElementById('dixeo-tutor');
        if (root) {
            root.setAttribute('data-guide-session', 'active');
        }
        if (this.ui && typeof this.ui.setMessageView === 'function') {
            this.ui.setMessageView('guide', {
                title: this._sessionTitle,
                description: this._sessionDescription,
                startedAt: this._sessionStartedAt,
            });
        }
        if (this.ui && typeof this.ui.syncGuideCompletionButtons === 'function') {
            this.ui.syncGuideCompletionButtons();
        }
    };

    GuideController.prototype._insertTopicCard = function(topic) {
        if (!this.ui || !this.ui.dom || !this.ui.dom.messagesContainer || !topic) {
            return;
        }
        guideTopicPanel.insertCard(this.ui.dom.messagesContainer, topic);
        if (typeof this.ui.applyMessageView === 'function') {
            this.ui.applyMessageView();
        }
        if (typeof this.ui.scrollToBottom === 'function') {
            this.ui.scrollToBottom();
        }
    };

    GuideController.prototype.onGuideModeEntered = function() {
        if (!this.sessionActive || !this._sessionTitle) {
            return;
        }
        if (this.ui && typeof this.ui.setMessageView === 'function') {
            this.ui.setMessageView('guide', {
                title: this._sessionTitle,
                description: this._sessionDescription,
                startedAt: this._sessionStartedAt || undefined,
            });
        }
    };

    GuideController.prototype.isReviewing = function() {
        return !!this._reviewing;
    };

    /**
     * @param {boolean} [resetMode] When true, return the mode selector to normal.
     * @private
     */
    GuideController.prototype._clearReviewChrome = function(resetMode) {
        const reviewSession = this._reviewSession;
        this._reviewing = false;
        this._reviewSession = null;
        this._setModeSelectorLocked(false);
        this.clearBannerTopic();
        const root = document.getElementById('dixeo-tutor');
        if (root && !this.sessionActive) {
            root.removeAttribute('data-guide-session');
        }
        if (reviewSession && reviewSession.title && reviewSession.description) {
            const endedSessions = this.getEndedSummarySessions().filter(function(item) {
                return !(String(item.title) === String(reviewSession.title)
                    && String(item.description || '') === String(reviewSession.description || ''));
            });
            endedSessions.push({
                title: reviewSession.title,
                description: reviewSession.description,
                startedAt: reviewSession.startedAt || 0,
                timeEnded: reviewSession.timeEnded || 0,
            });
            this.persistState({
                phase: 'ended',
                title: reviewSession.title,
                description: reviewSession.description,
                startedAt: reviewSession.startedAt || 0,
                timeEnded: reviewSession.timeEnded || 0,
                endedSessions: endedSessions,
            });
        }
        if (this.ui && typeof this.ui.setMessageView === 'function') {
            this.ui.setMessageView('standard');
        }
        this._syncModeMessagingLock();
        if (resetMode && this.modeController && typeof this.modeController.resetToNormal === 'function') {
            this.modeController.resetToNormal();
        }
    };

    GuideController.prototype._setReviewingChrome = function(session) {
        this._reviewing = true;
        this._reviewSession = {
            title: session.title || '',
            description: session.description || '',
            startedAt: session.startedAt || 0,
            timeEnded: session.timeEnded || 0,
        };
        this._setModeSelectorLocked(true);
        this.updateBanner({
            title: session.title,
            description: session.description,
        });
        const root = document.getElementById('dixeo-tutor');
        if (root) {
            root.setAttribute('data-guide-session', 'review');
        }
        if (this.modeController && typeof this.modeController.setMode === 'function') {
            // Same idea as reviewing a custom lesson: switch chrome to guide purple.
            this.modeController.setMode('guide', {skipRouting: true});
        }
        if (this.ui && typeof this.ui.setMessageView === 'function') {
            this.ui.setMessageView('review', {
                title: session.title,
                description: session.description,
                startedAt: session.startedAt || 0,
                timeEnded: session.timeEnded || 0,
            });
        }
        this._syncModeMessagingLock();
    };

    GuideController.prototype._persistReviewing = function(session) {
        if (!session || !session.title || !session.description) {
            return;
        }
        const endedSessions = this.getEndedSummarySessions().filter(function(item) {
            return !(String(item.title) === String(session.title)
                && String(item.description || '') === String(session.description || ''));
        });
        endedSessions.push({
            title: session.title,
            description: session.description,
            startedAt: session.startedAt || 0,
            timeEnded: session.timeEnded || 0,
        });
        const payload = {
            phase: 'reviewing',
            title: session.title,
            description: session.description,
            startedAt: session.startedAt || 0,
            timeEnded: session.timeEnded || 0,
            endedSessions: endedSessions,
            returnCard: {
                type: 'guide',
                title: session.title,
                description: session.description || '',
            },
        };
        if (this.ui && typeof this.ui.getReturnToCard === 'function') {
            const existing = this.ui.getReturnToCard();
            if (existing && existing.type === 'guide') {
                payload.returnCard = existing;
            }
        }
        this.persistState(payload);
    };

    GuideController.prototype.exitReview = async function() {
        if (!this._reviewing) {
            return;
        }
        if ((!this.ui || !this.ui.getReturnToCard || !this.ui.getReturnToCard())
                && this._reviewSession
                && this.ui
                && typeof this.ui.setReturnToMessageRow === 'function') {
            this.ui.setReturnToMessageRow({
                type: 'guide',
                title: this._reviewSession.title,
                description: this._reviewSession.description || '',
            });
        }
        this._clearReviewChrome(true);
        this.restoreEndedSummaryCard();
        if (this.ui && typeof this.ui.revealAndScrollReturnCard === 'function') {
            await this.ui.revealAndScrollReturnCard(this.chatController);
        }
    };

    /**
     * Finish a deferred review resume once conversation history is available.
     *
     * @private
     */
    GuideController.prototype._completePendingReviewResume = async function() {
        const session = this._pendingReviewResume;
        if (!session) {
            return;
        }
        this._pendingReviewResume = null;
        if (!this._reviewing) {
            this._setReviewingChrome(session);
        }
        if (this.chatController && typeof this.chatController.loadAllOlderMessages === 'function') {
            await this.chatController.loadAllOlderMessages();
        }
        if (this.ui && typeof this.ui.applyMessageView === 'function') {
            this.ui.applyMessageView();
        }
        if (this.ui && typeof this.ui.syncLoadOlderControl === 'function') {
            this.ui.syncLoadOlderControl(false);
        }
        if (this.ui && typeof this.ui.scrollToBottom === 'function') {
            this.ui.scrollToBottom();
        }
    };

    GuideController.prototype._enterReviewSession = async function(session, options) {
        const opts = options || {};
        if (!this.ui || typeof this.ui.setMessageView !== 'function' || !session) {
            return;
        }
        if (this._reviewing && !opts.fromResume) {
            return;
        }
        if (this.sessionActive) {
            return;
        }
        this._setReviewingChrome(session);
        if (!opts.fromResume) {
            this._persistReviewing(session);
        }
        if (opts.fromResume
                && this.chatController
                && typeof this.chatController.isInitialHistoryReady === 'function'
                && !this.chatController.isInitialHistoryReady()) {
            this._pendingReviewResume = {
                title: session.title,
                description: session.description,
                startedAt: session.startedAt || 0,
                timeEnded: session.timeEnded || 0,
            };
            return;
        }
        if (this.chatController && typeof this.chatController.loadAllOlderMessages === 'function') {
            await this.chatController.loadAllOlderMessages();
        }
        if (typeof this.ui.applyMessageView === 'function') {
            this.ui.applyMessageView();
        }
        if (typeof this.ui.syncLoadOlderControl === 'function') {
            this.ui.syncLoadOlderControl(false);
        }
        if (typeof this.ui.scrollToBottom === 'function') {
            this.ui.scrollToBottom();
        }
    };

    GuideController.prototype._endSession = function(options) {
        const opts = options || {};
        const title = this._sessionTitle || '';
        const description = this._sessionDescription || '';
        const startedAt = this._sessionStartedAt || Math.floor(Date.now() / 1000);
        const endedAt = Math.floor(Date.now() / 1000);

        if (title && description) {
            this._insertTopicCard({
                title: title,
                description: description,
                startedAt: startedAt,
                timeEnded: endedAt,
            });
        }

        this.sessionActive = false;
        this._reviewing = false;
        this._isUnderstood = false;
        this._sessionToken++;
        this._pendingUserPrompt = '';
        this._sessionTitle = '';
        this._sessionDescription = '';
        this._sessionStartedAt = 0;

        if (title && description) {
            const endedSessions = this.getEndedSummarySessions().filter(function(item) {
                return !(String(item.title) === String(title)
                    && String(item.description || '') === String(description));
            });
            endedSessions.push({
                title: title,
                description: description,
                startedAt: startedAt,
                timeEnded: endedAt,
            });
            this.persistState({
                phase: 'ended',
                title: title,
                description: description,
                startedAt: startedAt,
                timeEnded: endedAt,
                endedSessions: endedSessions,
            });
        } else {
            this.clearStorage();
        }

        this.clearBannerTopic();
        const root = document.getElementById('dixeo-tutor');
        if (root) {
            root.removeAttribute('data-guide-session');
        }
        if (this.ui && typeof this.ui.setMessageView === 'function') {
            this.ui.setMessageView('standard');
        }
        if (this.ui && typeof this.ui.syncGuideCompletionButtons === 'function') {
            this.ui.syncGuideCompletionButtons();
        }

        if (opts.reopenSetup) {
            this.openSetup();
            return;
        }
        if (!opts.skipModeReset && this.modeController) {
            this.modeController.resetToNormal();
        }
    };

    GuideController.prototype.exitGuide = function() {
        this._openSetupToken++;
        this._endSession({reopenSetup: false});
    };

    GuideController.prototype.restartGuide = function() {
        this._openSetupToken++;
        this._endSession({reopenSetup: true, skipModeReset: true});
    };

    GuideController.prototype.endSessionFromModeExpiry = function() {
        if (!this.sessionActive) {
            return;
        }
        this._openSetupToken++;
        this._endSession({reopenSetup: false, skipModeReset: true});
    };

    GuideController.prototype._cancelSetup = function() {
        this._openSetupToken++;
        this.closeGuidePane(false);
        if (this.ui && typeof this.ui.setMessageView === 'function') {
            this.ui.setMessageView('standard');
        }
        if (this.modeController) {
            this.modeController.resetToNormal();
        }
    };

    GuideController.prototype.openSetup = async function(initialValues) {
        if (!this.guidePane) {
            return;
        }
        const setupToken = ++this._openSetupToken;
        // Only keep a prompt when explicitly provided (e.g. restart from a session card).
        const prompt = (initialValues && typeof initialValues.userprompt === 'string')
            ? initialValues.userprompt
            : '';
        this._pendingUserPrompt = prompt;
        this.showGuidePane();

        await guideSetup.openSetup(
            this.guidePane,
            () => this._cancelSetup(),
            (config) => this._handleSetupSubmit(config, setupToken),
            {userprompt: prompt}
        );
    };

    /**
     * Open guide setup from a past session card, prefilling title + description.
     *
     * @param {{title?: string, description?: string}} session
     */
    GuideController.prototype.openSetupFromSession = function(session) {
        if (!session) {
            return;
        }
        if (this._reviewing && typeof this.exitReview === 'function') {
            this.exitReview();
        }
        const title = String(session.title || '').trim();
        const description = String(session.description || '').trim();
        let prompt = '';
        if (title && description && title !== description) {
            prompt = title + '\n\n' + description;
        } else {
            prompt = title || description;
        }
        this._pendingUserPrompt = prompt;
        if (this.modeController && typeof this.modeController.setMode === 'function') {
            this.modeController.setMode('guide', {skipRouting: true});
        }
        this.openSetup({userprompt: prompt});
    };

    GuideController.prototype._handleSetupSubmit = async function(config, setupToken) {
        if (setupToken !== this._openSetupToken) {
            return;
        }

        this._pendingUserPrompt = config.userprompt;
        this._setModeSelectorLocked(true);

        const loadingWrap = document.createElement('div');
        loadingWrap.className = 'dixeo-tutor-generating dixeo-guide-generating';
        loadingWrap.innerHTML =
            '<div class="dixeo-tutor-generating__icon" aria-hidden="true">' +
            '<i class="fa fa-spinner fa-spin"></i></div>' +
            '<p class="dixeo-tutor-generating__text"></p>';
        this.guidePane.innerHTML = '';
        this.guidePane.appendChild(loadingWrap);

        const loadingText = await str.get_string('guide_setup_starting', 'block_dixeo_tutor');
        loadingWrap.querySelector('.dixeo-tutor-generating__text').textContent = loadingText;

        try {
            const response = await guideApi.startGuideSession(
                this.courseid,
                config.userprompt,
                window.location.href,
                this.currentCmid
            );

            if (setupToken !== this._openSetupToken) {
                return;
            }

            if (!response.flushed || !response.jobid) {
                throw new Error('Guide session did not start');
            }

            this.state.setPending(true);
            this.ui.disableInput();
            this.ui.showPendingIndicator();
            this.state.savePollState({
                isPending: true,
                jobId: response.jobid,
                timestamp: Date.now(),
                fromProactiveFlush: true,
                fromGuideStart: true,
            });
            this.chatController._pollForJobCompletion(response.jobid);
        } catch (e) {
            if (setupToken !== this._openSetupToken) {
                return;
            }
            this.ui.appendErrorMessage(await str.get_string('guide_setup_error', 'block_dixeo_tutor'));
            await this.openSetup({userprompt: this._pendingUserPrompt || ''});
        }
    };

    GuideController.prototype._bindGuideAssistantContext = function() {
        window.addEventListener(constants.events.GUIDE_ASSISTANT_CONTEXT, (e) => {
            const detail = e.detail || {};
            this._onAssistantContext(detail);
        });
    };

    GuideController.prototype._bindConversationSynced = function() {
        window.addEventListener(constants.events.CONVERSATION_SYNCED, () => {
            if (this._pendingReviewResume) {
                this._completePendingReviewResume();
                return;
            }
            this.restoreEndedSummaryCard();
            if (this.ui && typeof this.ui.applyMessageView === 'function') {
                this.ui.applyMessageView();
            }
        });
    };

    /**
     * Re-insert the client-only review card after reload when the last session ended.
     */
    GuideController.prototype.restoreEndedSummaryCard = function() {
        if (this.sessionActive || this._reviewing) {
            return;
        }
        const saved = sessionStorage.load(STORAGE_MODE, this.userid, this.courseid);
        if (!saved || saved.phase !== 'ended' || !saved.title || !saved.description) {
            return;
        }
        const container = this.ui && this.ui.dom ? this.ui.dom.messagesContainer : null;
        if (!container) {
            return;
        }
        const existing = Array.from(
            container.querySelectorAll('.dixeo-tutor-message-row[data-lane="guide-summary"]')
        ).find((row) => {
            return String(row.dataset.guideTitle || '') === String(saved.title)
                && String(row.dataset.guideDescription || '') === String(saved.description || '');
        });
        if (existing) {
            return;
        }
        this._insertTopicCard({
            title: saved.title,
            description: saved.description,
            startedAt: saved.startedAt || 0,
            timeEnded: saved.timeEnded || 0,
        });
    };

    GuideController.prototype._onAssistantContext = function(detail) {
        if (!detail || !detail.title || !detail.description) {
            return;
        }

        const topic = {
            title: detail.title,
            description: detail.description,
        };

        this._isUnderstood = !!detail.isUnderstood;

        if (detail.fromGuideStart || !this.sessionActive) {
            const msgTime = parseInt(detail.messageTime, 10) || 0;
            this._activateSession(topic, {
                startedAt: msgTime || Math.floor(Date.now() / 1000),
            });
            const saved = sessionStorage.load(STORAGE_MODE, this.userid, this.courseid) || {};
            this.persistState(Object.assign({}, saved, {isUnderstood: this._isUnderstood}));
        } else {
            this._sessionTitle = topic.title;
            this._sessionDescription = topic.description;
            this.updateBanner(topic);
            const saved = sessionStorage.load(STORAGE_MODE, this.userid, this.courseid) || {};
            this.persistState(Object.assign({}, saved, {
                phase: 'active',
                title: topic.title,
                description: topic.description,
                timeEnded: null,
                isUnderstood: this._isUnderstood,
            }));
        }

        if (this.ui && typeof this.ui.syncGuideCompletionButtons === 'function') {
            this.ui.syncGuideCompletionButtons();
        }
    };

    /**
     * Resume a persisted guide review session.
     *
     * @param {Object} saved
     * @private
     */
    GuideController.prototype._resumeReviewingFromStorage = function(saved) {
        const session = {
            title: saved.title,
            description: saved.description,
            startedAt: saved.startedAt || 0,
            timeEnded: saved.timeEnded || 0,
        };
        if (this.ui && typeof this.ui.setReturnToMessageRow === 'function') {
            this.ui.setReturnToMessageRow(saved.returnCard || {
                type: 'guide',
                title: session.title,
                description: session.description || '',
            });
        }
        // Restore purple review chrome immediately (like teach viewing), then
        // load the full transcript once history is ready.
        this._setReviewingChrome(session);
        this._pendingReviewResume = session;
        if (this.chatController
                && typeof this.chatController.isInitialHistoryReady === 'function'
                && this.chatController.isInitialHistoryReady()) {
            this._completePendingReviewResume();
        }
    };

    /**
     * Resume a persisted active guide session.
     *
     * @param {Object} saved
     * @private
     */
    GuideController.prototype._resumeActiveFromStorage = function(saved) {
        this.sessionActive = true;
        this._reviewing = false;
        this._sessionTitle = saved.title || '';
        this._sessionDescription = saved.description || '';
        this._sessionStartedAt = saved.startedAt || 0;
        this.updateBanner({
            title: saved.title || '',
            description: saved.description || '',
        });
        this._pendingUserPrompt = '';
        this._isUnderstood = !!saved.isUnderstood;
        const root = document.getElementById('dixeo-tutor');
        if (root) {
            root.setAttribute('data-guide-session', 'active');
        }
        if (this.ui && typeof this.ui.setMessageView === 'function') {
            this.ui.setMessageView('guide', {
                title: this._sessionTitle,
                description: this._sessionDescription,
                startedAt: this._sessionStartedAt || undefined,
            });
        }
        if (this.ui && typeof this.ui.syncGuideCompletionButtons === 'function') {
            this.ui.syncGuideCompletionButtons();
        }
    };

    GuideController.prototype.tryResumeFromStorage = function() {
        const saved = sessionStorage.load(STORAGE_MODE, this.userid, this.courseid);
        if (!saved || !saved.phase) {
            return;
        }

        if (saved.phase === 'reviewing' && saved.title && saved.description) {
            this._resumeReviewingFromStorage(saved);
            return;
        }

        if (saved.phase !== 'active' || saved.timeEnded) {
            return;
        }

        if (this.modeController && this.modeController.currentMode !== 'guide') {
            return;
        }

        this._resumeActiveFromStorage(saved);
    };

    GuideController.prototype.destroy = function() {
        // No background pollers to tear down.
    };

    return GuideController;
});
