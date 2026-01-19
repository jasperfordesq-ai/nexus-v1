<?php
// views/modern/notifications/index.php
// Modern Glassmorphism Notifications Page

use Nexus\Models\Notification;
use Nexus\Core\TenantContext;

$hTitle = 'Notifications';
$hSubtitle = 'Stay updated with your latest activity';
$pageTitle = 'Notifications';

$userId = $_SESSION['user_id'] ?? 0;
$basePath = TenantContext::getBasePath();

// Filter Logic - Default to unread
$filter = $_GET['filter'] ?? 'unread';

// Use $allNotifications from controller, or fetch if not set
if (!isset($allNotifications)) {
    $allNotifications = Notification::getAll($userId, 50);
}

// Apply filter
$displayNotifications = $allNotifications;
if ($filter === 'unread') {
    $displayNotifications = array_filter($allNotifications, function ($n) {
        return !$n['is_read'];
    });
}

// Count unread for badge
$unreadCount = count(array_filter($allNotifications, fn($n) => !$n['is_read']));

require __DIR__ . '/../../layouts/modern/header.php';
?>

<!-- GLASSMORPHISM NOTIFICATIONS PAGE -->
<!-- CSS moved to /assets/css/notifications.css -->

<div class="notif-page-wrapper">
    <div class="notif-glass-container">

        <!-- Header Card -->
        <div class="notif-header-card">
            <h1><i class="fa-solid fa-bell" style="margin-right: 12px; font-size: 24px;"></i>Notifications</h1>
            <p class="subtitle">Stay updated with your latest activity and messages</p>

            <!-- Toolbar -->
            <div class="notif-toolbar">
                <div class="notif-filters">
                    <a href="?filter=unread" class="notif-filter-btn <?= $filter === 'unread' ? 'active' : '' ?>">
                        <i class="fa-solid fa-circle-dot"></i>
                        Unread
                        <?php if ($unreadCount > 0): ?>
                            <span class="notif-filter-badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=all" class="notif-filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                        <i class="fa-solid fa-list"></i>
                        All
                    </a>
                </div>

                <div style="display: flex; gap: 10px;">
                    <?php if ($unreadCount > 0): ?>
                        <button onclick="markAllAsRead()" class="notif-action-btn primary">
                            <i class="fa-solid fa-check-double"></i>
                            Mark all read
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($allNotifications)): ?>
                        <button onclick="deleteAllNotifications()" class="notif-action-btn danger">
                            <i class="fa-solid fa-trash-can"></i>
                            Clear all
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="notif-list-card">
            <?php if (empty($displayNotifications)): ?>
                <div class="notif-empty">
                    <div class="notif-empty-icon">
                        <i class="fa-regular fa-bell-slash"></i>
                    </div>
                    <?php if ($filter === 'unread'): ?>
                        <h3>All caught up!</h3>
                        <p>You have no unread notifications. <a href="?filter=all" style="color: #6366f1; font-weight: 600;">View all notifications</a></p>
                    <?php else: ?>
                        <h3>No notifications yet</h3>
                        <p>When you get notifications, they'll show up here.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($displayNotifications as $n): ?>
                    <?php
                    // Determine icon based on notification type/message
                    $iconClass = 'fa-bell';
                    $iconType = 'default';

                    $type = $n['type'] ?? '';
                    $msg = strtolower($n['message'] ?? '');

                    if ($type === 'like' || stripos($msg, 'liked') !== false) {
                        $iconClass = 'fa-heart';
                        $iconType = 'like';
                    } elseif ($type === 'comment' || stripos($msg, 'comment') !== false) {
                        $iconClass = 'fa-comment';
                        $iconType = 'comment';
                    } elseif (stripos($msg, 'message') !== false) {
                        $iconClass = 'fa-envelope';
                        $iconType = 'message';
                    } elseif (stripos($msg, 'event') !== false) {
                        $iconClass = 'fa-calendar';
                        $iconType = 'event';
                    } elseif (stripos($msg, 'wallet') !== false || stripos($msg, 'credit') !== false || stripos($msg, 'sent') !== false) {
                        $iconClass = 'fa-coins';
                        $iconType = 'wallet';
                    } elseif (stripos($msg, 'group') !== false || stripos($msg, 'hub') !== false) {
                        $iconClass = 'fa-users';
                        $iconType = 'group';
                    }

                    $isUnread = !$n['is_read'];

                    // Time formatting
                    $timeDiff = time() - strtotime($n['created_at']);
                    if ($timeDiff < 60) {
                        $timeStr = 'Just now';
                    } elseif ($timeDiff < 3600) {
                        $timeStr = floor($timeDiff / 60) . 'm ago';
                    } elseif ($timeDiff < 86400) {
                        $timeStr = floor($timeDiff / 3600) . 'h ago';
                    } elseif ($timeDiff < 604800) {
                        $timeStr = floor($timeDiff / 86400) . 'd ago';
                    } else {
                        $timeStr = date('M j', strtotime($n['created_at']));
                    }
                    ?>
                    <div class="notif-item <?= $isUnread ? 'unread' : '' ?>" data-id="<?= $n['id'] ?>">
                        <div class="notif-icon <?= $iconType ?>">
                            <i class="fa-solid <?= $iconClass ?>"></i>
                        </div>

                        <div class="notif-content">
                            <p class="notif-message"><?= htmlspecialchars($n['message']) ?></p>
                            <div class="notif-meta">
                                <span class="time">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= $timeStr ?>
                                </span>
                                <?php if (!empty($n['link'])):
                                    // Ensure link uses basePath if it's a relative path
                                    $notifLink = $n['link'];
                                    if (strpos($notifLink, 'http') !== 0 && strpos($notifLink, $basePath) !== 0) {
                                        $notifLink = $basePath . $notifLink;
                                    }
                                ?>
                                    <a href="<?= htmlspecialchars($notifLink) ?>" class="view-link" onclick="markNotificationRead(<?= $n['id'] ?>)">
                                        View details <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="notif-actions">
                            <?php if ($isUnread): ?>
                                <div class="notif-unread-dot"></div>
                            <?php endif; ?>
                            <button onclick="deleteNotification(<?= $n['id'] ?>)" class="notif-delete-btn" title="Delete">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const BASE_PATH = '<?= $basePath ?>';

// Mark a single notification as read
function markNotificationRead(id) {
    fetch(BASE_PATH + '/api/notifications/read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({ id: id })
    }).catch(err => console.error('Failed to mark notification as read:', err));
}

// Mark all notifications as read
async function markAllAsRead() {
    try {
        const res = await fetch(BASE_PATH + '/api/notifications/read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ all: true })
        });

        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to mark all as read'));
        }
    } catch (e) {
        console.error('Mark all as read failed:', e);
        alert('Failed to mark all as read. Please try again.');
    }
}

// Delete single notification
async function deleteNotification(id) {
    if (!confirm('Delete this notification?')) return;

    try {
        const res = await fetch(BASE_PATH + '/api/notifications/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ id: id })
        });

        const data = await res.json();
        if (data.success) {
            // Animate removal
            const item = document.querySelector(`.notif-item[data-id="${id}"]`);
            if (item) {
                item.style.opacity = '0';
                item.style.transform = 'translateX(20px)';
                setTimeout(() => location.reload(), 200);
            } else {
                location.reload();
            }
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        console.error(e);
        alert('Failed to delete.');
    }
}

// Delete all notifications
async function deleteAllNotifications() {
    if (!confirm('Are you sure you want to delete ALL notifications? This cannot be undone.')) return;

    try {
        const res = await fetch(BASE_PATH + '/api/notifications/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ all: true })
        });

        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        console.error(e);
        alert('Failed to delete all.');
    }
}
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
