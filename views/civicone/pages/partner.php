<?php
// CivicOne View: Partner With Us
// Tenant-specific: Hour Timebank only
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Partner With Us';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 40px;">
        <h1 style="margin-top: 0; margin-bottom: 15px; color: var(--skin-primary);">A 1:16 Return on Social Investment</h1>
        <p style="font-size: 1.2rem; color: #555; max-width: 800px; margin: 0 auto; line-height: 1.6;">
            Seeking partners to secure core operations and execute our 2026-2030 Strategic Plan.
        </p>
    </div>

    <!-- Funding Gap Section -->
    <div class="civic-card" style="padding: 50px; margin-bottom: 50px;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center;">
            <div>
                <h2 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 20px;">Addressing the Funding Gap</h2>
                <p style="font-size: 1.1rem; line-height: 1.7; margin-bottom: 20px; color: #555;">
                    The closure of our primary social enterprise income stream has created a funding gap. Our <strong>most urgent priority</strong> is funding the central <strong>Hub Coordinator (Broker)</strong> role for our West Cork Centre of Excellence.
                </p>

                <ul style="list-style: none; padding: 0; font-size: 1.05rem; color: #555;">
                    <li style="margin-bottom: 15px; position: relative; padding-left: 30px;">
                        <span style="position: absolute; left: 0; color: var(--skin-primary); font-weight: bold;">✔</span>
                        The Coordinator was identified as the "key enabler for expansion" and positive outcomes.
                    </li>
                    <li style="margin-bottom: 15px; position: relative; padding-left: 30px;">
                        <span style="position: absolute; left: 0; color: var(--skin-primary); font-weight: bold;">✔</span>
                        This investment transitions us from 100% grant reliance to a diversified, sustainable model.
                    </li>
                    <li style="margin-bottom: 15px; position: relative; padding-left: 30px;">
                        <span style="position: absolute; left: 0; color: var(--skin-primary); font-weight: bold;">✔</span>
                        Your funding of this role is the foundational first step to unlocking our entire national growth plan.
                    </li>
                </ul>
            </div>
            <div>
                <img src="/uploads/tenants/hour-timebank/SRI.jpg" alt="Social Return on Investment" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            </div>
        </div>
    </div>

    <!-- Impact Section -->
    <div style="margin-bottom: 50px;">
        <h2 style="text-align: center; margin-bottom: 40px; color: #333;">Deliver Measurable Social Impact</h2>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
            <div class="civic-card" style="padding: 30px;">
                <h3 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 15px;">Exceptional Social Value</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">Align your brand with a model proven to return <strong>€16 in social value for every €1 invested</strong>. We tackle social isolation, a critical public health issue in Ireland.</p>
            </div>
            <div class="civic-card" style="padding: 30px;">
                <h3 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 15px;">Proof and Transparency</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">Our impact is validated by an independent <strong>2023 Social Impact Study</strong>. We provide clear, data-driven reporting that showcases your commitment to CSR.</p>
            </div>
            <div class="civic-card" style="padding: 30px;">
                <h3 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 15px;">Strategic Growth</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">Invest in a resilient organisation that has a clear <strong>5-year roadmap</strong> to scale from a single region to a national network of over 2,500 active members.</p>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="civic-card" style="text-align: center; padding: 60px; margin-bottom: 50px;">
        <h2 style="color: #333; margin-top: 0; margin-bottom: 20px;">Let's Discuss Your Pathfinder Investment</h2>
        <p style="font-size: 1.2rem; color: var(--civic-text-secondary, #4B5563); margin-bottom: 30px;">Join us in building a more connected, resilient Ireland.</p>

        <div style="display: flex; gap: 20px; justify-content: center;">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/contact" class="civic-btn">Contact Strategy Team</a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/strategic-plan" class="civic-btn" style="background: #fff; color: #555; border: 1px solid #ccc;">View Strategic Plan</a>
        </div>
    </div>

    <!-- Partners Logos -->
    <div class="civic-card" style="padding: 40px; text-align: center; background: #f9fafb;">
        <h3 style="margin-top: 0; margin-bottom: 30px; color: var(--civic-text-secondary, #4B5563);">Our Partners & Supporters</h3>
        <div style="display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; align-items: center;">
            <img src="/uploads/tenants/hour-timebank/timebank_ireland_west_cork_partnership.webp" alt="West Cork Partnership" style="height: 100px; width: auto;">
            <img src="/uploads/tenants/hour-timebank/rethink_ireland_awardee.webp" alt="Rethink Ireland" style="height: 100px; width: auto;">
            <img src="/uploads/tenants/hour-timebank/Bantry-TT-logo-transparent.webp" alt="Tidy Towns" style="height: 100px; width: auto;">
        </div>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>