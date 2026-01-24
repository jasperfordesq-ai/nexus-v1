<?php

/**
 * Component: Progress Bar
 *
 * Horizontal progress indicator.
 *
 * @param int|float $percent Progress percentage (0-100)
 * @param string $label Optional label text
 * @param bool $showPercent Show percentage text (default: true)
 * @param string $color Color variant: 'primary', 'success', 'warning', 'danger', or CSS color (default: 'primary')
 * @param string $size Size: 'sm', 'md', 'lg' (default: 'md')
 * @param bool $animated Animate the fill (default: false)
 * @param bool $striped Show stripe pattern (default: false)
 * @param string $class Additional CSS classes
 * @param string $valuePrefix Prefix for value display (e.g., '$')
 * @param string $valueSuffix Suffix for value display (e.g., 'XP')
 * @param int|float $current Current value (optional, for displaying X/Y)
 * @param int|float $max Maximum value (optional, for displaying X/Y)
 */

$percent = max(0, min(100, (float)($percent ?? 0)));
$label = $label ?? '';
$showPercent = $showPercent ?? true;
$color = $color ?? 'primary';
$size = $size ?? 'md';
$animated = $animated ?? false;
$striped = $striped ?? false;
$class = $class ?? '';
$valuePrefix = $valuePrefix ?? '';
$valueSuffix = $valueSuffix ?? '';
$current = $current ?? null;
$max = $max ?? null;

// Color and size classes
$colorClasses = [
    'primary' => 'component-progress--primary',
    'success' => 'component-progress--success',
    'warning' => 'component-progress--warning',
    'danger' => 'component-progress--danger',
    'info' => 'component-progress--info',
];
$sizeClasses = [
    'sm' => 'component-progress--sm',
    'md' => 'component-progress--md',
    'lg' => 'component-progress--lg',
];

$colorClass = $colorClasses[$color] ?? 'component-progress--primary';
$sizeClass = $sizeClasses[$size] ?? 'component-progress--md';

$cssClass = trim(implode(' ', array_filter([
    'component-progress',
    $colorClass,
    $sizeClass,
    $striped ? 'component-progress--striped' : '',
    $animated ? 'component-progress--animated' : '',
    $class
])));

// Build value display
$valueDisplay = '';
if ($current !== null && $max !== null) {
    $valueDisplay = $valuePrefix . number_format($current) . ' / ' . number_format($max) . $valueSuffix;
} elseif ($showPercent) {
    $valueDisplay = round($percent) . '%';
}
?>

<div class="<?= e($cssClass) ?>">
    <?php if ($label || $valueDisplay): ?>
        <div class="component-progress__header">
            <?php if ($label): ?>
                <span class="component-progress__label"><?= e($label) ?></span>
            <?php endif; ?>
            <?php if ($valueDisplay): ?>
                <span class="component-progress__value"><?= e($valueDisplay) ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div
        class="component-progress__bar"
        role="progressbar"
        aria-valuenow="<?= $percent ?>"
        aria-valuemin="0"
        aria-valuemax="100"
    >
        <div class="component-progress__fill" style="width: <?= $percent ?>%;"></div>
    </div>
</div>
