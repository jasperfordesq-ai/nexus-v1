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

<style>
#about-wrapper {
    --about-theme: #2563eb;
    --about-theme-rgb: 37, 99, 235;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #about-wrapper {
        padding-top: 120px;
    }
}

#about-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
}

[data-theme="light"] #about-wrapper::before {
    background: linear-gradient(135deg,
        rgba(37, 99, 235, 0.08) 0%,
        rgba(59, 130, 246, 0.08) 50%,
        rgba(37, 99, 235, 0.08) 100%);
}

[data-theme="dark"] #about-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(37, 99, 235, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
}

#about-wrapper .about-inner {
    max-width: 900px;
    margin: 0 auto;
}

#about-wrapper .about-header {
    text-align: center;
    margin-bottom: 3rem;
}

#about-wrapper .about-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
}

#about-wrapper .about-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    max-width: 600px;
    margin: 0 auto;
}

#about-wrapper .about-section {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 1.5rem;
}

[data-theme="light"] #about-wrapper .about-section {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(37, 99, 235, 0.15);
    box-shadow: 0 8px 32px rgba(37, 99, 235, 0.1);
}

[data-theme="dark"] #about-wrapper .about-section {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(37, 99, 235, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#about-wrapper .about-section h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
}

#about-wrapper .about-section p {
    color: var(--htb-text-muted);
    line-height: 1.7;
    margin: 0 0 1rem 0;
}

#about-wrapper .values-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

#about-wrapper .value-card {
    text-align: center;
    padding: 2rem;
    border-radius: 16px;
}

[data-theme="light"] #about-wrapper .value-card {
    background: rgba(37, 99, 235, 0.05);
    border: 1px solid rgba(37, 99, 235, 0.1);
}

[data-theme="dark"] #about-wrapper .value-card {
    background: rgba(37, 99, 235, 0.1);
    border: 1px solid rgba(37, 99, 235, 0.15);
}

#about-wrapper .value-card .value-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

#about-wrapper .value-card h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#about-wrapper .value-card p {
    color: var(--htb-text-muted);
    font-size: 0.95rem;
    margin: 0;
}

@media (max-width: 768px) {
    #about-wrapper .about-header h1 {
        font-size: 2rem;
    }

    #about-wrapper .values-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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
