<?php
/**
 * CivicOne Auth Reset Password - New Password Form
 * Template D: Form/Flow (Section 10.7)
 * GOV.UK Design System v5.14.0 compliant
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Create a new password';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">

    <!-- Breadcrumbs -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/login">Sign in</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                Create new password
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <?php if (isset($_GET['error'])): ?>
                <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
                    <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li>
                                <a href="#password"><?= htmlspecialchars($_GET['error'] ?? 'Please check your password') ?></a>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['expired'])): ?>
                <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
                    <h2 class="govuk-error-summary__title" id="error-summary-title">Reset link has expired</h2>
                    <div class="govuk-error-summary__body">
                        <p class="govuk-body">Password reset links are valid for 1 hour.</p>
                        <p class="govuk-body">
                            <a href="<?= $basePath ?>/password/forgot" class="govuk-link">Request a new reset link</a>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <h1 class="govuk-heading-xl">Create a new password</h1>

                <p class="govuk-body-l">
                    Your new password must be at least 12 characters and include uppercase, lowercase, numbers and special characters.
                </p>

                <form action="<?= $basePath ?>/password/reset" method="POST" novalidate id="reset-password-form">
                    <?= Csrf::input() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

                    <!-- New Password -->
                    <div class="govuk-form-group" id="password-group">
                        <label class="govuk-label govuk-label--m" for="password">
                            New password
                        </label>
                        <div id="password-hint" class="govuk-hint">
                            Must be at least 12 characters with uppercase, lowercase, numbers and special characters
                        </div>
                        <input class="govuk-input"
                               id="password"
                               name="password"
                               type="password"
                               autocomplete="new-password"
                               aria-describedby="password-hint password-requirements"
                               required>

                        <!-- Password Requirements Checklist -->
                        <div id="password-requirements" class="govuk-inset-text govuk-!-margin-top-3" role="status" aria-live="polite">
                            <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-2">Password requirements:</p>
                            <ul class="govuk-list govuk-body-s">
                                <li id="rule-length" class="civicone-rule-pending">
                                    <span aria-hidden="true">○</span> At least 12 characters
                                </li>
                                <li id="rule-upper" class="civicone-rule-pending">
                                    <span aria-hidden="true">○</span> At least 1 uppercase letter
                                </li>
                                <li id="rule-lower" class="civicone-rule-pending">
                                    <span aria-hidden="true">○</span> At least 1 lowercase letter
                                </li>
                                <li id="rule-number" class="civicone-rule-pending">
                                    <span aria-hidden="true">○</span> At least 1 number
                                </li>
                                <li id="rule-symbol" class="civicone-rule-pending">
                                    <span aria-hidden="true">○</span> At least 1 special character (!@#$%^&*)
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="govuk-form-group" id="confirm-password-group">
                        <label class="govuk-label govuk-label--m" for="confirm_password">
                            Confirm new password
                        </label>
                        <div id="confirm-hint" class="govuk-hint">
                            Re-enter your new password
                        </div>
                        <input class="govuk-input"
                               id="confirm_password"
                               name="confirm_password"
                               type="password"
                               autocomplete="new-password"
                               aria-describedby="confirm-hint password-match-status"
                               required>
                        <p id="password-match-status" class="govuk-body-s govuk-!-margin-top-2" role="status" aria-live="polite"></p>
                    </div>

                    <button type="submit" class="govuk-button" data-module="govuk-button" id="submit-btn" disabled aria-disabled="true">
                        Update password
                    </button>

                </form>

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                <p class="govuk-body">
                    <a class="govuk-link" href="<?= $basePath ?>/login">Return to sign in</a>
                </p>

            </div>
        </div>

    </main>
</div>

<script src="<?= $basePath ?>/assets/js/civicone-auth-reset-password.js" defer></script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
