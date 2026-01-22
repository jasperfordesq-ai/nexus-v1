/**
 * GOV.UK Error Summary - Focus Management
 * WCAG 3.3.1 Error Identification (Level A)
 *
 * Automatically focuses the error summary on page load if it exists
 * and links form fields to their error messages via aria-describedby
 */

(function() {
    'use strict';

    /**
     * Initialize error summary focus and aria linkage
     */
    function initErrorSummary() {
        const errorSummary = document.querySelector('[data-module="govuk-error-summary"]');

        if (!errorSummary) return;

        // Focus the error summary on page load
        // Use requestAnimationFrame to ensure DOM is ready
        requestAnimationFrame(() => {
            errorSummary.focus();
        });

        // Link error messages to form fields via aria-describedby
        linkErrorsToFields(errorSummary);

        // Add click handler for error links to focus the field
        errorSummary.querySelectorAll('.govuk-error-summary__list a[href^="#"]').forEach(link => {
            link.addEventListener('click', handleErrorLinkClick);
        });
    }

    /**
     * Link form fields to their error messages
     * @param {HTMLElement} errorSummary
     */
    function linkErrorsToFields(errorSummary) {
        const errorLinks = errorSummary.querySelectorAll('.govuk-error-summary__list a[href^="#"]');

        errorLinks.forEach((link, index) => {
            const targetId = link.getAttribute('href').substring(1);
            const field = document.getElementById(targetId);

            if (!field) return;

            // Create error message ID
            const errorMessageId = 'error-message-' + (index + 1);

            // Check if there's already an inline error message
            const existingError = field.closest('.govuk-form-group')?.querySelector('.govuk-error-message');

            if (existingError && !existingError.id) {
                existingError.id = errorMessageId;
            }

            // Update aria-describedby on field
            const currentDescribedBy = field.getAttribute('aria-describedby') || '';
            const idToAdd = existingError ? existingError.id : errorMessageId;

            if (!currentDescribedBy.includes(idToAdd)) {
                field.setAttribute('aria-describedby',
                    (currentDescribedBy + ' ' + idToAdd).trim()
                );
            }

            // Mark field as invalid
            field.setAttribute('aria-invalid', 'true');

            // Add error class to form group
            const formGroup = field.closest('.govuk-form-group');
            if (formGroup) {
                formGroup.classList.add('govuk-form-group--error');
            }
        });
    }

    /**
     * Handle click on error link - focus the field
     * @param {Event} e
     */
    function handleErrorLinkClick(e) {
        const targetId = e.currentTarget.getAttribute('href').substring(1);
        const field = document.getElementById(targetId);

        if (field) {
            e.preventDefault();
            field.focus();

            // Scroll field into view if needed
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    /**
     * Programmatically show error summary with errors
     * Can be called after AJAX form submission
     * @param {Array} errors - Array of {text, href} objects
     * @param {Object} options - Optional settings
     */
    window.showErrorSummary = function(errors, options = {}) {
        const defaults = {
            title: 'There is a problem',
            containerId: 'error-summary-container',
            summaryId: 'error-summary'
        };

        const settings = { ...defaults, ...options };
        const container = document.getElementById(settings.containerId) ||
                         document.querySelector('main') ||
                         document.body;

        // Build error summary HTML
        let html = '<div class="govuk-error-summary" data-module="govuk-error-summary" role="alert" ';
        html += 'tabindex="-1" aria-labelledby="error-summary-title" id="' + settings.summaryId + '">';
        html += '<h2 class="govuk-error-summary__title" id="error-summary-title">' + escapeHtml(settings.title) + '</h2>';
        html += '<div class="govuk-error-summary__body">';
        html += '<ul class="govuk-error-summary__list">';

        errors.forEach(error => {
            html += '<li>';
            if (error.href) {
                html += '<a href="' + escapeHtml(error.href) + '">' + escapeHtml(error.text) + '</a>';
            } else {
                html += escapeHtml(error.text);
            }
            html += '</li>';
        });

        html += '</ul></div></div>';

        // Remove any existing error summary
        const existing = document.getElementById(settings.summaryId);
        if (existing) existing.remove();

        // Insert at top of container
        container.insertAdjacentHTML('afterbegin', html);

        // Initialize the new summary
        initErrorSummary();
    };

    /**
     * Hide/remove error summary
     * @param {string} summaryId
     */
    window.hideErrorSummary = function(summaryId = 'error-summary') {
        const summary = document.getElementById(summaryId);
        if (summary) {
            summary.remove();
        }
    };

    /**
     * Escape HTML entities
     * @param {string} text
     * @returns {string}
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initErrorSummary);
    } else {
        initErrorSummary();
    }

})();
