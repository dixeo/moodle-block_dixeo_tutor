define([
    'core/ajax',
    'block_dixeo_tutor/errors',
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/logger'
], function(ajax, errors, constants, Logger) {
    'use strict';

    const log = Logger.namespace('ChatAPI');

    /**
     * Enhanced AJAX helper with error handling and retry logic.
     * @param {string} methodname The API method to call.
     * @param {object} args The arguments for the API method.
     * @param {object} options Additional options for the AJAX call.
     */
    const callAjax = async(methodname, args, options = {}) => {
        const defaultOptions = {
            timeout: constants.network.AJAX_TIMEOUT,
            retries: 0,
            maxRetries: constants.network.MAX_RETRIES,
            retryDelay: constants.network.RETRY_DELAY,
        };

        const finalOptions = {...defaultOptions, ...options};
        const {retries, maxRetries, retryDelay, ...ajaxOptions} = finalOptions;

        try {
            const request = {
                methodname: `block_dixeo_tutor_${methodname}`,
                args: args,
                ...ajaxOptions
            };

            const result = await ajax.call([request])[0];

            if (result && result.error) {
                throw new errors.APIError(
                    result.message || 'API request failed',
                    result.errorcode || 'UNKNOWN',
                    {methodname, args}
                );
            }

            return result;

        } catch (error) {
            if (error.name === 'NetworkError' && retries < maxRetries) {
                await new Promise(resolve => setTimeout(resolve, retryDelay));
                return callAjax(methodname, args, {
                    ...options,
                    retries: retries + 1,
                    retryDelay: retryDelay * 2 // Exponential backoff.
                });
            }

            if (error.name === 'TimeoutError') {
                throw new errors.TimeoutError(
                    `Request timed out after ${ajaxOptions.timeout}ms`,
                    ajaxOptions.timeout,
                    {methodname, args}
                );
            }

            if (error instanceof errors.TutorError) {
                throw error;
            }

            throw new errors.NetworkError(
                error.message || 'Unknown network error',
                {originalError: error, methodname, args}
            );
        }
    };

    return class ChatAPI {

        /**
         * Loads conversation history.
         * @param {number} courseid The ID of the course.
         * @param {string|null} sinceid Optional message ID cursor for newer messages.
         * @param {number|null} offset Optional offset for loading older message pages.
         * @returns {Promise<object>} Conversation data.
         * @throws {NetworkError|APIError|ValidationError}
         */
        async loadConversation(courseid, sinceid = null, offset = null) {
            if (!courseid || courseid <= 0) {
                throw new errors.ValidationError(
                    'Invalid course ID',
                    'courseid',
                    {courseid}
                );
            }

            if (sinceid && offset) {
                throw new errors.ValidationError(
                    'Cannot use sinceid and offset together',
                    'cursor',
                    {sinceid, offset}
                );
            }

            const args = {courseid};
            if (sinceid) {
                args.sinceid = sinceid;
            }
            if (offset !== null && offset > 0) {
                args.offset = offset;
            }

            try {
                const result = await callAjax('get_conversation', args);

                if (!result || !Array.isArray(result.messages)) {
                    throw new errors.APIError(
                        'Invalid conversation response format',
                        'INVALID_RESPONSE',
                        {result}
                    );
                }

                result.messages = this._normalizeMessages(result.messages);
                return result;
            } catch (error) {
                log.error('Failed to load conversation', {
                    courseid,
                    sinceid,
                    offset,
                    error: error.message,
                    code: error.code
                });
                throw error;
            }
        }

        /**
         * @param {Array<object>} messages
         * @returns {Array<object>}
         * @private
         */
        _normalizeMessages(messages) {
            return messages.map((msg) => {
                const normalized = Object.assign({}, msg);
                if (typeof normalized.context === 'string' && normalized.context !== '') {
                    try {
                        normalized.context = JSON.parse(normalized.context);
                    } catch (e) {
                        // Ignore malformed context JSON.
                    }
                }
                return normalized;
            });
        }

        /*
         * Polls the status of a tutor job.
         * @param {string} jobId The job UUID.
         * @param {number} courseid The ID of the course.
         * @returns {Promise<object>} Job status data.
         * @throws {NetworkError|APIError|ValidationError}
         */
        async pollJobStatus(jobId, courseid) {
            if (!courseid || courseid <= 0) {
                throw new errors.ValidationError(
                    'Invalid course ID',
                    'courseid',
                    {courseid}
                );
            }
            if (!jobId) {
                throw new errors.ValidationError(
                    'Invalid job ID',
                    'jobId',
                    {jobId}
                );
            }

            try {
                return await callAjax('get_job_status', {courseid: courseid, jobid: jobId});
            } catch (error) {
                log.error('Failed to poll job status', {
                    error: error.message,
                    code: error.code
                });
                throw error;
            }
        }

        /**
         * Flushes queued proactive context for this course (if any).
         * @param {number} courseid The ID of the course.
         * @param {string} pageurl The current page URL for context.
         * @returns {Promise<object>} Flush result; flushed=true when a job was submitted.
         */
        async flushPendingContext(courseid, pageurl = '') {
            if (!courseid || courseid <= 0) {
                throw new errors.ValidationError(
                    'Invalid course ID',
                    'courseid',
                    {courseid}
                );
            }

            try {
                const result = await callAjax(
                    'flush_pending_context',
                    {courseid, pageurl},
                    {timeout: constants.network.AJAX_TIMEOUT}
                );

                if (!result || typeof result.flushed === 'undefined') {
                    throw new errors.APIError(
                        'Invalid flush pending context response',
                        'INVALID_RESPONSE',
                        {result}
                    );
                }

                return result;
            } catch (error) {
                log.error('Failed to flush pending context', {
                    courseid,
                    error: error.message,
                    code: error.code
                });
                throw error;
            }
        }

        /**
         * Sends a message to the tutor (async, returns job_id).
         * @param {number} courseid The ID of the course.
         * @param {string} message The message content.
         * @param {string} pageurl The current page URL for context.
         * @returns {Promise<object>} Send result with job_id.
         * @throws {NetworkError|APIError|ValidationError|TimeoutError}
         */
        async sendMessage(courseid, message, pageurl = '') {
            if (!courseid || courseid <= 0) {
                throw new errors.ValidationError(
                    'Invalid course ID',
                    'courseid',
                    {courseid}
                );
            }

            if (!message || !message.trim()) {
                throw new errors.ValidationError(
                    'Message cannot be empty',
                    'message',
                    {message}
                );
            }

            try {
                const result = await callAjax(
                    'send_message',
                    {courseid, message: message.trim(), pageurl},
                    {timeout: constants.network.AJAX_TIMEOUT}
                );

                // Validate response — new API returns {completed, job_id, status}.
                if (!result || typeof result.completed === 'undefined') {
                    throw new errors.APIError(
                        'Invalid send message response',
                        'INVALID_RESPONSE',
                        {result}
                    );
                }

                return result;
            } catch (error) {
                log.error('Failed to send message', {
                    messageLength: message.length,
                    error: error.message,
                    code: error.code
                });
                throw error;
            }
        }

        /**
         * Marks incoming tutor messages as read.
         * @param {number} courseid The ID of the course.
         * @param {number} [lastIncomingTime] Unix time of latest incoming message seen (0 = server resolves).
         * @returns {Promise<{hasunread: boolean, lastread: number}>}
         */
        async markMessagesRead(courseid, lastIncomingTime = 0) {
            if (!courseid || courseid <= 0) {
                throw new errors.ValidationError('Invalid course ID', 'courseid', {courseid});
            }

            const args = {courseid};
            if (lastIncomingTime > 0) {
                args.lastincomingtime = lastIncomingTime;
            }

            return callAjax('mark_messages_read', args);
        }
    };
});
