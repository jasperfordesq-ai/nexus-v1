<?php
/**
 * CivicOne View: Login
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Sign In';
require __DIR__ . '/../../layouts/civicone/header.php';

// Load GOV.UK error summary component
require_once __DIR__ . '/../components/govuk/error-summary.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Sign in to your account</h1>

        <?php if (isset($_GET['registered'])): ?>
            <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">
                        <i class="fa-solid fa-check-circle govuk-!-margin-right-2" aria-hidden="true"></i>
                        Registration successful! Please sign in.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <?= civicone_govuk_error_summary([
                'errors' => [
                    ['text' => $error, 'href' => '#login-email']
                ]
            ]) ?>
        <?php endif; ?>

        <!-- Biometric Login Option -->
        <div id="biometric-login-container" class="govuk-!-margin-bottom-6 govuk-!-display-none">
            <button type="button" id="biometric-login-btn" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="attemptBiometricLogin()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="govuk-!-margin-right-2" aria-hidden="true">
                    <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364a6 6 0 0112 0c0 .894-.074 1.771-.214 2.626M5 11a7 7 0 1114 0"></path>
                </svg>
                Sign in with Biometrics
            </button>
            <p class="govuk-body-s govuk-!-margin-top-2 civicone-secondary-text">or sign in with your email</p>
        </div>

        <!-- Biometric Feature Promo -->
        <div id="biometric-promo" class="govuk-inset-text govuk-!-margin-bottom-6 govuk-!-display-none civicone-inset-blue">
            <p class="govuk-body govuk-!-margin-bottom-0">
                <i class="fa-solid fa-fingerprint govuk-!-margin-right-2" aria-hidden="true"></i>
                <strong>Fingerprint & Face ID Available</strong>
            </p>
            <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Sign in faster after your first login</p>
        </div>

        <form action="<?= $basePath ?>/login" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <div class="govuk-form-group">
                <label class="govuk-label" for="login-email">Email address</label>
                <div id="login-email-hint" class="govuk-hint">
                    Enter the email address you registered with
                </div>
                <input type="email" name="email" id="login-email" class="govuk-input" required autocomplete="email webauthn" aria-describedby="login-email-hint" spellcheck="false">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label" for="login-password">Password</label>
                <div id="login-password-hint" class="govuk-hint">
                    <a href="<?= $basePath ?>/password/forgot" class="govuk-link">Forgot your password?</a>
                </div>
                <input type="password" name="password" id="login-password" class="govuk-input" required autocomplete="current-password" aria-describedby="login-password-hint">
            </div>

            <button type="submit" class="govuk-button" data-module="govuk-button">
                Sign in
            </button>
        </form>

        <?php
        // Safe Include for Social Login
        $socialPath = __DIR__ . '/../../partials/social_login.php';
        if (file_exists($socialPath)) {
            include $socialPath;
        }
        ?>

        <p class="govuk-body govuk-!-margin-top-6">
            Don't have an account? <a href="<?= $basePath ?>/register" class="govuk-link">Create an account</a>
        </p>

    </div>
</div>

<!-- Auth Login CSS -->
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/purged/civicone-auth-login.min.css">

<!-- Auth Login JavaScript -->
<script src="<?= $basePath ?>/assets/js/civicone-auth-login.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
