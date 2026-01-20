<?php
/**
 * CivicOne Dashboard - Full Featured Version
 * WCAG 2.1 AA Compliant
 * Feature parity with Modern dashboard
 */

$hTitle = "My Dashboard";
$hSubtitle = "Welcome back, " . htmlspecialchars($user['name']);
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(__DIR__) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tab = $_GET['tab'] ?? 'overview';

// Get notification count
$notifCount = 0;
if (isset($_SESSION['user_id'])) {
    $notifCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
}

// Process notification settings for UI
$globalFreq = 'daily';
$groupSettings = [];
if (!empty($notifSettings)) {
    foreach ($notifSettings as $s) {
        if ($s['context_type'] === 'global') {
            $globalFreq = $s['frequency'];
        } elseif ($s['context_type'] === 'group') {
            $groupSettings[$s['context_id']] = $s['frequency'];
        }
    }
}
?>

<div class="civic-dashboard">

    <!-- Tab Navigation -->
    <nav class="civic-dash-tabs" role="tablist" aria-label="Dashboard sections">
        <a href="?tab=overview" class="civic-dash-tab <?= $tab === 'overview' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'overview' ? 'true' : 'false' ?>">
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            <span>Overview</span>
        </a>
        <a href="?tab=notifications" class="civic-dash-tab <?= $tab === 'notifications' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'notifications' ? 'true' : 'false' ?>">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <span>Notifications</span>
            <?php if ($notifCount > 0): ?>
                <span class="badge" aria-label="<?= $notifCount ?> unread"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=groups" class="civic-dash-tab <?= $tab === 'groups' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'groups' ? 'true' : 'false' ?>">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            <span>My Hubs</span>
        </a>
        <a href="?tab=listings" class="civic-dash-tab <?= $tab === 'listings' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'listings' ? 'true' : 'false' ?>">
            <i class="fa-solid fa-list" aria-hidden="true"></i>
            <span>My Listings</span>
        </a>
        <a href="?tab=wallet" class="civic-dash-tab <?= $tab === 'wallet' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'wallet' ? 'true' : 'false' ?>">
            <i class="fa-solid fa-wallet" aria-hidden="true"></i>
            <span>Wallet</span>
        </a>
        <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
            <a href="?tab=events" class="civic-dash-tab <?= $tab === 'events' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'events' ? 'true' : 'false' ?>">
                <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                <span>Events</span>
            </a>
        <?php endif; ?>
    </nav>

    <?php if ($tab === 'overview'): ?>
        <!-- OVERVIEW TAB -->
        <div class="civic-dash-grid">
            <!-- Main Column -->
            <div>
                <!-- Balance Card -->
                <div class="civic-balance-card">
                    <div class="civic-balance-card-inner">
                        <div>
                            <div class="civic-balance-label">Current Balance</div>
                            <div class="civic-balance-amount"><?= htmlspecialchars($user['balance']) ?> Hours</div>
                        </div>
                        <a href="<?= $basePath ?>/wallet" class="civic-button civic-button--secondary" role="button">Manage Wallet</a>
                    </div>
                </div>

                <!-- Recent Notifications Preview -->
                <section class="civic-dash-card" aria-labelledby="notif-preview-heading">
                    <div class="civic-dash-card-header">
                        <h2 id="notif-preview-heading" class="civic-dash-card-title">
                            <i class="fa-solid fa-bell" aria-hidden="true"></i>
                            Recent Notifications
                        </h2>
                        <a href="?tab=notifications" class="civic-dash-view-all">
                            View All <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                    <?php
                    $previewNotifs = [];
                    if (isset($_SESSION['user_id'])) {
                        $previewNotifs = \Nexus\Models\Notification::getLatest($_SESSION['user_id'], 5);
                    }
                    ?>
                    <?php if (empty($previewNotifs)): ?>
                        <div class="civic-empty-state">
                            <div class="civic-empty-icon"><i class="fa-regular fa-bell-slash" aria-hidden="true"></i></div>
                            <p class="civic-empty-text">No new notifications.</p>
                        </div>
                    <?php else: ?>
                        <ul role="list" class="civic-notif-list">
                        <?php foreach ($previewNotifs as $n): ?>
                            <li>
                                <a href="<?= htmlspecialchars($n['link'] ?: '#') ?>" class="civic-notif-item">
                                    <div class="civic-notif-dot <?= $n['is_read'] ? 'read' : 'unread' ?>" aria-hidden="true"></div>
                                    <div>
                                        <div class="civic-notif-message <?= $n['is_read'] ? '' : 'unread' ?>">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </div>
                                        <div class="civic-notif-time">
                                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                            <?= date('M j, g:i a', strtotime($n['created_at'])) ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <!-- Recent Activity -->
                <section class="civic-dash-card" aria-labelledby="activity-heading">
                    <div class="civic-dash-card-header">
                        <h2 id="activity-heading" class="civic-dash-card-title">
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                            Recent Activity
                        </h2>
                    </div>
                    <?php if (empty($activity_feed)): ?>
                        <div class="civic-empty-state">
                            <div class="civic-empty-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></div>
                            <p class="civic-empty-text">No recent activity.</p>
                        </div>
                    <?php else: ?>
                        <ul role="list" class="civic-activity-list">
                        <?php foreach ($activity_feed as $log): ?>
                            <li class="civic-activity-item">
                                <div>
                                    <div class="civic-activity-action"><?= htmlspecialchars($log['action']) ?></div>
                                    <div class="civic-activity-details"><?= htmlspecialchars($log['details'] ?? '') ?></div>
                                </div>
                                <div class="civic-activity-date"><?= date('M j', strtotime($log['created_at'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <!-- Sidebar Column -->
            <div>
                <?php if (\Nexus\Core\TenantContext::hasFeature('events')): ?>
                    <!-- Upcoming Events -->
                    <section class="civic-dash-card" aria-labelledby="events-sidebar-heading">
                        <div class="civic-dash-card-header">
                            <h2 id="events-sidebar-heading" class="civic-dash-card-title">
                                <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                                Upcoming Events
                            </h2>
                        </div>
                        <?php if (empty($myEvents)): ?>
                            <div class="civic-empty-state">
                                <div class="civic-empty-icon"><i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i></div>
                                <p class="civic-empty-text">No upcoming events.</p>
                                <a href="<?= $basePath ?>/events" class="civic-button civic-button--start" role="button">Explore Events</a>
                            </div>
                        <?php else: ?>
                            <ul role="list">
                            <?php foreach (array_slice($myEvents, 0, 3) as $ev): ?>
                                <li>
                                    <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>" class="civic-event-item">
                                        <div class="civic-event-date-box">
                                            <div class="civic-event-month"><?= date('M', strtotime($ev['start_time'])) ?></div>
                                            <div class="civic-event-day"><?= date('d', strtotime($ev['start_time'])) ?></div>
                                        </div>
                                        <div>
                                            <div class="civic-event-title"><?= htmlspecialchars($ev['title']) ?></div>
                                            <div class="civic-event-location">
                                                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                                <?= htmlspecialchars($ev['location'] ?? 'TBA') ?>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- Smart Matches Widget -->
                <?php
                $dashboardMatches = [];
                try {
                    $dashboardMatches = \Nexus\Services\MatchingService::getHotMatches($user['id'], 3);
                } catch (\Exception $e) {
                    // Silently fail - matches not critical for dashboard
                }
                ?>
                <section class="civic-dash-card civic-matches-widget" aria-labelledby="matches-heading">
                    <div class="civic-dash-card-header civic-matches-header">
                        <h2 id="matches-heading" class="civic-dash-card-title">
                            <i class="fa-solid fa-fire" aria-hidden="true"></i>
                            Smart Matches
                        </h2>
                        <a href="<?= $basePath ?>/matches" class="civic-dash-view-all">View All <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <?php if (empty($dashboardMatches)): ?>
                        <div class="civic-empty-state">
                            <div class="civic-empty-icon">🔥</div>
                            <p class="civic-empty-text">No hot matches yet.</p>
                            <p class="civic-empty-subtext">Create listings to find compatible members nearby.</p>
                            <a href="<?= $basePath ?>/listings/create" class="civic-button civic-button--start" role="button">Create a Listing</a>
                        </div>
                    <?php else: ?>
                        <ul role="list" class="civic-matches-list">
                        <?php foreach ($dashboardMatches as $match): ?>
                            <?php
                            $matchScore = (int)($match['match_score'] ?? 0);
                            $scoreClass = $matchScore >= 85 ? 'hot' : ($matchScore >= 70 ? 'warm' : 'cool');
                            $distanceKm = $match['distance_km'] ?? null;
                            ?>
                            <li>
                                <a href="<?= $basePath ?>/listings/<?= $match['id'] ?>" class="civic-match-item">
                                    <div class="civic-match-avatar">
                                        <?php if (!empty($match['user_avatar'])): ?>
                                            <img src="<?= htmlspecialchars($match['user_avatar']) ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <span><?= strtoupper(substr($match['user_name'] ?? 'U', 0, 1)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="civic-match-info">
                                        <div class="civic-match-title"><?= htmlspecialchars($match['title'] ?? 'Listing') ?></div>
                                        <div class="civic-match-user"><?= htmlspecialchars($match['user_name'] ?? 'Unknown') ?></div>
                                        <div class="civic-match-meta">
                                            <span class="civic-match-score <?= $scoreClass ?>"><?= $matchScore ?>%</span>
                                            <?php if ($distanceKm !== null): ?>
                                                <span class="civic-match-distance">
                                                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                                    <?= number_format($distanceKm, 1) ?>km
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <div class="civic-card-footer">
                            <a href="<?= $basePath ?>/matches" class="civic-footer-link">
                                <i class="fa-solid fa-fire-flame-curved" aria-hidden="true"></i> See All Matches
                            </a>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- My Hubs -->
                <section class="civic-dash-card" aria-labelledby="hubs-sidebar-heading">
                    <div class="civic-dash-card-header">
                        <h2 id="hubs-sidebar-heading" class="civic-dash-card-title">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            My Hubs
                        </h2>
                    </div>
                    <?php if (empty($myGroups)): ?>
                        <div class="civic-empty-state">
                            <div class="civic-empty-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></div>
                            <p class="civic-empty-text">You haven't joined any hubs yet.</p>
                            <a href="<?= $basePath ?>/groups" class="civic-button civic-button--start" role="button">Join a Hub</a>
                        </div>
                    <?php else: ?>
                        <ul role="list">
                        <?php foreach (array_slice($myGroups, 0, 4) as $grp): ?>
                            <li>
                                <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="civic-hub-item">
                                    <div class="civic-hub-name"><?= htmlspecialchars($grp['name']) ?></div>
                                    <div class="civic-hub-members">
                                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                                        <?= $grp['member_count'] ?? '0' ?> members
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <!-- Quick Actions -->
                <div class="civic-dash-card">
                    <div class="civic-quick-actions">
                        <a href="<?= $basePath ?>/achievements" class="civic-button civic-button--secondary" role="button">
                            <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                            View Achievements
                        </a>
                        <a href="<?= $basePath ?>/listings/create" class="civic-button" role="button">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            Post Offer or Request
                        </a>
                        <a href="<?= $basePath ?>/groups" class="civic-button civic-button--secondary" role="button">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            Browse Hubs
                        </a>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'notifications'): ?>
        <!-- NOTIFICATIONS TAB -->
        <section class="civic-dash-card" aria-labelledby="all-notif-heading">
            <div class="civic-dash-card-header civic-notif-header">
                <h2 id="all-notif-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-bell" aria-hidden="true"></i>
                    All Notifications
                </h2>
                <div class="civic-notif-actions">
                    <button type="button" onclick="openEventsModal()" class="civic-button civic-button--secondary">
                        <i class="fa-solid fa-list-ul" aria-hidden="true"></i>
                        <span class="btn-label">Events</span>
                    </button>
                    <button type="button" onclick="toggleNotifSettings()" class="civic-button civic-button--secondary">
                        <i class="fa-solid fa-gear" aria-hidden="true"></i>
                        <span class="btn-label">Settings</span>
                    </button>
                    <button type="button" onclick="window.nexusNotifications.markAllRead(this)" class="civic-button civic-button--secondary">
                        <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                        <span class="btn-label">Mark All Read</span>
                    </button>
                </div>
            </div>

            <!-- Events Modal -->
            <dialog id="events-modal" class="civic-modal" aria-labelledby="events-modal-title">
                <div class="civic-modal-header">
                    <h3 id="events-modal-title">Notification Triggers</h3>
                    <button type="button" onclick="document.getElementById('events-modal').close()" class="civic-button civic-button--secondary" aria-label="Close">
                        <i class="fa-solid fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="civic-modal-body">
                    <div class="civic-modal-section">
                        <strong>Social Interactions</strong>
                        <p>Posts, Replies, Mentions</p>
                    </div>
                    <div class="civic-modal-section">
                        <strong>Connections</strong>
                        <p>Friend Requests, Accepted</p>
                    </div>
                    <div class="civic-modal-section">
                        <strong>Events</strong>
                        <p>Invitations</p>
                    </div>
                    <div class="civic-modal-section">
                        <strong>Wallet</strong>
                        <p>Payments, Transfers</p>
                    </div>
                    <div class="civic-modal-section">
                        <strong>Badges</strong>
                        <p>Volunteering milestones, Credits earned</p>
                    </div>
                </div>
                <div class="civic-modal-footer">
                    <button type="button" onclick="document.getElementById('events-modal').close()" class="civic-button">Got it</button>
                </div>
            </dialog>

            <!-- Settings Panel -->
            <div id="notif-settings-panel" class="civic-settings-panel" hidden>
                <div class="civic-settings-card">
                    <h3>Global Email Frequency</h3>
                    <p>Default for all notifications</p>
                    <label for="global-freq" class="visually-hidden">Email frequency</label>
                    <select id="global-freq" onchange="updateNotifSetting('global', 0, this.value)" class="civic-select">
                        <option value="instant" <?= $globalFreq === 'instant' ? 'selected' : '' ?>>Instant (As it happens)</option>
                        <option value="daily" <?= $globalFreq === 'daily' ? 'selected' : '' ?>>Daily Digest</option>
                        <option value="weekly" <?= $globalFreq === 'weekly' ? 'selected' : '' ?>>Weekly Digest</option>
                        <option value="off" <?= $globalFreq === 'off' ? 'selected' : '' ?>>Off (In-App Only)</option>
                    </select>
                </div>

                <?php if (!empty($myGroups)): ?>
                    <div class="civic-settings-card">
                        <h3>Hub Overrides</h3>
                        <p>Customize per hub</p>
                        <div class="civic-hub-settings">
                            <?php foreach ($myGroups as $grp):
                                $gFreq = $groupSettings[$grp['id']] ?? 'default';
                            ?>
                                <div class="civic-hub-setting-item">
                                    <span class="civic-hub-setting-name"><?= htmlspecialchars($grp['name']) ?></span>
                                    <label for="hub-freq-<?= $grp['id'] ?>" class="visually-hidden">Frequency for <?= htmlspecialchars($grp['name']) ?></label>
                                    <select id="hub-freq-<?= $grp['id'] ?>" onchange="updateNotifSetting('group', <?= $grp['id'] ?>, this.value)" class="civic-select-sm">
                                        <option value="default" <?= $gFreq === 'default' ? 'selected' : '' ?>>Use Global</option>
                                        <option value="instant" <?= $gFreq === 'instant' ? 'selected' : '' ?>>Instant</option>
                                        <option value="daily" <?= $gFreq === 'daily' ? 'selected' : '' ?>>Daily</option>
                                        <option value="off" <?= $gFreq === 'off' ? 'selected' : '' ?>>Mute</option>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Notification List -->
            <?php
            $allNotifs = $notifications ?? [];
            if (empty($allNotifs) && isset($_SESSION['user_id'])) {
                $allNotifs = \Nexus\Models\Notification::getLatest($_SESSION['user_id'], 50);
            }
            ?>
            <?php if (empty($allNotifs)): ?>
                <div class="civic-empty-state civic-empty-large">
                    <div class="civic-empty-icon"><i class="fa-regular fa-bell-slash" aria-hidden="true"></i></div>
                    <h3>All caught up!</h3>
                    <p class="civic-empty-text">You have no notifications at this time.</p>
                </div>
            <?php else: ?>
                <ul role="list" class="civic-notif-full-list">
                <?php foreach ($allNotifs as $n): ?>
                    <li class="civic-notif-full-item <?= $n['is_read'] ? 'read' : 'unread' ?>" data-notif-id="<?= $n['id'] ?>">
                        <div class="civic-notif-dot <?= $n['is_read'] ? 'read' : 'unread' ?>" aria-hidden="true"></div>
                        <div class="civic-notif-body">
                            <div class="civic-notif-message <?= $n['is_read'] ? '' : 'unread' ?>">
                                <?= htmlspecialchars($n['message']) ?>
                            </div>
                            <div class="civic-notif-time">
                                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                <?= date('M j, Y \a\t g:i A', strtotime($n['created_at'])) ?>
                            </div>
                            <div class="civic-notif-item-actions">
                                <?php if ($n['link']): ?>
                                    <a href="<?= htmlspecialchars($n['link']) ?>" onclick="window.nexusNotifications.markOneRead(<?= $n['id'] ?>)" class="civic-button" role="button">View</a>
                                <?php endif; ?>
                                <?php if (!$n['is_read']): ?>
                                    <button type="button" onclick="window.nexusNotifications.markOneRead(<?= $n['id'] ?>); this.closest('li').classList.add('read'); this.closest('li').classList.remove('unread'); this.remove();" class="civic-button civic-button--secondary">
                                        <i class="fa-solid fa-check" aria-hidden="true"></i> Mark Read
                                    </button>
                                <?php endif; ?>
                                <button type="button" onclick="deleteNotificationDashboard(<?= $n['id'] ?>)" class="civic-button civic-button--warning" aria-label="Delete notification">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    <?php elseif ($tab === 'groups'): ?>
        <!-- GROUPS TAB -->
        <section class="civic-dash-card" aria-labelledby="my-hubs-heading">
            <div class="civic-dash-card-header">
                <h2 id="my-hubs-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    My Hubs
                </h2>
                <a href="<?= $basePath ?>/groups" class="civic-button" role="button">
                    <i class="fa-solid fa-compass" aria-hidden="true"></i> Browse All Hubs
                </a>
            </div>
            <?php if (empty($myGroups)): ?>
                <div class="civic-empty-state civic-empty-large">
                    <div class="civic-empty-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></div>
                    <h3>No hubs joined</h3>
                    <p class="civic-empty-text">Join a hub to connect with your community.</p>
                    <a href="<?= $basePath ?>/groups" class="civic-button" role="button">Browse Hubs</a>
                </div>
            <?php else: ?>
                <div class="civic-hubs-grid">
                    <?php foreach ($myGroups as $grp): ?>
                        <article class="civic-hub-card">
                            <h3 class="civic-hub-card-title"><?= htmlspecialchars($grp['name']) ?></h3>
                            <p class="civic-hub-card-desc"><?= htmlspecialchars($grp['description'] ?? '') ?></p>
                            <div class="civic-hub-card-footer">
                                <span class="civic-hub-card-members">
                                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                                    <?= $grp['member_count'] ?? 0 ?> members
                                </span>
                                <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="civic-button" role="button">Enter Hub</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($tab === 'listings'): ?>
        <!-- LISTINGS TAB -->
        <?php
        $myListings = $my_listings ?? [];
        if (empty($myListings) && isset($_SESSION['user_id'])) {
            $myListings = \Nexus\Models\Listing::getForUser($_SESSION['user_id']);
        }
        $offerCount = 0;
        $reqCount = 0;
        foreach ($myListings as $ml) {
            if ($ml['type'] === 'offer') $offerCount++;
            else $reqCount++;
        }
        ?>
        <section class="civic-dash-card" aria-labelledby="my-listings-heading">
            <div class="civic-dash-card-header">
                <h2 id="my-listings-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-list" aria-hidden="true"></i>
                    My Listings
                </h2>
                <div class="civic-listing-stats">
                    <span class="civic-stat-badge civic-stat-offers"><?= $offerCount ?> Offers</span>
                    <span class="civic-stat-badge civic-stat-requests"><?= $reqCount ?> Requests</span>
                </div>
            </div>

            <div class="civic-listings-actions">
                <a href="<?= $basePath ?>/compose?type=listing" class="civic-button" role="button">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Post New Listing
                </a>
            </div>

            <?php if (empty($myListings)): ?>
                <div class="civic-empty-state civic-empty-large">
                    <div class="civic-empty-icon"><i class="fa-solid fa-seedling" aria-hidden="true"></i></div>
                    <h3>No listings yet</h3>
                    <p class="civic-empty-text">You haven't posted any offers or requests yet.</p>
                </div>
            <?php else: ?>
                <div class="civic-listings-grid">
                    <?php foreach ($myListings as $listing): ?>
                        <article class="civic-listing-card" id="listing-<?= $listing['id'] ?>">
                            <?php if (!empty($listing['image_url'])): ?>
                                <div class="civic-listing-image" style="background-image: url('<?= htmlspecialchars($listing['image_url']) ?>')"></div>
                            <?php else: ?>
                                <div class="civic-listing-image civic-listing-placeholder <?= strtolower($listing['type']) ?>">
                                    <i class="fa-solid fa-<?= $listing['type'] === 'offer' ? 'hand-holding-heart' : 'hand-holding' ?>" aria-hidden="true"></i>
                                </div>
                            <?php endif; ?>
                            <div class="civic-listing-card-body">
                                <div class="civic-listing-meta">
                                    <span class="civic-listing-type <?= strtolower($listing['type']) ?>">
                                        <?= strtoupper($listing['type']) ?>
                                    </span>
                                    <span class="civic-listing-date"><?= date('M j, Y', strtotime($listing['created_at'])) ?></span>
                                </div>
                                <h3 class="civic-listing-title">
                                    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>"><?= htmlspecialchars($listing['title']) ?></a>
                                </h3>
                                <div class="civic-listing-card-actions">
                                    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="civic-button civic-button--secondary" role="button">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i> View
                                    </a>
                                    <button type="button" onclick="deleteListing(<?= $listing['id'] ?>)" class="civic-button civic-button--warning">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($tab === 'wallet'): ?>
        <!-- WALLET TAB -->
        <?php
        $transactions = $wallet_transactions ?? [];
        if (empty($transactions) && isset($_SESSION['user_id'])) {
            $transactions = \Nexus\Models\Transaction::getHistory($_SESSION['user_id']);
        }
        ?>
        <div class="civic-wallet-grid">
            <!-- Left: Balance & Transfer -->
            <div class="civic-wallet-main">
                <!-- Balance Card -->
                <div class="civic-wallet-balance-card">
                    <div class="civic-wallet-balance-label">Current Balance</div>
                    <div class="civic-wallet-balance-amount">
                        <?= number_format($user['balance']) ?>
                        <span class="civic-wallet-balance-unit">Credits</span>
                    </div>
                    <div class="civic-wallet-balance-note">1 Credit = 1 Hour of Service</div>
                </div>

                <!-- Transfer Widget -->
                <section class="civic-dash-card" aria-labelledby="send-credits-heading">
                    <div class="civic-dash-card-header">
                        <h2 id="send-credits-heading" class="civic-dash-card-title">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            Send Credits
                        </h2>
                    </div>
                    <form id="transfer-form" action="<?= $basePath ?>/wallet/transfer" method="POST" onsubmit="return validateDashTransfer(this);" class="civic-transfer-form">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="username" id="dashRecipientUsername" value="">
                        <input type="hidden" name="recipient_id" id="dashRecipientId" value="">

                        <div class="civic-form-group">
                            <label for="dashUserSearch" class="civic-label">Recipient</label>
                            <div id="dashUserSearch-hint" class="civic-hint">
                                Search by name or username
                            </div>

                            <!-- Selected User Chip -->
                            <div id="dashSelectedUser" class="civic-selected-user" hidden>
                                <div id="dashSelectedAvatar" class="civic-selected-avatar">
                                    <span id="dashSelectedInitial">?</span>
                                </div>
                                <div class="civic-selected-info">
                                    <div id="dashSelectedName" class="civic-selected-name">-</div>
                                    <div id="dashSelectedUsername" class="civic-selected-username">-</div>
                                </div>
                                <button type="button" onclick="clearDashSelection()" class="civic-selected-clear" aria-label="Clear selection">
                                    <i class="fa-solid fa-times" aria-hidden="true"></i>
                                </button>
                            </div>

                            <!-- Search Input -->
                            <div id="dashSearchWrapper" class="civic-search-wrapper">
                                <input type="text"
                                       id="dashUserSearch"
                                       class="civic-input"
                                       autocomplete="off"
                                       aria-describedby="dashUserSearch-hint">
                                <div id="dashUserResults" class="civic-search-results" hidden></div>
                            </div>
                        </div>

                        <div class="civic-form-group">
                            <label for="transfer-amount" class="civic-label">Amount (credits)</label>
                            <div id="transfer-amount-hint" class="civic-hint">
                                Minimum transfer is 1 credit (1 hour of service)
                            </div>
                            <input type="number"
                                   id="transfer-amount"
                                   name="amount"
                                   class="civic-input civic-input--width-5"
                                   min="1"
                                   required
                                   aria-describedby="transfer-amount-hint">
                        </div>

                        <div class="civic-form-group">
                            <label for="transfer-desc" class="civic-label">Description (optional)</label>
                            <div id="transfer-desc-hint" class="civic-hint">
                                What is this transfer for?
                            </div>
                            <textarea id="transfer-desc"
                                      name="description"
                                      class="civic-textarea"
                                      rows="3"
                                      aria-describedby="transfer-desc-hint"></textarea>
                        </div>

                        <button type="submit" id="transfer-btn" class="civic-button civic-button--full-width">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send Credits
                        </button>
                    </form>
                </section>
            </div>

            <!-- Right: Transaction History -->
            <section class="civic-dash-card" aria-labelledby="transactions-heading">
                <div class="civic-dash-card-header">
                    <h2 id="transactions-heading" class="civic-dash-card-title">
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        Recent Transactions
                    </h2>
                </div>
                <?php if (empty($transactions)): ?>
                    <div class="civic-empty-state">
                        <div class="civic-empty-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></div>
                        <p class="civic-empty-text">No transactions found.</p>
                    </div>
                <?php else: ?>
                    <div class="civic-transactions-table" role="table" aria-label="Transaction history">
                        <div class="civic-transactions-header" role="row">
                            <div role="columnheader">Date</div>
                            <div role="columnheader">Description</div>
                            <div role="columnheader" class="civic-col-right">Amount</div>
                        </div>
                        <?php foreach (array_slice($transactions, 0, 10) as $t):
                            $isIncoming = $t['receiver_id'] == $_SESSION['user_id'];
                        ?>
                            <div class="civic-transaction-row" role="row">
                                <div role="cell" class="civic-transaction-date">
                                    <?= date('M j, Y', strtotime($t['created_at'])) ?>
                                </div>
                                <div role="cell" class="civic-transaction-desc">
                                    <div class="civic-transaction-title">
                                        <?= $isIncoming ? 'Received from ' . htmlspecialchars($t['sender_name']) : 'Sent to ' . htmlspecialchars($t['receiver_name']) ?>
                                    </div>
                                    <?php if (!empty($t['description'])): ?>
                                        <div class="civic-transaction-note">"<?= htmlspecialchars($t['description']) ?>"</div>
                                    <?php endif; ?>
                                </div>
                                <div role="cell" class="civic-transaction-amount <?= $isIncoming ? 'incoming' : 'outgoing' ?>">
                                    <?= $isIncoming ? '+' : '-' ?><?= number_format($t['amount']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

    <?php elseif ($tab === 'events'): ?>
        <!-- EVENTS TAB -->
        <?php
        $hosting = [];
        $attending = [];
        if (isset($_SESSION['user_id'])) {
            try {
                $hosting = \Nexus\Models\Event::getHosted($_SESSION['user_id']);
            } catch (\Exception $e) {}
            try {
                $attending = \Nexus\Models\Event::getAttending($_SESSION['user_id']);
            } catch (\Exception $e) {}
        }
        ?>
        <div class="civic-events-grid">
            <!-- Hosting -->
            <section class="civic-dash-card" aria-labelledby="hosting-heading">
                <div class="civic-dash-card-header">
                    <h2 id="hosting-heading" class="civic-dash-card-title">
                        <i class="fa-solid fa-calendar-star" aria-hidden="true"></i>
                        Hosting
                    </h2>
                    <a href="<?= $basePath ?>/events/create" class="civic-button" role="button">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Create Event
                    </a>
                </div>
                <?php if (empty($hosting)): ?>
                    <div class="civic-empty-state">
                        <div class="civic-empty-icon"><i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i></div>
                        <p class="civic-empty-text">You are not hosting any upcoming events.</p>
                    </div>
                <?php else: ?>
                    <ul role="list" class="civic-events-list">
                    <?php foreach ($hosting as $e): ?>
                        <li class="civic-event-hosted">
                            <div class="civic-event-hosted-header">
                                <div class="civic-event-hosted-date">
                                    <?= date('M j @ g:i A', strtotime($e['start_time'])) ?>
                                </div>
                                <div class="civic-event-hosted-stats">
                                    <span class="civic-event-going"><strong><?= $e['attending_count'] ?? 0 ?></strong> Going</span>
                                    <span><strong><?= $e['invited_count'] ?? 0 ?></strong> Invited</span>
                                </div>
                            </div>
                            <h3 class="civic-event-hosted-title">
                                <a href="<?= $basePath ?>/events/<?= $e['id'] ?>"><?= htmlspecialchars($e['title']) ?></a>
                            </h3>
                            <div class="civic-event-hosted-location">
                                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                <?= htmlspecialchars($e['location'] ?? 'TBA') ?>
                            </div>
                            <div class="civic-event-hosted-actions">
                                <a href="<?= $basePath ?>/events/<?= $e['id'] ?>/edit" class="civic-button civic-button--secondary" role="button">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
                                </a>
                                <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" class="civic-button civic-button--secondary" role="button">
                                    <i class="fa-solid fa-users" aria-hidden="true"></i> Manage
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Attending -->
            <section class="civic-dash-card" aria-labelledby="attending-heading">
                <div class="civic-dash-card-header">
                    <h2 id="attending-heading" class="civic-dash-card-title">
                        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                        Attending
                    </h2>
                </div>
                <?php if (empty($attending)): ?>
                    <div class="civic-empty-state">
                        <div class="civic-empty-icon"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i></div>
                        <p class="civic-empty-text">You are not attending any upcoming events.</p>
                        <a href="<?= $basePath ?>/events" class="civic-button civic-button--start" role="button">Browse Events</a>
                    </div>
                <?php else: ?>
                    <ul role="list" class="civic-events-list">
                    <?php foreach ($attending as $e): ?>
                        <li>
                            <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" class="civic-event-attending">
                                <div class="civic-event-date-box">
                                    <div class="civic-event-month"><?= date('M', strtotime($e['start_time'])) ?></div>
                                    <div class="civic-event-day"><?= date('j', strtotime($e['start_time'])) ?></div>
                                </div>
                                <div class="civic-event-attending-info">
                                    <div class="civic-event-attending-title"><?= htmlspecialchars($e['title']) ?></div>
                                    <div class="civic-event-attending-meta">
                                        <?= date('g:i A', strtotime($e['start_time'])) ?> • <?= htmlspecialchars($e['organizer_name'] ?? 'Unknown') ?>
                                    </div>
                                    <span class="civic-event-badge-going">
                                        <i class="fa-solid fa-check-circle" aria-hidden="true"></i> Going
                                    </span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

    <?php else: ?>
        <div class="civic-dash-card">
            <div class="civic-empty-state civic-empty-large">
                <p>This section is under construction or linked elsewhere.</p>
                <a href="?tab=overview" class="civic-button" role="button">Back to Dashboard</a>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Dashboard FAB -->
<div class="civic-fab" id="civicFab">
    <button type="button" class="civic-fab-main" onclick="toggleCivicFab()" aria-label="Quick Actions" aria-expanded="false">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
    </button>
    <div class="civic-fab-menu" id="civicFabMenu" hidden>
        <a href="<?= $basePath ?>/wallet" class="civic-fab-item">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
            <span>Send Credits</span>
        </a>
        <a href="<?= $basePath ?>/listings/create" class="civic-fab-item">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span>New Listing</span>
        </a>
        <a href="<?= $basePath ?>/events/create" class="civic-fab-item">
            <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
            <span>Create Event</span>
        </a>
    </div>
</div>

<script src="/assets/js/civicone-dashboard.js"></script>
<script>
// Initialize dashboard with basePath
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initCivicOneDashboard === 'function') {
        initCivicOneDashboard('<?= $basePath ?>');
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/civicone/footer.php'; ?>
