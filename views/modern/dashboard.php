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
                <div class="dash-balance-header">
                    <div>
                        <div class="dash-balance-label">Current Balance</div>
                        <div class="balance-amount"><?= $user['balance'] ?> Hours</div>
                    </div>
                    <div>
                        <a href="<?= $basePath ?>/dashboard/wallet" class="htb-btn dash-balance-btn">Manage Wallet</a>
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
                    <table class="htb-table dash-table-full">
                        <tbody>
                            <?php if (empty($activity_feed)): ?>
                                <tr>
                                    <td class="dash-empty-state dash-empty-cell">
                                        <div class="dash-empty-icon"><i class="fa-solid fa-list-check"></i></div>
                                        <p>No activity found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activity_feed as $log): ?>
                                    <tr class="dash-activity-row">
                                        <td class="dash-activity-cell">
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
            <div class="htb-card mb-4 dash-sidebar-card">
                <div class="htb-card-header dash-sidebar-card-header dash-sidebar-header">
                    <i class="fa-solid fa-calendar dash-sidebar-header-icon"></i>Upcoming Events
                </div>
                <?php if (empty($myEvents)): ?>
                    <div class="dash-sidebar-empty">
                        <div class="dash-sidebar-empty-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
                        <p class="dash-sidebar-empty-text">No upcoming events.</p>
                        <a href="<?= $basePath ?>/events" class="dash-sidebar-empty-link">Explore Events</a>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($myEvents, 0, 3) as $ev): ?>
                        <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>" class="dash-sidebar-event dash-sidebar-event-link">
                            <div class="dash-event-date-badge">
                                <div class="dash-event-date-month"><?= date('M', strtotime($ev['start_time'])) ?></div>
                                <div class="dash-event-date-day"><?= date('d', strtotime($ev['start_time'])) ?></div>
                            </div>
                            <div class="dash-event-content">
                                <div class="dash-event-title"><?= htmlspecialchars($ev['title']) ?></div>
                                <div class="dash-event-location"><i class="fa-solid fa-location-dot dash-event-location-icon"></i><?= htmlspecialchars($ev['location']) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- My Hubs -->
            <div class="htb-card mb-4">
                <div class="htb-card-header dash-sidebar-card-header dash-sidebar-header">
                    <i class="fa-solid fa-users dash-sidebar-header-icon dash-sidebar-header-icon--pink"></i>My Hubs
                </div>
                <?php if (empty($myGroups)): ?>
                    <div class="dash-sidebar-empty">
                        <div class="dash-sidebar-empty-icon"><i class="fa-solid fa-user-group"></i></div>
                        <p class="dash-sidebar-empty-text">You haven't joined any hubs yet.</p>
                        <a href="<?= $basePath ?>/groups" class="dash-sidebar-empty-link">Join a Hub</a>
                    </div>
                <?php else: ?>
                    <div class="dash-hubs-list">
                        <?php foreach (array_slice($myGroups, 0, 4) as $grp): ?>
                            <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="dash-sidebar-hub dash-hub-link">
                                <div class="dash-hub-name"><?= htmlspecialchars($grp['name']) ?></div>
                                <div class="dash-hub-members"><i class="fa-solid fa-users dash-hub-members-icon"></i><?= $grp['member_count'] ?? '0' ?> members</div>
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
                <div class="htb-card-header dash-sidebar-card-header dash-matches-header">
                    <span><i class="fa-solid fa-fire dash-matches-header-icon"></i>Smart Matches</span>
                    <a href="<?= $basePath ?>/matches" class="dash-matches-header-link">View All <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <?php if (empty($dashboardMatches)): ?>
                    <div class="dash-matches-empty">
                        <div class="dash-matches-empty-icon">🔥</div>
                        <p class="dash-matches-empty-text">No hot matches yet.</p>
                        <p class="dash-matches-empty-subtext">Create listings to find compatible members nearby.</p>
                        <a href="<?= $basePath ?>/listings/create" class="dash-matches-empty-link">Create a Listing</a>
                    </div>
                <?php else: ?>
                    <div class="dash-matches-list">
                        <?php foreach ($dashboardMatches as $match): ?>
                            <?php
                            $matchScore = (int)($match['match_score'] ?? 0);
                            $scoreClass = $matchScore >= 85 ? 'dash-match-score--hot' : ($matchScore >= 70 ? 'dash-match-score--warm' : 'dash-match-score--normal');
                            $distanceKm = $match['distance_km'] ?? null;
                            ?>
                            <a href="<?= $basePath ?>/listings/<?= $match['id'] ?>" class="dash-match-item dash-match-link">
                                <div class="dash-match-avatar-wrapper">
                                    <img src="<?= !empty($match['user_avatar']) ? htmlspecialchars($match['user_avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($match['user_name'] ?? 'U') . '&background=6366f1&color=fff' ?>" loading="lazy" alt="" class="dash-match-avatar">
                                </div>
                                <div class="dash-match-content">
                                    <div class="dash-match-title"><?= htmlspecialchars($match['title'] ?? 'Listing') ?></div>
                                    <div class="dash-match-user"><?= htmlspecialchars($match['user_name'] ?? 'Unknown') ?></div>
                                    <div class="dash-match-meta">
                                        <span class="dash-match-score <?= $scoreClass ?>"><?= $matchScore ?>%</span>
                                        <?php if ($distanceKm !== null): ?>
                                            <span class="dash-match-distance"><i class="fa-solid fa-location-dot"></i> <?= number_format($distanceKm, 1) ?>km</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="dash-matches-footer">
                        <a href="<?= $basePath ?>/matches" class="dash-matches-footer-link">
                            <i class="fa-solid fa-fire-flame-curved dash-matches-footer-icon"></i>See All Matches
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="htb-card">
                <div class="htb-card-body">
                    <a href="<?= $basePath ?>/nexus-score" class="htb-btn dash-quick-action-score">
                        <i class="fa-solid fa-trophy dash-quick-action-score-icon"></i>View My Score
                    </a>
                    <a href="<?= $basePath ?>/listings/create" class="htb-btn htb-btn-primary dash-quick-action-listing">Post Need or Offer</a>
                    <a href="<?= $basePath ?>/groups/create" class="htb-btn dash-quick-action-hub">Start New Hub</a>
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
