define([
    'core/templates',
    'core/str',
    'block_dixeo_tutor/text_utils',
], function(Templates, str, textUtils) {
    'use strict';

    /** Must match {@see \block_dixeo_tutor\service\tutor_context_schema::SCHEMA_PRACTICE_QUIZ_REVIEW}. */
    const SCHEMA_PRACTICE_QUIZ_REVIEW = 'practice_quiz_review';
    const OPTIMISTIC_REVIEW_ID_PREFIX = 'temp-quiz-review-';

    const STRING_KEYS = [
        'quiz_review_best_score',
        'quiz_review_exit_score',
        'quiz_review_correct',
        'quiz_review_incorrect',
        'quiz_review_your_answer',
        'quiz_review_correct_answer',
        'quiz_review_feedback',
        'quiz_review_retake',
    ];

    let stringsPromise = null;
    let templatePrefetched = false;
    let cachedStrings = null;
    /** @type {{quizController: object|null}} */
    let actionDeps = {quizController: null};

    /**
     * Preload review panel strings and Mustache template.
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
                    'quiz_review_best_score': 'Best score: {$a->score}/{$a->total} ({$a->percent}%)',
                    'quiz_review_exit_score': 'This attempt: {$a->score}/{$a->total} ({$a->percent}%)',
                    'quiz_review_correct': 'Correct',
                    'quiz_review_incorrect': 'Incorrect',
                    'quiz_review_your_answer': 'Your answer',
                    'quiz_review_correct_answer': 'Correct answer',
                    'quiz_review_feedback': 'Feedback',
                    'quiz_review_retake': 'Retake quiz',
                };
                return cachedStrings;
            });
        }
        if (!templatePrefetched) {
            templatePrefetched = true;
            Templates.prefetchTemplates(['block_dixeo_tutor/practice_quiz_review']);
        }
        return stringsPromise;
    }

    /**
     * @param {number} score
     * @param {number} total
     * @returns {number}
     */
    function scorePercent(score, total) {
        if (!total) {
            return 0;
        }
        return Math.round((score / total) * 100);
    }

    /**
     * @param {string} template Lang string with {$a->key} placeholders.
     * @param {object} params
     * @returns {string}
     */
    function formatString(template, params) {
        let out = template || '';
        Object.keys(params).forEach(function(key) {
            out = out.replace(new RegExp('\\{\\$a->' + key + '\\}', 'g'), String(params[key]));
        });
        return out;
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
     * @param {object} message Message with context.schema.
     * @returns {boolean}
     */
    function isReviewMessage(message) {
        if (!message) {
            return false;
        }
        return String(message.role || '').toLowerCase() === 'system'
            && contextSchema(message) === SCHEMA_PRACTICE_QUIZ_REVIEW;
    }

    /**
     * Extract review payload from a system message.
     *
     * @param {object} message
     * @returns {object|null}
     */
    function getReviewData(message) {
        if (!isReviewMessage(message)) {
            return null;
        }
        const ctx = message.context;
        if (!ctx) {
            return null;
        }
        if (typeof ctx === 'string') {
            try {
                const parsed = JSON.parse(ctx);
                return parsed && parsed.schema === 'practice_quiz_review' ? parsed : null;
            } catch (e) {
                return null;
            }
        }
        if (ctx.schema === 'practice_quiz_review') {
            return ctx;
        }
        return null;
    }

    /**
     * @param {object} message
     * @returns {{version: number, data: object}|null}
     */
    function parseReviewMessage(message) {
        const data = getReviewData(message);
        if (!data) {
            return null;
        }
        return {
            version: data.version || 1,
            data: data,
        };
    }

    /**
     * @param {string} html
     * @returns {string}
     */
    function htmlToPlain(html) {
        return textUtils.htmlToPlain(html);
    }

    /**
     * @param {object} question
     * @param {number[]} ids
     * @returns {string[]}
     */
    function answerTextsForIds(question, ids) {
        const texts = [];
        ids.forEach(function(id) {
            const answer = question.answers && question.answers[id];
            if (!answer) {
                return;
            }
            const plain = htmlToPlain(answer.text || '');
            if (plain) {
                texts.push(plain);
            }
        });
        return texts;
    }

    /**
     * @param {object} question
     * @returns {string[]}
     */
    function correctAnswerTexts(question) {
        const texts = [];
        (question.answers || []).forEach(function(answer) {
            if (answer && (answer.iscorrect === 1 || answer.iscorrect === true)) {
                const plain = htmlToPlain(answer.text || '');
                if (plain) {
                    texts.push(plain);
                }
            }
        });
        return texts;
    }

    /**
     * Grade selected answers for one question (mirrors question_grading_service).
     *
     * @param {object} question
     * @param {number[]} selectedIds
     * @returns {{isCorrect: boolean, hasPartial: boolean}}
     */
    function gradeQuestion(question, selectedIds) {
        const answers = question.answers || [];
        let isCorrect = true;
        const results = {};

        if (!selectedIds || selectedIds.length === 0) {
            return {isCorrect: false, hasPartial: false};
        }

        answers.forEach(function(answer, answerId) {
            if (!answer) {
                return;
            }
            const answerIsCorrect = answer.iscorrect === 1 || answer.iscorrect === true;
            const isSelected = selectedIds.indexOf(answerId) !== -1;

            if (answerIsCorrect && !isSelected) {
                isCorrect = false;
                return;
            }
            if (!answerIsCorrect && isSelected) {
                isCorrect = false;
                results[answerId] = false;
                return;
            }
            if (isSelected) {
                results[answerId] = true;
            }
        });

        let hasPartial = false;
        if (!isCorrect) {
            Object.keys(results).forEach(function(key) {
                if (results[key] === true) {
                    hasPartial = true;
                }
            });
        }

        return {isCorrect: isCorrect, hasPartial: hasPartial};
    }

    /**
     * @param {boolean} isCorrect
     * @param {boolean} hasPartial
     * @returns {'correct'|'partial'|'incorrect'}
     */
    function feedbackOutcomeFromGrading(isCorrect, hasPartial) {
        if (isCorrect) {
            return 'correct';
        }
        if (hasPartial) {
            return 'partial';
        }
        return 'incorrect';
    }

    /**
     * @param {object} question
     * @param {{isCorrect: boolean, hasPartial: boolean}} grading
     * @returns {{plain: string, html: string}}
     */
    function feedbackForGrading(question, grading) {
        const outcome = feedbackOutcomeFromGrading(grading.isCorrect, grading.hasPartial);
        let raw = '';
        if (outcome === 'correct') {
            raw = question.correctfeedback || '';
        } else if (outcome === 'partial') {
            raw = question.partiallycorrectfeedback || '';
        } else {
            raw = question.incorrectfeedback || '';
        }
        return {
            plain: htmlToPlain(raw),
            html: String(raw).trim(),
        };
    }

    /**
     * Build review JSON from the client submit payload (mirrors server shape for rendering).
     *
     * @param {object} payload title, questionsjson, bestattemptjson, exitscore, total.
     * @returns {object|null}
     */
    function buildReviewDataFromPayload(payload) {
        let questions = [];
        let best = {};
        try {
            questions = JSON.parse(payload.questionsjson || '[]');
            best = JSON.parse(payload.bestattemptjson || '{}');
        } catch (e) {
            return null;
        }
        if (!Array.isArray(questions) || questions.length === 0) {
            return null;
        }

        const total = parseInt(payload.total, 10) || questions.length;
        const exitscore = parseInt(payload.exitscore, 10);
        const bestscore = parseInt(best.score, 10) || 0;

        const items = questions.map(function(question, index) {
            const selectedIds = (best.selectedAnswerIds && best.selectedAnswerIds[index]) || [];
            const grading = gradeQuestion(question, selectedIds);
            const isCorrect = (best.answerResults && typeof best.answerResults[index] === 'boolean')
                ? best.answerResults[index]
                : grading.isCorrect;
            const feedback = feedbackForGrading(question, grading);
            return {
                index: index,
                question: htmlToPlain(question.text || ''),
                isCorrect: isCorrect,
                selected: answerTextsForIds(question, selectedIds),
                correct: correctAnswerTexts(question),
                feedback: feedback.plain,
                feedbackHtml: feedback.html,
            };
        });

        return {
            schema: 'practice_quiz_review',
            version: 2,
            title: payload.title || '',
            questionsJson: payload.questionsjson || '',
            bestAttempt: {
                score: bestscore,
                total: total,
                answerResults: best.answerResults || [],
                selectedAnswerIds: best.selectedAnswerIds || [],
            },
            exitAttempt: {score: isNaN(exitscore) ? bestscore : exitscore, total: total},
            questions: items,
        };
    }

    /**
     * Remove any optimistic quiz review bubble before appending the canonical server message.
     *
     * @param {Object} ui ChatUI
     */
    function removeOptimisticMessage(ui) {
        if (!ui || !ui.dom || !ui.dom.messagesContainer) {
            return;
        }
        ui.dom.messagesContainer.querySelectorAll('.dixeo-tutor-message-row--quiz-review').forEach(function(row) {
            const mid = row.dataset.mid || '';
            if (mid.indexOf(OPTIMISTIC_REVIEW_ID_PREFIX) === 0) {
                row.remove();
            }
        });
    }

    /**
     * Show the review panel immediately using local quiz data.
     *
     * @param {Object} ui ChatUI
     * @param {object} payload Review submit payload.
     * @returns {string|null} Temporary message id, or null if render skipped.
     */
    function appendOptimisticMessage(ui, payload) {
        const data = buildReviewDataFromPayload(payload);
        if (!data || !ui || typeof ui.appendMessage !== 'function') {
            return null;
        }
        removeOptimisticMessage(ui);
        const tempId = OPTIMISTIC_REVIEW_ID_PREFIX + Date.now();
        ui.appendMessage({
            id: tempId,
            role: 'system',
            context: data,
            content: '',
            time: Math.floor(Date.now() / 1000),
        });
        return tempId;
    }

    /**
     * @param {string[]} items
     * @returns {string}
     */
    function joinList(items) {
        if (!Array.isArray(items) || items.length === 0) {
            return '—';
        }
        return items.join(', ');
    }

    /**
     * @param {object} data Parsed review payload.
     * @param {object} [strings] Localized label strings.
     * @returns {object} Mustache context.
     */
    function buildTemplateContext(data, strings) {
        const labels = strings || cachedStrings || {};
        const best = data.bestAttempt || {};
        const exit = data.exitAttempt || {};
        const bestScore = best.score ?? 0;
        const bestTotal = best.total ?? 0;
        const exitScore = exit.score ?? 0;
        const exitTotal = exit.total ?? bestTotal;
        const showExitScore = exitScore !== bestScore;

        const questions = (data.questions || []).map(function(q, idx) {
            const feedbackHtml = q.feedbackHtml || '';
            const feedbackPlain = q.feedback || '';
            return {
                displayIndex: (q.index ?? idx) + 1,
                question: q.question || '',
                isCorrect: !!q.isCorrect,
                statusLabel: q.isCorrect
                    ? (labels.quiz_review_correct || 'Correct')
                    : (labels.quiz_review_incorrect || 'Incorrect'),
                selectedText: joinList(q.selected),
                correctText: joinList(q.correct),
                yourAnswerLabel: labels.quiz_review_your_answer || 'Your answer',
                correctAnswerLabel: labels.quiz_review_correct_answer || 'Correct answer',
                feedbackLabel: labels.quiz_review_feedback || 'Feedback',
                hasFeedback: !!(feedbackHtml || feedbackPlain),
                feedbackHtml: feedbackHtml || textUtils.escapeHtml(feedbackPlain).replace(/\n/g, '<br>'),
            };
        });

        return {
            version: data.version || 1,
            title: data.title || '',
            canRetake: !!(data.questionsJson),
            retakeLabel: labels.quiz_review_retake || 'Retake quiz',
            bestScoreLabel: formatString(labels.quiz_review_best_score, {
                score: bestScore,
                total: bestTotal,
                percent: scorePercent(bestScore, bestTotal),
            }),
            exitScoreLabel: formatString(labels.quiz_review_exit_score, {
                score: exitScore,
                total: exitTotal,
                percent: scorePercent(exitScore, exitTotal),
            }),
            showExitScore: showExitScore,
            questions: questions,
        };
    }

    /**
     * Plain-text fallback when template render fails.
     *
     * @param {object} data Parsed review payload.
     * @returns {string} Escaped HTML.
     */
    function renderFallbackHtml(data) {
        const best = data.bestAttempt || {};
        const lines = [];
        const bestScore = best.score ?? 0;
        const bestTotal = best.total ?? 0;
        lines.push((data.title || 'Practice quiz') + ' — ' + bestScore + '/' + bestTotal +
            ' (' + scorePercent(bestScore, bestTotal) + '%)');
        (data.questions || []).forEach(function(q, idx) {
            const num = (q.index ?? idx) + 1;
            const status = q.isCorrect ? 'Correct' : 'Incorrect';
            lines.push(num + '. [' + status + '] ' + (q.question || ''));
            lines.push('   Your answer: ' + joinList(q.selected));
            if (!q.isCorrect) {
                lines.push('   Correct: ' + joinList(q.correct));
            }
            if (q.feedback) {
                lines.push('   Feedback: ' + q.feedback);
            }
        });
        return '<pre class="dixeo-practice-quiz-review-fallback">' +
            textUtils.escapeHtml(lines.join('\n')) + '</pre>';
    }

    /**
     * Wire retake action on a rendered review panel.
     *
     * @param {HTMLElement} panelEl
     * @param {object} data Parsed review payload.
     */
    function wireReviewActions(panelEl, data) {
        if (!panelEl || !data || !data.questionsJson) {
            return;
        }
        const retakeBtn = panelEl.querySelector('[data-action="retake-quiz"]');
        if (!retakeBtn) {
            return;
        }
        retakeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const controller = actionDeps.quizController;
            if (controller && typeof controller.retakeQuizFromContext === 'function') {
                controller.retakeQuizFromContext(data);
            }
        });
    }

    /**
     * Render review HTML into a content element.
     *
     * @param {HTMLElement} contentEl Message content container.
     * @param {object} data Parsed review payload.
     */
    function renderInto(contentEl, data) {
        if (!contentEl || !data) {
            return;
        }
        contentEl.dataset.raw = JSON.stringify(data);
        contentEl.innerHTML = renderFallbackHtml(data);

        preload().then(function(strings) {
            const context = buildTemplateContext(data, strings);
            return Templates.render('block_dixeo_tutor/practice_quiz_review', context);
        }).then(function(html, js) {
            if (html) {
                contentEl.innerHTML = html;
                const panel = contentEl.querySelector('.dixeo-practice-quiz-review');
                if (panel) {
                    wireReviewActions(panel, data);
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
     * Create a chat message row shell for a practice quiz review.
     *
     * @param {object} msg Message object.
     * @param {{version: number, data: object}} parsed Parsed review.
     * @param {object} uiStrings Chat UI aria strings.
     * @returns {HTMLElement}
     */
    function createMessageNode(msg, parsed, uiStrings) {
        const time = new Date((msg.time || 0) * 1000).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        const alignCls = (msg.role === 'user' || contextSchema(msg) === SCHEMA_PRACTICE_QUIZ_REVIEW)
            ? 'd-flex justify-content-end'
            : 'd-flex justify-content-start';
        const ariaLabel = (msg.role === 'user' || contextSchema(msg) === SCHEMA_PRACTICE_QUIZ_REVIEW)
            ? (uiStrings.yourMessage || 'Your message')
            : (uiStrings.assistantMessage || 'Assistant message');

        const row = document.createElement('div');
        row.className = alignCls + ' mb-2 dixeo-tutor-message-row dixeo-tutor-message-row--quiz-review';
        row.innerHTML =
            '<div class="dixeo-tutor-message dixeo-tutor-message-user dixeo-tutor-message--quiz-review"' +
            ' role="article" aria-label="' + textUtils.escapeHtml(ariaLabel) + '" tabindex="0">' +
            '<div class="dixeo-tutor-message-content"></div>' +
            '<div class="dixeo-tutor-message-footer">' +
            '<div class="dixeo-tutor-message-actions"></div>' +
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
     * @param {{quizController: object|null}} deps
     */
    function wireActions(deps) {
        actionDeps = deps || {quizController: null};
    }

    preload();

    return {
        SCHEMA_PRACTICE_QUIZ_REVIEW,
        isReviewMessage,
        getReviewData,
        parseReviewMessage,
        buildReviewDataFromPayload,
        removeOptimisticMessage,
        appendOptimisticMessage,
        renderInto,
        renderFallbackHtml,
        createMessageNode,
        wireReviewActions,
        wireActions,
        preload,
    };
});
