<?php
/**
 * CivicOne Notifications Drawer v2
 *
 * COMPLETE REBUILD - Clean slide-out drawer
 *
 * WCAG 2.1 AA compliant:
 * - Focus trap when open
 * - Escape to close
 * - Screen reader announcements
 * - Keyboard navigation
 */

use Nexus\Core\Auth;
use Nexus\Core\TenantContext;
use Nexus\Services\NotificationService;

// Use $authUser to avoid overwriting view data (e.g., profile $user)
$authUser = Auth::user();
if (empty($authUser)) {
    return; // Don't render for logged-out users
}

$basePath = TenantContext::getBasePath();

// Get recent notifications (limit to 10 for drawer)
$notifications = NotificationService::getRecent($authUser['id'], 10);
?>
<!-- Notifications Drawer Overlay -->
<div id="notif-drawer-overlay" class="civicone-drawer-overlay" hidden></div>

<!-- Notifications Drawer -->
<aside id="notif-drawer"
       class="civicone-drawer"
       role="dialog"
       aria-labelledby="notif-drawer-title"
       aria-modal="true"
       hidden>

    <!-- Header -->
    <div class="civicone-drawer__header">
        <h2 id="notif-drawer-title" class="civicone-drawer__title">Notifications</h2>
        <button type="button"
                class="civicone-drawer__close"
                aria-label="Close notifications">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <!-- Content -->
    <div id="notif-list" class="civicone-drawer__content">
        <?php if (empty($notifications)): ?>
        <div class="civicone-notification civicone-notification--empty">
            <p>No notifications yet</p>
            <p>When you receive notifications, they'll appear here.</p>
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $notif):
            $isUnread = empty($notif['read_at']);
            $itemClass = 'civicone-notification';
            if ($isUnread) {
                $itemClass .= ' civicone-notification--unread';
            }
        ?>
        <div class="<?= $itemClass ?>">
            <p>
                <?php if (!empty($notif['link'])): ?>
                <a href="<?= htmlspecialchars($basePath . $notif['link']) ?>">
                    <?= htmlspecialchars($notif['message'] ?? $notif['title'] ?? 'Notification') ?>
                </a>
                <?php else: ?>
                <?= htmlspecialchars($notif['message'] ?? $notif['title'] ?? 'Notification') ?>
                <?php endif; ?>
            </p>
            <p class="civicone-notification__time">
                <?= \Nexus\Helpers\DateHelper::timeAgo($notif['created_at'] ?? 'now') ?>
            </p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="civicone-drawer__footer">
        <a href="<?= $basePath ?>/notifications" class="govuk-link">View all notifications</a>
        <?php if (!empty($notifications)): ?>
        <button type="button"
                class="govuk-button govuk-button--secondary civicone-drawer__footer-btn"
                id="mark-all-read">
            Mark all as read
        </button>
        <?php endif; ?>
    </div>
</aside>
