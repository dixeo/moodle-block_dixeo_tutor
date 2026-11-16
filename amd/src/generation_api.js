define([
    'local_dixeo/network',
    'block_dixeo_tutor/chat_api',
    'block_dixeo_tutor/constants',
], function(Network, ChatAPI, constants) {
    'use strict';

    /**
     * Call a web service once, with the tutor error mapping.
     *
     * Job starts (submit and finalize calls) go through here and are never resent.
     *
     * @param {string} methodname Full web service name.
     * @param {object} args Arguments.
     * @param {number} [timeout] Milliseconds; defaults to constants.network.AJAX_TIMEOUT.
     * @returns {Promise<*>}
     */
    const callAjax = function(methodname, args, timeout = constants.network.AJAX_TIMEOUT) {
        return ChatAPI.callService(methodname, args, timeout);
    };

    return {
        callAjax: callAjax,

        loadHierarchy: function(courseid) {
            return Network.retryRead(() => callAjax(
                'block_dixeo_tutor_get_course_hierarchy',
                {courseid: courseid},
                Network.TIMEOUT_STATUS_MS
            ).catch((failure) => {
                // Let retryRead see the connection failure behind the tutor error.
                throw ChatAPI.isConnectionFailure(failure) ? Network.connectionError(failure) : failure;
            })).catch((failure) => {
                throw failure && failure.connection === true ? failure.cause : failure;
            });
        },

        pollJobStatus: function(courseid, jobid) {
            return callAjax('block_dixeo_tutor_get_job_status', {
                courseid: courseid,
                jobid: jobid,
            }, Network.TIMEOUT_STATUS_MS);
        },

        cancelGenerationJob: function(courseid, jobid) {
            return callAjax('block_dixeo_tutor_cancel_generation_job', {
                courseid: courseid,
                jobid: jobid,
            });
        },

        loadConversation: function(courseid, sinceid) {
            const args = {courseid: courseid};
            if (sinceid) {
                args.sinceid = sinceid;
            }
            return callAjax('block_dixeo_tutor_get_conversation', args, Network.TIMEOUT_STATUS_MS).then(function(result) {
                if (result && Array.isArray(result.messages)) {
                    result.messages = result.messages.map(function(msg) {
                        const normalized = Object.assign({}, msg);
                        if (typeof normalized.context === 'string' && normalized.context !== '') {
                            try {
                                normalized.context = JSON.parse(normalized.context);
                            } catch (e) {
                                // Keep raw string when malformed.
                            }
                        }
                        return normalized;
                    });
                }
                return result;
            });
        },
    };
});
