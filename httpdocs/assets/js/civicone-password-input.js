/**
 * CivicOne GOV.UK Password Input Component
 * Based on: https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/password-input/password-input.mjs
 *
 * Provides show/hide toggle for password fields with full accessibility support.
 *
 * WCAG 2.1 AA Compliance:
 * - Keyboard accessible toggle button
 * - Screen reader announcements for state changes
 * - Proper ARIA attributes
 */

(function () {
    'use strict';

    /**
     * Initialize all password input components on the page
     */
    function initPasswordInputs() {
        var containers = document.querySelectorAll('[data-module="govuk-password-input"]');

        containers.forEach(function (container) {
            new PasswordInput(container);
        });
    }

    /**
     * Password Input component class
     * @param {HTMLElement} container - The password input container element
     */
    function PasswordInput(container) {
        this.container = container;
        this.input = container.querySelector('.govuk-js-password-input-input');
        this.toggleButton = container.querySelector('.govuk-js-password-input-toggle');

        if (!this.input || !this.toggleButton) {
            return;
        }

        // Get i18n strings from data attributes
        this.i18n = {
            showPassword: container.dataset['i18n.show-password'] || 'Show',
            hidePassword: container.dataset['i18n.hide-password'] || 'Hide',
            showPasswordAriaLabel: container.dataset['i18n.show-password-aria-label'] || 'Show password',
            hidePasswordAriaLabel: container.dataset['i18n.hide-password-aria-label'] || 'Hide password',
            passwordShownAnnouncement: container.dataset['i18n.password-shown-announcement'] || 'Your password is visible',
            passwordHiddenAnnouncement: container.dataset['i18n.password-hidden-announcement'] || 'Your password is hidden'
        };

        // Create live region for screen reader announcements
        this.createStatusElement();

        // Show the toggle button (hidden by default for progressive enhancement)
        this.toggleButton.removeAttribute('hidden');

        // Add event listener
        this.toggleButton.addEventListener('click', this.toggle.bind(this));
    }

    /**
     * Create a visually hidden status element for screen reader announcements
     */
    PasswordInput.prototype.createStatusElement = function () {
        this.statusElement = document.createElement('span');
        this.statusElement.className = 'govuk-password-input__sr-status govuk-visually-hidden';
        this.statusElement.setAttribute('role', 'status');
        this.statusElement.setAttribute('aria-live', 'polite');
        this.container.appendChild(this.statusElement);
    };

    /**
     * Toggle password visibility
     * @param {Event} event - Click event
     */
    PasswordInput.prototype.toggle = function (event) {
        event.preventDefault();

        if (this.input.type === 'password') {
            this.showPassword();
        } else {
            this.hidePassword();
        }
    };

    /**
     * Show the password (change input type to text)
     */
    PasswordInput.prototype.showPassword = function () {
        this.input.type = 'text';
        this.toggleButton.textContent = this.i18n.hidePassword;
        this.toggleButton.setAttribute('aria-label', this.i18n.hidePasswordAriaLabel);
        this.announce(this.i18n.passwordShownAnnouncement);
    };

    /**
     * Hide the password (change input type to password)
     */
    PasswordInput.prototype.hidePassword = function () {
        this.input.type = 'password';
        this.toggleButton.textContent = this.i18n.showPassword;
        this.toggleButton.setAttribute('aria-label', this.i18n.showPasswordAriaLabel);
        this.announce(this.i18n.passwordHiddenAnnouncement);
    };

    /**
     * Announce a message to screen readers
     * @param {string} message - Message to announce
     */
    PasswordInput.prototype.announce = function (message) {
        this.statusElement.textContent = '';
        // Use setTimeout to ensure the change is announced
        setTimeout(function () {
            this.statusElement.textContent = message;
        }.bind(this), 100);
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPasswordInputs);
    } else {
        initPasswordInputs();
    }

    // Expose for manual initialization
    window.CivicOnePasswordInput = {
        init: initPasswordInputs,
        PasswordInput: PasswordInput
    };
})();
