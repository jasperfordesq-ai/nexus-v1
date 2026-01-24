<?php

/**
 * Component: Timeline Item
 *
 * Single item in an activity timeline/log.
 * Used on: admin/activity_log, organizations/audit-log, federation/activity
 *
 * @param array $item Timeline item data with keys: id, type, title, description, actor, created_at, metadata
 * @param bool $showActor Show actor information (default: true)
 * @param bool $showTime Show timestamp (default: true)
 * @param string $class Additional CSS classes
 */

$item = $item ?? [];
$showActor = $showActor ?? true;
$showTime = $showTime ?? true;
$class = $class ?? '';

// Extract item data
$id = $item['id'] ?? 0;
$type = $item['type'] ?? $item['action'] ?? 'general';
$title = $item['title'] ?? $item['action_text'] ?? '';
$description = $item['description'] ?? $item['details'] ?? '';
$actor = $item['actor'] ?? $item['user'] ?? [];
$createdAt = $item['created_at'] ?? $item['timestamp'] ?? '';
$metadata = $item['metadata'] ?? [];

// Type classes
$typeClasses = [
    'create' => 'component-timeline__icon--success',
    'update' => 'component-timeline__icon--primary',
    'delete' => 'component-timeline__icon--danger',
    'login' => 'component-timeline__icon--info',
    'logout' => 'component-timeline__icon--muted',
    'approve' => 'component-timeline__icon--success',
    'reject' => 'component-timeline__icon--danger',
    'comment' => 'component-timeline__icon--primary',
    'transaction' => 'component-timeline__icon--warning',
    'message' => 'component-timeline__icon--primary',
    'system' => 'component-timeline__icon--muted',
    'general' => 'component-timeline__icon--muted',
];

$typeIcons = [
    'create' => 'plus-circle',
    'update' => 'pen',
    'delete' => 'trash',
    'login' => 'sign-in-alt',
    'logout' => 'sign-out-alt',
    'approve' => 'check-circle',
    'reject' => 'times-circle',
    'comment' => 'comment',
    'transaction' => 'exchange-alt',
    'message' => 'envelope',
    'system' => 'cog',
    'general' => 'circle',
];

$iconClass = $typeClasses[$type] ?? $typeClasses['general'];
$icon = $typeIcons[$type] ?? $typeIcons['general'];

// Format time
$timeFormatted = '';
$timeAgo = '';
if ($createdAt) {
    $timestamp = is_string($createdAt) ? strtotime($createdAt) : $createdAt;
    $timeFormatted = date('M j, Y \a\t g:i A', $timestamp);
    $diff = time() - $timestamp;
    if ($diff < 60) $timeAgo = 'Just now';
    elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
    elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
    elseif ($diff < 604800) $timeAgo = floor($diff / 86400) . 'd ago';
    else $timeAgo = date('M j', $timestamp);
}

$cssClass = trim('component-timeline__item ' . $class);
?>

<div class="<?= e($cssClass) ?>" id="timeline-<?= $id ?>">
    <!-- Timeline Line -->
    <div class="component-timeline__line"></div>

    <!-- Icon -->
    <div class="component-timeline__icon <?= e($iconClass) ?>">
        <i class="fa-solid fa-<?= e($icon) ?>"></i>
    </div>

    <!-- Content -->
    <div class="component-timeline__content">
        <div class="component-timeline__header">
            <div class="component-timeline__title-row">
                <?php if ($title): ?>
                    <span class="component-timeline__title">
                        <?= e($title) ?>
                    </span>
                <?php endif; ?>
                <?php if ($showActor && !empty($actor)): ?>
                    <span class="component-timeline__actor">
                        by
                        <a href="/members/<?= $actor['id'] ?? 0 ?>" class="component-timeline__actor-link">
                            <?= e($actor['name'] ?? 'Unknown') ?>
                        </a>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($showTime && $timeAgo): ?>
                <span class="component-timeline__time" title="<?= e($timeFormatted) ?>">
                    <?= e($timeAgo) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($description): ?>
            <p class="component-timeline__description">
                <?= e($description) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($metadata)): ?>
            <div class="component-timeline__metadata">
                <?php foreach ($metadata as $key => $value): ?>
                    <span class="component-timeline__meta-item">
                        <span class="component-timeline__meta-label"><?= e(ucfirst(str_replace('_', ' ', $key))) ?>:</span>
                        <span class="component-timeline__meta-value"><?= e($value) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
