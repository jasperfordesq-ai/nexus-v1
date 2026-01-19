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
        document.body.classList.add('mobile-notifications-open');
    }
};

window.closeMobileNotifications = function() {
    const sheet = document.getElementById('mobileNotifications');
    if (sheet) {
        sheet.classList.remove('active');
        document.body.classList.remove('mobile-notifications-open');
    }
};
</script>
<style>
/* FIX: Remove transform from html that breaks position:fixed */
html[data-layout] { transform: none !important; }

/* Hide on desktop by default */
.mobile-tab-bar { display: none; }

/* CRITICAL: Hide ALL legacy navigation on mobile including drawer AND header hamburger */
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

    /* Hide header hamburger menu buttons - we have Menu in bottom tab bar */
    .nexus-menu-btn,
    #civic-menu-toggle {
        display: none !important;
    }

    /* Show mobile tab bar */
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
    .mobile-tab-item[type="button"],
    button.mobile-tab-item {
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        margin: 0;
        font-family: inherit;
        outline: none;
        -webkit-appearance: none;
        appearance: none;
    }
}
</style>
<!-- Mobile Bottom Tab Bar -->
<nav class="mobile-tab-bar" id="mobileTabBar" role="navigation" aria-label="Mobile navigation">
    <div class="mobile-tab-bar-inner">
        <a href="<?= $base ?>/" class="mobile-tab-item<?= $isHomeActiveTab ? ' active' : '' ?>" aria-label="Home"<?= $isHomeActiveTab ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            <span>Home</span>
        </a>
        <a href="<?= $base ?>/listings" class="mobile-tab-item<?= $isListingsActiveTab ? ' active' : '' ?>" aria-label="Listings"<?= $isListingsActiveTab ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
            <span>Listings</span>
        </a>
        <a href="<?= $base ?>/compose" class="mobile-tab-item create-btn" aria-label="Create">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="create-icon" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            <span>Create</span>
        </a>
        <a href="<?= $base ?>/messages" class="mobile-tab-item<?= $isMessagesActiveTab ? ' active' : '' ?>" aria-label="Messages"<?= $isMessagesActiveTab ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
            <?php if ($msgCount > 0): ?>
            <span class="mobile-tab-badge" aria-hidden="true"><?= $msgCount > 99 ? '99+' : $msgCount ?></span>
            <?php endif; ?>
            <span>Messages</span>
        </a>
        <button type="button" class="mobile-tab-item" aria-label="Menu<?= ($notifCount > 0) ? ', ' . $notifCount . ' notifications' : '' ?>" onclick="openMobileMenu()">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
            <?php if ($notifCount > 0): ?>
            <span class="mobile-tab-badge" aria-hidden="true"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
            <?php endif; ?>
            <span>Menu</span>
        </button>
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
        <!-- Main Navigation -->
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Navigate</div>
            <a href="<?= $base ?>/" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-house"></i>
                Home
            </a>
            <a href="<?= $base ?>/listings" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-hand-holding-heart"></i>
                Listings
            </a>
            <a href="<?= $base ?>/community-groups" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-users"></i>
                Community Groups
            </a>
            <a href="<?= $base ?>/groups" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-map-pin"></i>
                Local Hubs
            </a>
            <a href="<?= $base ?>/members" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-user-group"></i>
                Members
            </a>
            <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
            <a href="<?= $base ?>/events" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-calendar-days"></i>
                Events
            </a>
            <?php endif; ?>
            <?php if (\Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
            <a href="<?= $base ?>/volunteering" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-hands-helping"></i>
                Volunteering
            </a>
            <?php endif; ?>
        </div>

        <!-- Explore Section -->
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Explore</div>
            <?php if (\Nexus\Core\TenantContext::hasFeature('polls')): ?>
            <a href="<?= $base ?>/polls" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-square-poll-vertical"></i>
                Polls
            </a>
            <?php endif; ?>
            <?php if (\Nexus\Core\TenantContext::hasFeature('goals')): ?>
            <a href="<?= $base ?>/goals" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-bullseye"></i>
                Goals
            </a>
            <?php endif; ?>
            <a href="<?= $base ?>/leaderboard" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-trophy"></i>
                Leaderboards
            </a>
            <a href="<?= $base ?>/achievements" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-medal"></i>
                Achievements
            </a>
            <a href="<?= $base ?>/matches" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                Smart Matching
            </a>
            <a href="<?= $base ?>/ai" class="mobile-menu-item" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); font-weight:600;" onclick="closeMobileMenu()">
                <i class="fa-solid fa-robot"></i>
                AI Assistant
            </a>
        </div>

        <!-- About Section -->
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">About</div>
            <?php if (\Nexus\Core\TenantContext::hasFeature('blog')): ?>
            <a href="<?= $base ?>/news" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-newspaper"></i>
                Latest News
            </a>
            <?php endif; ?>
            <?php
            // Custom pages for About section
            $mobilePages = \Nexus\Core\TenantContext::getCustomPages('modern');
            $aboutPageNames = ['about us', 'our story', 'about story', 'timebanking guide', 'partner with us', 'partner', 'social prescribing', 'timebanking faqs', 'timebanking faq', 'faq', 'impact summary', 'impact report', 'strategic plan'];
            $pageIcons = [
                'about us' => 'fa-solid fa-heart',
                'our story' => 'fa-solid fa-heart',
                'about story' => 'fa-solid fa-heart',
                'timebanking guide' => 'fa-solid fa-book-open',
                'partner' => 'fa-solid fa-handshake',
                'partner with us' => 'fa-solid fa-handshake',
                'social prescribing' => 'fa-solid fa-hand-holding-medical',
                'faq' => 'fa-solid fa-circle-question',
                'timebanking faq' => 'fa-solid fa-circle-question',
                'timebanking faqs' => 'fa-solid fa-circle-question',
                'impact summary' => 'fa-solid fa-leaf',
                'impact report' => 'fa-solid fa-file-contract',
                'strategic plan' => 'fa-solid fa-route',
            ];
            foreach ($mobilePages as $page):
                $pageName = strtolower($page['name']);
                if (!in_array($pageName, $aboutPageNames)) continue;
                $icon = $pageIcons[$pageName] ?? 'fa-solid fa-file-lines';
            ?>
            <a href="<?= htmlspecialchars($page['url']) ?>" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="<?= $icon ?>"></i>
                <?= htmlspecialchars($page['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>

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

        <!-- Your Account -->
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Your Account</div>
            <a href="<?= $base ?>/profile/<?= $userId ?>" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-user"></i>
                Profile
            </a>
            <a href="<?= $base ?>/dashboard" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-gauge-high"></i>
                Dashboard
            </a>
            <a href="<?= $base ?>/wallet" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-wallet"></i>
                Wallet
            </a>
            <a href="<?= $base ?>/settings" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-gear"></i>
                Settings
            </a>
        </div>
        <?php endif; ?>


        <!-- Help & Support -->
        <div class="mobile-menu-section">
            <div class="mobile-menu-section-title">Help & Support</div>
            <a href="<?= $base ?>/help" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-circle-question" style="color: #f97316;"></i>
                Help Center
            </a>
            <a href="<?= $base ?>/contact" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-envelope" style="color: #3b82f6;"></i>
                Contact Us
            </a>
            <a href="<?= $base ?>/accessibility" class="mobile-menu-item" onclick="closeMobileMenu()">
                <i class="fa-solid fa-universal-access" style="color: #10b981;"></i>
                Accessibility
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
        if (originalOpenMenu) originalOpenMenu();
    };

    window.closeMobileMenu = function() {
        Haptics.light();
        if (originalCloseMenu) originalCloseMenu();
    };

    window.openMobileNotifications = function() {
        Haptics.light();
        if (originalOpenNotif) originalOpenNotif();
    };

    window.closeMobileNotifications = function() {
        Haptics.light();
        if (originalCloseNotif) originalCloseNotif();
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
