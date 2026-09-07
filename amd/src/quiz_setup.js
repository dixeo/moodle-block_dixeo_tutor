define([
    'core/str',
    'core/templates',
    'block_dixeo_tutor/tutor_setup_core',
    'block_dixeo_tutor/setup_language',
], function(str, Templates, tutorSetupCore, setupLanguage) {
    'use strict';

    const DEFAULT_COUNT = 5;
    const DEFAULT_DIFFICULTY = 'medium';
    const SETUP_CLASS = 'dixeo-quiz-setup dixeo-tutor-setup';

    const QUIZ_LANGUAGE_FIELD = {
        languageselectid: 'dixeo-quiz-setup-language',
        labelclass: 'dixeo-tutor-setup__label dixeo-quiz-setup__label',
        selectclass: 'dixeo-quiz-setup__language-select',
    };

    const buildCountOptions = function(count) {
        return [3, 5, 7, 10].map(function(n) {
            return {value: n, active: n === count};
        });
    };

    const buildDifficultyFlags = function(difficulty) {
        return {
            'difficulty_easy_active': difficulty === 'easy',
            'difficulty_medium_active': difficulty === 'medium',
            'difficulty_hard_active': difficulty === 'hard',
        };
    };

    const readCountAndDifficulty = function(panel) {
        let count = DEFAULT_COUNT;
        let difficulty = DEFAULT_DIFFICULTY;

        const activeCount = panel.querySelector('[data-role="count-row"] button.active');
        if (activeCount) {
            count = parseInt(activeCount.dataset.count, 10);
        }

        const activeDiff = panel.querySelector('[data-role="diff-row"] button.active');
        if (activeDiff) {
            difficulty = activeDiff.dataset.difficulty;
        }

        return {count: count, difficulty: difficulty};
    };

    const bindCountAndDifficulty = function(panel) {
        panel.querySelector('[data-role="count-row"]').addEventListener('click', function(e) {
            const btn = e.target.closest('button[data-count]');
            if (!btn) {
                return;
            }
            panel.querySelectorAll('[data-role="count-row"] button').forEach(function(b) {
                b.classList.toggle('active', b === btn);
            });
        });

        panel.querySelector('[data-role="diff-row"]').addEventListener('click', function(e) {
            const btn = e.target.closest('button[data-difficulty]');
            if (!btn) {
                return;
            }
            panel.querySelectorAll('[data-role="diff-row"] button').forEach(function(b) {
                b.classList.toggle('active', b === btn);
            });
        });
    };

    const collectConfig = function(panel, optionMap) {
        const topic = tutorSetupCore.getSelectedTopic(panel, optionMap);
        const picked = readCountAndDifficulty(panel);
        return {
            scope: topic.scope,
            sectionnum: topic.sectionnum,
            cmid: topic.cmid,
            count: picked.count,
            difficulty: picked.difficulty,
            language: setupLanguage.getSelectedLanguage(panel),
            topictitle: topic.topictitle,
        };
    };

    const wireStart = function(panel, optionMap, onStart) {
        const startBtn = panel.querySelector('[data-action="start"]');
        if (!startBtn) {
            return;
        }
        startBtn.addEventListener('click', function() {
            onStart(collectConfig(panel, optionMap));
        });
    };

    /**
     * Render loading setup panel and hydrate topic when hierarchy is ready.
     *
     * @param {HTMLElement} container
     * @param {string} coursename
     * @param {Function} onCancel
     * @param {Function} onStart
     * @param {Function} loadHierarchy
     * @param {number} currentCmid
     * @param {{count?: number, difficulty?: string, language?: string}} [initialSelections]
     * @return {Promise<void>}
     */
    const openSetup = async function(container, coursename, onCancel, onStart, loadHierarchy, currentCmid, initialSelections) {
        const count = initialSelections?.count ?? DEFAULT_COUNT;
        const difficulty = initialSelections?.difficulty ?? DEFAULT_DIFFICULTY;
        const language = initialSelections?.language;

        tutorSetupCore.showInstantSpinner(container, SETUP_CLASS);

        const loadingText = await str.get_string('quiz_setup_loading', 'block_dixeo_tutor');
        const topicLabel = await str.get_string('quiz_setup_topic', 'block_dixeo_tutor');
        const startLabel = await str.get_string('quiz_setup_start', 'block_dixeo_tutor');
        const cancelLabel = await str.get_string('quiz_setup_cancel', 'block_dixeo_tutor');

        await Templates.render('block_dixeo_tutor/quiz_setup_loading', {
            coursename: coursename,
            counts: buildCountOptions(count),
            ...buildDifficultyFlags(difficulty),
            loadingtext: loadingText,
            topiclabel: topicLabel,
            startlabel: startLabel,
            cancellabel: cancelLabel,
            startdisabled: true,
            ...setupLanguage.buildLanguageContext(language),
            ...QUIZ_LANGUAGE_FIELD,
        }).then(function(renderedHtml, renderedJs) {
            container.innerHTML = renderedHtml;
            const panel = container.querySelector('.dixeo-quiz-setup');
            bindCountAndDifficulty(panel);
            tutorSetupCore.bindCancel(panel, onCancel);
            Templates.runTemplateJS(renderedJs);
            return undefined;
        });

        const panel = container.querySelector('.dixeo-quiz-setup');

        const hierarchy = await loadHierarchy();
        const selectedKey = initialSelections?.topicKey;
        const topicCmid = initialSelections?.cmid ?? currentCmid;
        const hydrated = tutorSetupCore.completeTopicLoading({
            panel: panel,
            hierarchy: hierarchy,
            currentCmid: topicCmid,
            selectClass: 'dixeo-quiz-setup__topic-select',
            coursename: coursename,
            selectedKey: selectedKey,
        });

        wireStart(panel, hydrated.optionMap, onStart);
    };

    return {
        openSetup: openSetup,
        DEFAULT_COUNT: DEFAULT_COUNT,
        DEFAULT_DIFFICULTY: DEFAULT_DIFFICULTY,
    };
});
