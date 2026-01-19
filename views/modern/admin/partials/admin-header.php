<?php
/**
 * Admin Gold Standard Header Component
 * STANDALONE admin interface - does NOT use main site header/footer
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPathClean = strtok($currentPath, '?');
$currentUser = $_SESSION['user_name'] ?? 'Admin';
$userInitials = strtoupper(substr($currentUser, 0, 2));

$adminPageTitle = $adminPageTitle ?? 'Admin';
$adminPageSubtitle = $adminPageSubtitle ?? 'Mission Control';
$adminPageIcon = $adminPageIcon ?? 'fa-satellite-dish';

// Check if user is super admin
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

/**
 * Admin Navigation Structure - REORGANIZED & INTELLIGENT
 * Grouped logically with mega-menu support for complex sections
 */
$adminModules = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'fa-gauge-high',
        'url' => '/admin',
        'single' => true,
    ],

    // === CORE OPERATIONS ===
    'users' => [
        'label' => 'Users',
        'icon' => 'fa-users',
        'items' => [
            ['label' => 'All Users', 'url' => '/admin/users', 'icon' => 'fa-users', 'desc' => 'Manage all platform users'],
            ['label' => 'Pending Approvals', 'url' => '/admin/users?filter=pending', 'icon' => 'fa-user-clock', 'desc' => 'Review pending user accounts'],
        ],
    ],

    'listings' => [
        'label' => 'Listings',
        'icon' => 'fa-rectangle-list',
        'items' => [
            ['label' => 'All Listings', 'url' => '/admin/listings', 'icon' => 'fa-list', 'desc' => 'View all listings'],
            ['label' => 'Pending Review', 'url' => '/admin/listings?status=pending', 'icon' => 'fa-clock', 'desc' => 'Approve new listings'],
        ],
    ],

    // === COMMUNITY (REORGANIZED & SPLIT) ===
    'community' => [
        'label' => 'Community',
        'icon' => 'fa-people-group',
        'mega' => true, // Enable mega-menu for complex section
        'columns' => [
            [
                'title' => 'Groups',
                'items' => [
                    ['label' => 'All Groups', 'url' => '/admin/groups', 'icon' => 'fa-users-rectangle'],
                    ['label' => 'Group Types', 'url' => '/admin/group-types', 'icon' => 'fa-layer-group'],
                    ['label' => 'Group Ranking', 'url' => '/admin/group-ranking', 'icon' => 'fa-star'],
                    ['label' => 'Group Analytics', 'url' => '/admin/groups/analytics', 'icon' => 'fa-chart-bar'],
                ],
            ],
            [
                'title' => 'Organizations',
                'items' => [
                    ['label' => 'All Organizations', 'url' => '/admin/volunteering/organizations', 'icon' => 'fa-building'],
                    ['label' => 'Pending Approvals', 'url' => '/admin/volunteering/approvals', 'icon' => 'fa-hands-helping'],
                ],
            ],
            [
                'title' => 'Smart Systems',
                'items' => [
                    ['label' => 'Smart Matching', 'url' => '/admin/smart-match-users', 'icon' => 'fa-users-between-lines'],
                    ['label' => 'Recommendations', 'url' => '/admin/groups/recommendations', 'icon' => 'fa-sparkles'],
                    ['label' => 'Match Monitoring', 'url' => '/admin/smart-match-monitoring', 'icon' => 'fa-chart-line'],
                    ['label' => 'Geocoding', 'url' => '/admin/geocode-groups', 'icon' => 'fa-map-location-dot'],
                    ['label' => 'Locations', 'url' => '/admin/group-locations', 'icon' => 'fa-location-dot'],
                ],
            ],
        ],
    ],

    // === CONTENT ===
    'content' => [
        'label' => 'Content',
        'icon' => 'fa-newspaper',
        'items' => [
            ['label' => 'Blog Posts', 'url' => '/admin/blog', 'icon' => 'fa-blog'],
            ['label' => 'Pages', 'url' => '/admin/pages', 'icon' => 'fa-file-lines'],
            ['label' => 'Menus', 'url' => '/admin/menus', 'icon' => 'fa-bars'],
            ['label' => 'Categories', 'url' => '/admin/categories', 'icon' => 'fa-folder'],
            ['label' => 'Attributes', 'url' => '/admin/attributes', 'icon' => 'fa-tags'],
        ],
    ],

    // === ENGAGEMENT ===
    'engagement' => [
        'label' => 'Engagement',
        'icon' => 'fa-trophy',
        'items' => [
            ['label' => 'Gamification Hub', 'url' => '/admin/gamification', 'icon' => 'fa-gamepad'],
            ['label' => 'Campaigns', 'url' => '/admin/gamification/campaigns', 'icon' => 'fa-bullhorn'],
            ['label' => 'Custom Badges', 'url' => '/admin/custom-badges', 'icon' => 'fa-medal'],
            ['label' => 'Analytics', 'url' => '/admin/gamification/analytics', 'icon' => 'fa-chart-bar'],
        ],
    ],

    // === MARKETING & COMMUNICATION ===
    'marketing' => [
        'label' => 'Marketing',
        'icon' => 'fa-megaphone',
        'items' => [
            ['label' => 'Newsletters', 'url' => '/admin/newsletters', 'icon' => 'fa-envelopes-bulk'],
            ['label' => 'Subscribers', 'url' => '/admin/newsletters/subscribers', 'icon' => 'fa-user-plus'],
            ['label' => 'Segments', 'url' => '/admin/newsletters/segments', 'icon' => 'fa-layer-group'],
            ['label' => 'Templates', 'url' => '/admin/newsletters/templates', 'icon' => 'fa-palette'],
        ],
    ],

    // === ADVANCED (MEGA MENU) ===
    'advanced' => [
        'label' => 'Advanced',
        'icon' => 'fa-wand-magic-sparkles',
        'mega' => true,
        'columns' => [
            [
                'title' => 'AI & Automation',
                'items' => [
                    ['label' => 'AI Settings', 'url' => '/admin/ai-settings', 'icon' => 'fa-microchip'],
                    ['label' => 'Smart Matching', 'url' => '/admin/smart-matching', 'icon' => 'fa-wand-magic-sparkles'],
                    ['label' => 'Feed Algorithm', 'url' => '/admin/feed-algorithm', 'icon' => 'fa-rss'],
                    ['label' => 'Algorithm Settings', 'url' => '/admin/algorithm-settings', 'icon' => 'fa-scale-balanced'],
                ],
            ],
            [
                'title' => 'SEO & Optimization',
                'items' => [
                    ['label' => 'SEO Overview', 'url' => '/admin/seo', 'icon' => 'fa-chart-line'],
                    ['label' => 'SEO Audit', 'url' => '/admin/seo/audit', 'icon' => 'fa-clipboard-check'],
                    ['label' => 'Bulk Edit', 'url' => '/admin/seo/bulk/listing', 'icon' => 'fa-pen-to-square'],
                    ['label' => 'Redirects', 'url' => '/admin/seo/redirects', 'icon' => 'fa-arrow-right-arrow-left'],
                    ['label' => '404 Error Tracking', 'url' => '/admin/404-errors', 'icon' => 'fa-exclamation-triangle', 'badge' => 'NEW'],
                ],
            ],
        ],
    ],

    // === FINANCIAL ===
    'financial' => [
        'label' => 'Financial',
        'icon' => 'fa-coins',
        'items' => [
            ['label' => 'Timebanking', 'url' => '/admin/timebanking', 'icon' => 'fa-clock-rotate-left'],
            ['label' => 'Fraud Alerts', 'url' => '/admin/timebanking/alerts', 'icon' => 'fa-triangle-exclamation'],
            ['label' => 'Org Wallets', 'url' => '/admin/timebanking/org-wallets', 'icon' => 'fa-wallet'],
            ['label' => 'Plans & Pricing', 'url' => '/admin/plans', 'icon' => 'fa-credit-card'],
            ['label' => 'Subscriptions', 'url' => '/admin/plans/subscriptions', 'icon' => 'fa-users'],
        ],
    ],

    // === ENTERPRISE (MEGA MENU) ===
    'enterprise' => [
        'label' => 'Enterprise',
        'icon' => 'fa-building-shield',
        'mega' => true,
        'columns' => [
            [
                'title' => 'Overview',
                'items' => [
                    ['label' => 'Enterprise Dashboard', 'url' => '/admin/enterprise', 'icon' => 'fa-building-shield'],
                    ['label' => 'Roles & Permissions', 'url' => '/admin/enterprise/roles', 'icon' => 'fa-user-tag'],
                    ['label' => 'Permission Browser', 'url' => '/admin/enterprise/permissions', 'icon' => 'fa-key'],
                    ['label' => 'Permissions Audit', 'url' => '/admin/enterprise/audit/permissions', 'icon' => 'fa-clipboard-list'],
                ],
            ],
            [
                'title' => 'GDPR Compliance',
                'items' => [
                    ['label' => 'GDPR Dashboard', 'url' => '/admin/enterprise/gdpr', 'icon' => 'fa-user-shield'],
                    ['label' => 'Data Requests', 'url' => '/admin/enterprise/gdpr/requests', 'icon' => 'fa-file-contract'],
                    ['label' => 'Consent Records', 'url' => '/admin/enterprise/gdpr/consents', 'icon' => 'fa-check-double'],
                    ['label' => 'Data Breaches', 'url' => '/admin/enterprise/gdpr/breaches', 'icon' => 'fa-triangle-exclamation'],
                    ['label' => 'GDPR Audit Log', 'url' => '/admin/enterprise/gdpr/audit', 'icon' => 'fa-scroll'],
                ],
            ],
            [
                'title' => 'System Health',
                'items' => [
                    ['label' => 'Monitoring Dashboard', 'url' => '/admin/enterprise/monitoring', 'icon' => 'fa-heart-pulse'],
                    ['label' => 'Health Check', 'url' => '/admin/enterprise/monitoring/health', 'icon' => 'fa-stethoscope'],
                    ['label' => 'Requirements', 'url' => '/admin/enterprise/monitoring/requirements', 'icon' => 'fa-list-check'],
                    ['label' => 'Error Logs', 'url' => '/admin/enterprise/monitoring/logs', 'icon' => 'fa-file-lines'],
                ],
            ],
            [
                'title' => 'Configuration',
                'items' => [
                    ['label' => 'System Config', 'url' => '/admin/enterprise/config', 'icon' => 'fa-gears'],
                    ['label' => 'Feature Flags', 'url' => '/admin/enterprise/config#features', 'icon' => 'fa-toggle-on'],
                    ['label' => 'Secrets Vault', 'url' => '/admin/enterprise/config/secrets', 'icon' => 'fa-vault'],
                ],
            ],
        ],
    ],

    // === PARTNER TIMEBANKS (Federation) ===
    // Only shown if federation is enabled for this tenant
    'federation' => [
        'label' => 'Partner Timebanks',
        'icon' => 'fa-globe',
        'items' => [
            ['label' => 'Settings', 'url' => '/admin/federation', 'icon' => 'fa-sliders'],
            ['label' => 'Partnerships', 'url' => '/admin/federation/partnerships', 'icon' => 'fa-handshake'],
            ['label' => 'Discover', 'url' => '/admin/federation/directory', 'icon' => 'fa-compass'],
            ['label' => 'My Listing', 'url' => '/admin/federation/directory/profile', 'icon' => 'fa-user-edit'],
            ['label' => 'Analytics', 'url' => '/admin/federation/analytics', 'icon' => 'fa-chart-line'],
            ['label' => 'API Keys', 'url' => '/admin/federation/api-keys', 'icon' => 'fa-key'],
            ['label' => 'Data Management', 'url' => '/admin/federation/data', 'icon' => 'fa-database'],
        ],
        'condition' => 'federation', // Will be checked against FederationFeatureService
    ],

    // === SYSTEM ===
    'system' => [
        'label' => 'System',
        'icon' => 'fa-gear',
        'items' => [
            ['label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'fa-sliders'],
            ['label' => 'Seed Generator', 'url' => '/admin/seed-generator', 'icon' => 'fa-seedling', 'desc' => 'Generate database seeding scripts'],
            ['label' => 'Blog Restore', 'url' => '/admin/blog-restore', 'icon' => 'fa-rotate-left', 'desc' => 'Import/Export blog posts', 'badge' => 'NEW'],
            ['label' => 'Cron Jobs', 'url' => '/admin/cron-jobs', 'icon' => 'fa-clock'],
            ['label' => 'Activity Log', 'url' => '/admin/activity-log', 'icon' => 'fa-list-ul'],
            ['label' => 'API Test Runner', 'url' => '/admin/tests', 'icon' => 'fa-flask', 'desc' => 'Run automated API tests'],
            ['label' => 'Native App', 'url' => '/admin/native-app', 'icon' => 'fa-mobile-screen'],
            ['label' => 'WebP Converter', 'url' => '/admin/webp-converter', 'icon' => 'fa-image', 'desc' => 'Convert images to WebP format'],
        ],
    ],

    // === DELIVERABILITY TRACKING ===
    'deliverability' => [
        'label' => 'Deliverability',
        'icon' => 'fa-tasks-alt',
        'items' => [
            ['label' => 'Dashboard', 'url' => '/admin/deliverability', 'icon' => 'fa-gauge-high'],
            ['label' => 'All Deliverables', 'url' => '/admin/deliverability/list', 'icon' => 'fa-list'],
            ['label' => 'Create New', 'url' => '/admin/deliverability/create', 'icon' => 'fa-plus'],
            ['label' => 'Analytics', 'url' => '/admin/deliverability/analytics', 'icon' => 'fa-chart-line'],
        ],
    ],
];

// Filter modules based on conditions
$adminModules = array_filter($adminModules, function($module) {
    if (!isset($module['condition'])) {
        return true; // No condition, always show
    }

    // Check federation condition
    if ($module['condition'] === 'federation') {
        return \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
    }

    return true;
});

if (!function_exists('isAdminNavActive')) {
    function isAdminNavActive($itemUrl, $currentPath, $basePath) {
        $fullUrl = $basePath . $itemUrl;
        $currentClean = strtok($currentPath, '?');
        if ($currentClean === $fullUrl) return true;
        if ($itemUrl === '/admin') return $currentClean === $fullUrl;
        return strpos($currentClean, $fullUrl) === 0;
    }
}

if (!function_exists('getActiveAdminModule')) {
    function getActiveAdminModule($modules, $currentPath, $basePath) {
        foreach ($modules as $key => $module) {
            if (isset($module['single']) && $module['single']) {
                if (isAdminNavActive($module['url'], $currentPath, $basePath)) return $key;
            } elseif (isset($module['mega']) && $module['mega']) {
                // Check mega menu columns
                foreach ($module['columns'] as $column) {
                    foreach ($column['items'] as $item) {
                        if (isAdminNavActive($item['url'], $currentPath, $basePath)) return $key;
                    }
                }
            } elseif (isset($module['items'])) {
                foreach ($module['items'] as $item) {
                    if (isAdminNavActive($item['url'], $currentPath, $basePath)) return $key;
                }
            }
        }
        return 'dashboard';
    }
}

if (!function_exists('generateAdminBreadcrumbs')) {
    function generateAdminBreadcrumbs($modules, $currentPath, $basePath, $pageTitle = '') {
        $breadcrumbs = [['label' => 'Admin', 'url' => $basePath . '/admin', 'icon' => 'fa-gauge-high']];
        $currentClean = strtok($currentPath, '?');

        // Find matching module and item
        foreach ($modules as $key => $module) {
            if (isset($module['single']) && $module['single']) {
                if (isAdminNavActive($module['url'], $currentPath, $basePath)) {
                    if ($module['url'] !== '/admin') {
                        $breadcrumbs[] = ['label' => $module['label'], 'url' => $basePath . $module['url'], 'icon' => $module['icon']];
                    }
                    break;
                }
            } elseif (isset($module['mega']) && $module['mega']) {
                // Check mega menu columns
                foreach ($module['columns'] as $column) {
                    foreach ($column['items'] as $item) {
                        if (isAdminNavActive($item['url'], $currentPath, $basePath)) {
                            // Add module
                            $breadcrumbs[] = ['label' => $module['label'], 'url' => null, 'icon' => $module['icon']];
                            // Add column title if meaningful
                            if (!empty($column['title'])) {
                                $breadcrumbs[] = ['label' => $column['title'], 'url' => null, 'icon' => $item['icon']];
                            }
                            // Add item
                            $breadcrumbs[] = ['label' => $item['label'], 'url' => $basePath . $item['url'], 'icon' => $item['icon']];
                            break 3;
                        }
                    }
                }
            } elseif (isset($module['items'])) {
                foreach ($module['items'] as $item) {
                    if (isAdminNavActive($item['url'], $currentPath, $basePath)) {
                        // Add module
                        $breadcrumbs[] = ['label' => $module['label'], 'url' => null, 'icon' => $module['icon']];
                        // Add item if different from current
                        $breadcrumbs[] = ['label' => $item['label'], 'url' => $basePath . $item['url'], 'icon' => $item['icon']];
                        break 2;
                    }
                }
            }
        }

        // Check for edit/create pages
        if (preg_match('/\/(edit|create)(?:\/(\d+))?$/', $currentClean, $matches)) {
            $action = $matches[1];
            $breadcrumbs[] = ['label' => ucfirst($action), 'url' => null, 'icon' => $action === 'edit' ? 'fa-pen' : 'fa-plus'];
        }

        return $breadcrumbs;
    }
}

$activeModule = getActiveAdminModule($adminModules, $currentPath, $basePath);
$adminBreadcrumbs = generateAdminBreadcrumbs($adminModules, $currentPath, $basePath, $adminPageTitle);
?>
<!DOCTYPE html>
<html lang="en" style="background:#0a0e1a">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminPageTitle) ?> - Admin</title>

    <!-- Critical: Prevent flash - must be first -->
    <style>html,body,.admin-gold-wrapper{background:#0a0e1a!important;color:#fff;min-height:100vh;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;-webkit-font-smoothing:antialiased}</style>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Admin CSS (combined) -->
    <link rel="stylesheet" href="/assets/css/admin-gold-standard.min.css?v=<?= time() ?>">
</head>
<body style="background:#0a0e1a;margin:0">
<div class="admin-gold-wrapper">
    <div class="admin-gold-bg"></div>

    <!-- Top Bar -->
    <div class="admin-header-bar">
        <!-- Mobile Menu Toggle -->
        <button type="button" class="admin-mobile-menu-btn" id="adminMobileBtn" aria-label="Toggle Menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="admin-header-brand">
            <div class="admin-header-brand-icon">
                <i class="fa-solid <?= htmlspecialchars($adminPageIcon) ?>"></i>
            </div>
            <div class="admin-header-brand-text">
                <span class="admin-header-title">NEXUS Admin</span>
                <span class="admin-header-subtitle"><?= htmlspecialchars($adminPageSubtitle) ?></span>
            </div>
        </div>
        <div class="admin-header-actions">
            <!-- Global Search -->
            <div class="admin-search-wrapper">
                <button type="button" class="admin-search-trigger" id="adminSearchTrigger" title="Search (Ctrl+K)">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span class="admin-search-label">Search...</span>
                    <kbd class="admin-search-kbd">Ctrl+K</kbd>
                </button>
            </div>

            <!-- Super Admin Button (only for super admins) -->
            <?php if ($isSuperAdmin): ?>
            <a href="<?= $basePath ?>/super-admin" class="admin-super-admin-btn" title="Platform Master - Manage All Tenants">
                <i class="fa-solid fa-crown"></i>
                <span>Super Admin</span>
            </a>
            <?php endif; ?>

            <!-- Notifications Bell -->
            <?php
            $unreadNotifications = 0;
            try {
                $unreadNotifications = \Nexus\Core\Database::query(
                    "SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL",
                    [$_SESSION['user_id'] ?? 0]
                )->fetch()['c'] ?? 0;
            } catch (\Exception $e) { /* Table may not exist */ }
            ?>
            <a href="<?= $basePath ?>/notifications" class="admin-notif-bell" title="Notifications">
                <i class="fa-solid fa-bell"></i>
                <?php if ($unreadNotifications > 0): ?>
                <span class="admin-notif-badge"><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span>
                <?php endif; ?>
            </a>

            <a href="<?= $basePath ?>/" class="admin-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Site</span>
            </a>
            <div class="admin-header-avatar"><?= htmlspecialchars($userInitials) ?></div>
        </div>
    </div>

    <!-- Search Modal -->
    <div class="admin-search-modal" id="adminSearchModal">
        <div class="admin-search-modal-backdrop"></div>
        <div class="admin-search-modal-content">
            <div class="admin-search-input-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="adminSearchInput" class="admin-search-input" placeholder="Search users, listings, settings..." autocomplete="off">
                <kbd class="admin-search-esc">ESC</kbd>
            </div>
            <div class="admin-search-results" id="adminSearchResults">
                <!-- Live Search Results (populated by AJAX) -->
                <div class="admin-search-section admin-live-section" id="adminLiveSection" style="display: none;">
                    <div id="adminLiveResults"></div>
                </div>

                <!-- Recently Viewed (populated by JS) -->
                <div class="admin-search-section admin-recent-section" id="adminRecentSection" style="display: none;">
                    <div class="admin-search-section-title">
                        <i class="fa-solid fa-clock-rotate-left"></i> Recently Viewed
                    </div>
                    <div id="adminRecentItems"></div>
                </div>

                <div class="admin-search-section" id="adminQuickNav">
                    <div class="admin-search-section-title">Quick Navigation</div>
                    <a href="<?= $basePath ?>/admin/users" class="admin-search-item" data-search="users members people">
                        <i class="fa-solid fa-users"></i>
                        <span>Users</span>
                        <kbd>Alt+U</kbd>
                    </a>
                    <a href="<?= $basePath ?>/admin/listings" class="admin-search-item" data-search="listings services offers">
                        <i class="fa-solid fa-rectangle-list"></i>
                        <span>Listings</span>
                        <kbd>Alt+L</kbd>
                    </a>
                    <a href="<?= $basePath ?>/admin/settings" class="admin-search-item" data-search="settings configuration options">
                        <i class="fa-solid fa-gear"></i>
                        <span>Settings</span>
                        <kbd>Alt+S</kbd>
                    </a>
                    <a href="<?= $basePath ?>/admin/newsletters" class="admin-search-item" data-search="newsletters email campaigns">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Newsletters</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/blog" class="admin-search-item" data-search="blog posts articles news">
                        <i class="fa-solid fa-blog"></i>
                        <span>Blog Posts</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/activity-log" class="admin-search-item" data-search="activity log audit events">
                        <i class="fa-solid fa-list-ul"></i>
                        <span>Activity Log</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/categories" class="admin-search-item" data-search="categories taxonomy tags">
                        <i class="fa-solid fa-folder-tree"></i>
                        <span>Categories</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/pages" class="admin-search-item" data-search="pages content cms">
                        <i class="fa-solid fa-file-lines"></i>
                        <span>Pages</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/enterprise/gdpr" class="admin-search-item" data-search="gdpr privacy compliance consent">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>GDPR Compliance</span>
                    </a>
                    <a href="<?= $basePath ?>/admin/enterprise/monitoring" class="admin-search-item" data-search="monitoring logs errors system health">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>System Monitoring</span>
                    </a>
                </div>
            </div>
            <div class="admin-search-footer">
                <span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span>
                <span><kbd>Enter</kbd> Open</span>
                <span><kbd>ESC</kbd> Close</span>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav role="navigation" aria-label="Main navigation" class="admin-smart-nav">
        <div class="admin-nav-scroll">
            <?php foreach ($adminModules as $moduleKey => $module): ?>
                <?php if (isset($module['single']) && $module['single']): ?>
                    <!-- Single link navigation item -->
                    <a href="<?= $basePath . $module['url'] ?>" class="admin-nav-tab <?= $activeModule === $moduleKey ? 'active' : '' ?>">
                        <i class="fa-solid <?= $module['icon'] ?>"></i>
                        <span><?= $module['label'] ?></span>
                    </a>
                <?php elseif (isset($module['mega']) && $module['mega']): ?>
                    <!-- Mega menu navigation item -->
                    <div class="admin-nav-dropdown admin-nav-mega-dropdown" data-dropdown="<?= $moduleKey ?>">
                        <button type="button" class="admin-nav-tab <?= $activeModule === $moduleKey ? 'active' : '' ?>">
                            <i class="fa-solid <?= $module['icon'] ?>"></i>
                            <span><?= $module['label'] ?></span>
                            <i class="fa-solid fa-chevron-down chevron"></i>
                        </button>
                        <div class="admin-mega-menu">
                            <div class="admin-mega-menu-columns">
                                <?php foreach ($module['columns'] as $column): ?>
                                    <div class="admin-mega-column">
                                        <?php if (!empty($column['title'])): ?>
                                            <div class="admin-mega-column-title">
                                                <?= htmlspecialchars($column['title']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="admin-mega-column-items">
                                            <?php foreach ($column['items'] as $item): ?>
                                                <a href="<?= $basePath . $item['url'] ?>" class="admin-mega-item <?= isAdminNavActive($item['url'], $currentPath, $basePath) ? 'active' : '' ?>">
                                                    <i class="fa-solid <?= $item['icon'] ?>"></i>
                                                    <span><?= htmlspecialchars($item['label']) ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Standard dropdown navigation item -->
                    <div class="admin-nav-dropdown" data-dropdown="<?= $moduleKey ?>">
                        <button type="button" class="admin-nav-tab <?= $activeModule === $moduleKey ? 'active' : '' ?>">
                            <i class="fa-solid <?= $module['icon'] ?>"></i>
                            <span><?= $module['label'] ?></span>
                            <i class="fa-solid fa-chevron-down chevron"></i>
                        </button>
                        <div class="admin-dropdown-menu">
                            <?php foreach ($module['items'] as $item): ?>
                                <a href="<?= $basePath . $item['url'] ?>" class="<?= isAdminNavActive($item['url'], $currentPath, $basePath) ? 'active' : '' ?>">
                                    <i class="fa-solid <?= $item['icon'] ?>"></i>
                                    <?= htmlspecialchars($item['label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- Mobile Menu (simple flat link list) -->
    <div id="adminNavScroll" class="admin-mobile-menu">
        <?php foreach ($adminModules as $moduleKey => $module): ?>
            <?php if (isset($module['single']) && $module['single']): ?>
                <!-- Single link -->
                <a href="<?= $basePath . $module['url'] ?>" class="admin-mobile-link <?= $activeModule === $moduleKey ? 'active' : '' ?>">
                    <i class="fa-solid <?= $module['icon'] ?>"></i>
                    <span><?= $module['label'] ?></span>
                </a>
            <?php elseif (isset($module['mega']) && $module['mega']): ?>
                <!-- Category header -->
                <div class="admin-mobile-category">
                    <i class="fa-solid <?= $module['icon'] ?>"></i>
                    <span><?= $module['label'] ?></span>
                </div>
                <!-- Flat list of items from mega menu -->
                <?php foreach ($module['columns'] as $column): ?>
                    <?php foreach ($column['items'] as $item): ?>
                        <a href="<?= $basePath . $item['url'] ?>" class="admin-mobile-link <?= isAdminNavActive($item['url'], $currentPath, $basePath) ? 'active' : '' ?>">
                            <i class="fa-solid <?= $item['icon'] ?>"></i>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Category header -->
                <div class="admin-mobile-category">
                    <i class="fa-solid <?= $module['icon'] ?>"></i>
                    <span><?= $module['label'] ?></span>
                </div>
                <!-- Flat list of items -->
                <?php foreach ($module['items'] as $item): ?>
                    <a href="<?= $basePath . $item['url'] ?>" class="admin-mobile-link <?= isAdminNavActive($item['url'], $currentPath, $basePath) ? 'active' : '' ?>">
                        <i class="fa-solid <?= $item['icon'] ?>"></i>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Content area starts here -->
    <div class="admin-gold-content">

    <?php if (count($adminBreadcrumbs) > 1): ?>
    <!-- Breadcrumb Navigation -->
    <nav role="navigation" aria-label="Main navigation" class="admin-breadcrumb" aria-label="Breadcrumb">
        <?php foreach ($adminBreadcrumbs as $index => $crumb): ?>
            <?php $isLast = $index === count($adminBreadcrumbs) - 1; ?>
            <?php if ($index > 0): ?>
                <span class="admin-breadcrumb-separator"><i class="fa-solid fa-chevron-right"></i></span>
            <?php endif; ?>
            <?php if ($crumb['url'] && !$isLast): ?>
                <a href="<?= htmlspecialchars($crumb['url']) ?>" class="admin-breadcrumb-item">
                    <i class="fa-solid <?= htmlspecialchars($crumb['icon']) ?>"></i>
                    <span><?= htmlspecialchars($crumb['label']) ?></span>
                </a>
            <?php else: ?>
                <span class="admin-breadcrumb-item <?= $isLast ? 'current' : '' ?>">
                    <i class="fa-solid <?= htmlspecialchars($crumb['icon']) ?>"></i>
                    <span><?= htmlspecialchars($crumb['label']) ?></span>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

<script>
// MOBILE MENU - Final working version
document.addEventListener('DOMContentLoaded', function() {
    var mobileBtn = document.getElementById('adminMobileBtn');
    var mobileMenu = document.getElementById('adminNavScroll');
    var mobileBtnIcon = mobileBtn ? mobileBtn.querySelector('i') : null;

    console.log('Mobile menu JS loaded');
    console.log('Button:', mobileBtn);
    console.log('Menu:', mobileMenu);

    if (!mobileBtn || !mobileMenu) {
        console.log('MISSING ELEMENTS!');
        return;
    }

    function openMenu() {
        console.log('OPEN called');
        mobileMenu.classList.add('open');
        console.log('Classes now:', mobileMenu.className);
        document.body.style.overflow = 'hidden';
        if (mobileBtnIcon) mobileBtnIcon.className = 'fa-solid fa-times';
    }

    function closeMenu() {
        mobileMenu.classList.remove('open');
        document.body.style.overflow = '';
        if (mobileBtnIcon) mobileBtnIcon.className = 'fa-solid fa-bars';
    }

    // Toggle menu
    mobileBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (mobileMenu.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    // Close when clicking a link
    mobileMenu.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', closeMenu);
    });

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileMenu.classList.contains('open')) {
            closeMenu();
        }
    });

    // REMOVED - No outside click handler, it causes the issue
});

// Admin Search Modal & Keyboard Shortcuts with AJAX Live Search
(function() {
    var searchModal = document.getElementById('adminSearchModal');
    var searchTrigger = document.getElementById('adminSearchTrigger');
    var searchInput = document.getElementById('adminSearchInput');
    var searchResults = document.getElementById('adminSearchResults');
    var activeIndex = -1;
    var basePath = '<?= $basePath ?>';
    var recentSection = document.getElementById('adminRecentSection');
    var recentItemsContainer = document.getElementById('adminRecentItems');
    var liveSection = document.getElementById('adminLiveSection');
    var liveResults = document.getElementById('adminLiveResults');
    var quickNav = document.getElementById('adminQuickNav');
    var RECENT_KEY = 'nexus_admin_recent';
    var MAX_RECENT = 5;
    var searchTimeout = null;
    var isSearching = false;

    // Track current page visit
    function trackPageVisit() {
        var pageTitle = '<?= addslashes($adminPageTitle ?? "Dashboard") ?>';
        var pageIcon = '<?= addslashes($adminPageIcon ?? "fa-gauge-high") ?>';
        var pageUrl = window.location.pathname;

        // Don't track the main dashboard
        if (pageUrl === basePath + '/admin' || pageUrl === basePath + '/admin/') return;

        var recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');

        // Remove duplicates
        recent = recent.filter(function(item) { return item.url !== pageUrl; });

        // Add current page to front
        recent.unshift({ title: pageTitle, icon: pageIcon, url: pageUrl, time: Date.now() });

        // Keep only MAX_RECENT items
        recent = recent.slice(0, MAX_RECENT);

        localStorage.setItem(RECENT_KEY, JSON.stringify(recent));
    }

    // Render recently viewed items
    function renderRecentItems() {
        var recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');

        if (recent.length === 0 || !recentSection || !recentItemsContainer) {
            if (recentSection) recentSection.style.display = 'none';
            return;
        }

        recentSection.style.display = 'block';
        recentItemsContainer.innerHTML = recent.map(function(item) {
            return '<a href="' + item.url + '" class="admin-search-item recent-item" data-search="' + item.title.toLowerCase() + '">' +
                '<i class="fa-solid ' + item.icon + '"></i>' +
                '<span>' + item.title + '</span>' +
                '</a>';
        }).join('');
    }

    // AJAX Live Search
    function performLiveSearch(query) {
        if (query.length < 2) {
            liveSection.style.display = 'none';
            liveResults.innerHTML = '';
            quickNav.style.display = 'block';
            recentSection.style.display = 'block';
            return;
        }

        isSearching = true;

        fetch(basePath + '/admin/api/search?q=' + encodeURIComponent(query))
            .then(function(response) { return response.json(); })
            .then(function(data) {
                isSearching = false;
                renderLiveResults(data, query);
            })
            .catch(function(err) {
                isSearching = false;
                console.error('Search error:', err);
            });
    }

    // Render live search results with quick actions
    function renderLiveResults(data, query) {
        var html = '';
        var hasResults = false;

        // Users section
        if (data.users && data.users.length > 0) {
            hasResults = true;
            html += '<div class="admin-search-section-title"><i class="fa-solid fa-users"></i> Users</div>';
            data.users.forEach(function(user) {
                html += '<div class="admin-search-result-item">' +
                    '<a href="' + user.url + '" class="admin-search-item live-item">' +
                        '<i class="fa-solid ' + user.icon + '"></i>' +
                        '<div class="admin-search-item-content">' +
                            '<span class="admin-search-item-title">' + escapeHtml(user.title) + '</span>' +
                            '<span class="admin-search-item-subtitle">' + escapeHtml(user.subtitle) + '</span>' +
                        '</div>' +
                    '</a>' +
                    '<div class="admin-search-actions">' +
                        renderQuickActions(user.actions) +
                    '</div>' +
                '</div>';
            });
        }

        // Listings section
        if (data.listings && data.listings.length > 0) {
            hasResults = true;
            html += '<div class="admin-search-section-title"><i class="fa-solid fa-rectangle-list"></i> Listings</div>';
            data.listings.forEach(function(listing) {
                html += '<div class="admin-search-result-item">' +
                    '<a href="' + listing.url + '" class="admin-search-item live-item">' +
                        '<i class="fa-solid ' + listing.icon + '"></i>' +
                        '<div class="admin-search-item-content">' +
                            '<span class="admin-search-item-title">' + escapeHtml(listing.title) + '</span>' +
                            '<span class="admin-search-item-subtitle">' + escapeHtml(listing.subtitle) + '</span>' +
                        '</div>' +
                    '</a>' +
                    '<div class="admin-search-actions">' +
                        renderQuickActions(listing.actions) +
                    '</div>' +
                '</div>';
            });
        }

        // Blog posts section
        if (data.pages && data.pages.length > 0) {
            hasResults = true;
            html += '<div class="admin-search-section-title"><i class="fa-solid fa-file-lines"></i> Content</div>';
            data.pages.forEach(function(page) {
                html += '<div class="admin-search-result-item">' +
                    '<a href="' + page.url + '" class="admin-search-item live-item">' +
                        '<i class="fa-solid ' + page.icon + '"></i>' +
                        '<div class="admin-search-item-content">' +
                            '<span class="admin-search-item-title">' + escapeHtml(page.title) + '</span>' +
                            '<span class="admin-search-item-subtitle">' + escapeHtml(page.subtitle) + '</span>' +
                        '</div>' +
                    '</a>' +
                    '<div class="admin-search-actions">' +
                        renderQuickActions(page.actions) +
                    '</div>' +
                '</div>';
            });
        }

        if (hasResults) {
            liveSection.style.display = 'block';
            liveResults.innerHTML = html;
            quickNav.style.display = 'none';
            recentSection.style.display = 'none';
        } else {
            liveSection.style.display = 'block';
            liveResults.innerHTML = '<div class="admin-search-empty"><i class="fa-solid fa-magnifying-glass"></i> No results for "' + escapeHtml(query) + '"</div>';
            quickNav.style.display = 'block';
            recentSection.style.display = 'block';
        }

        activeIndex = -1;
        updateActiveItem();
    }

    function renderQuickActions(actions) {
        if (!actions || actions.length === 0) return '';
        return actions.map(function(action) {
            return '<a href="' + action.url + '" class="admin-quick-action" title="' + escapeHtml(action.label) + '">' +
                '<i class="fa-solid ' + action.icon + '"></i>' +
            '</a>';
        }).join('');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Track page on load
    trackPageVisit();

    function openSearch() {
        if (searchModal) {
            searchModal.classList.add('open');
            renderRecentItems();
            liveSection.style.display = 'none';
            quickNav.style.display = 'block';
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
                filterItems('');
            }
            activeIndex = -1;
            updateActiveItem();
        }
    }

    function getAllSearchItems() {
        return searchResults ? searchResults.querySelectorAll('.admin-search-item') : [];
    }

    function closeSearch() {
        if (searchModal) {
            searchModal.classList.remove('open');
        }
    }

    function filterItems(query) {
        query = query.toLowerCase().trim();

        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // If query is long enough, do AJAX search with debounce
        if (query.length >= 2) {
            searchTimeout = setTimeout(function() {
                performLiveSearch(query);
            }, 250); // 250ms debounce
        } else {
            liveSection.style.display = 'none';
            quickNav.style.display = 'block';
        }

        // Also filter static items
        var allItems = quickNav.querySelectorAll('.admin-search-item');
        allItems.forEach(function(item) {
            var searchText = (item.getAttribute('data-search') || '') + ' ' + item.textContent;
            if (query === '' || searchText.toLowerCase().includes(query)) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });

        // Hide recent section if searching
        if (recentSection) {
            var recentVisible = recentItemsContainer ? recentItemsContainer.querySelectorAll('.admin-search-item:not(.hidden)').length : 0;
            recentSection.style.display = (query === '' || recentVisible > 0) ? 'block' : 'none';
        }

        activeIndex = -1;
        updateActiveItem();
    }

    function getVisibleItems() {
        return Array.from(getAllSearchItems()).filter(function(item) {
            return !item.classList.contains('hidden');
        });
    }

    function updateActiveItem() {
        var allItems = getAllSearchItems();
        allItems.forEach(function(item) {
            item.classList.remove('active');
        });
        var visible = getVisibleItems();
        if (activeIndex >= 0 && activeIndex < visible.length) {
            visible[activeIndex].classList.add('active');
            visible[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    function navigateItems(direction) {
        var visible = getVisibleItems();
        if (visible.length === 0) return;

        if (direction === 'down') {
            activeIndex = activeIndex < visible.length - 1 ? activeIndex + 1 : 0;
        } else {
            activeIndex = activeIndex > 0 ? activeIndex - 1 : visible.length - 1;
        }
        updateActiveItem();
    }

    function selectActive() {
        var visible = getVisibleItems();
        if (activeIndex >= 0 && activeIndex < visible.length) {
            window.location.href = visible[activeIndex].href;
        } else if (visible.length > 0) {
            window.location.href = visible[0].href;
        }
    }

    // Event listeners
    if (searchTrigger) {
        searchTrigger.addEventListener('click', openSearch);
    }

    if (searchModal) {
        searchModal.querySelector('.admin-search-modal-backdrop').addEventListener('click', closeSearch);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterItems(this.value);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                navigateItems('down');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                navigateItems('up');
            } else if (e.key === 'Enter') {
                e.preventDefault();
                selectActive();
            } else if (e.key === 'Escape') {
                closeSearch();
            }
        });
    }

    // Global keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+K or Cmd+K to open search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }

        // Escape to close search
        if (e.key === 'Escape' && searchModal && searchModal.classList.contains('open')) {
            closeSearch();
        }

        // Alt+shortcuts for quick navigation (only when not in input)
        if (e.altKey && !e.target.matches('input, textarea, select')) {
            var shortcutMap = {
                'u': basePath + '/admin/users',
                'l': basePath + '/admin/listings',
                's': basePath + '/admin/settings',
                'd': basePath + '/admin',
                'n': basePath + '/admin/newsletters'
            };
            var key = e.key.toLowerCase();
            if (shortcutMap[key]) {
                e.preventDefault();
                window.location.href = shortcutMap[key];
            }
        }

        // "?" to show keyboard shortcuts help
        if (e.key === '?' && !e.target.matches('input, textarea, select')) {
            e.preventDefault();
            showKeyboardHelp();
        }
    });

    function showKeyboardHelp() {
        var helpModal = document.getElementById('adminHelpModal');
        if (!helpModal) {
            helpModal = document.createElement('div');
            helpModal.id = 'adminHelpModal';
            helpModal.className = 'admin-search-modal';
            helpModal.innerHTML = '<div class="admin-search-modal-backdrop"></div>' +
                '<div class="admin-search-modal-content" style="max-width: 400px;">' +
                    '<div style="padding: 1.25rem; border-bottom: 1px solid rgba(99, 102, 241, 0.2);">' +
                        '<h3 style="margin: 0; font-size: 1.1rem; color: #fff;"><i class="fa-solid fa-keyboard" style="margin-right: 0.5rem; color: #818cf8;"></i>Keyboard Shortcuts</h3>' +
                    '</div>' +
                    '<div style="padding: 1rem;">' +
                        '<div class="admin-help-shortcuts">' +
                            '<div class="admin-help-row"><div><kbd>Ctrl</kbd><kbd>K</kbd></div><span>Open search</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>D</kbd></div><span>Dashboard</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>U</kbd></div><span>Users</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>L</kbd></div><span>Listings</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>S</kbd></div><span>Settings</span></div>' +
                            '<div class="admin-help-row"><div><kbd>Alt</kbd><kbd>N</kbd></div><span>Newsletters</span></div>' +
                            '<div class="admin-help-row"><div><kbd>ESC</kbd></div><span>Close modal</span></div>' +
                            '<div class="admin-help-row"><div><kbd>?</kbd></div><span>Show this help</span></div>' +
                        '</div>' +
                    '</div>' +
                    '<div style="padding: 0.75rem 1rem; border-top: 1px solid rgba(99, 102, 241, 0.15); text-align: center;">' +
                        '<span style="font-size: 0.75rem; color: rgba(255,255,255,0.4);">Press <kbd style="background: rgba(99,102,241,0.2); padding: 2px 6px; border-radius: 4px; font-size: 0.65rem;">ESC</kbd> to close</span>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(helpModal);

            helpModal.querySelector('.admin-search-modal-backdrop').addEventListener('click', function() {
                helpModal.classList.remove('open');
            });

            document.addEventListener('keydown', function(ev) {
                if (ev.key === 'Escape' && helpModal.classList.contains('open')) {
                    helpModal.classList.remove('open');
                }
            });
        }
        helpModal.classList.add('open');
    }
})();
</script>

<?php require __DIR__ . '/admin-modals.php'; ?>
<?php require __DIR__ . '/admin-bulk-actions.php'; ?>
<?php require __DIR__ . '/admin-export.php'; ?>
<?php require __DIR__ . '/admin-validation.php'; ?>
<?php require __DIR__ . '/admin-realtime.php'; ?>

