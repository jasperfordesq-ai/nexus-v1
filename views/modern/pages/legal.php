<?php
/**
 * Legal & Info Hub - Modern Layout
 * Full navigation with header/footer
 */

// Hero variables for header (passed from controller or defaults)
$hTitle = $hero_title ?? 'Legal & Info';
$hSubtitle = $hero_subtitle ?? 'Privacy, Terms, and Platform Information';
$hGradient = $hero_gradient ?? 'htb-hero-gradient-hub';
$hType = $hero_type ?? 'Legal';

// Page title for SEO (set in controller, but ensure it's set)
if (class_exists('\Nexus\Core\SEO')) {
    \Nexus\Core\SEO::setTitle($hTitle);
}

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Get tenant info
$tenantFooter = '';
$tenantName = 'This Community';
$tenantLogo = '';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
    $tenantLogo = $t['logo'] ?? '';
    if (!empty($t['configuration'])) {
        $tConfig = json_decode($t['configuration'], true);
        if (!empty($tConfig['footer_text'])) {
            $tenantFooter = $tConfig['footer_text'];
        }
    }
}
?>

<style>
/* ========== Legal Hub - Modern Interface Design ========== */
:root {
    --legal-bg: #f8fafc;
    --legal-card-bg: #ffffff;
    --legal-border: #e2e8f0;
    --legal-title: #0f172a;
    --legal-text: #334155;
    --legal-muted: #64748b;
    --legal-link: #6366f1;
    --legal-link-hover: #4f46e5;
    --legal-section-bg: #f1f5f9;
    --legal-accent: #6366f1;
    --legal-success: #10b981;
    --legal-warning: #f59e0b;
    --legal-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
    --legal-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] {
    --legal-bg: #0f172a;
    --legal-card-bg: #1e293b;
    --legal-border: #334155;
    --legal-title: #f1f5f9;
    --legal-text: #cbd5e1;
    --legal-muted: #94a3b8;
    --legal-link: #818cf8;
    --legal-link-hover: #a5b4fc;
    --legal-section-bg: #334155;
    --legal-shadow: 0 1px 3px rgba(0, 0, 0, 0.3), 0 1px 2px rgba(0, 0, 0, 0.2);
    --legal-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
}

@media (prefers-color-scheme: dark) {
    body:not([data-theme="light"]) {
        --legal-bg: #0f172a;
        --legal-card-bg: #1e293b;
        --legal-border: #334155;
        --legal-title: #f1f5f9;
        --legal-text: #cbd5e1;
        --legal-muted: #94a3b8;
        --legal-link: #818cf8;
        --legal-link-hover: #a5b4fc;
        --legal-section-bg: #334155;
    }
}

/* Page Container */
.legal-hub {
    background: var(--legal-bg);
    min-height: 60vh;
    padding-top: 160px;
    padding-bottom: 60px;
    position: relative;
    z-index: 20;
}

@media (max-width: 900px) {
    .legal-hub {
        padding-top: 120px;
    }
}

/* Main Layout */
.legal-hub-layout {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px 24px;
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 32px;
}

/* Sidebar */
.legal-hub-sidebar {
    position: sticky;
    top: 120px;
    height: fit-content;
}

/* Platform Card */
.legal-platform-card {
    background: var(--legal-card-bg);
    border-radius: 20px;
    border: 1px solid var(--legal-border);
    box-shadow: var(--legal-shadow);
    padding: 28px;
    text-align: center;
    margin-bottom: 20px;
}

.legal-platform-logo {
    width: 72px;
    height: 72px;
    margin: 0 auto 16px;
    border-radius: 18px;
    background: linear-gradient(135deg, var(--legal-accent), #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: #fff;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.25);
}

.legal-platform-logo img {
    width: 44px;
    height: 44px;
    object-fit: contain;
    filter: brightness(0) invert(1);
}

.legal-platform-name {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--legal-title);
    margin-bottom: 4px;
    letter-spacing: -0.025em;
}

.legal-platform-tagline {
    font-size: 0.9rem;
    color: var(--legal-muted);
    margin-bottom: 20px;
}

.legal-tenant-message {
    background: var(--legal-section-bg);
    border-radius: 12px;
    padding: 16px;
    font-size: 0.9rem;
    color: var(--legal-text);
    line-height: 1.5;
    white-space: pre-line;
    text-align: left;
    margin-bottom: 20px;
}

.legal-copyright {
    font-size: 0.85rem;
    color: var(--legal-muted);
    line-height: 1.5;
}

.legal-copyright a {
    color: var(--legal-link);
    text-decoration: none;
    font-weight: 600;
}

/* Contact Card */
.legal-contact-card {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 20px;
    padding: 28px;
    text-align: center;
    color: #fff;
    position: relative;
    overflow: hidden;
}

.legal-contact-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.legal-contact-card h3 {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 8px 0;
    position: relative;
}

.legal-contact-card p {
    font-size: 0.95rem;
    opacity: 0.9;
    margin: 0 0 20px 0;
    position: relative;
}

.legal-contact-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: #fff;
    color: #6366f1;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.95rem;
    text-decoration: none;
    transition: all 0.2s ease;
    position: relative;
    width: 100%;
}

.legal-contact-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

/* Main Content */
.legal-hub-content {
    min-width: 0;
}

/* Section Header */
.legal-section-header {
    margin-bottom: 20px;
}

.legal-section-header h2 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--legal-title);
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.legal-section-header h2 i {
    color: var(--legal-accent);
}

.legal-section-header p {
    font-size: 0.9rem;
    color: var(--legal-muted);
    margin: 0;
}

/* Navigation Cards Grid */
.legal-nav-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}

.legal-nav-card {
    background: var(--legal-card-bg);
    border-radius: 16px;
    border: 1px solid var(--legal-border);
    box-shadow: var(--legal-shadow);
    padding: 24px;
    text-decoration: none;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    transition: all 0.2s ease;
}

.legal-nav-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--legal-shadow-lg);
    border-color: var(--legal-accent);
}

.legal-nav-card:active {
    transform: scale(0.98);
}

.legal-nav-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    color: #fff;
    flex-shrink: 0;
}

.legal-nav-icon.privacy { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.legal-nav-icon.terms { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.legal-nav-icon.accessibility { background: linear-gradient(135deg, #10b981, #059669); }
.legal-nav-icon.cookies { background: linear-gradient(135deg, #f59e0b, #d97706); }

.legal-nav-text h3 {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--legal-title);
    margin: 0 0 6px 0;
}

.legal-nav-text p {
    font-size: 0.9rem;
    color: var(--legal-muted);
    margin: 0;
    line-height: 1.5;
}

.legal-nav-arrow {
    margin-left: auto;
    color: var(--legal-muted);
    font-size: 0.9rem;
    align-self: center;
    transition: transform 0.2s ease;
}

.legal-nav-card:hover .legal-nav-arrow {
    transform: translateX(4px);
    color: var(--legal-accent);
}

/* Quick Links Section */
.legal-quick-section {
    margin-bottom: 32px;
}

.legal-quick-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.legal-quick-card {
    background: var(--legal-card-bg);
    border: 1px solid var(--legal-border);
    border-radius: 14px;
    padding: 20px 16px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    text-align: center;
    transition: all 0.2s ease;
}

.legal-quick-card:hover {
    background: var(--legal-section-bg);
    border-color: var(--legal-accent);
    transform: translateY(-2px);
}

.legal-quick-card i {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--legal-section-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: var(--legal-accent);
    transition: all 0.2s ease;
}

.legal-quick-card:hover i {
    background: var(--legal-accent);
    color: #fff;
}

.legal-quick-card span {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--legal-title);
}

/* Community Guidelines Card */
.legal-guidelines-card {
    background: var(--legal-card-bg);
    border-radius: 20px;
    border: 1px solid var(--legal-border);
    box-shadow: var(--legal-shadow);
    overflow: hidden;
}

.legal-guidelines-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px;
    background: var(--legal-section-bg);
    border-bottom: 1px solid var(--legal-border);
}

.legal-guidelines-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #ec4899, #be185d);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #fff;
}

.legal-guidelines-header h3 {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--legal-title);
    margin: 0;
}

.legal-guidelines-header p {
    font-size: 0.9rem;
    color: var(--legal-muted);
    margin: 4px 0 0 0;
}

.legal-guidelines-body {
    padding: 24px;
}

.legal-guideline {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 20px;
}

.legal-guideline:last-child {
    margin-bottom: 0;
}

.legal-guideline-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--legal-section-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    color: var(--legal-accent);
    flex-shrink: 0;
}

.legal-guideline h4 {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--legal-title);
    margin: 0 0 4px 0;
}

.legal-guideline p {
    font-size: 0.9rem;
    color: var(--legal-text);
    margin: 0;
    line-height: 1.5;
}

/* ========== Mobile Styles ========== */
@media (max-width: 900px) {
    .legal-hub {
        margin-top: 0;
    }

    .legal-hub-layout {
        grid-template-columns: 1fr;
        padding: 16px;
        gap: 20px;
    }

    .legal-hub-sidebar {
        position: static;
        order: 2;
    }

    .legal-hub-content {
        order: 1;
    }

    /* Mobile Nav Grid */
    .legal-nav-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .legal-nav-card {
        padding: 20px;
    }

    .legal-nav-icon {
        width: 48px;
        height: 48px;
        font-size: 1.2rem;
    }

    .legal-nav-text h3 {
        font-size: 1rem;
    }

    /* Mobile Quick Grid */
    .legal-quick-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .legal-quick-card {
        padding: 16px 12px;
    }

    .legal-quick-card i {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }

    .legal-quick-card span {
        font-size: 0.8rem;
    }

    /* Mobile Platform Card */
    .legal-platform-card {
        padding: 24px;
    }

    .legal-platform-logo {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }

    .legal-platform-name {
        font-size: 1.1rem;
    }

    /* Mobile Guidelines */
    .legal-guidelines-header {
        padding: 18px 20px;
    }

    .legal-guidelines-icon {
        width: 42px;
        height: 42px;
        font-size: 1.1rem;
    }

    .legal-guidelines-body {
        padding: 20px;
    }

    .legal-guideline {
        margin-bottom: 16px;
    }

    .legal-guideline-icon {
        width: 32px;
        height: 32px;
        font-size: 0.85rem;
    }

    /* Mobile Contact Card */
    .legal-contact-card {
        padding: 24px;
    }
}

/* Small mobile */
@media (max-width: 400px) {
    .legal-quick-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
    .legal-nav-card,
    .legal-quick-card,
    .legal-contact-btn,
    .legal-nav-arrow {
        transition: none;
    }
}

/* Focus States */
.legal-nav-card:focus-visible,
.legal-quick-card:focus-visible,
.legal-contact-btn:focus-visible {
    outline: 2px solid var(--legal-accent);
    outline-offset: 2px;
}
</style>

<div class="legal-hub">
    <div class="legal-hub-layout">
        <!-- Sidebar -->
        <aside class="legal-hub-sidebar">
            <!-- Platform Card -->
            <div class="legal-platform-card">
                <div class="legal-platform-logo">
                    <?php if ($tenantLogo): ?>
                        <img src="<?= htmlspecialchars($tenantLogo) ?>" loading="lazy" alt="<?= htmlspecialchars($tenantName) ?> logo">
                    <?php else: ?>
                        <i class="fa-solid fa-globe"></i>
                    <?php endif; ?>
                </div>
                <div class="legal-platform-name"><?= htmlspecialchars($tenantName) ?></div>
                <div class="legal-platform-tagline">Building Community Through Time Exchange</div>

                <?php if ($tenantFooter): ?>
                    <div class="legal-tenant-message"><?= htmlspecialchars($tenantFooter) ?></div>
                <?php endif; ?>

                <p class="legal-copyright">
                    Platform by <a href="https://project-nexus.ie" target="_blank" rel="noopener">Project NEXUS</a><br>
                    &copy; <?= date('Y') ?> All rights reserved.
                </p>
            </div>

            <!-- Contact Card -->
            <div class="legal-contact-card">
                <h3>Need Help?</h3>
                <p>Questions about our policies? We're here to help.</p>
                <a href="<?= $basePath ?>/contact" class="legal-contact-btn">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Contact Us</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="legal-hub-content">
            <!-- Legal Documents Section -->
            <div class="legal-section-header">
                <h2><i class="fa-solid fa-scale-balanced"></i> Legal Documents</h2>
                <p>Our policies and terms of use</p>
            </div>

            <div class="legal-nav-grid">
                <!-- Privacy Policy -->
                <a href="<?= $basePath ?>/privacy" class="legal-nav-card">
                    <div class="legal-nav-icon privacy">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="legal-nav-text">
                        <h3>Privacy Policy</h3>
                        <p>How we collect, use, and protect your personal data</p>
                    </div>
                    <i class="fa-solid fa-chevron-right legal-nav-arrow"></i>
                </a>

                <!-- Terms of Service -->
                <a href="<?= $basePath ?>/terms" class="legal-nav-card">
                    <div class="legal-nav-icon terms">
                        <i class="fa-solid fa-file-contract"></i>
                    </div>
                    <div class="legal-nav-text">
                        <h3>Terms of Service</h3>
                        <p>Rules and conditions for using our platform</p>
                    </div>
                    <i class="fa-solid fa-chevron-right legal-nav-arrow"></i>
                </a>

                <!-- Accessibility -->
                <a href="<?= $basePath ?>/accessibility" class="legal-nav-card">
                    <div class="legal-nav-icon accessibility">
                        <i class="fa-solid fa-universal-access"></i>
                    </div>
                    <div class="legal-nav-text">
                        <h3>Accessibility</h3>
                        <p>Our commitment to digital accessibility for all</p>
                    </div>
                    <i class="fa-solid fa-chevron-right legal-nav-arrow"></i>
                </a>

                <!-- Cookie Policy -->
                <a href="<?= $basePath ?>/privacy#cookies" class="legal-nav-card">
                    <div class="legal-nav-icon cookies">
                        <i class="fa-solid fa-cookie-bite"></i>
                    </div>
                    <div class="legal-nav-text">
                        <h3>Cookie Policy</h3>
                        <p>How we use cookies and tracking technologies</p>
                    </div>
                    <i class="fa-solid fa-chevron-right legal-nav-arrow"></i>
                </a>
            </div>

            <!-- Quick Links Section -->
            <div class="legal-quick-section">
                <div class="legal-section-header">
                    <h2><i class="fa-solid fa-link"></i> Quick Links</h2>
                    <p>Helpful resources and information</p>
                </div>

                <div class="legal-quick-grid">
                    <a href="<?= $basePath ?>/faq" class="legal-quick-card">
                        <i class="fa-solid fa-circle-question"></i>
                        <span>FAQ</span>
                    </a>
                    <a href="<?= $basePath ?>/help" class="legal-quick-card">
                        <i class="fa-solid fa-life-ring"></i>
                        <span>Help Center</span>
                    </a>
                    <a href="<?= $basePath ?>/about" class="legal-quick-card">
                        <i class="fa-solid fa-info-circle"></i>
                        <span>About Us</span>
                    </a>
                    <a href="<?= $basePath ?>/contact" class="legal-quick-card">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Contact</span>
                    </a>
                </div>
            </div>

            <!-- Community Guidelines -->
            <div class="legal-guidelines-card">
                <div class="legal-guidelines-header">
                    <div class="legal-guidelines-icon">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                    <div>
                        <h3>Community Guidelines</h3>
                        <p>How we treat each other</p>
                    </div>
                </div>
                <div class="legal-guidelines-body">
                    <div class="legal-guideline">
                        <div class="legal-guideline-icon">
                            <i class="fa-solid fa-handshake"></i>
                        </div>
                        <div>
                            <h4>Be Kind & Respectful</h4>
                            <p>Treat all community members with respect. Harassment and discrimination are not tolerated.</p>
                        </div>
                    </div>

                    <div class="legal-guideline">
                        <div class="legal-guideline-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div>
                            <h4>Be Reliable</h4>
                            <p>Honor your commitments. If plans change, communicate promptly with the other party.</p>
                        </div>
                    </div>

                    <div class="legal-guideline">
                        <div class="legal-guideline-icon">
                            <i class="fa-solid fa-shield-alt"></i>
                        </div>
                        <div>
                            <h4>Be Safe</h4>
                            <p>Meet in public places for first exchanges. Trust your instincts and report concerns.</p>
                        </div>
                    </div>

                    <div class="legal-guideline">
                        <div class="legal-guideline-icon">
                            <i class="fa-solid fa-globe"></i>
                        </div>
                        <div>
                            <h4>Be Inclusive</h4>
                            <p>Our community welcomes everyone. Celebrate diversity and learn from each other.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
(function() {
    // Haptic feedback on card taps (mobile)
    document.querySelectorAll('.legal-nav-card, .legal-quick-card').forEach(card => {
        card.addEventListener('click', function() {
            if (window.NexusMobile?.haptic) {
                window.NexusMobile.haptic('light');
            }
        });
    });
})();
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
