/**
 * CivicOne GOV.UK Exit This Page Component
 * Based on: https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/exit-this-page/exit-this-page.mjs
 *
 * Provides quick exit functionality for sensitive content pages.
 * Keyboard shortcut: Press Shift 3 times rapidly to exit.
 *
 * WCAG 2.1 AA Compliance:
 * - Keyboard accessible
 * - Screen reader announcements
 * - Visual feedback for keyboard shortcut progress
 */

(function () {
    'use strict';

    // Constants
    var SHIFT_TIMEOUT = 1000; // Time in ms to press Shift 3 times

    /**
     * Initialize all Exit This Page components on the page
     */
    function initExitThisPage() {
        var containers = document.querySelectorAll('[data-module="govuk-exit-this-page"]');

        containers.forEach(function (container) {
            new ExitThisPage(container);
        });
    }

    /**
     * Exit This Page component class
     * @param {HTMLElement} container - The exit this page container element
     */
    function ExitThisPage(container) {
        this.container = container;
        this.button = container.querySelector('.govuk-js-exit-this-page-button');

        if (!this.button) {
            return;
        }

        // Get redirect URL from button href
        this.redirectUrl = this.button.getAttribute('href');

        // Get i18n strings from data attributes
        this.i18n = {
            activated: container.dataset['i18n.activated'] || 'Loading.',
            timedOut: container.dataset['i18n.timed-out'] || 'Exit this page expired.',
            pressTwoMoreTimes: container.dataset['i18n.press-two-more-times'] || 'Shift, press 2 more times to exit.',
            pressOneMoreTime: container.dataset['i18n.press-one-more-time'] || 'Shift, press 1 more time to exit.'
        };

        // Track shift key presses
        this.shiftPressCount = 0;
        this.shiftTimeout = null;

        // Create indicator element for screen readers and visual feedback
        this.createIndicator();

        // Create overlay
        this.createOverlay();

        // Bind event handlers
        this.handleKeyDown = this.handleKeyDown.bind(this);
        this.handleButtonClick = this.handleButtonClick.bind(this);

        // Add event listeners
        document.addEventListener('keydown', this.handleKeyDown);
        this.button.addEventListener('click', this.handleButtonClick);
    }

    /**
     * Create the screen reader indicator element
     */
    ExitThisPage.prototype.createIndicator = function () {
        this.indicator = document.createElement('span');
        this.indicator.className = 'govuk-exit-this-page__indicator';
        this.indicator.setAttribute('role', 'status');
        this.indicator.setAttribute('aria-live', 'polite');
        this.container.appendChild(this.indicator);
    };

    /**
     * Create the overlay element
     */
    ExitThisPage.prototype.createOverlay = function () {
        this.overlay = document.createElement('div');
        this.overlay.className = 'govuk-exit-this-page__overlay';
        this.overlay.setAttribute('aria-hidden', 'true');
        document.body.appendChild(this.overlay);
    };

    /**
     * Handle keyboard events
     * @param {KeyboardEvent} event - The keyboard event
     */
    ExitThisPage.prototype.handleKeyDown = function (event) {
        // Only track Shift key (without other modifiers)
        if (event.key !== 'Shift' || event.ctrlKey || event.altKey || event.metaKey) {
            return;
        }

        // Increment shift press count
        this.shiftPressCount++;

        // Clear existing timeout
        if (this.shiftTimeout) {
            clearTimeout(this.shiftTimeout);
        }

        // Set new timeout to reset count
        this.shiftTimeout = setTimeout(this.resetShiftCount.bind(this), SHIFT_TIMEOUT);

        // Update indicator based on count
        this.updateIndicator();

        // If pressed 3 times, trigger exit
        if (this.shiftPressCount >= 3) {
            event.preventDefault();
            this.exit();
        }
    };

    /**
     * Handle button click
     * @param {Event} event - The click event
     */
    ExitThisPage.prototype.handleButtonClick = function (event) {
        event.preventDefault();
        this.exit();
    };

    /**
     * Update the indicator element
     */
    ExitThisPage.prototype.updateIndicator = function () {
        var message = '';
        var showVisual = false;

        if (this.shiftPressCount === 1) {
            message = this.i18n.pressTwoMoreTimes;
            showVisual = true;
        } else if (this.shiftPressCount === 2) {
            message = this.i18n.pressOneMoreTime;
            showVisual = true;
        }

        this.indicator.textContent = message;

        // Toggle visual visibility
        if (showVisual) {
            this.indicator.classList.add('govuk-exit-this-page__indicator--visible');
        } else {
            this.indicator.classList.remove('govuk-exit-this-page__indicator--visible');
        }
    };

    /**
     * Reset the shift key press count
     */
    ExitThisPage.prototype.resetShiftCount = function () {
        this.shiftPressCount = 0;
        this.indicator.textContent = this.i18n.timedOut;

        // Hide visual indicator after brief delay
        setTimeout(function () {
            this.indicator.classList.remove('govuk-exit-this-page__indicator--visible');
            this.indicator.textContent = '';
        }.bind(this), 2000);
    };

    /**
     * Exit the page - redirect to safe URL
     */
    ExitThisPage.prototype.exit = function () {
        // Add active state
        this.container.classList.add('govuk-exit-this-page--active');

        // Announce to screen readers
        this.indicator.textContent = this.i18n.activated;
        this.indicator.classList.remove('govuk-exit-this-page__indicator--visible');

        // Clear shift timeout
        if (this.shiftTimeout) {
            clearTimeout(this.shiftTimeout);
        }

        // Replace current history entry to prevent back button returning
        // This helps protect the user's privacy
        try {
            window.history.replaceState(null, '', this.redirectUrl);
        } catch (e) {
            // Ignore errors (e.g., cross-origin restrictions)
        }

        // Redirect after brief delay for visual feedback
        setTimeout(function () {
            window.location.replace(this.redirectUrl);
        }.bind(this), 100);
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExitThisPage);
    } else {
        initExitThisPage();
    }

    // Expose for manual initialization
    window.CivicOneExitThisPage = {
        init: initExitThisPage,
        ExitThisPage: ExitThisPage
    };
})();
