define([], function() {
    'use strict';

    /** @type {SpeechSynthesisUtterance|null} */
    let currentUtterance = null;
    /** @type {string|null} */
    let activeGroupId = null;
    /** @type {Map<string, Set<HTMLElement>>} */
    const buttonGroups = new Map();
    /** @type {number} */
    let soloGroupCounter = 0;
    /** @type {{play: string, stop: string}} */
    let defaultLabels = {
        play: 'Read message aloud',
        stop: 'Stop reading',
    };

    window.addEventListener('pagehide', function() {
        stop();
    });

    /**
     * @returns {boolean}
     */
    const isSupported = function() {
        return 'speechSynthesis' in window;
    };

    /**
     * @param {HTMLElement} button
     * @param {boolean} playing
     * @param {{play: string, stop: string}} labels
     */
    const setButtonState = function(button, playing, labels) {
        const icon = button.querySelector('.dixeo-tutor-tts-btn__icon');
        if (!icon) {
            return;
        }
        if (playing) {
            icon.classList.remove('fa-volume-up');
            icon.classList.add('fa-stop');
            button.setAttribute('aria-label', labels.stop);
            button.classList.add('playing');
        } else {
            icon.classList.remove('fa-stop');
            icon.classList.add('fa-volume-up');
            button.setAttribute('aria-label', labels.play);
            button.classList.remove('playing');
        }
    };

    /**
     * @param {HTMLElement} button
     * @returns {{play: string, stop: string}}
     */
    const labelsForButton = function(button) {
        return {
            play: button.dataset.playLabel || defaultLabels.play,
            stop: button.dataset.stopLabel || defaultLabels.stop,
        };
    };

    /**
     * @param {string} groupId
     * @returns {Set<HTMLElement>}
     */
    const getGroup = function(groupId) {
        if (!buttonGroups.has(groupId)) {
            buttonGroups.set(groupId, new Set());
        }
        return buttonGroups.get(groupId);
    };

    /**
     * Reset all playing buttons in the tutor.
     */
    const resetAllButtons = function() {
        document.querySelectorAll('.dixeo-tutor-tts-btn.playing').forEach((btn) => {
            setButtonState(btn, false, labelsForButton(btn));
        });
    };

    /**
     * @param {string} groupId
     * @param {boolean} playing
     */
    const setGroupPlaying = function(groupId, playing) {
        const group = buttonGroups.get(groupId);
        if (!group) {
            return;
        }
        group.forEach(function(btn) {
            setButtonState(btn, playing, labelsForButton(btn));
        });
    };

    /**
     * @param {string} groupId
     */
    const syncGroupPlayback = function(groupId) {
        if (activeGroupId === groupId && window.speechSynthesis && speechSynthesis.speaking) {
            setGroupPlaying(groupId, true);
        }
    };

    /**
     * Stop current playback and reset UI.
     */
    const stop = function() {
        if (window.speechSynthesis && speechSynthesis.speaking) {
            speechSynthesis.cancel();
        }
        currentUtterance = null;
        activeGroupId = null;
        resetAllButtons();
    };

    /**
     * @param {string} text
     * @param {{play: string, stop: string}} labels
     * @param {string} groupId
     */
    const startSpeaking = function(text, labels, groupId) {
        stop();

        activeGroupId = groupId;
        setGroupPlaying(groupId, true);

        currentUtterance = new SpeechSynthesisUtterance(text);
        currentUtterance.lang = document.documentElement.lang || 'en';
        currentUtterance.rate = 1.0;
        currentUtterance.pitch = 1.0;
        currentUtterance.volume = 1.0;

        currentUtterance.onend = function() {
            if (activeGroupId === groupId) {
                setGroupPlaying(groupId, false);
                activeGroupId = null;
                currentUtterance = null;
            }
        };

        currentUtterance.onerror = function() {
            if (activeGroupId === groupId) {
                setGroupPlaying(groupId, false);
                activeGroupId = null;
                currentUtterance = null;
            }
        };

        speechSynthesis.speak(currentUtterance);
    };

    /**
     * @param {HTMLElement} button
     * @param {string} text
     * @param {string} groupId
     */
    const handleClick = function(button, text, groupId) {
        if (activeGroupId === groupId && speechSynthesis.speaking) {
            stop();
            return;
        }
        startSpeaking(text, labelsForButton(button), groupId);
    };

    /**
     * Wire an existing button for TTS play/stop toggling.
     *
     * @param {HTMLElement} button
     * @param {function(): string} textProvider
     * @param {{play: string, stop: string}} [labels]
     * @param {{groupId?: string}} [options]
     */
    const wireButton = function(button, textProvider, labels, options) {
        if (!isSupported() || !button) {
            return;
        }

        const opts = options || {};
        const groupId = opts.groupId || ('solo-' + (++soloGroupCounter));
        const ariaLabels = Object.assign({}, defaultLabels, labels || {});
        if (!button.dataset.playLabel) {
            button.dataset.playLabel = ariaLabels.play;
        }
        if (!button.dataset.stopLabel) {
            button.dataset.stopLabel = ariaLabels.stop;
        }
        button.dataset.ttsGroup = groupId;
        getGroup(groupId).add(button);
        syncGroupPlayback(groupId);

        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const text = typeof textProvider === 'function'
                ? textProvider()
                : String(textProvider || '');
            const trimmed = text.trim();
            if (!trimmed) {
                return;
            }
            handleClick(button, trimmed, groupId);
        });
    };

    /**
     * Remove a button from its TTS group without stopping playback.
     *
     * @param {HTMLElement} button
     */
    const unregisterButton = function(button) {
        if (!button) {
            return;
        }
        const groupId = button.dataset.ttsGroup;
        if (!groupId) {
            return;
        }
        const group = buttonGroups.get(groupId);
        if (group) {
            group.delete(button);
            if (group.size === 0) {
                buttonGroups.delete(groupId);
            }
        }
        delete button.dataset.ttsGroup;
    };

    /**
     * Attach a TTS button to a message actions container.
     *
     * @param {HTMLElement} actionsEl The .dixeo-tutor-message-actions element.
     * @param {HTMLElement} bubbleEl The .dixeo-tutor-message element.
     * @param {{play: string, stop: string}} [labels] Aria labels for play/stop.
     */
    const attach = function(actionsEl, bubbleEl, labels) {
        if (!isSupported() || !actionsEl || !bubbleEl) {
            return;
        }

        const ariaLabels = Object.assign({}, defaultLabels, labels || {});

        if (actionsEl.querySelector('.dixeo-tutor-tts-btn')) {
            return;
        }

        const content = bubbleEl.querySelector('.dixeo-tutor-message-content');
        if (!content) {
            return;
        }

        const text = content.textContent.trim();
        if (!text) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'dixeo-tutor-message-action-btn dixeo-tutor-tts-btn';
        button.dataset.playLabel = ariaLabels.play;
        button.dataset.stopLabel = ariaLabels.stop;
        button.setAttribute('aria-label', ariaLabels.play);
        button.innerHTML =
            '<i class="fa fa-volume-up dixeo-tutor-message-action-btn__icon dixeo-tutor-tts-btn__icon" aria-hidden="true"></i>';

        wireButton(button, text, ariaLabels);
        actionsEl.appendChild(button);
    };

    return {
        isSupported: isSupported,
        attach: attach,
        wireButton: wireButton,
        unregisterButton: unregisterButton,
        syncGroupPlayback: syncGroupPlayback,
        stop: stop,
    };
});
