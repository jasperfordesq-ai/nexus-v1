<?php
// CivicOne View: Impact Summary
// Tenant-specific: Hour Timebank only
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Impact Summary';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-impact-summary.css">

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card impact-summary-hero">
        <h1>For Every €1 Invested, We Generate €16 in Social Value.</h1>
        <div class="impact-summary-divider"></div>
        <p>Independently validated by our 2023 Social Impact Study.</p>
    </div>

    <!-- Main Content Grid -->
    <div class="impact-summary-grid">

        <!-- Wellbeing Section -->
        <div class="civic-card impact-summary-card">
            <h2>Profound Impact on Wellbeing</h2>
            <ul class="impact-summary-list">
                <li>
                    <span class="icon" aria-hidden="true">✔</span>
                    <strong>100%</strong> of members reported improved mental and emotional wellbeing.
                </li>
                <li>
                    <span class="icon" aria-hidden="true">✔</span>
                    <strong>95%</strong> feel more socially connected, actively tackling loneliness.
                </li>
                <li>
                    <span class="icon" aria-hidden="true">✔</span>
                    Members describe TBI as "transformational and lifesaving".
                </li>
            </ul>
        </div>

        <!-- Public Health Section -->
        <div class="civic-card impact-summary-card">
            <h2>A Public Health Solution</h2>
            <p class="impact-summary-text">
                The study found our model is a highly efficient, effective, and scalable intervention for tackling social isolation.
            </p>
            <p class="impact-summary-quote">
                It explicitly concluded that Timebank Ireland "could become part of a social prescribing offering".
            </p>
        </div>

    </div>

    <!-- Documents Section -->
    <div class="civic-card impact-summary-documents">
        <h2>Our Strategic Documents</h2>

        <div class="impact-summary-documents-grid">
            <div class="impact-summary-doc-card">
                <h3>2023 Impact Study</h3>
                <p>Full independent validation of our SROI model and outcomes.</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="civic-btn">Read Full Report</a>
            </div>

            <div class="impact-summary-doc-card">
                <h3>Strategic Plan 2030</h3>
                <p>Our roadmap for national scaling and sustainable growth.</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/strategic-plan" class="civic-btn impact-summary-doc-btn-secondary">Read Strategic Plan</a>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="civic-card impact-summary-cta">
        <h2>Ready to Scale Our Impact?</h2>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/contact" class="civic-btn impact-summary-cta-btn">Contact Strategy Team</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>