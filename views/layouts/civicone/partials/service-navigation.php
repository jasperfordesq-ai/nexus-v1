<?php
/**
 * CivicOne Service Navigation - GOV.UK Pattern
 * Pattern: GOV.UK Service Navigation Component
 * https://design-system.service.gov.uk/components/service-navigation/
 *
 * MANDATORY: This is the ONE primary navigation system for CivicOne
 * See: docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md Section 9A
 *
 * Rules:
 * - Top-level sections only (max 5-7 items)
 * - NO calls-to-action (Create, Join, etc. belong in utility bar or page content)
 * - Active state marked with aria-current="page"
 * - Keyboard operable (Tab, Enter, Escape)
 * - Visible focus indicator (GOV.UK yellow)
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$isLoggedIn = isset($_SESSION['user_id']);

// Primary navigation items - Top-level sections only
$navItems = [
    ['label' => 'Feed', 'url' => '/', 'pattern' => '/'],
    ['label' => 'Members', 'url' => '/members', 'pattern' => '/members'],
    ['label' => 'Groups', 'url' => '/groups', 'pattern' => '/groups'],
    ['label' => 'Listings', 'url' => '/listings', 'pattern' => '/listings'],
];

// Conditionally add Volunteering if feature enabled
if (\Nexus\Core\TenantContext::hasFeature('volunteering')) {
    $navItems[] = ['label' => 'Volunteering', 'url' => '/volunteering', 'pattern' => '/volunteering'];
}

// Conditionally add Events if feature enabled
if (\Nexus\Core\TenantContext::hasFeature('events')) {
    $navItems[] = ['label' => 'Events', 'url' => '/events', 'pattern' => '/events'];
}

// Add database-driven pages (Page Builder)
$dbPagesMain = \Nexus\Core\MenuGenerator::getMenuPages('main');
foreach ($dbPagesMain as $mainPage) {
    $navItems[] = [
        'label' => $mainPage['title'],
        'url' => $mainPage['url'],
        'pattern' => parse_url($mainPage['url'], PHP_URL_PATH) ?? $mainPage['url']
    ];
}

// Helper: Check if nav item is active
function isNavItemActive($pattern, $currentPath, $basePath) {
    if ($pattern === '/') {
        // Home page - exact match only
        return ($currentPath === '/' || $currentPath === $basePath . '/' || $currentPath === $basePath);
    }

    // Remove basePath from currentPath for comparison
    $normalizedPath = $currentPath;
    if (!empty($basePath) && strpos($currentPath, $basePath) === 0) {
        $normalizedPath = substr($currentPath, strlen($basePath));
    }

    // Section match - starts with the pattern
    return $normalizedPath === $pattern || strpos($normalizedPath, $pattern . '/') === 0;
}
?>

<nav class="civicone-service-navigation" aria-label="Main navigation">
    <div class="civicone-service-navigation__container">

        <!-- Logo (Service Name) -->
        <div class="civicone-service-navigation__branding">
            <a href="<?= $basePath ?: '/' ?>" class="civicone-service-navigation__logo" aria-label="<?= htmlspecialchars(\Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS') ?> - Go to homepage">
                <span class="civicone-service-navigation__service-name">
                    <?php
                    $civicName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
                    if (\Nexus\Core\TenantContext::getId() == 1) {
                        $civicName = 'Project NEXUS';
                    }
                    echo htmlspecialchars($civicName);
                    ?>
                </span>
            </a>
        </div>

        <!-- Navigation list (desktop) -->
        <ul class="civicone-service-navigation__list">
            <?php foreach ($navItems as $item):
                $isActive = isNavItemActive($item['pattern'], $currentPath, $basePath);
                $activeClass = $isActive ? ' civicone-service-navigation__item--active' : '';
            ?>
                <li class="civicone-service-navigation__item<?= $activeClass ?>">
                    <a href="<?= $basePath ?><?= htmlspecialchars($item['url']) ?>"
                       class="civicone-service-navigation__link"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Mobile menu toggle button -->
        <button id="civicone-service-nav-toggle"
                class="civicone-service-navigation__toggle"
                aria-controls="civicone-service-navigation-list"
                aria-expanded="false"
                aria-label="Toggle navigation menu">
            <span class="civicone-service-navigation__toggle-icon" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
            <span class="civicone-service-navigation__toggle-text">Menu</span>
        </button>

    </div>

    <!-- Mobile navigation panel -->
    <div id="civicone-service-navigation-list" class="civicone-service-navigation__mobile-panel" hidden>
        <ul class="civicone-service-navigation__mobile-list">
            <?php foreach ($navItems as $item):
                $isActive = isNavItemActive($item['pattern'], $currentPath, $basePath);
                $activeClass = $isActive ? ' civicone-service-navigation__item--active' : '';
            ?>
                <li class="civicone-service-navigation__mobile-item<?= $activeClass ?>">
                    <a href="<?= $basePath ?><?= htmlspecialchars($item['url']) ?>"
                       class="civicone-service-navigation__mobile-link"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
