define([
    'core/ajax',
    'block_dixeo_tutor/constants',
], function(ajax, constants) {
    'use strict';

    const callAjax = async function(methodname, args) {
        const request = {
            methodname: methodname,
            args: args,
            timeout: constants.network.AJAX_TIMEOUT,
        };
        return ajax.call([request])[0];
    };

    return {
        callAjax: callAjax,

        loadHierarchy: function(courseid) {
            return callAjax('block_dixeo_tutor_get_course_hierarchy', {courseid: courseid});
        },

        pollJobStatus: function(courseid, jobid) {
            return callAjax('block_dixeo_tutor_get_job_status', {
                courseid: courseid,
                jobid: jobid,
            });
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
            return callAjax('block_dixeo_tutor_get_conversation', args).then(function(result) {
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
