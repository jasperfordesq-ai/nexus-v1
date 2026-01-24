<?php

/**
 * Component: Empty State
 *
 * Display when there's no data/content to show.
 *
 * @param string $icon Emoji or FontAwesome icon name
 * @param string $title Heading text
 * @param string $message Description text
 * @param array $action Optional CTA button: ['label' => '', 'href' => '', 'icon' => '', 'variant' => '']
 * @param string $class Additional CSS classes
 * @param string $variant Style variant: 'default', 'glass', 'compact' (default: 'glass')
 */

$icon = $icon ?? '📭';
$title = $title ?? 'Nothing here yet';
$message = $message ?? '';
$action = $action ?? [];
$class = $class ?? '';
$variant = $variant ?? 'glass';

$variantClasses = [
    'default' => 'empty-state',
    'glass' => 'glass-empty-state',
    'compact' => 'empty-state empty-state-compact',
];
$baseClass = $variantClasses[$variant] ?? $variantClasses['glass'];

$cssClass = trim($baseClass . ' ' . $class);

// Check if icon is emoji or FontAwesome
$isEmoji = !preg_match('/^[a-z0-9-]+$/', $icon);
?>

<div class="<?= e($cssClass) ?>">
    <div class="empty-icon">
        <?php if ($isEmoji): ?>
            <?= $icon ?>
        <?php else: ?>
            <i class="fa-solid fa-<?= e($icon) ?>"></i>
        <?php endif; ?>
    </div>

    <?php if ($title): ?>
        <h3 class="empty-title"><?= e($title) ?></h3>
    <?php endif; ?>

    <?php if ($message): ?>
        <p class="empty-message"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if (!empty($action['label'])): ?>
        <?php
        $actionVariant = $action['variant'] ?? 'primary';
        $actionClass = 'nexus-smart-btn nexus-smart-btn-' . $actionVariant;
        ?>
        <a href="<?= e($action['href'] ?? '#') ?>" class="<?= e($actionClass) ?>">
            <?php if (!empty($action['icon'])): ?>
                <i class="fa-solid fa-<?= e($action['icon']) ?>"></i>
            <?php endif; ?>
            <?= e($action['label']) ?>
        </a>
    <?php endif; ?>
</div>
