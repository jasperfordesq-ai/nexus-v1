<?php
/**
 * CivicOne Site Footer - GOV.UK Footer Pattern
 * Based on GOV.UK Design System but adapted for non-government use
 * https://design-system.service.gov.uk/components/footer/
 */

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

<footer class="govuk-footer" role="contentinfo">
    <div class="govuk-width-container">

        <!-- Footer Navigation -->
        <div class="govuk-footer__navigation">

            <!-- Explore Section -->
            <div class="govuk-footer__section govuk-grid-column-one-quarter">
                <h2 class="govuk-footer__heading govuk-heading-s">Explore</h2>
                <ul class="govuk-footer__list">
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/listings">Offers &amp; Requests</a>
                    </li>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/members">Community</a>
                    </li>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/groups">Groups</a>
                    </li>
                    <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/events">Events</a>
                    </li>
                    <?php endif; ?>
                    <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/volunteering">Volunteering</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- About Section -->
            <div class="govuk-footer__section govuk-grid-column-one-quarter">
                <h2 class="govuk-footer__heading govuk-heading-s">About</h2>
                <ul class="govuk-footer__list">
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/how-it-works">How it works</a>
                    </li>
                    <?php if ($isHourTimebank): ?>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/our-story">Our story</a>
                    </li>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/faq">FAQ</a>
                    </li>
                    <?php endif; ?>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/contact">Contact us</a>
                    </li>
                </ul>
            </div>

            <!-- Support Section -->
            <div class="govuk-footer__section govuk-grid-column-one-quarter">
                <h2 class="govuk-footer__heading govuk-heading-s">Support</h2>
                <ul class="govuk-footer__list">
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/help">Help centre</a>
                    </li>
                    <?php if ($isHourTimebank): ?>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/timebanking-guide">Timebanking guide</a>
                    </li>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/partner">Partner with us</a>
                    </li>
                    <?php endif; ?>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/accessibility">Accessibility</a>
                    </li>
                </ul>
            </div>

            <!-- Connect Section -->
            <div class="govuk-footer__section govuk-grid-column-one-quarter">
                <h2 class="govuk-footer__heading govuk-heading-s">Connect</h2>
                <p class="govuk-body-s" style="color: #b1b4b6;">
                    <?php if ($tenantFooter): ?>
                        <?= htmlspecialchars($tenantFooter) ?>
                    <?php else: ?>
                        Building stronger communities through time exchange.
                    <?php endif; ?>
                </p>
                <ul class="govuk-footer__list govuk-footer__list--inline">
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="#" aria-label="Facebook">
                            <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                    </li>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="#" aria-label="X (Twitter)">
                            <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </a>
                    </li>
                    <li class="govuk-footer__list-item">
                        <a class="govuk-footer__link" href="#" aria-label="LinkedIn">
                            <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                    </li>
                </ul>
            </div>

        </div>

        <hr class="govuk-footer__section-break">

        <!-- Footer Meta -->
        <div class="govuk-footer__meta">
            <div class="govuk-footer__meta-item govuk-footer__meta-item--grow">
                <h2 class="govuk-visually-hidden">Support links</h2>
                <ul class="govuk-footer__inline-list">
                    <li class="govuk-footer__inline-list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/privacy">Privacy policy</a>
                    </li>
                    <li class="govuk-footer__inline-list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/terms">Terms of service</a>
                    </li>
                    <li class="govuk-footer__inline-list-item">
                        <a class="govuk-footer__link" href="<?= $basePath ?>/accessibility">Accessibility statement</a>
                    </li>
                </ul>
            </div>
            <div class="govuk-footer__meta-item">
                <p class="govuk-body-s" style="color: #b1b4b6; margin-bottom: 0;">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($tenantName) ?>. Built on
                    <a class="govuk-footer__link" href="https://project-nexus.ie" target="_blank" rel="noopener">Nexus Platform</a>.
                </p>
            </div>
        </div>

    </div>
</footer>

<!-- Mobile Bottom Navigation -->
<?php require __DIR__ . '/mobile-nav-v2.php'; ?>

<!-- Mobile Bottom Sheets -->
<?php require __DIR__ . '/../../../civicone/partials/mobile-sheets.php'; ?>
