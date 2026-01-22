/**
 * CivicOne Profile Edit Page - Form Interactions
 *
 * Features:
 * - Avatar preview
 * - Toggle organization field
 * - TinyMCE initialization for bio
 * - Error summary focus management
 *
 * WCAG 2.1 AA Compliant
 * Progressive enhancement - works without JS for basic form submission
 */

(function() {
    'use strict';

    // ==================================================
    // Avatar Preview
    // ==================================================

    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('avatar-preview');
                if (preview) {
                    preview.src = e.target.result;
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // ==================================================
    // Toggle Organization Field
    // ==================================================

    function toggleOrgField() {
        const profileType = document.getElementById('profile_type');
        const container = document.getElementById('org_field_container');

        if (!profileType || !container) return;

        if (profileType.value === 'organisation') {
            container.classList.remove('profile-field-hidden');
            container.classList.add('profile-field-visible');
        } else {
            container.classList.remove('profile-field-visible');
            container.classList.add('profile-field-hidden');
        }
    }

    // ==================================================
    // Initialize TinyMCE
    // ==================================================

    function initializeTinyMCE(apiKey, hasError) {
        if (typeof tinymce === 'undefined') {
            console.warn('TinyMCE not loaded');
            return;
        }

        tinymce.init({
            selector: '#bio-editor',
            height: 200,
            menubar: false,
            statusbar: false,
            plugins: ['link', 'lists', 'emoticons'],
            toolbar: 'bold italic | bullist numlist | link emoticons',
            content_style: `
                body {
                    font-family: "GDS Transport", arial, sans-serif;
                    font-size: 16px;
                    line-height: 1.5;
                    color: #0b0c0c;
                    padding: 8px;
                }
            `,
            placeholder: 'Tell others about yourself...',
            branding: false,
            promotion: false,
            setup: function(editor) {
                // Add aria-describedby to TinyMCE iframe when initialized
                editor.on('init', function() {
                    const iframe = editor.getContainer().querySelector('iframe');
                    if (iframe) {
                        const ariaDescribedBy = hasError ? 'bio-error bio-hint' : 'bio-hint';
                        iframe.setAttribute('aria-describedby', ariaDescribedBy);
                    }
                });
            }
        });
    }

    // ==================================================
    // Focus Error Summary
    // ==================================================

    function focusErrorSummary() {
        const errorSummary = document.querySelector('[data-module="govuk-error-summary"]');
        if (errorSummary) {
            errorSummary.focus();
        }
    }

    // ==================================================
    // Public API
    // ==================================================

    window.CivicProfileEdit = {
        previewAvatar: previewAvatar,
        toggleOrgField: toggleOrgField,
        initializeTinyMCE: initializeTinyMCE,
        focusErrorSummary: focusErrorSummary
    };

    // ==================================================
    // Auto-initialize on DOMContentLoaded
    // ==================================================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Setup profile type change listener
            const profileTypeSelect = document.getElementById('profile_type');
            if (profileTypeSelect) {
                profileTypeSelect.addEventListener('change', toggleOrgField);
            }

            // Setup avatar input change listener
            const avatarInput = document.getElementById('avatar');
            if (avatarInput) {
                avatarInput.addEventListener('change', function() {
                    previewAvatar(this);
                });
            }
        });
    }

})();
