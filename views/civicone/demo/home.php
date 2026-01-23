<?php
$pageTitle = "Public Sector Demo - Ireland";
$hSubtitle = "Modernising Community Engagement for Local Government & HSE";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<main class="civic-container" style="padding: 40px 20px;">

    <!-- Hero Section -->
    <div style="background: #f0f9ff; border-radius: 12px; padding: 40px; margin-bottom: 40px; border-left: 6px solid #002d72;">
        <h1 style="color: #002d72; font-size: 2.5rem; margin-bottom: 20px;">Modernising Community Engagement</h1>
        <p style="font-size: 1.2rem; color: #334155; line-height: 1.6; max-width: 800px;">
            **Empowering Irish Communities Through Digital Infrastructure.**
        </p>
        <p style="font-size: 1.1rem; color: #475569; line-height: 1.6; max-width: 800px; margin-top: 20px;">
            Project NEXUS provides the digital backbone for local government and healthcare bodies to bridge the gap between policy and grassroots action. By digitizing community exchange, we enable efficient resource allocation and measurable social impact.
        </p>
    </div>

    <!-- Core Pillars -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 50px;">

        <!-- Social Prescribing -->
        <div class="civic-card" style="padding: 30px; border-top: 4px solid #007b5f;">
            <h2 style="color: #007b5f; margin-top: 0;">Social Prescribing at Scale</h2>
            <p>Giving GPs and health workers a direct portal to refer patients to local volunteer groups. Reduce isolation and improve mental health outcomes through verified community integration.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/hse-case-study" class="civic-btn" style="background-color: #007b5f; color: white; display: inline-block; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 15px;">View HSE Integration</a>
        </div>

        <!-- Community Wealth -->
        <div class="civic-card" style="padding: 30px; border-top: 4px solid #002d72;">
            <h2 style="color: #002d72; margin-top: 0;">Community Wealth Building</h2>
            <p>Keeping local skills and resources within the county via a secure time-credit system. Empower citizens to exchange services without financial barriers, strengthening the local micro-economy.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/council-case-study" class="civic-btn" style="background-color: #002d72; color: white; display: inline-block; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 15px;">View Council Management</a>
        </div>

        <!-- Resilient Towns -->
        <div class="civic-card" style="padding: 30px; border-top: 4px solid #d97706;">
            <h2 style="color: #d97706; margin-top: 0;">Resilient Towns</h2>
            <p>Providing every town in your jurisdiction with their own branded hub while maintaining central oversight. Rapidly deploy local support networks during crises or for specific initiatives.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/technical-specs" class="civic-btn" style="background-color: #b45309; color: white; display: inline-block; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 15px;">Review Architecture</a>
        </div>

    </div>

    <!-- CTA -->
    <div style="text-align: center; margin-top: 60px; padding: 40px; background: #f8fafc; border-radius: 12px;">
        <h3 style="color: #1e293b;">Ready to Pilot?</h3>
        <p style="margin-bottom: 20px; color: #64748b;">This platform is fully compliant with S.I. No. 358/2020.</p>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compliance" style="text-decoration: underline; color: #002d72; font-weight: 600;">Read our Compliance Statement</a>
    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>