<?php
/**
 * Council Management Case Study
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = "Council Management Case Study";
$basePath = \Nexus\Core\TenantContext::getBasePath();
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/demo">Case Studies</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Local Government</li>
    </ol>
</nav>

<!-- Header Section -->
<div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="council-title">
    <div class="govuk-notification-banner__header">
        <h2 class="govuk-notification-banner__title" id="council-title">Case Study: Local Government</h2>
    </div>
    <div class="govuk-notification-banner__content govuk-!-text-align-center">
        <h1 class="govuk-notification-banner__heading">One County, Ten Hubs, One Dashboard</h1>
        <p class="govuk-body-l">Centralised oversight with localised identity for Bantry, Ennis, and Skibbereen.</p>
    </div>
</div>

<div class="govuk-grid-row govuk-!-margin-bottom-8">
    <!-- Challenge -->
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #d4351c; height: 100%;">
            <h2 class="govuk-heading-l" style="color: #d4351c;">The Challenge</h2>
            <p class="govuk-body">
                County Councils manage diverse towns, each with unique needs and identities. Centralised "one-size-fits-all" platforms often fail to engage local residents who feel disconnected from a county-wide system.
            </p>
            <p class="govuk-body govuk-!-margin-bottom-0">
                However, managing 30 different standalone websites for every town is administratively impossible and creates data silos.
            </p>
        </div>
    </div>

    <!-- Diagram -->
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-4" style="background: #f3f2f1; height: 100%;">
            <img src="https://placehold.co/600x300/002d72/FFF?text=Multi-Hub+Architecture" alt="Diagram showing Hub and Spoke model" style="width: 100%; height: auto;">
            <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-0" style="color: #505a5f;">Fig 1. The Hub & Spoke Tenant Model</p>
        </div>
    </div>
</div>

<!-- Solution Section -->
<section class="govuk-!-margin-bottom-8">
    <h2 class="govuk-heading-l govuk-!-text-align-center govuk-!-margin-bottom-6">The Solution: Multi-Hub Management</h2>
    <p class="govuk-body-l govuk-!-text-align-center govuk-!-margin-bottom-6">
        A County Council manages town-specific exchanges from a single "Master Seat."
    </p>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-top: 5px solid #1d70b8; height: 100%;">
                <h3 class="govuk-heading-m" style="color: #1d70b8;">Local Skins</h3>
                <p class="govuk-body">Each town maintains its unique identity, logo, and "Welcome" message. Residents feel they are joining <em>their</em> town's hub.</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-top: 5px solid #00703c; height: 100%;">
                <h3 class="govuk-heading-m" style="color: #00703c;">Central Data</h3>
                <p class="govuk-body">The Council centralises data for grant reporting. "How many hours of volunteering happened in West Cork?" is answered in one click.</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-top: 5px solid #d53880; height: 100%;">
                <h3 class="govuk-heading-m" style="color: #d53880;">Strategic Planning</h3>
                <p class="govuk-body">Identify capability gaps. If Hub A has excess gardening tools and Hub B has none, facilitate the transfer.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<div class="govuk-!-padding-6 govuk-!-text-align-center" style="background: #1d70b8; color: white;">
    <h2 class="govuk-heading-l" style="color: white;">Learn More</h2>
    <a href="<?= $basePath ?>/technical-specs" class="govuk-button govuk-button--secondary" data-module="govuk-button">
        Review Technical Architecture
    </a>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
