<?php
/**
 * CivicOne Auth Forgot Password - Password Reset Request
 * Template D: Form/Flow (Section 10.7)
 * Email-based password reset request form
 * WCAG 2.1 AA Compliant
 */
$heroTitle = "Reset Password";
$heroSub = "Recover access to your account.";
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
                        Enter your email address below and we'll send you a link to reset your password.
                    </p>

                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/email" method="POST" class="civic-auth-form">
                        <?= Nexus\Core\Csrf::input() ?>

                        <div class="civic-form-group">
                            <label for="email" class="civic-form-label">Email Address</label>
                            <input type="email"
                                   name="email"
                                   id="email"
                                   class="civic-form-input"
                                   placeholder="e.g. alice@example.com"
                                   autocomplete="email"
                                   required>
                        </div>

                        <button type="submit" class="civic-auth-submit-btn">
                            Send Reset Link
                        </button>
                    </form>

                    <div class="civic-auth-link">
                        Remember your password? <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div><!-- /civicone-width-container -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
