define([
    'core/str',
    'core/templates',
    'block_dixeo_tutor/tutor_setup_core',
    'block_dixeo_tutor/setup_language',
], function(str, Templates, tutorSetupCore, setupLanguage) {
    'use strict';

    const SETUP_CLASS = 'dixeo-teach-setup dixeo-tutor-setup';

    const TEACH_LANGUAGE_FIELD = {
        languageselectid: 'dixeo-teach-setup-language',
        labelclass: 'dixeo-tutor-setup__label dixeo-teach-setup__label',
        selectclass: 'dixeo-teach-setup__language-select',
    };

    const bindPromptValidation = function(panel, syncStart) {
        const prompt = panel.querySelector('[data-role="learner-prompt"]');
        if (!prompt) {
            return;
        }

        prompt.addEventListener('input', syncStart);
    };

    const collectConfig = function(panel, optionMap) {
        const topic = tutorSetupCore.getSelectedTopic(panel, optionMap);
        const prompt = panel.querySelector('[data-role="learner-prompt"]');
        const learnerrequest = (prompt?.value || '').trim();
        if (!learnerrequest) {
            return null;
        }
        return {
            scope: topic.scope,
            sectionnum: topic.sectionnum,
            cmid: topic.cmid,
            topictitle: topic.topictitle,
            learnerrequest: learnerrequest,
            language: setupLanguage.getSelectedLanguage(panel),
        };
    };

    const wireStart = function(panel, optionMap, onStart) {
        const startBtn = panel.querySelector('[data-action="start"]');
        if (!startBtn) {
            return;
        }
        startBtn.addEventListener('click', function() {
            const config = collectConfig(panel, optionMap);
            if (config) {
                onStart(config);
            }
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
     * @param {{learnerrequest?: string, language?: string, topicKey?: string, cmid?: number}} [initialValues]
     * @return {Promise<void>}
     */
    const openSetup = async function(container, coursename, onCancel, onStart, loadHierarchy, currentCmid, initialValues) {
        const language = initialValues?.language;
        const promptValue = initialValues?.learnerrequest || '';

        tutorSetupCore.showInstantSpinner(container, SETUP_CLASS);

        const loadingText = await str.get_string('teach_setup_loading', 'block_dixeo_tutor');
        const topicLabel = await str.get_string('teach_setup_topic', 'block_dixeo_tutor');
        const promptLabel = await str.get_string('teach_setup_prompt', 'block_dixeo_tutor');
        const promptPlaceholder = await str.get_string('teach_setup_prompt_placeholder', 'block_dixeo_tutor');
        const startLabel = await str.get_string('teach_setup_start', 'block_dixeo_tutor');
        const cancelLabel = await str.get_string('teach_setup_cancel', 'block_dixeo_tutor');

        await Templates.render('block_dixeo_tutor/teach_setup_loading', {
            coursename: coursename,
            loadingtext: loadingText,
            topiclabel: topicLabel,
            promptlabel: promptLabel,
            promptplaceholder: promptPlaceholder,
            promptvalue: promptValue,
            startlabel: startLabel,
            cancellabel: cancelLabel,
            startdisabled: true,
            ...setupLanguage.buildLanguageContext(language),
            ...TEACH_LANGUAGE_FIELD,
        }).then(function(renderedHtml, renderedJs) {
            container.innerHTML = renderedHtml;
            const panel = container.querySelector('.dixeo-teach-setup');
            tutorSetupCore.bindCancel(panel, onCancel);
            Templates.runTemplateJS(renderedJs);
            return undefined;
        });

        const panel = container.querySelector('.dixeo-teach-setup');

        const prompt = panel.querySelector('[data-role="learner-prompt"]');
        const canStart = function() {
            return (prompt?.value || '').trim().length > 0;
        };

        const hierarchy = await loadHierarchy();
        const topicCmid = initialValues?.cmid ?? currentCmid;
        const hydrated = tutorSetupCore.completeTopicLoading({
            panel: panel,
            hierarchy: hierarchy,
            currentCmid: topicCmid,
            selectClass: 'dixeo-teach-setup__topic-select',
            coursename: coursename,
            selectedKey: initialValues?.topicKey,
            canStart: canStart,
        });

        bindPromptValidation(panel, hydrated.syncStart);
        wireStart(panel, hydrated.optionMap, onStart);
    };

    return {
        openSetup: openSetup,
    };
});
