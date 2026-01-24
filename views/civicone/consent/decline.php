<?php
/**
 * CivicOne Consent Decline Page
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Unable to Continue';
$hero_title = 'Account Access';
$hero_subtitle = 'Action required';

require dirname(__DIR__) . '/../layouts/civicone/header.php';

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
$tenant = \Nexus\Core\TenantContext::get();
$tenantName = $tenant['name'] ?? 'the platform';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/consent/required">Accept Terms</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Unable to Continue</li>
    </ol>
</nav>

<!-- Error Banner -->
<div class="govuk-error-summary" role="alert" aria-labelledby="error-summary-title" data-module="govuk-error-summary">
    <h2 class="govuk-error-summary__title" id="error-summary-title">
        <i class="fa-solid fa-circle-exclamation govuk-!-margin-right-2" aria-hidden="true"></i>
        Unable to Continue
    </h2>
    <div class="govuk-error-summary__body">
        <p>To use <?= htmlspecialchars($tenantName) ?>, you must accept our updated Terms of Service and Privacy Policy.</p>
    </div>
</div>

<h1 class="govuk-heading-xl">Your Options</h1>

<!-- Option 1: Accept -->
<div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #00703c;">
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-quarter govuk-!-text-align-centre">
            <i class="fa-solid fa-check-circle" style="font-size: 3rem; color: #00703c;" aria-hidden="true"></i>
        </div>
        <div class="govuk-grid-column-three-quarters">
            <h2 class="govuk-heading-m">Accept the Updated Terms</h2>
            <p class="govuk-body govuk-!-margin-bottom-4">Return to the consent page and review the updated terms. This will allow you to continue using your account.</p>
            <a href="<?= $basePath ?>/consent/required" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i> Return to Consent Page
            </a>
            <span class="govuk-tag govuk-tag--green govuk-!-margin-left-2">Recommended</span>
        </div>
    </div>
</div>

<!-- Option 2: Contact Support -->
<div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-quarter govuk-!-text-align-centre">
            <i class="fa-solid fa-comments" style="font-size: 3rem; color: #1d70b8;" aria-hidden="true"></i>
        </div>
        <div class="govuk-grid-column-three-quarters">
            <h2 class="govuk-heading-m">Contact Support</h2>
            <p class="govuk-body govuk-!-margin-bottom-4">If you have questions about the updated terms or need clarification before accepting, our support team can help.</p>
            <a href="<?= $basePath ?>/contact" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-envelope govuk-!-margin-right-1" aria-hidden="true"></i> Contact Support
            </a>
        </div>
    </div>
</div>

<!-- Option 3: Delete Account -->
<div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #d4351c;">
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-quarter govuk-!-text-align-centre">
            <i class="fa-solid fa-user-xmark" style="font-size: 3rem; color: #d4351c;" aria-hidden="true"></i>
        </div>
        <div class="govuk-grid-column-three-quarters">
            <h2 class="govuk-heading-m">Request Account Deletion</h2>
            <p class="govuk-body govuk-!-margin-bottom-4">If you no longer wish to use our services, you can request deletion of your account and personal data under GDPR.</p>
            <a href="<?= $basePath ?>/settings" class="govuk-button govuk-button--warning" data-module="govuk-button">
                <i class="fa-solid fa-trash-alt govuk-!-margin-right-1" aria-hidden="true"></i> Go to Account Settings
            </a>
        </div>
    </div>
</div>

<!-- Info Section -->
<div class="govuk-inset-text">
    <h3 class="govuk-heading-s">
        <i class="fa-solid fa-info-circle govuk-!-margin-right-1" aria-hidden="true"></i>
        Why do I need to accept?
    </h3>
    <p class="govuk-body govuk-!-margin-bottom-2">These legal documents explain your rights and responsibilities when using our platform, and how we handle your personal data. Accepting them is required to comply with data protection laws.</p>
    <p class="govuk-body govuk-!-margin-bottom-0">Your account will remain in a restricted state until you accept the updated terms. You won't lose any data.</p>
</div>

<?php require dirname(__DIR__) . '/../layouts/civicone/footer.php'; ?>
