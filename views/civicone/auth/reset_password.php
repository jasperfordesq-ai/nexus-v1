<?php
/**
 * CivicOne Auth Reset Password - New Password Form
 * Template D: Form/Flow (Section 10.7)
 * Password reset form with real-time validation
 * WCAG 2.1 AA Compliant
 */
$heroTitle = "New Password";
$heroSub = "Secure your account.";
$heroType = 'Authentication';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>
<link rel="stylesheet" href="<?= Nexus\Core\TenantContext::getBasePath() ?>/assets/css/purged/civicone-auth-forms.min.css?v=<?= time() ?>">

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">
        <div class="civic-auth-wrapper">
            <div class="civic-auth-card">
                <div class="civic-auth-card-body">

                    <p class="civic-auth-description">
                        Please enter your new password below.
                    </p>

                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/reset" method="POST" class="civic-auth-form">
                        <?= Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

                        <div class="civic-form-group">
                            <label for="password" class="civic-form-label">New Password</label>
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="civic-form-input"
                                   autocomplete="new-password"
                                   onkeyup="checkPasswordStrength()"
                                   required>

                            <!-- Password Strength Meter -->
                            <div id="password-rules" class="civic-password-rules">
                                <div class="civic-rules-title">Password Requirements:</div>
                                <div id="rule-length">&#10060; At least 12 characters</div>
                                <div id="rule-upper">&#10060; At least 1 uppercase letter</div>
                                <div id="rule-lower">&#10060; At least 1 lowercase letter</div>
                                <div id="rule-number">&#10060; At least 1 number</div>
                                <div id="rule-symbol">&#10060; At least 1 special character (!@#$)</div>
                            </div>
                        </div>

                        <div class="civic-form-group">
                            <label for="confirm_password" class="civic-form-label">Confirm Password</label>
                            <input type="password"
                                   name="confirm_password"
                                   id="confirm_password"
                                   class="civic-form-input"
                                   autocomplete="new-password"
                                   onkeyup="checkPasswordMatch()"
                                   required>
                            <div id="password-match-status" class="civic-password-match-status hidden" role="alert" aria-live="polite"></div>
                        </div>

                        <button type="submit" id="submit-btn" class="civic-auth-submit-btn" disabled>
                            Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div><!-- /civicone-width-container -->

<script src="<?= Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-auth-reset-password.js?v=<?= time() ?>"></script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
