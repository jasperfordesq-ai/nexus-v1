    <!-- 1. Utility Bar (Top Row) - WCAG 2.1 AA Compliant -->
    <nav class="civic-utility-bar" aria-label="Utility navigation">
        <div class="civic-container civic-utility-wrapper">

            <!-- Platform Dropdown - Public to everyone -->
            <?php
            $showPlatform = true; // Made public to everyone
            if ($showPlatform):
            ?>
                <div class="civic-dropdown civic-dropdown--left">
                    <button class="civic-utility-link civic-utility-btn civic-utility-btn--uppercase" aria-haspopup="menu" aria-expanded="false" aria-controls="platform-dropdown-menu">
                        Platform <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" id="platform-dropdown-menu" role="menu">
                        <?php
                        $tenants = [];
                        try {
                            $tenants = \Nexus\Models\Tenant::all() ?? [];
                        } catch (\Exception $e) {
                            $tenants = [];
                        }
                        foreach ($tenants as $pt):
                            if (!empty($pt['domain'])) {
                                $link = 'https://' . $pt['domain'];
                            } else {
                                $link = '/' . ($pt['slug'] ?? '');
                                if (($pt['id'] ?? 0) == 1) $link = '/';
                            }
                        ?>
                            <a href="<?= htmlspecialchars($link) ?>" role="menuitem"><?= htmlspecialchars($pt['name'] ?? 'Unknown') ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Dark Mode Toggle -->
            <button id="civic-theme-toggle" class="civic-utility-link civic-utility-btn" aria-label="Toggle High Contrast">
                <span class="icon">◑</span> Contrast
            </button>

            <!-- Theme Switcher - Visible for everyone on desktop, admins only on mobile -->
            <?php
            // VISIBILITY LOGIC: Hide on 'public-sector-demo' tenant only
            $currentSlug = '';
            if (class_exists('\Nexus\Core\TenantContext')) {
                $currentSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
            }
            if ($currentSlug !== 'public-sector-demo'):
            ?>
                <?php
                // Layout dropdown removed - now using banner at top of page
                ?>
            <?php endif; ?>


            <!-- Auth / User Links -->
            <?php if (isset($_SESSION['user_id'])): ?>

                <!-- REMOVED: Create Dropdown (violates Rule HL-003) -->
                <!-- Moved to floating action button or page content -->
                <!-- See: docs/HEADER_FIX_ACTION_PLAN_2026-01-20.md -->

                <!-- REMOVED: Federation Dropdown (violates Section 9B Rule FS-003) -->
                <!-- Moved to federation-scope-switcher.php partial -->
                <!-- Federation scope switcher appears between header and main content on /federation/* pages -->
                <!-- See: docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md Section 9B.2 -->

                <?php if ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'newsletter_admin')): ?>
                    <!-- Newsletter Admin - Limited Access (Matches Modern) -->
                    <div class="civic-dropdown civic-dropdown--right">
                        <button class="civic-utility-link civic-utility-btn civic-utility-btn--newsletter" aria-haspopup="menu" aria-expanded="false">
                            Newsletter <span class="civic-arrow" aria-hidden="true">▾</span>
                        </button>
                        <div class="civic-dropdown-content" role="menu">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters" role="menuitem">All Newsletters</a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/create" role="menuitem">Create Newsletter</a>
                            <div class="civic-dropdown-separator" role="separator"></div>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/subscribers" role="menuitem">Subscribers</a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/newsletters/segments" role="menuitem">Segments</a>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- REMOVED: Admin and Ranking links (violates Rule HL-003 - clutters utility bar) -->
                <!-- Moved to user avatar dropdown below (lines 229+) -->

                <?php
                // Notification & Message counts (matches Modern header)
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

                <!-- Messages Icon (Matches Modern) -->
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/messages" class="civic-utility-link nexus-header-icon-btn badge-container" title="Messages">
                    <span class="dashicons dashicons-email" aria-hidden="true"></span>
                    <?php if ($msgUnread > 0): ?>
                        <span class="badge badge--danger badge--sm notification-badge"><?= $msgUnread > 99 ? '99+' : $msgUnread ?></span>
                    <?php endif; ?>
                </a>

                <!-- Notifications Bell (triggers drawer - Matches Modern) -->
                <button class="civic-utility-link civic-utility-btn--notification nexus-header-icon-btn badge-container" title="Notifications" data-action="open-notifications">
                    <span class="dashicons dashicons-bell" aria-hidden="true"></span>
                    <?php if ($nUnread > 0): ?>
                        <span id="nexus-bell-badge" class="badge badge--danger badge--sm notification-badge"><?= $nUnread > 99 ? '99+' : $nUnread ?></span>
                    <?php endif; ?>
                </button>

                <!-- User Avatar Dropdown (Premium - Matches Modern) -->
                <div class="civic-dropdown civic-dropdown--right civic-user-dropdown desktop-only-dd">
                    <button class="civic-utility-link civic-user-avatar-btn" aria-haspopup="menu" aria-expanded="false">
                        <img src="<?= $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp' ?>" alt="Profile">
                        <span><?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?></span>
                        <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" role="menu">
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $_SESSION['user_id'] ?>" role="menuitem">
                            <i class="fa-solid fa-user civic-menu-icon civic-menu-icon--brand"></i>My Profile
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/dashboard" role="menuitem">
                            <i class="fa-solid fa-gauge civic-menu-icon civic-menu-icon--purple"></i>Dashboard
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet" role="menuitem">
                            <i class="fa-solid fa-wallet civic-menu-icon civic-menu-icon--green"></i>Wallet
                        </a>

                        <?php
                        // Admin and Ranking links - Moved from utility bar (Section 9A compliance)
                        if ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])):
                        ?>
                            <div class="civic-dropdown-separator" role="separator"></div>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin" role="menuitem">
                                <i class="fa-solid fa-user-shield civic-menu-icon civic-menu-icon--brand"></i>Admin Panel
                            </a>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/group-ranking" role="menuitem" title="Smart Group Ranking">
                                <i class="fa-solid fa-chart-line civic-menu-icon civic-menu-icon--brand"></i>Group Ranking
                            </a>
                        <?php endif; ?>

                        <div class="civic-dropdown-separator" role="separator"></div>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/logout" role="menuitem" class="civic-utility-link--logout">
                            <i class="fa-solid fa-right-from-bracket civic-menu-icon"></i>Sign Out
                        </a>
                    </div>
                </div>
                <!-- Mobile fallback links -->
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/dashboard" class="civic-utility-link mobile-only-link mobile-only-link--dashboard">Dashboard</a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/logout" class="civic-utility-link mobile-only-link mobile-only-link--logout">Sign Out</a>
            <?php else: ?>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" class="civic-utility-link">Sign In</a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="civic-utility-link mobile-only-link--dashboard">Join Now</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Notifications Drawer (slides in from right - Matches Modern) -->
            <div id="notif-drawer-overlay" class="notif-drawer-overlay" onclick="window.nexusNotifDrawer.close()"></div>
            <aside id="notif-drawer" class="notif-drawer" role="dialog" aria-labelledby="notif-drawer-title" aria-modal="true">
                <div class="notif-drawer-header">
                    <span id="notif-drawer-title">NOTIFICATIONS</span>
                    <button class="notif-drawer-close" onclick="window.nexusNotifDrawer.close()" aria-label="Close notifications">
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
        <?php endif; ?>
    </nav><!-- End Utility Navigation -->
