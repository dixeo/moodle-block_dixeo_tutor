define([
    'core/templates',
    'core/str',
    'block_dixeo_tutor/text_utils',
    'block_dixeo_tutor/tts_player',
], function(Templates, str, textUtils, ttsPlayer) {
    'use strict';

    /** Must match {@see \block_dixeo_tutor\service\tutor_context_schema::SCHEMA_CUSTOM_LESSON}. */
    const SCHEMA_CUSTOM_LESSON = 'custom_lesson';
    const OPTIMISTIC_ID_PREFIX = 'temp-custom-lesson-';

    const STRING_KEYS = [
        'custom_lesson_label',
        'custom_lesson_view',
        'teach_lesson_tts_play',
        'teach_lesson_tts_stop',
    ];

    let stringsPromise = null;
    let templatePrefetched = false;
    let cachedStrings = null;
    /** @type {{teachController: object|null}} */
    let actionDeps = {teachController: null};

    /**
     * Preload panel strings and Mustache template.
     */
    function preload() {
        if (!stringsPromise) {
            stringsPromise = str.get_strings(
                STRING_KEYS.map(function(key) {
                    return {key: key, component: 'block_dixeo_tutor'};
                })
            ).then(function(values) {
                const map = {};
                STRING_KEYS.forEach(function(key, index) {
                    map[key] = values[index];
                });
                cachedStrings = map;
                return map;
            }).catch(function() {
                cachedStrings = {
                    'custom_lesson_label': 'Custom lesson',
                    'custom_lesson_view': 'View lesson',
                    'teach_lesson_tts_play': 'Read lesson aloud',
                    'teach_lesson_tts_stop': 'Stop reading',
                };
                return cachedStrings;
            });
        }
        if (!templatePrefetched) {
            templatePrefetched = true;
            Templates.prefetchTemplates(['block_dixeo_tutor/custom_lesson_panel']);
        }
        return stringsPromise;
    }

    /**
     * @param {object} message
     * @returns {string}
     */
    function contextSchema(message) {
        if (!message) {
            return '';
        }
        const ctx = message.context;
        if (typeof ctx === 'object' && ctx !== null && ctx.schema) {
            return String(ctx.schema);
        }
        return '';
    }

    /**
     * @param {object} message
     * @returns {boolean}
     */
    function isCustomLessonMessage(message) {
        if (!message) {
            return false;
        }
        return String(message.role || '').toLowerCase() === 'system'
            && contextSchema(message) === SCHEMA_CUSTOM_LESSON;
    }

    /**
     * @param {object} message
     * @returns {object|null}
     */
    function getLessonData(message) {
        if (!isCustomLessonMessage(message)) {
            return null;
        }
        const ctx = message.context;
        if (!ctx) {
            return null;
        }
        if (typeof ctx === 'string') {
            try {
                const parsed = JSON.parse(ctx);
                return parsed && parsed.schema === SCHEMA_CUSTOM_LESSON ? parsed : null;
            } catch (e) {
                return null;
            }
        }
        if (ctx.schema === SCHEMA_CUSTOM_LESSON) {
            return ctx;
        }
        return null;
    }

    /**
     * @param {object} message
     * @returns {{version: number, data: object}|null}
     */
    function parseCustomLessonMessage(message) {
        const data = getLessonData(message);
        if (!data) {
            return null;
        }
        return {
            version: data.version || 1,
            data: data,
        };
    }

    /**
     * @param {object} lesson
     * @returns {object}
     */
    function lessonFromPayload(lesson) {
        return {
            schema: SCHEMA_CUSTOM_LESSON,
            version: 1,
            title: lesson.title || '',
            introhtml: lesson.introhtml || '',
            contenthtml: lesson.contenthtml || '',
        };
    }

    /**
     * @param {object} data Parsed lesson context.
     * @returns {string}
     */
    function previewText(data) {
        const plain = textUtils.htmlToPlain(data.contenthtml || '');
        return textUtils.truncateAtWordBoundary(plain, 200);
    }

    /**
     * @param {object} data Parsed lesson context.
     * @returns {string}
     */
    function ttsText(data) {
        const intro = textUtils.htmlToPlain(data.introhtml || '');
        const content = textUtils.htmlToPlain(data.contenthtml || '');
        return [intro, content].filter(Boolean).join(' ').trim();
    }

    /**
     * Stable TTS group id so card and lesson panel share playback state.
     *
     * @param {object} data Parsed lesson context.
     * @returns {string}
     */
    function lessonTtsGroupId(data) {
        const text = ttsText(data);
        return 'custom-lesson:' + text.length + ':' + text.substring(0, 200);
    }

    /**
     * @param {object} data Parsed lesson context.
     * @param {object} [strings]
     * @returns {object}
     */
    function buildTemplateContext(data, strings) {
        const labels = strings || cachedStrings || {};
        return {
            version: data.version || 1,
            label: labels.custom_lesson_label || 'Custom lesson',
            title: data.title || '',
            preview: previewText(data),
            viewLabel: labels.custom_lesson_view || 'View lesson',
            ttssupported: ttsPlayer.isSupported(),
            ttsPlayLabel: labels.teach_lesson_tts_play || 'Read lesson aloud',
            ttsStopLabel: labels.teach_lesson_tts_stop || 'Stop reading',
        };
    }

    /**
     * @param {HTMLElement} panelEl
     * @param {object} data Parsed lesson context.
     * @param {object} [strings]
     */
    function wirePanelActions(panelEl, data, strings) {
        if (!panelEl || !data) {
            return;
        }

        const labels = strings || cachedStrings || {};
        const ttsLabels = {
            play: labels.teach_lesson_tts_play || 'Read lesson aloud',
            stop: labels.teach_lesson_tts_stop || 'Stop reading',
        };

        const ttsBtn = panelEl.querySelector('[data-action="tts"]');
        if (ttsBtn) {
            ttsPlayer.wireButton(ttsBtn, function() {
                return ttsText(data);
            }, ttsLabels, {
                groupId: lessonTtsGroupId(data),
            });
        }

        const viewBtn = panelEl.querySelector('[data-action="view-lesson"]');
        if (viewBtn) {
            viewBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const controller = actionDeps.teachController;
                if (controller && typeof controller.openLessonFromContext === 'function') {
                    controller.openLessonFromContext({
                        title: data.title || '',
                        introhtml: data.introhtml || '',
                        contenthtml: data.contenthtml || '',
                    });
                }
            });
        }
    }

    /**
     * @param {Object} ui ChatUI
     */
    function removeOptimisticMessage(ui) {
        if (!ui || !ui.dom || !ui.dom.messagesContainer) {
            return;
        }
        ui.dom.messagesContainer.querySelectorAll('.dixeo-tutor-message-row--custom-lesson').forEach(function(row) {
            const mid = row.dataset.mid || '';
            if (mid.indexOf(OPTIMISTIC_ID_PREFIX) === 0) {
                row.remove();
            }
        });
    }

    /**
     * @param {Object} ui ChatUI
     * @param {object} lesson Finalized lesson payload.
     * @returns {string|null}
     */
    function appendOptimisticMessage(ui, lesson) {
        const data = lessonFromPayload(lesson);
        if (!data.contenthtml || !ui || typeof ui.appendMessage !== 'function') {
            return null;
        }
        removeOptimisticMessage(ui);
        const tempId = OPTIMISTIC_ID_PREFIX + Date.now();
        ui.appendMessage({
            id: tempId,
            role: 'system',
            context: data,
            content: data.title || '',
            time: Math.floor(Date.now() / 1000),
        });
        return tempId;
    }

    /**
     * @param {HTMLElement} contentEl
     * @param {object} data Parsed lesson context.
     */
    function renderInto(contentEl, data) {
        if (!contentEl || !data) {
            return;
        }
        contentEl.dataset.raw = JSON.stringify(data);

        const fallbackTitle = textUtils.escapeHtml(data.title || 'Custom lesson');
        const fallbackPreview = textUtils.escapeHtml(previewText(data));
        contentEl.innerHTML =
            '<div class="dixeo-custom-lesson-panel-fallback">' +
            '<strong>' + fallbackTitle + '</strong><br>' + fallbackPreview +
            '</div>';

        preload().then(function(strings) {
            const context = buildTemplateContext(data, strings);
            return Templates.render('block_dixeo_tutor/custom_lesson_panel', context);
        }).then(function(html, js) {
            if (html) {
                contentEl.innerHTML = html;
                const panel = contentEl.querySelector('.dixeo-custom-lesson-panel');
                if (panel) {
                    wirePanelActions(panel, data, cachedStrings);
                }
            }
            if (js) {
                Templates.runTemplateJS(js);
            }
            return undefined;
        }).catch(function() {
            // Keep sync fallback.
        });
    }

    /**
     * @param {object} msg Message object.
     * @param {{version: number, data: object}} parsed Parsed lesson.
     * @param {object} uiStrings Chat UI aria strings.
     * @returns {HTMLElement}
     */
    function createMessageNode(msg, parsed, uiStrings) {
        const time = new Date((msg.time || 0) * 1000).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        const alignCls = (msg.role === 'user' || contextSchema(msg) === SCHEMA_CUSTOM_LESSON)
            ? 'd-flex justify-content-end'
            : 'd-flex justify-content-start';
        const ariaLabel = (msg.role === 'user' || contextSchema(msg) === SCHEMA_CUSTOM_LESSON)
            ? (uiStrings.yourMessage || 'Your message')
            : (uiStrings.assistantMessage || 'Assistant message');

        const row = document.createElement('div');
        row.className = alignCls + ' mb-2 dixeo-tutor-message-row dixeo-tutor-message-row--custom-lesson';
        if (msg.id) {
            row.dataset.mid = String(msg.id);
        }
        row.innerHTML =
            '<div class="dixeo-tutor-message dixeo-tutor-message-user dixeo-tutor-message--custom-lesson"' +
            ' role="article" aria-label="' + textUtils.escapeHtml(ariaLabel) + '" tabindex="0">' +
            '<div class="dixeo-tutor-message-content"></div>' +
            '<div class="dixeo-tutor-message-footer">' +
            '<small class="message-time" aria-label="Sent at ' + textUtils.escapeHtml(time) + '">' +
            textUtils.escapeHtml(time) + '</small>' +
            '</div></div>';

        const contentEl = row.querySelector('.dixeo-tutor-message-content');
        contentEl.style.background = '#f8f9fa';
        contentEl.style.color = '#212529';
        renderInto(contentEl, parsed.data);

        return row;
    }

    /**
     * @param {{teachController: object|null}} deps
     */
    function wireActions(deps) {
        actionDeps = deps || {teachController: null};
    }

    preload();

    return {
        SCHEMA_CUSTOM_LESSON,
        isCustomLessonMessage,
        getLessonData,
        parseCustomLessonMessage,
        lessonFromPayload,
        previewText,
        ttsText,
        lessonTtsGroupId,
        removeOptimisticMessage,
        appendOptimisticMessage,
        renderInto,
        createMessageNode,
        wirePanelActions,
        wireActions,
        preload,
    };
});
