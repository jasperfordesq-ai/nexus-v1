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

            <style>
                /* Force Dropdown Hover Logic */
                .civic-dropdown:hover .civic-dropdown-content {
                    display: block;
                    animation: fadeIn 0.2s;
                }
            </style>

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
                <div class="civic-dropdown civic-dropdown--right civic-interface-switcher">
                    <button class="civic-utility-link civic-utility-btn" aria-haspopup="menu" aria-expanded="false" aria-controls="interface-dropdown-menu">
                        Layout <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" id="interface-dropdown-menu" role="menu">
                        <?php
                        // Use LayoutHelper for consistent layout detection
                        $lay = \Nexus\Services\LayoutHelper::get();
                        ?>
                        <a href="?layout=modern" role="menuitem" <?= $lay === 'modern' ? 'aria-current="true"' : '' ?> style="<?= $lay === 'modern' ? 'font-weight:bold; color:var(--civic-brand, #00796B); background: rgba(0, 121, 107, 0.1);' : '' ?>">
                            <span style="display:inline-block; margin-right:8px;">✨</span> Modern UI
                            <?php if ($lay === 'modern'): ?>
                                <span style="float:right; color: var(--civic-brand, #00796B);">✓</span>
                            <?php endif; ?>
                        </a>
                        <a href="?layout=civicone" role="menuitem" <?= $lay === 'civicone' ? 'aria-current="true"' : '' ?> style="<?= $lay === 'civicone' ? 'font-weight:bold; color:var(--civic-brand, #00796B); background: rgba(0, 121, 107, 0.1);' : '' ?>">
                            <span style="display:inline-block; margin-right:8px;">♿</span> Accessible UI
                            <?php if ($lay === 'civicone'): ?>
                                <span style="float:right; color: var(--civic-brand, #00796B);">✓</span>
                            <?php endif; ?>
                        </a>

                        <?php
                        // Nexus Social: Available on Master (ID 1) and Hour Timebank
                        // Open to ALL users (Guest or Logged In)
                        $tId = \Nexus\Core\TenantContext::getId();
                        $isAllowedSocial = ($tId == 1) || ($currentSlug === 'hour-timebank' || $currentSlug === 'hour_timebank');

                        if ($isAllowedSocial):
                            $rootPath = \Nexus\Core\TenantContext::getBasePath();
                            if (empty($rootPath)) $rootPath = '/';

                        ?>
                            <div style="border-top:1px solid #e5e7eb; margin:5px 0;" role="separator"></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>


            <!-- Auth / User Links -->
            <?php if (isset($_SESSION['user_id'])): ?>

                <!-- Create Dropdown - SYNCHRONIZED WITH MODERN (uses /compose?tab= URLs) -->
                <div class="civic-dropdown civic-dropdown--right">
                    <button class="civic-utility-link civic-utility-btn civic-utility-btn--create" aria-haspopup="menu" aria-expanded="false" aria-controls="utility-create-dropdown-menu">
                        + Create <span class="civic-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="civic-dropdown-content" id="utility-create-dropdown-menu" role="menu">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=post" role="menuitem">📝 New Post</a>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=listing" role="menuitem">🎁 New Listing</a>
                        <?php if (Nexus\Core\TenantContext::hasFeature('events')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=event" role="menuitem">📅 New Event</a>
                        <?php endif; ?>
                        <?php if (Nexus\Core\TenantContext::hasFeature('volunteering')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=volunteer" role="menuitem">🤝 Volunteer Opp</a>
                        <?php endif; ?>
                        <?php if (Nexus\Core\TenantContext::hasFeature('polls')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=poll" role="menuitem">📊 New Poll</a>
                        <?php endif; ?>
                        <?php if (Nexus\Core\TenantContext::hasFeature('goals')): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?tab=goal" role="menuitem">🎯 New Goal</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                // Federation Dropdown - SYNCHRONIZED WITH MODERN
                $hasFederationUtilBar = false;
                if (class_exists('\Nexus\Services\FederationFeatureService')) {
                    try {
                        $hasFederationUtilBar = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
                    } catch (\Exception $e) {
                        $hasFederationUtilBar = false;
                    }
                }
                if ($hasFederationUtilBar): ?>
                    <div class="civic-dropdown civic-dropdown--right">
                        <button class="civic-utility-link civic-utility-btn civic-utility-btn--federation" aria-haspopup="menu" aria-expanded="false">
                            <i class="fa-solid fa-globe civic-menu-icon"></i>Partner Communities <span class="civic-arrow" aria-hidden="true">▾</span>
                        </button>
                        <div class="civic-dropdown-content" role="menu">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--purple"><i class="fa-solid fa-house"></i></span>Partner Communities Hub
                            </a>
                            <div class="civic-dropdown-separator" role="separator"></div>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/members" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--purple"><i class="fa-solid fa-user-group"></i></span>Members
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/listings" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--pink"><i class="fa-solid fa-hand-holding-heart"></i></span>Listings
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/events" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--amber"><i class="fa-solid fa-calendar-days"></i></span>Events
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/groups" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--indigo"><i class="fa-solid fa-users"></i></span>Groups
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/messages" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--blue"><i class="fa-solid fa-envelope"></i></span>Messages
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/federation/transactions" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--green"><i class="fa-solid fa-coins"></i></span>Transactions
                            </a>
                            <div class="civic-dropdown-separator" role="separator"></div>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings?section=federation" role="menuitem">
                                <span class="civic-menu-icon civic-menu-icon--gray"><i class="fa-solid fa-sliders"></i></span>Settings
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

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
                <?php elseif ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
                    <!-- Admin Links (Matches Modern Header) -->
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin" class="civic-utility-link civic-utility-btn civic-utility-btn--admin">Admin</a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/group-ranking" class="civic-utility-link civic-utility-btn civic-utility-btn--ranking" title="Smart Group Ranking">
                        <i class="fa-solid fa-chart-line"></i> Ranking
                    </a>
                <?php endif; ?>

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
                <button class="civic-utility-link nexus-header-icon-btn badge-container" title="Notifications" onclick="window.nexusNotifDrawer.open()" style="background:none; border:none; cursor:pointer;">
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
                            <i class="fa-solid fa-user" style="margin-right: 10px; width: 16px; text-align: center; color: var(--civic-brand, #00796B);"></i>My Profile
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/dashboard" role="menuitem">
                            <i class="fa-solid fa-gauge" style="margin-right: 10px; width: 16px; text-align: center; color: #8b5cf6;"></i>Dashboard
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet" role="menuitem">
                            <i class="fa-solid fa-wallet" style="margin-right: 10px; width: 16px; text-align: center; color: #10b981;"></i>Wallet
                        </a>
                        <div style="border-top: 1px solid #e5e7eb; margin: 8px 0;" role="separator"></div>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/logout" role="menuitem" style="color: #ef4444; font-weight: 600;">
                            <i class="fa-solid fa-right-from-bracket" style="margin-right: 10px; width: 16px; text-align: center;"></i>Sign Out
                        </a>
                    </div>
                </div>
                <!-- Mobile fallback links -->
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/dashboard" class="civic-utility-link mobile-only-link" style="font-weight:700; display:none;">Dashboard</a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/logout" class="civic-utility-link mobile-only-link" style="color:#dc2626; display:none;">Sign Out</a>
            <?php else: ?>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" class="civic-utility-link">Sign In</a>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/register" class="civic-utility-link" style="font-weight:700;">Join Now</a>
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
