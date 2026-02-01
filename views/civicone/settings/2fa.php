<?php
/**
 * CivicOne View: 2FA Settings
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 * Manage two-factor authentication settings
 * Note: 2FA is mandatory and cannot be disabled
 */
$pageTitle = 'Two-factor authentication settings';
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <a href="<?= $basePath ?>/settings" class="govuk-back-link">Back to settings</a>

        <h1 class="govuk-heading-xl">Two-factor authentication</h1>

        <?php if (!empty($flash_success)): ?>
            <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">
                        <?= htmlspecialchars($flash_success) ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
            <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
                <h2 class="govuk-error-summary__title" id="error-summary-title">
                    There is a problem
                </h2>
                <div class="govuk-error-summary__body">
                    <p><?= htmlspecialchars($flash_error) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_enabled): ?>
            <div class="govuk-panel govuk-panel--confirmation govuk-!-margin-bottom-6">
                <h2 class="govuk-panel__title govuk-!-font-size-27">
                    2FA is enabled
                </h2>
                <div class="govuk-panel__body">
                    Your account is protected with two-factor authentication
                </div>
            </div>

            <h2 class="govuk-heading-m">Backup codes</h2>

            <p class="govuk-body">
                You have <strong><?= $backup_codes_remaining ?></strong> backup codes remaining.
            </p>

            <?php if ($backup_codes_remaining <= 3): ?>
                <div class="govuk-warning-text">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">Warning</span>
                        You are running low on backup codes. Consider generating new ones.
                    </strong>
                </div>
            <?php endif; ?>

            <p class="govuk-body govuk-!-margin-bottom-6">
                <a href="<?= $basePath ?>/auth/2fa/backup-codes" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    View or regenerate backup codes
                </a>
            </p>

            <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

            <div class="govuk-inset-text">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">2FA is mandatory</h3>
                <p class="govuk-body govuk-!-margin-bottom-0">
                    Two-factor authentication is required for all accounts and cannot be disabled.
                    This helps protect your account and the security of the platform.
                </p>
            </div>

        <?php else: ?>
            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">Warning</span>
                    2FA setup required. You must set up two-factor authentication to continue using the service.
                </strong>
            </div>

            <p class="govuk-body">
                Two-factor authentication adds an extra layer of security to your account.
                Even if someone obtains your password, they cannot access your account without your phone.
            </p>

            <a href="<?= $basePath ?>/auth/2fa/setup" class="govuk-button" data-module="govuk-button">
                Set up two-factor authentication
            </a>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
