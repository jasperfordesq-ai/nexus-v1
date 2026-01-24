<?php
/**
 * CivicOne Service Navigation - GOV.UK Frontend Pattern
 * Uses official govuk-service-navigation classes from GOV.UK Frontend 5.14.0
 * https://design-system.service.gov.uk/components/service-navigation/
 *
 * Structure:
 * - Service name/logo (branding)
 * - Primary navigation links (max 5-6 visible)
 * - "More" dropdown for additional links (accessible)
 * - Mobile menu toggle
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$isLoggedIn = isset($_SESSION['user_id']);

// Get tenant/service name
$serviceName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
if (\Nexus\Core\TenantContext::getId() == 1) {
    $serviceName = 'Project NEXUS';
}

// Primary navigation items - Top-level sections only
$primaryNav = [
    ['label' => 'Home', 'url' => '/', 'pattern' => '/'],
    ['label' => 'Members', 'url' => '/members', 'pattern' => '/members'],
    ['label' => 'Groups', 'url' => '/groups', 'pattern' => '/groups'],
    ['label' => 'Listings', 'url' => '/listings', 'pattern' => '/listings'],
];

// Conditionally add features
if (\Nexus\Core\TenantContext::hasFeature('events')) {
    $primaryNav[] = ['label' => 'Events', 'url' => '/events', 'pattern' => '/events'];
}

// Secondary navigation (goes in "More" dropdown)
$secondaryNav = [];

if (\Nexus\Core\TenantContext::hasFeature('volunteering')) {
    $secondaryNav[] = ['label' => 'Volunteering', 'url' => '/volunteering', 'pattern' => '/volunteering'];
}

if (\Nexus\Core\TenantContext::hasFeature('blog')) {
    $secondaryNav[] = ['label' => 'News', 'url' => '/blog', 'pattern' => '/blog'];
}

if (\Nexus\Core\TenantContext::hasFeature('resources')) {
    $secondaryNav[] = ['label' => 'Resources', 'url' => '/resources', 'pattern' => '/resources'];
}

// Add Help
$secondaryNav[] = ['label' => 'Help', 'url' => '/help', 'pattern' => '/help'];

// Add database-driven pages
$dbPagesMain = \Nexus\Core\MenuGenerator::getMenuPages('main');
foreach ($dbPagesMain as $mainPage) {
    $secondaryNav[] = [
        'label' => $mainPage['title'],
        'url' => $mainPage['url'],
        'pattern' => parse_url($mainPage['url'], PHP_URL_PATH) ?? $mainPage['url']
    ];
}

// Helper: Check if nav item is active
function isNavActive($pattern, $currentPath, $basePath) {
    if ($pattern === '/') {
        return ($currentPath === '/' || $currentPath === $basePath . '/' || $currentPath === $basePath);
    }
    $normalizedPath = $currentPath;
    if (!empty($basePath) && strpos($currentPath, $basePath) === 0) {
        $normalizedPath = substr($currentPath, strlen($basePath));
    }
    return $normalizedPath === $pattern || strpos($normalizedPath, $pattern . '/') === 0;
}

// Check if any secondary nav item is active
$moreIsActive = false;
foreach ($secondaryNav as $item) {
    if (isNavActive($item['pattern'], $currentPath, $basePath)) {
        $moreIsActive = true;
        break;
    }
}
?>

<!-- GOV.UK Service Navigation -->
<div class="govuk-service-navigation" data-module="govuk-service-navigation">
    <div class="govuk-width-container">
        <div class="govuk-service-navigation__container">

            <!-- Service Name (Branding) -->
            <span class="govuk-service-navigation__service-name">
                <a href="<?= $basePath ?: '/' ?>" class="govuk-service-navigation__link">
                    <?= htmlspecialchars($serviceName) ?>
                </a>
            </span>

            <!-- Navigation -->
            <nav aria-label="Main navigation" class="govuk-service-navigation__wrapper">

                <!-- Mobile Menu Toggle -->
                <button type="button"
                        class="govuk-service-navigation__toggle govuk-js-service-navigation-toggle"
                        aria-controls="service-navigation-list"
                        hidden>
                    Menu
                </button>

                <!-- Navigation List -->
                <ul class="govuk-service-navigation__list" id="service-navigation-list">
                    <?php foreach ($primaryNav as $item):
                        $isActive = isNavActive($item['pattern'], $currentPath, $basePath);
                    ?>
                    <li class="govuk-service-navigation__item<?= $isActive ? ' govuk-service-navigation__item--active' : '' ?>">
                        <a class="govuk-service-navigation__link"
                           href="<?= $basePath . htmlspecialchars($item['url']) ?>"
                           <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <?php if ($isActive): ?>
                                <strong class="govuk-service-navigation__active-fallback"><?= htmlspecialchars($item['label']) ?></strong>
                            <?php else: ?>
                                <?= htmlspecialchars($item['label']) ?>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>

                    <?php if (!empty($secondaryNav)): ?>
                    <!-- More Dropdown -->
                    <li class="govuk-service-navigation__item<?= $moreIsActive ? ' govuk-service-navigation__item--active' : '' ?>">
                        <button type="button"
                                class="govuk-service-navigation__link govuk-service-navigation__link--more"
                                aria-expanded="false"
                                aria-controls="more-navigation-panel"
                                aria-haspopup="true"
                                id="more-navigation-toggle">
                            More
                            <svg class="govuk-service-navigation__chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="8" viewBox="0 0 14 8" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M0 0h14L7 8z"/>
                            </svg>
                        </button>

                        <!-- More Dropdown Panel -->
                        <div class="govuk-service-navigation__dropdown" id="more-navigation-panel" hidden>
                            <ul class="govuk-service-navigation__dropdown-list">
                                <?php foreach ($secondaryNav as $item):
                                    $isActive = isNavActive($item['pattern'], $currentPath, $basePath);
                                ?>
                                <li class="govuk-service-navigation__dropdown-item">
                                    <a class="govuk-service-navigation__dropdown-link govuk-link"
                                       href="<?= $basePath . htmlspecialchars($item['url']) ?>"
                                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                                        <?= htmlspecialchars($item['label']) ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- More Dropdown JavaScript (keyboard accessible) -->
<script>
(function() {
    const toggle = document.getElementById('more-navigation-toggle');
    const panel = document.getElementById('more-navigation-panel');

    if (!toggle || !panel) return;

    function openDropdown() {
        toggle.setAttribute('aria-expanded', 'true');
        panel.removeAttribute('hidden');
        const firstLink = panel.querySelector('a');
        if (firstLink) firstLink.focus();
    }

    function closeDropdown(returnFocus) {
        toggle.setAttribute('aria-expanded', 'false');
        panel.setAttribute('hidden', '');
        if (returnFocus) toggle.focus();
    }

    function isDropdownOpen() {
        return toggle.getAttribute('aria-expanded') === 'true';
    }

    // Toggle on click
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        if (isDropdownOpen()) {
            closeDropdown(true);
        } else {
            openDropdown();
        }
    });

    // Keyboard navigation
    toggle.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openDropdown();
        }
    });

    panel.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDropdown(true);
        }

        // Arrow key navigation within dropdown
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const links = Array.from(panel.querySelectorAll('a'));
            const currentIndex = links.indexOf(document.activeElement);

            if (e.key === 'ArrowDown' && currentIndex < links.length - 1) {
                links[currentIndex + 1].focus();
            } else if (e.key === 'ArrowUp' && currentIndex > 0) {
                links[currentIndex - 1].focus();
            } else if (e.key === 'ArrowUp' && currentIndex === 0) {
                closeDropdown(true);
            }
        }

        // Tab out closes dropdown
        if (e.key === 'Tab' && !e.shiftKey) {
            const links = panel.querySelectorAll('a');
            if (document.activeElement === links[links.length - 1]) {
                closeDropdown(false);
            }
        }
    });

    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (isDropdownOpen() && !toggle.contains(e.target) && !panel.contains(e.target)) {
            closeDropdown(false);
        }
    });
})();
</script>
