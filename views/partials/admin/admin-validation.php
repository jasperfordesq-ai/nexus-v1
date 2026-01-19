<?php
/**
 * Admin Form Validation - Gold Standard v2.0
 * Live client-side validation with visual feedback
 */
?>

<style>
/* Validation States */
.admin-form-group {
    position: relative;
    margin-bottom: 1.5rem;
}

.admin-form-group.has-error .admin-input,
.admin-form-group.has-error .admin-textarea,
.admin-form-group.has-error .admin-select,
.admin-modal-form-group.has-error .admin-modal-input,
.admin-modal-form-group.has-error .admin-modal-textarea {
    border-color: #ef4444;
    background: rgba(239, 68, 68, 0.05);
}

.admin-form-group.has-success .admin-input,
.admin-form-group.has-success .admin-textarea,
.admin-modal-form-group.has-success .admin-modal-input,
.admin-modal-form-group.has-success .admin-modal-textarea {
    border-color: #10b981;
}

.admin-validation-icon {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1rem;
    pointer-events: none;
}

.admin-form-group.has-error .admin-validation-icon {
    color: #ef4444;
}

.admin-form-group.has-success .admin-validation-icon {
    color: #10b981;
}

.admin-error-message {
    display: none;
    font-size: 0.8rem;
    color: #ef4444;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-form-group.has-error .admin-error-message {
    display: flex;
}

.admin-success-message {
    display: none;
    font-size: 0.8rem;
    color: #10b981;
    margin-top: 0.5rem;
}

.admin-form-group.has-success .admin-success-message {
    display: block;
}

/* Validation Summary */
.admin-validation-summary {
    padding: 1rem 1.25rem;
    border-radius: 12px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    margin-bottom: 1.5rem;
    display: none;
}

.admin-validation-summary.show {
    display: block;
}

.admin-validation-summary-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #ef4444;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-validation-summary-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.admin-validation-summary-list li {
    font-size: 0.85rem;
    color: #f87171;
    padding: 0.25rem 0;
    padding-left: 1.5rem;
    position: relative;
}

.admin-validation-summary-list li::before {
    content: '•';
    position: absolute;
    left: 0.5rem;
}
</style>

<script>
/**
 * Admin Form Validation System
 */
window.AdminValidation = {
    validators: {
        required: function(value) {
            return value.trim() !== '';
        },
        email: function(value) {
            if (!value) return true; // Only validate if has value
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },
        url: function(value) {
            if (!value) return true;
            try {
                new URL(value);
                return true;
            } catch {
                return false;
            }
        },
        min: function(value, min) {
            if (!value) return true;
            return parseFloat(value) >= parseFloat(min);
        },
        max: function(value, max) {
            if (!value) return true;
            return parseFloat(value) <= parseFloat(max);
        },
        minLength: function(value, length) {
            if (!value) return true;
            return value.length >= parseInt(length);
        },
        maxLength: function(value, length) {
            if (!value) return true;
            return value.length <= parseInt(length);
        },
        pattern: function(value, pattern) {
            if (!value) return true;
            const regex = new RegExp(pattern);
            return regex.test(value);
        },
        match: function(value, targetSelector) {
            if (!value) return true;
            const target = document.querySelector(targetSelector);
            return target && value === target.value;
        },
        phone: function(value) {
            if (!value) return true;
            return /^[\d\s\-\+\(\)]+$/.test(value) && value.replace(/\D/g, '').length >= 10;
        },
        alphanumeric: function(value) {
            if (!value) return true;
            return /^[a-zA-Z0-9]+$/.test(value);
        },
        numeric: function(value) {
            if (!value) return true;
            return /^\d+(\.\d+)?$/.test(value);
        }
    },

    errorMessages: {
        required: 'This field is required',
        email: 'Please enter a valid email address',
        url: 'Please enter a valid URL',
        min: 'Value must be at least {min}',
        max: 'Value must be at most {max}',
        minLength: 'Must be at least {length} characters',
        maxLength: 'Must be at most {length} characters',
        pattern: 'Invalid format',
        match: 'Fields do not match',
        phone: 'Please enter a valid phone number',
        alphanumeric: 'Only letters and numbers allowed',
        numeric: 'Please enter a valid number'
    },

    /**
     * Initialize validation for a form
     * @param {string} formSelector - Form selector
     * @param {Object} options - Configuration options
     */
    init: function(formSelector, options) {
        const defaults = {
            validateOnBlur: true,
            validateOnInput: true,
            validateOnSubmit: true,
            showSuccessState: false,
            scrollToError: true,
            summarySelector: null
        };

        const config = Object.assign({}, defaults, options);
        const form = document.querySelector(formSelector);

        if (!form) {
            console.error('AdminValidation: Form not found');
            return;
        }

        const self = this;
        const inputs = form.querySelectorAll('input[data-validate], textarea[data-validate], select[data-validate]');

        // Add event listeners
        inputs.forEach(input => {
            if (config.validateOnBlur) {
                input.addEventListener('blur', function() {
                    self.validateField(this, config);
                });
            }

            if (config.validateOnInput) {
                input.addEventListener('input', function() {
                    // Only validate on input if field has been touched
                    if (this.dataset.touched === 'true') {
                        self.validateField(this, config);
                    }
                });

                input.addEventListener('blur', function() {
                    this.dataset.touched = 'true';
                });
            }
        });

        // Form submit validation
        if (config.validateOnSubmit) {
            form.addEventListener('submit', function(e) {
                if (!self.validateForm(form, config)) {
                    e.preventDefault();
                    return false;
                }
            });
        }
    },

    /**
     * Validate a single field
     * @param {HTMLElement} input - Input element
     * @param {Object} config - Configuration
     * @returns {boolean}
     */
    validateField: function(input, config) {
        const rules = this.parseValidationRules(input);
        const value = input.value;
        const formGroup = input.closest('.admin-form-group, .admin-modal-form-group');

        if (!formGroup) return true;

        // Clear previous state
        formGroup.classList.remove('has-error', 'has-success');
        const existingError = formGroup.querySelector('.admin-error-message');
        if (existingError) existingError.remove();
        const existingIcon = formGroup.querySelector('.admin-validation-icon');
        if (existingIcon) existingIcon.remove();

        // Validate each rule
        for (const rule of rules) {
            const validator = this.validators[rule.type];
            if (!validator) continue;

            const isValid = rule.param
                ? validator(value, rule.param)
                : validator(value);

            if (!isValid) {
                this.showError(formGroup, input, rule, config);
                return false;
            }
        }

        // Show success state if configured
        if (config.showSuccessState && value !== '') {
            this.showSuccess(formGroup, input);
        }

        return true;
    },

    /**
     * Validate entire form
     * @param {HTMLElement} form - Form element
     * @param {Object} config - Configuration
     * @returns {boolean}
     */
    validateForm: function(form, config) {
        const inputs = form.querySelectorAll('input[data-validate], textarea[data-validate], select[data-validate]');
        let isValid = true;
        const errors = [];

        inputs.forEach(input => {
            if (!this.validateField(input, config)) {
                isValid = false;
                const label = input.closest('.admin-form-group, .admin-modal-form-group')?.querySelector('label')?.textContent || 'Field';
                const errorMsg = input.closest('.admin-form-group, .admin-modal-form-group')?.querySelector('.admin-error-message')?.textContent;
                if (errorMsg) {
                    errors.push({ label, message: errorMsg });
                }
            }
        });

        // Show validation summary if configured
        if (config.summarySelector && errors.length > 0) {
            this.showValidationSummary(config.summarySelector, errors);
        }

        // Scroll to first error
        if (!isValid && config.scrollToError) {
            const firstError = form.querySelector('.admin-form-group.has-error, .admin-modal-form-group.has-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        return isValid;
    },

    /**
     * Parse validation rules from data attribute
     */
    parseValidationRules: function(input) {
        const rules = [];
        const validateAttr = input.getAttribute('data-validate');
        if (!validateAttr) return rules;

        const ruleStrings = validateAttr.split('|');
        ruleStrings.forEach(ruleString => {
            const [type, param] = ruleString.split(':');
            rules.push({ type: type.trim(), param: param?.trim() });
        });

        return rules;
    },

    /**
     * Show error state
     */
    showError: function(formGroup, input, rule, config) {
        formGroup.classList.add('has-error');

        // Add error icon
        if (!input.matches('textarea')) {
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-circle-xmark admin-validation-icon';
            formGroup.appendChild(icon);
        }

        // Add error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'admin-error-message';
        errorDiv.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' +
            this.getErrorMessage(rule, input);
        formGroup.appendChild(errorDiv);
    },

    /**
     * Show success state
     */
    showSuccess: function(formGroup, input) {
        formGroup.classList.add('has-success');

        if (!input.matches('textarea')) {
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-circle-check admin-validation-icon';
            formGroup.appendChild(icon);
        }
    },

    /**
     * Get error message for a rule
     */
    getErrorMessage: function(rule, input) {
        const customMessage = input.getAttribute('data-error-' + rule.type);
        if (customMessage) return customMessage;

        let message = this.errorMessages[rule.type] || 'Invalid value';

        // Replace placeholders
        if (rule.param) {
            message = message.replace('{' + rule.type + '}', rule.param);
            message = message.replace('{length}', rule.param);
        }

        return message;
    },

    /**
     * Show validation summary
     */
    showValidationSummary: function(selector, errors) {
        const summary = document.querySelector(selector);
        if (!summary) return;

        const list = errors.map(err =>
            `<li><strong>${err.label}:</strong> ${err.message}</li>`
        ).join('');

        summary.innerHTML = `
            <div class="admin-validation-summary-title">
                <i class="fa-solid fa-circle-exclamation"></i>
                Please correct the following errors:
            </div>
            <ul class="admin-validation-summary-list">
                ${list}
            </ul>
        `;

        summary.classList.add('show');
    }
};
</script>
