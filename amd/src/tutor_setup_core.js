define([
    'block_dixeo_tutor/course_hierarchy_setup',
], function(courseHierarchySetup) {
    'use strict';

    /**
     * Replace topic-loading placeholder with a live topic select (preserves other field values).
     *
     * @param {HTMLElement} panel
     * @param {Object} hierarchy
     * @param {number} currentCmid
     * @param {string} selectClass
     * @param {string} coursename
     * @param {string} [selectedKey] Optional key to select instead of currentCmid default
     * @return {{optionMap: Object}}
     */
    const hydrateTopicSelect = function(panel, hierarchy, currentCmid, selectClass, coursename, selectedKey) {
        const loadingEl = panel.querySelector('[data-role="topic-loading"]');
        if (!loadingEl) {
            return {optionMap: {}};
        }

        const topicData = courseHierarchySetup.buildTopicOptions(hierarchy, currentCmid);
        if (selectedKey && topicData.optionMap[selectedKey]) {
            topicData.topics.forEach(function(topic) {
                topic.selected = topic.key === selectedKey;
            });
        }

        const select = document.createElement('select');
        select.className = 'form-control form-control-sm ' + selectClass + ' mb-2';
        select.setAttribute('data-role', 'topic-select');
        select.setAttribute('aria-label', coursename);

        topicData.topics.forEach(function(topic) {
            const option = document.createElement('option');
            option.value = topic.key;
            option.textContent = topic.label;
            if (topic.selected) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        loadingEl.replaceWith(select);

        return {optionMap: topicData.optionMap};
    };

    /**
     * Enable the setup start button once topic select is available.
     *
     * @param {HTMLElement} panel
     * @param {function(): boolean} [canStart] Optional extra guard
     * @return {function(): void} Call to re-sync start button state
     */
    const bindStartEnablement = function(panel, canStart) {
        const startBtn = panel.querySelector('[data-action="start"]');
        if (!startBtn) {
            return function() {
                // No start button on this panel.
            };
        }

        const sync = function() {
            const hasTopic = !!panel.querySelector('[data-role="topic-select"]');
            const extraOk = typeof canStart === 'function' ? canStart() : true;
            startBtn.disabled = !hasTopic || !extraOk;
        };

        return sync;
    };

    /**
     * Read selected topic metadata from a wired setup panel.
     *
     * @param {HTMLElement} panel
     * @param {Object} optionMap
     * @return {Object}
     */
    const getSelectedTopic = function(panel, optionMap) {
        const select = panel.querySelector('[data-role="topic-select"]');
        const key = select ? select.value : 'course';
        return optionMap[key] || optionMap.course || {
            scope: 'course',
            sectionnum: 0,
            cmid: 0,
            topictitle: '',
        };
    };

    /**
     * Wire cancel button on a setup panel.
     *
     * @param {HTMLElement} panel
     * @param {Function} onCancel
     */
    const bindCancel = function(panel, onCancel) {
        const cancelBtn = panel.querySelector('[data-action="cancel"]');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', onCancel);
        }
    };

    /**
     * Show a synchronous spinner so the pane is never blank before Mustache renders.
     *
     * @param {HTMLElement} container
     * @param {string} setupClass
     */
    const showInstantSpinner = function(container, setupClass) {
        container.innerHTML = [
            '<div class="' + setupClass + ' ' + setupClass + '--instant" role="status" aria-live="polite" aria-busy="true">',
            '<div class="' + setupClass + '__instant-spinner">',
            '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>',
            '</div>',
            '</div>',
        ].join('');
    };

    /**
     * After hierarchy loads: hydrate topic select and enable start.
     *
     * @param {Object} options
     * @param {HTMLElement} options.panel
     * @param {Object} options.hierarchy
     * @param {number} options.currentCmid
     * @param {string} options.selectClass
     * @param {string} options.coursename
     * @param {string} [options.selectedKey]
     * @param {function(): boolean} [options.canStart]
     * @return {{optionMap: Object, syncStart: function(): void}}
     */
    const completeTopicLoading = function(options) {
        const result = hydrateTopicSelect(
            options.panel,
            options.hierarchy,
            options.currentCmid,
            options.selectClass,
            options.coursename,
            options.selectedKey
        );
        const syncStart = bindStartEnablement(options.panel, options.canStart);
        syncStart();
        return {optionMap: result.optionMap, syncStart: syncStart};
    };

    return {
        hydrateTopicSelect: hydrateTopicSelect,
        bindStartEnablement: bindStartEnablement,
        getSelectedTopic: getSelectedTopic,
        bindCancel: bindCancel,
        showInstantSpinner: showInstantSpinner,
        completeTopicLoading: completeTopicLoading,
        buildTopicOptions: courseHierarchySetup.buildTopicOptions,
    };
});
