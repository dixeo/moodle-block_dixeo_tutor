define([
    'local_dixeo/network',
    'block_dixeo_tutor/chat_api',
    'block_dixeo_tutor/constants',
], function(Network, ChatAPI, constants) {
    'use strict';

    /**
     * Call a tutor web service once, with the tutor error mapping.
     *
     * @param {string} methodname The method name without the block_dixeo_tutor_ prefix.
     * @param {object} args Arguments.
     * @param {number} [timeout] Milliseconds; defaults to constants.network.AJAX_TIMEOUT.
     * @returns {Promise<*>}
     */
    const call = function(methodname, args, timeout = constants.network.AJAX_TIMEOUT) {
        return ChatAPI.callService('block_dixeo_tutor_' + methodname, args, timeout);
    };

    return {
        startGuideSession: function(courseid, userprompt, pageurl, cmid) {
            // The handler waits for the course file sync before it submits the job: sent once, no client timeout.
            return call('start_guide_session', {
                courseid: courseid,
                userprompt: userprompt,
                pageurl: pageurl || '',
                cmid: cmid || 0,
            }, Network.TIMEOUT_NONE);
        },
    };
});
