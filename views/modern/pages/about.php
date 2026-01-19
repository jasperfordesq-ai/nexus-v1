<?php
/**
 * About Page - Modern Theme
 */
$pageTitle = 'About Us';
$hTitle = 'About Us';
$hSubtitle = 'Our Mission & Vision';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'This Community';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
}
?>


<div id="about-wrapper">
    <div class="about-inner">

        <div class="about-header">
            <h1>About <?= htmlspecialchars($tenantName) ?></h1>
            <p>Empowering communities through the exchange of time and skills</p>
        </div>

        <div class="about-section">
            <h2>Our Mission</h2>
            <p>We believe that everyone has something valuable to contribute. Our platform connects neighbors who share interests and needs, facilitating the exchange of skills and support where every hour given is an hour received.</p>
            <p>By building a resilient and equitable society based on mutual respect, we're creating stronger, more connected communities.</p>
        </div>

        <div class="about-section">
            <h2>How It Works</h2>
            <p>Timebanking is simple: one hour of your time equals one time credit, regardless of the service provided. Everyone's time is valued equally.</p>
            <p>Whether you're offering gardening help, teaching a language, providing tech support, or offering companionship, your contribution matters and is rewarded fairly.</p>
        </div>

        <div class="values-grid">
            <div class="value-card">
                <div class="value-icon"><i class="fa-solid fa-handshake"></i></div>
                <h3>Connect</h3>
                <p>Find neighbors who share your interests and needs</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fa-solid fa-arrows-rotate"></i></div>
                <h3>Exchange</h3>
                <p>Trade 1 hour of help for 1 time credit. Everyone's time is equal.</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fa-solid fa-seedling"></i></div>
                <h3>Grow</h3>
                <p>Build a stronger, more resilient local community together</p>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
