<?php
/**
 * Partner With Us - Modern Theme
 */
$pageTitle = 'Partner With Us';
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
#partner-wrapper {
    --partner-theme: #0891b2;
    --partner-theme-rgb: 8, 145, 178;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #partner-wrapper {
        padding-top: 120px;
    }
}

#partner-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
}

[data-theme="light"] #partner-wrapper::before {
    background: linear-gradient(135deg,
        rgba(8, 145, 178, 0.08) 0%,
        rgba(6, 182, 212, 0.08) 50%,
        rgba(8, 145, 178, 0.08) 100%);
}

[data-theme="dark"] #partner-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(8, 145, 178, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(6, 182, 212, 0.1) 0%, transparent 50%);
}

#partner-wrapper .partner-inner {
    max-width: 1000px;
    margin: 0 auto;
}

#partner-wrapper .partner-header {
    text-align: center;
    margin-bottom: 3rem;
}

#partner-wrapper .partner-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
}

#partner-wrapper .partner-header p {
    color: var(--htb-text-muted);
    font-size: 1.2rem;
    max-width: 700px;
    margin: 0 auto;
}

#partner-wrapper .partner-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2rem;
}

[data-theme="light"] #partner-wrapper .partner-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(8, 145, 178, 0.15);
    box-shadow: 0 8px 32px rgba(8, 145, 178, 0.1);
}

[data-theme="dark"] #partner-wrapper .partner-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(8, 145, 178, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#partner-wrapper .partner-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 1.5rem 0;
}

#partner-wrapper .partner-card p {
    color: var(--htb-text-muted);
    line-height: 1.7;
    margin: 0 0 1rem 0;
}

#partner-wrapper .benefits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

#partner-wrapper .benefit-card {
    padding: 1.5rem;
    border-radius: 16px;
}

[data-theme="light"] #partner-wrapper .benefit-card {
    background: rgba(8, 145, 178, 0.05);
    border: 1px solid rgba(8, 145, 178, 0.1);
}

[data-theme="dark"] #partner-wrapper .benefit-card {
    background: rgba(8, 145, 178, 0.1);
    border: 1px solid rgba(8, 145, 178, 0.15);
}

#partner-wrapper .benefit-card h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

#partner-wrapper .benefit-card h3 i {
    color: var(--partner-theme);
}

#partner-wrapper .benefit-card p {
    color: var(--htb-text-muted);
    margin: 0;
    font-size: 0.95rem;
}

#partner-wrapper .cta-section {
    text-align: center;
    padding: 3rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    margin-top: 2rem;
}

[data-theme="light"] #partner-wrapper .cta-section {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.1) 0%, rgba(8, 145, 178, 0.05) 100%);
    border: 1px solid rgba(8, 145, 178, 0.2);
}

[data-theme="dark"] #partner-wrapper .cta-section {
    background: linear-gradient(135deg, rgba(8, 145, 178, 0.2) 0%, rgba(8, 145, 178, 0.1) 100%);
    border: 1px solid rgba(8, 145, 178, 0.3);
}

#partner-wrapper .cta-section h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 1rem 0;
}

#partner-wrapper .cta-section p {
    color: var(--htb-text-muted);
    font-size: 1.1rem;
    margin: 0 0 1.5rem 0;
}

#partner-wrapper .cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    background: linear-gradient(135deg, var(--partner-theme) 0%, #0e7490 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(8, 145, 178, 0.4);
    transition: all 0.3s ease;
}

#partner-wrapper .cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(8, 145, 178, 0.5);
}

@media (max-width: 768px) {
    #partner-wrapper .partner-header h1 {
        font-size: 2rem;
    }

    #partner-wrapper .benefits-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div id="partner-wrapper">
    <div class="partner-inner">

        <div class="partner-header">
            <h1>Partner With Us</h1>
            <p>Join us in building stronger, more connected communities through the power of timebanking.</p>
        </div>

        <div class="partner-card">
            <h2>Why Partner?</h2>
            <p>Timebanking creates measurable social impact by connecting people, reducing isolation, and building community resilience. Your partnership helps us expand these benefits to more communities.</p>

            <div class="benefits-grid">
                <div class="benefit-card">
                    <h3><i class="fa-solid fa-chart-line"></i> Measurable Impact</h3>
                    <p>Clear data-driven reporting that showcases your commitment to social responsibility and community development.</p>
                </div>
                <div class="benefit-card">
                    <h3><i class="fa-solid fa-users"></i> Community Connection</h3>
                    <p>Directly support local communities and see the real difference your partnership makes in people's lives.</p>
                </div>
                <div class="benefit-card">
                    <h3><i class="fa-solid fa-handshake-angle"></i> Strategic Alignment</h3>
                    <p>Align your brand with values of reciprocity, community, and sustainable social development.</p>
                </div>
            </div>
        </div>

        <div class="partner-card">
            <h2>Partnership Opportunities</h2>
            <p>We offer various partnership levels to suit different organisations and objectives:</p>
            <ul style="color: var(--htb-text-muted); line-height: 1.8; padding-left: 1.5rem;">
                <li><strong>Corporate Partnerships:</strong> CSR initiatives, employee volunteering, and skills sharing programmes</li>
                <li><strong>Community Sponsorship:</strong> Support local timebank hubs and community initiatives</li>
                <li><strong>Technology Partners:</strong> Help us develop and improve our platform</li>
                <li><strong>Research Collaboration:</strong> Partner on social impact research and evaluation</li>
            </ul>
        </div>

        <div class="cta-section">
            <h2>Let's Start a Conversation</h2>
            <p>Interested in exploring partnership opportunities? We'd love to hear from you.</p>
            <a href="<?= $basePath ?>/contact" class="cta-btn">
                <i class="fa-solid fa-envelope"></i>
                Contact Us
            </a>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
