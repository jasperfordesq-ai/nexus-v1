<?php
// Consolidated Login View (Single Source of Truth)
// Adapts to Modern, Social, and CivicOne layouts.

$layout = \Nexus\Services\LayoutHelper::get(); // Get current layout

// 1. Header Selection
if ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/header.php';
    echo '<div class="civicone-wrapper civicone-wrapper-auth">';
} else {
    // Modern Defaults
    $hero_title = "Member Login";
    $hero_subtitle = "Welcome back to the community.";
    $hero_gradient = 'htb-hero-gradient-brand';
    require dirname(__DIR__) . '/../layouts/civicone/header.php';
}

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="auth-wrapper">
    <div class="htb-card auth-card">
        <div class="auth-card-body">

            <h2 class="auth-title">Sign In</h2>

            <?php if (isset($_GET['registered'])): ?>
                <div class="auth-alert auth-alert-success">
                    Registration successful! Please login.
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="auth-alert auth-alert-error">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Biometric Login Option (for users who have set it up) -->
            <div id="biometric-login-container" class="biometric-container">
                <button type="button" id="biometric-login-btn" class="biometric-btn" onclick="attemptBiometricLogin()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364a6 6 0 0112 0c0 .894-.074 1.771-.214 2.626M5 11a7 7 0 1114 0"></path>
                    </svg>
                    Sign In with Biometrics
                </button>
                <div class="biometric-divider">or</div>
            </div>

            <!-- Biometric Feature Promo (for devices that support it but haven't set up) -->
            <div id="biometric-promo" class="biometric-promo">
                <div class="biometric-promo-inner">
                    <div class="biometric-promo-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364a6 6 0 0112 0c0 .894-.074 1.771-.214 2.626M5 11a7 7 0 1114 0"></path>
                        </svg>
                    </div>
                    <div class="biometric-promo-content">
                        <div class="biometric-promo-title">Fingerprint & Face ID Available</div>
                        <div class="biometric-promo-subtitle">Sign in faster after your first login</div>
                    </div>
                    <div class="biometric-promo-badge">New</div>
                </div>
            </div>

            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" method="POST">
                <?= Nexus\Core\Csrf::input() ?>

                <div class="auth-form-group">
                    <label for="login-email" class="auth-label">Email Address</label>
                    <input type="email" name="email" id="login-email" required placeholder="e.g. you@example.com"
                        autocomplete="email webauthn"
                        class="auth-input">
                </div>

                <div class="auth-form-group-lg">
                    <div class="auth-label-row">
                        <label for="login-password" class="auth-label-inline">Password</label>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/forgot" class="auth-link">Forgot?</a>
                    </div>
                    <input type="password" name="password" id="login-password" required
                        autocomplete="current-password"
                        class="auth-input">
                </div>

                <button type="submit" class="auth-submit-btn">
                    Sign In
                </button>
            </form>

            <?php
            // Safe Include for Social Login
            $socialPath = __DIR__ . '/../../partials/social_login.php';
            if (file_exists($socialPath)) {
                include $socialPath;
            }
            ?>

            <div class="auth-footer-text">
                Don't have an account? <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="auth-link-primary">Join Now</a>
            </div>

        </div>
    </div>
</div>

<!-- Auth Login CSS -->
<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/purged/civicone-auth-login.min.css">

<!-- Auth Login JavaScript -->
<script src="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-auth-login.min.js" defer></script>

<?php
// Close Wrappers & Include Footer
if ($layout === 'civicone') {
    echo '</div>'; // Close civicone-wrapper
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/civicone/footer.php';
}
?>
