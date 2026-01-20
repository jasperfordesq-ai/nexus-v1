<?php
/**
 * CivicOne Dashboard - Notifications Page
 * WCAG 2.1 AA Compliant
 * Template: Account Area Template (Template G)
 * Dedicated page for managing notifications (no longer a tab)
 */

$hTitle = "Notifications";
$hSubtitle = "Manage your notification preferences";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();

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

<div class="civic-dashboard civicone-account-area">

    <!-- Account Area Secondary Navigation -->
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

    <!-- NOTIFICATIONS CONTENT -->
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

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
