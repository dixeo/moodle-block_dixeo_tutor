define([], function() {
    'use strict';

    /**
     * Text processing utilities for the tutor.
     * Handles HTML escaping and markdown parsing.
     */
    return {
        /**
         * Escapes HTML special characters to prevent XSS attacks.
         * @param {string} text The text to escape.
         * @returns {string} The escaped text.
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return (text || '').replace(/[&<>"']/g, m => map[m]);
        },

        /**
         * Converts simple markdown to HTML.
         * Supports bold (**text**), italic (*text*), inline code (`text`), and links [text](url).
         * Strips markdown headers and code blocks, then always escapes HTML before processing.
         * We never trust server HTML blindly — escaping first is the safe default.
         * @param {string} markdown The markdown text to parse.
         * @returns {string} The HTML representation.
         */
        parseMarkdownToHtml: function(markdown) {
            const content = markdown || '';
            const cleanedContent = this._stripMarkdownSyntax(content);
            return this._processSimpleMarkdown(cleanedContent, true);
        },

        /**
         * Resolve display HTML for a chat message (server-filtered or client fallback).
         *
         * @param {object} msg Message with content and optional contenthtml.
         * @returns {string}
         */
        resolveMessageContentHtml: function(msg) {
            const serverHtml = msg && msg.contenthtml ? String(msg.contenthtml).trim() : '';
            const usedServer = serverHtml !== '';
            const resolved = usedServer
                ? serverHtml
                : this.parseMarkdownToHtml(msg ? msg.content : '');
            return resolved;
        },

        /**
         * Convert HTML to plain text for previews and TTS.
         *
         * @param {string} html
         * @returns {string}
         */
        htmlToPlain: function(html) {
            const div = document.createElement('div');
            div.innerHTML = html || '';
            return (div.textContent || div.innerText || '').trim();
        },

        /**
         * Truncate text at a word boundary without exceeding maxLength.
         *
         * @param {string} text
         * @param {number} [maxLength=200]
         * @returns {string}
         */
        truncateAtWordBoundary: function(text, maxLength) {
            const limit = maxLength || 200;
            const normalized = (text || '').trim();
            if (normalized.length <= limit) {
                return normalized;
            }

            const slice = normalized.slice(0, limit + 1);
            const lastSpace = slice.lastIndexOf(' ');
            if (lastSpace > 0) {
                return slice.slice(0, lastSpace).trim() + '\u2026';
            }

            return normalized.slice(0, limit).trim() + '\u2026';
        },

        /**
         * Strips markdown headers and code blocks from text content.
         * Removes # symbols from headers and ``` ``` from code blocks, keeping only the content.
         * @param {string} text The text to process.
         * @returns {string} The cleaned text with markdown syntax removed.
         * @private
         */
        _stripMarkdownSyntax: function(text) {
            if (!text) {
                return '';
            }

            let processedText = text;
            processedText = processedText.replace(/^(#{1,6})\s+(.+)$/gm, '$2');
            processedText = processedText.replace(/```[\w]*\n?([\s\S]*?)```/g, '$1');
            processedText = processedText.replace(/^`+|`+$/gm, '');
            return processedText;
        },

        /**
         * Processes text content with simple markdown transformations.
         * Supports bold, italic, inline code, and links only.
         * @param {string} text The text to process.
         * @param {boolean} escapeHtml Whether to escape HTML characters first.
         * @returns {string} The processed text with simple markdown converted to HTML.
         * @private
         */
        _processSimpleMarkdown: function(text, escapeHtml = false) {
            if (!text) {
                return '';
            }

            let processedText = escapeHtml ? this.escapeHtml(text) : text;
            processedText = processedText
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code>$1</code>')
                .replace(/\[([^\]]+)]\((https?:\/\/[^\s)]+)\)/g,
                    '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
            return processedText.replace(/\n/g, '<br>');
        }
    };
});
