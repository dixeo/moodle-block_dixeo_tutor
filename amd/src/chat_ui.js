/**
 * Chat UI: message rendering, input handling, and scroll management.
 *
 * @module     block_dixeo_tutor/chat_ui
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/str',
    'core/notification',
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/event_emitter',
    'block_dixeo_tutor/a11y',
    'block_dixeo_tutor/text_utils',
    'block_dixeo_tutor/message_actions',
    'block_dixeo_tutor/practice_quiz_review',
    'block_dixeo_tutor/custom_lesson_panel',
    'block_dixeo_tutor/guide_session_context',
    'block_dixeo_tutor/guide_topic_panel',
], function(
    str, Notification, constants, EventEmitter, a11y, textUtils, messageActions,
    practiceQuizReview, customLessonPanel, guideSessionContext, guideTopicPanel
) {
    'use strict';

    return class ChatUI extends EventEmitter {
        constructor() {
            super();
            this.dom = {
                container: document.getElementById('dixeo-tutor'),
                header: document.getElementById('dixeo-tutor-header'),
                messagesContainer: document.getElementById('dixeo-tutor-messages'),
                inputField: document.getElementById('dixeo-tutor-input'),
                sendButton: document.getElementById('dixeo-tutor-send'),
                deleteButton: document.getElementById('dixeo-tutor-delete'),
            };
            this.pendingIndicator = null;
            this.loadOlderSlot = null;
            this._hasMoreOlder = false;
            this._loadingOlder = false;
            this._messagingLocked = false;
            // Tracks whether the "Today" date separator has been added this session.
            this.todaySeparatorAdded = false;
            // User scrolled up to read older messages; reset when at bottom or scrollToBottom is called.
            this._userScrolledUp = false;
            // First history paint happened while quiz/teach hid the chat pane.
            this._initialScrollPending = false;
            // Scroll offset captured before a quiz/lesson pane hides the chat.
            this._savedMessagesScrollTop = null;
            /** @type {HTMLElement|null} Live DOM row when the overlay was opened. */
            this._returnToMessageRow = null;
            /**
             * Durable card identity for reload / pagination.
             * @type {{type: string, title?: string, description?: string, messageId?: string}|null}
             */
            this._returnToCard = null;
            this._guideCompletionHandlers = null;
            /** @type {'standard'|'guide'|'review'} */
            this._messageView = 'standard';
            /** @type {{title: string, description: string, startedAt?: number, timeEnded?: number}|null} */
            this._guideViewFilter = null;
            // Pre-fetched to avoid async race when the first message arrives quickly.
            this.todayLabel = null;
            this.strings = {};
            str.get_strings([
                {key: 'aria_sender_you', component: 'block_dixeo_tutor'},
                {key: 'aria_sender_assistant', component: 'block_dixeo_tutor'},
                {key: 'connection_lost', component: 'block_dixeo_tutor'},
                {key: 'aria_your_message', component: 'block_dixeo_tutor'},
                {key: 'aria_assistant_message', component: 'block_dixeo_tutor'},
                {key: 'message_too_long', component: 'block_dixeo_tutor'},
                {key: 'load_older_messages', component: 'block_dixeo_tutor'},
                {key: 'aria_load_older_messages', component: 'block_dixeo_tutor'},
                {key: 'aria_read_message', component: 'block_dixeo_tutor'},
                {key: 'aria_stop_reading', component: 'block_dixeo_tutor'},
                {key: 'aria_copy_message', component: 'block_dixeo_tutor'},
                {key: 'aria_message_copied', component: 'block_dixeo_tutor'},
                {key: 'guide_completion_exit', component: 'block_dixeo_tutor'},
                {key: 'guide_completion_restart', component: 'block_dixeo_tutor'},
            ]).then(([
                senderYou, senderAssistant, connLost, yourMsg, assistantMsg,
                tooLong, loadOlder, ariaLoadOlder, readMsg, stopReading, copyMsg, copiedMsg,
                guideExit, guideRestart,
            ]) => {
                this.strings.senderYou = senderYou;
                this.strings.senderAssistant = senderAssistant;
                this.strings.connectionLost = connLost;
                this.strings.yourMessage = yourMsg;
                this.strings.assistantMessage = assistantMsg;
                this.strings.messageTooLong = tooLong;
                this.strings.loadOlder = loadOlder;
                this.strings.ariaLoadOlder = ariaLoadOlder;
                this.strings.ariaReadMessage = readMsg;
                this.strings.ariaStopReading = stopReading;
                this.strings.ariaCopyMessage = copyMsg;
                this.strings.ariaMessageCopied = copiedMsg;
                this.strings.guideCompletionExit = guideExit;
                this.strings.guideCompletionRestart = guideRestart;
                return null;
            }).catch(() => {
                // Fallback to English.
                this.strings.senderYou = 'You';
                this.strings.senderAssistant = 'Assistant';
                this.strings.connectionLost = 'Connection lost. Attempting to reconnect...';
                this.strings.yourMessage = 'Your message';
                this.strings.assistantMessage = 'Assistant message';
                this.strings.messageTooLong = 'Message cannot exceed {a} characters.';
                this.strings.loadOlder = 'Load older messages';
                this.strings.ariaLoadOlder = 'Load older messages';
                this.strings.ariaReadMessage = 'Read message aloud';
                this.strings.ariaStopReading = 'Stop reading';
                this.strings.ariaCopyMessage = 'Copy message';
                this.strings.ariaMessageCopied = 'Copied';
                this.strings.guideCompletionExit = 'Exit';
                this.strings.guideCompletionRestart = 'Start new session';
            });

            this._initialize();
        }

        /**
         * @param {{shouldShow: function(): boolean, onExit: function, onRestart: function}|null} handlers
         */
        setGuideCompletionHandlers(handlers) {
            this._guideCompletionHandlers = handlers || null;
        }

        /**
         * Switch which transcript lanes are visible.
         *
         * @param {'standard'|'guide'|'review'} view
         * @param {{title?: string, description?: string, startedAt?: number, timeEnded?: number}|null} [guideFilter]
         */
        setMessageView(view, guideFilter) {
            const allowed = view === 'guide' || view === 'review' ? view : 'standard';
            const changed = this._messageView !== allowed;
            this._messageView = allowed;
            this._guideViewFilter = (allowed === 'standard') ? null : (guideFilter || null);
            this.applyMessageView();
            this._updateLoadOlderVisibility();
            if (allowed === 'standard' && changed) {
                this.emit(constants.events.ENSURE_VISIBLE_TRANSCRIPT);
            }
        }

        /**
         * @returns {'standard'|'guide'|'review'}
         */
        getMessageView() {
            return this._messageView || 'standard';
        }

        /**
         * Whether any transcript row is currently visible under the active message view.
         *
         * @returns {boolean}
         */
        hasVisibleTranscriptContent() {
            const container = this.dom.messagesContainer;
            if (!container) {
                return false;
            }
            return !!container.querySelector(
                '.dixeo-tutor-message-row:not(.dixeo-tutor-row--hidden)'
            );
        }

        /**
         * Re-apply lane visibility to every rendered row and date separator.
         */
        applyMessageView() {
            const container = this.dom.messagesContainer;
            if (!container) {
                return;
            }
            this._retagGuideLanesFromWindows();
            const view = this._messageView || 'standard';
            const filter = this._guideViewFilter;
            if (view === 'standard') {
                this._ensureGuideSummaryCards();
            }
            container.querySelectorAll('.dixeo-tutor-message-row').forEach((row) => {
                // Prefer a custom hidden class: Bootstrap d-flex beats d-none at equal specificity.
                row.classList.toggle(
                    'dixeo-tutor-row--hidden',
                    !this._rowVisibleInMessageView(row, view, filter)
                );
            });
            this._syncDateSeparatorVisibility();
            if (typeof this.syncGuideCompletionButtons === 'function') {
                this.syncGuideCompletionButtons();
            }
        }

        /**
         * @param {HTMLElement} row
         * @param {string} view
         * @param {{title?: string, description?: string, startedAt?: number, timeEnded?: number}|null} filter
         * @returns {boolean}
         * @private
         */
        _rowVisibleInMessageView(row, view, filter) {
            const lane = row.dataset.lane || 'standard';
            // Standard: only non-guide turns + review summary cards. Guide turns stay hidden.
            if (view === 'standard') {
                return lane !== 'guide';
            }
            // Guide / review: hide summary cards and every non-guide row.
            if (lane === 'guide-summary' || lane !== 'guide') {
                return false;
            }
            // Setup / blank session (no topic filter yet): hide everything for a clean slate.
            if (!filter || !filter.title) {
                return false;
            }
            return this._guideRowMatchesFilter(row, filter);
        }

        /**
         * @param {HTMLElement} row
         * @param {{title?: string, description?: string, startedAt?: number, timeEnded?: number}|null} filter
         * @returns {boolean}
         * @private
         */
        _guideRowMatchesFilter(row, filter) {
            if (String(row.dataset.guideTitle || '') !== String(filter.title)) {
                return false;
            }
            if (filter.description
                    && String(row.dataset.guideDescription || '') !== String(filter.description || '')) {
                return false;
            }
            const time = this._rowMessageTime(row);
            if (!time) {
                // Untimed guide-tagged rows still belong to the session once titled.
                return true;
            }
            if (filter.startedAt && time < filter.startedAt) {
                return false;
            }
            if (filter.timeEnded && time > filter.timeEnded) {
                return false;
            }
            return true;
        }

        /**
         * @param {HTMLElement} row
         * @returns {number}
         * @private
         */
        _rowMessageTime(row) {
            return parseInt(row.dataset.msgTime || row.dataset.guideTime || '0', 10) || 0;
        }

        /**
         * @returns {Array<{title: string, description: string, startedAt: number, timeEnded: number, anchor: HTMLElement|null}>}
         * @private
         */
        _collectGuideSessionClusters() {
            const container = this.dom.messagesContainer;
            if (!container) {
                return [];
            }

            const assistantMarks = [];
            container.querySelectorAll('.dixeo-tutor-message-row').forEach((row) => {
                if (row.dataset.lane === 'guide-summary') {
                    return;
                }
                if (!row.querySelector('.dixeo-tutor-message-assistant')) {
                    return;
                }
                if (row.dataset.lane !== 'guide' || !row.dataset.guideTitle) {
                    return;
                }
                const time = this._rowMessageTime(row);
                if (!time) {
                    return;
                }
                assistantMarks.push({
                    title: row.dataset.guideTitle,
                    description: row.dataset.guideDescription || '',
                    time: time,
                    row: row,
                });
            });
            assistantMarks.sort((a, b) => a.time - b.time);

            const clusters = [];
            let cluster = null;
            const flush = () => {
                if (!cluster) {
                    return;
                }
                clusters.push(cluster);
                cluster = null;
            };
            assistantMarks.forEach((mark) => {
                if (!cluster
                        || cluster.title !== mark.title
                        || cluster.description !== mark.description) {
                    flush();
                    cluster = {
                        title: mark.title,
                        description: mark.description,
                        startedAt: mark.time,
                        timeEnded: mark.time,
                        anchor: mark.row,
                    };
                    return;
                }
                cluster.timeEnded = mark.time;
                cluster.anchor = mark.row;
            });
            flush();

            // Extend end to cover user replies until the next session (or +15 min).
            for (let i = 0; i < clusters.length; i++) {
                const next = clusters[i + 1];
                if (next) {
                    clusters[i].timeEnded = Math.max(clusters[i].timeEnded, next.startedAt - 1);
                } else {
                    clusters[i].timeEnded = Math.max(clusters[i].timeEnded, clusters[i].timeEnded + 15 * 60);
                }
                // Prefer the last guide-lane row in the window as insert anchor.
                let lastInWindow = clusters[i].anchor;
                container.querySelectorAll('.dixeo-tutor-message-row').forEach((row) => {
                    if (row.dataset.lane !== 'guide') {
                        return;
                    }
                    if (String(row.dataset.guideTitle || '') !== String(clusters[i].title)) {
                        return;
                    }
                    const time = this._rowMessageTime(row);
                    if (!time || time < clusters[i].startedAt || time > clusters[i].timeEnded) {
                        return;
                    }
                    lastInWindow = row;
                });
                clusters[i].anchor = lastInWindow;
            }

            return clusters;
        }

        /**
         * Ensure each past guide session has a review summary card in the standard transcript.
         *
         * @private
         */
        _ensureGuideSummaryCards() {
            const container = this.dom.messagesContainer;
            if (!container) {
                return;
            }
            // Never synthesize cards while a live/review guide session chrome is up.
            const root = this.dom.container || document.getElementById('dixeo-tutor');
            const guideSession = root ? root.getAttribute('data-guide-session') : '';
            if (guideSession === 'active' || guideSession === 'review') {
                return;
            }

            const existingKeys = new Set();
            container.querySelectorAll('.dixeo-tutor-message-row[data-lane="guide-summary"]').forEach((row) => {
                existingKeys.add(
                    String(row.dataset.guideTitle || '') + '\n' + String(row.dataset.guideDescription || '')
                );
            });

            const providerSessions = (typeof this._guideSummarySessionsProvider === 'function')
                ? (this._guideSummarySessionsProvider() || [])
                : [];
            const byKey = new Map();
            providerSessions.forEach((session) => {
                if (!session || !session.title) {
                    return;
                }
                const key = String(session.title) + '\n' + String(session.description || '');
                byKey.set(key, {
                    title: session.title,
                    description: session.description || '',
                    startedAt: session.startedAt || 0,
                    timeEnded: session.timeEnded || 0,
                    anchor: null,
                });
            });

            this._collectGuideSessionClusters().forEach((cluster) => {
                const key = String(cluster.title) + '\n' + String(cluster.description || '');
                const prev = byKey.get(key);
                if (prev) {
                    prev.startedAt = prev.startedAt || cluster.startedAt;
                    prev.timeEnded = Math.max(prev.timeEnded || 0, cluster.timeEnded || 0);
                    prev.anchor = cluster.anchor;
                    return;
                }
                byKey.set(key, cluster);
            });

            byKey.forEach((session, key) => {
                const existing = Array.from(
                    container.querySelectorAll('.dixeo-tutor-message-row[data-lane="guide-summary"]')
                ).find((row) => {
                    return String(row.dataset.guideTitle || '') + '\n'
                        + String(row.dataset.guideDescription || '') === key;
                });
                if (existing) {
                    // After older pages load, move a bottom-appended card next to its session.
                    if (session.anchor && session.anchor.parentNode === container
                            && existing.previousElementSibling !== session.anchor) {
                        if (session.anchor.nextSibling) {
                            container.insertBefore(existing, session.anchor.nextSibling);
                        } else {
                            container.appendChild(existing);
                        }
                    }
                    return;
                }
                const node = guideTopicPanel.createCardNode({
                    title: session.title,
                    description: session.description,
                    startedAt: session.startedAt || 0,
                    timeEnded: session.timeEnded || 0,
                });
                if (!node) {
                    return;
                }
                if (session.anchor && session.anchor.parentNode === container) {
                    if (session.anchor.nextSibling) {
                        container.insertBefore(node, session.anchor.nextSibling);
                    } else {
                        container.appendChild(node);
                    }
                } else {
                    container.appendChild(node);
                }
                existingKeys.add(key);
            });
        }

        /**
         * @param {function(): Array<{title: string, description: string, startedAt?: number, timeEnded?: number}>} provider
         */
        setGuideSummarySessionsProvider(provider) {
            this._guideSummarySessionsProvider = typeof provider === 'function' ? provider : null;
        }

        /**
         * Tag user turns that fall inside known guide sessions (from summary cards /
         * assistant guide_assistant markers). Never un-tags guide rows, and never uses
         * the active view filter alone (that would pull in ordinary chat history).
         *
         * @private
         */
        _retagGuideLanesFromWindows() {
            const container = this.dom.messagesContainer;
            if (!container) {
                return;
            }

            const windows = [];

            container.querySelectorAll('.dixeo-tutor-message-row[data-lane="guide-summary"]').forEach((row) => {
                const start = parseInt(row.dataset.guideStarted || '0', 10) || 0;
                const end = parseInt(row.dataset.guideEnded || '0', 10) || 0;
                if (!row.dataset.guideTitle || !start) {
                    return;
                }
                windows.push({
                    title: row.dataset.guideTitle,
                    description: row.dataset.guideDescription || '',
                    start: start,
                    end: end || Number.MAX_SAFE_INTEGER,
                });
            });

            const assistantMarks = [];
            container.querySelectorAll('.dixeo-tutor-message-row').forEach((row) => {
                if (!row.querySelector('.dixeo-tutor-message-assistant')) {
                    return;
                }
                if (row.dataset.lane !== 'guide' || !row.dataset.guideTitle) {
                    return;
                }
                const time = this._rowMessageTime(row);
                if (!time) {
                    return;
                }
                assistantMarks.push({
                    title: row.dataset.guideTitle,
                    description: row.dataset.guideDescription || '',
                    time: time,
                });
            });
            assistantMarks.sort((a, b) => a.time - b.time);

            let cluster = null;
            const flushCluster = () => {
                if (!cluster) {
                    return;
                }
                windows.push({
                    title: cluster.title,
                    description: cluster.description,
                    start: cluster.start,
                    end: cluster.end,
                });
                cluster = null;
            };
            assistantMarks.forEach((mark) => {
                if (!cluster
                        || cluster.title !== mark.title
                        || cluster.description !== mark.description) {
                    flushCluster();
                    cluster = {
                        title: mark.title,
                        description: mark.description,
                        start: mark.time,
                        end: mark.time,
                    };
                    return;
                }
                cluster.end = mark.time;
            });
            flushCluster();

            // Extend each cluster so user replies after the last assistant turn still match,
            // until the next session starts (or +15 minutes for the latest open session).
            const derived = windows.filter((w) => w.start > 0).sort((a, b) => a.start - b.start);
            for (let i = 0; i < derived.length; i++) {
                const next = derived[i + 1];
                if (next && derived[i].end < next.start) {
                    derived[i].end = next.start - 1;
                } else if (!next && derived[i].end < Number.MAX_SAFE_INTEGER) {
                    derived[i].end = Math.max(derived[i].end, derived[i].end + 15 * 60);
                }
            }

            container.querySelectorAll('.dixeo-tutor-message-row').forEach((row) => {
                if (row.dataset.lane === 'guide-summary' || row.dataset.lane === 'guide') {
                    // Never demote guide / summary rows back to standard.
                    return;
                }
                // Quiz/lesson cards use the user alignment class but are not guide turns.
                // Retagging them as lane=guide hides them in standard view.
                if (row.classList.contains('dixeo-tutor-message-row--quiz-review')
                        || row.classList.contains('dixeo-tutor-message-row--custom-lesson')) {
                    return;
                }
                if (row.querySelector('.dixeo-tutor-message-assistant')) {
                    return;
                }

                const time = this._rowMessageTime(row);
                if (!time) {
                    return;
                }
                const match = derived.find((w) => {
                    if (!w.title) {
                        return false;
                    }
                    if (time < w.start) {
                        return false;
                    }
                    if (w.end !== Number.MAX_SAFE_INTEGER && time > w.end) {
                        return false;
                    }
                    return true;
                });

                if (!match) {
                    return;
                }
                row.dataset.lane = 'guide';
                row.dataset.guideTitle = match.title;
                row.dataset.guideDescription = match.description;
                const bubble = row.querySelector('.dixeo-tutor-message-user');
                if (bubble) {
                    bubble.classList.add('dixeo-tutor-message-user--guide');
                }
            });
        }

        /**
         * Hide date separators that have no visible message rows until the next separator.
         *
         * @private
         */
        _syncDateSeparatorVisibility() {
            const container = this.dom.messagesContainer;
            if (!container) {
                return;
            }
            const children = Array.from(container.children);
            let pendingSep = null;
            let visibleSinceSep = false;
            const flush = () => {
                if (pendingSep) {
                    pendingSep.classList.toggle('dixeo-tutor-row--hidden', !visibleSinceSep);
                }
            };
            children.forEach((child) => {
                if (child.classList.contains('dixeo-tutor-separator')) {
                    flush();
                    pendingSep = child;
                    visibleSinceSep = false;
                    return;
                }
                if (child.classList.contains('dixeo-tutor-message-row')
                        && !child.classList.contains('dixeo-tutor-row--hidden')) {
                    visibleSinceSep = true;
                }
            });
            flush();
        }

        /**
         * Tag a message row for lane filtering / guide coloring (Moodle-side only).
         *
         * @param {HTMLElement} row
         * @param {object} msg
         * @private
         */
        _annotateMessageLane(row, msg) {
            if (!row) {
                return;
            }
            if (msg && msg.time) {
                row.dataset.msgTime = String(msg.time);
            }
            // Prefer API assistant metadata; optimistic client guide_session is also fine.
            const session = guideSessionContext.parseSessionFields(msg);
            if (!session) {
                if (!row.dataset.lane) {
                    row.dataset.lane = 'standard';
                }
                return;
            }
            row.dataset.lane = 'guide';
            row.dataset.guideTitle = session.title;
            row.dataset.guideDescription = session.description;
            if (msg.time) {
                row.dataset.guideTime = String(msg.time);
            }
            const bubble = row.querySelector('.dixeo-tutor-message-user');
            if (bubble) {
                bubble.classList.add('dixeo-tutor-message-user--guide');
            }
        }

        /**
         * Show Exit / Restart on the last assistant message when guide session is understood.
         */
        syncGuideCompletionButtons() {
            const container = this.dom.messagesContainer;
            if (!container) {
                return;
            }
            container.querySelectorAll('.dixeo-guide-completion-actions').forEach(function(el) {
                el.remove();
            });

            if (!this._guideCompletionHandlers || !this._guideCompletionHandlers.shouldShow()) {
                return;
            }

            const rows = Array.from(container.querySelectorAll('.dixeo-tutor-message-row'))
                .filter(function(row) {
                    return !row.classList.contains('dixeo-tutor-row--hidden');
                });
            if (!rows.length) {
                return;
            }
            const lastRow = rows[rows.length - 1];
            if (!lastRow || lastRow.dataset.guideUnderstood !== '1') {
                return;
            }

            const footer = lastRow.querySelector('.dixeo-tutor-message-footer');
            if (!footer) {
                return;
            }

            const messageActions = lastRow.querySelector('.dixeo-tutor-message-actions');
            if (messageActions) {
                messageActions.classList.add('d-none');
            }

            const actions = document.createElement('div');
            actions.className = 'dixeo-guide-completion-actions d-flex flex-wrap mt-2';
            actions.innerHTML =
                '<button type="button" class="btn btn-sm btn-outline-secondary dixeo-guide-completion-actions__exit"' +
                ' data-action="guide-exit">' +
                textUtils.escapeHtml(this.strings.guideCompletionExit || 'Exit') +
                '</button>' +
                '<button type="button" class="btn btn-sm btn-primary dixeo-guide-completion-actions__restart"' +
                ' data-action="guide-restart">' +
                textUtils.escapeHtml(this.strings.guideCompletionRestart || 'Start new session') +
                '</button>';

            const exitBtn = actions.querySelector('[data-action="guide-exit"]');
            const restartBtn = actions.querySelector('[data-action="guide-restart"]');
            if (exitBtn) {
                exitBtn.addEventListener('click', () => {
                    if (typeof this._guideCompletionHandlers.onExit === 'function') {
                        this._guideCompletionHandlers.onExit();
                    }
                });
            }
            if (restartBtn) {
                restartBtn.addEventListener('click', () => {
                    if (typeof this._guideCompletionHandlers.onRestart === 'function') {
                        this._guideCompletionHandlers.onRestart();
                    }
                });
            }

            footer.insertBefore(actions, footer.firstChild);
        }

        /**
         * @param {string} htmlString The HTML string to convert.
         * @returns {HTMLElement} The first child element created.
         * @private
         */
        _createNodeFromHTML(htmlString) {
            const div = document.createElement('div');
            div.innerHTML = htmlString.trim().replace(/>\s+</g, '><');
            return div.firstChild;
        }

        /**
         * Sets up initial UI state and event listeners.
         * @private
         */
        _initialize() {
            if (!this.dom.inputField) {
              return;
            }

            a11y.setupARIA(this.dom);
            a11y.setupKeyboardNavigation(
                this.dom.messagesContainer,
                '.dixeo-tutor-message'
            );

            const skipLink = a11y.createSkipLink(
                constants.selectors.INPUT_FIELD,
                'Skip to message input'
            );
            document.body.insertBefore(skipLink, document.body.firstChild);
            str.get_string('aria_skip_to_input', 'block_dixeo_tutor').then(s => {
                skipLink.textContent = s;
                return null;
            }).catch(() => { /* Keep English fallback */ });

            // Pre-fetch "Today" label to prevent race conditions when the first message arrives.
            str.get_string('today', 'moodle').then(label => {
                this.todayLabel = label;
                return null;
            }).catch(() => {
                this.todayLabel = 'Today';
            });

            this.dom.sendButton.addEventListener('click', () => this._handleSendClick());
            if (this.dom.deleteButton) {
                this.dom.deleteButton.addEventListener('click', (e) => {
                    // The card header doubles as a collapse toggle; keep the click here.
                    e.stopPropagation();
                    this._handleDeleteClick();
                });
            }
            this.dom.inputField.addEventListener('keypress', (e) => this._handleKeyPress(e));
            this.dom.inputField.addEventListener('input', () => {
                this._adjustTextareaHeight();
                this._validateInputLength();
            });
            this._boundScrollHandler = () => this._onMessagesScroll();
            this.dom.messagesContainer.addEventListener('scroll', this._boundScrollHandler, {passive: true});
            this._adjustTextareaHeight();
        }

        /**
         * Handles scroll on the message container to track when user has scrolled up from the bottom.
         * @private
         */
        _onMessagesScroll() {
            const el = this.dom.messagesContainer;
            if (!el) {
                return;
            }
            const threshold = constants.ui.SCROLL_BOTTOM_THRESHOLD;
            const atBottom = el.scrollHeight - el.scrollTop - el.clientHeight <= threshold;
            this._userScrolledUp = !atBottom;
            this._updateLoadOlderVisibility();
        }

        /**
         * Creates or returns the load-older control slot at the top of the message list.
         * @returns {HTMLElement}
         * @private
         */
        _ensureLoadOlderSlot() {
            if (this.loadOlderSlot && this.loadOlderSlot.isConnected) {
                return this.loadOlderSlot;
            }

            this.loadOlderSlot = document.createElement('div');
            this.loadOlderSlot.id = 'dixeo-tutor-load-older';
            this.loadOlderSlot.className = 'dixeo-tutor-load-older';
            this.loadOlderSlot.hidden = true;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-primary dixeo-tutor-load-older__btn';
            btn.textContent = this.strings.loadOlder || 'Load older messages';
            btn.setAttribute('aria-label', this.strings.ariaLoadOlder || 'Load older messages');
            btn.addEventListener('click', () => {
                this.emit(constants.events.LOAD_OLDER_MESSAGES);
            });
            this.loadOlderSlot.appendChild(btn);

            const container = this.dom.messagesContainer;
            if (container.firstChild) {
                container.insertBefore(this.loadOlderSlot, container.firstChild);
            } else {
                container.appendChild(this.loadOlderSlot);
            }

            return this.loadOlderSlot;
        }

        /**
         * Updates whether older messages are available (called after each page fetch).
         * @param {boolean} hasMoreOlder
         */
        syncLoadOlderControl(hasMoreOlder) {
            this._hasMoreOlder = !!hasMoreOlder;
            this._ensureLoadOlderSlot();
            this._updateLoadOlderVisibility();
        }

        /**
         * Shows or hides the load-older slot based on scroll position and availability.
         * @private
         */
        _updateLoadOlderVisibility() {
            if (!this.loadOlderSlot || !this.loadOlderSlot.isConnected) {
                return;
            }
            // Review loads the full transcript; never show pagination chrome.
            if (this._messageView === 'review') {
                this.loadOlderSlot.hidden = true;
                return;
            }
            if (this._loadingOlder) {
                this.loadOlderSlot.hidden = false;
                return;
            }
            const el = this.dom.messagesContainer;
            if (!el) {
                return;
            }
            const atTop = el.scrollTop <= constants.ui.SCROLL_TOP_THRESHOLD;
            this.loadOlderSlot.hidden = !(this._hasMoreOlder && atTop);
        }

        /**
         * Swaps the load-older button for a loading indicator (or restores the button).
         * @param {boolean} loading
         */
        setLoadOlderLoading(loading) {
            this._loadingOlder = loading;
            const slot = this._ensureLoadOlderSlot();

            if (loading) {
                slot.innerHTML = `
                    <div class="dixeo-tutor-load-older__loading" role="status" aria-live="polite">
                        <div class="chat-dots"><span></span><span></span><span></span></div>
                    </div>`;
                slot.hidden = false;
                return;
            }

            slot.innerHTML = '';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-primary dixeo-tutor-load-older__btn';
            btn.textContent = this.strings.loadOlder || 'Load older messages';
            btn.setAttribute('aria-label', this.strings.ariaLoadOlder || 'Load older messages');
            btn.addEventListener('click', () => {
                this.emit(constants.events.LOAD_OLDER_MESSAGES);
            });
            slot.appendChild(btn);
            this._updateLoadOlderVisibility();
        }

        /**
         * Handles the click event on the send button.
         * @private
         */
        _handleSendClick() {
            if (this.dom.sendButton.disabled || this.dom.inputField.disabled) {
                return;
            }

            const message = this.dom.inputField.value.trim();
            if (message) {
                this.emit(constants.events.SEND_MESSAGE, message);
                window.dispatchEvent(new CustomEvent('dixeo-tutor-user-sent-message'));
                this.dom.inputField.value = '';
                this._adjustTextareaHeight();
                this._retainFocusInDrawer();
            }
        }

        /**
         * Handles the click event on the erase conversation button.
         *
         * Emits the intent only once the user has confirmed, mirroring _handleSendClick:
         * the controller reacts to decisions, not to raw clicks.
         * @private
         */
        _handleDeleteClick() {
            Notification.deleteCancelPromise(
                str.get_string('deleteconversation', 'block_dixeo_tutor'),
                str.get_string('deleteconversationconfirm', 'block_dixeo_tutor'),
                str.get_string('delete', 'moodle'),
                {triggerElement: this.dom.deleteButton}
            ).then(() => {
                this.emit(constants.events.DELETE_CONVERSATION);
                return null;
            }).catch(() => {
                // Cancelled — nothing to do.
            });
        }

        /**
         * Enables the erase button only when there is a stored conversation to erase.
         * @param {boolean} available Whether erasable messages are rendered.
         * @private
         */
        _setDeleteAvailable(available) {
            if (this.dom.deleteButton) {
                this.dom.deleteButton.disabled = !available;
            }
        }

        /**
         * Moves focus to the message input.
         *
         * Called once an erasure has rebuilt an empty transcript: the modal returns focus
         * to the erase button, which is disabled by then, so focus would land on the body
         * and strand keyboard and screen reader users outside the drawer. Focusing a
         * disabled element is a no-op, so a late focus restore cannot take it back.
         */
        focusInput() {
            if (this.dom.inputField) {
                this.dom.inputField.focus({preventScroll: true});
            }
        }

        /**
         * Move focus to the message list so the keyboard can dismiss without leaving the
         * drawer (Boost closes block drawers on resize when focus is outside drawercontent).
         * @private
         */
        _retainFocusInDrawer() {
            const el = this.dom.messagesContainer;
            if (!el) {
                this.dom.inputField.blur();
                return;
            }
            if (!el.hasAttribute('tabindex')) {
                el.setAttribute('tabindex', '-1');
            }
            el.focus({preventScroll: true});
        }

        /**
         * Handles the keypress event in the input field.
         * @param {KeyboardEvent} event The keyboard event.
         * @private
         */
        _handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                this._handleSendClick();
            }
        }

        /**
         * Checks whether a message with the given id is already in the DOM.
         * @param {number} id The unique ID of the message.
         * @returns {boolean} True if the message element exists.
         */
        hasMessage(id) {
            return !!this.dom.messagesContainer.querySelector(`[data-mid="${id}"]`);
        }

        /**
         * Removes a specific message from the DOM by its ID.
         * @param {number} id The unique ID of the message to remove.
         */
        removeMessage(id) {
            const messageNode = this.dom.messagesContainer.querySelector(`[data-mid="${id}"]`);
            if (messageNode) {
                messageNode.remove();
            }
            // Dropping the optimistic bubble of a failed send can empty the transcript again,
            // and the erase button must not outlive what it erases.
            this._setDeleteAvailable(!!this.dom.messagesContainer.querySelector('[data-mid]'));
        }

        /**
         * Replaces the ID of an existing message bubble with the real ID from the server
         * and updates its timestamp if provided.
         * @param {number} oldId - The temporary (negative) ID of the optimistic bubble.
         * @param {object} msg - The canonical message object {id, time, content, ...}.
         * @returns {boolean} True if the node was found and updated, false otherwise.
         */
        updateMessageId(oldId, msg) {
            const node = this.dom.messagesContainer.querySelector(`[data-mid="${oldId}"]`);
            if (!node) {
                return false;
            }
            node.dataset.mid = msg.id;
            if (msg.time) {
                node.dataset.msgTime = String(msg.time);
            }
            const timeElm = node.querySelector('.message-time');
            if (timeElm) {
                timeElm.textContent = new Date((msg.time || 0) * 1000)
                    .toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            }
            // Keep optimistic guide lane tags; retag runs on the next applyMessageView.
            return true;
        }

        /**
         * Renders the entire message history, grouped by date.
         * @param {Array<object>} messages A sorted list of message objects.
         */
        async renderMessageHistory(messages) {
            // Rebuilding from scratch: every state derived from the rendered transcript
            // resets here, including the detached pending indicator (a stale reference
            // makes showPendingIndicator() a no-op forever).
            this.hidePendingIndicator();
            this.dom.messagesContainer.innerHTML = '';
            this.loadOlderSlot = null;
            this._loadingOlder = false;
            this.todaySeparatorAdded = false;
            this.pendingIndicator = null;
            this._setDeleteAvailable(false);

            const todayLabel = this.todayLabel || (await str.get_strings([{key: 'today'}]))[0];
            let lastDateLabel = null;
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            messages.forEach(msg => {
                const msgDate = new Date((msg.time || 0) * 1000);
                const msgDay = new Date(msgDate).setHours(0, 0, 0, 0);

                let dateLabel;
                if (msgDay === today.getTime()) {
                    dateLabel = todayLabel;
                } else {
                    dateLabel = msgDate.toLocaleDateString(undefined, {year: 'numeric', month: 'long', day: 'numeric'});
                }

                if (dateLabel !== lastDateLabel) {
                    this._appendDateSeparator(dateLabel);
                    lastDateLabel = dateLabel;
                    if (dateLabel === todayLabel) {
                        this.todaySeparatorAdded = true;
                    }
                }
                // Pass false to skip date checks; false to skip per-message scroll — we scroll once at end.
                this.appendMessage(msg, false, false);
            });
            this.applyMessageView();
            this.syncGuideCompletionButtons();
            this.scrollToBottom();
        }

        /**
         * Prepends older messages at the top of the list, preserving scroll position.
         * @param {Array<object>} messages Chronological list of message objects.
         */
        async prependMessages(messages) {
            if (!messages || !messages.length) {
                return;
            }

            const container = this.dom.messagesContainer;
            const prevScrollHeight = container.scrollHeight;
            this._ensureLoadOlderSlot();

            const [todayLabel] = await str.get_strings([{key: 'today'}]);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            let lastDateLabel = null;
            const fragment = document.createDocumentFragment();
            let lastPrependedDateLabel = null;

            messages.forEach(msg => {
                if (msg.id && this.hasMessage(msg.id)) {
                    return;
                }

                const dateLabel = this._dateLabelForMessage(msg, todayLabel, today);
                if (dateLabel !== lastDateLabel) {
                    fragment.appendChild(this._createDateSeparatorNode(dateLabel));
                    lastDateLabel = dateLabel;
                }
                lastPrependedDateLabel = dateLabel;

                const messageNode = this._createMessageNode(msg);
                if (!messageNode) {
                    return;
                }
                if (msg.id) {
                    messageNode.dataset.mid = msg.id;
                }
                this._annotateMessageLane(messageNode, msg);
                fragment.appendChild(messageNode);
            });

            let insertBefore = this._getFirstContentNode();
            if (insertBefore && lastPrependedDateLabel) {
                const firstExistingLabel = this._dateLabelForFirstContentNode(insertBefore, todayLabel, today);
                if (firstExistingLabel === lastPrependedDateLabel
                    && insertBefore.classList.contains('dixeo-tutor-separator')) {
                    insertBefore.remove();
                }
            }

            const anchor = this._getFirstContentNode();
            if (anchor && anchor.isConnected) {
                container.insertBefore(fragment, anchor);
            } else {
                container.appendChild(fragment);
            }

            this.applyMessageView();
            container.scrollTop += container.scrollHeight - prevScrollHeight;
        }

        /**
         * @param {object} msg
         * @param {string} todayLabel
         * @param {Date} today
         * @returns {string}
         * @private
         */
        _dateLabelForMessage(msg, todayLabel, today) {
            const msgDate = new Date((msg.time || 0) * 1000);
            const msgDay = new Date(msgDate).setHours(0, 0, 0, 0);
            if (msgDay === today.getTime()) {
                return todayLabel;
            }
            return msgDate.toLocaleDateString(undefined, {year: 'numeric', month: 'long', day: 'numeric'});
        }

        /**
         * @param {Element} node
         * @param {string} todayLabel
         * @param {Date} today
         * @returns {string|null}
         * @private
         */
        _dateLabelForFirstContentNode(node, todayLabel, today) {
            if (node.classList.contains('dixeo-tutor-separator')) {
                return node.querySelector('.text-muted')?.textContent?.trim() || null;
            }
            if (node.dataset.mid) {
                const timeEl = node.querySelector('.message-time');
                if (!timeEl) {
                    return null;
                }
                const msgDate = new Date(timeEl.textContent);
                if (Number.isNaN(msgDate.getTime())) {
                    return null;
                }
                return this._dateLabelForMessage({time: Math.floor(msgDate.getTime() / 1000)}, todayLabel, today);
            }
            return null;
        }

        /**
         * First message or separator node after the load-older slot.
         * @returns {Element|null}
         * @private
         */
        _getFirstContentNode() {
            const slot = this.loadOlderSlot;
            if (slot && slot.nextElementSibling) {
                return slot.nextElementSibling;
            }
            return this.dom.messagesContainer.querySelector('[data-mid], .dixeo-tutor-separator');
        }

        /**
         * @param {string} label
         * @returns {HTMLElement}
         * @private
         */
        _createDateSeparatorNode(label) {
            const div = document.createElement('div');
            div.className = 'w-100 d-flex align-items-center dixeo-tutor-separator my-2';
            div.innerHTML = `
                <hr class="flex-grow-1 mx-2">
                <span class="mx-2 text-muted small text-nowrap">${textUtils.escapeHtml(label)}</span>
                <hr class="flex-grow-1 mx-2">`;
            return div;
        }

        /**
         * Retrieves all messages currently rendered in the DOM.
         * @returns {Array<object>} Array of message objects with id and role.
         */
        getRenderedMessages() {
            const messageElements = this.dom.messagesContainer.querySelectorAll('[data-mid]');
            const messages = [];

            messageElements.forEach(element => {
                const id = element.dataset.mid;
                const messageDiv = element.querySelector('.dixeo-tutor-message');
                messages.push({id, role: this._roleFromMessageBubble(messageDiv)});
            });

            return messages;
        }

        /**
         * Map a rendered bubble to a conversation role.
         * Quiz review and custom lesson cards use the user alignment class but are system rows.
         *
         * @param {Element|null} messageDiv
         * @returns {string}
         * @private
         */
        _roleFromMessageBubble(messageDiv) {
            if (!messageDiv) {
                return 'user';
            }
            if (messageDiv.classList.contains('dixeo-tutor-message-system')
                    || messageDiv.classList.contains('dixeo-tutor-message--quiz-review')
                    || messageDiv.classList.contains('dixeo-tutor-message--custom-lesson')) {
                return 'system';
            }
            if (messageDiv.classList.contains('dixeo-tutor-message-assistant')) {
                return 'assistant';
            }
            return 'user';
        }

        /**
         * Gets the text content of a specific message by its ID.
         * @param {number} messageId The ID of the message to get content for.
         * @returns {string|null} The message content or null if not found.
         */
        getMessageContent(messageId) {
            const messageElement = this.dom.messagesContainer.querySelector(`[data-mid="${messageId}"]`);
            if (!messageElement) {
                return null;
            }
            const contentDiv = messageElement.querySelector('.dixeo-tutor-message-content');
            return contentDiv ? contentDiv.textContent : null;
        }

        /**
         * Appends a single message to the chat container.
         * @param {object} msg The message object to append.
         * @param {boolean} [checkDate=true] Whether to check for and add a date separator.
         * @param {boolean} [scroll=true] Whether to scroll to bottom after appending (set false when batch-rendering).
         */
        appendMessage(msg, checkDate = true, scroll = true) {
            // Idempotency guard — skip if already rendered.
            if (msg.id && this.hasMessage(msg.id)) {
                return;
            }

            if (checkDate && !this.todaySeparatorAdded) {
                // Use pre-fetched label if available, otherwise fall back to async fetch.
                if (this.todayLabel) {
                    this._appendDateSeparator(this.todayLabel);
                    this.todaySeparatorAdded = true;
                } else {
                    // Defensive fallback for cases where pre-fetch hasn't completed yet.
                    str.get_string('today', 'moodle').then(label => {
                        // Double-check that separator hasn't been added while we waited.
                        if (!this.todaySeparatorAdded) {
                            this._appendDateSeparator(label);
                            this.todaySeparatorAdded = true;
                        }
                        return null;
                    }).catch(() => {
                        if (!this.todaySeparatorAdded) {
                            this._appendDateSeparator('Today');
                            this.todaySeparatorAdded = true;
                        }
                    });
                }
            }

            const messageNode = this._createMessageNode(msg);
            if (!messageNode) {
                return;
            }
            if (msg.id) {
                messageNode.dataset.mid = msg.id;
                // The welcome message carries id 0 and is generated locally: nothing to erase.
                this._setDeleteAvailable(true);
            }

            this.dom.messagesContainer.appendChild(messageNode);
            this._annotateMessageLane(messageNode, msg);
            this.applyMessageView();
            this.syncGuideCompletionButtons();

            const sender = msg.role === 'user'
                ? (this.strings.senderYou || 'You')
                : (this.strings.senderAssistant || 'Assistant');
            const announcement = `${sender}: ${msg.content.substring(0, 100)}`;
            a11y.announce(announcement);

            if (scroll) {
                this.scrollToBottom();
            }
        }

        /**
         * Appends an error message with a retry button.
         * @param {string} originalMessage The message that failed to send.
         * @param {string} errorText The error text to display.
         */
        async appendErrorWithRetry(originalMessage, errorText) {
            const retryText = await str.get_string('retry', 'moodle');
            const errorNode = this._createNodeFromHTML(`
                <div class="d-flex justify-content-center mb-2">
                    <div class="dixeo-tutor-message dixeo-tutor-message-error alert alert-danger d-inline-flex flex-column">
                        <div>${textUtils.escapeHtml(errorText)}</div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2">
                            <i class="icon fa fa-refresh" aria-hidden="true"></i> ${textUtils.escapeHtml(retryText)}
                        </button>
                    </div>
                </div>`);

            errorNode.querySelector('button').addEventListener('click', () => {
                errorNode.remove();
                this.emit(constants.events.RETRY_SEND_MESSAGE, originalMessage);
            });

            this.dom.messagesContainer.appendChild(errorNode);
            this.scrollToBottom();
        }

        /**
         * Appends a simple error message without retry functionality.
         * @param {string} errorText The error text to display.
         */
        appendErrorMessage(errorText) {
            const errorNode = this._createNodeFromHTML(`
                <div class="d-flex justify-content-center mb-2">
                    <div class="dixeo-tutor-message dixeo-tutor-message-error alert alert-danger">
                        <div>${textUtils.escapeHtml(errorText)}</div>
                    </div>
                </div>`);
            this.dom.messagesContainer.appendChild(errorNode);
            this.scrollToBottom();
        }

        /**
         * Lock the composer so pending-reply enablement cannot reopen it (quiz/teach modes).
         *
         * @param {boolean} locked
         */
        setMessagingLocked(locked) {
            this._messagingLocked = !!locked;
            if (this._messagingLocked) {
                this.setInputEnabled(false);
            }
        }

        /**
         * Enables or disables the input field and send button without clearing pending indicators.
         *
         * @param {boolean} enabled
         */
        setInputEnabled(enabled) {
            if (!this.dom.inputField || !this.dom.sendButton) {
                return;
            }
            const allow = !!enabled && !this._messagingLocked;
            this.dom.inputField.disabled = !allow;
            this.dom.sendButton.disabled = !allow;
        }

        /**
         * Enables the input field and send button. Clears any temporary elements.
         */
        enableInput() {
            this._removeSystemMessage();
            this._removeConnectionLostBanner();
            this.setInputEnabled(true);
        }

        /**
         * Disables the input field and send button.
         */
        disableInput() {
            this.setInputEnabled(false);
        }

        /**
         * Hides and removes the pending reply indicator (typing dots).
         */
        hidePendingIndicator() {
            if (this.pendingIndicator) {
                this.pendingIndicator.remove();
                this.pendingIndicator = null;
            }
        }

        /**
         * Shows the pending reply indicator (typing dots).
         */
        showPendingIndicator() {
            if (this.pendingIndicator) {
                return;
            }
            this.pendingIndicator = this._createNodeFromHTML(`
                <div class="d-flex justify-content-start mb-2">
                    <div class="dixeo-tutor-message dixeo-tutor-message-assistant dixeo-tutor-loading">
                        <div class="chat-dots"><span></span><span></span><span></span></div>
                    </div>
                </div>`);
            this.dom.messagesContainer.appendChild(this.pendingIndicator);
            this.scrollToBottom();
        }

        /**
         * Whether the user has scrolled up from the bottom (to read older messages).
         * Reset to false when the user scrolls back to the bottom or when scrollToBottom is called.
         * @returns {boolean}
         */
        hasUserScrolledUp() {
            return this._userScrolledUp;
        }

        /**
         * Scrolls the message container to the very bottom.
         * Uses immediate scroll + rAF + scrollIntoView on last child so it works after load and when new messages arrive.
         * Also resets the user-scrolled-up tracker to false.
         */
        scrollToBottom() {
            this._userScrolledUp = false;
            const el = this.dom.messagesContainer;
            if (!el) {
                return;
            }
            const doScroll = () => {
                el.scrollTop = el.scrollHeight;
            };
            // Immediate scroll so we don't wait for rAF.
            doScroll();
            // After layout: scroll again and, if possible, scroll last element into view (handles late layout/images).
            requestAnimationFrame(() => {
                doScroll();
                const lastChild = el.lastElementChild;
                if (lastChild) {
                    lastChild.scrollIntoView({block: 'end', behavior: 'instant'});
                } else {
                    doScroll();
                }
                requestAnimationFrame(doScroll);
            });
        }

        /**
         * Whether quiz/teach CSS is currently hiding the chat pane.
         * Zero height alone is not enough: the popup/drawer is often unmeasured on first paint.
         *
         * @returns {boolean}
         * @private
         */
        _isChatPaneHiddenByMode() {
            const el = this.dom.messagesContainer;
            if (!el) {
                return false;
            }
            const chatPane = el.closest('.dixeo-tutor-chat-pane');
            return !!(chatPane && window.getComputedStyle(chatPane).display === 'none');
        }

        /**
         * Whether this page loaded already in a mode that hides the chat pane.
         *
         * @returns {boolean}
         * @private
         */
        _isBootModeHidingChat() {
            const root = this.dom.container;
            const mode = root && root.getAttribute('data-tutor-mode');
            return mode === 'quiz' || mode === 'teach';
        }

        /**
         * After the first conversation paint: defer scroll-to-bottom only when quiz/teach
         * hid the pane (or will, because that mode is already selected).
         */
        noteInitialHistoryPainted() {
            this._initialScrollPending = this._isChatPaneHiddenByMode() || this._isBootModeHidingChat();
        }

        /**
         * Remember the message-list offset before a quiz/lesson pane hides the chat.
         * No-op if the pane is already hidden, so nested overlays keep the original spot.
         */
        preserveMessagesScroll() {
            const el = this.dom.messagesContainer;
            if (!el || this._isChatPaneHiddenByMode()) {
                return;
            }
            this._savedMessagesScrollTop = el.scrollTop;
        }

        /**
         * Restore a saved offset after display:none would otherwise reset it.
         *
         * @private
         */
        _restoreMessagesScroll() {
            const el = this.dom.messagesContainer;
            if (!el || this._savedMessagesScrollTop === null) {
                return;
            }
            const top = this._savedMessagesScrollTop;
            this._savedMessagesScrollTop = null;
            const apply = () => {
                el.scrollTop = top;
            };
            apply();
            requestAnimationFrame(apply);
        }

        /**
         * Build a durable return-card target from a transcript row.
         *
         * @param {HTMLElement|null} row
         * @returns {{type: string, title?: string, description?: string, messageId?: string}|null}
         */
        cardTargetFromRow(row) {
            if (!row || row.nodeType !== 1) {
                return null;
            }
            const messageId = row.dataset.mid ? String(row.dataset.mid) : '';
            if (row.dataset.lane === 'guide-summary') {
                return {
                    type: 'guide',
                    title: String(row.dataset.guideTitle || ''),
                    description: String(row.dataset.guideDescription || ''),
                    messageId: messageId || undefined,
                };
            }
            let title = '';
            const rawEl = row.querySelector('.dixeo-tutor-message-content[data-raw]');
            if (rawEl && rawEl.dataset.raw) {
                try {
                    const parsed = JSON.parse(rawEl.dataset.raw);
                    title = parsed && parsed.title ? String(parsed.title) : '';
                } catch (e) {
                    title = '';
                }
            }
            if (row.classList.contains('dixeo-tutor-message-row--custom-lesson')) {
                return {type: 'lesson', title: title, messageId: messageId || undefined};
            }
            if (row.classList.contains('dixeo-tutor-message-row--quiz-review')) {
                return {type: 'quiz', title: title, messageId: messageId || undefined};
            }
            if (messageId) {
                return {type: 'message', messageId: messageId};
            }
            return null;
        }

        /**
         * Remember which transcript card opened a quiz/lesson/guide overlay.
         *
         * @param {HTMLElement|{type: string, title?: string, description?: string, messageId?: string}|null} rowOrTarget
         */
        setReturnToMessageRow(rowOrTarget) {
            if (!rowOrTarget) {
                this._returnToMessageRow = null;
                this._returnToCard = null;
                return;
            }
            if (rowOrTarget.nodeType === 1) {
                this._returnToMessageRow = rowOrTarget;
                this._returnToCard = this.cardTargetFromRow(rowOrTarget);
                return;
            }
            this._returnToMessageRow = null;
            this._returnToCard = rowOrTarget;
        }

        /**
         * @returns {{type: string, title?: string, description?: string, messageId?: string}|null}
         */
        getReturnToCard() {
            return this._returnToCard;
        }

        /**
         * Locate a return-card row currently in the transcript.
         *
         * @param {{type: string, title?: string, description?: string, messageId?: string}|null} [target]
         * @returns {HTMLElement|null}
         */
        findReturnCardRow(target) {
            const container = this.dom.messagesContainer;
            if (!container) {
                return null;
            }
            const spec = target || this._returnToCard;
            if (this._returnToMessageRow && this._returnToMessageRow.isConnected) {
                return this._returnToMessageRow;
            }
            if (!spec) {
                return null;
            }
            if (spec.messageId) {
                const mid = String(spec.messageId);
                const byId = Array.from(
                    container.querySelectorAll('.dixeo-tutor-message-row[data-mid]')
                ).find((row) => String(row.dataset.mid) === mid);
                if (byId) {
                    return byId;
                }
            }
            if (spec.type === 'guide' && spec.title) {
                return Array.from(
                    container.querySelectorAll('.dixeo-tutor-message-row[data-lane="guide-summary"]')
                ).find((row) => {
                    return String(row.dataset.guideTitle || '') === String(spec.title)
                        && String(row.dataset.guideDescription || '') === String(spec.description || '');
                }) || null;
            }
            if (spec.type === 'lesson' && spec.title) {
                return Array.from(
                    container.querySelectorAll('.dixeo-tutor-message-row--custom-lesson')
                ).find((row) => {
                    const t = this.cardTargetFromRow(row);
                    return t && String(t.title || '') === String(spec.title);
                }) || null;
            }
            if (spec.type === 'quiz' && spec.title) {
                return Array.from(
                    container.querySelectorAll('.dixeo-tutor-message-row--quiz-review')
                ).find((row) => {
                    const t = this.cardTargetFromRow(row);
                    return t && String(t.title || '') === String(spec.title);
                }) || null;
            }
            return null;
        }

        /**
         * Whether guide-lane turns for a return-card target are already painted.
         *
         * @param {{type: string, title?: string, description?: string}|null} target
         * @returns {boolean}
         * @private
         */
        _hasGuideSessionTurns(target) {
            const container = this.dom.messagesContainer;
            if (!container || !target || target.type !== 'guide' || !target.title) {
                return false;
            }
            return Array.from(
                container.querySelectorAll('.dixeo-tutor-message-row[data-lane="guide"]')
            ).some((row) => {
                return String(row.dataset.guideTitle || '') === String(target.title)
                    && String(row.dataset.guideDescription || '') === String(target.description || '');
            });
        }

        /**
         * Load older history until the return card is in the DOM, then scroll to it.
         *
         * @param {Object|null} chatController
         * @returns {Promise<boolean>}
         */
        async revealAndScrollReturnCard(chatController) {
            const target = this._returnToCard;
            const liveRow = this._returnToMessageRow;
            this._returnToMessageRow = null;

            const find = () => {
                if (liveRow && liveRow.isConnected) {
                    return liveRow;
                }
                return this.findReturnCardRow(target);
            };

            const isReady = () => {
                if (typeof this.applyMessageView === 'function') {
                    this.applyMessageView();
                }
                const row = find();
                if (!row) {
                    return false;
                }
                // Guide cards are synthesized; keep paging until session turns exist so
                // the card can be re-anchored chronologically (not stuck at the bottom).
                if (target && target.type === 'guide'
                        && chatController
                        && typeof chatController.state?.getHasMoreOlder === 'function'
                        && chatController.state.getHasMoreOlder()
                        && !this._hasGuideSessionTurns(target)) {
                    return false;
                }
                return true;
            };

            if (!isReady() && target && chatController
                    && typeof chatController.loadOlderUntil === 'function') {
                await chatController.loadOlderUntil(isReady);
            }

            this._returnToCard = null;
            const row = find();
            if (!row) {
                return false;
            }
            const apply = () => {
                if (row.isConnected) {
                    row.scrollIntoView({block: 'center', behavior: 'instant'});
                }
            };
            apply();
            requestAnimationFrame(apply);
            return true;
        }

        /**
         * Scroll the remembered card into view (if still in the DOM).
         * Prefer {@see revealAndScrollReturnCard} when older pages may be needed.
         *
         * @returns {boolean} True when a card was scrolled.
         */
        scrollReturnToMessageRow() {
            const row = this.findReturnCardRow(this._returnToCard);
            this._returnToMessageRow = null;
            this._returnToCard = null;
            if (!row || !row.isConnected) {
                return false;
            }
            const apply = () => {
                if (row.isConnected) {
                    row.scrollIntoView({block: 'center', behavior: 'instant'});
                }
            };
            apply();
            requestAnimationFrame(apply);
            return true;
        }

        /**
         * After a quiz/lesson pane closes: scroll to bottom only for the deferred initial
         * load. Prefer the card that opened the overlay (loading older pages if needed).
         *
         * @param {Object|null} [chatController]
         * @returns {Promise<boolean>}
         */
        async consumeInitialScrollPending(chatController) {
            if (this._isChatPaneHiddenByMode()) {
                return false;
            }
            if (this._initialScrollPending) {
                this._initialScrollPending = false;
                this._savedMessagesScrollTop = null;
                this._returnToMessageRow = null;
                this._returnToCard = null;
                this.scrollToBottom();
                return true;
            }
            if (this._returnToCard || this._returnToMessageRow) {
                this._savedMessagesScrollTop = null;
                return this.revealAndScrollReturnCard(chatController || null);
            }
            this._restoreMessagesScroll();
            return false;
        }

        /**
         * Automatically adjusts the height of the textarea based on its content.
         * @private
         */
        _adjustTextareaHeight() {
            const textarea = this.dom.inputField;
            textarea.style.height = 'auto';
            const maxHeight = constants.ui.TEXTAREA_MAX_HEIGHT;
            textarea.style.height = `${Math.min(textarea.scrollHeight, maxHeight)}px`;
            textarea.style.overflowY = textarea.scrollHeight > maxHeight ? 'auto' : 'hidden';
        }

        /**
         * Validates input field length and provides user feedback.
         * @private
         */
        _validateInputLength() {
            const textarea = this.dom.inputField;
            const currentLength = textarea.value.length;
            const maxLength = constants.ui.MAX_MESSAGE_LENGTH;

            const existingWarning = document.getElementById('dixeo-length-warning');
            if (existingWarning) {
                existingWarning.remove();
            }

            if (currentLength > maxLength) {
                // Hard-truncate to enforce the server-side limit client-side too.
                textarea.value = textarea.value.substring(0, maxLength);

                const warningElement = document.createElement('div');
                warningElement.id = 'dixeo-length-warning';
                warningElement.className = 'text-warning small mt-1';
                const msgTemplate = this.strings.messageTooLong || `Message cannot exceed ${maxLength} characters.`;
                warningElement.textContent = msgTemplate.replace('{a}', maxLength);
                textarea.parentNode.insertBefore(warningElement, textarea.nextSibling);

                setTimeout(() => {
                    if (warningElement.parentNode) {
                        warningElement.remove();
                    }
                }, 3000);
            }
        }

        /**
         * Creates and returns a DOM node for a message.
         * @param {object} msg The message object.
         * @returns {HTMLElement} The message DOM element.
         * @private
         */
        _createMessageNode(msg) {
            const parsedReview = practiceQuizReview.parseReviewMessage(msg);
            if (parsedReview) {
                return practiceQuizReview.createMessageNode(msg, parsedReview, this.strings);
            }

            const parsedLesson = customLessonPanel.parseCustomLessonMessage(msg);
            if (parsedLesson) {
                return customLessonPanel.createMessageNode(msg, parsedLesson, this.strings);
            }

            const time = new Date((msg.time || 0) * 1000).toLocaleTimeString([], constants.ui.TIME_FORMAT);
            const alignCls = msg.role === 'user' ? 'd-flex justify-content-end' : 'd-flex justify-content-start';
            const contentHtml = textUtils.resolveMessageContentHtml(msg);
            const ariaLabel = msg.role === 'user'
                ? (this.strings.yourMessage || 'Your message')
                : (this.strings.assistantMessage || 'Assistant message');
            const assistantCtx = guideSessionContext.parseAssistantContext(msg);
            const guideUnderstoodAttr = assistantCtx && assistantCtx.isUnderstood ? ' data-guide-understood="1"' : '';

            const row = this._createNodeFromHTML(`
                <div class="${alignCls} mb-2 dixeo-tutor-message-row"${guideUnderstoodAttr}>
                    <div class="dixeo-tutor-message dixeo-tutor-message-${msg.role}"
                         role="article"
                         aria-label="${ariaLabel}"
                         tabindex="0">
                        <div class="dixeo-tutor-message-content">${contentHtml}</div>
                        <div class="dixeo-tutor-message-footer">
                            <div class="dixeo-tutor-message-actions"></div>
                            <small class="message-time" aria-label="Sent at ${time}">${time}</small>
                        </div>
                    </div>
                </div>`);

            if (!(assistantCtx && assistantCtx.isUnderstood)) {
                this._attachMessageActions(row.querySelector('.dixeo-tutor-message'));
            }
            this._annotateMessageLane(row, msg);
            return row;
        }

        /**
         * Attach copy and TTS controls to a message bubble.
         * @param {HTMLElement|null} bubbleEl The .dixeo-tutor-message element.
         * @private
         */
        _attachMessageActions(bubbleEl) {
            if (!bubbleEl) {
                return;
            }
            messageActions.attach(bubbleEl, {
                copy: this.strings.ariaCopyMessage || 'Copy message',
                copied: this.strings.ariaMessageCopied || 'Copied',
                play: this.strings.ariaReadMessage || 'Read message aloud',
                stop: this.strings.ariaStopReading || 'Stop reading',
            });
        }

        /**
         * Appends a date separator to the message container.
         * @param {string} label The text label for the separator (e.g., "Today").
         * @private
         */
        _appendDateSeparator(label) {
            const separatorHtml = `
                <div class="w-100 d-flex align-items-center dixeo-tutor-separator my-2">
                    <hr class="flex-grow-1 mx-2">
                    <span class="mx-2 text-muted small text-nowrap">${textUtils.escapeHtml(label)}</span>
                    <hr class="flex-grow-1 mx-2">
                </div>`;
            this.dom.messagesContainer.insertAdjacentHTML('beforeend', separatorHtml);
        }

        /**
         * Removes the pending indicator and any system messages from the DOM.
         * Assistant messages use `dixeo-tutor-message-assistant`, never `dixeo-tutor-message-system`,
         * so unconditional removal is safe.
         * @private
         */
        _removeSystemMessage() {
            this.hidePendingIndicator();

            this.dom.messagesContainer.querySelectorAll('.dixeo-tutor-message-system').forEach(msg => {
                msg.remove();
            });
        }

        /**
         * Shows a connection lost banner in the chat area.
         * @private
         */
        _showConnectionLostBanner() {
            this._removeConnectionLostBanner();
            this.connectionLostBanner = document.createElement('div');
            this.connectionLostBanner.className = 'dixeo-tutor-connection-lost';
            this.connectionLostBanner.textContent = this.strings.connectionLost || 'Connection lost. Attempting to reconnect...';
            this.connectionLostBanner.setAttribute('role', 'alert');
            this.dom.messagesContainer.appendChild(this.connectionLostBanner);
            this.scrollToBottom();
        }

        /**
         * Removes the connection lost banner.
         * @private
         */
        _removeConnectionLostBanner() {
            if (this.connectionLostBanner) {
                this.connectionLostBanner.remove();
                this.connectionLostBanner = null;
            }
        }

        /**
         * Shows connection lost state in UI.
         */
        showConnectionLost() {
            this._showConnectionLostBanner();

            // Snapshot disabled state so hideConnectionLost() can restore it precisely.
            this.previousInputState = {
                inputDisabled: this.dom.inputField.disabled,
                buttonDisabled: this.dom.sendButton.disabled
            };

            this.dom.inputField.disabled = true;
            this.dom.sendButton.disabled = true;
        }

        /**
         * Hides connection lost state from UI.
         */
        hideConnectionLost() {
            this._removeConnectionLostBanner();

            if (this.previousInputState) {
                const wasEnabled = !this.previousInputState.inputDisabled
                    && !this.previousInputState.buttonDisabled;
                this.setInputEnabled(wasEnabled);
                this.previousInputState = null;
            }
        }

        /**
         * Cleanup method to prevent memory leaks.
         * Removes pending UI elements and clears references.
         */
        destroy() {
            messageActions.stop();
            this._removeSystemMessage();
            this._removeConnectionLostBanner();

            if (this.dom.messagesContainer && this._boundScrollHandler) {
                this.dom.messagesContainer.removeEventListener('scroll', this._boundScrollHandler);
                this._boundScrollHandler = null;
            }

            if (this.ariaLiveRegion) {
                this.ariaLiveRegion.remove();
                this.ariaLiveRegion = null;
            }

            this.dom = null;
            this.pendingIndicator = null;
            this.loadOlderSlot = null;
            this.connectionLostBanner = null;
            this.previousInputState = null;
            this.strings = null;

            this.removeAllListeners();
        }
    };
});
