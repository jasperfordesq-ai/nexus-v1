<?php
/**
 * Modern Layout - Utility Bar Partial
 * Contains: mode switcher, federation dropdown, create dropdown, admin links, notifications, user dropdown
 *
 * Variables expected:
 * - $mode: current theme mode (dark/light)
 */

// Platform Switcher - God users only
$showPlatform = !empty($_SESSION['is_god']);
// Detect protocol for tenants with custom domains
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
$protocol = $isSecure ? 'https://' : 'http://';
?>
<nav class="nexus-utility-bar">
    <div class="left-utils">
        <!-- Try New Frontend Button -->
        <?php
        $reactSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
        $reactPath = $reactSlug ? ('/' . $reactSlug . '/dashboard') : '/';
        ?>
        <a href="https://app.project-nexus.ie<?= $reactPath ?>" class="try-new-frontend-btn" target="_blank" rel="noopener" title="Try our new React-powered frontend">
            <i class="fa-solid fa-sparkles sparkle-icon"></i>
            <span class="btn-text">Try the New Experience</span>
        </a>

        <?php if ($showPlatform): ?>
            <div class="htb-dropdown">
                <button class="util-link platform-dropdown-btn">Platform <span class="htb-arrow">▾</span></button>
                <div class="htb-dropdown-content platform-dropdown">
                    <?php foreach (\Nexus\Models\Tenant::all() as $pt):
                        if ($pt['domain']) {
                            // Tenant has its own domain (uses current protocol)
                            $link = $protocol . $pt['domain'];
                        } else {
                            // Tenant uses slug - use relative path (works on any domain)
                            $link = '/' . $pt['slug'];
                            if ($pt['id'] == 1) $link = '/';
                        }
                    ?>
                        <a href="<?= $link ?>"><?= htmlspecialchars($pt['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="right-header-utils">
        <!-- Mode Switcher - Light/Dark -->
        <button onclick="toggleMode()" class="mode-switcher" aria-label="Switch between light and dark mode" title="<?= $mode === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode' ?>">
            <span class="mode-icon-container <?= $mode === 'dark' ? 'dark-mode' : 'light-mode' ?>" id="modeIconContainer">
                <i class="fa-solid <?= $mode === 'dark' ? 'fa-moon' : 'fa-sun' ?> mode-icon" id="modeIcon"></i>
            </span>
            <span class="mode-label" id="modeLabel"><?= $mode === 'dark' ? 'Dark Mode' : 'Light Mode' ?></span>
            <i class="fa-solid fa-chevron-right mode-arrow"></i>
        </button>

        <?php if (!empty($GLOBALS['showLayoutSwitcher'])): ?>
        <!-- Layout Switcher - Switch to Accessible Theme -->
        <a href="#" data-layout-switcher="civicone" class="util-link layout-switcher-link" title="Switch to Accessible (GOV.UK) Theme">
            <i class="fa-solid fa-universal-access"></i>
            <span class="layout-switcher-label">Accessible</span>
        </a>
        <?php endif; ?>

        <?php
        // Federation menu - visible to all users (including guests) when federation is enabled
        $hasFederationUtilBar = false;
        if (class_exists('\Nexus\Services\FederationFeatureService')) {
            try {
                $hasFederationUtilBar = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
            } catch (\Exception $e) {
                $hasFederationUtilBar = false;
            }
        }
        if ($hasFederationUtilBar): ?>
            <div class="htb-dropdown">
                <button class="util-link federation-dropdown-btn">
                    <i class="fa-solid fa-globe"></i>Partner Communities <span class="htb-arrow">▾</span>
                </button>
                <div class="htb-dropdown-content federation-dropdown">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation" class="federation-hub-link">
                        <i class="fa-solid fa-house"></i>Partner Communities Hub
                    </a>
                    <div class="layout-divider"></div>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/members">
                        <i class="fa-solid fa-user-group federation-menu-icon federation-menu-icon--members"></i>Members
                    </a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/listings">
                        <i class="fa-solid fa-hand-holding-heart federation-menu-icon federation-menu-icon--listings"></i>Listings
                    </a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/events">
                        <i class="fa-solid fa-calendar-days federation-menu-icon federation-menu-icon--events"></i>Events
                    </a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/groups">
                        <i class="fa-solid fa-users federation-menu-icon federation-menu-icon--groups"></i>Groups
                    </a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/messages">
                        <i class="fa-solid fa-envelope federation-menu-icon federation-menu-icon--messages"></i>Messages
                    </a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/transactions">
                        <i class="fa-solid fa-coins federation-menu-icon federation-menu-icon--transactions"></i>Transactions
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="layout-divider"></div>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings?section=federation">
                        <i class="fa-solid fa-sliders federation-menu-icon federation-menu-icon--settings"></i>Settings
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="htb-dropdown">
                <button class="util-link create-dropdown-btn">+ Create <span class="htb-arrow">▾</span></button>
                <div class="htb-dropdown-content">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=post">📝 New Post</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=listing">🎁 New Listing</a>
                    <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=event">📅 New Event</a>
                    <?php endif; ?>
                    <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=volunteer">🤝 Volunteer Opp</a>
                    <?php endif; ?>
                    <?php if (Nexus\Core\TenantContext::hasFeature('polls')): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=poll">📊 New Poll</a>
                    <?php endif; ?>
                    <?php if (Nexus\Core\TenantContext::hasFeature('goals')): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=goal">🎯 New Goal</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'newsletter_admin')): ?>
            <!-- Newsletter Admin - Limited Access -->
            <div class="htb-dropdown">
                <button class="util-link newsletter-dropdown-btn">Newsletter <span class="htb-arrow">▾</span></button>
                <div class="htb-dropdown-content">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters">All Newsletters</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/create">Create Newsletter</a>
                    <div class="layout-divider"></div>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/subscribers">Subscribers</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/segments">Segments</a>
                </div>
            </div>
        <?php elseif ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin" class="util-link admin-link">Admin</a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/group-ranking" class="util-link group-ranking-link" title="Smart Group Ranking">
                <i class="fa-solid fa-chart-line"></i> Ranking
            </a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])):
            // Notification Logic
            $nUserId = $_SESSION['user_id'];
            $nUnread = \Nexus\Models\Notification::countUnread($nUserId);
            $nRecent = \Nexus\Models\Notification::getLatest($nUserId, 5);

            // Message count logic
            $msgUnread = 0;
            try {
                if (class_exists('Nexus\Models\MessageThread')) {
                    $msgThreads = Nexus\Models\MessageThread::getForUser($nUserId);
                    foreach ($msgThreads as $msgThread) {
                        if (!empty($msgThread['unread_count'])) {
                            $msgUnread += (int)$msgThread['unread_count'];
                        }
                    }
                }
            } catch (Exception $e) {
                $msgUnread = 0;
            }
        ?>
            <!-- Messages Icon -->
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages" class="nexus-header-icon-btn" title="Messages">
                <i class="fa-solid fa-envelope"></i>
                <?php if ($msgUnread > 0): ?>
                    <span class="nexus-header-icon-badge"><?= $msgUnread > 99 ? '99+' : $msgUnread ?></span>
                <?php endif; ?>
            </a>

            <!-- Notifications Bell -->
            <button class="nexus-header-icon-btn" title="Notifications" onclick="window.nexusNotifDrawer.open()">
                <i class="fa-solid fa-bell"></i>
                <?php if ($nUnread > 0): ?>
                    <span class="nexus-notif-indicator"></span>
                <?php endif; ?>
            </button>

            <!-- Notifications Drawer (slides in from right) -->
            <div id="notif-drawer-overlay" class="notif-drawer-overlay" onclick="window.nexusNotifDrawer.close()"></div>
            <aside id="notif-drawer" class="notif-drawer">
                <div class="notif-drawer-header">
                    <span>NOTIFICATIONS</span>
                    <button class="notif-drawer-close" onclick="window.nexusNotifDrawer.close()" aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div id="nexus-notif-list" class="notif-drawer-list">
                    <?php if (empty($nRecent)): ?>
                        <div class="notif-drawer-empty">
                            <div class="notif-icon"><i class="fa-regular fa-bell-slash"></i></div>
                            <div class="notif-text">No notifications yet</div>
                        </div>
                    <?php else: ?>
                        <?php
                        $notifBasePath = Nexus\Core\TenantContext::getBasePath();
                        foreach ($nRecent as $n):
                            // Ensure link uses basePath if it's a relative path
                            $notifLink = $n['link'] ?: '#';
                            if ($notifLink !== '#' && strpos($notifLink, 'http') !== 0 && strpos($notifLink, $notifBasePath) !== 0) {
                                // Relative path - prepend basePath
                                $notifLink = $notifBasePath . $notifLink;
                            }
                        ?>
                            <a href="<?= htmlspecialchars($notifLink) ?>" data-notif-id="<?= $n['id'] ?>" class="notif-drawer-item<?= $n['is_read'] ? ' is-read' : '' ?>">
                                <div class="notif-message">
                                    <?= htmlspecialchars($n['message']) ?>
                                </div>
                                <div class="notif-time">
                                    <i class="fa-regular fa-clock"></i> <?= date('M j, g:i a', strtotime($n['created_at'])) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="notif-drawer-footer">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/notifications">
                        <i class="fa-solid fa-list"></i> View all
                    </a>
                    <button type="button" onclick="window.nexusNotifications.markAllRead(this);">
                        <i class="fa-solid fa-check-double"></i> Mark all read
                    </button>
                </div>
            </aside>

            <!-- User Avatar Dropdown (Premium) -->
            <div class="htb-dropdown desktop-only user-dropdown">
                <button class="util-link user-dropdown-btn">
                    <img src="<?= $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp' ?>"
                        alt="Profile"
                        class="user-avatar">
                    <span class="user-name"><?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?></span>
                    <span class="htb-arrow">▾</span>
                </button>
                <div class="htb-dropdown-content">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $_SESSION['user_id'] ?>">
                        <i class="fa-solid fa-user user-menu-icon user-menu-icon--profile"></i>My Profile
                    </a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/dashboard">
                        <i class="fa-solid fa-gauge user-menu-icon user-menu-icon--dashboard"></i>Dashboard
                    </a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/wallet">
                        <i class="fa-solid fa-wallet user-menu-icon user-menu-icon--wallet"></i>Wallet
                    </a>
                    <div class="user-menu-divider"></div>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/logout" class="logout-link">
                        <i class="fa-solid fa-right-from-bracket user-menu-icon"></i>Sign Out
                    </a>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="util-link">Login</a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register" class="util-link auth-link--join">Join</a>
        <?php endif; ?>
    </div>
</nav>
