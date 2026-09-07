define([
    'block_dixeo_tutor/generation_api',
], function(generationApi) {
    'use strict';

    return Object.assign({}, generationApi, {
        submitTeachLesson: function(courseid, params) {
            return generationApi.callAjax('block_dixeo_tutor_submit_teach_lesson', Object.assign({courseid: courseid}, params));
        },

        finalizeTeachLesson: function(courseid, jobid, topictitle) {
            return generationApi.callAjax('block_dixeo_tutor_finalize_teach_lesson', {
                courseid: courseid,
                jobid: jobid,
                topictitle: topictitle || '',
            });
        },

        submitTeachLessonContext: function(courseid, params) {
            return generationApi.callAjax(
                'block_dixeo_tutor_submit_teach_lesson_context',
                Object.assign({courseid: courseid}, params)
            );
        },
    });
});
