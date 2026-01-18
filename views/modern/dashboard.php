<?php
// Phoenix Dashboard (Wallet Style) - Upgraded with Notifications
$hero_title = "My Dashboard";
$hero_subtitle = "Welcome back, " . htmlspecialchars($user['name']);
$hero_gradient = 'htb-hero-gradient-wallet';
$hero_type = 'Wallet';

require __DIR__ . '/../layouts/header.php';

// Helper for tabs
$tab = $activeTab ?? 'overview';
?>

<div class="dashboard-glass-bg"></div>

<div class="dashboard-container">

    <!-- Glass Tab Navigation -->
    <div class="dash-tabs-glass">
        <a href="?tab=overview" class="dash-tab-glass <?= $tab === 'overview' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> Overview
        </a>
        <a href="?tab=notifications" class="dash-tab-glass <?= $tab === 'notifications' ? 'active' : '' ?>">
            <i class="fa-solid fa-bell"></i> Notifications
            <?php
            $uCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
            if ($uCount > 0): ?>
                <span style="background: #ef4444; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 99px; font-weight: 700;"><?= $uCount ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=groups" class="dash-tab-glass <?= $tab === 'groups' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> My Hubs
        </a>
        <a href="?tab=listings" class="dash-tab-glass <?= $tab === 'listings' ? 'active' : '' ?>">
            <i class="fa-solid fa-list"></i> My Listings
        </a>
        <a href="?tab=wallet" class="dash-tab-glass <?= $tab === 'wallet' ? 'active' : '' ?>">
            <i class="fa-solid fa-wallet"></i> Wallet
        </a>
        <a href="?tab=events" class="dash-tab-glass <?= $tab === 'events' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar"></i> Events
        </a>
    </div>

    <!-- TAB: OVERVIEW -->
    <?php if ($tab === 'overview'): ?>
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
                            <a href="?tab=wallet" style="background: white; color: #4f46e5; border: none; font-weight: 700; padding: 12px 24px; border-radius: 12px; text-decoration: none; display: inline-block; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">Manage Wallet</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Notifications Preview -->
                <div class="htb-card mb-4 dash-notif-preview">
                    <div class="htb-card-header dash-overview-notif-header">
                        <h4 class="dash-section-title"><i class="fa-solid fa-bell"></i>Recent Notifications</h4>
                        <a href="?tab=notifications" class="dash-view-all-link">View All <i class="fa-solid fa-arrow-right"></i></a>
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
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events" style="color: #4f46e5; text-decoration: none; font-weight: 600;">Explore Events</a>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($myEvents, 0, 3) as $ev): ?>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $ev['id'] ?>" class="dash-sidebar-event" style="padding: 14px 20px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 12px; text-decoration: none; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
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
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups" style="color: #4f46e5; text-decoration: none; font-weight: 600;">Join a Hub</a>
                        </div>
                    <?php else: ?>
                        <div style="padding: 8px;">
                            <?php foreach (array_slice($myGroups, 0, 4) as $grp): ?>
                                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $grp['id'] ?>" class="dash-sidebar-hub" style="display: block; padding: 10px 12px; border-radius: 8px; text-decoration: none; color: #334155; transition: background 0.15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
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
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/matches" style="font-size: 0.8rem; color: #6366f1; text-decoration: none; font-weight: 600;">View All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <?php if (empty($dashboardMatches)): ?>
                        <div style="padding: 24px 20px; text-align: center; color: #94a3b8; font-size: 0.9rem;">
                            <div style="font-size: 2rem; margin-bottom: 8px; opacity: 0.5;">🔥</div>
                            <p style="margin: 0 0 8px 0;">No hot matches yet.</p>
                            <p style="font-size: 0.8rem; color: #64748b; margin: 0 0 12px 0;">Create listings to find compatible members nearby.</p>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings/create" style="color: #6366f1; text-decoration: none; font-weight: 600;">Create a Listing</a>
                        </div>
                    <?php else: ?>
                        <div style="padding: 8px;">
                            <?php foreach ($dashboardMatches as $match): ?>
                                <?php
                                $matchScore = (int)($match['match_score'] ?? 0);
                                $scoreColor = $matchScore >= 85 ? '#ef4444' : ($matchScore >= 70 ? '#6366f1' : '#64748b');
                                $distanceKm = $match['distance_km'] ?? null;
                                ?>
                                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings/<?= $match['id'] ?>" class="dash-match-item" style="display: flex; gap: 12px; padding: 12px; border-radius: 12px; text-decoration: none; transition: background 0.15s; margin-bottom: 8px; background: rgba(99, 102, 241, 0.03);" onmouseover="this.style.background='rgba(99, 102, 241, 0.08)'" onmouseout="this.style.background='rgba(99, 102, 241, 0.03)'">
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
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/matches" style="color: #6366f1; text-decoration: none; font-weight: 600; font-size: 0.85rem;">
                                <i class="fa-solid fa-fire-flame-curved" style="margin-right: 4px;"></i>See All Matches
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="htb-card">
                    <div class="htb-card-body">
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/nexus-score" class="htb-btn" style="background: linear-gradient(135deg, #10b981, #06b6d4); color: white; width: 100%; justify-content: center; margin-bottom: 10px;">
                            <i class="fa-solid fa-trophy" style="margin-right: 8px;"></i>View My Score
                        </a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings/create" class="htb-btn htb-btn-primary" style="width: 100%; justify-content: center; margin-bottom: 10px;">Post Need or Offer</a>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/create" class="htb-btn" style="background: #f1f5f9; color: #334155; width: 100%; justify-content: center;">Start New Hub</a>
                    </div>
                </div>

            </div>
        </div>

        <!-- TAB: NOTIFICATIONS (Full Page) -->
    <?php elseif ($tab === 'notifications'): ?>
        <?php
        // Process Settings for UI
        $globalFreq = 'daily'; // Default
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


        <div class="htb-card">
            <!-- Header -->
            <div class="notif-card-header">
                <div class="notif-header-top">
                    <h3 style="margin: 0; font-size: 1.15rem; color: #1e293b;">All Notifications</h3>
                </div>
                <div class="notif-header-actions">
                    <button onclick="openEventsModal()" class="htb-btn htb-btn-sm" style="background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe;">
                        <i class="fa-solid fa-list-ul"></i> Events
                    </button>
                    <button onclick="toggleNotifSettings()" class="htb-btn htb-btn-sm" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">
                        <i class="fa-solid fa-gear"></i> Settings
                    </button>
                    <button onclick="window.nexusNotifications.markAllRead(this)" class="htb-btn htb-btn-sm" style="background: white; color: #475569; border: 1px solid #cbd5e1;">
                        <i class="fa-solid fa-check-double"></i> Mark All Read
                    </button>
                </div>
            </div>

            <!-- EVENTS MODAL -->
            <dialog id="events-modal" style="border:none; border-radius:16px; padding:0; width:500px; max-width:92vw; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); z-index:1000;">
                <div style="padding:18px 20px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border-radius:16px 16px 0 0;">
                    <h3 style="margin:0; font-size:1rem; font-weight:700;">Notification Triggers</h3>
                    <button onclick="document.getElementById('events-modal').close()" style="background:#f1f5f9; border:none; cursor:pointer; font-size:1.2rem; color:#64748b; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;">&times;</button>
                </div>
                <div style="padding:20px; max-height:60vh; overflow-y:auto; line-height:1.6; color:#334155; font-size:0.9rem;">
                    <div style="margin-bottom:16px;"><strong style="color:#1e293b;">Social Interactions</strong><br>Posts, Replies, Mentions</div>
                    <div style="margin-bottom:16px;"><strong style="color:#1e293b;">Connections</strong><br>Friend Requests, Accepted</div>
                    <div style="margin-bottom:16px;"><strong style="color:#1e293b;">Events</strong><br>Invitations</div>
                    <div style="margin-bottom:16px;"><strong style="color:#1e293b;">Wallet</strong><br>Payments, Transfers</div>
                    <div><strong style="color:#1e293b;">Badges</strong><br>Volunteering milestones, Credits earned</div>
                </div>
                <div style="padding:14px 20px; border-top:1px solid #e5e7eb; background:#f8fafc; border-radius:0 0 16px 16px;">
                    <button onclick="document.getElementById('events-modal').close()" class="htb-btn htb-btn-sm" style="background:#6366f1; color:white; border:none; width:100%;">Got it</button>
                </div>
            </dialog>
            <script>
                function openEventsModal() {
                    const m = document.getElementById('events-modal');
                    if (typeof m.showModal === "function") {
                        m.showModal();
                    } else {
                        alert("Your browser does not support the dialog API.");
                    }
                }
            </script>

            <!-- SETTINGS PANEL -->
            <div id="notif-settings-panel" class="notif-settings-panel">
                <div class="notif-settings-card">
                    <h5>Global Email Frequency</h5>
                    <p>Default for all notifications</p>
                    <select onchange="updateNotifSetting('global', 0, this.value)">
                        <option value="instant" <?= $globalFreq === 'instant' ? 'selected' : '' ?>>Instant (As it happens)</option>
                        <option value="daily" <?= $globalFreq === 'daily' ? 'selected' : '' ?>>Daily Digest</option>
                        <option value="weekly" <?= $globalFreq === 'weekly' ? 'selected' : '' ?>>Weekly Digest</option>
                        <option value="off" <?= $globalFreq === 'off' ? 'selected' : '' ?>>Off (In-App Only)</option>
                    </select>
                </div>

                <?php if (!empty($myGroups)): ?>
                    <div class="notif-settings-card">
                        <h5>Hub Overrides</h5>
                        <p>Customize per hub</p>
                        <div class="hub-settings-list">
                            <?php foreach ($myGroups as $grp):
                                $gFreq = $groupSettings[$grp['id']] ?? 'default';
                            ?>
                                <div class="hub-setting-item">
                                    <span class="hub-name"><?= htmlspecialchars($grp['name']) ?></span>
                                    <select onchange="updateNotifSetting('group', <?= $grp['id'] ?>, this.value)">
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

            <script>
                function toggleNotifSettings() {
                    document.getElementById('notif-settings-panel').classList.toggle('show');
                }

                function updateNotifSetting(type, id, freq) {
                    fetch(NEXUS_BASE + '/api/notifications/settings', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            context_type: type,
                            context_id: id,
                            frequency: freq
                        })
                    }).then(res => res.json()).then(data => {
                        if (!data.success) alert('Failed to save settings');
                    });
                }

                function deleteNotificationDashboard(id) {
                    if (!confirm('Delete this notification?')) return;
                    const formData = new URLSearchParams();
                    formData.append('id', id);
                    fetch(NEXUS_BASE + '/api/notifications/delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: formData
                    }).then(res => res.json()).then(data => {
                        if (data.success) location.reload();
                        else alert('Error: ' + (data.error || 'Unknown error'));
                    }).catch(() => alert('Failed to delete.'));
                }
            </script>

            <!-- Notification List -->
            <div class="notif-list">
                <?php if (empty($notifications)): ?>
                    <div class="notif-empty">
                        <div class="notif-empty-icon"><i class="fa-regular fa-bell-slash"></i></div>
                        <h3>All caught up!</h3>
                        <p>You have no notifications at this time.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <div class="notif-item-new <?= $n['is_read'] ? 'read' : 'unread' ?>" data-notif-id="<?= $n['id'] ?>">
                            <div class="notif-item-content">
                                <div class="notif-indicator <?= $n['is_read'] ? 'read' : 'unread' ?>"></div>
                                <div class="notif-body">
                                    <div class="notif-message"><?= htmlspecialchars($n['message']) ?></div>
                                    <div class="notif-time"><?= date('M j, Y \a\t g:i A', strtotime($n['created_at'])) ?></div>
                                    <div class="notif-actions">
                                        <?php if ($n['link']): ?>
                                            <a href="<?= $n['link'] ?>" onclick="window.nexusNotifications.markOneRead(<?= $n['id'] ?>)" class="htb-btn htb-btn-sm htb-btn-primary">View</a>
                                        <?php endif; ?>
                                        <?php if (!$n['is_read']): ?>
                                            <button onclick="window.nexusNotifications.markOneRead(<?= $n['id'] ?>); this.closest('.notif-item-new').classList.add('read'); this.closest('.notif-item-new').classList.remove('unread'); this.remove();" class="htb-btn htb-btn-sm" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569;">
                                                <i class="fa-solid fa-check"></i> Mark Read
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteNotificationDashboard(<?= $n['id'] ?>)" class="notif-delete-btn" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB: MY HUBS -->
    <?php elseif ($tab === 'groups'): ?>
        <div class="htb-card">
            <div class="htb-card-header dash-hubs-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.2rem;">My Hubs</h3>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups" class="htb-btn htb-btn-primary"><i class="fa-solid fa-compass"></i> Browse All Hubs</a>
            </div>
            <div class="dash-hubs-grid" style="padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php if (empty($myGroups)): ?>
                    <p>No hubs found.</p>
                <?php else: ?>
                    <?php foreach ($myGroups as $grp): ?>
                        <div class="htb-card" style="border: 1px solid #e2e8f0;">
                            <div class="htb-card-body">
                                <h4><?= htmlspecialchars($grp['name']) ?></h4>
                                <p style="color: #64748b; font-size: 0.9rem;"><?= htmlspecialchars($grp['description'] ?? '') ?></p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                                    <span style="color: #64748b; font-size: 0.85rem;"><i class="fa-solid fa-users" style="margin-right: 6px; color: #db2777;"></i><?= $grp['member_count'] ?? 0 ?> members</span>
                                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $grp['id'] ?>" class="htb-btn htb-btn-primary htb-btn-sm">Enter Hub</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB: MY LISTINGS -->
    <?php elseif ($tab === 'listings'): ?>
        <?php
        $myListings = \Nexus\Models\Listing::getForUser($_SESSION['user_id']);
        // Separate counts locally
        $offerCount = 0;
        $reqCount = 0;
        foreach ($myListings as $ml) {
            if ($ml['type'] === 'offer') $offerCount++;
            else $reqCount++;
        }
        ?>
        <div class="htb-card">
            <div class="htb-card-header dash-listings-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.2rem;">My Listings</h3>
                <div class="dash-listings-stats" style="display: flex; gap: 10px;">
                    <span style="font-size:0.85rem; color:#10b981; background:#ecfdf5; padding:6px 12px; border-radius:99px; font-weight:600;"><?= $offerCount ?> Offers</span>
                    <span style="font-size:0.85rem; color:#f59e0b; background:#fffbeb; padding:6px 12px; border-radius:99px; font-weight:600;"><?= $reqCount ?> Requests</span>
                </div>
            </div>

            <div class="dash-listings-content" style="padding: 20px;">
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compose?type=listing" class="htb-btn htb-btn-primary" style="margin-bottom: 20px; justify-content:center; max-width:200px;"><i class="fa-solid fa-plus"></i> Post New Listing</a>

                <?php if (empty($myListings)): ?>
                    <div style="text-align: center; padding: 40px; color: #94a3b8;">
                        <div style="font-size: 3rem; margin-bottom: 10px; opacity: 0.3;"><i class="fa-solid fa-seedling"></i></div>
                        <p>You haven't posted any offers or requests yet.</p>
                    </div>
                <?php else: ?>
                    <div class="dash-listings-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                        <?php foreach ($myListings as $l): ?>
                            <div class="htb-card" id="listing-<?= $l['id'] ?>" style="border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column;">
                                <?php if ($l['image_url']): ?>
                                    <div style="height: 140px; background: url('<?= $l['image_url'] ?>') center/cover no-repeat;"></div>
                                <?php else: ?>
                                    <div style="height: 140px; background: linear-gradient(135deg, #e0e7ff, #ede9fe); display: flex; align-items: center; justify-content: center; color: #a5b4fc; font-size: 3rem;">
                                        <i class="fa-solid fa-<?= $l['type'] === 'offer' ? 'hand-holding-heart' : 'hand-holding' ?>"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="htb-card-body" style="flex-grow: 1; display: flex; flex-direction: column;">
                                    <div style="margin-bottom: 10px;">
                                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: <?= $l['type'] === 'offer' ? '#10b981' : '#f59e0b' ?>;">
                                            <?= strtoupper($l['type']) ?>
                                        </span>
                                        <span style="font-size: 0.75rem; color: #94a3b8; float: right;">
                                            <?= date('M j, Y', strtotime($l['created_at'])) ?>
                                        </span>
                                    </div>
                                    <h4 style="margin: 0 0 5px 0; font-size: 1.1rem; line-height: 1.4;">
                                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings/<?= $l['id'] ?>" style="color: #1e293b; text-decoration: none;"><?= htmlspecialchars($l['title']) ?></a>
                                    </h4>

                                    <div class="dash-listing-card-actions" style="margin-top: auto; padding-top: 15px; border-top: 1px solid #f1f5f9; display: flex; gap: 10px;">
                                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings/<?= $l['id'] ?>" class="htb-btn htb-btn-sm" style="flex: 1; justify-content: center; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1;"><i class="fa-solid fa-eye"></i> View</a>
                                        <button onclick="deleteListing(<?= $l['id'] ?>)" class="htb-btn htb-btn-sm" style="flex: 1; justify-content: center; background: #fef2f2; border: 1px solid #fecaca; color: #ef4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function deleteListing(id) {
                if (!confirm("Are you sure you want to delete this listing? It cannot be undone.")) return;

                // Optimistic UI
                const el = document.getElementById('listing-' + id);
                if (el) el.style.opacity = '0.5';

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const headers = {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                };
                if (csrf) headers['X-CSRF-Token'] = csrf;

                const body = new URLSearchParams();
                body.append('id', id);
                if (csrf) body.append('csrf_token', csrf);

                fetch('<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/listings/delete', {
                        method: 'POST',
                        headers: headers,
                        body: body,
                        credentials: 'same-origin'
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(data => {
                                throw new Error(data.error || 'Request failed');
                            });
                        }
                        return res.json();
                    })
                    .then(data => {
                        if (data.success) {
                            if (el) el.remove();
                        } else {
                            alert('Failed: ' + (data.error || 'Unknown error'));
                            if (el) el.style.opacity = '1';
                        }
                    })
                    .catch(e => {
                        alert('Error: ' + e.message);
                        if (el) el.style.opacity = '1';
                    });
            }
        </script>

        <!-- TAB: WALLET -->
    <?php elseif ($tab === 'wallet'): ?>
        <?php
        $transactions = \Nexus\Models\Transaction::getHistory($_SESSION['user_id']);
        ?>
        <div class="dash-wallet-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Left: Balance & Actions -->
            <div style="display: flex; flex-direction: column; gap: 30px;">
                <!-- Balance Card -->
                <div class="htb-card" style="background: linear-gradient(135deg, #4f46e5, #818cf8); color: white;">
                    <div class="htb-card-body">
                        <div style="font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Current Balance</div>
                        <div class="dash-wallet-balance-amount" style="font-size: 3.5rem; font-weight: 800; line-height: 1; margin: 10px 0;">
                            <?= number_format($user['balance']) ?> <span style="font-size: 1.5rem; font-weight: 400; opacity: 0.8;">Credits</span>
                        </div>
                        <div style="font-size: 0.85rem; opacity: 0.8;">
                            1 Credit = 1 Hour of Service
                        </div>
                    </div>
                </div>

                <!-- Transfer Widget -->
                <div class="htb-card">
                    <div class="htb-card-header" style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #334155;">
                        <i class="fa-solid fa-paper-plane" style="margin-right: 8px; color: #4f46e5;"></i> Send Credits
                    </div>
                    <div class="htb-card-body">
                        <form id="transfer-form" class="dash-transfer-form" action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet/transfer" method="POST" onsubmit="return validateDashTransfer(this);">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="username" id="dashRecipientUsername" value="">
                            <input type="hidden" name="recipient_id" id="dashRecipientId" value="">

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 5px;">Recipient</label>

                                <!-- Selected User Chip -->
                                <div id="dashSelectedUser" style="display: none; align-items: center; gap: 10px; padding: 10px 14px; background: rgba(79, 70, 229, 0.1); border: 2px solid rgba(79, 70, 229, 0.3); border-radius: 8px; margin-bottom: 8px;">
                                    <div id="dashSelectedAvatar" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #4f46e5, #7c3aed); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px; flex-shrink: 0; overflow: hidden;">
                                        <span id="dashSelectedInitial">?</span>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div id="dashSelectedName" style="font-weight: 600; color: #1f2937; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">-</div>
                                        <div id="dashSelectedUsername" style="font-size: 0.85rem; color: #6b7280;">-</div>
                                    </div>
                                    <button type="button" onclick="clearDashSelection()" style="width: 28px; height: 28px; border-radius: 50%; background: transparent; border: none; color: #6b7280; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s;" onmouseover="this.style.background='rgba(239, 68, 68, 0.1)'; this.style.color='#ef4444';" onmouseout="this.style.background='transparent'; this.style.color='#6b7280';" title="Clear">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </div>

                                <!-- Search Input -->
                                <div id="dashSearchWrapper" style="position: relative;">
                                    <input type="text" id="dashUserSearch" placeholder="Search by name or username..." autocomplete="off" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 16px;">
                                    <div id="dashUserResults" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 2px solid rgba(79, 70, 229, 0.2); border-top: none; border-radius: 0 0 8px 8px; max-height: 280px; overflow-y: auto; z-index: 100; display: none; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);"></div>
                                </div>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 5px;">Amount</label>
                                <input type="number" name="amount" min="1" required placeholder="0" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 16px;">
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 5px;">Description (Optional)</label>
                                <textarea name="description" rows="2" placeholder="What is this for?" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 16px;"></textarea>
                            </div>
                            <button type="submit" id="transfer-btn" class="htb-btn htb-btn-primary" style="width: 100%; justify-content: center; padding: 14px 24px;"><i class="fa-solid fa-paper-plane"></i> Send Credits</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Transaction History -->
            <div class="htb-card" style="height: fit-content;">
                <div class="htb-card-header" style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #334155;">
                    <i class="fa-solid fa-clock-rotate-left" style="margin-right: 8px; color: #64748b;"></i> Recent Transactions
                </div>
                <div class="dash-transactions-table">
                    <?php if (empty($transactions)): ?>
                        <div style="padding: 40px; text-align: center; color: #94a3b8;">
                            <div style="font-size: 2.5rem; margin-bottom: 10px; opacity: 0.3;"><i class="fa-solid fa-receipt"></i></div>
                            <p>No transactions found.</p>
                        </div>
                    <?php else: ?>
                        <table class="htb-table" style="width: 100%">
                            <thead>
                                <tr style="background: #f8fafc; font-size: 0.8rem; text-transform: uppercase;">
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th style="text-align: right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($transactions, 0, 10) as $t):
                                    $isIncoming = $t['receiver_id'] == $_SESSION['user_id'];
                                ?>
                                    <tr>
                                        <td style="font-size: 0.85rem; color: #64748b;">
                                            <?= date('M j, Y', strtotime($t['created_at'])) ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; font-size: 0.9rem; color: #334155;">
                                                <?= $isIncoming ? 'Received from ' . htmlspecialchars($t['sender_name']) : 'Sent to ' . htmlspecialchars($t['receiver_name']) ?>
                                            </div>
                                            <?php if ($t['description']): ?>
                                                <div style="font-size: 0.8rem; color: #94a3b8; font-style: italic;">
                                                    "<?= htmlspecialchars($t['description']) ?>"
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right; font-weight: 700; color: <?= $isIncoming ? '#10b981' : '#ef4444' ?>;">
                                            <?= $isIncoming ? '+' : '-' ?><?= number_format($t['amount']) ?>
                                            <button onclick="deleteTransaction(<?= $t['id'] ?>)" style="background:none; border:none; color:#cbd5e1; cursor:pointer; margin-left:10px;" title="Delete Log"><i class="fa-solid fa-times"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            function deleteTransaction(id) {
                if (!confirm('Delete this transaction record?')) return;

                fetch('<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/wallet/delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: id
                        })
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + (res.error || 'Failed'));
                        }
                    });
            }

            // Dashboard User Search Functionality
            let dashSearchTimeout = null;
            let dashSelectedIndex = -1;

            const dashSearchInput = document.getElementById('dashUserSearch');
            const dashResultsContainer = document.getElementById('dashUserResults');
            const dashSearchWrapper = document.getElementById('dashSearchWrapper');
            const dashSelectedUser = document.getElementById('dashSelectedUser');
            const dashUsernameInput = document.getElementById('dashRecipientUsername');

            if (dashSearchInput) {
                dashSearchInput.addEventListener('input', function() {
                    const query = this.value.trim();
                    clearTimeout(dashSearchTimeout);

                    if (query.length < 1) {
                        dashResultsContainer.style.display = 'none';
                        dashResultsContainer.innerHTML = '';
                        return;
                    }

                    dashSearchTimeout = setTimeout(() => {
                        searchDashUsers(query);
                    }, 200);
                });

                dashSearchInput.addEventListener('keydown', function(e) {
                    const results = dashResultsContainer.querySelectorAll('.dash-user-result');
                    if (!results.length) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        dashSelectedIndex = Math.min(dashSelectedIndex + 1, results.length - 1);
                        updateDashResultsSelection(results);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        dashSelectedIndex = Math.max(dashSelectedIndex - 1, 0);
                        updateDashResultsSelection(results);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (dashSelectedIndex >= 0 && results[dashSelectedIndex]) {
                            results[dashSelectedIndex].click();
                        }
                    } else if (e.key === 'Escape') {
                        dashResultsContainer.style.display = 'none';
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!dashSearchWrapper.contains(e.target)) {
                        dashResultsContainer.style.display = 'none';
                    }
                });
            }

            function updateDashResultsSelection(results) {
                results.forEach((r, i) => {
                    if (i === dashSelectedIndex) {
                        r.style.background = 'rgba(79, 70, 229, 0.08)';
                    } else {
                        r.style.background = '';
                    }
                });
                if (results[dashSelectedIndex]) {
                    results[dashSelectedIndex].scrollIntoView({ block: 'nearest' });
                }
            }

            function searchDashUsers(query) {
                fetch('<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/wallet/user-search', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: query })
                })
                .then(res => res.json())
                .then(data => {
                    dashSelectedIndex = -1;

                    if (data.status === 'success' && data.users && data.users.length > 0) {
                        dashResultsContainer.innerHTML = data.users.map(user => {
                            const initial = (user.display_name || '?')[0].toUpperCase();
                            const avatarHtml = user.avatar_url
                                ? `<img src="${user.avatar_url}" alt="" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">`
                                : `<span>${initial}</span>`;

                            return `
                                <div class="dash-user-result" onclick="selectDashUser('${escapeHtml(user.username || '')}', '${escapeHtml(user.display_name)}', '${escapeHtml(user.avatar_url || '')}', '${user.id}')" style="display: flex; align-items: center; padding: 12px 16px; cursor: pointer; transition: background 0.15s; gap: 12px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #4f46e5, #7c3aed); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px; flex-shrink: 0; overflow: hidden;">${avatarHtml}</div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: #1f2937; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(user.display_name)}</div>
                                        <div style="font-size: 0.85rem; color: #6b7280;">${user.username ? '@' + escapeHtml(user.username) : '<em>No username set</em>'}</div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                        dashResultsContainer.style.display = 'block';
                    } else {
                        dashResultsContainer.innerHTML = '<div style="padding: 16px; text-align: center; color: #9ca3af; font-size: 0.9rem;">No users found</div>';
                        dashResultsContainer.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Dashboard user search error:', err);
                    dashResultsContainer.innerHTML = '<div style="padding: 16px; text-align: center; color: #9ca3af; font-size: 0.9rem;">Search error</div>';
                    dashResultsContainer.style.display = 'block';
                });
            }

            function selectDashUser(username, displayName, avatarUrl, userId) {
                dashUsernameInput.value = username;
                document.getElementById('dashRecipientId').value = userId || '';

                const initial = (displayName || '?')[0].toUpperCase();
                document.getElementById('dashSelectedAvatar').innerHTML = avatarUrl
                    ? `<img src="${avatarUrl}" alt="" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">`
                    : `<span>${initial}</span>`;
                document.getElementById('dashSelectedName').textContent = displayName;
                document.getElementById('dashSelectedUsername').textContent = username ? '@' + username : 'No username';

                dashSelectedUser.style.display = 'flex';
                dashSearchWrapper.style.display = 'none';
                dashResultsContainer.style.display = 'none';
            }

            function clearDashSelection() {
                dashUsernameInput.value = '';
                document.getElementById('dashRecipientId').value = '';
                dashSelectedUser.style.display = 'none';
                dashSearchWrapper.style.display = 'block';
                dashSearchInput.value = '';
                dashSearchInput.focus();
            }

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function validateDashTransfer(form) {
                const username = document.getElementById('dashRecipientUsername').value.trim();
                const recipientId = document.getElementById('dashRecipientId').value.trim();

                if (!username && !recipientId) {
                    alert('Please select a recipient from the search results.');
                    document.getElementById('dashUserSearch').focus();
                    return false;
                }

                // Disable button to prevent double submit
                const btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

                return true;
            }
        </script>

        <!-- TAB: EVENTS -->
    <?php elseif ($tab === 'events'): ?>
        <?php
        $hosting = \Nexus\Models\Event::getHosted($_SESSION['user_id']);
        $attending = \Nexus\Models\Event::getAttending($_SESSION['user_id']);
        ?>
        <div class="dash-events-flex" style="display: flex; gap: 30px; flex-wrap: wrap;">

            <!-- Left: Hosting -->
            <div style="flex: 1; min-width: 300px;">
                <div class="dash-events-section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; font-size: 1.1rem; color: #334155;"><i class="fa-solid fa-calendar-star" style="margin-right: 8px; color: #4f46e5;"></i>Hosting</h3>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/create" class="htb-btn htb-btn-sm htb-btn-primary"><i class="fa-solid fa-plus"></i> Create Event</a>
                </div>

                <?php if (empty($hosting)): ?>
                    <div class="htb-card" style="padding: 40px 20px; text-align: center; color: #94a3b8;">
                        <div style="font-size: 2.5rem; margin-bottom: 10px; opacity: 0.3;"><i class="fa-solid fa-calendar-xmark"></i></div>
                        <p style="margin: 0;">You are not hosting any upcoming events.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($hosting as $e): ?>
                            <div class="htb-card">
                                <div class="htb-card-body" style="padding: 16px;">
                                    <div class="dash-event-card-inner" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 0.8rem; font-weight: 700; color: #4f46e5; text-transform: uppercase;">
                                                <?= date('M j @ g:i A', strtotime($e['start_time'])) ?>
                                            </div>
                                            <h4 style="margin: 5px 0; font-size: 1rem; line-height: 1.3;">
                                                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $e['id'] ?>" style="color: #1e293b; text-decoration: none;">
                                                    <?= htmlspecialchars($e['title']) ?>
                                                </a>
                                            </h4>
                                            <div style="font-size: 0.85rem; color: #64748b;">
                                                <i class="fa-solid fa-location-dot" style="margin-right: 5px;"></i> <?= htmlspecialchars($e['location']) ?>
                                            </div>
                                        </div>
                                        <div class="dash-event-stats" style="text-align: right; font-size: 0.8rem; color: #64748b; white-space: nowrap;">
                                            <div><strong style="color: #10b981;"><?= $e['attending_count'] ?></strong> Going</div>
                                            <div><strong><?= $e['invited_count'] ?></strong> Invited</div>
                                        </div>
                                    </div>
                                    <div class="dash-event-actions" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9; display: flex; gap: 10px;">
                                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $e['id'] ?>/edit" class="htb-btn htb-btn-sm" style="flex: 1; justify-content: center; background: #f8fafc; border: 1px solid #cbd5e1;"><i class="fa-solid fa-pen"></i> Edit</a>
                                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $e['id'] ?>" class="htb-btn htb-btn-sm" style="flex: 1; justify-content: center; background: #f8fafc; border: 1px solid #cbd5e1;"><i class="fa-solid fa-users"></i> Manage</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Attending -->
            <div style="flex: 1; min-width: 300px;">
                <h3 style="margin: 0 0 15px 0; font-size: 1.1rem; color: #334155;"><i class="fa-solid fa-calendar-check" style="margin-right: 8px; color: #10b981;"></i>Attending</h3>

                <?php if (empty($attending)): ?>
                    <div class="htb-card" style="padding: 40px 20px; text-align: center; color: #94a3b8;">
                        <div style="font-size: 2.5rem; margin-bottom: 10px; opacity: 0.3;"><i class="fa-solid fa-calendar-plus"></i></div>
                        <p style="margin: 0;">You are not attending any upcoming events.</p>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events" style="color: #4f46e5; font-size: 0.9rem; display: inline-block; margin-top: 10px;">Browse Events</a>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($attending as $e): ?>
                            <div class="htb-card">
                                <div class="htb-card-body" style="padding: 16px; display: flex; gap: 14px;">
                                    <!-- Date Box -->
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 56px; min-width: 56px; background: linear-gradient(135deg, #eff6ff, #e0e7ff); border-radius: 10px; padding: 8px 0;">
                                        <div style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700; color: #4f46e5;"><?= date('M', strtotime($e['start_time'])) ?></div>
                                        <div style="font-size: 1.4rem; font-weight: 800; color: #1e293b; line-height: 1;"><?= date('j', strtotime($e['start_time'])) ?></div>
                                    </div>

                                    <!-- Details -->
                                    <div style="flex: 1; min-width: 0;">
                                        <h4 style="margin: 0 0 5px 0; font-size: 0.95rem; line-height: 1.3;">
                                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $e['id'] ?>" style="color: #1e293b; text-decoration: none;">
                                                <?= htmlspecialchars($e['title']) ?>
                                            </a>
                                        </h4>
                                        <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 6px;">
                                            <?= date('g:i A', strtotime($e['start_time'])) ?> • <?= htmlspecialchars($e['organizer_name']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #10b981; font-weight: 600; background: #ecfdf5; display: inline-block; padding: 3px 8px; border-radius: 99px;">
                                            <i class="fa-solid fa-check-circle"></i> Going
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="htb-card" style="padding: 50px; text-align: center;">
            <p>This section is under construction or linked elsewhere.</p>
            <a href="?tab=overview" class="htb-btn htb-btn-primary">Back to Dashboard</a>
        </div>
    <?php endif; ?>

</div>

</div><!-- End dashboard-container -->

<!-- Dashboard FAB -->
<div class="dash-fab">
    <button class="dash-fab-main" onclick="toggleDashFab()" aria-label="Quick Actions">
        <i class="fa-solid fa-plus"></i>
    </button>
    <div class="dash-fab-menu" id="dashFabMenu">
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet" class="dash-fab-item">
            <i class="fa-solid fa-paper-plane icon-send"></i>
            <span>Send Credits</span>
        </a>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/listings/create" class="dash-fab-item">
            <i class="fa-solid fa-plus icon-listing"></i>
            <span>New Listing</span>
        </a>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/create" class="dash-fab-item">
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

    // Pull-to-refresh feature has been permanently removed
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>