<?php
/**
 * Council Management Case Study
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = "Council Management Case Study";
$basePath = \Nexus\Core\TenantContext::getBasePath();
require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Case Studies', 'href' => $basePath . '/demo'],
        ['text' => 'Local Government']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

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
        <div class="govuk-!-padding-4 civicone-accent-card--red civicone-flex-card">
            <h2 class="govuk-heading-l civicone-heading-red">The Challenge</h2>
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
        <div class="govuk-!-padding-4 civicone-panel-bg civicone-flex-card">
            <img src="https://placehold.co/600x300/002d72/FFF?text=Multi-Hub+Architecture" alt="Diagram showing Hub and Spoke model" class="civicone-responsive-image">
            <p class="govuk-body-s govuk-!-margin-top-2 govuk-!-margin-bottom-0 civicone-secondary-text">Fig 1. The Hub & Spoke Tenant Model</p>
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
            <div class="govuk-!-padding-4 civicone-top-accent--blue civicone-flex-card">
                <h3 class="govuk-heading-m civicone-heading-blue">Local Skins</h3>
                <p class="govuk-body">Each town maintains its unique identity, logo, and "Welcome" message. Residents feel they are joining <em>their</em> town's hub.</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 civicone-top-accent--green civicone-flex-card">
                <h3 class="govuk-heading-m civicone-heading-green">Central Data</h3>
                <p class="govuk-body">The Council centralises data for grant reporting. "How many hours of volunteering happened in West Cork?" is answered in one click.</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
            <div class="govuk-!-padding-4 civicone-top-accent--pink civicone-flex-card">
                <h3 class="govuk-heading-m civicone-heading-pink">Strategic Planning</h3>
                <p class="govuk-body">Identify capability gaps. If Hub A has excess gardening tools and Hub B has none, facilitate the transfer.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<div class="govuk-!-padding-6 govuk-!-text-align-center civicone-hero-blue">
    <h2 class="govuk-heading-l">Learn More</h2>
    <a href="<?= $basePath ?>/technical-specs" class="govuk-button govuk-button--secondary" data-module="govuk-button">
        Review Technical Architecture
    </a>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
