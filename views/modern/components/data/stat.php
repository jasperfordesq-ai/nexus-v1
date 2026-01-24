<?php

/**
 * Component: Stat
 *
 * Single statistic/metric display (inline version of stat-card).
 *
 * @param string|int $value Stat value
 * @param string $label Stat label
 * @param string $icon Optional FontAwesome icon name
 * @param string $prefix Value prefix (e.g., '$', '£')
 * @param string $suffix Value suffix (e.g., 'hrs', '%')
 * @param string $trend Trend direction: 'up', 'down', 'neutral' (default: null)
 * @param string $trendValue Trend value (e.g., '+12%')
 * @param string $class Additional CSS classes
 * @param string $size Size: 'sm', 'md', 'lg' (default: 'md')
 */

$value = $value ?? 0;
$label = $label ?? '';
$icon = $icon ?? '';
$prefix = $prefix ?? '';
$suffix = $suffix ?? '';
$trend = $trend ?? null;
$trendValue = $trendValue ?? '';
$class = $class ?? '';
$size = $size ?? 'md';

$sizeClasses = [
    'sm' => 'component-stat--sm',
    'md' => 'component-stat--md',
    'lg' => 'component-stat--lg',
];
$sizeClass = $sizeClasses[$size] ?? 'component-stat--md';

$trendClasses = [
    'up' => 'component-stat__trend--up',
    'down' => 'component-stat__trend--down',
    'neutral' => 'component-stat__trend--neutral',
];
$trendClass = $trendClasses[$trend] ?? '';
$trendIcon = $trend === 'up' ? 'arrow-up' : ($trend === 'down' ? 'arrow-down' : 'minus');

$cssClass = trim(implode(' ', array_filter(['component-stat', $sizeClass, $class])));
?>

<div class="<?= e($cssClass) ?>">
    <div class="component-stat__value-row">
        <?php if ($icon): ?>
            <i class="fa-solid fa-<?= e($icon) ?> component-stat__icon"></i>
        <?php endif; ?>

        <span class="component-stat__value">
            <?php if ($prefix): ?><span class="component-stat__prefix"><?= e($prefix) ?></span><?php endif; ?>
            <?= e($value) ?>
            <?php if ($suffix): ?><span class="component-stat__suffix"><?= e($suffix) ?></span><?php endif; ?>
        </span>

        <?php if ($trend && $trendValue): ?>
            <span class="component-stat__trend <?= e($trendClass) ?>">
                <i class="fa-solid fa-<?= e($trendIcon) ?>"></i>
                <?= e($trendValue) ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($label): ?>
        <span class="component-stat__label">
            <?= e($label) ?>
        </span>
    <?php endif; ?>
</div>
