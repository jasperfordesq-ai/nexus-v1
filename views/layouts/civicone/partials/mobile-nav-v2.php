<?php
/**
 * Modern Mobile Navigation v2
 * Full-screen, native app-like mobile navigation
 * Inspired by Instagram, TikTok, and iOS design patterns
 */

// User data
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'Guest';
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? '';
$userBalance = $_SESSION['user_balance'] ?? 0;
$isAdmin = ($userRole === 'admin') || !empty($_SESSION['is_super_admin']);

// Get notifications count
$notifCount = 0;
$notifications = [];
if ($isLoggedIn && class_exists('\Nexus\Models\Notification')) {
    try {
        $notifCount = \Nexus\Models\Notification::countUnread($userId);
        $notifications = \Nexus\Models\Notification::getLatest($userId, 10);
    } catch (\Exception $e) {
        $notifCount = 0;
        $notifications = [];
    }
}

// Get message count
$msgCount = 0;
if ($isLoggedIn && class_exists('\Nexus\Models\MessageThread')) {
    try {
        $threads = \Nexus\Models\MessageThread::getForUser($userId);
        foreach ($threads as $thread) {
            if (!empty($thread['unread_count'])) {
                $msgCount += (int)$thread['unread_count'];
            }
        }
    } catch (\Exception $e) {
        $msgCount = 0;
    }
}

// Base path
$base = \Nexus\Core\TenantContext::getBasePath();

// Current path for active state
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentPath = rtrim($currentPath, '/');
$basePath = rtrim($base, '/');

if (!function_exists('isNavActive')) {
    function isNavActive($path, $currentPath, $basePath) {
        $fullPath = $basePath . $path;
        if ($path === '' || $path === '/') {
            return $currentPath === $basePath || $currentPath === $basePath . '/' || $currentPath === $basePath . '/home';
        }
        return strpos($currentPath, $fullPath) === 0;
    }
}
?>

<?php
// Pre-calculate active states for cleaner template
$isHomeActiveTab = isNavActive('/', $currentPath, $basePath);
$isListingsActiveTab = isNavActive('/listings', $currentPath, $basePath);
$isMessagesActiveTab = isNavActive('/messages', $currentPath, $basePath);
$isProfileActiveTab = isNavActive('/profile', $currentPath, $basePath) || isNavActive('/dashboard', $currentPath, $basePath);
?>
<!-- Mobile Tab Bar -->
<script>
// Define mobile menu functions early to prevent ReferenceError
// These need to be available immediately for onclick handlers
window.openMobileMenu = function() {
    const menu = document.getElementById('mobileMenu');
    if (menu) {
        menu.classList.add('active');
        document.body.classList.add('mobile-menu-open');
    }
};

window.closeMobileMenu = function() {
    const menu = document.getElementById('mobileMenu');
    if (menu) {
        menu.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
    }
};

window.openMobileNotifications = function() {
    const sheet = document.getElementById('mobileNotifications');
    if (sheet) {
        sheet.classList.add('active');
        document.body.classList.add('mobile-menu-open');
    }
};

window.closeMobileNotifications = function() {
    const sheet = document.getElementById('mobileNotifications');
    if (sheet) {
        sheet.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
    }
};
</script>
<style>
/* CRITICAL: Hide ALL legacy navigation on mobile including drawer */
@media (max-width: 1024px) {
    .nexus-native-nav,
    .nexus-native-nav-inner,
    .nexus-bottom-nav,
    .nexus-quick-fab,
    .nexus-native-drawer,
    .nexus-native-drawer-overlay {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
        transform: translateX(-100%) !important;
    }
}

@media (max-width: 1024px) {
    .mobile-tab-bar {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 99999 !important;
        height: 84px !important;
        background: rgba(255, 255, 255, 0.98) !important;
        border-top: 1px solid rgba(0, 0, 0, 0.1) !important;
        -webkit-backdrop-filter: saturate(180%) blur(20px) !important;
        backdrop-filter: saturate(180%) blur(20px) !important;
    }
    [data-theme="dark"] .mobile-tab-bar {
        background: rgba(28, 28, 30, 0.98) !important;
        border-top-color: rgba(255, 255, 255, 0.1) !important;
    }
    body {
        padding-bottom: 90px !important;
    }
    /* Ensure menu button looks like other tab items */
    .mobile-tab-item[type="button"] {
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        margin: 0;
    }
}
</style>
<nav class="mobile-tab-bar" id="mobileTabBar" role="navigation" aria-label="Mobile navigation">
    <div class="mobile-tab-bar-inner">
        <?php
        // MenuManager Integration - Mobile Tab Bar
        $tabMenus = \Nexus\Core\MenuManager::getMenu('mobile-tabs', 'modern');

        if (!empty($tabMenus)):
            foreach ($tabMenus as $tabMenu):
                foreach ($tabMenu['items'] as $tabItem):
                    // Determine if this tab is active
                    $isActive = false;
                    $tabPath = parse_url($tabItem['url'], PHP_URL_PATH);
                    if ($tabPath === '/' || $tabPath === '') {
                        $isActive = $isHomeActiveTab;
                    } elseif (strpos($tabPath, '/listings') !== false) {
                        $isActive = $isListingsActiveTab;
                    } elseif (strpos($tabPath, '/messages') !== false) {
                        $isActive = $isMessagesActiveTab;
                    } elseif (strpos($tabPath, '/profile') !== false || strpos($tabPath, '/dashboard') !== false) {
                        $isActive = $isProfileActiveTab;
                    }

                    $activeClass = $isActive ? ' active' : '';
                    $ariaCurrent = $isActive ? ' aria-current="page"' : '';

                    // Special handling for create button
                    $isCreateTab = stripos($tabItem['label'], 'create') !== false;
                    $cssClass = $tabItem['css_class'] ?? 'mobile-tab-item';
                    if ($isCreateTab) {
                        $cssClass .= ' create-btn';
                    }
        ?>
        <a href="<?= htmlspecialchars($tabItem['url']) ?>" class="<?= htmlspecialchars($cssClass . $activeClass) ?>" aria-label="<?= htmlspecialchars($tabItem['label']) ?>"<?= $ariaCurrent ?>>
            <?php if (!empty($tabItem['icon'])): ?>
                <?php if ($isCreateTab): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="create-icon" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                <?php elseif (stripos($tabItem['label'], 'profile') !== false && $isLoggedIn): ?>
                    <img src="<?= htmlspecialchars($userAvatar) ?>" alt="" class="mobile-tab-avatar" aria-hidden="true">
                <?php else: ?>
                    <i class="<?= htmlspecialchars($tabItem['icon']) ?>" aria-hidden="true"></i>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (stripos($tabItem['label'], 'messages') !== false && $msgCount > 0): ?>
            <span class="mobile-tab-badge" aria-hidden="true"><?= $msgCount > 99 ? '99+' : $msgCount ?></span>
            <?php endif; ?>
            <span><?= htmlspecialchars($tabItem['label']) ?></span>
        </a>
        <?php
                endforeach;
            endforeach;
        else:
            // Fallback to hardcoded tabs
        ?>
        <a href="<?= $base ?>/" class="mobile-tab-item <?= $isHomeActiveTab ? 'active' : '' ?>" aria-label="Home"<?= $isHomeActiveTab ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            <span>Home</span>
        </a>
        <a href="<?= $base ?>/listings" class="mobile-tab-item <?= $isListingsActiveTab ? 'active' : '' ?>" aria-label="Listings"<?= $isListingsActiveTab ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
            <span>Listings</span>
        </a>
        <a href="<?= $base ?>/compose" class="mobile-tab-item create-btn" aria-label="Create">
            <i class="fa-solid fa-plus-circle" aria-hidden="true"></i>
            <span>Create</span>
        </a>
        <a href="<?= $base ?>/messages" class="mobile-tab-item <?= $isMessagesActiveTab ? 'active' : '' ?>" aria-label="Messages"<?= $isMessagesActiveTab ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            <span>Messages</span>
        </a>
        <?php if ($isLoggedIn): ?>
        <a href="<?= $base ?>/profile/<?= $userId ?>" class="mobile-tab-item <?= $isProfileActiveTab ? 'active' : '' ?>" aria-label="Profile"<?= $isProfileActiveTab ? ' aria-current="page"' : '' ?>>
            <?php if (!empty($userAvatar)): ?>
                <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Profile" class="mobile-tab-avatar" aria-hidden="true">
            <?php else: ?>
                <i class="fa-solid fa-user" aria-hidden="true"></i>
            <?php endif; ?>
            <span>Profile</span>
        </a>
        <?php else: ?>
        <a href="<?= $base ?>/login" class="mobile-tab-item" aria-label="Sign In">
            <i class="fa-solid fa-user" aria-hidden="true"></i>
            <span>Sign In</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</nav>


<!-- Full-Screen Menu -->
<div class="mobile-fullscreen-menu" id="mobileMenu">
    <!-- Enhanced Gradient Header -->
    <div class="mobile-menu-header">
        <div class="mobile-menu-header-bg"></div>
        <button class="mobile-menu-close" onclick="closeMobileMenu()" aria-label="Close menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <?php if ($isLoggedIn): ?>
        <a href="<?= $base ?>/profile/<?= $userId ?>" class="mobile-menu-user" onclick="closeMobileMenu()">
            <div class="mobile-menu-avatar-wrapper">
                <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Your profile" class="mobile-menu-avatar">
                <div class="mobile-menu-avatar-ring"></div>
            </div>
            <div class="mobile-menu-user-info">
                <h3><?= htmlspecialchars($userName) ?></h3>
                <p><i class="fa-solid fa-arrow-right" style="font-size: 11px; margin-right: 4px;"></i>View profile</p>
            </div>
        </a>
        <?php else: ?>
        <a href="<?= $base ?>/login" class="mobile-menu-user" onclick="closeMobileMenu()">
            <div class="mobile-menu-avatar-wrapper">
                <div class="mobile-menu-avatar mobile-menu-avatar-guest">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="mobile-menu-avatar-ring"></div>
            </div>
            <div class="mobile-menu-user-info">
                <h3>Welcome</h3>
                <p><i class="fa-solid fa-arrow-right" style="font-size: 11px; margin-right: 4px;"></i>Sign in to get started</p>
            </div>
        </a>
        <?php endif; ?>
    </div>

    <!-- Quick Stats (Logged in only) -->
    <?php if ($isLoggedIn): ?>
    <div class="mobile-menu-stats">
        <a href="<?= $base ?>/wallet" class="mobile-menu-stat" onclick="closeMobileMenu()">
            <div class="mobile-menu-stat-value"><?= number_format($userBalance) ?></div>
            <div class="mobile-menu-stat-label">Credits</div>
        </a>
        <a href="<?= $base ?>/messages" class="mobile-menu-stat" onclick="closeMobileMenu()">
            <div class="mobile-menu-stat-value"><?= $msgCount > 0 ? $msgCount : '0' ?></div>
            <div class="mobile-menu-stat-label">Messages</div>
        </a>
        <a href="<?= $base ?>/dashboard?tab=notifications" class="mobile-menu-stat" onclick="closeMobileMenu()">
            <div class="mobile-menu-stat-value"><?= $notifCount > 0 ? $notifCount : '0' ?></div>
            <div class="mobile-menu-stat-label">Alerts</div>
        </a>
    </div>
    <?php endif; ?>

    <!-- Menu Body -->
    <div class="mobile-menu-body">
        <!-- Main Navigation - MenuManager Integration -->
        <?php
        // Get menu from MenuManager database
        $mobileMenus = \Nexus\Core\MenuManager::getMenu('header-main', 'modern');

        if (!empty($mobileMenus)):
            foreach ($mobileMenus as $menu):
                // Separate main items from dropdown children
                $mainItems = [];
                $exploreChildren = [];

                foreach ($menu['items'] as $item) {
                    if ($item['type'] === 'dropdown' && !empty($item['children'])) {
                        // This is the Explore dropdown - extract its children
                        $exploreChildren = $item['children'];
                    } else {
                        // Regular main nav item
                        $mainItems[] = $item;
                    }
                }
        ?>

        <!-- Main Navigation Section -->
        <?php if (!empty($mainItems)): ?>
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Main Navigation</div>
            <?php foreach ($mainItems as $item): ?>
            <a href="<?= htmlspecialchars($item['url']) ?>" class="mobile-menu-item" onclick="closeMobileMenu()">
                <?php if (!empty($item['icon'])): ?>
                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                <?php endif; ?>
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Explore Section -->
        <?php if (!empty($exploreChildren)): ?>
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Explore</div>
            <?php foreach ($exploreChildren as $item):
                $itemStyle = '';
                // Check for special highlighting (AI Assistant, Get App)
                if (stripos($item['label'], 'AI') !== false || stripos($item['label'], 'Assistant') !== false) {
                    $itemStyle = ' style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); font-weight:600;"';
                }
            ?>
            <a href="<?= htmlspecialchars($item['url']) ?>" class="mobile-menu-item"<?= $itemStyle ?> onclick="closeMobileMenu()">
                <?php if (!empty($item['icon'])): ?>
                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                <?php endif; ?>
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
            endforeach;
        else:
            // Fallback to Navigation class if MenuManager has no menus
            $navFile = __DIR__ . '/../../civicone/config/navigation.php';
            if (file_exists($navFile)) {
                require_once $navFile;
            }

            // Check if Navigation class exists
            if (class_exists('\Nexus\Config\Navigation')) {
                $mainNavItems = \Nexus\Config\Navigation::getMainNavItems();
                $exploreItems = \Nexus\Config\Navigation::getExploreItems();
            } else {
                // Ultimate fallback - hardcoded items
                $mainNavItems = [
                    ['label' => 'News Feed', 'url' => $base . '/', 'icon' => 'fa-solid fa-newspaper'],
                    ['label' => 'Listings', 'url' => $base . '/listings', 'icon' => 'fa-solid fa-hand-holding-heart'],
                    ['label' => 'Groups', 'url' => $base . '/groups', 'icon' => 'fa-solid fa-users'],
                ];
                $exploreItems = [
                    ['label' => 'Events', 'url' => $base . '/events', 'icon' => 'fa-solid fa-calendar'],
                    ['label' => 'Members', 'url' => $base . '/members', 'icon' => 'fa-solid fa-user-group'],
                ];
            }
        ?>
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Main Navigation</div>
            <?php foreach ($mainNavItems as $key => $item):
                if (class_exists('\Nexus\Config\Navigation') && !\Nexus\Config\Navigation::shouldShow($item)) continue;
            ?>
            <a href="<?= htmlspecialchars($item['url']) ?>" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Explore</div>
            <?php foreach ($exploreItems as $key => $item):
                if (class_exists('\Nexus\Config\Navigation') && !\Nexus\Config\Navigation::shouldShow($item)) continue;
            ?>
            <a href="<?= htmlspecialchars($item['url']) ?>" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($isLoggedIn && ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin']))): ?>
        <!-- Admin Section -->
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title" style="color: #ea580c;">Admin Tools</div>
            <a href="<?= $base ?>/admin" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-gauge-high"></i>
                Admin Dashboard
            </a>
        </div>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
        <!-- Create -->
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Create</div>
            <a href="<?= $base ?>/compose?tab=post" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-pen-to-square" style="color: #3b82f6;"></i>
                New Post
            </a>
            <a href="<?= $base ?>/compose?tab=listing" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-hand-holding-heart" style="color: #10b981;"></i>
                New Listing
            </a>
            <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
            <a href="<?= $base ?>/compose?tab=event" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-calendar-plus" style="color: #f59e0b;"></i>
                New Event
            </a>
            <?php endif; ?>
            <?php if (\Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
            <a href="<?= $base ?>/compose?tab=volunteer" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-hands-helping" style="color: #ec4899;"></i>
                Volunteer Opportunity
            </a>
            <?php endif; ?>
            <?php if (\Nexus\Core\TenantContext::hasFeature('polls')): ?>
            <a href="<?= $base ?>/compose?tab=poll" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-chart-bar" style="color: #8b5cf6;"></i>
                New Poll
            </a>
            <?php endif; ?>
            <?php if (\Nexus\Core\TenantContext::hasFeature('goals')): ?>
            <a href="<?= $base ?>/compose?tab=goal" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-bullseye" style="color: #ef4444;"></i>
                New Goal
            </a>
            <?php endif; ?>
        </div>

        <!-- Utility Bar Items - MenuManager Integration -->
        <?php
        // Try to get utility menu from MenuManager
        $utilityMenus = \Nexus\Core\MenuManager::getMenu('header-secondary', 'modern');
        $hasUtilityMenu = !empty($utilityMenus);

        if ($hasUtilityMenu):
            foreach ($utilityMenus as $utilMenu):
        ?>
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Your Account</div>
            <?php foreach ($utilMenu['items'] as $utilItem):
                // Resolve {user_id} placeholder
                $utilUrl = $utilItem['url'];
                if (strpos($utilUrl, '{user_id}') !== false) {
                    $utilUrl = str_replace('{user_id}', $userId ?? '', $utilUrl);
                }

                // Determine color
                $itemColor = '';
                if (stripos($utilItem['label'], 'admin') !== false) {
                    $itemColor = ' style="color: #ea580c;"';
                } elseif (stripos($utilItem['label'], 'sign out') !== false || stripos($utilItem['label'], 'logout') !== false) {
                    $itemColor = ' style="color: #ef4444;"';
                }
            ?>
            <a href="<?= htmlspecialchars($utilUrl) ?>" class="mobile-menu-item" onclick="closeMobileMenu()">
                <?php if (!empty($utilItem['icon'])): ?>
                <i class="<?= htmlspecialchars($utilItem['icon']) ?>"<?= $itemColor ?>></i>
                <?php endif; ?>
                <?= htmlspecialchars($utilItem['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php
            endforeach;
        else:
            // Fallback to Navigation class
            if (class_exists('\Nexus\Config\Navigation')) {
                $utilityItems = \Nexus\Config\Navigation::getUtilityItems();
            } else {
                $utilityItems = [
                    ['label' => 'Profile', 'url' => $base . '/profile/' . $userId, 'icon' => 'fa-solid fa-user'],
                    ['label' => 'Settings', 'url' => $base . '/settings', 'icon' => 'fa-solid fa-gear'],
                    ['label' => 'Logout', 'url' => $base . '/logout', 'icon' => 'fa-solid fa-arrow-right-from-bracket', 'color' => '#ef4444'],
                ];
            }
        ?>
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Your Account</div>
            <?php foreach ($utilityItems as $key => $item):
                if (class_exists('\Nexus\Config\Navigation') && !\Nexus\Config\Navigation::shouldShow($item)) continue;
                if ($item['is_icon_button'] ?? false) continue;
                $itemColor = isset($item['color']) ? ' style="color: ' . htmlspecialchars($item['color']) . ';"' : '';
            ?>
            <a href="<?= htmlspecialchars($item['url']) ?>" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="<?= htmlspecialchars($item['icon']) ?>"<?= $itemColor ?>></i>
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>


        <!-- New App Beta -->
        <div class="mobile-menu-section">
            <a href="?view=mobile" class="mobile-menu-item" style="background: linear-gradient(135deg, rgba(236, 72, 153, 0.1), rgba(219, 39, 119, 0.1)); border: 1px solid rgba(236, 72, 153, 0.2); border-radius: 12px; margin: 8px 16px;">
                <i class="fa-solid fa-mobile-screen-button" style="color: #ec4899;"></i>
                New App
                <span style="background: linear-gradient(135deg, #ec4899, #db2777); color: white; font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; margin-left: auto;">Beta</span>
            </a>
        </div>
    </div>

    <!-- Footer -->
    <div class="mobile-menu-footer">
        <a href="https://project-nexus.canny.io/" target="_blank" rel="noopener" class="mobile-menu-footer-btn" onclick="closeMobileMenu()">
            <i class="fa-solid fa-comment-dots"></i>
            Feedback
        </a>
        <button class="mobile-menu-footer-btn theme-toggle-inline" onclick="toggleTheme();">
            <i class="fa-solid <?= isset($_COOKIE['nexus_mode']) && $_COOKIE['nexus_mode'] === 'dark' ? 'fa-sun' : 'fa-moon' ?> theme-toggle-icon"></i>
            <span class="theme-toggle-text"><?= isset($_COOKIE['nexus_mode']) && $_COOKIE['nexus_mode'] === 'dark' ? 'Light' : 'Dark' ?></span>
        </button>
        <?php if ($isLoggedIn): ?>
        <a href="<?= $base ?>/logout" class="mobile-menu-footer-btn danger">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            Sign Out
        </a>
        <?php else: ?>
        <a href="<?= $base ?>/login" class="mobile-menu-footer-btn" onclick="closeMobileMenu()">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            Sign In
        </a>
        <?php endif; ?>
    </div>
</div>


<!-- Notification Sheet -->
<div class="mobile-notification-sheet" id="mobileNotifications">
    <div class="mobile-notification-backdrop" onclick="closeMobileNotifications()"></div>
    <div class="mobile-notification-container">
        <div class="mobile-notification-handle"></div>
        <div class="mobile-notification-header">
            <h2 class="mobile-notification-title">Notifications</h2>
            <div class="mobile-notification-actions">
                <?php if ($isLoggedIn && $notifCount > 0): ?>
                <button class="mobile-notification-action" onclick="markAllNotificationsRead()">Mark all read</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="mobile-notification-list">
            <?php if (empty($notifications)): ?>
            <div class="mobile-notification-empty">
                <i class="fa-regular fa-bell-slash"></i>
                <p>No notifications yet</p>
            </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                <a href="<?= htmlspecialchars($n['link'] ?: '#') ?>" class="mobile-notification-item <?= $n['is_read'] ? '' : 'unread' ?>" onclick="closeMobileNotifications()">
                    <div class="mobile-notification-dot"></div>
                    <div class="mobile-notification-content">
                        <p class="mobile-notification-message"><?= htmlspecialchars($n['message']) ?></p>
                        <span class="mobile-notification-time"><?= date('M j, g:i a', strtotime($n['created_at'])) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
// Haptic Feedback Helper
const Haptics = {
    // Check if vibration API is available
    isSupported: () => 'vibrate' in navigator,

    // Light tap feedback (for buttons, toggles)
    light: () => {
        if (Haptics.isSupported()) navigator.vibrate(10);
        // Also try Capacitor Haptics if available
        if (window.Capacitor?.Plugins?.Haptics) {
            window.Capacitor.Plugins.Haptics.impact({ style: 'light' });
        }
    },

    // Medium feedback (for selections, confirmations)
    medium: () => {
        if (Haptics.isSupported()) navigator.vibrate(20);
        if (window.Capacitor?.Plugins?.Haptics) {
            window.Capacitor.Plugins.Haptics.impact({ style: 'medium' });
        }
    },

    // Success feedback
    success: () => {
        if (Haptics.isSupported()) navigator.vibrate([10, 50, 10]);
        if (window.Capacitor?.Plugins?.Haptics) {
            window.Capacitor.Plugins.Haptics.notification({ type: 'success' });
        }
    }
};

// Enhance Mobile Menu Functions with Haptic Feedback
// Functions already defined at top of file, we're just adding haptics here
(function() {
    // Store original functions
    const originalOpenMenu = window.openMobileMenu;
    const originalCloseMenu = window.closeMobileMenu;
    const originalOpenNotif = window.openMobileNotifications;
    const originalCloseNotif = window.closeMobileNotifications;

    // Enhance with haptic feedback
    window.openMobileMenu = function() {
        Haptics.light();
        originalOpenMenu();
    };

    window.closeMobileMenu = function() {
        Haptics.light();
        originalCloseMenu();
    };

    window.openMobileNotifications = function() {
        Haptics.light();
        originalOpenNotif();
    };

    window.closeMobileNotifications = function() {
        Haptics.light();
        originalCloseNotif();
    };
})();

function markAllNotificationsRead() {
    Haptics.success();
    if (typeof window.nexusNotifications !== 'undefined' && window.nexusNotifications.markAllRead) {
        window.nexusNotifications.markAllRead();
    }
    // Visual feedback
    document.querySelectorAll('.mobile-notification-item.unread').forEach(item => {
        item.classList.remove('unread');
    });
    document.querySelectorAll('.mobile-tab-badge').forEach(badge => {
        badge.style.display = 'none';
    });
}

// Add haptic feedback to tab bar items
document.querySelectorAll('.mobile-tab-item').forEach(item => {
    item.addEventListener('click', () => Haptics.light());
});

// Add haptic feedback to menu items
document.querySelectorAll('.mobile-menu-item').forEach(item => {
    item.addEventListener('click', () => Haptics.light());
});

// Close menu on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMobileMenu();
        closeMobileNotifications();
    }
});

</script>
