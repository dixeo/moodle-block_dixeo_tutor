define([
    'block_dixeo_tutor/tts_player'
], function(ttsPlayer) {
    'use strict';

    /**
     * Copy text to the clipboard.
     *
     * @param {string} text
     * @returns {Promise<void>}
     */
    const copyToClipboard = function(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function(resolve, reject) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                const ok = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (ok) {
                    resolve();
                } else {
                    reject(new Error('Copy failed'));
                }
            } catch (err) {
                document.body.removeChild(textarea);
                reject(err);
            }
        });
    };

    /**
     * Ensure the actions container exists in the message footer.
     *
     * @param {HTMLElement} footer
     * @returns {HTMLElement|null}
     */
    const ensureActionsContainer = function(footer) {
        let actions = footer.querySelector('.dixeo-tutor-message-actions');
        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'dixeo-tutor-message-actions';
            footer.insertBefore(actions, footer.firstChild);
        }
        return actions;
    };

    /**
     * Attach copy and TTS controls to a chat message bubble.
     *
     * @param {HTMLElement} bubbleEl The .dixeo-tutor-message element.
     * @param {{copy: string, copied: string, play: string, stop: string}} [labels]
     */
    const attach = function(bubbleEl, labels) {
        if (!bubbleEl) {
            return;
        }

        const ariaLabels = Object.assign({
            copy: 'Copy message',
            copied: 'Copied',
            play: 'Read message aloud',
            stop: 'Stop reading',
        }, labels || {});

        const content = bubbleEl.querySelector('.dixeo-tutor-message-content');
        const footer = bubbleEl.querySelector('.dixeo-tutor-message-footer');
        if (!content || !footer) {
            return;
        }

        const text = content.textContent.trim();
        if (!text) {
            return;
        }

        const actions = ensureActionsContainer(footer);

        if (!actions.querySelector('.dixeo-tutor-copy-btn')) {
            const copyButton = document.createElement('button');
            copyButton.type = 'button';
            copyButton.className = 'dixeo-tutor-message-action-btn dixeo-tutor-copy-btn';
            copyButton.dataset.copyLabel = ariaLabels.copy;
            copyButton.dataset.copiedLabel = ariaLabels.copied;
            copyButton.setAttribute('aria-label', ariaLabels.copy);
            copyButton.innerHTML = '<i class="fa fa-copy dixeo-tutor-message-action-btn__icon" aria-hidden="true"></i>';

            copyButton.addEventListener('click', function(e) {
                e.stopPropagation();
                const icon = copyButton.querySelector('.dixeo-tutor-message-action-btn__icon');
                copyToClipboard(text).then(function() {
                    copyButton.classList.add('dixeo-tutor-copy-btn--done');
                    if (icon) {
                        icon.className = 'fa fa-check dixeo-tutor-message-action-btn__icon';
                    }
                    copyButton.setAttribute('aria-label', ariaLabels.copied);
                    window.setTimeout(function() {
                        copyButton.classList.remove('dixeo-tutor-copy-btn--done');
                        if (icon) {
                            icon.className = 'fa fa-copy dixeo-tutor-message-action-btn__icon';
                        }
                        copyButton.setAttribute('aria-label', ariaLabels.copy);
                    }, 1500);
                    return undefined;
                }).catch(function() {
                    // Silent failure — clipboard may be blocked.
                });
            });

            actions.appendChild(copyButton);
        }

        ttsPlayer.attach(actions, bubbleEl, {
            play: ariaLabels.play,
            stop: ariaLabels.stop,
        });
    };

    return {
        attach: attach,
        stop: ttsPlayer.stop,
    };
});
