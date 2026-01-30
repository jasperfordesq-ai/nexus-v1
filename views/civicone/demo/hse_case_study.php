<?php
/**
 * HSE Social Prescribing Case Study
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = "HSE Social Prescribing Case Study";
$basePath = \Nexus\Core\TenantContext::getBasePath();
require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Case Studies', 'href' => $basePath . '/demo'],
        ['text' => 'HSE Healthcare']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<!-- Header Section -->
<div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="hse-title">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="hse-title">Case Study: Healthcare</h2>
    </div>
    <div class="govuk-notification-banner__content govuk-!-text-align-center">
        <h1 class="govuk-notification-banner__heading">Reducing Healthcare Pressure via Community Action</h1>
        <p class="govuk-body-l">A Community Healthcare Organisation (CHO) uses NEXUS to manage a network of "Wellness Volunteers."</p>
    </div>
</div>

<div class="govuk-grid-row govuk-!-margin-bottom-8">
    <!-- Scenario -->
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-4 civicone-accent-card--green civicone-flex-card">
            <h2 class="govuk-heading-l civicone-heading-green">The Scenario</h2>
            <p class="govuk-body">
                GPs and Public Health Nurses often see patients whose primary complaints are rooted in isolation or lack of activity, rather than acute medical issues. The "Social Prescribing" model works, but tracking referrals and ensuring patient safety has historically been manual and paper-based.
            </p>
            <p class="govuk-body govuk-!-margin-bottom-0">
                <strong>The Challenge:</strong> Connecting patients to trusted, vetted community groups without adding administrative burden to clinical staff.
            </p>
        </div>
    </div>

    <!-- Solution -->
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-4 civicone-action-card civicone-flex-card">
            <h2 class="govuk-heading-l civicone-heading-blue">The Result</h2>
            <ul class="govuk-list govuk-list--spaced">
                <li>
                    <strong class="govuk-tag govuk-tag--green govuk-!-margin-right-2">Direct Referral</strong>
                    Patients are referred directly to community garden projects or walking groups via the NEXUS portal.
                </li>
                <li>
                    <strong class="govuk-tag govuk-tag--light-blue govuk-!-margin-right-2">Real-Time Data</strong>
                    The HSE can track engagement levels and "hours of support" generated.
                </li>
                <li>
                    <strong class="govuk-tag govuk-tag--purple govuk-!-margin-right-2">ROI Calculator</strong>
                    Clear visualisation of social value versus clinical hours saved.
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- CTA Section -->
<div class="govuk-!-padding-6 govuk-!-text-align-center civicone-hero-green">
    <h2 class="govuk-heading-l">See It In Action</h2>
    <p class="govuk-body-l govuk-!-margin-bottom-4">Experience the user journey for a potential volunteer.</p>
    <a href="<?= $basePath ?>/volunteering" class="govuk-button govuk-button--secondary" data-module="govuk-button">
        View Live Volunteer Opportunities
    </a>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
