<!-- Footer styles now loaded from /assets/css/civicone-footer.min.css -->
<!-- Removed inline styles per CLAUDE.md guidelines (2026-01-20) -->

<footer class="civic-footer" role="contentinfo">
    <div class="civic-container">
        <?php
        $tenantFooter = '';
        $tenantName = 'Project NEXUS';
        $tenantLogo = '';
        if (class_exists('Nexus\Core\TenantContext')) {
            $t = Nexus\Core\TenantContext::get();
            $tenantName = $t['name'] ?? 'Project NEXUS';
            $tenantLogo = $t['logo_url'] ?? '';
            if (!empty($t['configuration'])) {
                $tConfig = json_decode($t['configuration'], true);
                if (!empty($tConfig['footer_text'])) {
                    $tenantFooter = $tConfig['footer_text'];
                }
            }
        }
        $basePath = Nexus\Core\TenantContext::getBasePath();
        $tSlug = $t['slug'] ?? '';
        $isHourTimebank = ($tSlug === 'hour-timebank' || $tSlug === 'hour_timebank');
        ?>

        <div class="civic-footer-grid">
            <!-- Brand Column -->
            <div class="civic-footer-brand">
                <span class="civic-footer-logo"><?= htmlspecialchars($tenantName) ?></span>
                <p class="civic-footer-tagline">
                    <?php if ($tenantFooter): ?>
                        <?= htmlspecialchars($tenantFooter) ?>
                    <?php else: ?>
                        Building stronger communities through time exchange. One hour equals one credit - everyone's time is valued equally.
                    <?php endif; ?>
                </p>
                <div class="civic-footer-social" role="list" aria-label="Social media links">
                    <a href="#" aria-label="Follow us on Facebook" title="Facebook" role="listitem">
                        <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                        </svg>
                    </a>
                    <a href="#" aria-label="Follow us on X (formerly Twitter)" title="X (Twitter)" role="listitem">
                        <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                        </svg>
                    </a>
                    <a href="#" aria-label="Follow us on LinkedIn" title="LinkedIn" role="listitem">
                        <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Explore Column -->
            <div class="civic-footer-column">
                <div class="civic-footer-column-heading">Explore</div>
                <ul>
                    <li><a href="<?= $basePath ?>/listings">Offers & Requests</a></li>
                    <li><a href="<?= $basePath ?>/members">Community</a></li>
                    <li><a href="<?= $basePath ?>/groups">Local Hubs</a></li>
                    <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                        <li><a href="<?= $basePath ?>/events">Events</a></li>
                    <?php endif; ?>
                    <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                        <li><a href="<?= $basePath ?>/volunteering">Volunteering</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- About Column -->
            <div class="civic-footer-column">
                <div class="civic-footer-column-heading">About</div>
                <ul>
                    <li><a href="<?= $basePath ?>/how-it-works">How It Works</a></li>
                    <?php if ($isHourTimebank): ?>
                        <li><a href="<?= $basePath ?>/our-story">Our Story</a></li>
                        <li><a href="<?= $basePath ?>/faq">FAQ</a></li>
                    <?php endif; ?>
                    <li><a href="<?= $basePath ?>/contact">Contact Us</a></li>
                </ul>
            </div>

            <!-- Support Column -->
            <div class="civic-footer-column">
                <div class="civic-footer-column-heading">Support</div>
                <ul>
                    <li><a href="<?= $basePath ?>/help">Help Center</a></li>
                    <?php if ($isHourTimebank): ?>
                        <li><a href="<?= $basePath ?>/timebanking-guide">Timebanking Guide</a></li>
                        <li><a href="<?= $basePath ?>/partner">Partner With Us</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="civic-footer-bottom">
            <p class="civic-footer-copyright">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($tenantName) ?>. Built on
                <a href="https://project-nexus.ie" target="_blank" rel="noopener noreferrer">Nexus TimeBank Platform</a>.
            </p>
            <div class="civic-footer-links">
                <a href="<?= $basePath ?>/privacy">Privacy Policy</a>
                <a href="<?= $basePath ?>/terms">Terms of Service</a>
                <a href="<?= $basePath ?>/cookie-preferences">Cookie Preferences</a>
                <a href="<?= $basePath ?>/legal/cookies">Cookies</a>
                <a href="<?= $basePath ?>/accessibility">Accessibility</a>
            </div>
        </div>
    </div>
</footer>

<!-- Mobile Bottom Navigation (Full-Screen Native Style) -->
<?php require __DIR__ . '/mobile-nav-v2.php'; ?>

<!-- Mobile Bottom Sheets (Comments, etc) -->
<?php require __DIR__ . '/../../../civicone/partials/mobile-sheets.php'; ?>
