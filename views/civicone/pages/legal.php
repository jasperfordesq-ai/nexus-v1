<?php
/**
 * Legal & Info Hub - CivicOne Mobile-First Layout
 * Full mobile native experience with all footer information
 */

// Hero variables for header
$hTitle = $hero_title ?? 'Legal & Privacy';
$hSubtitle = $hero_subtitle ?? 'Privacy, Terms, and Platform Information';
$hType = $hero_type ?? 'Legal';

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
$isHourTimebank = ($tSlug === 'hour-timebank' || $tSlug === 'hour_timebank');

// Get tenant info
$tenantFooter = '';
$tenantName = 'This Community';
if (class_exists('Nexus\Core\TenantContext')) {
    $t = Nexus\Core\TenantContext::get();
    $tenantName = $t['name'] ?? 'This Community';
    if (!empty($t['configuration'])) {
        $tConfig = json_decode($t['configuration'], true);
        if (!empty($tConfig['footer_text'])) {
            $tenantFooter = $tConfig['footer_text'];
        }
    }
}
?>

<style>
/* Legal Hub - CivicOne Native Mobile Design */
.civic-legal-hub {
    padding: 0 16px 100px;
    max-width: 800px;
    margin: 0 auto;
}

/* Section Headers */
.civic-legal-section {
    margin-bottom: 24px;
}

.civic-legal-section-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--civic-text-main, #111827);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.civic-legal-section-title .dashicons {
    color: var(--civic-brand);
}

/* Legal Cards */
.civic-legal-cards {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.civic-legal-card {
    background: var(--civic-bg-card, #ffffff);
    border: 1px solid var(--civic-border, #e5e7eb);
    border-radius: 12px;
    padding: 16px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.15s ease;
    min-height: 48px;
}

.civic-legal-card:hover,
.civic-legal-card:active {
    background: var(--civic-bg-page, #f9fafb);
    border-color: var(--civic-brand);
}

.civic-legal-card-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: white;
}

.civic-legal-card-icon.privacy { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.civic-legal-card-icon.terms { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.civic-legal-card-icon.accessibility { background: linear-gradient(135deg, #10b981, #059669); }
.civic-legal-card-icon.cookies { background: linear-gradient(135deg, #f59e0b, #d97706); }
.civic-legal-card-icon.help { background: linear-gradient(135deg, #ec4899, #be185d); }
.civic-legal-card-icon.contact { background: linear-gradient(135deg, #06b6d4, #0891b2); }
.civic-legal-card-icon.faq { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.civic-legal-card-icon.about { background: linear-gradient(135deg, #f97316, #ea580c); }

.civic-legal-card-icon .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.civic-legal-card-text {
    flex: 1;
    min-width: 0;
}

.civic-legal-card-text h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--civic-text-main, #111827);
    margin: 0 0 2px 0;
}

.civic-legal-card-text p {
    font-size: 0.85rem;
    color: var(--civic-text-muted, #6b7280);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.civic-legal-card-arrow {
    color: var(--civic-text-muted, #6b7280);
    flex-shrink: 0;
}

/* Platform Info Card */
.civic-legal-platform {
    background: var(--civic-bg-card, #ffffff);
    border: 1px solid var(--civic-border, #e5e7eb);
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    margin-bottom: 24px;
}

.civic-legal-platform-icon {
    width: 64px;
    height: 64px;
    background: var(--civic-brand);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: white;
}

.civic-legal-platform-icon .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
}

.civic-legal-platform-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--civic-text-main, #111827);
    margin: 0 0 4px 0;
}

.civic-legal-platform-tagline {
    font-size: 0.9rem;
    color: var(--civic-text-muted, #6b7280);
    margin: 0 0 16px 0;
}

.civic-legal-platform-footer {
    font-size: 0.85rem;
    color: var(--civic-text-muted, #6b7280);
    border-top: 1px solid var(--civic-border, #e5e7eb);
    padding-top: 16px;
    margin-top: 16px;
}

.civic-legal-platform-footer a {
    color: var(--civic-brand);
    text-decoration: none;
    font-weight: 600;
}

/* Community Guidelines */
.civic-legal-guidelines {
    background: var(--civic-bg-card, #ffffff);
    border: 1px solid var(--civic-border, #e5e7eb);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 24px;
}

.civic-legal-guidelines-header {
    background: var(--civic-bg-page, #f9fafb);
    padding: 16px;
    border-bottom: 1px solid var(--civic-border, #e5e7eb);
    display: flex;
    align-items: center;
    gap: 12px;
}

.civic-legal-guidelines-header .dashicons {
    color: var(--civic-brand);
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.civic-legal-guidelines-header h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--civic-text-main, #111827);
    margin: 0;
}

.civic-legal-guidelines-body {
    padding: 16px;
}

.civic-legal-guideline {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
}

.civic-legal-guideline:last-child {
    margin-bottom: 0;
}

.civic-legal-guideline-icon {
    width: 32px;
    height: 32px;
    background: var(--civic-bg-page, #f3f4f6);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.civic-legal-guideline-icon .dashicons {
    color: var(--civic-brand);
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.civic-legal-guideline h4 {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--civic-text-main, #111827);
    margin: 0 0 2px 0;
}

.civic-legal-guideline p {
    font-size: 0.85rem;
    color: var(--civic-text-muted, #6b7280);
    margin: 0;
    line-height: 1.4;
}

/* Social Links */
.civic-legal-social {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 16px;
}

.civic-legal-social a {
    width: 44px;
    height: 44px;
    background: var(--civic-bg-page, #f3f4f6);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--civic-text-muted, #6b7280);
    text-decoration: none;
    transition: all 0.15s ease;
}

.civic-legal-social a:hover {
    background: var(--civic-brand);
    color: white;
}

/* Dark Mode */
body.dark-mode .civic-legal-card,
body.dark-mode .civic-legal-platform,
body.dark-mode .civic-legal-guidelines {
    background: var(--civic-bg-card, #1f2937);
    border-color: var(--civic-border, #374151);
}

body.dark-mode .civic-legal-guidelines-header {
    background: var(--civic-bg-page, #111827);
}
</style>

<div class="civic-legal-hub">

    <!-- Platform Info -->
    <div class="civic-legal-platform">
        <div class="civic-legal-platform-icon">
            <span class="dashicons dashicons-groups" aria-hidden="true"></span>
        </div>
        <h2 class="civic-legal-platform-name"><?= htmlspecialchars($tenantName) ?></h2>
        <p class="civic-legal-platform-tagline">Building Community Through Time Exchange</p>

        <?php if ($tenantFooter): ?>
            <p style="font-size:0.9rem; color:var(--civic-text-secondary); margin-bottom:16px;">
                <?= htmlspecialchars($tenantFooter) ?>
            </p>
        <?php endif; ?>

        <div class="civic-legal-social" role="list" aria-label="Social media links">
            <a href="#" aria-label="Facebook" role="listitem">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
            </a>
            <a href="#" aria-label="X (Twitter)" role="listitem">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                </svg>
            </a>
            <a href="#" aria-label="LinkedIn" role="listitem">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                </svg>
            </a>
        </div>

        <div class="civic-legal-platform-footer">
            Platform by <a href="https://project-nexus.ie" target="_blank" rel="noopener">Project NEXUS</a><br>
            &copy; <?= date('Y') ?> All rights reserved.
        </div>
    </div>

    <!-- Legal Documents Section -->
    <section class="civic-legal-section">
        <h2 class="civic-legal-section-title">
            <span class="dashicons dashicons-shield" aria-hidden="true"></span>
            Legal Documents
        </h2>

        <div class="civic-legal-cards">
            <a href="<?= $basePath ?>/privacy" class="civic-legal-card">
                <div class="civic-legal-card-icon privacy">
                    <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                </div>
                <div class="civic-legal-card-text">
                    <h3>Privacy Policy</h3>
                    <p>How we protect your personal data</p>
                </div>
                <span class="dashicons dashicons-arrow-right-alt2 civic-legal-card-arrow" aria-hidden="true"></span>
            </a>

            <a href="<?= $basePath ?>/terms" class="civic-legal-card">
                <div class="civic-legal-card-icon terms">
                    <span class="dashicons dashicons-media-document" aria-hidden="true"></span>
                </div>
                <div class="civic-legal-card-text">
                    <h3>Terms of Service</h3>
                    <p>Rules for using our platform</p>
                </div>
                <span class="dashicons dashicons-arrow-right-alt2 civic-legal-card-arrow" aria-hidden="true"></span>
            </a>

            <a href="<?= $basePath ?>/accessibility" class="civic-legal-card">
                <div class="civic-legal-card-icon accessibility">
                    <span class="dashicons dashicons-universal-access" aria-hidden="true"></span>
                </div>
                <div class="civic-legal-card-text">
                    <h3>Accessibility</h3>
                    <p>Our commitment to digital accessibility</p>
                </div>
                <span class="dashicons dashicons-arrow-right-alt2 civic-legal-card-arrow" aria-hidden="true"></span>
            </a>

            <a href="<?= $basePath ?>/privacy#cookies" class="civic-legal-card">
                <div class="civic-legal-card-icon cookies">
                    <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                </div>
                <div class="civic-legal-card-text">
                    <h3>Cookie Policy</h3>
                    <p>How we use cookies and tracking</p>
                </div>
                <span class="dashicons dashicons-arrow-right-alt2 civic-legal-card-arrow" aria-hidden="true"></span>
            </a>
        </div>
    </section>

    <!-- Quick Links Section -->
    <section class="civic-legal-section">
        <h2 class="civic-legal-section-title">
            <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
            Quick Links
        </h2>

        <div class="civic-legal-cards">
            <a href="<?= $basePath ?>/help" class="civic-legal-card">
                <div class="civic-legal-card-icon help">
                    <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
                </div>
                <div class="civic-legal-card-text">
                    <h3>Help Center</h3>
                    <p>Get support and guidance</p>
                </div>
                <span class="dashicons dashicons-arrow-right-alt2 civic-legal-card-arrow" aria-hidden="true"></span>
            </a>

            <?php if ($isHourTimebank): ?>
            <a href="<?= $basePath ?>/faq" class="civic-legal-card">
                <div class="civic-legal-card-icon faq">
                    <span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
                </div>
                <div class="civic-legal-card-text">
                    <h3>FAQ</h3>
                    <p>Frequently asked questions</p>
                </div>
                <span class="dashicons dashicons-arrow-right-alt2 civic-legal-card-arrow" aria-hidden="true"></span>
            </a>

            <a href="<?= $basePath ?>/our-story" class="civic-legal-card">
                <div class="civic-legal-card-icon about">
                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                </div>
                <div class="civic-legal-card-text">
                    <h3>About Us</h3>
                    <p>Learn more about our community</p>
                </div>
                <span class="dashicons dashicons-arrow-right-alt2 civic-legal-card-arrow" aria-hidden="true"></span>
            </a>
            <?php endif; ?>

            <a href="<?= $basePath ?>/contact" class="civic-legal-card">
                <div class="civic-legal-card-icon contact">
                    <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                </div>
                <div class="civic-legal-card-text">
                    <h3>Contact Us</h3>
                    <p>Get in touch with our team</p>
                </div>
                <span class="dashicons dashicons-arrow-right-alt2 civic-legal-card-arrow" aria-hidden="true"></span>
            </a>
        </div>
    </section>

    <!-- Community Guidelines -->
    <section class="civic-legal-guidelines">
        <div class="civic-legal-guidelines-header">
            <span class="dashicons dashicons-heart" aria-hidden="true"></span>
            <h3>Community Guidelines</h3>
        </div>
        <div class="civic-legal-guidelines-body">
            <div class="civic-legal-guideline">
                <div class="civic-legal-guideline-icon">
                    <span class="dashicons dashicons-groups" aria-hidden="true"></span>
                </div>
                <div>
                    <h4>Be Kind & Respectful</h4>
                    <p>Treat all community members with respect. Harassment is not tolerated.</p>
                </div>
            </div>

            <div class="civic-legal-guideline">
                <div class="civic-legal-guideline-icon">
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                </div>
                <div>
                    <h4>Be Reliable</h4>
                    <p>Honor your commitments. Communicate promptly if plans change.</p>
                </div>
            </div>

            <div class="civic-legal-guideline">
                <div class="civic-legal-guideline-icon">
                    <span class="dashicons dashicons-shield" aria-hidden="true"></span>
                </div>
                <div>
                    <h4>Be Safe</h4>
                    <p>Meet in public places for first exchanges. Report concerns promptly.</p>
                </div>
            </div>

            <div class="civic-legal-guideline">
                <div class="civic-legal-guideline-icon">
                    <span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
                </div>
                <div>
                    <h4>Be Inclusive</h4>
                    <p>Our community welcomes everyone. Celebrate diversity.</p>
                </div>
            </div>
        </div>
    </section>

</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
