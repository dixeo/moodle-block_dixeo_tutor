define([
    'core/templates',
    'core/str',
    'block_dixeo_tutor/text_utils',
], function(Templates, str, textUtils) {
    'use strict';

    let templatePrefetched = false;
    let reviewHandler = null;
    let restartHandler = null;

    /**
     * Prefetch guide topic panel template.
     */
    function preload() {
        if (!templatePrefetched) {
            templatePrefetched = true;
            Templates.prefetchTemplates(['block_dixeo_tutor/guide_topic_panel']);
        }
    }

    /**
     * @param {Function|null} handler
     */
    function setReviewHandler(handler) {
        reviewHandler = typeof handler === 'function' ? handler : null;
    }

    /**
     * @param {Function|null} handler
     */
    function setRestartHandler(handler) {
        restartHandler = typeof handler === 'function' ? handler : null;
    }

    /**
     * @param {object} data
     * @returns {object}
     */
    function sessionPayload(data) {
        return {
            title: data.title || '',
            description: data.description || '',
            startedAt: data.startedAt || 0,
            timeEnded: data.timeEnded || 0,
        };
    }

    /**
     * @param {HTMLElement} contentEl
     * @param {object} data
     */
    function renderInto(contentEl, data) {
        if (!contentEl || !data) {
            return;
        }
        const fallbackTitle = textUtils.escapeHtml(data.title || '');
        const fallbackDesc = textUtils.escapeHtml(data.description || '');
        contentEl.innerHTML =
            '<div class="dixeo-guide-topic-panel-fallback">' +
            '<strong>' + fallbackTitle + '</strong><br>' + fallbackDesc +
            '</div>';

        Promise.all([
            str.get_string('guide_topic_label', 'block_dixeo_tutor'),
            str.get_string('guide_review_session', 'block_dixeo_tutor'),
            str.get_string('guide_review_back', 'block_dixeo_tutor'),
            str.get_string('guide_completion_restart', 'block_dixeo_tutor'),
        ]).then(function(labels) {
            return Templates.render('block_dixeo_tutor/guide_topic_panel', {
                label: labels[0],
                title: data.title || '',
                description: data.description || '',
                startedat: data.startedAt || 0,
                timeended: data.timeEnded || 0,
                reviewlabel: labels[1],
                backlabel: labels[2],
                restartlabel: labels[3],
            });
        }).then(function(html, js) {
            if (html) {
                contentEl.innerHTML = html;
            }
            if (js) {
                Templates.runTemplateJS(js);
            }
            wireCardActions(contentEl, data);
            return undefined;
        }).catch(function() {
            // Keep sync fallback.
        });
    }

    /**
     * @param {HTMLElement} contentEl
     * @param {object} data
     */
    function wireCardActions(contentEl, data) {
        const reviewBtn = contentEl.querySelector('[data-action="review-guide-session"]');
        if (reviewBtn) {
            reviewBtn.addEventListener('click', function() {
                if (!reviewHandler) {
                    return;
                }
                reviewHandler(sessionPayload(data), reviewBtn);
            });
        }

        const restartBtn = contentEl.querySelector('[data-action="restart-guide-session"]');
        if (restartBtn) {
            restartBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!restartHandler) {
                    return;
                }
                restartHandler(sessionPayload(data));
            });
        }
    }

    /**
     * @param {object} data
     * @returns {HTMLElement}
     */
    function createCardNode(data) {
        const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        const row = document.createElement('div');
        row.className = 'd-flex justify-content-end mb-2 dixeo-tutor-message-row dixeo-tutor-message-row--guide-topic';
        row.dataset.lane = 'guide-summary';
        row.dataset.guideTitle = data.title || '';
        row.dataset.guideDescription = data.description || '';
        if (data.startedAt) {
            row.dataset.guideStarted = String(data.startedAt);
        }
        if (data.timeEnded) {
            row.dataset.guideEnded = String(data.timeEnded);
        }
        row.innerHTML =
            '<div class="dixeo-tutor-message dixeo-tutor-message-user dixeo-tutor-message--guide-topic"' +
            ' role="article" aria-label="Guide topic" tabindex="0">' +
            '<div class="dixeo-tutor-message-content"></div>' +
            '<div class="dixeo-tutor-message-footer">' +
            '<small class="message-time" aria-label="Sent at ' + textUtils.escapeHtml(time) + '">' +
            textUtils.escapeHtml(time) + '</small></div></div>';

        renderInto(row.querySelector('.dixeo-tutor-message-content'), data);
        const contentEl = row.querySelector('.dixeo-tutor-message-content');
        if (contentEl) {
            contentEl.style.background = '#f8f9fa';
            contentEl.style.color = '#212529';
        }
        return row;
    }

    /**
     * Insert a client-only guide topic summary card (e.g. on session end).
     *
     * @param {HTMLElement} container
     * @param {object} data
     * @returns {HTMLElement|null}
     */
    function insertCard(container, data) {
        if (!container || !data) {
            return null;
        }
        const node = createCardNode(data);
        container.appendChild(node);
        return node;
    }

    preload();

    return {
        createCardNode,
        insertCard,
        preload,
        setReviewHandler,
        setRestartHandler,
    };
});
