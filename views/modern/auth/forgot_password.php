<?php
// Consolidated Forgot Password View
// Adapts to Modern, Social, and CivicOne layouts.

$layout = \Nexus\Services\LayoutHelper::get(); // Get current layout

// 1. Header Selection
if ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/header.php';
    echo '<div class="civicone-wrapper" style="padding-top: 40px;">';
} else {
    // Modern Defaults
    $hero_title = "Reset Password";
    $hero_subtitle = "Recover access to your account.";
    $hero_gradient = 'htb-hero-gradient-brand';
    $hero_type = 'Authentication';
    require dirname(__DIR__) . '/../layouts/modern/header.php';
}
?>

<div class="auth-wrapper">
    <div class="htb-card auth-card">
        <div class="auth-card-body">

            <div style="text-align: center; margin-bottom: 25px; color: var(--htb-text-main);">
                Enter your email address below and we'll send you a link to reset your password.
            </div>

            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/email" method="POST" class="auth-form">
                <?= Nexus\Core\Csrf::input() ?>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" placeholder="e.g. alice@example.com" required class="form-input">
                </div>

                <button type="submit" class="htb-btn htb-btn-primary auth-submit-btn" style="opacity: 1; pointer-events: auto;">Send Reset Link</button>
            </form>

            <div class="auth-login-link">
                Remember your password? <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login">Login here</a>
            </div>
        </div>
    </div>
</div>


<?php
// Close Wrappers & Include Footer
if ($layout === 'civicone') {
    echo '</div>'; // Close civicone-wrapper
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/modern/footer.php';
}
?>