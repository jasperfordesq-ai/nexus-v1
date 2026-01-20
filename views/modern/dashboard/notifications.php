<?php
/**
 * Modern Dashboard - Notifications Page
 * Dedicated route version (replaces tab-based approach)
 */

$hero_title = "Notifications";
$hero_subtitle = "Manage your notification preferences";
$hero_gradient = 'htb-hero-gradient-wallet';
$hero_type = 'Wallet';

require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

// Process Settings for UI
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

<div class="dashboard-glass-bg"></div>

<div class="dashboard-container">

    <!-- Glass Navigation -->
    <div class="dash-tabs-glass">
        <a href="<?= $basePath ?>/dashboard" class="dash-tab-glass">
            <i class="fa-solid fa-house"></i> Overview
        </a>
        <a href="<?= $basePath ?>/dashboard/notifications" class="dash-tab-glass active">
            <i class="fa-solid fa-bell"></i> Notifications
            <?php
            $uCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
            if ($uCount > 0): ?>
                <span class="dash-notif-badge"><?= $uCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $basePath ?>/dashboard/hubs" class="dash-tab-glass">
            <i class="fa-solid fa-users"></i> My Hubs
        </a>
        <a href="<?= $basePath ?>/dashboard/listings" class="dash-tab-glass">
            <i class="fa-solid fa-list"></i> My Listings
        </a>
        <a href="<?= $basePath ?>/dashboard/wallet" class="dash-tab-glass">
            <i class="fa-solid fa-wallet"></i> Wallet
        </a>
        <a href="<?= $basePath ?>/dashboard/events" class="dash-tab-glass">
            <i class="fa-solid fa-calendar"></i> Events
        </a>
    </div>

    <div class="htb-card">
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
                                        <a href="<?= htmlspecialchars($n['link']) ?>" onclick="window.nexusNotifications.markOneRead(<?= $n['id'] ?>)" class="htb-btn htb-btn-sm htb-btn-primary">View</a>
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
</div>

<script>
function openEventsModal() {
    const m = document.getElementById('events-modal');
    if (typeof m.showModal === "function") {
        m.showModal();
    } else {
        alert("Your browser does not support the dialog API.");
    }
}

function toggleNotifSettings() {
    document.getElementById('notif-settings-panel').classList.toggle('show');
}

function updateNotifSetting(type, id, freq) {
    fetch('<?= $basePath ?>/api/notifications/settings', {
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
    fetch('<?= $basePath ?>/api/notifications/delete', {
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

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
