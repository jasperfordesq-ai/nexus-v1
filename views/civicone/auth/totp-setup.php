<?php
/**
 * CivicOne View: TOTP Setup
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 * Full-page setup wizard for 2FA
 */
$pageTitle = 'Set up two-factor authentication';
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Set up two-factor authentication</h1>

        <?php if (!empty($error)): ?>
            <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
                <h2 class="govuk-error-summary__title" id="error-summary-title">
                    There is a problem
                </h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li>
                            <a href="#totp-code"><?= htmlspecialchars(urldecode($error)) ?></a>
                        </li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="govuk-notification-banner" role="region" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">
                    Required
                </h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    Two-factor authentication is mandatory for all accounts.
                </p>
                <p class="govuk-body">You must complete this setup to continue using the service.</p>
            </div>
        </div>

        <ol class="govuk-list govuk-list--number govuk-!-margin-bottom-6">
            <li class="govuk-!-margin-bottom-6">
                <h2 class="govuk-heading-m">Download an authenticator app</h2>
                <p class="govuk-body">If you do not have one, download one of these free apps from your device's app store:</p>
                <ul class="govuk-list govuk-list--bullet">
                    <li>Google Authenticator</li>
                    <li>Authy</li>
                    <li>Microsoft Authenticator</li>
                </ul>
            </li>

            <li class="govuk-!-margin-bottom-6">
                <h2 class="govuk-heading-m">Scan the QR code</h2>
                <p class="govuk-body">Open your authenticator app and scan this QR code:</p>

                <?php if (!empty($qr_code)): ?>
                    <div class="totp-qr-govuk">
                        <?= $qr_code ?>
                    </div>

                    <details class="govuk-details govuk-!-margin-top-4" data-module="govuk-details">
                        <summary class="govuk-details__summary">
                            <span class="govuk-details__summary-text">
                                I cannot scan the QR code
                            </span>
                        </summary>
                        <div class="govuk-details__text">
                            <p class="govuk-body">Enter this code manually in your authenticator app:</p>
                            <p class="totp-secret-govuk"><?= htmlspecialchars($secret) ?></p>
                        </div>
                    </details>
                <?php else: ?>
                    <div class="govuk-warning-text">
                        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                        <strong class="govuk-warning-text__text">
                            <span class="govuk-visually-hidden">Warning</span>
                            Failed to generate QR code. <a href="<?= $basePath ?>/auth/2fa/setup?refresh=1" class="govuk-link">Click here to try again</a>.
                        </strong>
                    </div>
                <?php endif; ?>
            </li>

            <li class="govuk-!-margin-bottom-6">
                <h2 class="govuk-heading-m">Enter the verification code</h2>
                <p class="govuk-body">Enter the 6-digit code shown in your authenticator app to complete setup.</p>

                <form action="<?= $basePath ?>/auth/2fa/setup" method="POST" id="totp-setup-form">
                    <?= \Nexus\Core\Csrf::input() ?>

                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="totp-code">
                            Verification code
                        </label>
                        <div id="totp-code-hint" class="govuk-hint">
                            Enter the 6-digit code from your authenticator app
                        </div>
                        <input class="govuk-input govuk-input--width-10"
                               id="totp-code"
                               name="code"
                               type="text"
                               inputmode="numeric"
                               pattern="[0-9]{6}"
                               maxlength="6"
                               autocomplete="one-time-code"
                               spellcheck="false"
                               aria-describedby="totp-code-hint"
                               autofocus>
                    </div>

                    <button type="submit" class="govuk-button" data-module="govuk-button" data-prevent-double-click="true">
                        Verify and enable 2FA
                    </button>
                </form>
            </li>
        </ol>

        <div class="govuk-inset-text">
            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Why is this required?</h3>
            <p class="govuk-body govuk-!-margin-bottom-0">
                Two-factor authentication adds a second layer of security to your account.
                Even if someone obtains your password, they cannot access your account without your phone.
            </p>
        </div>

    </div>
</div>

<style>
.totp-qr-govuk {
    background: #ffffff;
    padding: 20px;
    display: inline-block;
    border: 3px solid #0b0c0c;
}

.totp-qr-govuk svg {
    width: 180px;
    height: 180px;
    display: block;
}

.totp-secret-govuk {
    background: #0b0c0c;
    color: #00703c;
    padding: 15px 20px;
    font-family: monospace;
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    word-break: break-all;
    display: inline-block;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var codeInput = document.getElementById('totp-code');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }
});
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
