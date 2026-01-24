<?php

/**
 * Component: Avatar
 *
 * User avatar with fallback to initials.
 *
 * @param string $image Image URL (or null/empty for initials)
 * @param string $name User name (for alt text and initials fallback)
 * @param int $size Size in pixels (default: 40)
 * @param bool $showRing Show colored ring around avatar (default: false)
 * @param string $ringColor Ring color (CSS value)
 * @param string $class Additional CSS classes
 * @param string $href Optional link URL
 * @param string $status Online status: 'online', 'away', 'offline', null (default: null)
 */

$image = $image ?? '';
$name = $name ?? 'User';
$size = $size ?? 40;
$showRing = $showRing ?? false;
$ringColor = $ringColor ?? 'var(--color-primary-500)';
$class = $class ?? '';
$href = $href ?? '';
$status = $status ?? null;

// Generate initials from name
$initials = '';
$nameParts = explode(' ', trim($name));
if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
} else {
    $initials = strtoupper(substr($name, 0, 2));
}

// Size classes
$sizeClasses = [
    24 => 'component-avatar--xs',
    32 => 'component-avatar--sm',
    40 => 'component-avatar--md',
    48 => 'component-avatar--lg',
    64 => 'component-avatar--xl',
    80 => 'component-avatar--2xl',
];

// Find closest size class or use custom size
$sizeClass = $sizeClasses[$size] ?? 'component-avatar--custom';
$useCustomSize = !isset($sizeClasses[$size]);

$cssClass = trim(implode(' ', array_filter([
    'component-avatar nexus-avatar',
    $sizeClass,
    $showRing ? 'component-avatar--ring' : '',
    $class
])));

$statusClasses = [
    'online' => 'component-avatar__status--online',
    'away' => 'component-avatar__status--away',
    'offline' => 'component-avatar__status--offline',
];

$tag = $href ? 'a' : 'span';
// Size is a truly dynamic value that must be inline
$customStyle = $useCustomSize ? "width: {$size}px; height: {$size}px;" : '';
?>

<<?= $tag ?>
    <?php if ($href): ?>href="<?= e($href) ?>"<?php endif; ?>
    class="<?= e($cssClass) ?>"
    <?php if ($customStyle): ?>style="<?= e($customStyle) ?>"<?php endif; ?>
>
    <?php if ($image): ?>
        <?= webp_avatar($image, $name, $showRing ? $size - 6 : $size) ?>
    <?php else: ?>
        <span class="component-avatar__initials" aria-label="<?= e($name) ?>">
            <?= e($initials) ?>
        </span>
    <?php endif; ?>

    <?php if ($status): ?>
        <span
            class="component-avatar__status <?= $statusClasses[$status] ?? $statusClasses['offline'] ?>"
            aria-label="<?= ucfirst(e($status)) ?>"
        ></span>
    <?php endif; ?>
</<?= $tag ?>>
