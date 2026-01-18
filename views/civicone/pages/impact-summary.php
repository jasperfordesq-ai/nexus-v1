<?php
// CivicOne View: Impact Summary
$pageTitle = 'Impact Summary';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 40px;">
        <h1 style="margin-bottom: 15px; font-size: 2.5rem; color: var(--skin-primary); line-height: 1.2;">For Every €1 Invested, We Generate €16 in Social Value.</h1>
        <div style="width: 80px; height: 4px; background: var(--skin-primary); margin: 0 auto 20px;"></div>
        <p style="font-size: 1.3rem; max-width: 800px; margin: 0 auto; color: #555; line-height: 1.6;">
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
            <p style="color: #555; font-size: 1.1rem; line-height: 1.7; margin-bottom: 20px;">
                The study found our model is a highly efficient, effective, and scalable intervention for tackling social isolation.
            </p>
            <p style="color: #333; font-size: 1.1rem; line-height: 1.7; font-weight: bold; border-left: 4px solid var(--skin-primary); padding-left: 15px;">
                It explicitly concluded that Timebank Ireland "could become part of a social prescribing offering".
            </p>
        </div>

    </div>

    <!-- Documents Section -->
    <div class="civic-card" style="text-align: center; padding: 40px; margin-bottom: 40px;">
        <h2 style="margin-top: 0; margin-bottom: 30px; color: #333;">Our Strategic Documents</h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
            <div style="padding: 20px; background: #f9f9f9; border-radius: 8px;">
                <h3 style="color: var(--skin-primary); margin-top: 0;">2023 Impact Study</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); margin-bottom: 20px;">Full independent validation of our SROI model and outcomes.</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="civic-btn">Read Full Report</a>
            </div>

            <div style="padding: 20px; background: #f9f9f9; border-radius: 8px;">
                <h3 style="color: var(--skin-primary); margin-top: 0;">Strategic Plan 2030</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); margin-bottom: 20px;">Our roadmap for national scaling and sustainable growth.</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/strategic-plan" class="civic-btn" style="background: #555;">Read Strategic Plan</a>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="civic-card" style="text-align: center; padding: 50px; background: var(--skin-primary); color: white;">
        <h2 style="margin-top: 0; margin-bottom: 20px; color: white;">Ready to Scale Our Impact?</h2>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/contact" class="civic-btn" style="background: white; color: var(--skin-primary); font-weight: bold; border: none;">Contact Strategy Team</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>