<?php
/**
 * Admin Gold Standard Layout - Mission Control Design
 * Dark Mode Holographic Glassmorphism Theme
 * Version 2.0
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Database;

$basePath = TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentUser = $_SESSION['user_name'] ?? 'Admin';
$userInitials = strtoupper(substr($currentUser, 0, 2));

// Define all admin navigation modules
$adminModules = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'fa-gauge-high',
        'url' => '/admin',
        'single' => true,
    ],
    'users' => [
        'label' => 'Users',
        'icon' => 'fa-users',
        'items' => [
            ['label' => 'All Users', 'url' => '/admin/users', 'icon' => 'fa-users'],
            ['label' => 'Approvals', 'url' => '/admin/users?filter=pending', 'icon' => 'fa-user-clock'],
            ['label' => 'Badges', 'url' => '/admin/custom-badges', 'icon' => 'fa-award'],
        ],
    ],
    'content' => [
        'label' => 'Content',
        'icon' => 'fa-newspaper',
        'items' => [
            ['label' => 'Blog Posts', 'url' => '/admin/blog', 'icon' => 'fa-blog'],
            ['label' => 'Pages', 'url' => '/admin/pages', 'icon' => 'fa-file-lines'],
            ['label' => 'Categories', 'url' => '/admin/categories', 'icon' => 'fa-folder'],
            ['label' => 'Attributes', 'url' => '/admin/attributes', 'icon' => 'fa-tags'],
        ],
    ],
    'listings' => [
        'label' => 'Listings',
        'icon' => 'fa-list-check',
        'items' => [
            ['label' => 'All Listings', 'url' => '/admin/listings', 'icon' => 'fa-list'],
            ['label' => 'Pending Approval', 'url' => '/admin/listings?status=pending', 'icon' => 'fa-clock'],
        ],
    ],
    'community' => [
        'label' => 'Community',
        'icon' => 'fa-people-group',
        'items' => [
            ['label' => 'Volunteering', 'url' => '/admin/volunteering', 'icon' => 'fa-hands-helping'],
            ['label' => 'Organizations', 'url' => '/admin/volunteering/organizations', 'icon' => 'fa-building'],
            ['label' => 'Group Locations', 'url' => '/admin/group-locations', 'icon' => 'fa-location-dot'],
        ],
    ],
    'engagement' => [
        'label' => 'Engagement',
        'icon' => 'fa-trophy',
        'items' => [
            ['label' => 'Gamification', 'url' => '/admin/gamification', 'icon' => 'fa-gamepad'],
            ['label' => 'Campaigns', 'url' => '/admin/gamification/campaigns', 'icon' => 'fa-bullhorn'],
            ['label' => 'Analytics', 'url' => '/admin/gamification/analytics', 'icon' => 'fa-chart-bar'],
        ],
    ],
    'newsletters' => [
        'label' => 'Newsletters',
        'icon' => 'fa-envelope',
        'items' => [
            ['label' => 'All Newsletters', 'url' => '/admin/newsletters', 'icon' => 'fa-envelopes-bulk'],
            ['label' => 'Subscribers', 'url' => '/admin/newsletters/subscribers', 'icon' => 'fa-user-plus'],
            ['label' => 'Segments', 'url' => '/admin/newsletters/segments', 'icon' => 'fa-layer-group'],
            ['label' => 'Templates', 'url' => '/admin/newsletters/templates', 'icon' => 'fa-palette'],
        ],
    ],
    'seo' => [
        'label' => 'SEO',
        'icon' => 'fa-magnifying-glass-chart',
        'items' => [
            ['label' => 'Overview', 'url' => '/admin/seo', 'icon' => 'fa-chart-line'],
            ['label' => 'Audit', 'url' => '/admin/seo/audit', 'icon' => 'fa-clipboard-check'],
            ['label' => 'Bulk Edit', 'url' => '/admin/seo/bulk-edit', 'icon' => 'fa-pen-to-square'],
            ['label' => 'Redirects', 'url' => '/admin/seo/redirects', 'icon' => 'fa-arrow-right-arrow-left'],
        ],
    ],
    'ai' => [
        'label' => 'AI & Smart',
        'icon' => 'fa-robot',
        'items' => [
            ['label' => 'AI Settings', 'url' => '/admin/ai-settings', 'icon' => 'fa-microchip'],
            ['label' => 'Smart Matching', 'url' => '/admin/smart-matching', 'icon' => 'fa-wand-magic-sparkles'],
            ['label' => 'Feed Algorithm', 'url' => '/admin/feed-algorithm', 'icon' => 'fa-sliders'],
        ],
    ],
    'timebanking' => [
        'label' => 'Timebanking',
        'icon' => 'fa-clock-rotate-left',
        'items' => [
            ['label' => 'Dashboard', 'url' => '/admin/timebanking', 'icon' => 'fa-gauge'],
            ['label' => 'Alerts', 'url' => '/admin/timebanking/alerts', 'icon' => 'fa-triangle-exclamation'],
            ['label' => 'Org Wallets', 'url' => '/admin/timebanking/org-wallets', 'icon' => 'fa-wallet'],
        ],
    ],
    'enterprise' => [
        'label' => 'Enterprise',
        'icon' => 'fa-building-shield',
        'items' => [
            ['label' => 'Overview', 'url' => '/admin/enterprise', 'icon' => 'fa-chart-pie'],
            ['label' => 'GDPR', 'url' => '/admin/enterprise/gdpr', 'icon' => 'fa-user-shield'],
            ['label' => 'Monitoring', 'url' => '/admin/enterprise/monitoring', 'icon' => 'fa-heart-pulse'],
            ['label' => 'Configuration', 'url' => '/admin/enterprise/config', 'icon' => 'fa-gears'],
        ],
    ],
    'system' => [
        'label' => 'System',
        'icon' => 'fa-cog',
        'items' => [
            ['label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'fa-sliders'],
            ['label' => 'Cron Jobs', 'url' => '/admin/cron-jobs', 'icon' => 'fa-clock'],
            ['label' => 'Activity Log', 'url' => '/admin/activity-log', 'icon' => 'fa-list-ul'],
            ['label' => 'Native App', 'url' => '/admin/native-app', 'icon' => 'fa-mobile-screen'],
        ],
    ],
];

// Helper function to check if a path is active
function isAdminPathActive($itemUrl, $currentPath, $basePath) {
    $fullUrl = $basePath . $itemUrl;
    // Exact match or starts with (for sub-pages)
    return $currentPath === $fullUrl || strpos($currentPath, $fullUrl) === 0;
}

// Determine which module is currently active
function getActiveModule($modules, $currentPath, $basePath) {
    foreach ($modules as $key => $module) {
        if (isset($module['single']) && $module['single']) {
            if (isAdminPathActive($module['url'], $currentPath, $basePath)) {
                return $key;
            }
        } else if (isset($module['items'])) {
            foreach ($module['items'] as $item) {
                if (isAdminPathActive($item['url'], $currentPath, $basePath)) {
                    return $key;
                }
            }
        }
    }
    return 'dashboard';
}

$activeModule = getActiveModule($adminModules, $currentPath, $basePath);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin' ?> | NEXUS Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Gold Standard CSS -->
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/admin-gold-standard.min.css?v=<?= time() ?>">

    <?php if (isset($additionalCss)): ?>
    <?= $additionalCss ?>
    <?php endif; ?>
</head>
<body class="admin-gold">
    <!-- Animated Background -->
    <div class="admin-gold-bg"></div>

    <!-- Top Navigation Bar -->
    <header class="admin-topbar">
        <!-- Brand -->
        <a href="<?= $basePath ?>/admin" class="admin-topbar-brand">
            <div class="admin-topbar-brand-icon">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <span>NEXUS</span>
        </a>

        <!-- Mobile Menu Toggle -->
        <button class="admin-mobile-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <!-- Smart Tab Navigation -->
        <nav role="navigation" aria-label="Main navigation" class="admin-smart-tabs" id="adminSmartTabs">
            <?php foreach ($adminModules as $moduleKey => $module): ?>
                <?php if (isset($module['single']) && $module['single']): ?>
                    <!-- Single Item (Dashboard) -->
                    <a href="<?= $basePath . $module['url'] ?>"
                       class="admin-tab <?= $activeModule === $moduleKey ? 'active' : '' ?>">
                        <i class="fa-solid <?= $module['icon'] ?>"></i>
                        <span class="admin-hide-mobile"><?= $module['label'] ?></span>
                    </a>
                <?php else: ?>
                    <!-- Dropdown Group -->
                    <div class="admin-tab-group">
                        <button class="admin-tab <?= $activeModule === $moduleKey ? 'active' : '' ?>" type="button">
                            <i class="fa-solid <?= $module['icon'] ?>"></i>
                            <span class="admin-hide-mobile"><?= $module['label'] ?></span>
                            <i class="fa-solid fa-chevron-down admin-hide-mobile" style="font-size: 0.625rem; margin-left: 4px; opacity: 0.5;"></i>
                        </button>
                        <div class="admin-tab-dropdown">
                            <?php foreach ($module['items'] as $item): ?>
                                <a href="<?= $basePath . $item['url'] ?>"
                                   class="admin-tab-dropdown-item <?= isAdminPathActive($item['url'], $currentPath, $basePath) ? 'active' : '' ?>">
                                    <i class="fa-solid <?= $item['icon'] ?>"></i>
                                    <?= $item['label'] ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Topbar Actions -->
        <div class="admin-topbar-actions">
            <!-- View Site -->
            <a href="<?= $basePath ?>/" class="admin-topbar-btn" title="View Site" target="_blank">
                <i class="fa-solid fa-external-link"></i>
            </a>

            <!-- Notifications -->
            <button class="admin-topbar-btn" title="Notifications">
                <i class="fa-solid fa-bell"></i>
            </button>

            <!-- User Menu -->
            <div class="admin-topbar-user">
                <div class="admin-topbar-avatar">
                    <?= $userInitials ?>
                </div>
                <div class="admin-topbar-user-info">
                    <div class="admin-topbar-user-name"><?= htmlspecialchars($currentUser) ?></div>
                    <div class="admin-topbar-user-role">Administrator</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-container">
            <?php if (isset($pageContent)): ?>
                <?= $pageContent ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Toast Container -->
    <div class="admin-toast-container" id="adminToastContainer"></div>

    <!-- Core Scripts -->
    <script>
    // Mobile Menu Toggle
    function toggleMobileMenu() {
        const tabs = document.getElementById('adminSmartTabs');
        tabs.classList.toggle('open');
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        const tabs = document.getElementById('adminSmartTabs');
        const toggle = document.querySelector('.admin-mobile-toggle');
        if (tabs.classList.contains('open') && !tabs.contains(e.target) && !toggle.contains(e.target)) {
            tabs.classList.remove('open');
        }
    });

    // Toast Notification System
    const AdminToast = {
        container: document.getElementById('adminToastContainer'),

        show(type, title, message, duration = 5000) {
            const toast = document.createElement('div');
            toast.className = `admin-toast ${type}`;

            const icons = {
                success: 'fa-check-circle',
                error: 'fa-times-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };

            toast.innerHTML = `
                <i class="fa-solid ${icons[type]} admin-toast-icon"></i>
                <div class="admin-toast-content">
                    <div class="admin-toast-title">${title}</div>
                    <div class="admin-toast-message">${message}</div>
                </div>
                <button class="admin-toast-close" onclick="this.parentElement.remove()">
                    <i class="fa-solid fa-times"></i>
                </button>
            `;

            this.container.appendChild(toast);

            if (duration > 0) {
                setTimeout(() => toast.remove(), duration);
            }

            return toast;
        },

        success(title, message) { return this.show('success', title, message); },
        error(title, message) { return this.show('error', title, message); },
        warning(title, message) { return this.show('warning', title, message); },
        info(title, message) { return this.show('info', title, message); }
    };

    // Make AdminToast globally available
    window.AdminToast = AdminToast;

    // Admin Modal System
    const AdminModal = {
        show(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('open');
        },
        hide(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.remove('open');
        }
    };
    window.AdminModal = AdminModal;

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // ESC to close modals
        if (e.key === 'Escape') {
            document.querySelectorAll('.admin-modal-backdrop.open').forEach(m => m.classList.remove('open'));
            document.getElementById('adminSmartTabs')?.classList.remove('open');
        }
    });
    </script>

    <?php if (isset($additionalJs)): ?>
    <?= $additionalJs ?>
    <?php endif; ?>
</body>
</html>
