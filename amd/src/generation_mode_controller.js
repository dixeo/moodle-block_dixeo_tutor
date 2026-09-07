define([
    'core/str',
    'block_dixeo_tutor/generation_job_poller',
], function(str, generationJobPoller) {
    'use strict';

    /**
     * Build topic option key from a saved setup config.
     *
     * @param {Object} config
     * @return {string}
     */
    const topicKeyFromConfig = function(config) {
        if (config.cmid) {
            return 'activity-' + config.cmid;
        }
        if (config.scope === 'section' && config.sectionnum) {
            return 'section-' + config.sectionnum;
        }
        return 'course';
    };

    /**
     * Apply shared generation-mode methods to a controller prototype.
     *
     * @param {Object} proto Controller prototype
     * @param {Object} config Mode configuration
     */
    const applyGenerationModeMixin = function(proto, config) {
        proto.loadHierarchy = function() {
            if (this.hierarchy) {
                return Promise.resolve(this.hierarchy);
            }
            if (!this.hierarchyPromise) {
                this.hierarchyPromise = this.api.loadHierarchy(this.courseid).then((data) => {
                    this.hierarchy = data || {course: {name: ''}, sections: []};
                    return this.hierarchy;
                }).catch((err) => {
                    this.hierarchyPromise = null;
                    const detail = err?.message || err?.error || '';
                    throw new Error(detail ? String(detail) : config.hierarchyErrorMessage);
                });
            }
            return this.hierarchyPromise;
        };

        proto._setModeSelectorLocked = function(locked) {
            if (this.modeController && typeof this.modeController.setSelectorLocked === 'function') {
                this.modeController.setSelectorLocked(locked);
            }
        };

        proto.showLoading = async function(onCancel) {
            const pane = config.getPane.call(this);
            if (!pane) {
                return;
            }
            pane.innerHTML = '';

            const loadingWrap = document.createElement('div');
            loadingWrap.className = 'dixeo-tutor-generating';

            const iconWrap = document.createElement('div');
            iconWrap.className = 'dixeo-tutor-generating__icon';
            iconWrap.setAttribute('aria-hidden', 'true');
            iconWrap.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

            const loadingText = document.createElement('p');
            loadingText.className = 'dixeo-tutor-generating__text';

            loadingWrap.appendChild(iconWrap);
            loadingWrap.appendChild(loadingText);
            pane.appendChild(loadingWrap);

            loadingText.textContent = await str.get_string(config.strings.generating, 'block_dixeo_tutor');

            if (typeof onCancel === 'function') {
                const actions = document.createElement('div');
                actions.className = 'dixeo-tutor-generating__actions';

                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.className = 'btn btn-outline-secondary btn-sm';
                cancelBtn.textContent = await str.get_string(config.strings.setupCancel, 'block_dixeo_tutor');
                cancelBtn.addEventListener('click', onCancel);
                actions.appendChild(cancelBtn);
                loadingWrap.appendChild(actions);
            }
        };

        proto.cancelGeneration = function(generationToken) {
            if (generationToken !== this._generationToken) {
                return;
            }
            this._generationToken++;
            const jobId = this._activeGenerationJobId;
            this._activeGenerationJobId = null;
            if (typeof config.onBeforeCancelClose === 'function') {
                config.onBeforeCancelClose.call(this);
            }
            config.cancelMode.call(this);
            if (jobId) {
                this.api.cancelGenerationJob(this.courseid, jobId).catch(() => {
                    // Best-effort: polling is already stopped client-side.
                });
            }
        };

        proto.handleError = async function(source, options) {
            const opts = options || {};
            let message;
            const errorcode = source?.errorcode;

            if (errorcode === 'payment_required') {
                message = await str.get_string('error:payment_required', 'local_dixeo');
            } else if (source?.errormessage) {
                message = String(source.errormessage).replace(/^API Error:\s*/i, '');
            } else if (source?.message) {
                message = String(source.message).replace(/^API Error:\s*/i, '');
            } else if (source?.error) {
                message = String(source.error);
            } else {
                message = await str.get_string(config.strings.generateError, 'block_dixeo_tutor');
            }

            if (typeof config.onGenerationError === 'function') {
                config.onGenerationError.call(this);
            }

            if (opts.reopenSetup) {
                this.ui.appendErrorMessage(message);
                await this._reopenSetupPanel();
                return;
            }

            config.closePane.call(this, false);
            this.ui.appendErrorMessage(message);
        };

        proto._reopenSetupPanel = async function() {
            const pane = config.getPane.call(this);
            if (!pane) {
                return;
            }

            const setupToken = this._openSetupToken;
            const savedConfig = this._lastSetupConfig || {};
            const initialValues = config.buildReopenInitialValues(savedConfig, this.currentCmid);

            if (typeof config.beforeReopenSetup === 'function') {
                config.beforeReopenSetup.call(this);
            }
            config.showPane.call(this);

            try {
                const root = document.getElementById('dixeo-tutor');
                const coursename = root?.dataset.courseName || '';
                const loadHierarchy = () => this.loadHierarchy();

                await config.setup.openSetup(
                    pane,
                    coursename,
                    () => config.cancelMode.call(this),
                    (cfg) => this.startGeneration(cfg),
                    loadHierarchy,
                    this.currentCmid,
                    initialValues
                );

                if (setupToken !== this._openSetupToken) {
                    return;
                }
            } catch (e) {
                if (setupToken !== this._openSetupToken) {
                    return;
                }
                await this.handleError(e);
            }
        };

        proto.openSetup = async function() {
            const pane = config.getPane.call(this);
            if (!pane) {
                return;
            }

            const setupToken = ++this._openSetupToken;
            const root = document.getElementById('dixeo-tutor');
            const coursename = root?.dataset.courseName || '';

            config.showPane.call(this);

            try {
                const loadHierarchy = () => this.loadHierarchy();
                await config.setup.openSetup(
                    pane,
                    coursename,
                    () => config.cancelMode.call(this),
                    (cfg) => this.startGeneration(cfg),
                    loadHierarchy,
                    this.currentCmid,
                    config.buildFreshInitialValues()
                );
                if (setupToken !== this._openSetupToken) {
                    return;
                }
            } catch (e) {
                if (setupToken !== this._openSetupToken) {
                    return;
                }
                await this.handleError(e);
            }
        };

        proto.startGeneration = async function(setupConfig) {
            this._lastSetupConfig = setupConfig;
            if (typeof config.onStartGeneration === 'function') {
                config.onStartGeneration.call(this, setupConfig);
            }
            this._setModeSelectorLocked(true);

            const generationToken = ++this._generationToken;
            this._activeGenerationJobId = null;
            await this.showLoading(() => {
                this.cancelGeneration(generationToken);
            });

            try {
                const submit = await config.submitGeneration.call(this, setupConfig);

                if (generationToken !== this._generationToken) {
                    return;
                }

                if (!submit || !submit.jobid) {
                    await this.handleError(submit, {reopenSetup: true});
                    return;
                }

                this._activeGenerationJobId = submit.jobid;

                if (typeof config.persistGenerating === 'function') {
                    config.persistGenerating.call(this, setupConfig, submit.jobid);
                }

                await this.pollGenerationJob(submit.jobid, setupConfig.topictitle, generationToken);
            } catch (e) {
                if (generationToken !== this._generationToken) {
                    return;
                }
                await this.handleError(e, {reopenSetup: true});
            }
        };

        proto.pollGenerationJob = async function(jobId, topictitle, generationToken) {
            const self = this;
            await generationJobPoller.pollGenerationJob({
                api: this.api,
                courseid: this.courseid,
                jobId: jobId,
                generationToken: generationToken,
                getGenerationToken: function() {
                    return self._generationToken;
                },
                onCompleted: async function() {
                    await config.finalizeAndMount.call(self, jobId, topictitle, generationToken);
                },
            });
        };

        proto.tryResumeGenerating = async function(saved) {
            this._lastSetupConfig = saved.setupConfig || null;
            if (typeof config.onResumeGenerating === 'function') {
                config.onResumeGenerating.call(this, saved);
            }
            const generationToken = ++this._generationToken;
            this._activeGenerationJobId = saved.jobId;
            await this.showLoading(() => {
                this.cancelGeneration(generationToken);
            });
            try {
                await this.pollGenerationJob(saved.jobId, saved.topictitle || '', generationToken);
            } catch (e) {
                if (generationToken !== this._generationToken) {
                    return;
                }
                await this.handleError(e, {reopenSetup: true});
            }
        };
    };

    return {
        applyGenerationModeMixin: applyGenerationModeMixin,
        topicKeyFromConfig: topicKeyFromConfig,
    };
});
