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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminPageTitle) ?> - Admin</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Admin CSS -->
    <link rel="stylesheet" href="/assets/css/admin-gold-standard.css?v=<?= time() ?>">

    <style>
    /* Base Reset */
    *, *::before, *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    html, body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #0a0e1a;
        color: #fff;
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }

    /* Admin Wrapper */
    .admin-gold-wrapper {
        position: relative;
        min-height: 100vh;
        padding: 1rem;
        background: linear-gradient(135deg, #0a0e1a 0%, #0f1629 50%, #151d32 100%);
    }

    .admin-gold-bg {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
    }

    .admin-gold-bg::before {
        content: '';
        position: absolute;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
        top: -200px;
        right: -200px;
        animation: adminFloat 20s ease-in-out infinite;
    }

    .admin-gold-bg::after {
        content: '';
        position: absolute;
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
        bottom: -150px;
        left: -150px;
        animation: adminFloat 25s ease-in-out infinite reverse;
    }

    @keyframes adminFloat {
        0%, 100% { transform: translate(0, 0); }
        50% { transform: translate(30px, -30px); }
    }

    /* Top Bar */
    .admin-header-bar {
        position: relative;
        z-index: 10;
        background: rgba(15, 23, 42, 0.9);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        max-width: 1600px;
        margin-left: auto;
        margin-right: auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    /* Mobile menu button in header - hidden by default */
    .admin-mobile-menu-btn {
        display: none;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        padding: 0;
        background: rgba(99, 102, 241, 0.15);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 8px;
        color: rgba(255, 255, 255, 0.9);
        cursor: pointer;
        transition: all 0.2s;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .admin-mobile-menu-btn:hover {
        background: rgba(99, 102, 241, 0.25);
        border-color: rgba(99, 102, 241, 0.5);
        transform: scale(1.05);
    }

    .admin-mobile-menu-btn:active {
        transform: scale(0.95);
    }

    .admin-header-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .admin-header-brand-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
    }

    .admin-header-brand-text {
        display: flex;
        flex-direction: column;
    }

    .admin-header-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #fff;
    }

    .admin-header-subtitle {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.5);
        text-transform: uppercase;
    }

    .admin-header-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .admin-back-link {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.8rem;
        background: rgba(16, 185, 129, 0.15);
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-radius: 6px;
        color: #10b981;
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 600;
        transition: background 0.2s;
    }

    .admin-back-link:hover {
        background: rgba(16, 185, 129, 0.25);
    }

    /* Super Admin Button - Prominent gradient style */
    .admin-super-admin-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #9333ea, #ec4899);
        border: none;
        border-radius: 8px;
        color: #fff;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 700;
        transition: all 0.2s;
        box-shadow: 0 4px 15px rgba(147, 51, 234, 0.3);
        animation: superAdminGlow 3s ease-in-out infinite;
    }

    .admin-super-admin-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 25px rgba(147, 51, 234, 0.5);
    }

    .admin-super-admin-btn i {
        color: #fbbf24;
        filter: drop-shadow(0 0 2px rgba(251, 191, 36, 0.5));
    }

    @keyframes superAdminGlow {
        0%, 100% { box-shadow: 0 4px 15px rgba(147, 51, 234, 0.3); }
        50% { box-shadow: 0 4px 20px rgba(236, 72, 153, 0.4); }
    }

    .admin-header-avatar {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        background: linear-gradient(135deg, #06b6d4, #3b82f6);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
        color: white;
    }

    /* Navigation Bar */
    .admin-smart-nav {
        position: relative;
        z-index: 100;
        background: rgba(15, 23, 42, 0.95);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 12px;
        padding: 0.5rem;
        margin: 0 auto 2rem;
        max-width: 1600px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .admin-nav-scroll {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        flex: 1;
        flex-wrap: wrap;
    }

    /* Hide mobile menu on desktop */
    .admin-mobile-menu {
        display: none;
    }

    /* Nav tabs */
    .admin-nav-tab {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.45rem 0.7rem;
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        font-size: 0.78rem;
        font-weight: 500;
        border-radius: 6px;
        border: none;
        background: transparent;
        cursor: pointer;
        white-space: nowrap;
        font-family: inherit;
        transition: all 0.15s;
    }

    .admin-nav-tab:hover {
        color: #fff;
        background: rgba(99, 102, 241, 0.15);
    }

    .admin-nav-tab.active {
        color: #fff;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
    }

    .admin-nav-tab .chevron {
        font-size: 0.55rem;
        opacity: 0.5;
        transition: transform 0.2s;
    }

    /* Dropdowns */
    .admin-nav-dropdown {
        position: relative;
        z-index: 1;
    }

    .admin-nav-dropdown:hover {
        z-index: 1000;
    }

    .admin-nav-dropdown::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        height: 16px;
        background: transparent;
    }

    .admin-dropdown-menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        min-width: 220px;
        background: #0f172a;
        border: 1px solid rgba(99, 102, 241, 0.4);
        border-radius: 10px;
        padding: 0.5rem;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
        z-index: 9999;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        pointer-events: none;
    }

    .admin-nav-dropdown:hover > .admin-dropdown-menu,
    .admin-nav-dropdown:focus-within > .admin-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        pointer-events: auto;
    }

    .admin-nav-dropdown:hover > .admin-nav-tab .chevron {
        transform: rotate(180deg);
    }

    .admin-dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.55rem 0.75rem;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        font-size: 0.82rem;
        border-radius: 6px;
        transition: all 0.1s;
    }

    .admin-dropdown-menu a:hover {
        color: #fff;
        background: rgba(99, 102, 241, 0.2);
    }

    .admin-dropdown-menu a.active {
        color: #a5b4fc;
        background: rgba(99, 102, 241, 0.15);
    }

    .admin-dropdown-menu a i {
        width: 16px;
        text-align: center;
        font-size: 0.8rem;
        opacity: 0.6;
    }

    .admin-dropdown-menu a:hover i {
        opacity: 1;
        color: #818cf8;
    }

    /* Mega Menu */
    .admin-mega-menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        min-width: 600px;
        background: #0f172a;
        border: 1px solid rgba(99, 102, 241, 0.4);
        border-radius: 12px;
        padding: 1rem;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
        z-index: 9999;
        box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        pointer-events: none;
    }

    .admin-nav-mega-dropdown:hover > .admin-mega-menu,
    .admin-nav-mega-dropdown:focus-within > .admin-mega-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        pointer-events: auto;
    }

    .admin-mega-menu-columns {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
    }

    .admin-mega-column {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .admin-mega-column-title {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: rgba(99, 102, 241, 0.8);
        padding: 0.4rem 0.75rem;
        border-bottom: 1px solid rgba(99, 102, 241, 0.2);
        margin-bottom: 0.25rem;
    }

    .admin-mega-column-items {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .admin-mega-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.55rem 0.75rem;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        font-size: 0.82rem;
        border-radius: 6px;
        transition: all 0.15s;
    }

    .admin-mega-item:hover {
        color: #fff;
        background: rgba(99, 102, 241, 0.2);
        transform: translateX(3px);
    }

    .admin-mega-item.active {
        color: #a5b4fc;
        background: rgba(99, 102, 241, 0.15);
        border-left: 2px solid #6366f1;
    }

    .admin-mega-item i {
        width: 18px;
        text-align: center;
        font-size: 0.85rem;
        opacity: 0.6;
    }

    .admin-mega-item:hover i {
        opacity: 1;
        color: #818cf8;
    }

    .admin-mega-item.active i {
        opacity: 1;
        color: #6366f1;
    }

    /* Mobile toggle */
    .admin-mobile-btn {
        display: none;
        width: 34px;
        height: 34px;
        border-radius: 6px;
        background: rgba(99, 102, 241, 0.15);
        border: 1px solid rgba(99, 102, 241, 0.3);
        color: rgba(255,255,255,0.8);
        cursor: pointer;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* Content */
    .admin-gold-content {
        position: relative;
        z-index: 5;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Glass cards */
    .admin-glass-card {
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .admin-card-header {
        padding: 1rem;
        border-bottom: 1px solid rgba(99, 102, 241, 0.15);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .admin-card-body {
        padding: 1rem;
    }

    /* Mobile - Enhanced UX */
    @media (max-width: 1024px) {
        /* Show mobile menu button in header */
        .admin-mobile-menu-btn {
            display: flex !important;
            z-index: 201;
        }

        /* Hide desktop navigation */
        .admin-smart-nav {
            display: none !important;
        }

        /* MOBILE MENU - Simple and working */
        .admin-mobile-menu {
            display: block !important;
            position: fixed !important;
            top: 0 !important;
            left: -100% !important;
            width: 280px !important;
            height: 100vh !important;
            background: linear-gradient(180deg, #0a0e1a 0%, #0f1629 100%) !important;
            padding: 80px 15px 20px 15px !important;
            overflow-y: scroll !important;
            overflow-x: hidden !important;
            -webkit-overflow-scrolling: touch !important;
            z-index: 99999 !important;
            transition: left 0.3s ease !important;
        }

        .admin-mobile-menu.open {
            left: 0 !important;
        }

        /* Ensure links are clickable */
        .admin-mobile-link {
            pointer-events: auto !important;
            position: relative !important;
            z-index: 1 !important;
        }

        /* Force scrollbar always visible for debugging */
        .admin-mobile-menu {
            scrollbar-width: thin;
            scrollbar-color: rgba(99, 102, 241, 0.4) transparent;
        }

        /* Custom scrollbar */
        .admin-mobile-menu::-webkit-scrollbar {
            width: 4px;
        }

        .admin-mobile-menu::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.4);
            border-radius: 2px;
        }

        /* Category headers */
        .admin-mobile-category {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            margin-top: 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(139, 92, 246, 0.9);
            border-bottom: 2px solid rgba(139, 92, 246, 0.3);
        }

        .admin-mobile-category:first-child {
            margin-top: 0;
        }

        .admin-mobile-category i {
            font-size: 0.9rem;
        }

        /* Mobile links */
        .admin-mobile-link {
            display: block;
            padding: 12px 15px;
            margin: 5px 0;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
        }

        .admin-mobile-link i {
            margin-right: 10px;
        }

        .admin-mobile-link:active {
            background: rgba(99, 102, 241, 0.3);
        }

        .admin-mobile-link.active {
            background: rgba(99, 102, 241, 0.25);
            border-color: rgba(99, 102, 241, 0.5);
            opacity: 0.85;
        }

        /* OLD STYLES TO REMOVE */
        .admin-nav-scroll#adminNavScroll.open .admin-nav-tab,
        .admin-nav-scroll#adminNavScroll.open .admin-nav-dropdown {
            animation: slideInLeft 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            opacity: 0;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Staggered animation for each item */
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(1) { animation-delay: 0.05s; }
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(2) { animation-delay: 0.1s; }
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(3) { animation-delay: 0.15s; }
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(4) { animation-delay: 0.2s; }
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(5) { animation-delay: 0.25s; }
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(6) { animation-delay: 0.3s; }
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(7) { animation-delay: 0.35s; }
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(8) { animation-delay: 0.4s; }
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(9) { animation-delay: 0.45s; }
        .admin-nav-scroll#adminNavScroll.open > *:nth-child(10) { animation-delay: 0.5s; }

        /* Clean mobile nav tabs - FDS Gold Standard */
        #adminNavScroll .admin-nav-tab {
            width: 100% !important;
            justify-content: flex-start !important;
            padding: 1rem 1.25rem !important;
            font-size: 0.95rem !important;
            background: rgba(15, 23, 42, 0.8) !important;
            border: 2px solid rgba(99, 102, 241, 0.3) !important;
            border-radius: 12px !important;
            margin-bottom: 0 !important;
            font-weight: 600 !important;
            letter-spacing: 0.01em !important;
            color: rgba(255, 255, 255, 0.95) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }

        #adminNavScroll .admin-nav-tab:hover {
            background: rgba(99, 102, 241, 0.25) !important;
            border-color: rgba(99, 102, 241, 0.5) !important;
            transform: translateX(4px) !important;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.15) !important;
        }

        #adminNavScroll .admin-nav-tab.active {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.35), rgba(139, 92, 246, 0.35)) !important;
            border-color: rgba(99, 102, 241, 0.7) !important;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3) !important;
        }

        /* Style dropdown containers */
        #adminNavScroll .admin-nav-dropdown {
            width: 100% !important;
        }

        #adminNavScroll .admin-nav-tab i {
            font-size: 1.15rem !important;
            min-width: 28px !important;
            opacity: 0.9 !important;
        }

        #adminNavScroll .admin-nav-tab span {
            flex: 1 !important;
        }

        #adminNavScroll .admin-nav-tab .chevron {
            font-size: 0.7rem !important;
            transition: transform 0.3s ease !important;
        }

        /* Mobile dropdown chevron animation */
        #adminNavScroll .admin-nav-dropdown.open > .admin-nav-tab .chevron {
            transform: rotate(180deg);
            color: rgba(99, 102, 241, 0.8);
        }

        #adminNavScroll .admin-nav-dropdown::after {
            display: none;
        }

        /* Clean mobile dropdown menu */
        #adminNavScroll .admin-dropdown-menu {
            position: static !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: none !important;
            margin: 0.75rem 0 0 0 !important;
            padding: 0.75rem 0 0.75rem 1rem !important;
            background: rgba(10, 14, 26, 0.5) !important;
            border: none !important;
            border-left: 3px solid rgba(99, 102, 241, 0.5) !important;
            border-radius: 0 8px 8px 0 !important;
            box-shadow: none !important;
            max-height: 0;
            overflow: hidden;
            pointer-events: auto !important;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.4s;
        }

        #adminNavScroll .admin-nav-dropdown.open .admin-dropdown-menu {
            max-height: 600px !important;
            padding: 0.75rem 0.75rem 0.75rem 1.25rem !important;
        }

        #adminNavScroll .admin-dropdown-menu a {
            display: flex !important;
            align-items: center !important;
            gap: 0.75rem !important;
            padding: 0.85rem 1rem !important;
            font-size: 0.9rem !important;
            border-radius: 8px !important;
            margin-bottom: 0.5rem !important;
            background: rgba(15, 23, 42, 0.4) !important;
            border: 1px solid rgba(99, 102, 241, 0.1) !important;
            transition: all 0.2s !important;
        }

        #adminNavScroll .admin-dropdown-menu a:hover {
            background: rgba(99, 102, 241, 0.2) !important;
            border-color: rgba(99, 102, 241, 0.3) !important;
            transform: translateX(4px) !important;
        }

        #adminNavScroll .admin-dropdown-menu a.active {
            background: rgba(99, 102, 241, 0.25) !important;
            border-color: rgba(99, 102, 241, 0.5) !important;
        }

        #adminNavScroll .admin-dropdown-menu a i {
            font-size: 1rem !important;
            opacity: 0.8 !important;
        }

        /* Disable hover effects on mobile - only clicks work */
        #adminNavScroll .admin-nav-dropdown:hover > .admin-dropdown-menu,
        #adminNavScroll .admin-nav-dropdown:focus-within > .admin-dropdown-menu {
            max-height: 0 !important;
            padding: 0.75rem 0 0 1rem !important;
        }

        #adminNavScroll .admin-nav-dropdown.open:hover > .admin-dropdown-menu,
        #adminNavScroll .admin-nav-dropdown.open:focus-within > .admin-dropdown-menu {
            max-height: 600px !important;
            padding: 0.75rem 0.75rem 0.75rem 1.25rem !important;
        }

        /* Clean mobile mega menu */
        #adminNavScroll .admin-mega-menu {
            position: static !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: none !important;
            margin: 0.75rem 0 0 0 !important;
            padding: 0 !important;
            background: rgba(10, 14, 26, 0.5) !important;
            border: none !important;
            border-left: 3px solid rgba(139, 92, 246, 0.5) !important;
            border-radius: 0 8px 8px 0 !important;
            box-shadow: none !important;
            max-height: 0;
            overflow: hidden;
            pointer-events: auto !important;
            min-width: auto !important;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.4s;
        }

        #adminNavScroll .admin-nav-mega-dropdown.open .admin-mega-menu {
            max-height: 1200px !important;
            padding: 1rem !important;
        }

        #adminNavScroll .admin-mega-menu-columns {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }

        #adminNavScroll .admin-mega-column {
            background: rgba(15, 23, 42, 0.4) !important;
            border-radius: 10px !important;
            padding: 1rem !important;
            border: 1px solid rgba(99, 102, 241, 0.15) !important;
        }

        #adminNavScroll .admin-mega-column-title {
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            padding: 0.5rem 0.75rem !important;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2)) !important;
            border-radius: 6px !important;
            margin-bottom: 0.75rem !important;
            letter-spacing: 0.05em !important;
        }

        #adminNavScroll .admin-mega-item {
            display: flex !important;
            align-items: center !important;
            gap: 0.75rem !important;
            padding: 0.85rem 1rem !important;
            font-size: 0.9rem !important;
            border-radius: 8px !important;
            margin-bottom: 0.5rem !important;
            background: rgba(15, 23, 42, 0.3) !important;
            border: 1px solid rgba(99, 102, 241, 0.08) !important;
            transition: all 0.2s !important;
        }

        #adminNavScroll .admin-mega-item:hover {
            background: rgba(99, 102, 241, 0.2) !important;
            border-color: rgba(99, 102, 241, 0.3) !important;
            transform: translateX(4px) !important;
        }

        #adminNavScroll .admin-mega-item.active {
            background: rgba(99, 102, 241, 0.25) !important;
            border-color: rgba(99, 102, 241, 0.5) !important;
        }

        #adminNavScroll .admin-mega-item i {
            font-size: 1rem !important;
            opacity: 0.8 !important;
        }

        /* Mobile header optimizations */
        .admin-header-brand-text {
            display: none;
        }

        .admin-back-link span {
            display: none;
        }

        .admin-super-admin-btn span {
            display: none;
        }

        .admin-super-admin-btn {
            padding: 0.5rem 0.75rem;
        }

        /* Close menu on link click (visual feedback) */
        .admin-nav-scroll a {
            position: relative;
        }

        .admin-nav-scroll a::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(99, 102, 241, 0.1);
            opacity: 0;
            transition: opacity 0.15s;
        }

        .admin-nav-scroll a:active::after {
            opacity: 1;
        }
    }

    @media (max-width: 600px) {
        .admin-gold-wrapper {
            padding: 0.5rem;
        }

        .admin-header-bar {
            padding: 0.5rem;
        }

        .admin-smart-nav {
            padding: 0.4rem;
        }

        .admin-glass-card {
            border-radius: 10px;
        }

        .admin-card-body {
            padding: 0.75rem;
        }
    }

    /* Breadcrumb Navigation */
    .admin-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        margin-bottom: 1.5rem;
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(99, 102, 241, 0.15);
        border-radius: 10px;
        font-size: 0.82rem;
        flex-wrap: wrap;
    }

    .admin-breadcrumb-item {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        transition: color 0.15s;
    }

    .admin-breadcrumb-item:hover {
        color: #a5b4fc;
    }

    .admin-breadcrumb-item.current {
        color: #fff;
        font-weight: 600;
    }

    .admin-breadcrumb-item i {
        font-size: 0.75rem;
        opacity: 0.7;
    }

    .admin-breadcrumb-separator {
        color: rgba(99, 102, 241, 0.4);
        font-size: 0.7rem;
    }

    @media (max-width: 600px) {
        .admin-breadcrumb {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
        }
    }

    /* Page Header - Global Spacing */
    .admin-page-header {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-top: 0.5rem;
    }

    @media (min-width: 768px) {
        .admin-page-header {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }

    .admin-page-header-content {
        flex: 1;
    }

    .admin-page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin: 0 0 0.5rem 0;
    }

    .admin-page-title i {
        color: #818cf8;
        font-size: 1.25rem;
    }

    .admin-page-subtitle {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.6);
        margin: 0;
    }

    .admin-page-header-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* Global Search Trigger */
    .admin-search-wrapper {
        position: relative;
    }

    .admin-search-trigger {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.75rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 8px;
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s;
        font-family: inherit;
    }

    .admin-search-trigger:hover {
        background: rgba(99, 102, 241, 0.15);
        border-color: rgba(99, 102, 241, 0.4);
        color: #fff;
    }

    .admin-search-label {
        opacity: 0.7;
    }

    .admin-search-kbd {
        background: rgba(99, 102, 241, 0.2);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.65rem;
        font-family: inherit;
        border: none;
    }

    /* Notifications Bell */
    .admin-notif-bell {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 8px;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        transition: all 0.2s;
    }

    .admin-notif-bell:hover {
        background: rgba(99, 102, 241, 0.15);
        color: #fff;
    }

    .admin-notif-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border-radius: 9px;
        font-size: 0.65rem;
        font-weight: 700;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    }

    /* Search Modal */
    .admin-search-modal {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: none;
        align-items: flex-start;
        justify-content: center;
        padding-top: 15vh;
    }

    .admin-search-modal.open {
        display: flex;
    }

    .admin-search-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
    }

    .admin-search-modal-content {
        position: relative;
        width: 100%;
        max-width: 560px;
        margin: 0 1rem;
        background: #0f172a;
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        overflow: hidden;
    }

    .admin-search-input-wrapper {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    }

    .admin-search-input-wrapper i {
        color: rgba(255, 255, 255, 0.4);
        font-size: 1rem;
    }

    .admin-search-input {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: #fff;
        font-size: 1rem;
        font-family: inherit;
    }

    .admin-search-input::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .admin-search-esc {
        background: rgba(99, 102, 241, 0.2);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        color: rgba(255, 255, 255, 0.5);
        border: none;
        font-family: inherit;
    }

    .admin-search-results {
        max-height: 350px;
        overflow-y: auto;
        padding: 0.75rem;
    }

    .admin-search-section {
        margin-bottom: 0.5rem;
    }

    .admin-search-section-title {
        font-size: 0.7rem;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.4);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.5rem 0.75rem;
    }

    .admin-search-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 0.75rem;
        border-radius: 8px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.15s;
    }

    .admin-search-item:hover,
    .admin-search-item.active {
        background: rgba(99, 102, 241, 0.2);
        color: #fff;
    }

    .admin-search-item i {
        width: 20px;
        text-align: center;
        color: #818cf8;
    }

    .admin-search-item span {
        flex: 1;
    }

    .admin-search-item kbd {
        background: rgba(99, 102, 241, 0.15);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.65rem;
        color: rgba(255, 255, 255, 0.4);
        border: none;
        font-family: inherit;
    }

    .admin-search-item.hidden {
        display: none;
    }

    .admin-search-footer {
        display: flex;
        gap: 1.5rem;
        padding: 0.75rem 1.25rem;
        border-top: 1px solid rgba(99, 102, 241, 0.15);
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.4);
    }

    .admin-search-footer kbd {
        background: rgba(99, 102, 241, 0.15);
        padding: 2px 5px;
        border-radius: 3px;
        font-size: 0.65rem;
        margin-right: 3px;
        border: none;
        font-family: inherit;
    }

    @media (max-width: 768px) {
        .admin-search-label,
        .admin-search-kbd {
            display: none;
        }
        .admin-search-trigger {
            width: 36px;
            height: 36px;
            padding: 0;
            justify-content: center;
        }
    }

    /* Help Tooltips */
    .admin-help-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: rgba(99, 102, 241, 0.2);
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.65rem;
        cursor: help;
        border: none;
        margin-left: 0.5rem;
        transition: all 0.2s;
        position: relative;
    }

    .admin-help-trigger:hover {
        background: rgba(99, 102, 241, 0.4);
        color: #fff;
    }

    .admin-help-tooltip {
        position: absolute;
        bottom: calc(100% + 8px);
        left: 50%;
        transform: translateX(-50%);
        width: 240px;
        padding: 0.75rem;
        background: #1e293b;
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 8px;
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.8);
        text-align: left;
        line-height: 1.4;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s;
        pointer-events: none;
    }

    .admin-help-tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 6px solid transparent;
        border-top-color: rgba(99, 102, 241, 0.3);
    }

    .admin-help-trigger:hover .admin-help-tooltip {
        opacity: 1;
        visibility: visible;
    }

    .admin-help-tooltip strong {
        color: #a5b4fc;
        display: block;
        margin-bottom: 0.25rem;
    }

    /* Keyboard Hints Panel */
    .admin-keyboard-hint {
        position: fixed;
        bottom: 1rem;
        right: 1rem;
        background: rgba(15, 23, 42, 0.95);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 10px;
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        z-index: 100;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        backdrop-filter: blur(8px);
    }

    .admin-keyboard-hint kbd {
        background: rgba(99, 102, 241, 0.2);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.7rem;
        margin-right: 4px;
        border: none;
        font-family: inherit;
    }

    @media (max-width: 768px) {
        .admin-keyboard-hint {
            display: none;
        }
    }
    </style>
</head>
<body>
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

<style>
.admin-help-shortcuts {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.admin-help-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.4rem 0;
}
.admin-help-row kbd {
    background: rgba(99, 102, 241, 0.2);
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    color: #a5b4fc;
    border: none;
    font-family: inherit;
    margin-right: 2px;
}
.admin-help-row span {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
}

/* Live Search Results Styling */
.admin-search-result-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem;
    border-radius: 8px;
    margin-bottom: 0.25rem;
    transition: background 0.15s;
}

.admin-search-result-item:hover {
    background: rgba(99, 102, 241, 0.1);
}

.admin-search-result-item .admin-search-item {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.5rem;
}

.admin-search-item-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
}

.admin-search-item-title {
    font-weight: 500;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-search-item-subtitle {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-search-actions {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.15s;
}

.admin-search-result-item:hover .admin-search-actions {
    opacity: 1;
}

.admin-quick-action {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
    text-decoration: none;
    transition: all 0.15s;
}

.admin-quick-action:hover {
    background: rgba(99, 102, 241, 0.4);
    color: #fff;
    transform: scale(1.1);
}

.admin-quick-action i {
    font-size: 0.75rem;
}

.admin-search-empty {
    padding: 1.5rem;
    text-align: center;
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
}

.admin-search-empty i {
    display: block;
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

.admin-live-section .admin-search-section-title {
    margin-top: 0.5rem;
}

.admin-live-section .admin-search-section-title:first-child {
    margin-top: 0;
}

.admin-live-section .admin-search-section-title i {
    margin-right: 0.4rem;
    color: #818cf8;
}
</style>
