<?php
/**
 * CivicOne Auth Forgot Password - Password Reset Request
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
$pageTitle = 'Reset your password';

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
                Reset password
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <?php if (isset($_GET['success'])): ?>
                <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">
                            If an account exists with that email, you will receive a password reset link shortly.
                        </p>
                        <p class="govuk-body">Check your email inbox and spam folder.</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
                    <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
                    <div class="govuk-error-summary__body">
                        <ul class="govuk-list govuk-error-summary__list">
                            <li>
                                <a href="#email"><?= htmlspecialchars($_GET['error'] ?? 'Please check your email address') ?></a>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <h1 class="govuk-heading-xl">Reset your password</h1>

                <p class="govuk-body-l">
                    Enter your email address and we'll send you a link to reset your password.
                </p>

                <form action="<?= $basePath ?>/password/email" method="POST" novalidate>
                    <?= Csrf::input() ?>

                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="email">
                            Email address
                        </label>
                        <div id="email-hint" class="govuk-hint">
                            Enter the email address you used to register
                        </div>
                        <input class="govuk-input govuk-!-width-two-thirds"
                               id="email"
                               name="email"
                               type="email"
                               autocomplete="email"
                               aria-describedby="email-hint"
                               spellcheck="false"
                               required>
                    </div>

                    <button type="submit" class="govuk-button" data-module="govuk-button">
                        Send reset link
                    </button>

                </form>

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                <h2 class="govuk-heading-m">Other options</h2>

                <ul class="govuk-list">
                    <li>
                        <a class="govuk-link" href="<?= $basePath ?>/login">Return to sign in</a>
                    </li>
                    <li>
                        <a class="govuk-link" href="<?= $basePath ?>/register">Create an account</a>
                    </li>
                    <li>
                        <a class="govuk-link" href="<?= $basePath ?>/contact">Contact support</a>
                    </li>
                </ul>

            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
