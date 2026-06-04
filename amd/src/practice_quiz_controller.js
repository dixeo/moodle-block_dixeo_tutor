define([
    'core/str',
    'mod_simplequiz2/embed_player',
    'block_dixeo_tutor/quiz_setup',
    'block_dixeo_tutor/quiz_api',
    'block_dixeo_tutor/context_poller',
    'block_dixeo_tutor/generation_mode_controller',
    'block_dixeo_tutor/tutor_session_storage',
    'block_dixeo_tutor/practice_quiz_review',
], function(str, embedPlayer, quizSetup, quizApi, contextPoller, generationModeController, sessionStorage, practiceQuizReview) {
    'use strict';

    const STORAGE_MODE = 'quiz';

    /**
     * Practice quiz orchestration for the tutor drawer.
     *
     * @param {Object} options
     * @param {number} options.courseid
     * @param {number} options.userid
     * @param {Object} options.ui ChatUI
     * @param {Object} options.state ChatState
     * @param {Object|null} options.modeController TutorModeController
     */
    const PracticeQuizController = function(options) {
        this.courseid = options.courseid;
        this.userid = options.userid;
        this.ui = options.ui;
        this.state = options.state;
        this.modeController = options.modeController || null;
        this.api = quizApi;
        this.bgPoller = contextPoller.createBackgroundPoller({
            api: this.api,
            ui: this.ui,
            state: this.state,
            courseid: this.courseid,
        });

        const root = document.getElementById('dixeo-tutor');
        this.quizPane = document.getElementById('dixeo-tutor-quiz-pane');
        this.body = document.getElementById('dixeo-tutor-body');

        this.hierarchy = null;
        this.hierarchyPromise = null;
        this.currentCmid = 0;
        if (root) {
            this.currentCmid = parseInt(root.dataset.currentCmid, 10) || 0;
        }

        this.active = false;
        this.quizInProgress = false;
        this.expectedCount = 0;
        this.embedInstance = null;
        this.quizTitle = '';
        this.questions = [];
        this.questionsJson = '';
        this.lastPlayerState = null;
        this._openSetupToken = 0;
        this._lastSetupConfig = null;
        this._generationToken = 0;
        this._activeGenerationJobId = null;

        this.tryResumeFromStorage();
    };

    generationModeController.applyGenerationModeMixin(PracticeQuizController.prototype, {
        hierarchyErrorMessage: 'Failed to load quiz hierarchy',
        strings: {
            generating: 'quiz_generating',
            setupCancel: 'quiz_setup_cancel',
            generateError: 'quiz_generate_error',
        },
        getPane: function() {
            return this.quizPane;
        },
        showPane: function() {
            this.showQuizPane();
        },
        closePane: function() {
            this.closeQuizPane(false);
        },
        cancelMode: function() {
            this._cancelQuiz();
        },
        onBeforeCancelClose: function() {
            this.clearStorage();
        },
        onGenerationError: function() {
            this.clearStorage();
        },
        setup: quizSetup,
        buildFreshInitialValues: function() {
            return {};
        },
        buildReopenInitialValues: function(savedConfig, currentCmid) {
            return {
                count: savedConfig.count,
                difficulty: savedConfig.difficulty,
                language: savedConfig.language,
                cmid: savedConfig.cmid || currentCmid,
                topicKey: generationModeController.topicKeyFromConfig(savedConfig),
            };
        },
        onResumeGenerating: function(saved) {
            this.expectedCount = saved.expectedCount || 0;
        },
        submitGeneration: function(setupConfig) {
            return this.api.submitPracticeQuiz(this.courseid, {
                scope: setupConfig.scope,
                sectionnum: setupConfig.sectionnum || 0,
                cmid: setupConfig.cmid || 0,
                count: setupConfig.count,
                difficulty: setupConfig.difficulty,
                topictitle: setupConfig.topictitle,
                language: setupConfig.language || '',
            });
        },
        persistGenerating: function(setupConfig, jobId) {
            this.persistState({
                phase: 'generating',
                jobId: jobId,
                topictitle: setupConfig.topictitle,
                expectedCount: this.expectedCount,
                setupConfig: setupConfig,
            });
        },
        onStartGeneration: function(setupConfig) {
            this.expectedCount = setupConfig.count || 0;
        },
        beforeReopenSetup: function() {
            this._setModeSelectorLocked(false);
        },
        finalizeAndMount: async function(jobId, topictitle, generationToken) {
            const finalized = await this.api.finalizePracticeQuiz(
                this.courseid,
                jobId,
                topictitle,
                this.expectedCount
            );
            if (generationToken !== this._generationToken) {
                return;
            }
            if (!finalized.success) {
                throw new Error(finalized.error || 'Finalize failed');
            }
            await this.mountPlayer(finalized.title, finalized.questions, null, {
                jobId: jobId,
                topictitle: topictitle,
            });
        },
    });

    PracticeQuizController.prototype.persistState = function(data) {
        sessionStorage.save(STORAGE_MODE, this.userid, this.courseid, data);
    };

    PracticeQuizController.prototype.clearStorage = function() {
        sessionStorage.clear(STORAGE_MODE, this.userid, this.courseid);
    };

    PracticeQuizController.prototype.hasPersistedSession = function() {
        return sessionStorage.hasActiveSession(STORAGE_MODE, this.userid, this.courseid);
    };

    PracticeQuizController.prototype.showQuizPane = function() {
        if (!this.quizPane) {
            return;
        }
        this.active = true;
        this.quizPane.classList.remove('d-none');
        this.quizPane.setAttribute('aria-hidden', 'false');
        if (this.body) {
            this.body.classList.add('dixeo-tutor-body--quiz-active');
        }
    };

    PracticeQuizController.prototype.setComposerQuizMode = function(inQuiz) {
        this.quizInProgress = inQuiz;
        if (this.body) {
            this.body.classList.toggle('dixeo-tutor-body--quiz-playing', inQuiz);
        }
        if (inQuiz) {
            this._setModeSelectorLocked(true);
        }
    };

    PracticeQuizController.prototype.attachQuestionsExitButton = async function() {
        const questionsPanel = this.quizPane && this.quizPane.querySelector('#simplequiz-questions');
        if (!questionsPanel || questionsPanel.querySelector('.dixeo-tutor-quiz-panel-exit')) {
            return;
        }

        const exitBtn = document.createElement('button');
        exitBtn.type = 'button';
        exitBtn.className = 'btn btn-sm btn-outline-secondary dixeo-tutor-quiz-panel-exit';
        exitBtn.textContent = await str.get_string('quiz_exit', 'block_dixeo_tutor');
        exitBtn.addEventListener('click', () => this.handleQuizExit());
        questionsPanel.insertBefore(exitBtn, questionsPanel.firstChild);
    };

    PracticeQuizController.prototype.handleQuizExit = function() {
        const best = this.lastPlayerState && this.lastPlayerState.bestAttempt;
        if (best && this.questionsJson) {
            this.submitReview({
                title: this.quizTitle,
                questionsjson: this.questionsJson,
                bestattemptjson: JSON.stringify(best),
                exitscore: best.score,
                total: best.total,
            });
        }
        this.clearStorage();
        this.closeQuizPane(false);
        this._lastSetupConfig = null;
        if (this.modeController) {
            this.modeController.resetToNormal();
        }
    };

    PracticeQuizController.prototype.closeQuizPane = function(wasCancelled) {
        if (wasCancelled || !this.quizInProgress) {
            this.clearStorage();
        }
        if (this.embedInstance) {
            this.embedInstance.destroy();
            this.embedInstance = null;
        }
        this.active = false;
        this.expectedCount = 0;
        this.quizTitle = '';
        this.questions = [];
        this.questionsJson = '';
        this.setComposerQuizMode(false);
        if (this.quizPane) {
            this.quizPane.innerHTML = '';
            this.quizPane.classList.add('d-none');
            this.quizPane.setAttribute('aria-hidden', 'true');
        }
        if (this.body) {
            this.body.classList.remove('dixeo-tutor-body--quiz-active');
        }
        this._setModeSelectorLocked(false);
    };

    PracticeQuizController.prototype._cancelQuiz = function() {
        this._openSetupToken++;
        this._lastSetupConfig = null;
        this.closeQuizPane(false);
        if (this.modeController) {
            this.modeController.resetToNormal();
        }
    };

    PracticeQuizController.prototype.mountPlayer = async function(title, questionsJson, savedProgress, resumeMeta) {
        this._lastSetupConfig = null;
        this.quizTitle = title;
        this.questionsJson = questionsJson || '[]';
        this.questions = JSON.parse(this.questionsJson);
        const self = this;
        const meta = resumeMeta || {};
        this._resumeJobId = meta.jobId || this._resumeJobId || null;
        this._resumeTopicTitle = meta.topictitle || this._resumeTopicTitle || '';

        const rendered = await this.api.renderEmbed(this.courseid, this.questionsJson, '');
        this.quizPane.innerHTML = rendered.html;

        const root = this.quizPane.querySelector('.simplequiz2-embed') || this.quizPane;
        this.showQuizPane();
        this.setComposerQuizMode(true);
        await this.attachQuestionsExitButton();

        const playerState = savedProgress || null;
        this.lastPlayerState = playerState;

        // Technical resume fields only — re-finalize from jobId after reload.
        if (this._resumeJobId) {
            this.persistState({
                phase: 'playing',
                jobId: this._resumeJobId,
                title: this.quizTitle,
                topictitle: this._resumeTopicTitle,
                expectedCount: this.expectedCount,
            });
        }

        this.embedInstance = embedPlayer.init(root, {
            courseid: this.courseid,
            questionsJson: this.questionsJson,
            questionCount: this.questions.length,
            initialState: playerState,
            onStateChange: function(state) {
                self.lastPlayerState = state;
            },
            onFinish: function(result) {
                if (result && result.bestAttempt) {
                    self.submitReview({
                        title: self.quizTitle,
                        questionsjson: self.questionsJson,
                        bestattemptjson: JSON.stringify(result.bestAttempt),
                        exitscore: result.score,
                        total: result.total,
                    });
                }
                self.clearStorage();
                self._lastSetupConfig = null;
                self.setComposerQuizMode(false);
                self.closeQuizPane(false);
                if (self.modeController) {
                    self.modeController.resetToNormal();
                }
            },
            onRestart: function() {
                self.setComposerQuizMode(true);
            },
        });
    };

    PracticeQuizController.prototype.tryResumeFromStorage = async function() {
        const saved = sessionStorage.load(STORAGE_MODE, this.userid, this.courseid);
        if (!saved || !saved.phase) {
            return;
        }

        if (saved.phase === 'generating' || saved.phase === 'playing') {
            this._setModeSelectorLocked(true);
        }

        this.showQuizPane();

        if (saved.phase === 'generating' && saved.jobId) {
            await this.tryResumeGenerating(saved);
            return;
        }

        if (saved.phase === 'playing' && saved.jobId) {
            this.expectedCount = saved.expectedCount || 0;
            this._resumeJobId = saved.jobId;
            this._resumeTopicTitle = saved.topictitle || saved.title || '';
            this._generationToken++;
            const generationToken = this._generationToken;
            try {
                await this.finalizeAndMount(
                    saved.jobId,
                    this._resumeTopicTitle,
                    generationToken
                );
            } catch (e) {
                await this.handleError(e);
            }
        }
    };

    PracticeQuizController.prototype.submitReview = async function(payload) {
        practiceQuizReview.appendOptimisticMessage(this.ui, payload);

        try {
            const result = await this.api.submitQuizReview(this.courseid, payload);
            if (result.success && result.jobid) {
                this.bgPoller.poll(result.jobid);
            }
        } catch (e) {
            // Non-fatal: quiz UI continues even if review submit fails.
        }
    };

    PracticeQuizController.prototype.retakeQuizFromContext = async function(data) {
        if (!data || !data.questionsJson) {
            return;
        }
        let total;
        try {
            total = (data.bestAttempt && data.bestAttempt.total)
                || JSON.parse(data.questionsJson).length;
        } catch (e) {
            return;
        }
        const freshState = {
            answerResults: [],
            selectedAnswerIds: [],
            currentQuestionIndex: 0,
            showingResults: false,
            score: null,
            total: total,
            bestAttempt: data.bestAttempt || null,
        };
        if (this.modeController && typeof this.modeController.setMode === 'function') {
            this.modeController.setMode('quiz', {skipRouting: true});
        }
        try {
            await this.mountPlayer(data.title || '', data.questionsJson, freshState);
        } catch (e) {
            if (typeof this.handleError === 'function') {
                await this.handleError(e);
            }
        }
    };

    PracticeQuizController.prototype.destroy = function() {
        this.bgPoller.cancel();
        if (this.embedInstance) {
            this.embedInstance.destroy();
            this.embedInstance = null;
        }
    };

    return PracticeQuizController;
});
