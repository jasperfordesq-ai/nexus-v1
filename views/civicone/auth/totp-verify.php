<?php
/**
 * CivicOne View: TOTP Verification
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 * Full-page verification for 2FA during login
 */
$pageTitle = 'Verify your identity';
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <span class="govuk-caption-xl">Sign in</span>
        <h1 class="govuk-heading-xl">Verify your identity</h1>

        <?php if (!empty($error)): ?>
            <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" tabindex="-1" data-module="govuk-error-summary">
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

        <p class="govuk-body-l">Enter the 6-digit code from your authenticator app to complete sign in.</p>

        <form action="<?= $basePath ?>/auth/2fa" method="POST" id="totp-form" class="govuk-!-margin-bottom-8" novalidate>
            <?= \Nexus\Core\Csrf::input() ?>

            <div class="govuk-form-group <?= !empty($error) ? 'govuk-form-group--error' : '' ?>">
                <label class="govuk-label govuk-label--m" for="totp-code">
                    Authentication code
                </label>
                <div id="totp-code-hint" class="govuk-hint">
                    Enter the 6-digit code shown in your authenticator app (such as Google Authenticator or Authy)
                </div>
                <?php if (!empty($error)): ?>
                    <p id="totp-code-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">Error:</span> <?= htmlspecialchars(urldecode($error)) ?>
                    </p>
                <?php endif; ?>
                <input class="govuk-input govuk-input--width-10 <?= !empty($error) ? 'govuk-input--error' : '' ?>"
                       id="totp-code"
                       name="code"
                       type="text"
                       inputmode="numeric"
                       pattern="[0-9]{6}"
                       maxlength="6"
                       autocomplete="one-time-code"
                       spellcheck="false"
                       aria-describedby="totp-code-hint<?= !empty($error) ? ' totp-code-error' : '' ?>"
                       autofocus>
            </div>

            <div class="govuk-form-group">
                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="remember-device" name="remember_device" type="checkbox" value="1">
                        <label class="govuk-label govuk-checkboxes__label" for="remember-device">
                            Remember this device for 30 days
                        </label>
                        <div id="remember-device-hint" class="govuk-hint govuk-checkboxes__hint">
                            Skip two-factor authentication on this browser for 30 days
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="govuk-button" data-module="govuk-button" data-prevent-double-click="true">
                Verify and sign in
            </button>
        </form>

        <details class="govuk-details" data-module="govuk-details">
            <summary class="govuk-details__summary">
                <span class="govuk-details__summary-text">
                    I cannot access my authenticator app
                </span>
            </summary>
            <div class="govuk-details__text">
                <p class="govuk-body">If you have lost access to your authenticator app, you can use one of your backup codes instead.</p>

                <form action="<?= $basePath ?>/auth/2fa" method="POST" id="backup-form" novalidate>
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="use_backup_code" value="1">

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="backup-code">
                            Backup code
                        </label>
                        <div id="backup-code-hint" class="govuk-hint">
                            Enter one of your 8-character backup codes (for example, ABCD-1234)
                        </div>
                        <input class="govuk-input govuk-input--width-10"
                               id="backup-code"
                               name="code"
                               type="text"
                               maxlength="9"
                               spellcheck="false"
                               autocomplete="off"
                               class="backup-code-input-govuk"
                               aria-describedby="backup-code-hint">
                    </div>

                    <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button" data-prevent-double-click="true">
                        Use backup code
                    </button>
                </form>

                <p class="govuk-body govuk-!-margin-top-4">
                    <strong>Do not have any backup codes?</strong><br>
                    Contact your administrator to reset your two-factor authentication.
                </p>
            </div>
        </details>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <p class="govuk-body">
            <a href="<?= $basePath ?>/login" class="govuk-link">Cancel and return to sign in</a>
        </p>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // TOTP code input - numbers only and auto-submit
    var codeInput = document.getElementById('totp-code');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
            if (this.value.length === 6) {
                document.getElementById('totp-form').submit();
            }
        });
    }

    // Backup code input - uppercase and format
    var backupInput = document.getElementById('backup-code');
    if (backupInput) {
        backupInput.addEventListener('input', function() {
            var value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (value.length > 4) {
                value = value.substring(0, 4) + '-' + value.substring(4, 8);
            }
            this.value = value;
        });
    }

    // Focus error summary if present
    var errorSummary = document.querySelector('.govuk-error-summary');
    if (errorSummary) {
        errorSummary.focus();
    }
});
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
