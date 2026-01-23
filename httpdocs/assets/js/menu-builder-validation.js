/**
 * Menu Builder Form Validation
 * Client-side validation for menu items and settings
 */

class MenuBuilderValidation {
    constructor() {
        this.validationRules = {
            label: {
                required: true,
                minLength: 1,
                maxLength: 100,
                pattern: /^.+$/,
                message: 'Label is required and must be 1-100 characters'
            },
            url: {
                required: false,
                maxLength: 500,
                pattern: /^(\/|https?:\/\/)/,
                message: 'URL must start with / or http(s)://'
            },
            icon: {
                required: false,
                maxLength: 100,
                pattern: /^(fa-[a-z]+ fa-[a-z0-9-]+|)$/,
                message: 'Icon must be a valid FontAwesome class (e.g., fa-solid fa-home)'
            },
            css_class: {
                required: false,
                maxLength: 200,
                pattern: /^[a-zA-Z0-9_\- ]*$/,
                message: 'CSS class can only contain letters, numbers, hyphens, underscores, and spaces'
            },
            menu_name: {
                required: true,
                minLength: 1,
                maxLength: 100,
                message: 'Menu name is required and must be 1-100 characters'
            },
            menu_slug: {
                required: true,
                minLength: 1,
                maxLength: 100,
                pattern: /^[a-z0-9-]+$/,
                message: 'Slug must contain only lowercase letters, numbers, and hyphens'
            }
        };

        // Dangerous URL protocols to block
        this.dangerousProtocols = [
            'javascript:',
            'data:',
            'vbscript:',
            'file:',
            'about:'
        ];

        // Allowed URL protocols
        this.allowedProtocols = [
            'http://',
            'https://',
            '/',
            '#'
        ];
    }

    /**
     * Validate a single field
     */
    validateField(fieldName, value) {
        const rules = this.validationRules[fieldName];
        if (!rules) {
            return { valid: true };
        }

        // Check required
        if (rules.required && (!value || value.trim() === '')) {
            return {
                valid: false,
                message: rules.message || `${fieldName} is required`
            };
        }

        // Skip other checks if not required and empty
        if (!rules.required && (!value || value.trim() === '')) {
            return { valid: true };
        }

        // Check minLength
        if (rules.minLength && value.length < rules.minLength) {
            return {
                valid: false,
                message: `${fieldName} must be at least ${rules.minLength} characters`
            };
        }

        // Check maxLength
        if (rules.maxLength && value.length > rules.maxLength) {
            return {
                valid: false,
                message: `${fieldName} must be no more than ${rules.maxLength} characters`
            };
        }

        // Check pattern
        if (rules.pattern && !rules.pattern.test(value)) {
            return {
                valid: false,
                message: rules.message
            };
        }

        return { valid: true };
    }

    /**
     * Validate URL for security
     */
    validateURL(url) {
        if (!url || url.trim() === '') {
            return { valid: true };
        }

        const trimmedUrl = url.trim().toLowerCase();

        // Check for dangerous protocols
        for (const protocol of this.dangerousProtocols) {
            if (trimmedUrl.startsWith(protocol)) {
                return {
                    valid: false,
                    message: `Dangerous protocol detected: ${protocol}. Only http, https, and relative paths are allowed.`
                };
            }
        }

        // Check if it starts with an allowed protocol or is a relative path
        const hasValidStart = this.allowedProtocols.some(protocol =>
            trimmedUrl.startsWith(protocol)
        );

        if (!hasValidStart) {
            return {
                valid: false,
                message: 'URL must start with /, #, http://, or https://'
            };
        }

        // Additional length check
        if (url.length > 500) {
            return {
                valid: false,
                message: 'URL is too long (max 500 characters)'
            };
        }

        return { valid: true };
    }

    /**
     * Sanitize FontAwesome icon class
     */
    validateIcon(icon) {
        if (!icon || icon.trim() === '') {
            return { valid: true, sanitized: '' };
        }

        const trimmed = icon.trim();

        // Must be in format: fa-{style} fa-{name}
        // Examples: fa-solid fa-home, fa-regular fa-user
        const iconPattern = /^fa-(solid|regular|light|thin|duotone|brands) fa-[a-z0-9-]+$/;

        if (!iconPattern.test(trimmed)) {
            return {
                valid: false,
                message: 'Icon must be a valid FontAwesome class (e.g., fa-solid fa-home, fa-brands fa-twitter)'
            };
        }

        return {
            valid: true,
            sanitized: trimmed
        };
    }

    /**
     * Sanitize CSS classes
     */
    validateCssClass(cssClass) {
        if (!cssClass || cssClass.trim() === '') {
            return { valid: true, sanitized: '' };
        }

        // Remove any potentially dangerous characters
        const sanitized = cssClass
            .replace(/[^a-zA-Z0-9_\- ]/g, '')
            .trim();

        if (sanitized !== cssClass.trim()) {
            return {
                valid: false,
                message: 'CSS class contains invalid characters. Only letters, numbers, hyphens, underscores, and spaces are allowed.'
            };
        }

        if (sanitized.length > 200) {
            return {
                valid: false,
                message: 'CSS class is too long (max 200 characters)'
            };
        }

        return {
            valid: true,
            sanitized: sanitized
        };
    }

    /**
     * Validate menu item form
     */
    validateMenuItemForm(formData) {
        const errors = [];
        const type = formData.get('type');

        // Validate label (always required)
        const labelValidation = this.validateField('label', formData.get('label'));
        if (!labelValidation.valid) {
            errors.push({ field: 'label', message: labelValidation.message });
        }

        // Validate URL (if not page or dropdown or divider type)
        if (type !== 'page' && type !== 'dropdown' && type !== 'divider') {
            const url = formData.get('url');
            const urlValidation = this.validateURL(url);
            if (!urlValidation.valid) {
                errors.push({ field: 'url', message: urlValidation.message });
            }
        }

        // Validate icon
        const icon = formData.get('icon');
        if (icon && icon.trim() !== '') {
            const iconValidation = this.validateIcon(icon);
            if (!iconValidation.valid) {
                errors.push({ field: 'icon', message: iconValidation.message });
            }
        }

        // Validate CSS class
        const cssClass = formData.get('css_class');
        if (cssClass && cssClass.trim() !== '') {
            const cssValidation = this.validateCssClass(cssClass);
            if (!cssValidation.valid) {
                errors.push({ field: 'css_class', message: cssValidation.message });
            }
        }

        return {
            valid: errors.length === 0,
            errors: errors
        };
    }

    /**
     * Validate menu settings form
     */
    validateMenuSettingsForm(formData) {
        const errors = [];

        // Validate menu name
        const nameValidation = this.validateField('menu_name', formData.get('name'));
        if (!nameValidation.valid) {
            errors.push({ field: 'name', message: nameValidation.message });
        }

        // Validate slug
        const slugValidation = this.validateField('menu_slug', formData.get('slug'));
        if (!slugValidation.valid) {
            errors.push({ field: 'slug', message: slugValidation.message });
        }

        return {
            valid: errors.length === 0,
            errors: errors
        };
    }

    /**
     * Show validation error on a form field
     */
    showFieldError(fieldName, message) {
        const field = document.getElementById(`item_${fieldName}`) || document.getElementById(`menu_${fieldName}`);
        if (!field) return;

        // Remove existing error
        this.clearFieldError(fieldName);

        // Add error class
        field.classList.add('validation-error');

        // Create error message element
        const errorElement = document.createElement('div');
        errorElement.className = 'validation-error-message';
        errorElement.textContent = message;
        errorElement.style.cssText = `
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            animation: slideDown 0.2s ease;
        `;

        // Insert after field
        field.parentNode.insertBefore(errorElement, field.nextSibling);
    }

    /**
     * Clear validation error from a field
     */
    clearFieldError(fieldName) {
        const field = document.getElementById(`item_${fieldName}`) || document.getElementById(`menu_${fieldName}`);
        if (!field) return;

        field.classList.remove('validation-error');

        // Remove error message
        const errorMessage = field.parentNode.querySelector('.validation-error-message');
        if (errorMessage) {
            errorMessage.remove();
        }
    }

    /**
     * Clear all validation errors
     */
    clearAllErrors() {
        document.querySelectorAll('.validation-error').forEach(field => {
            field.classList.remove('validation-error');
        });

        document.querySelectorAll('.validation-error-message').forEach(msg => {
            msg.remove();
        });
    }

    /**
     * Show validation summary
     */
    showValidationSummary(errors) {
        const summary = document.createElement('div');
        summary.className = 'validation-summary';
        summary.style.cssText = `
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #ef4444;
        `;

        const title = document.createElement('div');
        title.className = 'menu-builder-error-title';
        title.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Please fix the following errors:';

        const list = document.createElement('ul');
        list.className = 'menu-builder-error-list';

        errors.forEach(error => {
            const item = document.createElement('li');
            item.textContent = error.message;
            list.appendChild(item);
        });

        summary.appendChild(title);
        summary.appendChild(list);

        // Find modal body and prepend summary
        const modalBody = document.querySelector('.admin-modal-body');
        if (modalBody) {
            // Remove existing summary
            const existingSummary = modalBody.querySelector('.validation-summary');
            if (existingSummary) {
                existingSummary.remove();
            }
            modalBody.insertBefore(summary, modalBody.firstChild);
        }
    }
}

// Add validation styles
if (!document.getElementById('menu-validation-styles')) {
    const style = document.createElement('style');
    style.id = 'menu-validation-styles';
    style.textContent = `
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .validation-error {
            border-color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.05) !important;
        }

        .validation-error:focus {
            outline: 2px solid #ef4444;
            outline-offset: 2px;
        }
    `;
    document.head.appendChild(style);
}

// Export for global use
window.MenuBuilderValidation = MenuBuilderValidation;
