<?php
$pageTitle = "Public Sector Demo - Ireland";
$hSubtitle = "Modernising Community Engagement for Local Government & HSE";
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-demo-pages.css">

<main class="demo-page">

    <!-- Hero Section -->
    <div class="demo-intro-box">
        <h1 class="demo-intro-box__title">Modernising Community Engagement</h1>
        <p class="demo-intro-box__lead">
            <strong>Empowering Irish Communities Through Digital Infrastructure.</strong>
        </p>
        <p class="demo-intro-box__text">
            Project NEXUS provides the digital backbone for local government and healthcare bodies to bridge the gap between policy and grassroots action. By digitizing community exchange, we enable efficient resource allocation and measurable social impact.
        </p>
    </div>

    <!-- Core Pillars -->
    <section class="demo-pillars" aria-label="Core pillars">

        <!-- Social Prescribing -->
        <article class="demo-pillar demo-pillar--green">
            <h2 class="demo-pillar__title demo-pillar__title--green">Social Prescribing at Scale</h2>
            <p class="demo-pillar__text">Giving GPs and health workers a direct portal to refer patients to local volunteer groups. Reduce isolation and improve mental health outcomes through verified community integration.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/hse-case-study" class="demo-cta__btn demo-cta__btn--green">View HSE Integration</a>
        </article>

        <!-- Community Wealth -->
        <article class="demo-pillar demo-pillar--navy">
            <h2 class="demo-pillar__title demo-pillar__title--navy">Community Wealth Building</h2>
            <p class="demo-pillar__text">Keeping local skills and resources within the county via a secure time-credit system. Empower citizens to exchange services without financial barriers, strengthening the local micro-economy.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/council-case-study" class="demo-cta__btn demo-cta__btn--navy">View Council Management</a>
        </article>

        <!-- Resilient Towns -->
        <article class="demo-pillar demo-pillar--amber">
            <h2 class="demo-pillar__title demo-pillar__title--amber">Resilient Towns</h2>
            <p class="demo-pillar__text">Providing every town in your jurisdiction with their own branded hub while maintaining central oversight. Rapidly deploy local support networks during crises or for specific initiatives.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/technical-specs" class="demo-cta__btn demo-cta__btn--amber">Review Architecture</a>
        </article>

    </section>

    <!-- CTA -->
    <div class="demo-footer-cta">
        <h3 class="demo-footer-cta__title">Ready to Pilot?</h3>
        <p class="demo-footer-cta__text">This platform is fully compliant with S.I. No. 358/2020.</p>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compliance" class="demo-footer-cta__link">Read our Compliance Statement</a>
    </div>

</main>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>