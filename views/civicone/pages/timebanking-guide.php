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

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card civic-mb-8 civic-text-center civic-p-10">
        <h1 style="margin-top: 0; margin-bottom: 15px; color: var(--skin-primary);">hOUR Timebank: Building Community</h1>
        <p class="civic-text-2xl civic-text-medium civic-mb-6">Give an hour, get an hour. It's that simple.</p>

        <div style="display: flex; justify-content: center; gap: 20px;">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="civic-btn">Join Community</a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="civic-btn civic-btn-outline">See Impact</a>
        </div>
    </div>

    <!-- Verified Impact Stats -->
    <div style="margin-bottom: 50px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <span style="background: var(--skin-primary); color: white; padding: 5px 15px; border-radius: 4px; font-weight: bold;">Our Verified Impact</span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
            <div class="civic-card" style="text-align: center; padding: 30px;">
                <h3 style="color: var(--civic-text-secondary, #4B5563); font-size: 1rem; text-transform: uppercase; margin-top: 0;">Social Return</h3>
                <div style="font-size: 3rem; font-weight: bold; color: var(--skin-primary);">16:1</div>
            </div>
            <div class="civic-card civic-text-center civic-p-6">
                <h3 style="color: var(--civic-text-secondary, #4B5563); font-size: 1rem; text-transform: uppercase; margin-top: 0;">Improved Wellbeing</h3>
                <div class="civic-text-4xl civic-font-bold civic-text-pink">100%</div>
            </div>
            <div class="civic-card civic-text-center civic-p-6">
                <h3 style="color: var(--civic-text-secondary, #4B5563); font-size: 1rem; text-transform: uppercase; margin-top: 0;">Socially Connected</h3>
                <div class="civic-text-4xl civic-font-bold civic-text-emerald">95%</div>
            </div>
        </div>
    </div>

    <!-- How It Works -->
    <div class="civic-card civic-p-10 civic-mb-10">
        <h2 class="civic-text-center civic-mt-0 civic-mb-8 civic-text-dark">How It Works: 3 Simple Steps</h2>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px;">

            <div class="civic-text-center">
                <div class="civic-step-circle civic-step-circle--blue">
                    <i class="fa-solid fa-handshake"></i>
                </div>
                <h3 class="civic-text-dark civic-mb-3">Give an Hour</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">Share a skill you love—from practical help to a friendly chat or a lift to the shops.</p>
            </div>

            <div class="civic-text-center">
                <div class="civic-step-circle civic-step-circle--pink">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <h3 class="civic-text-dark civic-mb-3">Earn a Credit</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">You automatically earn one Time Credit for every hour you spend helping another member.</p>
            </div>

            <div class="civic-text-center">
                <div class="civic-step-circle civic-step-circle--green">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <h3 class="civic-text-dark civic-mb-3">Get Help</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">Spend your credit to get support, learn a new skill, or join a community work day.</p>
            </div>

        </div>
    </div>

    <!-- Fundamental Values -->
    <div class="civic-card civic-p-10 civic-mb-10 civic-border-left-primary">
        <h2 class="civic-text-center civic-mt-0 civic-mb-6 civic-text-dark">Our Fundamental Values</h2>
        <p class="civic-text-center civic-text-lg civic-text-medium civic-max-w-lg civic-mx-auto civic-mb-6">
            At hOUR Timebank, we believe that true wealth is found in our connections with one another. Our community is built on five fundamental values:
        </p>

        <ul class="civic-max-w-lg civic-mx-auto civic-leading-loose civic-text-medium" style="padding-left: 20px;">
            <li style="margin-bottom: 15px;"><strong>We Are All Assets:</strong> Every human being has something of value to contribute.</li>
            <li style="margin-bottom: 15px;"><strong>Redefining Work:</strong> We honour the real work of family and community.</li>
            <li style="margin-bottom: 15px;"><strong>Reciprocity:</strong> Helping works better as a two-way street.</li>
            <li style="margin-bottom: 15px;"><strong>Social Networks:</strong> People flourish in community and perish in isolation.</li>
        </ul>
    </div>

    <!-- CTA -->
    <div class="civic-card civic-p-10 civic-text-center">
        <span class="civic-tag civic-tag--purple civic-tag--pill civic-mb-4">Social Impact</span>
        <h2 style="margin-top: 0; margin-bottom: 20px; color: var(--skin-primary);">A 1:16 Return on Investment</h2>
        <p class="civic-text-lg civic-text-medium civic-max-w-md civic-mx-auto civic-mb-6 civic-leading-relaxed">
            We have a proven, independently validated model. We are now seeking strategic partners to help us secure our core operations and scale our impact across Ireland.
        </p>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/partner" class="civic-btn">Partner With Us</a>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>