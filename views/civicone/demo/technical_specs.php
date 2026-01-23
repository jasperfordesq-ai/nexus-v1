<?php
$pageTitle = "Technical Specifications - Project NEXUS";
$hSubtitle = "Security Standards, GDPR Compliance & Cloud Architecture";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-demo-pages.css">

<main class="demo-page demo-page--narrow">

    <header class="demo-header">
        <h1 class="demo-header__title">Technical Proposal</h1>
        <p class="demo-header__subtitle">Project NEXUS: Public Sector Edition</p>
    </header>

    <div class="demo-content-card">

        <!-- Platform Summary -->
        <h2 class="demo-section-heading demo-section-heading--first">1. Executive Summary</h2>
        <table class="demo-spec-table" aria-label="Platform specifications">
            <tr>
                <th scope="row">Platform</th>
                <td>NEXUS (Custom PHP MVC)</td>
            </tr>
            <tr>
                <th scope="row">Database</th>
                <td>MySQL (Spatial Extensions Enabled)</td>
            </tr>
            <tr>
                <th scope="row">Hosting</th>
                <td>Ireland-based / Data Sovereign</td>
            </tr>
            <tr>
                <th scope="row">Performance</th>
                <td>&lt; 300ms Page Load (Zero-Bloat Arch)</td>
            </tr>
        </table>

        <!-- Security -->
        <h2 class="demo-section-heading">2. Security & Compliance</h2>
        <ul class="demo-feature-list">
            <li class="demo-feature-list__item"><strong>SQL Injection Proof:</strong> All database interactions utilize PDO Prepared Statements.</li>
            <li class="demo-feature-list__item"><strong>Strict Multi-Tenancy:</strong> Physical <code>tenant_id</code> scoping at the Database Wrapper level prevents cross-contamination.</li>
            <li class="demo-feature-list__item"><strong>Audit Logging:</strong> Granular tracking of all User/Admin actions.</li>
            <li class="demo-feature-list__item"><strong>Accessibility:</strong> WCAG 2.1 Level AA Compliant (CivicOne Layout).</li>
        </ul>

        <!-- Integration -->
        <h2 class="demo-section-heading">3. Integration Capabilities</h2>
        <p class="demo-text-body">
            The NEXUS platform is API-First. We expose secure RESTful endpoints for integration with existing Council/HSE data portals, CRM systems (Salesforce/Microsoft Dynamics), and volunteer registries.
        </p>

    </div>

    <div class="demo-cta">
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/" class="demo-cta__btn demo-cta__btn--navy">Back to Demo Home</a>
    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>