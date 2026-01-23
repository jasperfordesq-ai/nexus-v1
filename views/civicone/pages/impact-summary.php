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

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card civic-mb-8 civic-text-center civic-p-8">
        <h1 style="margin-bottom: 15px; font-size: 2.5rem; color: var(--skin-primary); line-height: 1.2;">For Every €1 Invested, We Generate €16 in Social Value.</h1>
        <div style="width: 80px; height: 4px; background: var(--skin-primary); margin: 0 auto 20px;"></div>
        <p class="civic-text-2xl civic-max-w-lg civic-mx-auto civic-text-medium civic-leading-relaxed">
            Independently validated by our 2023 Social Impact Study.
        </p>
    </div>

    <!-- Main Content Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px;">

        <!-- Wellbeing Section -->
        <div class="civic-card">
            <h2 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 20px;">Profound Impact on Wellbeing</h2>
            <ul style="list-style: none; padding: 0; font-size: 1.1rem; line-height: 1.6;">
                <li style="margin-bottom: 20px; position: relative; padding-left: 35px;">
                    <span style="position: absolute; left: 0; color: var(--skin-primary); font-weight: bold; font-size: 1.2rem;">✔</span>
                    <strong>100%</strong> of members reported improved mental and emotional wellbeing.
                </li>
                <li style="margin-bottom: 20px; position: relative; padding-left: 35px;">
                    <span style="position: absolute; left: 0; color: var(--skin-primary); font-weight: bold; font-size: 1.2rem;">✔</span>
                    <strong>95%</strong> feel more socially connected, actively tackling loneliness.
                </li>
                <li style="margin-bottom: 20px; position: relative; padding-left: 35px;">
                    <span style="position: absolute; left: 0; color: var(--skin-primary); font-weight: bold; font-size: 1.2rem;">✔</span>
                    Members describe TBI as "transformational and lifesaving".
                </li>
            </ul>
        </div>

        <!-- Public Health Section -->
        <div class="civic-card">
            <h2 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 20px;">A Public Health Solution</h2>
            <p class="civic-text-medium civic-text-lg civic-mb-4" style="line-height: 1.7;">
                The study found our model is a highly efficient, effective, and scalable intervention for tackling social isolation.
            </p>
            <p class="civic-text-dark civic-text-lg civic-font-bold" style="line-height: 1.7; border-left: 4px solid var(--skin-primary); padding-left: 15px;">
                It explicitly concluded that Timebank Ireland "could become part of a social prescribing offering".
            </p>
        </div>

    </div>

    <!-- Documents Section -->
    <div class="civic-card civic-text-center civic-p-8 civic-mb-8">
        <h2 class="civic-mt-0 civic-mb-6 civic-text-dark">Our Strategic Documents</h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
            <div class="civic-p-4 civic-bg-gray-50 civic-rounded">
                <h3 style="color: var(--skin-primary); margin-top: 0;">2023 Impact Study</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); margin-bottom: 20px;">Full independent validation of our SROI model and outcomes.</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="civic-btn">Read Full Report</a>
            </div>

            <div class="civic-p-4 civic-bg-gray-50 civic-rounded">
                <h3 style="color: var(--skin-primary); margin-top: 0;">Strategic Plan 2030</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); margin-bottom: 20px;">Our roadmap for national scaling and sustainable growth.</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/strategic-plan" class="civic-btn civic-text-medium" style="background: var(--color-gray-600, #555);">Read Strategic Plan</a>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="civic-card civic-text-center civic-p-10" style="background: var(--skin-primary); color: white;">
        <h2 class="civic-mt-0 civic-mb-4 civic-text-white">Ready to Scale Our Impact?</h2>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/contact" class="civic-btn civic-bg-white civic-font-bold" style="color: var(--skin-primary); border: none;">Contact Strategy Team</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>