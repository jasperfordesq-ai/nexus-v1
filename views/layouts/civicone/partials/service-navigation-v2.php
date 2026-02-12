<?php
/**
 * CivicOne Service Navigation v2.2 - GOV.UK Design System Compliant
 *
 * SOURCE: https://github.com/alphagov/govuk-frontend/tree/main/packages/govuk-frontend/src/govuk/components/service-navigation
 *
 * WCAG 2.1 AA Compliance:
 * - 2.4.1 Bypass Blocks: Skip link handled in skip-link-and-banner.php
 * - 2.4.4 Link Purpose: Clear link text
 * - 2.4.7 Focus Visible: GOV.UK yellow focus ring
 * - 4.1.2 Name, Role, Value: Proper ARIA attributes
 *
 * Account/auth links are in the service navigation (GOV.UK pattern).
 * No separate utility bar needed.
 *
 * Updated: 2026-01-31
 */

use Nexus\Helpers\NavigationConfig;
use Nexus\Core\TenantContext;
use Nexus\Core\Auth;

// Get navigation data
// CivicOne uses a modified secondary nav that excludes gamification from primary navigation
// Gamification features (Leaderboard, Achievements) are accessible via user dashboard/profile
$primaryNav = NavigationConfig::getPrimaryNav();
$secondaryNav = NavigationConfig::getSecondaryNavCivicOne();

// Get base path and current URL
$basePath = TenantContext::getBasePath();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Service name
$serviceName = TenantContext::getSetting('site_name') ?? 'Community';

// Auth state - use $authUser to avoid overwriting view data (e.g., profile $user)
$authUser = Auth::user();
$isLoggedIn = !empty($authUser);

// Generate unique IDs for accessibility
$navId = 'service-navigation-' . uniqid();
$menuButtonId = 'menu-button-' . uniqid();

/**
 * Check if a navigation item is active
 * Returns 'page' for exact match, 'true' for section match, false otherwise
 */
function isNavItemActive(string $itemUrl, string $currentPath, string $basePath): string|false
{
    $fullUrl = $basePath . $itemUrl;

    // Exact match for home
    if ($itemUrl === '/' && $currentPath === $basePath . '/') {
        return 'page';
    }

    // Exact match
    if ($currentPath === $fullUrl || rtrim($currentPath, '/') === rtrim($fullUrl, '/')) {
        return 'page';
    }

    // Section/prefix match for other pages (e.g., /listings/123 matches /listings)
    if ($itemUrl !== '/' && strpos($currentPath, $fullUrl) === 0) {
        return 'true';
    }

    return false;
}
?>
<section aria-label="Service information" class="govuk-service-navigation" data-module="govuk-service-navigation">
    <div class="govuk-width-container">
        <div class="govuk-service-navigation__container">

            <!-- Service Name (Logo/Brand) -->
            <span class="govuk-service-navigation__service-name">
                <a href="<?= htmlspecialchars($basePath) ?>/" class="govuk-service-navigation__link">
                    <?= htmlspecialchars($serviceName) ?>
                </a>
            </span>

            <!-- Navigation -->
            <nav aria-label="Menu" class="govuk-service-navigation__wrapper">

                <!-- Mobile Toggle Button (JS-enhanced, hidden by default) -->
                <button type="button"
                        class="govuk-service-navigation__toggle govuk-js-service-navigation-toggle"
                        aria-controls="<?= $navId ?>"
                        id="<?= $menuButtonId ?>"
                        aria-expanded="false"
                        hidden>
                    Menu
                </button>

                <!-- Navigation List -->
                <ul class="govuk-service-navigation__list" id="<?= $navId ?>">
                    <?php foreach ($primaryNav as $item):
                        $activeState = isNavItemActive($item['url'], $currentPath, $basePath);
                        $itemClass = 'govuk-service-navigation__item';
                        if ($activeState) {
                            $itemClass .= ' govuk-service-navigation__item--active';
                        }
                    ?>
                    <li class="<?= $itemClass ?>">
                        <a class="govuk-service-navigation__link"
                           href="<?= htmlspecialchars($basePath . $item['url']) ?>"
                           <?= $activeState ? 'aria-current="' . $activeState . '"' : '' ?>>
                            <?php if ($activeState === 'page'): ?>
                                <strong class="govuk-service-navigation__active-fallback"><?= htmlspecialchars($item['label']) ?></strong>
                            <?php else: ?>
                                <?= htmlspecialchars($item['label']) ?>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>

                    <!-- "More" Dropdown for Secondary Navigation -->
                    <?php if (!empty($secondaryNav)):
                        $moreNavPanelId = 'more-nav-panel-' . uniqid();
                    ?>
                    <li class="govuk-service-navigation__item civicone-nav-more">
                        <button type="button"
                                class="civicone-nav-more__btn"
                                aria-expanded="false"
                                aria-controls="<?= $moreNavPanelId ?>"
                                aria-haspopup="menu">
                            More
                            <svg class="civicone-chevron" width="12" height="8" viewBox="0 0 12 8" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M1.41 0L6 4.58 10.59 0 12 1.41l-6 6-6-6z"/>
                            </svg>
                        </button>

                        <div class="civicone-nav-dropdown" id="<?= $moreNavPanelId ?>" role="menu" hidden>
                            <div class="civicone-nav-dropdown__grid">
                                <?php foreach ($secondaryNav as $sectionKey => $section):
                                    if (empty($section['items'])) continue;
                                ?>
                                <div class="civicone-nav-dropdown__section">
                                    <h3 id="nav-section-<?= htmlspecialchars($sectionKey) ?>"><?= htmlspecialchars($section['title']) ?></h3>
                                    <ul class="civicone-nav-dropdown__list" role="group" aria-labelledby="nav-section-<?= htmlspecialchars($sectionKey) ?>">
                                        <?php foreach ($section['items'] as $item):
                                            $activeState = isNavItemActive($item['url'], $currentPath, $basePath);
                                        ?>
                                        <li role="none">
                                            <a class="civicone-nav-dropdown__link"
                                               href="<?= htmlspecialchars($basePath . $item['url']) ?>"
                                               role="menuitem"
                                               <?= $activeState ? 'aria-current="' . $activeState . '"' : '' ?>>
                                                <?= htmlspecialchars($item['label']) ?>
                                            </a>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </li>
                    <?php endif; ?>

                    <!-- Try New Frontend Button -->
                    <li class="govuk-service-navigation__item govuk-service-navigation__item--right">
                        <?php
                        $reactSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
                        $reactPath = $reactSlug ? ('/' . $reactSlug . '/dashboard') : '/';
                        ?>
                        <a class="civicone-try-new-frontend"
                           href="https://app.project-nexus.ie<?= $reactPath ?>"
                           target="_blank"
                           rel="noopener">
                            <span class="btn-text">Try the New Experience</span>
                        </a>
                    </li>

                    <!-- Account Links (right-aligned via CSS) -->
                    <?php if ($isLoggedIn): ?>
                    <li class="govuk-service-navigation__item govuk-service-navigation__item--right">
                        <a class="govuk-service-navigation__link"
                           href="<?= htmlspecialchars($basePath) ?>/dashboard"
                           <?= strpos($currentPath, '/dashboard') !== false ? 'aria-current="true"' : '' ?>>
                            Account
                        </a>
                    </li>
                    <li class="govuk-service-navigation__item govuk-service-navigation__item--right">
                        <a class="govuk-service-navigation__link"
                           href="<?= htmlspecialchars($basePath) ?>/logout">
                            Sign out
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="govuk-service-navigation__item govuk-service-navigation__item--right">
                        <a class="govuk-service-navigation__link"
                           href="<?= htmlspecialchars($basePath) ?>/login"
                           <?= strpos($currentPath, '/login') !== false ? 'aria-current="page"' : '' ?>>
                            Sign in
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</section>

<!-- Service Navigation JS handled by civicone-header-v2.js (mobile toggle + More dropdown with keyboard nav) -->
