<?php
/**
 * Privacy Policy - Modern Glassmorphism Design
 * Theme Color: Indigo (#6366f1)
 */
$pageTitle = 'Privacy Policy';
$hideHero = true;

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantName = 'This Community';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
}
?>

<style>
/* ============================================
   PRIVACY POLICY - GLASSMORPHISM 2025
   Theme: Indigo (#6366f1)
   ============================================ */

#privacy-glass-wrapper {
    --privacy-theme: #6366f1;
    --privacy-theme-rgb: 99, 102, 241;
    --privacy-theme-light: #818cf8;
    --privacy-theme-dark: #4f46e5;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem; /* Top padding for fixed header + utility bar on desktop */
}

@media (max-width: 900px) {
    #privacy-glass-wrapper {
        padding-top: 120px; /* Smaller top padding on mobile (no utility bar) */
    }
}

#privacy-glass-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #privacy-glass-wrapper::before {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.08) 0%,
        rgba(129, 140, 248, 0.08) 25%,
        rgba(139, 92, 246, 0.08) 50%,
        rgba(167, 139, 250, 0.08) 75%,
        rgba(99, 102, 241, 0.08) 100%);
    background-size: 400% 400%;
    animation: privacyGradientShift 15s ease infinite;
}

[data-theme="dark"] #privacy-glass-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(129, 140, 248, 0.05) 0%, transparent 70%);
}

@keyframes privacyGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

/* Content Reveal Animation */
@keyframes privacyFadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#privacy-glass-wrapper {
    animation: privacyFadeInUp 0.4s ease-out;
}

#privacy-glass-wrapper .privacy-inner {
    max-width: 900px;
    margin: 0 auto;
}

/* Page Header */
#privacy-glass-wrapper .privacy-page-header {
    text-align: center;
    margin-bottom: 2rem;
}

#privacy-glass-wrapper .privacy-page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#privacy-glass-wrapper .privacy-page-header .header-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--privacy-theme) 0%, var(--privacy-theme-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}

#privacy-glass-wrapper .privacy-page-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    margin: 0;
    max-width: 600px;
    margin: 0 auto;
}

#privacy-glass-wrapper .privacy-page-header .last-updated {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
}

[data-theme="light"] #privacy-glass-wrapper .privacy-page-header .last-updated {
    background: rgba(99, 102, 241, 0.1);
    color: var(--privacy-theme-dark);
}

[data-theme="dark"] #privacy-glass-wrapper .privacy-page-header .last-updated {
    background: rgba(99, 102, 241, 0.2);
    color: var(--privacy-theme-light);
}

/* Quick Navigation */
#privacy-glass-wrapper .privacy-quick-nav {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
}

#privacy-glass-wrapper .privacy-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.15rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

[data-theme="light"] #privacy-glass-wrapper .privacy-nav-btn {
    background: rgba(255, 255, 255, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(99, 102, 241, 0.2);
    box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #privacy-glass-wrapper .privacy-nav-btn {
    background: rgba(30, 41, 59, 0.7);
    color: var(--htb-text-main);
    border: 1px solid rgba(99, 102, 241, 0.3);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

#privacy-glass-wrapper .privacy-nav-btn:hover {
    transform: translateY(-2px);
    border-color: var(--privacy-theme);
}

[data-theme="light"] #privacy-glass-wrapper .privacy-nav-btn:hover {
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] #privacy-glass-wrapper .privacy-nav-btn:hover {
    background: rgba(30, 41, 59, 0.9);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
}

/* Privacy Section Card */
#privacy-glass-wrapper .privacy-section {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

[data-theme="light"] #privacy-glass-wrapper .privacy-section {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.15);
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #privacy-glass-wrapper .privacy-section {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

#privacy-glass-wrapper .privacy-section:hover {
    transform: translateY(-2px);
}

[data-theme="light"] #privacy-glass-wrapper .privacy-section:hover {
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.15);
}

[data-theme="dark"] #privacy-glass-wrapper .privacy-section:hover {
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
}

#privacy-glass-wrapper .privacy-section .section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

#privacy-glass-wrapper .privacy-section .section-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

[data-theme="light"] #privacy-glass-wrapper .privacy-section .section-icon {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0.05) 100%);
    color: var(--privacy-theme);
}

[data-theme="dark"] #privacy-glass-wrapper .privacy-section .section-icon {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.25) 0%, rgba(99, 102, 241, 0.1) 100%);
    color: var(--privacy-theme-light);
}

#privacy-glass-wrapper .privacy-section .section-header h2 {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0;
}

#privacy-glass-wrapper .privacy-section p {
    color: var(--htb-text-muted);
    font-size: 1rem;
    line-height: 1.75;
    margin: 0 0 1rem 0;
}

#privacy-glass-wrapper .privacy-section p:last-child {
    margin-bottom: 0;
}

#privacy-glass-wrapper .privacy-section strong {
    color: var(--htb-text-main);
}

#privacy-glass-wrapper .privacy-section ul {
    margin: 1rem 0;
    padding-left: 0;
    list-style: none;
}

#privacy-glass-wrapper .privacy-section ul li {
    position: relative;
    padding-left: 1.75rem;
    margin-bottom: 0.75rem;
    color: var(--htb-text-muted);
    line-height: 1.6;
}

#privacy-glass-wrapper .privacy-section ul li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.5rem;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--privacy-theme);
}

/* Highlight Section */
#privacy-glass-wrapper .privacy-section.highlight {
    border-left: 4px solid var(--privacy-theme);
}

[data-theme="light"] #privacy-glass-wrapper .privacy-section.highlight {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(255, 255, 255, 0.7) 100%);
}

[data-theme="dark"] #privacy-glass-wrapper .privacy-section.highlight {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(30, 41, 59, 0.6) 100%);
}

/* Data Table */
#privacy-glass-wrapper .data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 1rem 0;
    border-radius: 12px;
    overflow: hidden;
}

[data-theme="light"] #privacy-glass-wrapper .data-table {
    border: 1px solid rgba(99, 102, 241, 0.15);
}

[data-theme="dark"] #privacy-glass-wrapper .data-table {
    border: 1px solid rgba(99, 102, 241, 0.2);
}

#privacy-glass-wrapper .data-table th,
#privacy-glass-wrapper .data-table td {
    padding: 1rem;
    text-align: left;
}

#privacy-glass-wrapper .data-table th {
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

[data-theme="light"] #privacy-glass-wrapper .data-table th {
    background: rgba(99, 102, 241, 0.1);
    color: var(--privacy-theme-dark);
}

[data-theme="dark"] #privacy-glass-wrapper .data-table th {
    background: rgba(99, 102, 241, 0.2);
    color: var(--privacy-theme-light);
}

#privacy-glass-wrapper .data-table td {
    color: var(--htb-text-muted);
    font-size: 0.95rem;
}

[data-theme="light"] #privacy-glass-wrapper .data-table tr:nth-child(even) td {
    background: rgba(99, 102, 241, 0.03);
}

[data-theme="dark"] #privacy-glass-wrapper .data-table tr:nth-child(even) td {
    background: rgba(99, 102, 241, 0.05);
}

/* Rights Grid */
#privacy-glass-wrapper .rights-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin: 1.5rem 0;
}

#privacy-glass-wrapper .right-card {
    padding: 1.25rem;
    border-radius: 14px;
    transition: all 0.3s ease;
}

[data-theme="light"] #privacy-glass-wrapper .right-card {
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #privacy-glass-wrapper .right-card {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

#privacy-glass-wrapper .right-card:hover {
    transform: translateY(-2px);
}

[data-theme="light"] #privacy-glass-wrapper .right-card:hover {
    background: rgba(99, 102, 241, 0.08);
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] #privacy-glass-wrapper .right-card:hover {
    background: rgba(99, 102, 241, 0.15);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

#privacy-glass-wrapper .right-card .right-icon {
    font-size: 1.5rem;
    margin-bottom: 0.75rem;
    display: block;
}

#privacy-glass-wrapper .right-card h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#privacy-glass-wrapper .right-card p {
    font-size: 0.9rem;
    color: var(--htb-text-muted);
    margin: 0;
    line-height: 1.5;
}

/* Contact CTA */
#privacy-glass-wrapper .privacy-cta {
    text-align: center;
    padding: 3rem 2rem;
    border-radius: 20px;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    margin-top: 2rem;
}

[data-theme="light"] #privacy-glass-wrapper .privacy-cta {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0.05) 100%);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

[data-theme="dark"] #privacy-glass-wrapper .privacy-cta {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
}

#privacy-glass-wrapper .privacy-cta h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#privacy-glass-wrapper .privacy-cta p {
    color: var(--htb-text-muted);
    font-size: 1.05rem;
    line-height: 1.6;
    max-width: 600px;
    margin: 0 auto 1.5rem auto;
}

#privacy-glass-wrapper .privacy-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, var(--privacy-theme) 0%, var(--privacy-theme-dark) 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
}

#privacy-glass-wrapper .privacy-cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
}

/* Back to Legal Link */
#privacy-glass-wrapper .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

[data-theme="light"] #privacy-glass-wrapper .back-link {
    color: var(--privacy-theme-dark);
}

[data-theme="dark"] #privacy-glass-wrapper .back-link {
    color: var(--privacy-theme-light);
}

#privacy-glass-wrapper .back-link:hover {
    gap: 0.75rem;
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    #privacy-glass-wrapper {
        padding: 120px 1rem 3rem;
    }

    #privacy-glass-wrapper .privacy-page-header h1 {
        font-size: 1.85rem;
        flex-direction: column;
        gap: 1rem;
    }

    #privacy-glass-wrapper .privacy-page-header .header-icon {
        width: 56px;
        height: 56px;
        font-size: 1.5rem;
    }

    #privacy-glass-wrapper .privacy-page-header p {
        font-size: 1rem;
    }

    #privacy-glass-wrapper .privacy-quick-nav {
        gap: 0.5rem;
    }

    #privacy-glass-wrapper .privacy-nav-btn {
        padding: 0.5rem 0.9rem;
        font-size: 0.8rem;
    }

    #privacy-glass-wrapper .privacy-section {
        padding: 1.5rem;
    }

    #privacy-glass-wrapper .privacy-section .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    #privacy-glass-wrapper .privacy-section .section-icon {
        width: 42px;
        height: 42px;
        font-size: 1.15rem;
    }

    #privacy-glass-wrapper .privacy-section .section-header h2 {
        font-size: 1.2rem;
    }

    #privacy-glass-wrapper .rights-grid {
        grid-template-columns: 1fr;
    }

    #privacy-glass-wrapper .data-table th,
    #privacy-glass-wrapper .data-table td {
        padding: 0.75rem;
        font-size: 0.85rem;
    }

    #privacy-glass-wrapper .privacy-cta {
        padding: 2rem 1.5rem;
    }

    #privacy-glass-wrapper .privacy-cta h2 {
        font-size: 1.35rem;
        flex-direction: column;
        gap: 0.5rem;
    }

    @keyframes privacyGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Touch Targets */
#privacy-glass-wrapper .privacy-nav-btn,
#privacy-glass-wrapper .privacy-cta-btn {
    min-height: 44px;
}

@media (max-width: 768px) {
    #privacy-glass-wrapper .privacy-nav-btn,
    #privacy-glass-wrapper .privacy-cta-btn {
        min-height: 48px;
    }
}

/* Button Press States */
#privacy-glass-wrapper .privacy-nav-btn:active,
#privacy-glass-wrapper .privacy-cta-btn:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Focus Visible */
#privacy-glass-wrapper .privacy-nav-btn:focus-visible,
#privacy-glass-wrapper .privacy-cta-btn:focus-visible,
#privacy-glass-wrapper .back-link:focus-visible {
    outline: 3px solid rgba(99, 102, 241, 0.5);
    outline-offset: 2px;
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    [data-theme="light"] #privacy-glass-wrapper .privacy-section,
    [data-theme="light"] #privacy-glass-wrapper .privacy-cta {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] #privacy-glass-wrapper .privacy-section,
    [data-theme="dark"] #privacy-glass-wrapper .privacy-cta {
        background: rgba(30, 41, 59, 0.95);
    }
}
</style>

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

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
