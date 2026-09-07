define([
    'block_dixeo_tutor/constants',
    'block_dixeo_tutor/storage_service'
], function(constants, StorageService) {
    'use strict';

    return class ChatState {
        constructor(courseid, userid) {
            this.courseid = courseid;
            this.userid = userid;
            this.pending = false;
            this.lastRenderedId = null;
            // Draft message bodies stay in memory only (audit: no personal content in Web Storage).
            this.draftMessage = '';
            this.oldestLoadedId = null;
            this.hasMoreOlder = false;
            this.historyOffset = 0;
            // Non-sensitive poll checkpoint (jobId / pending) uses localStorage for cross-tab sync.
            this.storage = StorageService.namespace(`${userid}_${courseid}`);
        }

        getCourseId() {
            return this.courseid;
        }

        isPending() {
            return this.pending;
        }

        setPending(pending) {
            this.pending = pending;
        }

        getLastRenderedId() {
            return this.lastRenderedId;
        }

        setLastRenderedId(id) {
            this.lastRenderedId = id;
        }

        getOldestLoadedId() {
            return this.oldestLoadedId;
        }

        setOldestLoadedId(id) {
            this.oldestLoadedId = id;
        }

        getHasMoreOlder() {
            return this.hasMoreOlder;
        }

        setHasMoreOlder(hasMore) {
            this.hasMoreOlder = !!hasMore;
        }

        getHistoryOffset() {
            return this.historyOffset;
        }

        setHistoryOffset(offset) {
            this.historyOffset = Math.max(0, offset);
        }

        getDraft() {
            return this.draftMessage || '';
        }

        setDraft(message) {
            this.draftMessage = message || '';
        }

        clearDraft() {
            this.draftMessage = '';
        }

        savePollState(state) {
            this.storage.set('polling', state, {
                expiry: Date.now() + constants.polling.STATE_EXPIRY_MS
            });
        }

        getPollState() {
            return this.storage.get('polling', null);
        }

        clearPollState() {
            this.storage.remove('polling');
        }

        /**
         * Get the full localStorage key for the polling state (for cross-tab event matching).
         * @returns {string} The full prefixed key.
         */
        getPollingStorageKey() {
            return this.storage.getFullKey('polling');
        }

        clearAll() {
            this.clearDraft();
            this.storage.clear();
        }

        destroy() {
            this.clearAll();
            this.storage = null;
        }
    };
});
