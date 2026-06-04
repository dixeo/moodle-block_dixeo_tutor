define([
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/system_message_display',
    'block_dixeo_tutor/practice_quiz_review',
    'block_dixeo_tutor/custom_lesson_panel',
], function(constants, systemMessageDisplay, practiceQuizReview, customLessonPanel) {
    'use strict';

    /**
     * Poll tutor jobs in background and append new assistant messages (quiz context).
     *
     * Each submitted context event gets its own poll loop. Loops are independent and
     * are not cancelled when the quiz UI closes — only on explicit cancel() (page unload).
     *
     * @param {Object} deps
     * @param {Object} deps.api quiz_api
     * @param {Object} deps.ui ChatUI
     * @param {Object} deps.state ChatState
     * @param {number} deps.courseid
     * @return {{poll: Function, cancel: Function}}
     */
    const createBackgroundPoller = function(deps) {
        /** @type {Map<string, {timeoutId: number|null, silent: boolean}>} */
        const activeJobs = new Map();
        let appendChain = Promise.resolve();

        /**
         * Show or hide the typing indicator for in-flight proactive context jobs.
         */
        const syncPendingIndicator = function() {
            let showIndicator = false;
            activeJobs.forEach(function(record) {
                if (!record.silent) {
                    showIndicator = true;
                }
            });
            if (showIndicator) {
                deps.ui.showPendingIndicator();
                return;
            }
            if (!deps.state.isPending()) {
                deps.ui.hidePendingIndicator();
            }
        };

        /**
         * Fetch conversation delta and append visible assistant replies.
         * Serialized so concurrent job completions do not race on lastRenderedId.
         *
         * @return {Promise<void>}
         */
        const appendReplies = function() {
            appendChain = appendChain.then(async function() {
                const sinceid = deps.state.getLastRenderedId();
                const delta = await deps.api.loadConversation(deps.courseid, sinceid);
                const messages = Array.isArray(delta.messages) ? delta.messages : [];
                messages.forEach(function(msg) {
                    if (systemMessageDisplay.isHiddenSystemMessage(msg)) {
                        return;
                    }
                    const role = String(msg.role || '').toLowerCase();
                    const isReview = practiceQuizReview.isReviewMessage(msg);
                    const isCustomLesson = customLessonPanel.isCustomLessonMessage(msg);
                    if (role !== 'assistant' && !isReview && !isCustomLesson) {
                        return;
                    }
                    if (isReview) {
                        practiceQuizReview.removeOptimisticMessage(deps.ui);
                    }
                    if (isCustomLesson) {
                        customLessonPanel.removeOptimisticMessage(deps.ui);
                    }
                    if (msg.id && deps.ui.hasMessage(msg.id)) {
                        if (msg.id) {
                            deps.state.setLastRenderedId(msg.id);
                        }
                        return;
                    }
                    deps.ui.appendMessage(msg);
                    if (msg.id) {
                        deps.state.setLastRenderedId(msg.id);
                    }
                });
                syncPendingIndicator();
                return undefined;
            }).catch(function() {
                // Swallow — individual job loops handle retry on next completion attempt.
            });
            return appendChain;
        };

        /**
         * Stop polling a single job.
         *
         * @param {string} jobId
         */
        const stopJob = function(jobId) {
            const record = activeJobs.get(jobId);
            if (record && record.timeoutId) {
                clearTimeout(record.timeoutId);
            }
            activeJobs.delete(jobId);
            syncPendingIndicator();
        };

        /**
         * Start polling a tutor reply job. Duplicate job IDs are ignored.
         *
         * @param {string} jobId
         * @param {{silent?: boolean}} [options]
         */
        const poll = function(jobId, options) {
            if (!jobId || activeJobs.has(jobId)) {
                return;
            }

            const opts = options || {};
            activeJobs.set(jobId, {timeoutId: null, silent: !!opts.silent});
            syncPendingIndicator();

            const tick = async function() {
                if (!activeJobs.has(jobId)) {
                    return;
                }

                try {
                    const status = await deps.api.pollJobStatus(deps.courseid, jobId);
                    if (status.status === 'completed') {
                        await appendReplies();
                        stopJob(jobId);
                        return;
                    }
                    if (status.status === 'failed') {
                        stopJob(jobId);
                        return;
                    }

                    const record = activeJobs.get(jobId);
                    if (record) {
                        record.timeoutId = setTimeout(tick, constants.polling.REPLY_INTERVAL_MS);
                    }
                } catch (e) {
                    const record = activeJobs.get(jobId);
                    if (record) {
                        record.timeoutId = setTimeout(tick, constants.polling.REPLY_INTERVAL_MS * 2);
                    }
                }
            };

            const record = activeJobs.get(jobId);
            record.timeoutId = setTimeout(tick, constants.polling.REPLY_INTERVAL_MS);
        };

        /**
         * Cancel all active polls (e.g. page unload).
         */
        const cancel = function() {
            Array.from(activeJobs.keys()).forEach(function(jobId) {
                stopJob(jobId);
            });
        };

        return {poll: poll, cancel: cancel};
    };

    return {
        createBackgroundPoller: createBackgroundPoller,
    };
});
