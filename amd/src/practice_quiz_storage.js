define([
    'block_dixeo_tutor/tutor_session_storage',
], function(sessionStorage) {
    'use strict';

    return {
        load: function(userid, courseid) {
            return sessionStorage.load('quiz', userid, courseid);
        },
        save: function(userid, courseid, data) {
            sessionStorage.save('quiz', userid, courseid, data);
        },
        clear: function(userid, courseid) {
            sessionStorage.clear('quiz', userid, courseid);
        },
    };
});
