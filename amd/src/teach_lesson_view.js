define([
    'core/templates',
    'core/modal',
    'core/modal_events',
    'core/str',
    'block_dixeo_tutor/custom_lesson_panel',
    'block_dixeo_tutor/tts_player',
], function(Templates, Modal, ModalEvents, str, customLessonPanel, ttsPlayer) {
    'use strict';

    let activeModal = null;
    /** @type {HTMLElement|null} */
    let panelTtsButton = null;

    /**
     * Build modal body HTML from lesson data.
     *
     * @param {Object} lesson
     * @return {string}
     */
    const buildModalBodyHtml = function(lesson) {
        const parts = [];
        if (lesson.introhtml) {
            parts.push('<div class="dixeo-teach-lesson__intro">', lesson.introhtml, '</div>');
        }
        parts.push('<div class="dixeo-teach-lesson-modal__body">', lesson.contenthtml || '', '</div>');
        return parts.join('');
    };

    /**
     * Open a large modal with the lesson content.
     *
     * @param {Object} lesson {title, introhtml, contenthtml}
     * @return {Promise<void>}
     */
    const openFullscreenModal = function(lesson) {
        if (activeModal) {
            activeModal.destroy();
            activeModal = null;
        }

        return Modal.create({
            type: Modal.TYPE.DEFAULT,
            title: lesson.title || '',
            body: buildModalBodyHtml(lesson),
        }).then(function(modal) {
            activeModal = modal;
            modal.setLarge();
            modal.show();
            modal.getRoot().on(ModalEvents.hidden, function() {
                if (activeModal === modal) {
                    activeModal = null;
                }
                modal.destroy();
            });
            return undefined;
        });
    };

    /**
     * Wire TTS control on the lesson panel header.
     *
     * @param {HTMLElement} panel
     * @param {Object} lesson
     * @return {Promise<void>}
     */
    const wireTts = function(panel, lesson) {
        const ttsBtn = panel.querySelector('[data-action="tts"]');
        panelTtsButton = ttsBtn || null;
        if (!ttsBtn || !ttsPlayer.isSupported()) {
            if (ttsBtn) {
                ttsBtn.classList.add('d-none');
            }
            return Promise.resolve();
        }

        const data = customLessonPanel.lessonFromPayload(lesson);
        const groupId = customLessonPanel.lessonTtsGroupId(data);

        return str.get_strings([
            {key: 'teach_lesson_tts_play', component: 'block_dixeo_tutor'},
            {key: 'teach_lesson_tts_stop', component: 'block_dixeo_tutor'},
        ]).then(function(strings) {
            ttsPlayer.wireButton(ttsBtn, function() {
                return customLessonPanel.ttsText(data);
            }, {
                play: strings[0],
                stop: strings[1],
            }, {
                groupId: groupId,
            });
            return undefined;
        }).catch(function() {
            ttsPlayer.wireButton(ttsBtn, function() {
                return customLessonPanel.ttsText(data);
            }, null, {
                groupId: groupId,
            });
        });
    };

    /**
     * Render lesson panel and wire actions.
     *
     * @param {HTMLElement} container
     * @param {Object} lesson {title, introhtml, contenthtml}
     * @param {Function} onClose
     * @return {Promise<void>}
     */
    const mountLesson = function(container, lesson, onClose) {
        const context = {
            title: lesson.title || '',
            introhtml: lesson.introhtml || '',
            contenthtml: lesson.contenthtml || '',
            hasintro: !!(lesson.introhtml && lesson.introhtml.trim()),
            hastts: ttsPlayer.isSupported(),
        };

        let templateJs = '';
        return Templates.render('block_dixeo_tutor/teach_lesson', context).then(function(html, js) {
            templateJs = js;
            container.innerHTML = html;
            const panel = container.querySelector('.dixeo-teach-lesson');
            if (!panel) {
                throw new Error('Teach lesson panel element missing after template render');
            }

            panel.querySelector('[data-action="close"]').addEventListener('click', onClose);
            panel.querySelector('[data-action="fullscreen"]').addEventListener('click', function() {
                openFullscreenModal(lesson);
            });

            return wireTts(panel, lesson);
        }).then(function() {
            return Templates.runTemplateJS(templateJs);
        });
    };

    /**
     * Destroy any open fullscreen modal.
     */
    const destroy = function() {
        if (activeModal) {
            activeModal.destroy();
            activeModal = null;
        }
        if (panelTtsButton) {
            ttsPlayer.unregisterButton(panelTtsButton);
            panelTtsButton = null;
        }
    };

    return {
        mountLesson: mountLesson,
        openFullscreenModal: openFullscreenModal,
        destroy: destroy,
    };
});
