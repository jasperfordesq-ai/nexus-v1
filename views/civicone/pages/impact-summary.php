<?php
/**
 * CivicOne View: Impact Summary
 * GOV.UK Design System (WCAG 2.1 AA)
 * Tenant-specific: Hour Timebank only
 */
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Impact Summary';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Impact Summary</li>
    </ol>
</nav>

<!-- Header Section -->
<div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="impact-title">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="impact-title">Proven Social Impact</h2>
    </div>
    <div class="govuk-notification-banner__content govuk-!-text-align-center">
        <h1 class="govuk-notification-banner__heading">For Every €1 Invested, We Generate €16 in Social Value.</h1>
        <p class="govuk-body-l">Independently validated by our 2023 Social Impact Study.</p>
    </div>
</div>

<!-- Main Content Grid -->
<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <!-- Wellbeing Section -->
    <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #00703c; height: 100%;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-heart govuk-!-margin-right-2" style="color: #00703c;" aria-hidden="true"></i>
                Profound Impact on Wellbeing
            </h2>
            <ul class="govuk-list govuk-list--bullet">
                <li>
                    <span class="govuk-tag govuk-tag--green govuk-!-margin-right-1">100%</span>
                    of members reported improved mental and emotional wellbeing.
                </li>
                <li>
                    <span class="govuk-tag govuk-tag--green govuk-!-margin-right-1">95%</span>
                    feel more socially connected, actively tackling loneliness.
                </li>
                <li>Members describe TBI as <strong>"transformational and lifesaving"</strong>.</li>
            </ul>
        </div>
    </div>

    <!-- Public Health Section -->
    <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8; height: 100%;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-stethoscope govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                A Public Health Solution
            </h2>
            <p class="govuk-body">
                The study found our model is a highly efficient, effective, and scalable intervention for tackling social isolation.
            </p>
            <div class="govuk-inset-text" style="border-left-color: #1d70b8;">
                It explicitly concluded that Timebank Ireland <strong>"could become part of a social prescribing offering"</strong>.
            </div>
        </div>
    </div>
</div>

<!-- Documents Section -->
<div class="govuk-!-padding-6 govuk-!-margin-bottom-6 civicone-panel-bg">
    <h2 class="govuk-heading-l govuk-!-text-align-center govuk-!-margin-bottom-6">Our Strategic Documents</h2>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4" style="background: white; height: 100%;">
                <h3 class="govuk-heading-s">2023 Impact Study</h3>
                <p class="govuk-body-s govuk-!-margin-bottom-4">Full independent validation of our SROI model and outcomes.</p>
                <a href="<?= $basePath ?>/impact-report" class="govuk-button" data-module="govuk-button">Read Full Report</a>
            </div>
        </div>

        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4" style="background: white; height: 100%;">
                <h3 class="govuk-heading-s">Strategic Plan 2030</h3>
                <p class="govuk-body-s govuk-!-margin-bottom-4">Our roadmap for national scaling and sustainable growth.</p>
                <a href="<?= $basePath ?>/strategic-plan" class="govuk-button govuk-button--secondary" data-module="govuk-button">Read Strategic Plan</a>
            </div>
        </div>
    </div>
</div>

<!-- CTA Section -->
<div class="govuk-!-padding-6 govuk-!-text-align-center" style="background: #1d70b8; color: white;">
    <h2 class="govuk-heading-l" style="color: white;">Ready to Scale Our Impact?</h2>
    <a href="<?= $basePath ?>/contact" class="govuk-button govuk-button--secondary" data-module="govuk-button">Contact Strategy Team</a>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>