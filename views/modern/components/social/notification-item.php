<?php

/**
 * Component: Notification Item
 *
 * Single notification display item.
 * Used on: notifications/index, dashboard notifications
 *
 * @param array $notification Notification data with keys: id, type, title, message, read, created_at, link, actor
 * @param bool $showActions Show mark read/delete actions (default: true)
 * @param string $class Additional CSS classes
 */

$notification = $notification ?? [];
$showActions = $showActions ?? true;
$class = $class ?? '';

// Extract notification data
$id = $notification['id'] ?? 0;
$type = $notification['type'] ?? 'general';
$title = $notification['title'] ?? '';
$message = $notification['message'] ?? $notification['body'] ?? '';
$isRead = $notification['read'] ?? $notification['is_read'] ?? false;
$createdAt = $notification['created_at'] ?? '';
$link = $notification['link'] ?? $notification['url'] ?? '#';
$actor = $notification['actor'] ?? $notification['user'] ?? [];

// Type icons
$typeIcons = [
    'like' => 'heart',
    'comment' => 'comment',
    'follow' => 'user-plus',
    'mention' => 'at',
    'message' => 'envelope',
    'event' => 'calendar',
    'listing' => 'list',
    'transaction' => 'clock',
    'achievement' => 'trophy',
    'system' => 'bell',
    'general' => 'bell',
];
$icon = $typeIcons[$type] ?? 'bell';

// Type classes for colors
$typeClasses = [
    'like' => 'component-notification__icon--danger',
    'comment' => 'component-notification__icon--primary',
    'follow' => 'component-notification__icon--success',
    'mention' => 'component-notification__icon--info',
    'message' => 'component-notification__icon--primary',
    'event' => 'component-notification__icon--warning',
    'listing' => 'component-notification__icon--success',
    'transaction' => 'component-notification__icon--primary',
    'achievement' => 'component-notification__icon--warning',
    'system' => 'component-notification__icon--muted',
    'general' => 'component-notification__icon--muted',
];
$typeClass = $typeClasses[$type] ?? 'component-notification__icon--muted';

// Format time
$timeAgo = '';
if ($createdAt) {
    $timestamp = is_string($createdAt) ? strtotime($createdAt) : $createdAt;
    $diff = time() - $timestamp;
    if ($diff < 60) $timeAgo = 'Just now';
    elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
    elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
    elseif ($diff < 604800) $timeAgo = floor($diff / 86400) . 'd ago';
    else $timeAgo = date('M j', $timestamp);
}

$itemClass = 'component-notification';
$itemClass .= $isRead ? ' component-notification--read' : ' component-notification--unread';
$cssClass = trim($itemClass . ' ' . $class);

$titleClass = 'component-notification__title';
if (!$isRead) $titleClass .= ' component-notification__title--unread';
?>

<div class="<?= e($cssClass) ?>" id="notif-<?= $id ?>" data-notif-id="<?= $id ?>">
    <!-- Icon or Actor Avatar -->
    <div class="component-notification__icon-wrapper">
        <?php if (!empty($actor['avatar'])): ?>
            <div class="component-notification__avatar-wrapper">
                <?= webp_avatar($actor['avatar'], $actor['name'] ?? '', 44) ?>
                <span class="component-notification__type-badge <?= e($typeClass) ?>">
                    <i class="fa-solid fa-<?= e($icon) ?>"></i>
                </span>
            </div>
        <?php else: ?>
            <div class="component-notification__icon-circle <?= e($typeClass) ?>">
                <i class="fa-solid fa-<?= e($icon) ?>"></i>
            </div>
        <?php endif; ?>
    </div>

    <!-- Content -->
    <div class="component-notification__content">
        <a href="<?= e($link) ?>" class="component-notification__link">
            <?php if ($title): ?>
                <div class="<?= e($titleClass) ?>">
                    <?= e($title) ?>
                </div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="component-notification__message">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>
        </a>
        <div class="component-notification__meta">
            <span class="component-notification__time"><?= e($timeAgo) ?></span>
            <?php if (!$isRead): ?>
                <span class="component-notification__unread-dot"></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actions -->
    <?php if ($showActions): ?>
        <div class="component-notification__actions">
            <?php if (!$isRead): ?>
                <button
                    type="button"
                    class="component-notification__action-btn"
                    onclick="markNotificationRead(<?= $id ?>)"
                    title="Mark as read"
                >
                    <i class="fa-solid fa-check"></i>
                </button>
            <?php endif; ?>
            <button
                type="button"
                class="component-notification__action-btn"
                onclick="deleteNotification(<?= $id ?>)"
                title="Delete"
            >
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
</div>
