define([
    'block_dixeo_tutor/generation_api',
], function(generationApi) {
    'use strict';

    return Object.assign({}, generationApi, {
        submitPracticeQuiz: function(courseid, params) {
            return generationApi.callAjax('block_dixeo_tutor_submit_practice_quiz', Object.assign({courseid: courseid}, params));
        },

        finalizePracticeQuiz: function(courseid, jobid, topictitle, expectedcount) {
            return generationApi.callAjax('block_dixeo_tutor_finalize_practice_quiz', {
                courseid: courseid,
                jobid: jobid,
                topictitle: topictitle || '',
                expectedcount: expectedcount || 0,
            });
        },

        renderEmbed: function(courseid, questions, title) {
            return generationApi.callAjax('mod_simplequiz2_render_embed', {
                courseid: courseid,
                questions: questions,
                title: title || '',
            });
        },

        submitQuizReview: function(courseid, params) {
            return generationApi.callAjax('block_dixeo_tutor_submit_quiz_context', Object.assign({courseid: courseid}, params));
        },
    });
});
