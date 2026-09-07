define([
    'core/str',
    'core/templates',
    'block_dixeo_tutor/tutor_setup_core',
], function(str, Templates, tutorSetupCore) {
    'use strict';

    const SETUP_CLASS = 'dixeo-guide-setup dixeo-tutor-setup';

    const bindPromptValidation = function(panel, syncStart) {
        const prompt = panel.querySelector('[data-role="learner-prompt"]');
        if (!prompt) {
            return;
        }
        prompt.addEventListener('input', syncStart);
    };

    const collectConfig = function(panel) {
        const prompt = panel.querySelector('[data-role="learner-prompt"]');
        const userprompt = (prompt?.value || '').trim();
        if (!userprompt) {
            return null;
        }
        return {userprompt: userprompt};
    };

    const wireStart = function(panel, onStart) {
        const startBtn = panel.querySelector('[data-action="start"]');
        if (!startBtn) {
            return;
        }
        startBtn.addEventListener('click', function() {
            const config = collectConfig(panel);
            if (config) {
                onStart(config);
            }
        });
    };

    /**
     * Render guide setup panel.
     *
     * @param {HTMLElement} container
     * @param {Function} onCancel
     * @param {Function} onStart
     * @param {{userprompt?: string}} [initialValues]
     * @return {Promise<void>}
     */
    const openSetup = async function(container, onCancel, onStart, initialValues) {
        const promptValue = initialValues?.userprompt || '';

        tutorSetupCore.showInstantSpinner(container, SETUP_CLASS);

        const context = {
            promptvalue: promptValue,
            startdisabled: promptValue.trim() === '',
        };

        await Templates.render('block_dixeo_tutor/guide_setup', context).then(function(renderedHtml, renderedJs) {
            container.innerHTML = renderedHtml;
            const panel = container.querySelector('.dixeo-guide-setup');
            tutorSetupCore.bindCancel(panel, onCancel);
            Templates.runTemplateJS(renderedJs);
            return undefined;
        });

        const panel = container.querySelector('.dixeo-guide-setup');
        const prompt = panel.querySelector('[data-role="learner-prompt"]');
        const canStart = function() {
            return (prompt?.value || '').trim().length > 0;
        };
        const syncStart = tutorSetupCore.bindStartEnablement(panel, canStart);
        bindPromptValidation(panel, syncStart);
        syncStart();
        wireStart(panel, onStart);
    };

    return {
        openSetup: openSetup,
    };
});
