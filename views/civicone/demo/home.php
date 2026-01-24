<?php
/**
 * CivicOne View: Public Sector Demo Landing
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = "Public Sector Demo - Ireland";
require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Public Sector Demo</li>
    </ol>
</nav>

<!-- Hero Section -->
<div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="govuk-notification-banner-title">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">
            Public Sector Solution
        </h2>
    </div>
    <div class="govuk-notification-banner__content">
        <h1 class="govuk-notification-banner__heading">Modernising Community Engagement</h1>
        <p class="govuk-body-l">
            <strong>Empowering Irish Communities Through Digital Infrastructure.</strong>
        </p>
        <p class="govuk-body">
            Project NEXUS provides the digital backbone for local government and healthcare bodies to bridge the gap between policy and grassroots action. By digitizing community exchange, we enable efficient resource allocation and measurable social impact.
        </p>
    </div>
</div>

<!-- Core Pillars -->
<h2 class="govuk-heading-l govuk-!-margin-bottom-6">
    <i class="fa-solid fa-landmark govuk-!-margin-right-2" aria-hidden="true"></i>
    Core Pillars
</h2>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <!-- Social Prescribing -->
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #00703c; height: 100%; display: flex; flex-direction: column;">
            <h3 class="govuk-heading-m" style="color: #00703c;">
                <i class="fa-solid fa-stethoscope govuk-!-margin-right-2" aria-hidden="true"></i>
                Social Prescribing at Scale
            </h3>
            <p class="govuk-body" style="flex-grow: 1;">
                Giving GPs and health workers a direct portal to refer patients to local volunteer groups. Reduce isolation and improve mental health outcomes through verified community integration.
            </p>
            <a href="<?= $basePath ?>/hse-case-study" class="govuk-button" data-module="govuk-button" style="background: #00703c;">
                View HSE Integration
            </a>
        </div>
    </div>

    <!-- Community Wealth -->
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8; height: 100%; display: flex; flex-direction: column;">
            <h3 class="govuk-heading-m" style="color: #1d70b8;">
                <i class="fa-solid fa-coins govuk-!-margin-right-2" aria-hidden="true"></i>
                Community Wealth Building
            </h3>
            <p class="govuk-body" style="flex-grow: 1;">
                Keeping local skills and resources within the county via a secure time-credit system. Empower citizens to exchange services without financial barriers, strengthening the local micro-economy.
            </p>
            <a href="<?= $basePath ?>/council-case-study" class="govuk-button" data-module="govuk-button">
                View Council Management
            </a>
        </div>
    </div>

    <!-- Resilient Towns -->
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #f47738; height: 100%; display: flex; flex-direction: column;">
            <h3 class="govuk-heading-m" style="color: #f47738;">
                <i class="fa-solid fa-city govuk-!-margin-right-2" aria-hidden="true"></i>
                Resilient Towns
            </h3>
            <p class="govuk-body" style="flex-grow: 1;">
                Providing every town in your jurisdiction with their own branded hub while maintaining central oversight. Rapidly deploy local support networks during crises or for specific initiatives.
            </p>
            <a href="<?= $basePath ?>/technical-specs" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                Review Architecture
            </a>
        </div>
    </div>
</div>

<!-- CTA -->
<div class="govuk-inset-text govuk-!-margin-bottom-6">
    <h3 class="govuk-heading-m">
        <i class="fa-solid fa-rocket govuk-!-margin-right-2" aria-hidden="true"></i>
        Ready to Pilot?
    </h3>
    <p class="govuk-body">This platform is fully compliant with S.I. No. 358/2020.</p>
    <a href="<?= $basePath ?>/compliance" class="govuk-link">Read our Compliance Statement</a>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
