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
    <div class="civic-card" style="margin-bottom: 40px; text-align: center; padding: 50px;">
        <span style="display: inline-block; padding: 5px 15px; background: #e0e7ff; color: var(--skin-primary); border-radius: 20px; font-size: 0.8rem; font-weight: bold; margin-bottom: 20px;">HOUR TIMEBANK CLG</span>
        <h1 style="color: var(--skin-primary); margin-top: 0; margin-bottom: 15px;">Our Mission & Vision</h1>
        <p style="font-size: 1.3rem; color: #555; max-width: 800px; margin: 0 auto;">Building a resilient and equitable society based on mutual respect.</p>
    </div>

    <!-- Mission & Vision Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 50px;">

        <!-- Mission -->
        <div class="civic-card" style="padding: 40px; border-left: 5px solid var(--skin-primary);">
            <h2 style="color: var(--skin-primary); margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span>🚩</span> Our Mission
            </h2>
            <p style="font-size: 1.1rem; line-height: 1.7; color: #555;">
                To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received, building a resilient and equitable society based on mutual respect.
            </p>
        </div>

        <!-- Vision -->
        <div class="civic-card" style="padding: 40px; border-left: 5px solid #db2777;">
            <h2 style="color: #db2777; margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span>👁️</span> Our Vision
            </h2>
            <p style="font-size: 1.1rem; line-height: 1.7; color: #555;">
                An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient, and thriving local communities.
            </p>
        </div>
    </div>

    <!-- Values Section -->
    <div class="civic-card" style="padding: 50px; margin-bottom: 50px;">
        <h2 style="text-align: center; margin-top: 0; margin-bottom: 40px; color: #333;">The Values That Guide Every Hour</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px;">

            <div style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 10px;">⚖️</div>
                <h3 style="color: var(--skin-primary); margin-bottom: 15px;">Reciprocity & Equality</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">
                    We believe in a two-way street; everyone has something to give. We honour the time and skills of all members equally—one hour equals one hour, no matter the service.
                </p>
            </div>

            <div style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 10px;">🕸️</div>
                <h3 style="color: #db2777; margin-bottom: 15px;">Inclusion & Connection</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">
                    We welcome people of all ages, backgrounds, and abilities, celebrating everyone as a valuable asset. We exist to reduce isolation and build meaningful relationships.
                </p>
            </div>

            <div style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 10px;">💚</div>
                <h3 style="color: #166534; margin-bottom: 15px;">Empowerment & Resilience</h3>
                <p style="color: var(--civic-text-secondary, #4B5563); line-height: 1.6;">
                    We provide a platform for individuals to recognize their own value and actively participate in building community. This mechanism is proven to build resilience.
                </p>
            </div>

        </div>
    </div>

    <!-- Professional Foundation -->
    <div class="civic-card" style="padding: 50px; text-align: center; background: #f9fafb;">
        <h2 style="color: #333; margin-top: 0; margin-bottom: 30px;">Our Professional Foundation</h2>
        <p style="font-size: 1.1rem; color: #555; max-width: 800px; margin: 0 auto 40px auto; line-height: 1.6;">
            Our journey began in 2012 with the Clonakilty Favour Exchange. To ensure long-term stability and impact, the directors established hOUR Timebank CLG as a formal, registered Irish charity in 2017.
        </p>

        <div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin-bottom: 40px;">
            <span style="background: #d1fae5; color: #065f46; padding: 8px 20px; border-radius: 20px; font-weight: bold;">✓ Registered Charity</span>
            <span style="background: #d1fae5; color: #065f46; padding: 8px 20px; border-radius: 20px; font-weight: bold;">✓ Rethink Ireland Awardee</span>
            <span style="background: #d1fae5; color: #065f46; padding: 8px 20px; border-radius: 20px; font-weight: bold;">✓ 1:16 SROI Impact</span>
        </div>

        <div style="background: white; padding: 30px; border-radius: 8px; display: inline-block; border: 1px solid #eee;">
            <h3 style="margin-top: 0; color: #333;">Want proof of our impact?</h3>
            <p style="color: var(--civic-text-secondary, #4B5563); margin-bottom: 20px;">We have an independently verified Social Return on Investment (SROI) study.</p>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/impact-report" class="civic-btn">View Full Report</a>
        </div>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
