<?php
/**
 * Privacy Policy - Modern Glassmorphism Design
 * Theme Color: Indigo (#6366f1)
 */
$pageTitle = 'Privacy Policy';
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


<div id="privacy-glass-wrapper">
    <div class="privacy-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/legal" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="privacy-page-header">
            <h1>
                <span class="header-icon"><i class="fa-solid fa-shield-halved"></i></span>
                Privacy Policy
            </h1>
            <p>How we collect, use, and protect your personal information</p>
            <span class="last-updated">
                <i class="fa-solid fa-calendar"></i>
                Last Updated: <?= date('F j, Y') ?>
            </span>
        </div>

        <!-- Quick Navigation -->
        <div class="privacy-quick-nav">
            <a href="#data-collection" class="privacy-nav-btn">
                <i class="fa-solid fa-database"></i> Data Collection
            </a>
            <a href="#data-usage" class="privacy-nav-btn">
                <i class="fa-solid fa-chart-pie"></i> How We Use Data
            </a>
            <a href="#your-rights" class="privacy-nav-btn">
                <i class="fa-solid fa-user-shield"></i> Your Rights
            </a>
            <a href="#cookies" class="privacy-nav-btn">
                <i class="fa-solid fa-cookie-bite"></i> Cookies
            </a>
        </div>

        <!-- Introduction -->
        <div class="privacy-section highlight">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-handshake"></i></div>
                <h2>Our Commitment to Your Privacy</h2>
            </div>
            <p><?= htmlspecialchars($tenantName) ?> is committed to protecting your privacy and ensuring your personal data is handled responsibly. This policy explains what information we collect, why we collect it, and how you can manage your data.</p>
            <p>We believe in <strong>transparency</strong> and <strong>user control</strong>. You have the right to understand and manage how your information is used.</p>
        </div>

        <!-- Data Collection -->
        <div class="privacy-section" id="data-collection">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-database"></i></div>
                <h2>Information We Collect</h2>
            </div>
            <p>We collect only the information necessary to provide and improve our services:</p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data Type</th>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Account Information</strong></td>
                        <td>Name, email, password (encrypted) - Required to create and manage your account</td>
                    </tr>
                    <tr>
                        <td><strong>Profile Details</strong></td>
                        <td>Bio, skills, location, photo - Helps connect you with community members</td>
                    </tr>
                    <tr>
                        <td><strong>Activity Data</strong></td>
                        <td>Exchanges, messages, time credits - Essential for platform functionality</td>
                    </tr>
                    <tr>
                        <td><strong>Device Information</strong></td>
                        <td>Browser type, IP address - Used for security and troubleshooting</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Data Usage -->
        <div class="privacy-section" id="data-usage">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <h2>How We Use Your Data</h2>
            </div>
            <p>Your data is used exclusively for the following purposes:</p>
            <ul>
                <li><strong>Service Delivery:</strong> Facilitating time exchanges and community connections</li>
                <li><strong>Communication:</strong> Sending important updates, notifications, and messages from other members</li>
                <li><strong>Security:</strong> Protecting your account and preventing fraud or abuse</li>
                <li><strong>Improvement:</strong> Analyzing usage patterns to enhance platform features</li>
                <li><strong>Legal Compliance:</strong> Meeting regulatory requirements when necessary</li>
            </ul>
            <p><strong>We do not sell your personal data to third parties.</strong> Your information is never shared with advertisers or data brokers.</p>
        </div>

        <!-- Profile Visibility -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-eye"></i></div>
                <h2>Profile Visibility</h2>
            </div>
            <p>Your profile is visible to other verified members of the timebank community. This visibility is essential for facilitating exchanges and building trust within the community.</p>
            <p>You can control what information appears on your profile through your <strong>account settings</strong>. Some information, like your name and general location, is required for meaningful community participation.</p>
        </div>

        <!-- Data Protection -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-lock"></i></div>
                <h2>How We Protect Your Data</h2>
            </div>
            <p>We implement robust security measures to safeguard your information:</p>
            <ul>
                <li><strong>Encryption:</strong> All data is encrypted in transit (HTTPS) and at rest</li>
                <li><strong>Secure Passwords:</strong> Passwords are hashed using industry-standard algorithms</li>
                <li><strong>Access Controls:</strong> Strict internal policies limit who can access your data</li>
                <li><strong>Regular Audits:</strong> We conduct security reviews and update our practices accordingly</li>
                <li><strong>Secure Infrastructure:</strong> Our servers are hosted in certified data centers</li>
            </ul>
        </div>

        <!-- Your Rights -->
        <div class="privacy-section highlight" id="your-rights">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-user-shield"></i></div>
                <h2>Your Privacy Rights</h2>
            </div>
            <p>You have full control over your personal data. Here are your rights:</p>

            <div class="rights-grid">
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-eye"></i></span>
                    <h4>Right to Access</h4>
                    <p>Request a copy of all personal data we hold about you</p>
                </div>
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-pen"></i></span>
                    <h4>Right to Rectification</h4>
                    <p>Correct any inaccurate or incomplete information</p>
                </div>
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-trash"></i></span>
                    <h4>Right to Erasure</h4>
                    <p>Request deletion of your account and associated data</p>
                </div>
                <div class="right-card">
                    <span class="right-icon"><i class="fa-solid fa-download"></i></span>
                    <h4>Right to Portability</h4>
                    <p>Export your data in a machine-readable format</p>
                </div>
            </div>

            <p>To exercise any of these rights, please contact us through the link below.</p>
        </div>

        <!-- Cookies -->
        <div class="privacy-section" id="cookies">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-cookie-bite"></i></div>
                <h2>Cookies & Tracking</h2>
            </div>
            <p>We use cookies to enhance your experience on our platform:</p>
            <ul>
                <li><strong>Essential Cookies:</strong> Required for login, security, and basic functionality</li>
                <li><strong>Preference Cookies:</strong> Remember your settings like theme and language</li>
                <li><strong>Analytics Cookies:</strong> Help us understand how people use the platform (anonymized)</li>
            </ul>
            <p>We do <strong>not</strong> use advertising cookies or share data with ad networks. You can manage cookie preferences in your browser settings.</p>
        </div>

        <!-- Data Retention -->
        <div class="privacy-section">
            <div class="section-header">
                <div class="section-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <h2>Data Retention</h2>
            </div>
            <p>We retain your data only as long as necessary:</p>
            <ul>
                <li><strong>Active Accounts:</strong> Data is kept while your account remains active</li>
                <li><strong>Deleted Accounts:</strong> Personal data is removed within 30 days of account deletion</li>
                <li><strong>Transaction Records:</strong> May be retained longer for legal and audit purposes</li>
            </ul>
        </div>

        <!-- Contact CTA -->
        <div class="privacy-cta">
            <h2><i class="fa-solid fa-envelope"></i> Questions About Your Privacy?</h2>
            <p>We're here to help. If you have any questions about this policy or want to exercise your data rights, please don't hesitate to reach out.</p>
            <a href="<?= $basePath ?>/contact" class="privacy-cta-btn">
                <i class="fa-solid fa-paper-plane"></i>
                Contact Our Privacy Team
            </a>
        </div>

    </div>
</div>

<script>
// Smooth scroll for anchor links
document.querySelectorAll('#privacy-glass-wrapper .privacy-nav-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href.startsWith('#')) {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});

// Button press states
document.querySelectorAll('#privacy-glass-wrapper .privacy-nav-btn, #privacy-glass-wrapper .privacy-cta-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
