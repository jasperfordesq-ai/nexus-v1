/**
 * CivicOne Auth Reset Password - Password Validation
 * WCAG 2.1 AA Compliant
 * Real-time password strength validation and match checking
 */

(function() {
    'use strict';

    let passwordValid = false;
    let passwordsMatch = false;

    function checkPasswordStrength() {
        const password = document.getElementById('password').value;
        const rules = {
            length: password.length >= 12,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            symbol: /[\W_]/.test(password)
        };

        passwordValid = true;
        for (const [key, passed] of Object.entries(rules)) {
            const el = document.getElementById('rule-' + key);
            if (!el) continue;

            const text = el.innerHTML.substring(el.innerHTML.indexOf(' ') + 1);
            if (passed) {
                el.innerHTML = '\u2705 ' + text;
                el.style.color = '#16a34a';
            } else {
                el.innerHTML = '\u274C ' + text;
                el.style.color = '#ef4444';
                passwordValid = false;
            }
        }

        checkPasswordMatch();
        updateSubmitButton();
    }

    function checkPasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const statusEl = document.getElementById('password-match-status');

        if (!statusEl) return;

        if (confirmPassword.length === 0) {
            statusEl.style.display = 'none';
            passwordsMatch = false;
        } else if (password === confirmPassword) {
            statusEl.style.display = 'block';
            statusEl.innerHTML = '\u2705 Passwords match';
            statusEl.style.color = '#16a34a';
            passwordsMatch = true;
        } else {
            statusEl.style.display = 'block';
            statusEl.innerHTML = '\u274C Passwords do not match';
            statusEl.style.color = '#ef4444';
            passwordsMatch = false;
        }

        updateSubmitButton();
    }

    function updateSubmitButton() {
        const btn = document.getElementById('submit-btn');
        if (!btn) return;

        if (passwordValid && passwordsMatch) {
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'auto';
            btn.style.cursor = 'pointer';
            btn.disabled = false;
        } else {
            btn.style.opacity = '0.5';
            btn.style.pointerEvents = 'none';
            btn.style.cursor = 'not-allowed';
            btn.disabled = true;
        }
    }

    // Expose functions globally
    window.checkPasswordStrength = checkPasswordStrength;
    window.checkPasswordMatch = checkPasswordMatch;

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateSubmitButton();
    });

})();
