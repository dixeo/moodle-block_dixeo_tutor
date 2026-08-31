define([
    'block_dixeo_tutor/teach_setup',
    'block_dixeo_tutor/teach_api',
    'block_dixeo_tutor/teach_lesson_view',
    'block_dixeo_tutor/generation_mode_controller',
    'block_dixeo_tutor/tutor_session_storage',
    'block_dixeo_tutor/context_poller',
    'block_dixeo_tutor/custom_lesson_panel',
], function(
    teachSetup,
    teachApi,
    teachLessonView,
    generationModeController,
    sessionStorage,
    contextPoller,
    customLessonPanel
) {
    'use strict';

    const STORAGE_MODE = 'teach';

    /**
     * Teach lesson orchestration for the tutor drawer.
     *
     * @param {Object} options
     * @param {number} options.courseid
     * @param {number} options.userid
     * @param {Object} options.ui ChatUI
     * @param {Object} options.state ChatState
     * @param {Object|null} options.modeController TutorModeController
     */
    const TeachController = function(options) {
        this.courseid = options.courseid;
        this.userid = options.userid;
        this.ui = options.ui;
        this.state = options.state;
        this.modeController = options.modeController || null;
        this.api = teachApi;
        this.bgPoller = contextPoller.createBackgroundPoller({
            api: this.api,
            ui: this.ui,
            state: this.state,
            courseid: this.courseid,
        });

        const root = document.getElementById('dixeo-tutor');
        this.teachPane = document.getElementById('dixeo-tutor-teach-pane');
        this.body = document.getElementById('dixeo-tutor-body');

        this.hierarchy = null;
        this.hierarchyPromise = null;
        this.currentCmid = 0;
        if (root) {
            this.currentCmid = parseInt(root.dataset.currentCmid, 10) || 0;
        }

        this.active = false;
        this.lessonViewing = false;
        this._openSetupToken = 0;
        this._lastSetupConfig = null;
        this._currentLesson = null;
        this._generationToken = 0;
        this._activeGenerationJobId = null;

        this.tryResumeFromStorage();
    };

    generationModeController.applyGenerationModeMixin(TeachController.prototype, {
        hierarchyErrorMessage: 'Failed to load topic hierarchy',
        strings: {
            generating: 'teach_generating',
            setupCancel: 'teach_setup_cancel',
            generateError: 'teach_generate_error',
        },
        getPane: function() {
            return this.teachPane;
        },
        showPane: function() {
            this.showTeachPane();
        },
        closePane: function() {
            this.closeTeachPane(false);
        },
        cancelMode: function() {
            this._cancelTeach();
        },
        onBeforeCancelClose: function() {
            this.clearStorage();
        },
        setup: teachSetup,
        buildFreshInitialValues: function() {
            return {};
        },
        buildReopenInitialValues: function(savedConfig, currentCmid) {
            return {
                learnerrequest: savedConfig.learnerrequest || '',
                language: savedConfig.language || '',
                cmid: savedConfig.cmid || currentCmid,
                topicKey: generationModeController.topicKeyFromConfig(savedConfig),
            };
        },
        submitGeneration: function(setupConfig) {
            return this.api.submitTeachLesson(this.courseid, {
                scope: setupConfig.scope,
                sectionnum: setupConfig.sectionnum || 0,
                cmid: setupConfig.cmid || 0,
                topictitle: setupConfig.topictitle,
                learnerrequest: setupConfig.learnerrequest,
                language: setupConfig.language || '',
            });
        },
        persistGenerating: function(setupConfig, jobId) {
            this.persistState({
                phase: 'generating',
                jobId: jobId,
                topictitle: setupConfig.topictitle,
                setupConfig: setupConfig,
            });
        },
        beforeReopenSetup: function() {
            this._setModeSelectorLocked(false);
            this.setLessonViewingMode(false);
        },
        finalizeAndMount: async function(jobId, topictitle, generationToken) {
            const finalized = await this.api.finalizeTeachLesson(
                this.courseid,
                jobId,
                topictitle
            );
            if (generationToken !== this._generationToken) {
                return;
            }
            if (!finalized.success) {
                throw new Error(finalized.error || 'Finalize failed');
            }
            const lesson = {
                title: finalized.title,
                introhtml: finalized.introhtml,
                contenthtml: finalized.contenthtml,
            };
            await this.mountLesson(lesson, {
                jobId: jobId,
                topictitle: topictitle,
            });
            if (generationToken !== this._generationToken) {
                return;
            }
            this.submitLessonContext(lesson);
        },
    });

    TeachController.prototype.onLessonFlowEnd = function() {
        // Lesson remains in chat history via custom lesson system message.
    };

    TeachController.prototype.submitLessonContext = async function(lesson) {
        customLessonPanel.appendOptimisticMessage(this.ui, lesson);

        try {
            const result = await this.api.submitTeachLessonContext(this.courseid, {
                title: lesson.title || '',
                introhtml: lesson.introhtml || '',
                contenthtml: lesson.contenthtml || '',
            });
            if (result.success && result.jobid) {
                this.bgPoller.poll(result.jobid, {silent: true});
            }
        } catch (e) {
            // Non-fatal: lesson pane still works if context submit fails.
        }
    };

    TeachController.prototype.openLessonFromContext = async function(lesson) {
        await this.mountLesson(lesson);
    };

    TeachController.prototype.persistState = function(data) {
        sessionStorage.save(STORAGE_MODE, this.userid, this.courseid, data);
    };

    TeachController.prototype.clearStorage = function() {
        sessionStorage.clear(STORAGE_MODE, this.userid, this.courseid);
    };

    TeachController.prototype.hasPersistedSession = function() {
        return sessionStorage.hasActiveSession(STORAGE_MODE, this.userid, this.courseid);
    };

    TeachController.prototype.showTeachPane = function() {
        if (!this.teachPane) {
            return;
        }
        this.active = true;
        this.teachPane.classList.remove('d-none');
        this.teachPane.setAttribute('aria-hidden', 'false');
        if (this.body) {
            this.body.classList.add('dixeo-tutor-body--teach-active');
        }
    };

    TeachController.prototype.setLessonViewingMode = function(viewing) {
        this.lessonViewing = viewing;
        if (this.body) {
            this.body.classList.toggle('dixeo-tutor-body--teach-viewing', viewing);
        }
        if (viewing) {
            this._setModeSelectorLocked(true);
        }
    };

    TeachController.prototype.closeTeachPane = function() {
        teachLessonView.destroy();
        this.active = false;
        this.lessonViewing = false;
        this._currentLesson = null;
        this.setLessonViewingMode(false);
        if (this.teachPane) {
            this.teachPane.innerHTML = '';
            this.teachPane.classList.add('d-none');
            this.teachPane.setAttribute('aria-hidden', 'true');
        }
        if (this.body) {
            this.body.classList.remove('dixeo-tutor-body--teach-active');
            this.body.classList.remove('dixeo-tutor-body--teach-viewing');
        }
        this._setModeSelectorLocked(false);
    };

    TeachController.prototype._cancelTeach = function() {
        this._openSetupToken++;
        this._lastSetupConfig = null;
        this.clearStorage();
        this.closeTeachPane(false);
        if (this.modeController) {
            this.modeController.resetToNormal();
        }
    };

    TeachController.prototype._handleLessonClose = function() {
        this._lastSetupConfig = null;
        this.clearStorage();
        this.closeTeachPane(false);
        this.onLessonFlowEnd();
        if (this.modeController) {
            this.modeController.resetToNormal();
        }
    };

    TeachController.prototype.mountLesson = async function(lesson, resumeMeta) {
        this._lastSetupConfig = null;
        this._currentLesson = lesson;
        this.showTeachPane();
        this.setLessonViewingMode(true);

        const meta = resumeMeta || {};
        this._resumeJobId = meta.jobId || this._resumeJobId || null;
        this._resumeTopicTitle = meta.topictitle || this._resumeTopicTitle || '';

        // Technical resume fields only — re-finalize from jobId after reload.
        if (this._resumeJobId) {
            this.persistState({
                phase: 'viewing',
                jobId: this._resumeJobId,
                title: lesson.title,
                topictitle: this._resumeTopicTitle,
            });
        }

        await teachLessonView.mountLesson(
            this.teachPane,
            lesson,
            () => this._handleLessonClose()
        );
    };

    TeachController.prototype.tryResumeFromStorage = async function() {
        const saved = sessionStorage.load(STORAGE_MODE, this.userid, this.courseid);
        if (!saved || !saved.phase) {
            return;
        }

        if (saved.phase === 'generating' || saved.phase === 'viewing') {
            this._setModeSelectorLocked(true);
        }

        this.showTeachPane();

        if (saved.phase === 'generating' && saved.jobId) {
            await this.tryResumeGenerating(saved);
            return;
        }

        if (saved.phase === 'viewing' && saved.jobId) {
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

    TeachController.prototype.destroy = function() {
        this.bgPoller.cancel();
        teachLessonView.destroy();
    };

    return TeachController;
});
