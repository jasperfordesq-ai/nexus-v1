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
$tSlug = class_exists('\Nexus\Core\TenantContext') ? (\Nexus\Core\TenantContext::get()['slug'] ?? '') : '';
$isHourTimebank = ($tSlug === 'hour-timebank' || $tSlug === 'hour_timebank');

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
                    <?php if ($isHourTimebank): ?>
                    <a href="<?= $basePath ?>/faq" class="legal-quick-card">
                        <i class="fa-solid fa-circle-question"></i>
                        <span>FAQ</span>
                    </a>
                    <?php endif; ?>
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
