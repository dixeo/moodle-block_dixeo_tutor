define([
    'core/str',
    'local_dixeo/network',
    'block_dixeo_tutor/errors',
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/logger'
], function(str, Network, errors, constants, Logger) {
    'use strict';

    const log = Logger.namespace('ChatAPI');

    /**
     * Call a web service once and map its failure onto the tutor error classes.
     *
     * Never resends. A lost connection becomes a TimeoutError (no answer in time) or a
     * NetworkError, both carrying the localised network message; a server error becomes an
     * APIError carrying the server message and errorcode.
     *
     * @param {string} methodname Full web service name.
     * @param {object} args Arguments.
     * @param {number} [timeout] Milliseconds; defaults to constants.network.AJAX_TIMEOUT.
     * @returns {Promise<*>}
     * @throws {NetworkError|TimeoutError|APIError}
     */
    const callService = async(methodname, args, timeout = constants.network.AJAX_TIMEOUT) => {
        try {
            return await Network.call({methodname: methodname, args: args}, {timeout: timeout});
        } catch (failure) {
            if (failure instanceof errors.TutorError) {
                throw failure;
            }
            const details = {originalError: failure, methodname};
            if (Network.isConnectionError(failure)) {
                const message = await str.get_string('error_network', 'block_dixeo_tutor').catch(() => '');
                if (failure.reason === 'timeout') {
                    throw new errors.TimeoutError(message, timeout, details);
                }
                throw new errors.NetworkError(message, details);
            }
            const apiError = new errors.APIError(failure.message || '', failure.errorcode || 'UNKNOWN', details);
            apiError.errorcode = failure.errorcode;
            throw apiError;
        }
    };

    /**
     * Whether a tutor error comes from the connection rather than from the server.
     *
     * @param {*} failure
     * @returns {boolean}
     */
    const isConnectionFailure = (failure) => failure instanceof errors.NetworkError ||
        failure instanceof errors.TimeoutError;

    /**
     * Call a tutor web service.
     *
     * @param {string} methodname The method name without the block_dixeo_tutor_ prefix.
     * @param {object} args The arguments for the API method.
     * @param {number} [timeout] Milliseconds; defaults to constants.network.AJAX_TIMEOUT.
     * @returns {Promise<*>}
     */
    const callAjax = async(methodname, args, timeout = constants.network.AJAX_TIMEOUT) => {
        const result = await callService(`block_dixeo_tutor_${methodname}`, args, timeout);
        if (result && result.error) {
            throw new errors.APIError(
                result.message || 'API request failed',
                result.errorcode || 'UNKNOWN',
                {methodname, args}
            );
        }
        return result;
    };

    const ChatAPI = class {

        /**
         * Loads conversation history.
         * @param {number} courseid The ID of the course.
         * @param {string|null} sinceid Optional message ID cursor for newer messages.
         * @param {number|null} offset Optional offset for loading older message pages.
         * @param {boolean} [status=false] True when the read polls or probes the connection.
         * @returns {Promise<object>} Conversation data.
         * @throws {NetworkError|APIError|ValidationError}
         */
        async loadConversation(courseid, sinceid = null, offset = null, status = false) {
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
                const result = await callAjax(
                    'get_conversation',
                    args,
                    status ? Network.TIMEOUT_STATUS_MS : constants.network.AJAX_TIMEOUT
                );

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

        /**
         * Erases the current user's conversation in a course.
         * @param {number} courseid The ID of the course.
         * @returns {Promise<object>} Result with the number of conversations deleted.
         * @throws {NetworkError|APIError|ValidationError}
         */
        async deleteConversation(courseid) {
            if (!courseid || courseid <= 0) {
                throw new errors.ValidationError(
                    'Invalid course ID',
                    'courseid',
                    {courseid}
                );
            }

            try {
                return await callAjax('delete_conversation', {courseid});
            } catch (error) {
                log.error('Failed to delete conversation', {
                    error: error.message,
                    code: error.code
                });
                throw error;
            }
        }

        /**
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
                return await callAjax('get_job_status', {courseid: courseid, jobid: jobId}, Network.TIMEOUT_STATUS_MS);
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
         *
         * Submits a paid reply job when context is queued, so it is sent once and never retried.
         *
         * @param {number} courseid The ID of the course.
         * @param {string} pageurl The current page URL for context.
         * @param {number} [cmid=0] Course module id when on an activity page.
         * @returns {Promise<object>} Flush result; flushed=true when a job was submitted.
         */
        async flushPendingContext(courseid, pageurl = '', cmid = 0) {
            if (!courseid || courseid <= 0) {
                throw new errors.ValidationError(
                    'Invalid course ID',
                    'courseid',
                    {courseid}
                );
            }

            try {
                // The handler waits for the course file sync before it submits the reply job: sent once,
                // no client timeout.
                const result = await callAjax(
                    'flush_pending_context',
                    {courseid, pageurl, cmid: cmid || 0},
                    Network.TIMEOUT_NONE
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
         * @param {number} [cmid=0] Course module id when on an activity page.
         * @param {object|null} [guideSession=null] Active guide session context from client storage.
         * @returns {Promise<object>} Send result with job_id.
         * @throws {NetworkError|APIError|ValidationError|TimeoutError}
         */
        async sendMessage(courseid, message, pageurl = '', cmid = 0, guideSession = null) {
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
                const args = {
                    courseid,
                    message: message.trim(),
                    pageurl,
                    cmid: cmid || 0,
                };
                if (guideSession && guideSession.title && guideSession.description) {
                    args.guidetitle = guideSession.title;
                    args.guidedescription = guideSession.description;
                }
                // The handler waits for the course file sync before it submits the job: sent once, no client
                // timeout. A lost send is followed through the conversation, never resent.
                const result = await callAjax('send_message', args, Network.TIMEOUT_NONE);

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

            return callAjax('mark_messages_read', args, Network.TIMEOUT_STATUS_MS);
        }
    };

    ChatAPI.callService = callService;
    ChatAPI.isConnectionFailure = isConnectionFailure;

    return ChatAPI;
});
