define([
    'core/ajax',
], function(ajax) {
    'use strict';

    const call = function(methodname, args) {
        return ajax.call([{
            methodname: 'block_dixeo_tutor_' + methodname,
            args: args,
        }])[0];
    };

    return {
        startGuideSession: function(courseid, userprompt, pageurl, cmid) {
            return call('start_guide_session', {
                courseid: courseid,
                userprompt: userprompt,
                pageurl: pageurl || '',
                cmid: cmid || 0,
            });
        },
    };
});
