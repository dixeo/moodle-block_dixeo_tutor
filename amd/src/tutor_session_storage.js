define([], function() {
    'use strict';

    const PREFIX = 'block_dixeo_tutor_';

    /**
     * Lesson/quiz *bodies* must not be persisted at the top level (audit:
     * technical state only). Nested lessonSnapshot / quizSnapshot for
     * context-view / retake resume, plus playerState progress, are allowed.
     * @type {string[]}
     */
    const CONTENT_KEYS = ['questionsJson', 'contenthtml', 'introhtml', 'userprompt', 'learnerrequest'];

    /**
     * Saved sessions older than this are discarded on load, so lesson content and Guide me
     * summaries do not linger on a shared device.
     * @type {number}
     */
    const TTL_MS = 24 * 60 * 60 * 1000;

    /**
     * Ended Guide me sessions kept for review cards; older ones are dropped.
     * @type {number}
     */
    const MAX_ENDED_SESSIONS = 10;

    /**
     * Whether a parsed record still holds content that must not be persisted.
     *
     * @param {Object} parsed
     * @return {boolean}
     */
    const hasLegacyContent = function(parsed) {
        const topLevel = CONTENT_KEYS.some(function(k) {
            return Object.prototype.hasOwnProperty.call(parsed, k);
        });
        const nested = !!(parsed.setupConfig && typeof parsed.setupConfig === 'object'
            && Object.prototype.hasOwnProperty.call(parsed.setupConfig, 'learnerrequest'));
        const tooManyEnded = Array.isArray(parsed.endedSessions) && parsed.endedSessions.length > MAX_ENDED_SESSIONS;
        return topLevel || nested || tooManyEnded;
    };

    /**
     * Whether a saved record is past its time to live. Records without a timestamp come from
     * older builds and count as expired.
     *
     * @param {Object} parsed
     * @return {boolean}
     */
    const isExpired = function(parsed) {
        const savedAt = Number(parsed.savedAt);
        return !savedAt || Date.now() - savedAt > TTL_MS;
    };

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
        if (clean.setupConfig && typeof clean.setupConfig === 'object') {
            clean.setupConfig = Object.assign({}, clean.setupConfig);
            delete clean.setupConfig.learnerrequest;
        }
        if (Array.isArray(clean.endedSessions) && clean.endedSessions.length > MAX_ENDED_SESSIONS) {
            clean.endedSessions = clean.endedSessions.slice(-MAX_ENDED_SESSIONS);
        }
        return clean;
    };

    /**
     * Remove expired records, and rewrite records from older builds that stored quiz/lesson
     * bodies or the learner's free-text request in localStorage.
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
                    if (isExpired(parsed)) {
                        localStorage.removeItem(key);
                    } else if (hasLegacyContent(parsed)) {
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
        const key = storageKey(mode, userid, courseid);
        try {
            const raw = localStorage.getItem(key);
            if (!raw) {
                return null;
            }
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object' || isExpired(parsed)) {
                localStorage.removeItem(key);
                return null;
            }
            const clean = sanitizePersisted(parsed);
            const serialised = JSON.stringify(clean);
            if (serialised !== raw) {
                localStorage.setItem(key, serialised);
            }
            return clean;
        } catch (e) {
            return null;
        }
    };

    /**
     * Persist technical resume fields (phase, jobId, titles/counts, playerState,
     * lessonSnapshot for context viewing), stamped with the save time for expiry.
     *
     * @param {string} mode
     * @param {number} userid
     * @param {number} courseid
     * @param {Object} data
     * @return {void}
     */
    const save = function(mode, userid, courseid, data) {
        try {
            const record = Object.assign({}, sanitizePersisted(data), {savedAt: Date.now()});
            localStorage.setItem(storageKey(mode, userid, courseid), JSON.stringify(record));
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
     * @param {string} mode quiz|teach|guide
     * @param {number} userid
     * @param {number} courseid
     * @return {boolean}
     */
    const hasActiveSession = function(mode, userid, courseid) {
        const saved = load(mode, userid, courseid);
        if (!saved || !saved.phase) {
            return false;
        }
        if (saved.phase === 'generating' || saved.phase === 'playing') {
            return !!(saved.jobId || saved.quizSnapshot);
        }
        if (saved.phase === 'viewing') {
            return !!(saved.jobId || saved.lessonSnapshot);
        }
        if (saved.phase === 'active') {
            return !!(saved.title && saved.description && !saved.timeEnded);
        }
        if (saved.phase === 'reviewing') {
            return !!(saved.title && saved.description);
        }
        return false;
    };

    /**
     * Remove the saved Quiz, Teach and Guide me sessions of a course.
     *
     * @param {number} userid
     * @param {number} courseid
     * @return {void}
     */
    const clearAllModes = function(userid, courseid) {
        ['quiz', 'teach', 'guide'].forEach(function(mode) {
            clear(mode, userid, courseid);
        });
    };

    return {
        load: load,
        save: save,
        clear: clear,
        clearAllModes: clearAllModes,
        hasActiveSession: hasActiveSession,
    };
});
