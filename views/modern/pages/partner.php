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
