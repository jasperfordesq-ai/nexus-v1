/**
 * Form Validation Animations
 * Shake on error, checkmark on success, smooth feedback
 * Version: 1.0 - 2026-01-19
 *
 * Usage:
 *   // Mark field as error with shake
 *   FormValidation.error(input, 'Email is required');
 *
 *   // Mark field as success with checkmark
 *   FormValidation.success(input);
 *
 *   // Clear validation state
 *   FormValidation.clear(input);
 *
 *   // Validate entire form
 *   FormValidation.validateForm(form);
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        shakeOnError: true,
        pulseOnSuccess: true,
        showIcons: true,
        autoValidate: true,
        debounceMs: 300,
        selectors: {
            form: 'form[data-validate], form.validate-form',
            field: 'input, select, textarea'
        }
    };

    // Check for reduced motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // SVG icons
    const icons = {
        success: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>`,
        error: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>`
    };

    // Debounce helper
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Get or create validation wrapper
     */
    function ensureWrapper(field) {
        const parent = field.parentElement;
        if (parent && parent.classList.contains('form-validation-wrapper')) {
            return parent;
        }

        // Don't wrap if already wrapped or if in complex layouts
        if (field.closest('.form-validation-wrapper')) {
            return field.closest('.form-validation-wrapper');
        }

        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'form-validation-wrapper';
        field.parentNode.insertBefore(wrapper, field);
        wrapper.appendChild(field);

        return wrapper;
    }

    /**
     * Get or create validation icon
     */
    function ensureIcon(field, type) {
        if (!config.showIcons) return null;

        const wrapper = field.closest('.form-validation-wrapper') || field.parentElement;
        let icon = wrapper.querySelector('.form-validation-icon');

        if (!icon) {
            icon = document.createElement('span');
            icon.className = 'form-validation-icon';
            icon.setAttribute('aria-hidden', 'true');
            wrapper.appendChild(icon);
        }

        // Update icon type
        icon.className = `form-validation-icon form-validation-icon--${type}`;
        icon.innerHTML = icons[type] || '';

        return icon;
    }

    /**
     * Get or create error message element
     */
    function getErrorElement(field) {
        const wrapper = field.closest('.form-validation-wrapper') || field.parentElement;
        const fieldId = field.id || field.name;
        let errorEl = wrapper.querySelector('.form-error-message');

        if (!errorEl) {
            const parent = wrapper.parentElement || wrapper;
            errorEl = parent.querySelector('.form-error-message');
        }

        if (!errorEl) {
            errorEl = document.createElement('span');
            errorEl.className = 'form-error-message';
            if (fieldId) {
                errorEl.id = `${fieldId}-error`;
                field.setAttribute('aria-describedby', errorEl.id);
            }
            const insertAfter = field.closest('.form-validation-wrapper') || field;
            insertAfter.parentNode.insertBefore(errorEl, insertAfter.nextSibling);
        }

        return errorEl;
    }

    /**
     * Mark field as error
     */
    function markError(field, message) {
        if (!field) return;

        // Remove success state
        field.classList.remove('is-valid', 'form-success');

        // Add error state
        field.classList.add('is-invalid', 'form-error');
        field.setAttribute('aria-invalid', 'true');

        // Shake animation
        if (config.shakeOnError && !prefersReducedMotion) {
            field.classList.add('form-shake');
            setTimeout(() => field.classList.remove('form-shake'), 500);
        }

        // Show error icon
        ensureIcon(field, 'error');

        // Show error message
        if (message) {
            const errorEl = getErrorElement(field);
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        }

        // Shake form group if exists
        const formGroup = field.closest('.form-group, .field-group');
        if (formGroup) {
            formGroup.classList.add('has-error');
            formGroup.classList.remove('has-success');
        }

        // Haptic feedback on mobile
        if (navigator.vibrate) {
            navigator.vibrate([10, 50, 10]);
        }
    }

    /**
     * Mark field as success
     */
    function markSuccess(field) {
        if (!field) return;

        // Remove error state
        field.classList.remove('is-invalid', 'form-error');
        field.removeAttribute('aria-invalid');

        // Add success state
        field.classList.add('is-valid', 'form-success');

        // Pulse animation
        if (config.pulseOnSuccess && !prefersReducedMotion) {
            field.classList.add('form-success-pulse');
            setTimeout(() => field.classList.remove('form-success-pulse'), 600);
        }

        // Show success icon
        ensureIcon(field, 'success');

        // Hide error message
        const wrapper = field.closest('.form-validation-wrapper') || field.parentElement;
        const errorEl = wrapper.querySelector('.form-error-message') ||
                        wrapper.parentElement?.querySelector('.form-error-message');
        if (errorEl) {
            errorEl.classList.add('form-message-fade-out');
            setTimeout(() => {
                errorEl.classList.add('hidden');
                errorEl.classList.remove('form-message-fade-out');
            }, 200);
        }

        // Update form group
        const formGroup = field.closest('.form-group, .field-group');
        if (formGroup) {
            formGroup.classList.remove('has-error');
            formGroup.classList.add('has-success');
        }
    }

    /**
     * Clear validation state
     */
    function clearValidation(field) {
        if (!field) return;

        // Remove all states
        field.classList.remove('is-invalid', 'is-valid', 'form-error', 'form-success', 'form-shake');
        field.removeAttribute('aria-invalid');

        // Remove icon
        const wrapper = field.closest('.form-validation-wrapper') || field.parentElement;
        const icon = wrapper.querySelector('.form-validation-icon');
        if (icon) {
            icon.classList.add('js-hidden-icon');
            icon.classList.remove('js-visible-icon');
        }

        // Hide error message
        const errorEl = wrapper.querySelector('.form-error-message') ||
                        wrapper.parentElement?.querySelector('.form-error-message');
        if (errorEl) {
            errorEl.classList.add('hidden');
        }

        // Clear form group
        const formGroup = field.closest('.form-group, .field-group');
        if (formGroup) {
            formGroup.classList.remove('has-error', 'has-success');
        }
    }

    /**
     * Validate a single field
     */
    function validateField(field) {
        // Skip hidden or disabled fields
        if (field.type === 'hidden' || field.disabled) {
            return true;
        }

        const value = field.value.trim();
        const rules = parseRules(field);
        let isValid = true;
        let errorMessage = '';

        // Required check
        if (rules.required && !value) {
            isValid = false;
            errorMessage = rules.requiredMessage || 'This field is required';
        }

        // Email check
        if (isValid && rules.email && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = rules.emailMessage || 'Please enter a valid email address';
            }
        }

        // Min length check
        if (isValid && rules.minLength && value.length < rules.minLength) {
            isValid = false;
            errorMessage = rules.minLengthMessage || `Minimum ${rules.minLength} characters required`;
        }

        // Max length check
        if (isValid && rules.maxLength && value.length > rules.maxLength) {
            isValid = false;
            errorMessage = rules.maxLengthMessage || `Maximum ${rules.maxLength} characters allowed`;
        }

        // Pattern check
        if (isValid && rules.pattern && value) {
            const regex = new RegExp(rules.pattern);
            if (!regex.test(value)) {
                isValid = false;
                errorMessage = rules.patternMessage || 'Please match the requested format';
            }
        }

        // Match another field (confirm password)
        if (isValid && rules.match) {
            const matchField = document.querySelector(rules.match);
            if (matchField && value !== matchField.value) {
                isValid = false;
                errorMessage = rules.matchMessage || 'Fields do not match';
            }
        }

        // Custom validator
        if (isValid && rules.customValidator) {
            const customResult = rules.customValidator(value, field);
            if (customResult !== true) {
                isValid = false;
                errorMessage = typeof customResult === 'string' ? customResult : 'Validation failed';
            }
        }

        // Apply state
        if (isValid && value) {
            markSuccess(field);
        } else if (!isValid) {
            markError(field, errorMessage);
        } else {
            clearValidation(field);
        }

        return isValid;
    }

    /**
     * Parse validation rules from field attributes
     */
    function parseRules(field) {
        const rules = {};

        // Required
        if (field.hasAttribute('required') || field.dataset.required) {
            rules.required = true;
            rules.requiredMessage = field.dataset.requiredMessage;
        }

        // Email type
        if (field.type === 'email' || field.dataset.email) {
            rules.email = true;
            rules.emailMessage = field.dataset.emailMessage;
        }

        // Length constraints
        if (field.minLength > 0 || field.dataset.minLength) {
            rules.minLength = parseInt(field.minLength || field.dataset.minLength);
            rules.minLengthMessage = field.dataset.minLengthMessage;
        }

        if (field.maxLength > 0 || field.dataset.maxLength) {
            rules.maxLength = parseInt(field.maxLength || field.dataset.maxLength);
            rules.maxLengthMessage = field.dataset.maxLengthMessage;
        }

        // Pattern
        if (field.pattern || field.dataset.pattern) {
            rules.pattern = field.pattern || field.dataset.pattern;
            rules.patternMessage = field.dataset.patternMessage || field.title;
        }

        // Match field
        if (field.dataset.match) {
            rules.match = field.dataset.match;
            rules.matchMessage = field.dataset.matchMessage;
        }

        return rules;
    }

    /**
     * Validate entire form
     */
    function validateForm(form) {
        if (!form) return true;

        const fields = form.querySelectorAll(config.selectors.field);
        let isFormValid = true;
        let firstInvalid = null;

        fields.forEach(field => {
            const isValid = validateField(field);
            if (!isValid && !firstInvalid) {
                firstInvalid = field;
                isFormValid = false;
            } else if (!isValid) {
                isFormValid = false;
            }
        });

        // Focus first invalid field
        if (firstInvalid) {
            firstInvalid.focus();

            // Scroll into view if needed
            firstInvalid.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }

        return isFormValid;
    }

    /**
     * Initialize auto-validation for a form
     */
    function initForm(form) {
        if (!form || form.dataset.validationInit) return;
        form.dataset.validationInit = 'true';

        const fields = form.querySelectorAll(config.selectors.field);

        // Real-time validation on blur
        fields.forEach(field => {
            // Validate on blur
            field.addEventListener('blur', () => {
                if (field.value.trim()) {
                    validateField(field);
                }
            });

            // Clear error on input (debounced)
            const debouncedValidate = debounce(() => {
                if (field.classList.contains('is-invalid')) {
                    validateField(field);
                }
            }, config.debounceMs);

            field.addEventListener('input', () => {
                // Remove error state while typing
                if (field.classList.contains('is-invalid')) {
                    field.classList.add('form-typing');
                    debouncedValidate();
                }
            });

            field.addEventListener('blur', () => {
                field.classList.remove('form-typing');
            });
        });

        // Validate on submit
        form.addEventListener('submit', (e) => {
            const isValid = validateForm(form);
            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();

                // Shake submit button
                const submitBtn = form.querySelector('[type="submit"]');
                if (submitBtn && !prefersReducedMotion) {
                    submitBtn.classList.add('form-shake-subtle');
                    setTimeout(() => submitBtn.classList.remove('form-shake-subtle'), 400);
                }
            }
        });
    }

    /**
     * Set up character counter
     */
    function initCharCounter(field, maxLength) {
        const counter = document.createElement('div');
        counter.className = 'char-counter';

        const parent = field.closest('.form-validation-wrapper') || field.parentElement;
        parent.appendChild(counter);

        function updateCounter() {
            const length = field.value.length;
            counter.textContent = `${length}/${maxLength}`;

            counter.classList.remove('warning', 'error');
            if (length >= maxLength) {
                counter.classList.add('error');
            } else if (length >= maxLength * 0.9) {
                counter.classList.add('warning');
            }
        }

        field.addEventListener('input', updateCounter);
        updateCounter();

        return counter;
    }

    /**
     * Set up password strength indicator
     */
    function initPasswordStrength(field) {
        const container = document.createElement('div');
        container.className = 'password-strength';
        container.innerHTML = '<div class="password-strength-bar"></div>';

        const parent = field.closest('.form-validation-wrapper') || field.parentElement;
        parent.appendChild(container);

        function checkStrength(password) {
            let strength = 0;

            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            const levels = ['', 'weak', 'fair', 'good', 'strong'];
            const level = Math.min(strength, 4);
            container.dataset.strength = levels[level];
        }

        field.addEventListener('input', () => checkStrength(field.value));
        checkStrength(field.value);

        return container;
    }

    /**
     * Initialize all forms on page
     */
    function init() {
        if (!config.autoValidate) return;

        const forms = document.querySelectorAll(config.selectors.form);
        forms.forEach(initForm);

        // Watch for new forms
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;

                    if (node.matches && node.matches(config.selectors.form)) {
                        initForm(node);
                    }

                    if (node.querySelectorAll) {
                        node.querySelectorAll(config.selectors.form).forEach(initForm);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.warn(`[FormValidation] Initialized ${forms.length} forms`);
    }

    // Public API
    window.FormValidation = {
        error: markError,
        success: markSuccess,
        clear: clearValidation,
        validate: validateField,
        validateForm: validateForm,
        initForm: initForm,
        charCounter: initCharCounter,
        passwordStrength: initPasswordStrength,
        config: (newConfig) => Object.assign(config, newConfig)
    };

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        setTimeout(init, 50);
    }

})();
