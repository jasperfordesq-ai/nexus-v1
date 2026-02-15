<?php
/**
 * Admin Gold Standard Header Component - Modern Theme
 * STANDALONE admin interface - does NOT use main site header/footer
 * Uses shared navigation configuration from views/partials/admin/
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Nexus\Core\TenantContext;

// Include shared navigation configuration
require_once dirname(__DIR__, 3) . '/partials/admin/admin-navigation-config.php';

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

// Get admin navigation modules from shared config
$adminModules = getAdminNavigationModules();
$adminModules = filterAdminModules($adminModules);

$activeModule = getActiveAdminModule($adminModules, $currentPath, $basePath);
$adminBreadcrumbs = generateAdminBreadcrumbs($adminModules, $currentPath, $basePath, $adminPageTitle);
?>
<!DOCTYPE html>
<html lang="en" class="admin-page">
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

    <!-- Design Tokens - MUST load first -->
    <link rel="stylesheet" href="/assets/css/design-tokens.css?v=<?= time() ?>">
    <!-- Admin CSS (combined) -->
    <link rel="stylesheet" href="/assets/css/admin-gold-standard.css?v=<?= time() ?>">
    <!-- Admin Sidebar CSS -->
    <link rel="stylesheet" href="/assets/css/admin-sidebar.css?v=<?= time() ?>">
    <!-- Admin Menu Builder - Extracted inline styles -->
    <link rel="stylesheet" href="/assets/css/admin-menu-builder.css?v=<?= time() ?>">
    <!-- Admin Menu Index - Extracted inline styles -->
    <link rel="stylesheet" href="/assets/css/admin-menu-index.css?v=<?= time() ?>">
    <!-- Federation External Partners -->
    <link rel="stylesheet" href="/assets/css/admin-legacy/federation-external-partners.css?v=<?= time() ?>">
    <!-- Broker Controls -->
    <link rel="stylesheet" href="/assets/css/admin-legacy/broker-controls.css?v=<?= time() ?>">
</head>
<body class="admin-page">
<div class="admin-gold-wrapper">
    <div class="admin-gold-bg"></div>

    <!-- Mobile Menu Toggle (floating) -->
    <button type="button" class="admin-mobile-menu-btn" id="adminMobileBtn" aria-label="Toggle Menu">
        <i class="fa-solid fa-bars"></i>
    </button>

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
                <div class="admin-search-section admin-live-section hidden" id="adminLiveSection">
                    <div id="adminLiveResults"></div>
                </div>

                <!-- Recently Viewed (populated by JS) -->
                <div class="admin-search-section admin-recent-section hidden" id="adminRecentSection">
                    <div class="admin-search-section-title">
                        <i class="fa-solid fa-clock-rotate-left"></i> Recently Viewed
                    </div>
                    <div id="adminRecentItems"></div>
                </div>

                <div class="admin-search-section" id="adminQuickNav">
                    <div class="admin-search-section-title">Quick Navigation</div>
                    <a href="<?= $basePath ?>/admin-legacy/users" class="admin-search-item" data-search="users members people">
                        <i class="fa-solid fa-users"></i>
                        <span>Users</span>
                        <kbd>Alt+U</kbd>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/listings" class="admin-search-item" data-search="listings services offers">
                        <i class="fa-solid fa-rectangle-list"></i>
                        <span>Listings</span>
                        <kbd>Alt+L</kbd>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/settings" class="admin-search-item" data-search="settings configuration options">
                        <i class="fa-solid fa-gear"></i>
                        <span>Settings</span>
                        <kbd>Alt+S</kbd>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/newsletters" class="admin-search-item" data-search="newsletters email campaigns">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Newsletters</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/blog" class="admin-search-item" data-search="blog posts articles news">
                        <i class="fa-solid fa-blog"></i>
                        <span>Blog Posts</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/activity-log" class="admin-search-item" data-search="activity log audit events">
                        <i class="fa-solid fa-list-ul"></i>
                        <span>Activity Log</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/categories" class="admin-search-item" data-search="categories taxonomy tags">
                        <i class="fa-solid fa-folder-tree"></i>
                        <span>Categories</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/pages" class="admin-search-item" data-search="pages content cms">
                        <i class="fa-solid fa-file-lines"></i>
                        <span>Pages</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr" class="admin-search-item" data-search="gdpr privacy compliance consent">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>GDPR Compliance</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring" class="admin-search-item" data-search="monitoring logs errors system health">
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

<!-- Sidebar Layout -->
<div class="admin-layout">
    <?php require dirname(__DIR__, 3) . '/partials/admin/admin-sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="admin-main-content">
        <div class="admin-gold-content">

    <?php // Include breadcrumbs component ?>
    <?php require __DIR__ . '/admin-breadcrumbs.php'; ?>

<!-- Admin Sidebar JS -->
<script src="/assets/js/admin-sidebar.js?v=<?= time() ?>"></script>

<!-- Admin Search Modal Configuration -->
<script>
window.NEXUS_ADMIN_CONFIG = {
    basePath: '<?= $basePath ?>',
    pageTitle: '<?= addslashes($adminPageTitle ?? "Dashboard") ?>',
    pageIcon: '<?= addslashes($adminPageIcon ?? "fa-gauge-high") ?>'
};
</script>
<!-- Admin Search Modal JS (extracted for CLAUDE.md compliance) -->
<script src="/assets/js/admin-search-modal.js?v=<?= time() ?>"></script>

<?php
// Include shared admin partials
$sharedAdminPartials = dirname(__DIR__, 3) . '/partials/admin';
require $sharedAdminPartials . '/admin-modals.php';
require $sharedAdminPartials . '/admin-bulk-actions.php';
require $sharedAdminPartials . '/admin-export.php';
require $sharedAdminPartials . '/admin-validation.php';
require $sharedAdminPartials . '/admin-realtime.php';
?>

