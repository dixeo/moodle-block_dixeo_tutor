/**
 * In-memory Web Storage API fallback when localStorage is unavailable.
 *
 * @module     block_dixeo_tutor/memory_storage_adapter
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * In-memory storage adapter that implements the Web Storage API interface.
     * Used as a fallback when localStorage is not available (e.g., private browsing).
     * Note: Data persists only for the lifetime of the page and is not shared across tabs.
     * The StorageService wrapper handles expiry logic.
     */
    class MemoryStorageAdapter {
        constructor() {
            this.store = new Map();
        }

        /**
         * Returns the current number of key/value pairs.
         * @returns {number} The number of items in storage
         */
        get length() {
            return this.store.size;
        }

        /**
         * Returns the name of the nth key.
         * @param {number} index The index of the key to retrieve
         * @returns {string|null} The key name or null if index is out of bounds
         */
        key(index) {
            const keys = Array.from(this.store.keys());
            return keys[index] || null;
        }

        /**
         * Returns the value associated with the given key.
         * @param {string} key The key to look up
         * @returns {string|null} The value or null if not found
         */
        getItem(key) {
            return this.store.get(key) || null;
        }

        /**
         * Sets the value of the given key.
         * @param {string} key The key to set
         * @param {string} value The value to store
         */
        setItem(key, value) {
            this.store.set(key, String(value));
        }

        /**
         * Removes the key/value pair with the given key.
         * @param {string} key The key to remove
         */
        removeItem(key) {
            this.store.delete(key);
        }

        /**
         * Removes all key/value pairs.
         */
        clear() {
            this.store.clear();
        }

        /**
         * Creates a new instance of MemoryStorageAdapter.
         * @returns {MemoryStorageAdapter} New adapter instance
         */
        static create() {
            return new MemoryStorageAdapter();
        }
    }

    return MemoryStorageAdapter;
});