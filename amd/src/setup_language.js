define([], function() {
    'use strict';

    /**
     * Read generation language options embedded on #dixeo-tutor by the block template.
     *
     * @return {{languages: Array, defaultlanguage: string}}
     */
    const readGenerationLanguages = function() {
        const root = document.getElementById('dixeo-tutor');
        if (!root || !root.dataset.generationLanguages) {
            return {languages: [], defaultlanguage: 'en'};
        }

        try {
            return {
                languages: JSON.parse(root.dataset.generationLanguages),
                defaultlanguage: root.dataset.defaultGenerationLanguage || 'en',
            };
        } catch (e) {
            return {languages: [], defaultlanguage: 'en'};
        }
    };

    /**
     * Build Mustache context for the language selector.
     *
     * @param {string} [initialLanguage]
     * @return {{languages: Array, showlanguageselector: boolean}}
     */
    const buildLanguageContext = function(initialLanguage) {
        const data = readGenerationLanguages();
        const selected = initialLanguage || data.defaultlanguage;
        const languages = (data.languages || []).map(function(lang) {
            return {
                code: lang.code,
                name: lang.name,
                selected: lang.code === selected,
            };
        });

        return {
            languages: languages,
            showlanguageselector: languages.length > 1,
        };
    };

    /**
     * @param {HTMLElement} panel
     * @return {string}
     */
    const getSelectedLanguage = function(panel) {
        const select = panel.querySelector('[data-role="language-select"]');
        if (select && select.value) {
            return select.value;
        }
        return readGenerationLanguages().defaultlanguage;
    };

    return {
        buildLanguageContext: buildLanguageContext,
        getSelectedLanguage: getSelectedLanguage,
        readGenerationLanguages: readGenerationLanguages,
    };
});
