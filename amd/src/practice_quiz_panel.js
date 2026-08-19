define([
    'core/templates',
    'core/str',
], function(Templates, str) {
    'use strict';

    /** @type {HTMLElement|null} */
    let panelEl = null;
    /** @type {HTMLElement|null} */
    let overlayEl = null;
    /** @type {HTMLElement|null} */
    let embedSlot = null;
    /** @type {Function|null} */
    let onEscape = null;

    /**
     * Whether the quiz embed is currently in the large overlay.
     *
     * @return {boolean}
     */
    const isFullscreen = function() {
        return !!(overlayEl && overlayEl.parentNode);
    };

    /**
     * Move the live embed node back into the tutor panel.
     * Does not clone or re-init the player.
     */
    const closeFullscreen = function() {
        if (!isFullscreen()) {
            return;
        }
        const embed = overlayEl.querySelector('.simplequiz2-embed');
        if (embed && embedSlot && embedSlot.parentNode) {
            embedSlot.parentNode.insertBefore(embed, embedSlot);
            embedSlot.remove();
        } else if (embed && panelEl) {
            const body = panelEl.querySelector('.dixeo-practice-quiz-panel__body');
            if (body) {
                body.appendChild(embed);
            }
        }
        embedSlot = null;
        overlayEl.remove();
        overlayEl = null;
        if (onEscape) {
            document.removeEventListener('keydown', onEscape);
            onEscape = null;
        }
    };

    /**
     * Show the existing quiz embed in a larger overlay (same DOM node).
     *
     * @param {string} title
     */
    const openFullscreen = function(title) {
        if (isFullscreen() || !panelEl) {
            return;
        }
        const embed = panelEl.querySelector('.simplequiz2-embed');
        if (!embed) {
            return;
        }

        embedSlot = document.createElement('div');
        embedSlot.className = 'dixeo-practice-quiz-panel__embed-slot';
        embed.parentNode.insertBefore(embedSlot, embed);

        overlayEl = document.createElement('div');
        overlayEl.className = 'dixeo-practice-quiz-fullscreen';
        overlayEl.setAttribute('role', 'dialog');
        overlayEl.setAttribute('aria-modal', 'true');

        const dialog = document.createElement('div');
        dialog.className = 'dixeo-practice-quiz-fullscreen__dialog';

        const header = document.createElement('div');
        header.className = 'dixeo-practice-quiz-panel__header dixeo-practice-quiz-fullscreen__header';

        const heading = document.createElement('h5');
        heading.className = 'dixeo-practice-quiz-panel__title mb-0';
        heading.textContent = title || '';

        const actions = document.createElement('div');
        actions.className = 'dixeo-practice-quiz-panel__header-actions';

        const closeFsBtn = document.createElement('button');
        closeFsBtn.type = 'button';
        closeFsBtn.className = 'btn btn-sm btn-outline-secondary';
        closeFsBtn.innerHTML = '<i class="fa fa-compress" aria-hidden="true"></i>';
        closeFsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            closeFullscreen();
        });

        actions.appendChild(closeFsBtn);
        header.appendChild(heading);
        header.appendChild(actions);

        const body = document.createElement('div');
        body.className = 'dixeo-practice-quiz-fullscreen__body';
        body.appendChild(embed);

        dialog.appendChild(header);
        dialog.appendChild(body);
        overlayEl.appendChild(dialog);
        overlayEl.addEventListener('click', function(e) {
            if (e.target === overlayEl) {
                closeFullscreen();
            }
        });
        document.body.appendChild(overlayEl);

        onEscape = function(e) {
            if (e.key === 'Escape') {
                closeFullscreen();
            }
        };
        document.addEventListener('keydown', onEscape);

        str.get_string('quiz_panel_exit_fullscreen', 'block_dixeo_tutor').then(function(label) {
            closeFsBtn.setAttribute('title', label);
            closeFsBtn.setAttribute('aria-label', label);
            return undefined;
        }).catch(function() {
            closeFsBtn.setAttribute('title', 'Exit full screen');
            closeFsBtn.setAttribute('aria-label', 'Exit full screen');
        });

        closeFsBtn.focus();
    };

    /**
     * Wrap embed HTML in the quiz chrome and wire header actions.
     *
     * @param {HTMLElement} container Quiz pane.
     * @param {string} title
     * @param {string} embedHtml Server-rendered player markup.
     * @param {Function} onClose Exit quiz (not fullscreen).
     * @return {Promise<HTMLElement>} The .simplequiz2-embed root for embedPlayer.init.
     */
    const mount = function(container, title, embedHtml, onClose) {
        destroy();
        return Templates.render('block_dixeo_tutor/practice_quiz_panel', {
            title: title || '',
        }).then(function(html) {
            container.innerHTML = html;
            panelEl = container.querySelector('.dixeo-practice-quiz-panel');
            if (!panelEl) {
                throw new Error('Practice quiz panel element missing after template render');
            }
            const body = panelEl.querySelector('.dixeo-practice-quiz-panel__body');
            body.innerHTML = embedHtml;

            panelEl.querySelector('[data-action="close"]').addEventListener('click', onClose);
            panelEl.querySelector('[data-action="fullscreen"]').addEventListener('click', function() {
                openFullscreen(title || '');
            });

            return panelEl.querySelector('.simplequiz2-embed');
        });
    };

    /**
     * Leave fullscreen and drop panel references. Does not destroy the embed player.
     */
    const destroy = function() {
        closeFullscreen();
        panelEl = null;
    };

    return {
        mount: mount,
        destroy: destroy,
        closeFullscreen: closeFullscreen,
    };
});
