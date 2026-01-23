/**
 * Modern Onboarding Page - Interactive Features
 * Handles avatar preview, form validation, and navigation locking
 * Created: 2026-01-23
 */

(function() {
    'use strict';

    // DOM Elements
    const form = document.getElementById('onboardingForm');
    const bioField = document.getElementById('bio');
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarDropzone = document.getElementById('avatarDropzone');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const submitIcon = document.getElementById('submitIcon');
    const alertContainer = document.getElementById('alertContainer');
    const charCurrent = document.getElementById('charCurrent');
    const charCountEl = document.getElementById('bioCharCount');
    const bioFieldContainer = document.getElementById('bioField');

    // State
    let isSubmitting = false;
    const MAX_BIO_LENGTH = 1000;
    const MIN_BIO_LENGTH = 10;
    const MAX_FILE_SIZE = 8 * 1024 * 1024; // 8MB

    /**
     * Initialize all event listeners
     */
    function init() {
        // Bio character count
        if (bioField) {
            updateCharCount();
            bioField.addEventListener('input', updateCharCount);
            bioField.addEventListener('blur', validateBio);
        }

        // Avatar upload handling
        if (avatarInput) {
            avatarInput.addEventListener('change', handleAvatarChange);
        }

        // Avatar dropzone keyboard support
        if (avatarDropzone) {
            avatarDropzone.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    avatarInput.click();
                }
            });

            // Drag and drop support
            avatarDropzone.addEventListener('dragover', handleDragOver);
            avatarDropzone.addEventListener('dragleave', handleDragLeave);
            avatarDropzone.addEventListener('drop', handleDrop);
        }

        // Form submission
        if (form) {
            form.addEventListener('submit', handleSubmit);
        }

        // Navigation prevention
        setupNavigationLock();
    }

    /**
     * Update character count display
     */
    function updateCharCount() {
        if (!bioField || !charCurrent || !charCountEl) return;

        const length = bioField.value.length;
        charCurrent.textContent = length;

        // Update styling based on length
        charCountEl.classList.remove('warning', 'error');

        if (length > MAX_BIO_LENGTH) {
            charCountEl.classList.add('error');
        } else if (length > MAX_BIO_LENGTH - 50) {
            charCountEl.classList.add('warning');
        }
    }

    /**
     * Validate bio field
     * @returns {boolean}
     */
    function validateBio() {
        if (!bioField || !bioFieldContainer) return true;

        const value = bioField.value.trim();
        bioFieldContainer.classList.remove('error', 'success');

        if (!value) {
            bioFieldContainer.classList.add('error');
            return false;
        }

        if (value.length < MIN_BIO_LENGTH) {
            bioFieldContainer.classList.add('error');
            return false;
        }

        bioFieldContainer.classList.add('success');
        return true;
    }

    /**
     * Handle avatar file selection
     * @param {Event} e
     */
    function handleAvatarChange(e) {
        const file = e.target.files && e.target.files[0];
        if (file) {
            processAvatarFile(file);
        }
    }

    /**
     * Process avatar file (validate and preview)
     * @param {File} file
     */
    function processAvatarFile(file) {
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            showAlert('Please select a valid image file (JPG, PNG, GIF, or WebP).', 'error');
            return;
        }

        // Validate file size
        if (file.size > MAX_FILE_SIZE) {
            showAlert('Image is too large. Maximum size is 8MB.', 'error');
            return;
        }

        // Preview the image
        const reader = new FileReader();
        reader.onload = function(e) {
            if (avatarPreview) {
                avatarPreview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar preview" id="avatarImg">';

                // Add success visual feedback
                const avatarField = document.getElementById('avatarField');
                if (avatarField) {
                    avatarField.classList.add('success');
                }
            }
        };
        reader.onerror = function() {
            showAlert('Failed to read the image file. Please try again.', 'error');
        };
        reader.readAsDataURL(file);
    }

    /**
     * Handle drag over event
     * @param {DragEvent} e
     */
    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        avatarDropzone.classList.add('dragover');
    }

    /**
     * Handle drag leave event
     * @param {DragEvent} e
     */
    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        avatarDropzone.classList.remove('dragover');
    }

    /**
     * Handle file drop event
     * @param {DragEvent} e
     */
    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        avatarDropzone.classList.remove('dragover');

        const files = e.dataTransfer && e.dataTransfer.files;
        if (files && files.length > 0) {
            // Update the file input
            if (avatarInput) {
                // Create a new DataTransfer to set the files
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                avatarInput.files = dt.files;
            }
            processAvatarFile(files[0]);
        }
    }

    /**
     * Handle form submission
     * @param {Event} e
     */
    function handleSubmit(e) {
        // Clear previous alerts
        clearAlerts();

        // Validate bio
        const bioValue = bioField ? bioField.value.trim() : '';

        if (!bioValue) {
            e.preventDefault();
            showAlert('Please tell us a bit about yourself.', 'error');
            if (bioField) bioField.focus();
            return;
        }

        if (bioValue.length < MIN_BIO_LENGTH) {
            e.preventDefault();
            showAlert('Please write a bit more about yourself (at least ' + MIN_BIO_LENGTH + ' characters).', 'error');
            if (bioField) bioField.focus();
            return;
        }

        // Mark as submitting to disable beforeunload warning
        isSubmitting = true;

        // Show loading state
        setLoadingState(true);
    }

    /**
     * Set form loading state
     * @param {boolean} loading
     */
    function setLoadingState(loading) {
        if (!submitBtn || !submitText || !submitIcon) return;

        submitBtn.disabled = loading;

        if (loading) {
            submitText.textContent = 'Setting up your profile...';
            submitIcon.className = 'fa-solid fa-spinner fa-spin';
            form.classList.add('ob-loading');
        } else {
            submitText.textContent = 'Complete Setup';
            submitIcon.className = 'fa-solid fa-arrow-right';
            form.classList.remove('ob-loading');
        }
    }

    /**
     * Show alert message
     * @param {string} message
     * @param {string} type - 'error' or 'success'
     */
    function showAlert(message, type) {
        if (!alertContainer) return;

        const icon = type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
        const alertClass = type === 'error' ? 'ob-alert-error' : 'ob-alert-success';

        const alertHTML = '<div class="ob-alert ' + alertClass + '" role="alert">' +
            '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>' +
            '<span>' + escapeHtml(message) + '</span>' +
            '</div>';

        alertContainer.innerHTML = alertHTML;

        // Auto-dismiss success alerts
        if (type === 'success') {
            setTimeout(clearAlerts, 5000);
        }
    }

    /**
     * Clear all alerts
     */
    function clearAlerts() {
        if (alertContainer) {
            alertContainer.innerHTML = '';
        }
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text
     * @returns {string}
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Setup navigation lock to prevent leaving page
     */
    function setupNavigationLock() {
        // Prevent accidental navigation (but not when form is being submitted)
        window.addEventListener('beforeunload', function(e) {
            if (isSubmitting) {
                return undefined;
            }
            e.preventDefault();
            e.returnValue = 'Are you sure you want to leave? You must complete your profile to access the platform.';
            return e.returnValue;
        });

        // Disable ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                showAlert('Please complete your profile to continue. You cannot skip this step.', 'error');
            }
        });

        // Disable back button navigation
        history.pushState(null, '', location.href);
        window.addEventListener('popstate', function() {
            history.pushState(null, '', location.href);
            showAlert('Please complete your profile to continue. You cannot go back.', 'error');
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
