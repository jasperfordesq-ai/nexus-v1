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
<style>
/* Animated Gradient Background */
.notif-page-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.08) 0%,
        rgba(139, 92, 246, 0.08) 25%,
        rgba(236, 72, 153, 0.06) 50%,
        rgba(59, 130, 246, 0.08) 75%,
        rgba(16, 185, 129, 0.06) 100%);
    background-size: 400% 400%;
    animation: gradientShift 20s ease infinite;
    z-index: -1;
    pointer-events: none;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Glass Container */
.notif-glass-container {
    max-width: 720px;
    margin: 0 auto;
    padding: 160px 20px 80px;
}

@media (max-width: 768px) {
    .notif-glass-container {
        padding-top: 120px;
    }
}

/* Glass Header Card */
.notif-header-card {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.85),
        rgba(255, 255, 255, 0.65));
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 24px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12),
                0 2px 8px rgba(0, 0, 0, 0.06);
    padding: 28px 32px;
    margin-bottom: 24px;
}

.notif-header-card h1 {
    margin: 0 0 8px 0;
    font-size: 28px;
    font-weight: 800;
    background: linear-gradient(135deg, #1e293b, #475569);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.notif-header-card .subtitle {
    color: #64748b;
    font-size: 15px;
    margin: 0;
}

/* Filter & Actions Bar */
.notif-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 20px;
}

.notif-filters {
    display: flex;
    gap: 8px;
}

.notif-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.25s ease;
    border: 1.5px solid transparent;
}

.notif-filter-btn.active {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.15),
        rgba(139, 92, 246, 0.15));
    color: #6366f1;
    border-color: rgba(99, 102, 241, 0.3);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
}

.notif-filter-btn:not(.active) {
    background: rgba(255, 255, 255, 0.6);
    color: #64748b;
    border-color: rgba(148, 163, 184, 0.2);
}

.notif-filter-btn:not(.active):hover {
    background: rgba(255, 255, 255, 0.9);
    color: #475569;
    border-color: rgba(148, 163, 184, 0.4);
    transform: translateY(-1px);
}

.notif-filter-badge {
    background: linear-gradient(135deg, #ef4444, #f97316);
    color: white;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 50px;
    min-width: 20px;
    text-align: center;
}

/* Action Buttons */
.notif-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: all 0.25s ease;
}

.notif-action-btn.primary {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.9),
        rgba(139, 92, 246, 0.9));
    color: white;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
}

.notif-action-btn.primary:hover {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

.notif-action-btn.danger {
    background: rgba(254, 226, 226, 0.8);
    color: #b91c1c;
    border: 1px solid rgba(252, 165, 165, 0.5);
}

.notif-action-btn.danger:hover {
    background: rgba(254, 202, 202, 0.9);
    transform: translateY(-1px);
}

/* Glass Notifications List */
.notif-list-card {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.8),
        rgba(255, 255, 255, 0.6));
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 24px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1),
                0 2px 8px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

/* Empty State */
.notif-empty {
    padding: 80px 40px;
    text-align: center;
}

.notif-empty-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.1),
        rgba(139, 92, 246, 0.1));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
}

.notif-empty-icon i {
    font-size: 40px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.notif-empty h3 {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 8px 0;
}

.notif-empty p {
    color: #64748b;
    font-size: 15px;
    margin: 0;
}

/* Notification Item */
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid rgba(226, 232, 240, 0.6);
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
    position: relative;
}

.notif-item:last-child {
    border-bottom: none;
}

.notif-item:hover {
    background: rgba(99, 102, 241, 0.04);
}

.notif-item.unread {
    background: linear-gradient(90deg,
        rgba(99, 102, 241, 0.08) 0%,
        rgba(255, 255, 255, 0) 100%);
}

.notif-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #6366f1, #8b5cf6);
    border-radius: 0 4px 4px 0;
}

/* Notification Icon */
.notif-icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 20px;
}

.notif-icon.like {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(244, 63, 94, 0.15));
    color: #ef4444;
}

.notif-icon.comment {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(99, 102, 241, 0.15));
    color: #3b82f6;
}

.notif-icon.message {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(34, 197, 94, 0.15));
    color: #10b981;
}

.notif-icon.event {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(234, 88, 12, 0.15));
    color: #f59e0b;
}

.notif-icon.wallet {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
    color: #10b981;
}

.notif-icon.group {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.15));
    color: #8b5cf6;
}

.notif-icon.default {
    background: linear-gradient(135deg, rgba(100, 116, 139, 0.12), rgba(71, 85, 105, 0.12));
    color: #64748b;
}

/* Notification Content */
.notif-content {
    flex: 1;
    min-width: 0;
}

.notif-message {
    font-size: 15px;
    line-height: 1.5;
    color: #1e293b;
    margin: 0 0 6px 0;
}

.notif-item.unread .notif-message {
    font-weight: 600;
}

.notif-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    color: #64748b;
}

.notif-meta .time {
    display: flex;
    align-items: center;
    gap: 5px;
}

.notif-meta .view-link {
    color: #6366f1;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.2s;
}

.notif-meta .view-link:hover {
    color: #4f46e5;
}

/* Notification Actions */
.notif-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.notif-delete-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.notif-delete-btn:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* Unread Indicator */
.notif-unread-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    flex-shrink: 0;
    box-shadow: 0 0 8px rgba(99, 102, 241, 0.5);
}

/* Responsive */
@media (max-width: 640px) {
    .notif-glass-container {
        padding: 20px 16px 60px;
    }

    .notif-header-card {
        padding: 20px;
        border-radius: 20px;
    }

    .notif-header-card h1 {
        font-size: 24px;
    }

    .notif-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .notif-filters {
        justify-content: center;
    }

    .notif-item {
        padding: 16px;
    }

    .notif-icon {
        width: 44px;
        height: 44px;
        font-size: 18px;
    }
}

/* ========================================
   DARK MODE FOR NOTIFICATIONS PAGE
   ======================================== */

/* Background gradient - darker */
[data-theme="dark"] .notif-page-wrapper::before {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.12) 0%,
        rgba(139, 92, 246, 0.12) 25%,
        rgba(236, 72, 153, 0.08) 50%,
        rgba(59, 130, 246, 0.12) 75%,
        rgba(16, 185, 129, 0.08) 100%);
}

/* Glass Header Card */
[data-theme="dark"] .notif-header-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.85),
        rgba(30, 41, 59, 0.65));
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3),
                0 2px 8px rgba(0, 0, 0, 0.2);
}

[data-theme="dark"] .notif-header-card h1 {
    background: linear-gradient(135deg, #e2e8f0, #94a3b8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

[data-theme="dark"] .notif-header-card .subtitle {
    color: #94a3b8;
}

/* Filter Buttons */
[data-theme="dark"] .notif-filter-btn.active {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.25),
        rgba(139, 92, 246, 0.25));
    color: #a5b4fc;
    border-color: rgba(99, 102, 241, 0.4);
}

[data-theme="dark"] .notif-filter-btn:not(.active) {
    background: rgba(30, 41, 59, 0.6);
    color: #94a3b8;
    border-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .notif-filter-btn:not(.active):hover {
    background: rgba(51, 65, 85, 0.8);
    color: #e2e8f0;
    border-color: rgba(255, 255, 255, 0.2);
}

/* Action Buttons */
[data-theme="dark"] .notif-action-btn.danger {
    background: rgba(185, 28, 28, 0.2);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.3);
}

[data-theme="dark"] .notif-action-btn.danger:hover {
    background: rgba(185, 28, 28, 0.3);
}

/* Glass Notifications List */
[data-theme="dark"] .notif-list-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.8),
        rgba(30, 41, 59, 0.6));
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3),
                0 2px 8px rgba(0, 0, 0, 0.2);
}

/* Empty State */
[data-theme="dark"] .notif-empty-icon {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.2),
        rgba(139, 92, 246, 0.2));
}

[data-theme="dark"] .notif-empty h3 {
    color: #f1f5f9;
}

[data-theme="dark"] .notif-empty p {
    color: #94a3b8;
}

[data-theme="dark"] .notif-empty p a {
    color: #818cf8;
}

/* Notification Items */
[data-theme="dark"] .notif-item {
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .notif-item:hover {
    background: rgba(99, 102, 241, 0.08);
}

[data-theme="dark"] .notif-item.unread {
    background: linear-gradient(90deg,
        rgba(99, 102, 241, 0.15) 0%,
        rgba(30, 41, 59, 0) 100%);
}

/* Notification Content */
[data-theme="dark"] .notif-message {
    color: #f1f5f9;
}

[data-theme="dark"] .notif-meta {
    color: #94a3b8;
}

[data-theme="dark"] .notif-meta .view-link {
    color: #818cf8;
}

[data-theme="dark"] .notif-meta .view-link:hover {
    color: #a5b4fc;
}

/* Notification Icons - Dark Mode */
[data-theme="dark"] .notif-icon.like {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(244, 63, 94, 0.2));
}

[data-theme="dark"] .notif-icon.comment {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(99, 102, 241, 0.2));
}

[data-theme="dark"] .notif-icon.message {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(34, 197, 94, 0.2));
}

[data-theme="dark"] .notif-icon.event {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(234, 88, 12, 0.2));
}

[data-theme="dark"] .notif-icon.wallet {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
}

[data-theme="dark"] .notif-icon.group {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(168, 85, 247, 0.2));
}

[data-theme="dark"] .notif-icon.default {
    background: linear-gradient(135deg, rgba(100, 116, 139, 0.2), rgba(71, 85, 105, 0.2));
}

/* Delete Button */
[data-theme="dark"] .notif-delete-btn {
    color: #64748b;
}

[data-theme="dark"] .notif-delete-btn:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}
</style>

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
