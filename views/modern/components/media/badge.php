<?php

/**
 * Component: Badge
 *
 * Small label/tag for status, categories, or counts.
 *
 * @param string $text Badge text
 * @param string $variant Color variant: 'primary', 'secondary', 'success', 'warning', 'danger', 'info', 'muted' (default: 'primary')
 * @param string $icon Optional FontAwesome icon name
 * @param string $size Size: 'sm', 'md', 'lg' (default: 'md')
 * @param string $class Additional CSS classes
 * @param bool $pill Pill shape (more rounded) (default: false)
 * @param string $href Optional link URL
 */

$text = $text ?? '';
$variant = $variant ?? 'primary';
$icon = $icon ?? '';
$size = $size ?? 'md';
$class = $class ?? '';
$pill = $pill ?? false;
$href = $href ?? '';

$variantClasses = [
    'primary' => 'badge-primary',
    'secondary' => 'badge-secondary',
    'success' => 'badge-success',
    'warning' => 'badge-warning',
    'danger' => 'badge-danger',
    'info' => 'badge-info',
    'muted' => 'badge-muted',
];

$sizeClasses = [
    'sm' => 'badge-sm',
    'md' => '',
    'lg' => 'badge-lg',
];

$baseClass = 'badge';
$variantClass = $variantClasses[$variant] ?? $variantClasses['primary'];
$sizeClass = $sizeClasses[$size] ?? '';
$pillClass = $pill ? 'badge-pill' : '';

$cssClass = trim(implode(' ', array_filter([$baseClass, $variantClass, $sizeClass, $pillClass, $class])));

$tag = $href ? 'a' : 'span';
$hrefAttr = $href ? ' href="' . e($href) . '"' : '';
?>

<<?= $tag ?> class="<?= e($cssClass) ?>"<?= $hrefAttr ?>>
    <?php if ($icon): ?>
        <i class="fa-solid fa-<?= e($icon) ?> badge-icon"></i>
    <?php endif; ?>
    <?php if ($text): ?>
        <span class="badge-text"><?= e($text) ?></span>
    <?php endif; ?>
</<?= $tag ?>>
