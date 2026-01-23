<?php
// CivicOne View: Our Story
// Tenant-specific: Hour Timebank only
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
if ($tSlug !== 'hour-timebank' && $tSlug !== 'hour_timebank') {
    http_response_code(404);
    \Nexus\Core\View::render('errors/404');
    exit;
}

$pageTitle = 'Our Story';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card civic-mb-8 civic-text-center civic-p-10">
        <span class="civic-inline-block civic-py-1 civic-px-3 civic-bg-blue-50 civic-rounded-pill civic-text-sm civic-font-bold civic-mb-4" style="color: var(--skin-primary);">HOUR TIMEBANK CLG</span>
        <h1 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 15px;">Our Mission & Vision</h1>
        <p class="civic-text-2xl civic-text-medium civic-max-w-lg civic-mx-auto">Building a resilient and equitable society based on mutual respect.</p>
    </div>

    <!-- Mission & Vision Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 50px;">

        <!-- Mission -->
        <div class="civic-card civic-p-8 civic-border-left-primary">
            <h2 style="color: var(--skin-primary); margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span>🚩</span> Our Mission
            </h2>
            <p class="civic-text-lg civic-text-medium" style="line-height: 1.7;">
                To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received, building a resilient and equitable society based on mutual respect.
            </p>
        </div>

        <!-- Vision -->
        <div class="civic-card civic-p-8 civic-border-left-rose">
            <h2 class="civic-text-rose civic-mt-0" style="display: flex; align-items: center; gap: 10px;">
                <span>👁️</span> Our Vision
            </h2>
            <p class="civic-text-lg civic-text-medium" style="line-height: 1.7;">
                An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient, and thriving local communities.
            </p>
        </div>
    </div>

    <!-- Values Section -->
    <div class="civic-card civic-p-10 civic-mb-10">
        <h2 class="civic-text-center civic-mt-0 civic-mb-8 civic-text-dark">The Values That Guide Every Hour</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px;">

            <div class="civic-text-center">
                <div class="civic-text-4xl civic-mb-3">⚖️</div>
                <h3 style="color: var(--skin-primary); margin-bottom: 15px;">Reciprocity & Equality</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">
                    We believe in a two-way street; everyone has something to give. We honour the time and skills of all members equally—one hour equals one hour, no matter the service.
                </p>
            </div>

            <div class="civic-text-center">
                <div class="civic-text-4xl civic-mb-3">🕸️</div>
                <h3 class="civic-text-rose civic-mb-3">Inclusion & Connection</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">
                    We welcome people of all ages, backgrounds, and abilities, celebrating everyone as a valuable asset. We exist to reduce isolation and build meaningful relationships.
                </p>
            </div>

            <div class="civic-text-center">
                <div class="civic-text-4xl civic-mb-3">💚</div>
                <h3 class="civic-text-green-dark civic-mb-3">Empowerment & Resilience</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">
                    We provide a platform for individuals to recognize their own value and actively participate in building community. This mechanism is proven to build resilience.
                </p>
            </div>

        </div>
    </div>

    <!-- Professional Foundation -->
    <div class="civic-card civic-p-10 civic-text-center civic-bg-gray-50">
        <h2 class="civic-text-dark civic-mt-0 civic-mb-6">Our Professional Foundation</h2>
        <p class="civic-text-lg civic-text-medium civic-max-w-lg civic-mx-auto civic-mb-8 civic-leading-relaxed">
            Our journey began in 2012 with the Clonakilty Favour Exchange. To ensure long-term stability and impact, the directors established hOUR Timebank CLG as a formal, registered Irish charity in 2017.
        </p>

        <div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin-bottom: 40px;">
            <span class="civic-bg-green-50 civic-text-green-dark civic-rounded-pill civic-font-bold" style="padding: 8px 20px;">✓ Registered Charity</span>
            <span class="civic-bg-green-50 civic-text-green-dark civic-rounded-pill civic-font-bold" style="padding: 8px 20px;">✓ Rethink Ireland Awardee</span>
            <span class="civic-bg-green-50 civic-text-green-dark civic-rounded-pill civic-font-bold" style="padding: 8px 20px;">✓ 1:16 SROI Impact</span>
        </div>

        <div class="civic-bg-white civic-p-6 civic-rounded civic-inline-block civic-border">
            <h3 class="civic-mt-0 civic-text-dark">Want proof of our impact?</h3>
            <p style="color: var(--civic-text-secondary, #4B5563); margin-bottom: 20px;">We have an independently verified Social Return on Investment (SROI) study.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="civic-btn">View Full Report</a>
        </div>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
