define([
    'core/str',
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/event_emitter',
    'block_dixeo_tutor/a11y',
    'block_dixeo_tutor/text_utils',
    'block_dixeo_tutor/message_actions',
    'block_dixeo_tutor/practice_quiz_review',
    'block_dixeo_tutor/custom_lesson_panel',
], function(str, constants, EventEmitter, a11y, textUtils, messageActions, practiceQuizReview, customLessonPanel) {
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
            };
            this.pendingIndicator = null;
            this.loadOlderSlot = null;
            this._hasMoreOlder = false;
            this._loadingOlder = false;
            // Tracks whether the "Today" date separator has been added this session.
            this.todaySeparatorAdded = false;
            // User scrolled up to read older messages; reset when at bottom or scrollToBottom is called.
            this._userScrolledUp = false;
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
            ]).then(([
                senderYou, senderAssistant, connLost, yourMsg, assistantMsg,
                tooLong, loadOlder, ariaLoadOlder, readMsg, stopReading, copyMsg, copiedMsg,
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
            });

            this._initialize();
        }

        /**
         * Creates a DOM element from an HTML string.
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
            const timeElm = node.querySelector('.message-time');
            if (timeElm) {
                timeElm.textContent = new Date((msg.time || 0) * 1000)
                    .toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            }
            return true;
        }

        /**
         * Renders the entire message history, grouped by date.
         * @param {Array<object>} messages A sorted list of message objects.
         */
        async renderMessageHistory(messages) {
            this.hidePendingIndicator();
            this.dom.messagesContainer.innerHTML = '';
            this.loadOlderSlot = null;
            this._loadingOlder = false;
            this.todaySeparatorAdded = false;

            const [todayLabel] = await str.get_strings([{key: 'today'}]);
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
                if (msg.id) {
                    messageNode.dataset.mid = msg.id;
                }
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
                let role = 'user';

                if (messageDiv) {
                    if (messageDiv.classList.contains('dixeo-tutor-message-user')) {
                        role = 'user';
                    } else if (messageDiv.classList.contains('dixeo-tutor-message-assistant')) {
                        role = 'assistant';
                    }
                }

                messages.push({id, role});
            });

            return messages;
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
            if (msg.id) {
                messageNode.dataset.mid = msg.id;
            }

            this.dom.messagesContainer.appendChild(messageNode);

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
         * Enables or disables the input field and send button without clearing pending indicators.
         *
         * @param {boolean} enabled
         */
        setInputEnabled(enabled) {
            this.dom.inputField.disabled = !enabled;
            this.dom.sendButton.disabled = !enabled;
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
                const row = practiceQuizReview.createMessageNode(msg, parsedReview, this.strings);
                this._attachMessageActions(row.querySelector('.dixeo-tutor-message'));
                return row;
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

            const row = this._createNodeFromHTML(`
                <div class="${alignCls} mb-2 dixeo-tutor-message-row">
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

            this._attachMessageActions(row.querySelector('.dixeo-tutor-message'));
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
                this.dom.inputField.disabled = this.previousInputState.inputDisabled;
                this.dom.sendButton.disabled = this.previousInputState.buttonDisabled;
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
