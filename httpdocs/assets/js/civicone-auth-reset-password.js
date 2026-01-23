/**
 * CivicOne Auth Reset Password - Client-side validation
 * GOV.UK Design System compliant
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0
 * @since 2026-01-23
 */

(function() {
    'use strict';

    var form = document.getElementById('reset-password-form');
    if (!form) return;

    var passwordInput = document.getElementById('password');
    var confirmInput = document.getElementById('confirm_password');
    var submitBtn = document.getElementById('submit-btn');
    var matchStatus = document.getElementById('password-match-status');

    if (!passwordInput || !confirmInput || !submitBtn) return;

    var rules = {
        length: { el: document.getElementById('rule-length'), test: function(p) { return p.length >= 12; } },
        upper: { el: document.getElementById('rule-upper'), test: function(p) { return /[A-Z]/.test(p); } },
        lower: { el: document.getElementById('rule-lower'), test: function(p) { return /[a-z]/.test(p); } },
        number: { el: document.getElementById('rule-number'), test: function(p) { return /[0-9]/.test(p); } },
        symbol: { el: document.getElementById('rule-symbol'), test: function(p) { return /[!@#$%^&*()_+\-=[\]{}|;:,.<>?]/.test(p); } }
    };

    function checkPassword() {
        var password = passwordInput.value;
        var allPassed = true;

        for (var key in rules) {
            if (Object.prototype.hasOwnProperty.call(rules, key)) {
                var rule = rules[key];
                if (!rule.el) continue;

                var passed = rule.test(password);
                var span = rule.el.querySelector('span');

                if (passed) {
                    rule.el.classList.remove('civicone-rule-pending', 'civicone-rule-failed');
                    rule.el.classList.add('civicone-rule-passed');
                    if (span) span.textContent = '\u2713'; // checkmark
                } else {
                    rule.el.classList.remove('civicone-rule-passed', 'civicone-rule-pending');
                    rule.el.classList.add(password.length > 0 ? 'civicone-rule-failed' : 'civicone-rule-pending');
                    if (span) span.textContent = password.length > 0 ? '\u2717' : '\u25CB'; // X or circle
                    allPassed = false;
                }
            }
        }

        checkMatch();
        return allPassed;
    }

    function checkMatch() {
        var password = passwordInput.value;
        var confirm = confirmInput.value;
        var allRulesPassed = true;

        for (var key in rules) {
            if (Object.prototype.hasOwnProperty.call(rules, key) && rules[key].el && !rules[key].test(password)) {
                allRulesPassed = false;
                break;
            }
        }

        if (matchStatus) {
            if (confirm.length === 0) {
                matchStatus.textContent = '';
                matchStatus.className = 'govuk-body-s govuk-!-margin-top-2';
            } else if (password === confirm && allRulesPassed) {
                matchStatus.textContent = '\u2713 Passwords match';
                matchStatus.className = 'govuk-body-s govuk-!-margin-top-2 civicone-match-success';
            } else if (password !== confirm) {
                matchStatus.textContent = '\u2717 Passwords do not match';
                matchStatus.className = 'govuk-body-s govuk-!-margin-top-2 civicone-match-error';
            }
        }

        // Enable/disable submit
        var canSubmit = allRulesPassed && password === confirm && confirm.length > 0;
        submitBtn.disabled = !canSubmit;
        submitBtn.setAttribute('aria-disabled', String(!canSubmit));
    }

    passwordInput.addEventListener('input', checkPassword);
    confirmInput.addEventListener('input', checkMatch);

    // Prevent form submission if invalid
    form.addEventListener('submit', function(e) {
        if (submitBtn.disabled) {
            e.preventDefault();
        }
    });

    // Expose functions globally for backward compatibility
    window.checkPasswordStrength = checkPassword;
    window.checkPasswordMatch = checkMatch;
})();
