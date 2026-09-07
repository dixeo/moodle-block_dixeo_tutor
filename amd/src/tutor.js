define([
    'block_dixeo_tutor/chat_controller',
    'block_dixeo_tutor/chat_state',
    'block_dixeo_tutor/chat_ui',
    'block_dixeo_tutor/chat_api',
    'block_dixeo_tutor/tutor_panel_resize',
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/unread_indicator',
    'block_dixeo_tutor/tutor_mode_controller',
], function(
    ChatController,
    ChatState,
    ChatUI,
    ChatAPI,
    tutorPanelResize,
    constants,
    unreadIndicator,
    TutorModeController
) {
    'use strict';

    /**
     * @returns {boolean}
     */
    function isDrawerTutorVisible() {
        const block = document.querySelector('#block-region-side-pre .block_dixeo_tutor');
        if (!block || block.classList.contains('d-none')) {
            return false;
        }
        const drawer = block.closest('.drawer.drawer-right');
        if (drawer) {
            return drawer.classList.contains('show');
        }
        return true;
    }

    /**
     * Opens the tutor drawer or popup when it is not already visible.
     * @param {object} ui ChatUI instance.
     * @param {{open: function, isOpen: function}|null} popupApi
     */
    function openTutorIfClosed(ui, popupApi) {
        if (popupApi) {
            if (!popupApi.isOpen()) {
                popupApi.open();
            } else if (ui && typeof ui.scrollToBottom === 'function' && !ui.hasUserScrolledUp()) {
                ui.scrollToBottom();
            }
            return;
        }
        if (isDrawerTutorVisible()) {
            if (ui && typeof ui.scrollToBottom === 'function' && !ui.hasUserScrolledUp()) {
                ui.scrollToBottom();
            }
            return;
        }
        const launcher = document.getElementById('dixeo-chat-toggle-btn');
        if (launcher) {
            launcher.click();
            return;
        }
        const openDrawerButton = document.querySelector('.drawer-right-toggle button');
        if (openDrawerButton) {
            openDrawerButton.click();
        }
    }

    /**
     * @param {object} ui
     * @param {{isOpen: function}|null} popupApi
     * @param {{markReadUpTo: function}} unreadApi
     */
    function bindProactiveAutoOpen(ui, popupApi, unreadApi) {
        window.addEventListener(constants.events.PROACTIVE_REPLY_READY, function(e) {
            const lastIncomingTime = parseInt(e.detail?.lastIncomingTime, 10) || 0;
            if (lastIncomingTime > 0) {
                unreadApi.markReadUpTo(lastIncomingTime);
            }
            openTutorIfClosed(ui, popupApi);
        });
    }

    /**
     * Move the tutor block out of the drawer into a popup container and add floating toggle button.
     * @param {object} ui The ChatUI instance (used to scroll to bottom after move).
     * @param {function} onTutorOpened Called when the user opens the popup.
     * @param {string} openTooltip Tooltip when tutor is closed.
     * @param {string} hideTooltip Tooltip when tutor is open.
     * @returns {{open: function, isOpen: function}|null}
     */
    function initPopup(ui, onTutorOpened, openTooltip, hideTooltip) {
        const tutorEl = document.getElementById('dixeo-tutor');
        if (!tutorEl) {
            return null;
        }
        const blockWrapper = tutorEl.closest('section[data-block="dixeo_tutor"]');
        if (!blockWrapper) {
            return null;
        }

        const popupContainer = document.createElement('div');
        popupContainer.id = 'dixeo-tutor-popup-container';
        popupContainer.className = 'dixeo-tutor-popup-container';
        popupContainer.setAttribute('aria-hidden', 'true');
        blockWrapper.parentNode.removeChild(blockWrapper);
        popupContainer.appendChild(blockWrapper);
        document.body.appendChild(popupContainer);

        tutorPanelResize.createPanelResize({
            panel: blockWrapper,
            defaultWidth: 380,
            minWidth: 320,
            viewportMarginPx: 48,
            panelCssVarName: '--dixeo-tutor-popup-width',
        });

        // Moving the node can reset the message container scroll; scroll to bottom after attach.
        if (ui && typeof ui.scrollToBottom === 'function') {
            ui.scrollToBottom();
        }

        const openIconHtml = '<i class="fa fa-comment" aria-hidden="true"></i>';
        const closeIconHtml = '<i class="fa fa-times" aria-hidden="true"></i>';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dixeo-tutor-popup-btn';
        btn.setAttribute('aria-label', openTooltip);
        btn.title = openTooltip;
        btn.innerHTML = openIconHtml;
        (document.getElementById('page-footer') || document.body).appendChild(btn);

        let backdropPointerStartedOnContainer = false;
        let suppressBackdropCloseUntil = 0;

        window.addEventListener('dixeo-tutor-user-sent-message', function() {
            suppressBackdropCloseUntil = Date.now() + 2000;
        });

        popupContainer.addEventListener('pointerdown', function(e) {
            backdropPointerStartedOnContainer = (e.target === popupContainer);
        }, true);

        /**
         * @param {boolean} open
         */
        function setButtonState(open) {
            popupContainer.classList.toggle('dixeo-tutor-popup-visible', open);
            popupContainer.setAttribute('aria-hidden', String(!open));
            btn.title = open ? hideTooltip : openTooltip;
            btn.setAttribute('aria-label', open ? hideTooltip : openTooltip);
            btn.innerHTML = open ? closeIconHtml : openIconHtml;
        }

        btn.addEventListener('click', function() {
            const willOpen = !popupContainer.classList.contains('dixeo-tutor-popup-visible');
            setButtonState(willOpen);
            if (willOpen) {
                onTutorOpened();
                if (!ui.hasUserScrolledUp()) {
                    ui.scrollToBottom();
                }
            }
        });

        popupContainer.addEventListener('click', function(e) {
            if (Date.now() < suppressBackdropCloseUntil) {
                return;
            }
            if (e.target === popupContainer && backdropPointerStartedOnContainer) {
                setButtonState(false);
            }
            backdropPointerStartedOnContainer = false;
        });

        setButtonState(false);

        return {
            open: () => {
                setButtonState(true);
                onTutorOpened();
            },
            isOpen: () => popupContainer.classList.contains('dixeo-tutor-popup-visible'),
        };
    }

    return {
        /**
         * Initializes the entire tutor application.
         * @param {number} courseid The ID of the current course.
         * @param {number} userid The ID of the current user.
         * @param {string} [displaymode] 'drawer' or 'popup'. Default 'popup'.
         * @param {string} [openTooltip] Tooltip for opening the tutor (popup mode).
         * @param {string} [hideTooltip] Tooltip for hiding the tutor (popup mode).
         * @param {number} [lastread] Last-read incoming watermark from server (Unix seconds).
         * @param {boolean} [practicequizavailable] Whether mod_simplequiz2 is installed.
         */
        init: function(courseid, userid, displaymode, openTooltip, hideTooltip, lastread, practicequizavailable) {
            const state = new ChatState(courseid, userid);
            const ui = new ChatUI();

            const modeController = new TutorModeController({
                courseid: courseid,
                quizAvailable: !!practicequizavailable,
            });
            modeController.setMessagingLockHandler((locked) => {
                if (locked) {
                    ui.disableInput();
                } else if (!state.isPending()) {
                    ui.setInputEnabled(true);
                }
            });

            const controller = new ChatController(state, ui, new ChatAPI(), modeController);

            const unreadCallbacks = {
                onTutorOpened: function() {
                    // Replaced after unreadIndicator.init().
                },
            };
            const popupApi = (displaymode === 'popup' && openTooltip && hideTooltip)
                ? initPopup(ui, function() {
                    unreadCallbacks.onTutorOpened();
                }, openTooltip, hideTooltip)
                : null;

            const unreadApi = unreadIndicator.init(courseid, lastread || 0, popupApi);
            unreadCallbacks.onTutorOpened = unreadApi.onTutorOpened;

            bindProactiveAutoOpen(ui, popupApi, unreadApi);
            controller.initialize();

            let quizController = null;
            let teachController = null;
            if (practicequizavailable) {
                require([
                    'block_dixeo_tutor/practice_quiz_controller',
                    'block_dixeo_tutor/practice_quiz_review',
                ], function(PracticeQuizController, practiceQuizReview) {
                    quizController = new PracticeQuizController({
                        courseid: courseid,
                        userid: userid,
                        ui: ui,
                        state: state,
                        modeController: modeController,
                    });
                    practiceQuizReview.wireActions({quizController: quizController});
                    modeController.setQuizController(quizController);
                });
            }

            require([
                'block_dixeo_tutor/teach_controller',
                'block_dixeo_tutor/custom_lesson_panel',
            ], function(TeachController, customLessonPanel) {
                teachController = new TeachController({
                    courseid: courseid,
                    userid: userid,
                    ui: ui,
                    state: state,
                    modeController: modeController,
                });
                customLessonPanel.wireActions({teachController: teachController});
                modeController.setTeachController(teachController);
            });

            const container = document.getElementById('dixeo-tutor');
            if (container) {
                const observer = new MutationObserver(function() {
                    if (!document.body.contains(container)) {
                        controller.destroy();
                        if (quizController) {
                            quizController.destroy();
                        }
                        if (teachController) {
                            teachController.destroy();
                        }
                        observer.disconnect();
                    }
                });
                observer.observe(document.body, {childList: true, subtree: true});
            }

            window.addEventListener('pagehide', function() {
                controller.destroy();
                if (quizController) {
                    quizController.destroy();
                }
                if (teachController) {
                    teachController.destroy();
                }
            });
        }
    };
});
