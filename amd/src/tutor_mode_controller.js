define([
    'core/ajax',
], function(ajax) {
    'use strict';

    const MODES = {
        NORMAL: 'normal',
        GUIDE: 'guide',
        QUIZ: 'quiz',
        TEACH: 'teach',
    };

    /**
     * Tutor mode selector and preference orchestration.
     *
     * @param {Object} options
     * @param {number} options.courseid
     * @param {boolean} options.quizAvailable
     * @param {boolean} options.teachAvailable
     * @param {boolean} options.guideAvailable
     * @param {Object|null} options.quizController PracticeQuizController instance when ready.
     * @param {Object|null} options.teachController TeachController instance when ready.
     * @param {Object|null} options.guideController GuideController instance when ready.
     */
    const TutorModeController = function(options) {
        this.courseid = options.courseid;
        this.quizAvailable = !!options.quizAvailable;
        this.teachAvailable = !!options.teachAvailable;
        this.guideAvailable = !!options.guideAvailable;
        this.quizController = options.quizController || null;
        this.teachController = options.teachController || null;
        this.guideController = options.guideController || null;
        this._persistCount = 0;
        this._externalSelectorLocked = false;
        this._messagingLockHandler = null;
        this._afterPersistHandler = null;
        this._expiryTimer = null;
        this._lastActivity = parseInt(options.lastActivity, 10) || 0;

        this.root = document.querySelector('.tutor-mode-selector');
        this.select = document.getElementById('tutormode');
        this.currentMode = this._readBootMode();

        if (this.select) {
            this.select.addEventListener('change', () => this._handleChange());
        }

        this._applyVisualState(this.currentMode);
        this._wireGuideBanner();
        this._syncExpiryTimer();

        if (this.currentMode === MODES.QUIZ && this.quizAvailable) {
            this._openQuizWhenReady();
        }
        if (this.currentMode === MODES.TEACH && this.teachAvailable) {
            this._openTeachWhenReady();
        }
        if (this.currentMode === MODES.GUIDE && this.guideAvailable) {
            this._openGuideWhenReady();
        }
    };

    TutorModeController.prototype._readBootMode = function() {
        if (this.select && this.select.value) {
            return this.select.value;
        }
        return MODES.NORMAL;
    };

    TutorModeController.prototype.isPersisting = function() {
        return this._persistCount > 0;
    };

    /**
     * True while a mode preference is saving, or while Quiz me / Teach me is active.
     *
     * @return {boolean}
     */
    TutorModeController.prototype.isMessagingLocked = function() {
        if (this._persistCount > 0
            || this.currentMode === MODES.QUIZ
            || this.currentMode === MODES.TEACH) {
            return true;
        }
        if (this.guideController
            && this.guideController.active
            && !this.guideController.sessionActive) {
            return true;
        }
        if (this.guideController
            && typeof this.guideController.isReviewing === 'function'
            && this.guideController.isReviewing()) {
            return true;
        }
        return false;
    };

    /**
     * @param {function(boolean): void} handler Called with true while composer messaging should stay disabled.
     */
    TutorModeController.prototype.setMessagingLockHandler = function(handler) {
        this._messagingLockHandler = typeof handler === 'function' ? handler : null;
        this._syncMessagingLock();
    };

    /**
     * @param {function(string): void} handler Called after a mode preference save finishes.
     */
    TutorModeController.prototype.setAfterPersistHandler = function(handler) {
        this._afterPersistHandler = typeof handler === 'function' ? handler : null;
    };

    TutorModeController.prototype.setQuizController = function(controller) {
        this.quizController = controller;
        if (this.currentMode === MODES.QUIZ && this.quizAvailable) {
            this._openQuizWhenReady();
        }
    };

    TutorModeController.prototype.setTeachController = function(controller) {
        this.teachController = controller;
        if (this.currentMode === MODES.TEACH && this.teachAvailable) {
            this._openTeachWhenReady();
        }
    };

    TutorModeController.prototype.setGuideController = function(controller) {
        this.guideController = controller;
        if (this.currentMode === MODES.GUIDE && this.guideAvailable) {
            this._openGuideWhenReady();
        }
    };

    TutorModeController.prototype.setMode = function(mode, options) {
        const opts = options || {};
        const valueChanged = this.select && this.select.value !== mode;
        if (valueChanged) {
            this.select.value = mode;
        }
        this.currentMode = mode;
        this._applyVisualState(mode);
        if (this._isExpirableMode(mode)) {
            this._lastActivity = Math.floor(Date.now() / 1000);
        }
        this._syncExpiryTimer();
        if (!opts.skipPersist) {
            this._persistMode(mode);
        }
        if (!opts.skipRouting) {
            this._routeMode(mode);
        }
        if (valueChanged && this.select) {
            // Sync choicedropdown button label; _handleChange skips when currentMode already matches.
            this.select.dispatchEvent(new Event('change', {bubbles: true}));
        }
    };

    TutorModeController.prototype._openQuizWhenReady = function() {
        if (!this.quizController || typeof this.quizController.openSetup !== 'function') {
            return;
        }
        if (typeof this.quizController.hasPersistedSession === 'function'
            && this.quizController.hasPersistedSession()) {
            return;
        }
        this.quizController.openSetup();
    };

    TutorModeController.prototype._openTeachWhenReady = function() {
        if (!this.teachController || typeof this.teachController.openSetup !== 'function') {
            return;
        }
        if (typeof this.teachController.hasPersistedSession === 'function'
            && this.teachController.hasPersistedSession()) {
            return;
        }
        this.teachController.openSetup();
    };

    TutorModeController.prototype._openGuideWhenReady = function() {
        if (!this.guideController || typeof this.guideController.openSetup !== 'function') {
            return;
        }
        if (typeof this.guideController.hasPersistedSession === 'function'
            && this.guideController.hasPersistedSession()) {
            return;
        }
        if (this.guideController.sessionActive) {
            if (typeof this.guideController.onGuideModeEntered === 'function') {
                this.guideController.onGuideModeEntered();
            }
            return;
        }
        this.guideController.openSetup();
    };

    TutorModeController.prototype._closeModePanes = function() {
        if (this.quizController && typeof this.quizController.closeQuizPane === 'function') {
            this.quizController.closeQuizPane(false);
        }
        if (this.teachController && typeof this.teachController.closeTeachPane === 'function') {
            this.teachController.closeTeachPane(false);
        }
        if (this.guideController && typeof this.guideController.closeGuidePane === 'function') {
            if (this.guideController.sessionActive
                && typeof this.guideController._endSession === 'function') {
                this.guideController._endSession({skipModeReset: true});
            } else {
                this.guideController.closeGuidePane();
            }
        }
    };

    TutorModeController.prototype._handleChange = function() {
        if (this._isSelectorLocked()) {
            if (this.select && this.select.value !== this.currentMode) {
                this.select.value = this.currentMode;
                this.select.dispatchEvent(new Event('change', {bubbles: true}));
            }
            return;
        }
        const mode = this.select ? this.select.value : MODES.NORMAL;
        if (mode === this.currentMode) {
            return;
        }
        this.currentMode = mode;
        this._applyVisualState(mode);
        this._persistMode(mode);
        this._routeMode(mode);
        if (this._isExpirableMode(mode)) {
            this._lastActivity = Math.floor(Date.now() / 1000);
        }
        this._syncExpiryTimer();
    };

    TutorModeController.prototype._isSelectorLocked = function() {
        return this._persistCount > 0 || this._externalSelectorLocked;
    };

    TutorModeController.prototype._syncSelectorLocked = function() {
        const locked = this._isSelectorLocked();
        if (!this.select) {
            return;
        }
        if (locked) {
            this.select.setAttribute('disabled', 'disabled');
        } else {
            this.select.removeAttribute('disabled');
        }
        if (this.root) {
            this.root.classList.toggle('tutor-mode-selector--locked', locked);
            this.root.setAttribute('aria-disabled', locked ? 'true' : 'false');
        }
    };

    TutorModeController.prototype._setSelectorLocked = function(locked) {
        this._externalSelectorLocked = locked;
        this._syncSelectorLocked();
    };

    /**
     * Lock or unlock the mode dropdown (e.g. while a quiz is in progress).
     *
     * @param {boolean} locked
     */
    TutorModeController.prototype.setSelectorLocked = function(locked) {
        this._setSelectorLocked(locked);
    };

    TutorModeController.prototype._wireGuideBanner = function() {
        const exitBtn = document.querySelector('#dixeo-tutor [data-action="exit-guide"]');
        if (!exitBtn) {
            return;
        }
        exitBtn.addEventListener('click', () => {
            if (this.guideController
                && typeof this.guideController.isReviewing === 'function'
                && this.guideController.isReviewing()
                && typeof this.guideController.exitReview === 'function') {
                this.guideController.exitReview();
                return;
            }
            if (this.guideController && typeof this.guideController.exitGuide === 'function') {
                this.guideController.exitGuide();
                return;
            }
            this.resetToNormal();
        });
    };

    TutorModeController.prototype._applyVisualState = function(mode) {
        if (this.root) {
            this.root.dataset.currentMode = mode;
        }
        const tutorRoot = document.getElementById('dixeo-tutor');
        if (tutorRoot) {
            tutorRoot.setAttribute('data-tutor-mode', mode);
        }
        this._syncMessagingLock();
    };

    TutorModeController.prototype._notifyMessagingLock = function(locked) {
        if (this._messagingLockHandler) {
            this._messagingLockHandler(locked);
        }
    };

    TutorModeController.prototype._syncMessagingLock = function() {
        this._notifyMessagingLock(this.isMessagingLocked());
    };

    TutorModeController.prototype._beginPersist = function() {
        this._persistCount += 1;
        if (this._persistCount === 1) {
            this._syncSelectorLocked();
            this._syncMessagingLock();
        }
    };

    TutorModeController.prototype._endPersist = function() {
        if (this._persistCount > 0) {
            this._persistCount -= 1;
        }
        if (this._persistCount === 0) {
            this._syncSelectorLocked();
            this._syncMessagingLock();
        }
    };

    TutorModeController.prototype._persistMode = function(mode) {
        this._beginPersist();
        ajax.call([{
            methodname: 'block_dixeo_tutor_set_tutor_mode',
            args: {
                courseid: this.courseid,
                mode: mode,
            },
        }])[0].fail(function() {
            // Preference sync failure is non-fatal; client mode still applies this session.
        }).always(() => {
            this._endPersist();
            if (typeof this._afterPersistHandler === 'function') {
                this._afterPersistHandler(this.currentMode);
            }
        });
    };

    TutorModeController.prototype._routeMode = function(mode) {
        if (mode === MODES.QUIZ && this.quizAvailable) {
            if (this.teachController && typeof this.teachController.closeTeachPane === 'function') {
                this.teachController.closeTeachPane(false);
            }
            if (this.guideController && typeof this.guideController.closeGuidePane === 'function') {
                this.guideController.closeGuidePane();
            }
            this._openQuizWhenReady();
            return;
        }
        if (mode === MODES.TEACH && this.teachAvailable) {
            if (this.quizController && typeof this.quizController.closeQuizPane === 'function') {
                this.quizController.closeQuizPane(false);
            }
            if (this.guideController && typeof this.guideController.closeGuidePane === 'function') {
                this.guideController.closeGuidePane();
            }
            this._openTeachWhenReady();
            return;
        }
        if (mode === MODES.GUIDE && this.guideAvailable) {
            if (this.quizController && typeof this.quizController.closeQuizPane === 'function') {
                this.quizController.closeQuizPane(false);
            }
            if (this.teachController && typeof this.teachController.closeTeachPane === 'function') {
                this.teachController.closeTeachPane(false);
            }
            this._openGuideWhenReady();
            return;
        }
        this._closeModePanes();
    };

    TutorModeController.prototype.noteActivity = function() {
        this._lastActivity = Math.floor(Date.now() / 1000);
        this._syncExpiryTimer();
    };

    TutorModeController.prototype._isExpirableMode = function(mode) {
        return mode === MODES.GUIDE || mode === MODES.QUIZ || mode === MODES.TEACH;
    };

    TutorModeController.prototype._clearExpiryTimer = function() {
        if (this._expiryTimer) {
            clearTimeout(this._expiryTimer);
            this._expiryTimer = null;
        }
    };

    TutorModeController.prototype._syncExpiryTimer = function() {
        this._clearExpiryTimer();
        if (!this._isExpirableMode(this.currentMode)) {
            return;
        }
        if (!this._lastActivity) {
            this._lastActivity = Math.floor(Date.now() / 1000);
        }
        const remainingMs = (3600 - (Math.floor(Date.now() / 1000) - this._lastActivity)) * 1000;
        if (remainingMs <= 0) {
            this.resetToNormal();
            return;
        }
        this._expiryTimer = setTimeout(() => {
            this._expiryTimer = null;
            this.resetToNormal();
        }, remainingMs);
    };

    TutorModeController.prototype.resetToNormal = function() {
        // Keep an in-progress quiz/lesson open across the idle timer; intentional
        // exits clear storage first, so this does not block leaving those modes.
        if (this.currentMode === MODES.QUIZ
                && this.quizController
                && typeof this.quizController.hasPersistedSession === 'function'
                && this.quizController.hasPersistedSession()) {
            return;
        }
        if (this.currentMode === MODES.TEACH
                && this.teachController
                && typeof this.teachController.hasPersistedSession === 'function'
                && this.teachController.hasPersistedSession()) {
            return;
        }
        if (this.guideController
            && this.guideController.sessionActive
            && typeof this.guideController.endSessionFromModeExpiry === 'function') {
            this.guideController.endSessionFromModeExpiry();
        }
        this.setMode(MODES.NORMAL);
    };

    TutorModeController.MODES = MODES;

    return TutorModeController;
});
