<?php

/**
 * Component: Avatar Stack
 *
 * Overlapping stack of avatars (like attendee displays).
 *
 * @param array $users Array of user data: ['image' => '', 'name' => '']
 * @param int $max Maximum avatars to show before "+N" (default: 3)
 * @param int $size Individual avatar size in pixels (default: 32)
 * @param int $overlap Overlap amount in pixels (default: 8)
 * @param string $class Additional CSS classes
 * @param bool $showCount Show remaining count (default: true)
 */

$users = $users ?? [];
$max = $max ?? 3;
$size = $size ?? 32;
$overlap = $overlap ?? 8;
$class = $class ?? '';
$showCount = $showCount ?? true;

$totalUsers = count($users);
$displayUsers = array_slice($users, 0, $max);
$remaining = $totalUsers - $max;

// Size classes
$sizeClasses = [
    24 => 'component-avatar-stack--xs',
    32 => 'component-avatar-stack--sm',
    40 => 'component-avatar-stack--md',
    48 => 'component-avatar-stack--lg',
];
$sizeClass = $sizeClasses[$size] ?? '';

$cssClass = trim(implode(' ', array_filter([
    'component-avatar-stack',
    'nexus-attendee-stack',
    $sizeClass,
    $class
])));
?>

<div class="<?= e($cssClass) ?>">
    <?php foreach ($displayUsers as $index => $user): ?>
        <div
            class="component-avatar-stack__item nexus-attendee"
            title="<?= e($user['name'] ?? 'User') ?>"
        >
            <?php if (!empty($user['image']) || !empty($user['avatar'])): ?>
                <?= webp_avatar($user['image'] ?? $user['avatar'] ?? '', $user['name'] ?? 'User', $size) ?>
            <?php else: ?>
                <?php
                $name = $user['name'] ?? 'User';
                $nameParts = explode(' ', trim($name));
                $initials = count($nameParts) >= 2
                    ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1))
                    : strtoupper(substr($name, 0, 2));
                ?>
                <span class="component-avatar-stack__initials">
                    <?= e($initials) ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($showCount && $remaining > 0): ?>
        <span class="component-avatar-stack__count">
            +<?= $remaining ?>
        </span>
    <?php endif; ?>
</div>
