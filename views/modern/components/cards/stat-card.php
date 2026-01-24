<?php

/**
 * Component: Stat Card
 *
 * Card for displaying a single statistic/metric.
 *
 * @param string $label Stat label
 * @param string|int $value Stat value
 * @param string $icon FontAwesome icon name (without fa- prefix)
 * @param string $trend Trend indicator: 'up', 'down', 'neutral' (default: null)
 * @param string $trendValue Trend value text (e.g., '+12%')
 * @param string $href Optional link URL
 * @param string $class Additional CSS classes
 * @param string $variant 'default', 'glass', 'compact' (default: 'glass')
 */

$label = $label ?? '';
$value = $value ?? 0;
$icon = $icon ?? '';
$trend = $trend ?? null;
$trendValue = $trendValue ?? '';
$href = $href ?? '';
$class = $class ?? '';
$variant = $variant ?? 'glass';

$variantClasses = [
    'default' => 'stat-card',
    'glass' => 'glass-stat-card',
    'compact' => 'stat-card-compact',
];
$baseClass = $variantClasses[$variant] ?? $variantClasses['glass'];
$cssClass = trim($baseClass . ' ' . $class);

$trendClasses = [
    'up' => 'trend-up',
    'down' => 'trend-down',
    'neutral' => 'trend-neutral',
];
$trendClass = $trendClasses[$trend] ?? '';
$trendIcon = $trend === 'up' ? 'arrow-up' : ($trend === 'down' ? 'arrow-down' : 'minus');

$tag = $href ? 'a' : 'div';
$hrefAttr = $href ? ' href="' . e($href) . '"' : '';
?>

<<?= $tag ?> class="<?= e($cssClass) ?>"<?= $hrefAttr ?>>
    <?php if ($icon): ?>
        <div class="stat-icon">
            <i class="fa-solid fa-<?= e($icon) ?>"></i>
        </div>
    <?php endif; ?>

    <div class="stat-content">
        <div class="stat-value"><?= e($value) ?></div>
        <div class="stat-label"><?= e($label) ?></div>
    </div>

    <?php if ($trend && $trendValue): ?>
        <div class="stat-trend <?= e($trendClass) ?>">
            <i class="fa-solid fa-<?= e($trendIcon) ?>"></i>
            <span><?= e($trendValue) ?></span>
        </div>
    <?php endif; ?>
</<?= $tag ?>>
