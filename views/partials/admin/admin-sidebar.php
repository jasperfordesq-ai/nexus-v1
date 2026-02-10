<?php
/**
 * Admin Sidebar Navigation Component
 * Renders collapsible vertical sidebar from centralized config
 */

use Nexus\Core\TenantContext;
use Nexus\Services\AdminBadgeCountService;

// Load navigation config
$adminNavigation = require __DIR__ . '/../../../config/admin-navigation.php';

// Load badge counts for sidebar
$badgeCounts = AdminBadgeCountService::getCounts();

$basePath = TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPathClean = strtok($currentPath, '?');

// Check federation condition
$isFederationEnabled = false;
try {
    $isFederationEnabled = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
} catch (\Exception $e) {
    // Service not available
}

// Check if user is super admin
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

/**
 * Check if a URL is active
 */
function isAdminSidebarActive($itemUrl, $currentPath, $basePath) {
    $fullUrl = $basePath . $itemUrl;
    $currentClean = strtok($currentPath, '?');

    // Exact match (handles query params in nav config like ?filter=pending)
    $itemUrlClean = strtok($itemUrl, '?');
    $fullUrlClean = $basePath . $itemUrlClean;

    if ($currentClean === $fullUrlClean) {
        return true;
    }

    // Dashboard is exact match only
    if ($itemUrl === '/admin') {
        return $currentClean === $fullUrl;
    }

    // Prefix match for child routes - must be followed by / or end of string
    // This prevents /admin/groups matching /admin/group-ranking
    if (strpos($currentClean, $fullUrlClean) === 0) {
        $remainder = substr($currentClean, strlen($fullUrlClean));
        // Must be exact match, or followed by /
        return $remainder === '' || $remainder[0] === '/';
    }

    return false;
}

/**
 * Check if any item in a section is active
 */
function isSectionActive($section, $currentPath, $basePath) {
    if (isset($section['url']) && isAdminSidebarActive($section['url'], $currentPath, $basePath)) {
        return true;
    }
    if (isset($section['children'])) {
        foreach ($section['children'] as $child) {
            if (isAdminSidebarActive($child['url'], $currentPath, $basePath)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Render badge HTML if count > 0
 */
function renderAdminBadge($badgeKey, $badgeCounts) {
    if (empty($badgeKey) || !isset($badgeCounts[$badgeKey])) {
        return '';
    }
    $count = (int) $badgeCounts[$badgeKey];
    if ($count <= 0) {
        return '';
    }
    $displayCount = $count > 99 ? '99+' : $count;
    return '<span class="admin-sidebar-badge">' . $displayCount . '</span>';
}
?>
<!-- Sidebar Backdrop (mobile) -->
<div class="admin-sidebar-backdrop"></div>

<!-- Sidebar -->
<aside class="admin-sidebar" role="complementary" aria-label="Admin sidebar navigation">
    <!-- Sidebar Header -->
    <div class="admin-sidebar-header">
        <a href="<?= $basePath ?>/" class="admin-sidebar-logo" title="Back to Site" data-tooltip="Back to Site">
            <div class="admin-sidebar-logo-icon">
                <i class="fa-solid fa-arrow-left"></i>
            </div>
            <span class="admin-sidebar-logo-text">Back to Site</span>
        </a>
        <button type="button" class="admin-sidebar-toggle" title="Toggle Sidebar [" aria-label="Toggle Sidebar">
            <i class="fa-solid fa-angles-left"></i>
        </button>
    </div>

    <!-- Search Trigger -->
    <div class="admin-sidebar-search">
        <button type="button" class="admin-sidebar-search-btn" id="adminSearchTrigger" title="Search (Ctrl+K)">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span class="admin-sidebar-search-label">Search...</span>
            <kbd class="admin-sidebar-search-kbd">Ctrl+K</kbd>
        </button>
    </div>

    <?php if ($isSuperAdmin): ?>
    <!-- Super Admin Link -->
    <a href="<?= $basePath ?>/super-admin" class="admin-sidebar-super-admin" title="Platform Master" data-tooltip="Super Admin">
        <i class="fa-solid fa-crown"></i>
        <span class="admin-sidebar-super-label">Super Admin</span>
    </a>
    <?php endif; ?>

    <!-- Sidebar Navigation -->
    <nav class="admin-sidebar-nav" role="navigation" aria-label="Admin navigation">
        <?php foreach ($adminNavigation as $groupKey => $group): ?>
            <?php
            // Handle single link items (like Dashboard)
            if (isset($group['single']) && $group['single']):
                $isActive = isAdminSidebarActive($group['url'], $currentPath, $basePath);
            ?>
                <a href="<?= $basePath . $group['url'] ?>"
                   class="admin-sidebar-single <?= $isActive ? 'active' : '' ?>"
                   data-tooltip="<?= htmlspecialchars($group['label']) ?>">
                    <i class="fa-solid <?= $group['icon'] ?>"></i>
                    <span class="admin-sidebar-single-label"><?= htmlspecialchars($group['label']) ?></span>
                </a>
            <?php
            continue;
            endif;

            // Skip groups without sections
            if (!isset($group['sections'])) continue;

            // Filter sections based on conditions
            $visibleSections = [];
            foreach ($group['sections'] as $sectionKey => $section) {
                // Check federation condition
                if (isset($section['condition']) && $section['condition'] === 'federation') {
                    if (!$isFederationEnabled) continue;
                }
                $visibleSections[$sectionKey] = $section;
            }

            // Skip empty groups
            if (empty($visibleSections)) continue;
            ?>

            <!-- Group: <?= $group['label'] ?> -->
            <div class="admin-sidebar-group">
                <div class="admin-sidebar-group-label">
                    <span><?= htmlspecialchars($group['label']) ?></span>
                </div>

                <?php foreach ($visibleSections as $sectionKey => $section):
                    $sectionActive = isSectionActive($section, $currentPath, $basePath);
                ?>
                    <div class="admin-sidebar-section <?= $sectionActive ? 'expanded' : '' ?>"
                         data-section="<?= $groupKey ?>-<?= $sectionKey ?>">

                        <button type="button"
                                class="admin-sidebar-section-header <?= $sectionActive ? 'active' : '' ?>"
                                data-tooltip="<?= htmlspecialchars($section['label']) ?>"
                                aria-expanded="<?= $sectionActive ? 'true' : 'false' ?>">
                            <i class="fa-solid <?= $section['icon'] ?>"></i>
                            <span class="admin-sidebar-section-label"><?= htmlspecialchars($section['label']) ?></span>
                            <?= renderAdminBadge($section['badge'] ?? null, $badgeCounts) ?>
                            <i class="fa-solid fa-chevron-down admin-sidebar-section-chevron"></i>
                        </button>

                        <?php if (isset($section['children']) && !empty($section['children'])): ?>
                            <div class="admin-sidebar-section-items" role="menu" aria-label="<?= htmlspecialchars($section['label']) ?> submenu">
                                <?php foreach ($section['children'] as $item):
                                    $itemActive = isAdminSidebarActive($item['url'], $currentPath, $basePath);
                                ?>
                                    <a href="<?= $basePath . $item['url'] ?>"
                                       class="admin-sidebar-item <?= $itemActive ? 'active' : '' ?>"
                                       role="menuitem"
                                       <?= $itemActive ? 'aria-current="page"' : '' ?>>
                                        <i class="fa-solid <?= $item['icon'] ?>" aria-hidden="true"></i>
                                        <span class="admin-sidebar-item-label"><?= htmlspecialchars($item['label']) ?></span>
                                        <?= renderAdminBadge($item['badge'] ?? null, $badgeCounts) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- Flyout for collapsed state -->
                            <div class="admin-sidebar-flyout" role="menu" aria-label="<?= htmlspecialchars($section['label']) ?>">
                                <div class="admin-sidebar-flyout-title"><?= htmlspecialchars($section['label']) ?></div>
                                <?php foreach ($section['children'] as $item):
                                    $itemActive = isAdminSidebarActive($item['url'], $currentPath, $basePath);
                                ?>
                                    <a href="<?= $basePath . $item['url'] ?>"
                                       class="admin-sidebar-item <?= $itemActive ? 'active' : '' ?>"
                                       role="menuitem"
                                       <?= $itemActive ? 'aria-current="page"' : '' ?>>
                                        <i class="fa-solid <?= $item['icon'] ?>" aria-hidden="true"></i>
                                        <span class="admin-sidebar-item-label"><?= htmlspecialchars($item['label']) ?></span>
                                        <?= renderAdminBadge($item['badge'] ?? null, $badgeCounts) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="admin-sidebar-footer">
        <?php
        $currentUser = $_SESSION['user_name'] ?? 'Admin';
        $userInitials = strtoupper(substr($currentUser, 0, 2));
        $userAvatar = $_SESSION['user_avatar'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;
        ?>
        <!-- User Profile Section -->
        <div class="admin-sidebar-user">
            <a href="<?= $basePath ?>/profile/<?= $userId ?>" class="admin-sidebar-user-info" title="View Profile" data-tooltip="<?= htmlspecialchars($currentUser) ?>">
                <?php if ($userAvatar): ?>
                    <img src="<?= htmlspecialchars($userAvatar) ?>" alt="" class="admin-sidebar-user-avatar">
                <?php else: ?>
                    <div class="admin-sidebar-user-initials"><?= htmlspecialchars($userInitials) ?></div>
                <?php endif; ?>
                <div class="admin-sidebar-user-details">
                    <span class="admin-sidebar-user-name"><?= htmlspecialchars($currentUser) ?></span>
                    <span class="admin-sidebar-user-role">Administrator</span>
                </div>
            </a>
            <a href="<?= $basePath ?>/logout" class="admin-sidebar-logout" title="Sign Out" data-tooltip="Sign Out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>

        <!-- Collapse Toggle -->
        <button type="button" class="admin-sidebar-footer-btn" onclick="AdminSidebar.toggle()" title="Toggle Sidebar [">
            <i class="fa-solid fa-angles-left"></i>
            <span class="admin-sidebar-footer-label">Collapse</span>
        </button>
    </div>
</aside>
