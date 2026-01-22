/**
 * CivicOne Registration Form
 * Password strength validation and organization field toggle
 * WCAG 2.1 AA Compliant
 */

(function() {
    'use strict';

    // ============================================
    // Toggle Organization Field
    // ============================================
    function toggleOrgField() {
        const typeSelect = document.getElementById('profile_type_select');
        const orgContainer = document.getElementById('org_field_container');
        const orgInput = document.getElementById('organization_name');

        if (!typeSelect || !orgContainer) return;

        const type = typeSelect.value;

        if (type === 'organisation') {
            orgContainer.style.display = 'block';
            if (orgInput) orgInput.setAttribute('required', 'required');
        } else {
            orgContainer.style.display = 'none';
            if (orgInput) {
                orgInput.removeAttribute('required');
                orgInput.value = ''; // Clear value when hidden
            }
        }
    }

    // ============================================
    // Password Strength Validation
    // ============================================
    function checkPasswordStrength() {
        const password = document.getElementById('password').value;
        const submitBtn = document.getElementById('submit-btn');

        // Define password rules
        const rules = {
            length: password.length >= 12,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            symbol: /[\W_]/.test(password)
        };

        let allValid = true;

        // Update each rule indicator
        for (const [key, passed] of Object.entries(rules)) {
            const el = document.getElementById('rule-' + key);
            if (!el) continue;

            const originalText = el.textContent.replace(/^[✅❌]\s*/, '');

            if (passed) {
                el.innerHTML = '✅ ' + originalText;
                el.style.color = '#16a34a'; // Green
            } else {
                el.innerHTML = '❌ ' + originalText;
                el.style.color = '#ef4444'; // Red
                allValid = false;
            }
        }

        // Enable/disable submit button
        if (submitBtn) {
            if (allValid) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            }
        }
    }

    // ============================================
    // Public API
    // ============================================
    window.civiconeRegister = {
        toggleOrgField: toggleOrgField,
        checkPasswordStrength: checkPasswordStrength
    };

    // ============================================
    // Auto-attach Event Listeners
    // ============================================
    function init() {
        const typeSelect = document.getElementById('profile_type_select');
        const passwordInput = document.getElementById('password');

        if (typeSelect) {
            typeSelect.addEventListener('change', toggleOrgField);
            // Check initial state
            toggleOrgField();
        }

        if (passwordInput) {
            passwordInput.addEventListener('keyup', checkPasswordStrength);
            // Check initial state
            checkPasswordStrength();
        }
    }

    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
