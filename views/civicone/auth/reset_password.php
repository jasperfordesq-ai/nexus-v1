<?php
// Consolidated Reset Password View
// Adapts to Modern, Social, and CivicOne layouts.

$layout = \Nexus\Services\LayoutHelper::get(); // Get current layout

// 1. Header Selection
else ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/header.php';
    echo '<div class="civicone-wrapper" style="padding-top: 40px;">';
} else {
    // Modern Defaults
    $hero_title = "New Password";
    $hero_subtitle = "Secure your account.";
    $hero_gradient = 'htb-hero-gradient-brand';
    $hero_type = 'Authentication';
    require dirname(__DIR__) . '/../layouts/civicone/header.php';
}
?>

<div class="auth-wrapper">
    <div class="htb-card auth-card">
        <div class="auth-card-body">

            <div style="text-align: center; margin-bottom: 25px; color: var(--htb-text-main);">
                Please enter your new password below.
            </div>

            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/reset" method="POST" class="auth-form">
                <?= Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

                <div class="form-group">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" name="password" id="password" required class="form-input" onkeyup="checkPasswordStrength()">

                    <!-- Password Strength Meter -->
                    <div id="password-rules" class="password-rules">
                        <div class="rules-title">Password Requirements:</div>
                        <div id="rule-length">&#10060; At least 12 characters</div>
                        <div id="rule-upper">&#10060; At least 1 uppercase letter</div>
                        <div id="rule-lower">&#10060; At least 1 lowercase letter</div>
                        <div id="rule-number">&#10060; At least 1 number</div>
                        <div id="rule-symbol">&#10060; At least 1 special character (!@#$)</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required class="form-input" onkeyup="checkPasswordMatch()">
                    <div id="password-match-status" class="password-match-status" style="display: none;"></div>
                </div>

                <button type="submit" id="submit-btn" class="htb-btn htb-btn-primary auth-submit-btn">Update Password</button>
            </form>

            <script>
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
                    if (passwordValid && passwordsMatch) {
                        btn.style.opacity = '1';
                        btn.style.pointerEvents = 'auto';
                        btn.style.cursor = 'pointer';
                    } else {
                        btn.style.opacity = '0.5';
                        btn.style.pointerEvents = 'none';
                        btn.style.cursor = 'not-allowed';
                    }
                }
            </script>
        </div>
    </div>
</div>

<style>
    /* Scoped Refactor Styles */
    .auth-wrapper {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        padding: 0 15px;
        box-sizing: border-box;
    }

    .auth-card {
        margin-top: 0;
        position: relative;
        z-index: 10;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .auth-card-body {
        padding: 50px;
    }

    /* Desktop spacing for no-hero layout */
    @media (min-width: 601px) {
        .auth-wrapper {
            padding-top: 140px;
        }
    }

    .form-group {
        margin-bottom: 20px;
        width: 100%;
    }

    .form-label {
        display: block;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--htb-text-main, #1f2937);
        font-size: 1rem;
    }

    .form-input {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        padding: 12px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: #4f46e5 !important;
    }

    .password-rules {
        margin-top: 12px;
        font-size: 0.85rem;
        color: #6b7280;
        background: #f9fafb;
        padding: 12px;
        border-radius: 6px;
    }

    .rules-title {
        font-weight: 700;
        margin-bottom: 6px;
    }

    .password-match-status {
        margin-top: 8px;
        font-size: 0.85rem;
    }

    .auth-submit-btn {
        width: 100%;
        font-size: 1.1rem;
        padding: 14px;
        background: var(--htb-gradient-brand, linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%));
        border: none;
        color: white;
        border-radius: 8px;
        margin-top: 10px;
        opacity: 0.5;
        pointer-events: none;
        cursor: not-allowed;
    }

    /* Mobile Responsiveness */
    @media (max-width: 600px) {
        .auth-wrapper {
            padding-top: 120px;
            padding-left: 10px;
            padding-right: 10px;
        }

        .auth-card-body {
            padding: 25px !important;
        }
    }

    /* Reset negative margin for Social/Civic layouts */
    

    /* ========================================
       DARK MODE FOR RESET PASSWORD
       ======================================== */

    [data-theme="dark"] .auth-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    }

    [data-theme="dark"] .auth-card-body div[style*="color: var(--htb-text-main)"] {
        color: #e2e8f0 !important;
    }

    [data-theme="dark"] .form-label {
        color: #e2e8f0;
    }

    [data-theme="dark"] .form-input {
        background: rgba(15, 23, 42, 0.6);
        border-color: rgba(255, 255, 255, 0.15);
        color: #f1f5f9;
    }

    [data-theme="dark"] .form-input::placeholder {
        color: #64748b;
    }

    [data-theme="dark"] .form-input:focus {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }

    /* Password Rules */
    [data-theme="dark"] .password-rules {
        background: rgba(15, 23, 42, 0.6);
        color: #94a3b8;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] .rules-title {
        color: #e2e8f0;
    }

    /* Password Match Status */
    [data-theme="dark"] .password-match-status {
        color: #94a3b8;
    }
</style>

<?php
// Close Wrappers & Include Footer
else ($layout === 'civicone') {
    echo '</div>'; // Close civicone-wrapper
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/civicone/footer.php';
}
?>