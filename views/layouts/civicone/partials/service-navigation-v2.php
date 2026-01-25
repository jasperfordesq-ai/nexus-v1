<?php
/**
 * CivicOne Service Navigation v2.1 - Pure GOV.UK Compliance
 *
 * SOURCE: https://github.com/alphagov/govuk-frontend/tree/main/packages/govuk-frontend/src/govuk/components/service-navigation
 *
 * Account/auth links are in the service navigation (GOV.UK pattern).
 * No separate utility bar.
 */

use Nexus\Helpers\NavigationConfig;
use Nexus\Core\TenantContext;
use Nexus\Core\Auth;

// Get navigation data
$primaryNav = NavigationConfig::getPrimaryNav();
$secondaryNav = NavigationConfig::getSecondaryNav();

// Get base path and current URL
$basePath = TenantContext::getBasePath();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Service name
$serviceName = TenantContext::getSetting('site_name') ?? 'Community';

// Auth state
$user = Auth::user();
$isLoggedIn = !empty($user);

/**
 * Check if a navigation item is active
 */
function isNavItemActive(string $itemUrl, string $currentPath, string $basePath): bool
{
    $fullUrl = $basePath . $itemUrl;

    // Exact match for home
    if ($itemUrl === '/' && $currentPath === $basePath . '/') {
        return true;
    }

    // Prefix match for other pages
    if ($itemUrl !== '/' && strpos($currentPath, $fullUrl) === 0) {
        return true;
    }

    return false;
}
?>
<section aria-label="Service information" class="govuk-service-navigation" data-module="govuk-service-navigation">
    <div class="govuk-width-container">
        <div class="govuk-service-navigation__container">

            <!-- Service Name -->
            <span class="govuk-service-navigation__service-name">
                <a href="<?= htmlspecialchars($basePath) ?>/" class="govuk-service-navigation__link">
                    <?= htmlspecialchars($serviceName) ?>
                </a>
            </span>

            <!-- Navigation -->
            <nav aria-label="Menu" class="govuk-service-navigation__wrapper">

                <!-- Mobile Toggle Button -->
                <button type="button"
                        class="govuk-service-navigation__toggle govuk-js-service-navigation-toggle"
                        aria-controls="service-navigation-list"
                        hidden
                        aria-hidden="true">
                    Menu
                </button>

                <!-- Navigation List -->
                <ul class="govuk-service-navigation__list" id="service-navigation-list">
                    <?php foreach ($primaryNav as $item):
                        $isActive = isNavItemActive($item['url'], $currentPath, $basePath);
                        $itemClass = 'govuk-service-navigation__item';
                        if ($isActive) {
                            $itemClass .= ' govuk-service-navigation__item--active';
                        }
                    ?>
                    <li class="<?= $itemClass ?>">
                        <a class="govuk-service-navigation__link"
                           href="<?= htmlspecialchars($basePath . $item['url']) ?>"
                           <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <?php if ($isActive): ?>
                                <strong class="govuk-service-navigation__active-fallback"><?= htmlspecialchars($item['label']) ?></strong>
                            <?php else: ?>
                                <?= htmlspecialchars($item['label']) ?>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>

                    <!-- "More" Dropdown -->
                    <li class="govuk-service-navigation__item civicone-nav-more">
                        <button type="button"
                                class="civicone-nav-more__btn"
                                aria-expanded="false"
                                aria-controls="more-nav-panel"
                                aria-haspopup="true">
                            More
                            <svg class="civicone-chevron" width="12" height="8" viewBox="0 0 12 8" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M1.41 0L6 4.58 10.59 0 12 1.41l-6 6-6-6z"/>
                            </svg>
                        </button>

                        <div class="civicone-nav-dropdown" id="more-nav-panel" hidden>
                            <div class="civicone-nav-dropdown__grid">
                                <?php foreach ($secondaryNav as $sectionKey => $section):
                                    if (empty($section['items'])) continue;
                                ?>
                                <div class="civicone-nav-dropdown__section">
                                    <h3><?= htmlspecialchars($section['title']) ?></h3>
                                    <ul class="civicone-nav-dropdown__list">
                                        <?php foreach ($section['items'] as $item):
                                            $isActive = isNavItemActive($item['url'], $currentPath, $basePath);
                                        ?>
                                        <li>
                                            <a class="civicone-nav-dropdown__link"
                                               href="<?= htmlspecialchars($basePath . $item['url']) ?>"
                                               <?= $isActive ? 'aria-current="page"' : '' ?>>
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

                    <!-- Account Links (right-aligned via CSS) -->
                    <?php if ($isLoggedIn): ?>
                    <li class="govuk-service-navigation__item govuk-service-navigation__item--right">
                        <a class="govuk-service-navigation__link"
                           href="<?= htmlspecialchars($basePath) ?>/dashboard">
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
                           href="<?= htmlspecialchars($basePath) ?>/login">
                            Sign in
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</section>
