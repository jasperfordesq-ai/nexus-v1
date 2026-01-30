/**
 * CivicOne GOV.UK Character Count Component
 * Based on: https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/character-count/character-count.mjs
 *
 * Provides real-time character/word count feedback with accessibility support.
 *
 * WCAG 2.1 AA Compliance:
 * - Real-time count updates for screen readers
 * - Visual and programmatic feedback
 * - Error state handling
 */

(function () {
    'use strict';

    /**
     * Initialize all character count components on the page
     */
    function initCharacterCounts() {
        var containers = document.querySelectorAll('[data-module="govuk-character-count"]');

        containers.forEach(function (container) {
            new CharacterCount(container);
        });
    }

    /**
     * Character Count component class
     * @param {HTMLElement} container - The character count container element
     */
    function CharacterCount(container) {
        this.container = container;
        this.textarea = container.querySelector('.govuk-js-character-count');
        this.messageElement = container.querySelector('.govuk-character-count__message');

        if (!this.textarea || !this.messageElement) {
            return;
        }

        // Get configuration from data attributes
        this.maxlength = parseInt(container.dataset.maxlength, 10) || null;
        this.maxwords = parseInt(container.dataset.maxwords, 10) || null;
        this.threshold = parseInt(container.dataset.threshold, 10) || 0;

        // Store original message
        this.originalMessage = this.messageElement.textContent;

        // Create live region for screen reader announcements
        this.createStatusElement();

        // Bind event handlers
        this.handleInput = this.handleInput.bind(this);
        this.textarea.addEventListener('input', this.handleInput);
        this.textarea.addEventListener('focus', this.handleInput);

        // Initial update
        this.handleInput();
    }

    /**
     * Create a visually hidden status element for screen reader announcements
     */
    CharacterCount.prototype.createStatusElement = function () {
        this.statusElement = document.createElement('span');
        this.statusElement.className = 'govuk-character-count__sr-status govuk-visually-hidden';
        this.statusElement.setAttribute('role', 'status');
        this.statusElement.setAttribute('aria-live', 'polite');
        this.container.appendChild(this.statusElement);
    };

    /**
     * Handle input event
     */
    CharacterCount.prototype.handleInput = function () {
        var count = this.getCount();
        var limit = this.maxwords || this.maxlength;

        if (!limit) {
            return;
        }

        var remaining = limit - count;
        var thresholdReached = this.threshold === 0 || (count / limit * 100) >= this.threshold;

        this.updateMessage(remaining, thresholdReached);
        this.updateErrorState(remaining);
        this.announceToScreenReader(remaining);
    };

    /**
     * Get the current count (characters or words)
     * @returns {number} Current count
     */
    CharacterCount.prototype.getCount = function () {
        var value = this.textarea.value;

        if (this.maxwords) {
            // Count words (split by whitespace, filter empty strings)
            return value.trim() ? value.trim().split(/\s+/).length : 0;
        }

        // Count characters
        return value.length;
    };

    /**
     * Update the visible message
     * @param {number} remaining - Remaining count
     * @param {boolean} thresholdReached - Whether to show the count
     */
    CharacterCount.prototype.updateMessage = function (remaining, thresholdReached) {
        var countType = this.maxwords ? 'word' : 'character';

        if (!thresholdReached) {
            this.messageElement.textContent = this.originalMessage;
            this.messageElement.classList.remove('govuk-character-count__message--disabled');
            return;
        }

        var message;
        if (remaining >= 0) {
            message = 'You have ' + remaining + ' ' + countType + (remaining !== 1 ? 's' : '') + ' remaining';
        } else {
            var over = Math.abs(remaining);
            message = 'You have ' + over + ' ' + countType + (over !== 1 ? 's' : '') + ' too many';
        }

        this.messageElement.textContent = message;
    };

    /**
     * Update error state classes
     * @param {number} remaining - Remaining count
     */
    CharacterCount.prototype.updateErrorState = function (remaining) {
        if (remaining < 0) {
            this.textarea.classList.add('govuk-textarea--error');
            this.messageElement.classList.add('govuk-error-message');
            this.container.classList.add('govuk-form-group--error');
        } else {
            this.textarea.classList.remove('govuk-textarea--error');
            this.messageElement.classList.remove('govuk-error-message');
            this.container.classList.remove('govuk-form-group--error');
        }
    };

    /**
     * Announce to screen readers (debounced)
     * @param {number} remaining - Remaining count
     */
    CharacterCount.prototype.announceToScreenReader = function (remaining) {
        // Clear any pending announcement
        if (this.announceTimeout) {
            clearTimeout(this.announceTimeout);
        }

        // Debounce announcements to avoid overwhelming screen readers
        this.announceTimeout = setTimeout(function () {
            this.statusElement.textContent = this.messageElement.textContent;
        }.bind(this), 1000);
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharacterCounts);
    } else {
        initCharacterCounts();
    }

    // Expose for manual initialization
    window.CivicOneCharacterCount = {
        init: initCharacterCounts,
        CharacterCount: CharacterCount
    };
})();
