<?php
// CivicOne View: Timebanking Guide
// Tenant-specific: Hour Timebank only
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Timebanking Guide';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-demo-pages.css">

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card civic-mb-8 civic-text-center civic-p-10">
        <h1 class="civic-text-primary civic-mt-0 civic-mb-4">hOUR Timebank: Building Community</h1>
        <p class="civic-text-2xl civic-text-medium civic-mb-6">Give an hour, get an hour. It's that simple.</p>

        <div class="demo-cta-row">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="civic-btn">Join Community</a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="civic-btn civic-btn-outline">See Impact</a>
        </div>
    </div>

    <!-- Verified Impact Stats -->
    <section class="demo-stats-section" aria-label="Impact statistics">
        <div class="demo-stats-label">
            <span>Our Verified Impact</span>
        </div>

        <div class="demo-stats-grid">
            <article class="civic-card demo-stat-card">
                <h3 class="demo-stat-card__label">Social Return</h3>
                <p class="demo-stat-card__value demo-stat-card__value--primary">16:1</p>
            </article>
            <article class="civic-card demo-stat-card">
                <h3 class="demo-stat-card__label">Improved Wellbeing</h3>
                <p class="demo-stat-card__value demo-stat-card__value--pink">100%</p>
            </article>
            <article class="civic-card demo-stat-card">
                <h3 class="demo-stat-card__label">Socially Connected</h3>
                <p class="demo-stat-card__value demo-stat-card__value--green">95%</p>
            </article>
        </div>
    </section>

    <!-- How It Works -->
    <div class="civic-card civic-p-10 civic-mb-10">
        <h2 class="civic-text-center civic-mt-0 civic-mb-8 civic-text-dark">How It Works: 3 Simple Steps</h2>

        <div class="demo-steps-grid">

            <div class="demo-step">
                <div class="civic-step-circle civic-step-circle--blue">
                    <i class="fa-solid fa-handshake" aria-hidden="true"></i>
                </div>
                <h3 class="demo-step__title">Give an Hour</h3>
                <p class="demo-step__desc">Share a skill you love—from practical help to a friendly chat or a lift to the shops.</p>
            </div>

            <div class="demo-step">
                <div class="civic-step-circle civic-step-circle--pink">
                    <i class="fa-solid fa-clock" aria-hidden="true"></i>
                </div>
                <h3 class="demo-step__title">Earn a Credit</h3>
                <p class="demo-step__desc">You automatically earn one Time Credit for every hour you spend helping another member.</p>
            </div>

            <div class="demo-step">
                <div class="civic-step-circle civic-step-circle--green">
                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                </div>
                <h3 class="demo-step__title">Get Help</h3>
                <p class="demo-step__desc">Spend your credit to get support, learn a new skill, or join a community work day.</p>
            </div>

        </div>
    </div>

    <!-- Fundamental Values -->
    <div class="civic-card civic-p-10 civic-mb-10 civic-border-left-primary">
        <h2 class="civic-text-center civic-mt-0 civic-mb-6 civic-text-dark">Our Fundamental Values</h2>
        <p class="civic-text-center civic-text-lg civic-text-medium civic-max-w-lg civic-mx-auto civic-mb-6">
            At hOUR Timebank, we believe that true wealth is found in our connections with one another. Our community is built on five fundamental values:
        </p>

        <ul class="demo-values-list" role="list">
            <li><strong>We Are All Assets:</strong> Every human being has something of value to contribute.</li>
            <li><strong>Redefining Work:</strong> We honour the real work of family and community.</li>
            <li><strong>Reciprocity:</strong> Helping works better as a two-way street.</li>
            <li><strong>Social Networks:</strong> People flourish in community and perish in isolation.</li>
        </ul>
    </div>

    <!-- CTA -->
    <div class="civic-card civic-p-10 civic-text-center">
        <span class="civic-tag civic-tag--purple civic-tag--pill civic-mb-4">Social Impact</span>
        <h2 class="civic-text-primary civic-mt-0 civic-mb-5">A 1:16 Return on Investment</h2>
        <p class="civic-text-lg civic-text-medium civic-max-w-md civic-mx-auto civic-mb-6 civic-leading-relaxed">
            We have a proven, independently validated model. We are now seeking strategic partners to help us secure our core operations and scale our impact across Ireland.
        </p>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/partner" class="civic-btn">Partner With Us</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>