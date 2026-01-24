<?php
$pageTitle = "Council Management Case Study - Project NEXUS";
$hSubtitle = "Community Wealth Building & Time Credits for Local Govt";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-demo-pages.css">

<main class="demo-page">

    <!-- Council Branding Header -->
    <div class="demo-hero">
        <span class="demo-hero__label">Case Study: Local Government</span>
        <h1 class="demo-hero__title">One County, Ten Hubs, One Dashboard</h1>
        <p class="demo-hero__desc">Centralised oversight with localised identity for Bantry, Ennis, and Skibbereen.</p>
    </div>

    <div class="demo-two-col">

        <div>
            <h2 class="demo-section-heading demo-section-heading--green">The Challenge</h2>
            <p class="demo-text-body">
                County Councils manage diverse towns, each with unique needs and identities. Centralised "one-size-fits-all" platforms often fail to engage local residents who feel disconnected from a county-wide system.
            </p>
            <p class="demo-text-body">
                However, managing 30 different standalone websites for every town is administratively impossible and creates data silos.
            </p>
        </div>

        <div>
            <img src="https://placehold.co/600x300/002d72/FFF?text=Multi-Hub+Architecture" alt="Diagram showing Hub and Spoke model" class="demo-feature-image">
            <p class="demo-image-caption">Fig 1. The Hub & Spoke Tenant Model</p>
        </div>

    </div>

    <section class="demo-section">
        <h3 class="demo-section-heading">The Solution: Multi-Hub Management</h3>
        <p class="demo-text-lead">
            A County Council manages town-specific exchanges from a single "Master Seat."
        </p>

        <div class="demo-feature-grid">
            <article class="demo-feature-card">
                <strong>Local Skins</strong>
                <p>Each town maintains its unique identity, logo, and "Welcome" message. Residents feel they are joining <em>their</em> town's hub.</p>
            </article>
            <article class="demo-feature-card">
                <strong>Central Data</strong>
                <p>The Council centralises data for grant reporting. "How many hours of volunteering happened in West Cork?" is answered in one click.</p>
            </article>
            <article class="demo-feature-card">
                <strong>Strategic Planning</strong>
                <p>Identify capability gaps. If Hub A has excess gardening tools and Hub B has none, facilitate the transfer.</p>
            </article>
        </div>
    </section>

    <!-- Call to Action -->
    <div class="demo-cta">
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/technical-specs" class="demo-cta__btn demo-cta__btn--navy">Review Technical Architecture</a>
    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>