define([], function() {
    'use strict';

    /**
     * Build nested sections from legacy flat hierarchy (activities at top level).
     *
     * @param {Object} hierarchy
     * @return {Array}
     */
    const normalizeSections = function(hierarchy) {
        const sections = hierarchy.sections || [];
        if (sections.length > 0 && Array.isArray(sections[0].activities)) {
            return sections;
        }

        const bySection = {};
        sections.forEach(function(sec) {
            bySection[sec.num] = {
                num: sec.num,
                name: sec.name,
                activities: [],
            };
        });

        (hierarchy.activities || []).forEach(function(act) {
            const sec = bySection[act.sectionnum];
            if (sec) {
                sec.activities.push({
                    cmid: act.cmid,
                    name: act.name,
                });
            }
        });

        return Object.keys(bySection).sort(function(a, b) {
            return parseInt(a, 10) - parseInt(b, 10);
        }).map(function(key) {
            return bySection[key];
        });
    };

    /**
     * Flatten course index into topic options for Mustache or DOM hydrate.
     *
     * @param {Object} hierarchy
     * @param {number} currentCmid
     * @return {{topics: Array, optionMap: Object, selectedKey: string}}
     */
    const buildTopicOptions = function(hierarchy, currentCmid) {
        const courseName = hierarchy.course?.name || '';
        const options = [{
            key: 'course',
            scope: 'course',
            sectionnum: 0,
            cmid: 0,
            topictitle: courseName,
            label: courseName,
        }];

        const indent = '\u00A0\u00A0';
        normalizeSections(hierarchy).forEach(function(sec) {
            const activities = sec.activities || [];
            if (activities.length === 0) {
                return;
            }

            options.push({
                key: 'section-' + sec.num,
                scope: 'section',
                sectionnum: sec.num,
                cmid: 0,
                topictitle: sec.name,
                label: indent + sec.name,
            });

            activities.forEach(function(act) {
                options.push({
                    key: 'activity-' + act.cmid,
                    scope: 'activity',
                    sectionnum: sec.num,
                    cmid: act.cmid,
                    topictitle: act.name,
                    label: indent + indent + act.name,
                });
            });
        });

        let selectedKey = 'course';
        options.forEach(function(opt) {
            if (currentCmid && opt.cmid === currentCmid) {
                selectedKey = opt.key;
            }
        });

        const optionMap = {};
        const topics = options.map(function(opt) {
            optionMap[opt.key] = opt;
            return {
                key: opt.key,
                label: opt.label,
                selected: opt.key === selectedKey,
            };
        });

        return {topics: topics, optionMap: optionMap, selectedKey: selectedKey};
    };

    /**
     * Build topic select markup for in-place hierarchy hydrate.
     *
     * @param {Object} hierarchy
     * @param {number} currentCmid
     * @param {string} selectClass
     * @param {string} coursename
     * @return {{html: string, optionMap: Object}}
     */
    const buildTopicSelectHtml = function(hierarchy, currentCmid, selectClass, coursename) {
        const topicData = buildTopicOptions(hierarchy, currentCmid);
        const optionsHtml = topicData.topics.map(function(topic) {
            const selected = topic.selected ? ' selected' : '';
            return '<option value="' + topic.key + '"' + selected + '>' + topic.label + '</option>';
        }).join('');

        const html = '<select class="form-control form-control-sm ' + selectClass + ' mb-2"' +
            ' data-role="topic-select"' +
            ' aria-label="' + coursename + '">' +
            optionsHtml +
            '</select>';

        return {html: html, optionMap: topicData.optionMap};
    };

    return {
        normalizeSections: normalizeSections,
        buildTopicOptions: buildTopicOptions,
        buildTopicSelectHtml: buildTopicSelectHtml,
    };
});
