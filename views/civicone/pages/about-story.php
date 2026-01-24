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

<link rel="stylesheet" href="<?= Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-about-story.css">

<div class="civic-container">

    <!-- Header Section -->
    <div class="civic-card about-story-hero">
        <span class="about-story-badge">HOUR TIMEBANK CLG</span>
        <h1>Our Mission & Vision</h1>
        <p>Building a resilient and equitable society based on mutual respect.</p>
    </div>

    <!-- Mission & Vision Grid -->
    <div class="about-story-mission-grid">

        <!-- Mission -->
        <div class="civic-card about-story-mission-card">
            <h2>
                <span aria-hidden="true">🚩</span> Our Mission
            </h2>
            <p>
                To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received, building a resilient and equitable society based on mutual respect.
            </p>
        </div>

        <!-- Vision -->
        <div class="civic-card about-story-vision-card">
            <h2>
                <span aria-hidden="true">👁️</span> Our Vision
            </h2>
            <p>
                An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient, and thriving local communities.
            </p>
        </div>
    </div>

    <!-- Values Section -->
    <div class="civic-card about-story-values">
        <h2>The Values That Guide Every Hour</h2>

        <div class="about-story-values-grid">

            <div class="about-story-value">
                <div class="about-story-value-icon" aria-hidden="true">⚖️</div>
                <h3 class="primary">Reciprocity & Equality</h3>
                <p>
                    We believe in a two-way street; everyone has something to give. We honour the time and skills of all members equally—one hour equals one hour, no matter the service.
                </p>
            </div>

            <div class="about-story-value">
                <div class="about-story-value-icon" aria-hidden="true">🕸️</div>
                <h3 class="pink">Inclusion & Connection</h3>
                <p>
                    We welcome people of all ages, backgrounds, and abilities, celebrating everyone as a valuable asset. We exist to reduce isolation and build meaningful relationships.
                </p>
            </div>

            <div class="about-story-value">
                <div class="about-story-value-icon" aria-hidden="true">💚</div>
                <h3 class="green">Empowerment & Resilience</h3>
                <p>
                    We provide a platform for individuals to recognize their own value and actively participate in building community. This mechanism is proven to build resilience.
                </p>
            </div>

        </div>
    </div>

    <!-- Professional Foundation -->
    <div class="civic-card about-story-foundation">
        <h2>Our Professional Foundation</h2>
        <p>
            Our journey began in 2012 with the Clonakilty Favour Exchange. To ensure long-term stability and impact, the directors established hOUR Timebank CLG as a formal, registered Irish charity in 2017.
        </p>

        <div class="about-story-badges">
            <span class="about-story-badge-item">✓ Registered Charity</span>
            <span class="about-story-badge-item">✓ Rethink Ireland Awardee</span>
            <span class="about-story-badge-item">✓ 1:16 SROI Impact</span>
        </div>

        <div class="about-story-proof-card">
            <h3>Want proof of our impact?</h3>
            <p>We have an independently verified Social Return on Investment (SROI) study.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="civic-btn">View Full Report</a>
        </div>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
