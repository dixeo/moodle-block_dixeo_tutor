define([], function() {
    'use strict';

    const PREFIX = 'block_dixeo_tutor_';

    /**
     * Content keys must not be persisted (audit: technical state only).
     * @type {string[]}
     */
    const CONTENT_KEYS = ['questionsJson', 'contenthtml', 'introhtml', 'playerState'];

    /**
     * @param {string} mode quiz|teach
     * @param {number} userid
     * @param {number} courseid
     * @return {string}
     */
    const storageKey = function(mode, userid, courseid) {
        return PREFIX + mode + '_' + userid + '_' + courseid;
    };

    /**
     * Drop legacy content bodies from a saved session object.
     *
     * @param {Object|null} data
     * @return {Object|null}
     */
    const sanitizePersisted = function(data) {
        if (!data || typeof data !== 'object') {
            return data;
        }
        const clean = Object.assign({}, data);
        CONTENT_KEYS.forEach(function(key) {
            delete clean[key];
        });
        return clean;
    };

    /**
     * Remove older builds that stored quiz/lesson bodies in localStorage.
     * @private
     */
    const clearLegacyContentKeys = function() {
        try {
            for (let i = localStorage.length - 1; i >= 0; i--) {
                const key = localStorage.key(i);
                if (!key || !key.startsWith(PREFIX)) {
                    continue;
                }
                try {
                    const raw = localStorage.getItem(key);
                    if (!raw) {
                        continue;
                    }
                    const parsed = JSON.parse(raw);
                    if (!parsed || typeof parsed !== 'object') {
                        continue;
                    }
                    const hadContent = CONTENT_KEYS.some(function(k) {
                        return Object.prototype.hasOwnProperty.call(parsed, k);
                    });
                    if (hadContent) {
                        localStorage.setItem(key, JSON.stringify(sanitizePersisted(parsed)));
                    }
                } catch (e) {
                    // Ignore corrupt keys.
                }
            }
        } catch (e) {
            // Ignore unavailable storage.
        }
    };

    clearLegacyContentKeys();

    /**
     * @param {string} mode
     * @param {number} userid
     * @param {number} courseid
     * @return {Object|null}
     */
    const load = function(mode, userid, courseid) {
        try {
            const raw = localStorage.getItem(storageKey(mode, userid, courseid));
            if (!raw) {
                return null;
            }
            return sanitizePersisted(JSON.parse(raw));
        } catch (e) {
            return null;
        }
    };

    /**
     * Persist technical resume fields only (phase, jobId, titles/counts).
     *
     * @param {string} mode
     * @param {number} userid
     * @param {number} courseid
     * @param {Object} data
     * @return {void}
     */
    const save = function(mode, userid, courseid, data) {
        try {
            localStorage.setItem(
                storageKey(mode, userid, courseid),
                JSON.stringify(sanitizePersisted(data))
            );
        } catch (e) {
            // Quota or private mode — session continues without persistence.
        }
    };

    /**
     * @param {string} mode
     * @param {number} userid
     * @param {number} courseid
     * @return {void}
     */
    const clear = function(mode, userid, courseid) {
        try {
            localStorage.removeItem(storageKey(mode, userid, courseid));
        } catch (e) {
            // Ignore.
        }
    };

    /**
     * Whether local storage holds a session that should resume instead of opening setup.
     *
     * @param {string} mode quiz|teach
     * @param {number} userid
     * @param {number} courseid
     * @return {boolean}
     */
    const hasActiveSession = function(mode, userid, courseid) {
        const saved = load(mode, userid, courseid);
        if (!saved || !saved.phase) {
            return false;
        }
        if (saved.phase === 'generating' || saved.phase === 'playing' || saved.phase === 'viewing') {
            return !!saved.jobId;
        }
        return false;
    };

    return {
        load: load,
        save: save,
        clear: clear,
        hasActiveSession: hasActiveSession,
    };
});
