<?php
$pageTitle = "Compliance Statement - Project NEXUS";
$hSubtitle = "WCAG 2.1 AA & GDPR Compliance Documentation";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-demo-pages.css">

<main class="demo-page">

    <header class="demo-header demo-header--bordered">
        <span class="demo-badge demo-badge--blue">Security & Standards</span>
        <h1 class="demo-header__title">Government-Grade Infrastructure</h1>
        <p class="demo-header__subtitle">Security That Meets National Standards.</p>
    </header>

    <div class="demo-sidebar-layout">

        <div class="demo-sidebar-layout__main">
            <p class="demo-text-body demo-text-body--intro">
                Designed with the Irish public sector in mind, our architecture ensures absolute data sovereignty and compliance. We adhere to rigorous standards to ensure trust and reliability for Local Authorities and the HSE.
            </p>

            <ul class="demo-checklist" role="list">
                <li class="demo-checklist__item">
                    <span class="demo-checklist__icon" aria-hidden="true">✓</span>
                    <div class="demo-checklist__content">
                        <h3>GDPR & Multi-Tenancy</h3>
                        <p>Using strict physical <code>tenant_id</code> isolation to ensure no data leakage between different local authorities. Data is encrypted at rest and in transit.</p>
                    </div>
                </li>
                <li class="demo-checklist__item">
                    <span class="demo-checklist__icon" aria-hidden="true">✓</span>
                    <div class="demo-checklist__content">
                        <h3>S.I. No. 358/2020 Compliance</h3>
                        <p>The CivicOne interface is audited for WCAG 2.1 Level AA accessibility, ensuring inclusivity for all citizens regardless of ability.</p>
                    </div>
                </li>
                <li class="demo-checklist__item">
                    <span class="demo-checklist__icon" aria-hidden="true">✓</span>
                    <div class="demo-checklist__content">
                        <h3>Forensic Audit Trails</h3>
                        <p>Every administrative action is logged with Actor ID, IP Address, and Timestamps for full accountability and transparent governance.</p>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Sidebar -->
        <aside class="demo-sidebar">
            <h4 class="demo-sidebar__title">Technical Specs</h4>
            <ul class="demo-sidebar__list" role="list">
                <li><strong>Hosting:</strong> Dublin, Ireland (EU)</li>
                <li><strong>Encryption:</strong> AES-256</li>
                <li><strong>Role Access:</strong> RBAC Level 3</li>
                <li><strong>Sovereignty:</strong> 100% Irish Data Residency</li>
            </ul>

            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/technical-specs" class="demo-sidebar__btn">View Full Proposal</a>
        </aside>

    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>