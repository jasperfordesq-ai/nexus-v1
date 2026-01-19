<?php
// Consolidated Reset Password View
// Adapts to Modern, Social, and CivicOne layouts.

$layout = \Nexus\Services\LayoutHelper::get(); // Get current layout

// 1. Header Selection
if ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/header.php';
    echo '<div class="civicone-wrapper" style="padding-top: 40px;">';
} else {
    // Modern Defaults
    $hero_title = "New Password";
    $hero_subtitle = "Secure your account.";
    $hero_gradient = 'htb-hero-gradient-brand';
    $hero_type = 'Authentication';
    require dirname(__DIR__) . '/../layouts/modern/header.php';
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

<!-- Auth CSS now loaded via header.php: auth.min.css -->

<?php
// Close Wrappers & Include Footer
if ($layout === 'civicone') {
    echo '</div>'; // Close civicone-wrapper
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/modern/footer.php';
}
?>