<?php
// Phoenix Dashboard - Overview Page
$hero_title = "My Dashboard";
$hero_subtitle = "Welcome back, " . htmlspecialchars($user['name']);
$hero_gradient = 'htb-hero-gradient-wallet';
$hero_type = 'Wallet';

require __DIR__ . '/../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
?>

<div class="dashboard-glass-bg"></div>

<div class="dashboard-container">

    <!-- Glass Navigation -->
    <div class="dash-tabs-glass">
        <a href="<?= $basePath ?>/dashboard" class="dash-tab-glass <?= strpos($currentPath, '/dashboard/') === false ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> Overview
        </a>
        <a href="<?= $basePath ?>/dashboard/notifications" class="dash-tab-glass <?= strpos($currentPath, '/dashboard/notifications') !== false ? 'active' : '' ?>">
            <i class="fa-solid fa-bell"></i> Notifications
            <?php
            $uCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
            if ($uCount > 0): ?>
                <span class="dash-notif-badge"><?= $uCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $basePath ?>/dashboard/hubs" class="dash-tab-glass <?= strpos($currentPath, '/dashboard/hubs') !== false ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> My Hubs
        </a>
        <a href="<?= $basePath ?>/dashboard/listings" class="dash-tab-glass <?= strpos($currentPath, '/dashboard/listings') !== false ? 'active' : '' ?>">
            <i class="fa-solid fa-list"></i> My Listings
        </a>
        <a href="<?= $basePath ?>/dashboard/wallet" class="dash-tab-glass <?= strpos($currentPath, '/dashboard/wallet') !== false ? 'active' : '' ?>">
            <i class="fa-solid fa-wallet"></i> Wallet
        </a>
        <a href="<?= $basePath ?>/dashboard/events" class="dash-tab-glass <?= strpos($currentPath, '/dashboard/events') !== false ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar"></i> Events
        </a>
    </div>

    <!-- Overview Content -->
    <div class="dash-grid">
        <!-- Left Column -->
        <div>
            <!-- Welcome Stats - Glass Balance Card -->
            <div class="dash-balance-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.8; text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">Current Balance</div>
                        <div class="balance-amount"><?= $user['balance'] ?> Hours</div>
                    </div>
                    <div>
                        <a href="<?= $basePath ?>/dashboard/wallet" class="htb-btn" style="background: white; color: #4f46e5; border: none; font-weight: 700;">Manage Wallet</a>
                    </div>
                </div>
            </div>

            <!-- Recent Notifications Preview -->
            <div class="htb-card mb-4 dash-notif-preview">
                <div class="htb-card-header dash-overview-notif-header">
                    <h4 class="dash-section-title"><i class="fa-solid fa-bell"></i>Recent Notifications</h4>
                    <a href="<?= $basePath ?>/dashboard/notifications" class="dash-view-all-link">View All <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div>
                    <?php
                    $previewNotifs = array_slice($notifications ?? [], 0, 5);
                    if (empty($previewNotifs)):
                    ?>
                        <div class="dash-empty-state">
                            <div class="dash-empty-icon"><i class="fa-regular fa-bell-slash"></i></div>
                            <p>No new notifications.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($previewNotifs as $n): ?>
                            <a href="<?= $n['link'] ?: '#' ?>" class="dash-notif-item">
                                <div class="dash-notif-dot <?= $n['is_read'] ? 'read' : 'unread' ?>"></div>
                                <div class="dash-notif-content">
                                    <div class="dash-notif-message <?= $n['is_read'] ? '' : 'unread' ?>">
                                        <?= htmlspecialchars($n['message']) ?>
                                    </div>
                                    <div class="dash-notif-time">
                                        <?= date('M j, g:i a', strtotime($n['created_at'])) ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity Table -->
            <h4 class="mb-3 dash-section-title"><i class="fa-solid fa-clock-rotate-left"></i>Recent Activity</h4>
            <div class="htb-card dash-activity-card">
                <div class="dash-activity-table">
                    <table class="htb-table" style="width: 100%;">
                        <tbody>
                            <?php if (empty($activity_feed)): ?>
                                <tr>
                                    <td class="dash-empty-state" style="padding: 40px 20px;">
                                        <div class="dash-empty-icon"><i class="fa-solid fa-list-check"></i></div>
                                        <p>No activity found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activity_feed as $log): ?>
                                    <tr class="dash-activity-row">
                                        <td style="padding: 14px 16px;">
                                            <div class="dash-activity-action"><?= htmlspecialchars($log['action']) ?></div>
                                            <div class="dash-activity-details"><?= htmlspecialchars($log['details']) ?></div>
                                        </td>
                                        <td class="dash-activity-date">
                                            <?= date('M j', strtotime($log['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div>
            <!-- Upcoming Events -->
            <div class="htb-card mb-4" style="overflow: hidden;">
                <div class="htb-card-header dash-sidebar-card-header" style="background: #f8fafc; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #475569; font-size: 0.9rem;">
                    <i class="fa-solid fa-calendar" style="margin-right: 8px; color: #4f46e5;"></i>Upcoming Events
                </div>
                <?php if (empty($myEvents)): ?>
                    <div style="padding: 30px 20px; text-align: center; color: #94a3b8; font-size: 0.9rem;">
                        <div style="font-size: 2rem; margin-bottom: 8px; opacity: 0.3;"><i class="fa-solid fa-calendar-xmark"></i></div>
                        <p style="margin: 0 0 8px 0;">No upcoming events.</p>
                        <a href="<?= $basePath ?>/events" style="color: #4f46e5; text-decoration: none; font-weight: 600;">Explore Events</a>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($myEvents, 0, 3) as $ev): ?>
                        <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>" class="dash-sidebar-event" style="padding: 14px 20px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 12px; text-decoration: none; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <div style="background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; border-radius: 8px; padding: 6px 10px; text-align: center; min-width: 48px;">
                                <div style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase;"><?= date('M', strtotime($ev['start_time'])) ?></div>
                                <div style="font-size: 1.1rem; font-weight: 800; line-height: 1;"><?= date('d', strtotime($ev['start_time'])) ?></div>
                            </div>
                            <div style="min-width: 0;">
                                <div style="font-weight: 600; font-size: 0.85rem; color: #1e293b; line-height: 1.3;"><?= htmlspecialchars($ev['title']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;"><i class="fa-solid fa-location-dot" style="margin-right: 4px;"></i><?= htmlspecialchars($ev['location']) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- My Hubs -->
            <div class="htb-card mb-4">
                <div class="htb-card-header dash-sidebar-card-header" style="background: #f8fafc; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #475569; font-size: 0.9rem;">
                    <i class="fa-solid fa-users" style="margin-right: 8px; color: #db2777;"></i>My Hubs
                </div>
                <?php if (empty($myGroups)): ?>
                    <div style="padding: 30px 20px; text-align: center; color: #94a3b8; font-size: 0.9rem;">
                        <div style="font-size: 2rem; margin-bottom: 8px; opacity: 0.3;"><i class="fa-solid fa-user-group"></i></div>
                        <p style="margin: 0 0 8px 0;">You haven't joined any hubs yet.</p>
                        <a href="<?= $basePath ?>/groups" style="color: #4f46e5; text-decoration: none; font-weight: 600;">Join a Hub</a>
                    </div>
                <?php else: ?>
                    <div style="padding: 8px;">
                        <?php foreach (array_slice($myGroups, 0, 4) as $grp): ?>
                            <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="dash-sidebar-hub" style="display: block; padding: 10px 12px; border-radius: 8px; text-decoration: none; color: #334155; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                                <div style="font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($grp['name']) ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><i class="fa-solid fa-users" style="margin-right: 4px;"></i><?= $grp['member_count'] ?? '0' ?> members</div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Smart Matches Widget -->
            <?php
            // Get smart matches for dashboard widget
            $dashboardMatches = [];
            try {
                $dashboardMatches = \Nexus\Services\MatchingService::getHotMatches($user['id'], 3);
            } catch (\Exception $e) {
                // Silently fail - matches not critical for dashboard
            }
            ?>
            <div class="htb-card mb-4 dash-matches-widget">
                <div class="htb-card-header dash-sidebar-card-header" style="background: linear-gradient(135deg, #fef2f2, #fce7f3); padding: 14px 20px; border-bottom: 1px solid #fecaca; font-weight: 700; color: #be123c; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fa-solid fa-fire" style="margin-right: 8px;"></i>Smart Matches</span>
                    <a href="<?= $basePath ?>/matches" style="font-size: 0.8rem; color: #6366f1; text-decoration: none; font-weight: 600;">View All <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <?php if (empty($dashboardMatches)): ?>
                    <div style="padding: 24px 20px; text-align: center; color: #94a3b8; font-size: 0.9rem;">
                        <div style="font-size: 2rem; margin-bottom: 8px; opacity: 0.5;">🔥</div>
                        <p style="margin: 0 0 8px 0;">No hot matches yet.</p>
                        <p style="font-size: 0.8rem; color: #64748b; margin: 0 0 12px 0;">Create listings to find compatible members nearby.</p>
                        <a href="<?= $basePath ?>/listings/create" style="color: #6366f1; text-decoration: none; font-weight: 600;">Create a Listing</a>
                    </div>
                <?php else: ?>
                    <div style="padding: 8px;">
                        <?php foreach ($dashboardMatches as $match): ?>
                            <?php
                            $matchScore = (int)($match['match_score'] ?? 0);
                            $scoreColor = $matchScore >= 85 ? '#ef4444' : ($matchScore >= 70 ? '#6366f1' : '#64748b');
                            $distanceKm = $match['distance_km'] ?? null;
                            ?>
                            <a href="<?= $basePath ?>/listings/<?= $match['id'] ?>" class="dash-match-item" style="display: flex; gap: 12px; padding: 12px; border-radius: 12px; text-decoration: none; transition: background 0.15s; margin-bottom: 8px; background: rgba(99, 102, 241, 0.03);" onmouseover="this.style.background='rgba(99, 102, 241, 0.08)'" onmouseout="this.style.background='rgba(99, 102, 241, 0.03)'">
                                <div style="flex-shrink: 0;">
                                    <img src="<?= !empty($match['user_avatar']) ? htmlspecialchars($match['user_avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($match['user_name'] ?? 'U') . '&background=6366f1&color=fff' ?>" loading="lazy" alt="" style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 0.85rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($match['title'] ?? 'Listing') ?></div>
                                    <div style="font-size: 0.75rem; color: #6366f1; font-weight: 500;"><?= htmlspecialchars($match['user_name'] ?? 'Unknown') ?></div>
                                    <div style="display: flex; gap: 8px; margin-top: 4px; font-size: 0.7rem;">
                                        <span style="background: <?= $scoreColor ?>; color: white; padding: 2px 8px; border-radius: 10px; font-weight: 700;"><?= $matchScore ?>%</span>
                                        <?php if ($distanceKm !== null): ?>
                                            <span style="color: #10b981; font-weight: 600;"><i class="fa-solid fa-location-dot"></i> <?= number_format($distanceKm, 1) ?>km</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div style="padding: 12px 16px; border-top: 1px solid #f1f5f9; text-align: center;">
                        <a href="<?= $basePath ?>/matches" style="color: #6366f1; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                            <i class="fa-solid fa-fire-flame-curved" style="margin-right: 4px;"></i>See All Matches
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="htb-card">
                <div class="htb-card-body">
                    <a href="<?= $basePath ?>/nexus-score" class="htb-btn" style="background: linear-gradient(135deg, #10b981, #06b6d4); color: white; width: 100%; justify-content: center; margin-bottom: 10px;">
                        <i class="fa-solid fa-trophy" style="margin-right: 8px;"></i>View My Score
                    </a>
                    <a href="<?= $basePath ?>/listings/create" class="htb-btn htb-btn-primary" style="width: 100%; justify-content: center; margin-bottom: 10px;">Post Need or Offer</a>
                    <a href="<?= $basePath ?>/groups/create" class="htb-btn" style="background: #f1f5f9; color: #334155; width: 100%; justify-content: center;">Start New Hub</a>
                </div>
            </div>

        </div>
    </div>

</div><!-- End dashboard-container -->

<!-- Dashboard FAB -->
<div class="dash-fab">
    <button class="dash-fab-main" onclick="toggleDashFab()" aria-label="Quick Actions">
        <i class="fa-solid fa-plus"></i>
    </button>
    <div class="dash-fab-menu" id="dashFabMenu">
        <a href="<?= $basePath ?>/dashboard/wallet" class="dash-fab-item">
            <i class="fa-solid fa-paper-plane icon-send"></i>
            <span>Send Credits</span>
        </a>
        <a href="<?= $basePath ?>/listings/create" class="dash-fab-item">
            <i class="fa-solid fa-plus icon-listing"></i>
            <span>New Listing</span>
        </a>
        <a href="<?= $basePath ?>/events/create" class="dash-fab-item">
            <i class="fa-solid fa-calendar-plus icon-event"></i>
            <span>Create Event</span>
        </a>
    </div>
</div>

<script>
    function toggleDashFab() {
        const menu = document.getElementById('dashFabMenu');
        const btn = document.querySelector('.dash-fab-main');
        menu.classList.toggle('show');
        btn.classList.toggle('active');
    }

    document.addEventListener('click', function(e) {
        const fab = document.querySelector('.dash-fab');
        if (fab && !fab.contains(e.target)) {
            document.getElementById('dashFabMenu')?.classList.remove('show');
            document.querySelector('.dash-fab-main')?.classList.remove('active');
        }
    });
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
