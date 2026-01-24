<?php

/**
 * Component: Button
 *
 * Versatile button component supporting multiple variants and states.
 *
 * @param string $label Button text
 * @param string $type Button type: 'button', 'submit', 'reset' (default: 'button')
 * @param string $variant Style variant: 'primary', 'secondary', 'outline', 'ghost', 'danger' (default: 'primary')
 * @param string $size Size: 'sm', 'md', 'lg' (default: 'md')
 * @param string $icon FontAwesome icon name (left side)
 * @param string $iconRight FontAwesome icon name (right side)
 * @param string $href If set, renders as <a> instead of <button>
 * @param bool $disabled Disabled state
 * @param bool $loading Loading state (shows spinner)
 * @param bool $fullWidth Full width button
 * @param string $class Additional CSS classes
 * @param string $id Button ID
 * @param array $attributes Additional HTML attributes
 * @param array $data Data attributes
 */

$label = $label ?? '';
$type = $type ?? 'button';
$variant = $variant ?? 'primary';
$size = $size ?? 'md';
$icon = $icon ?? '';
$iconRight = $iconRight ?? '';
$href = $href ?? '';
$disabled = $disabled ?? false;
$loading = $loading ?? false;
$fullWidth = $fullWidth ?? false;
$class = $class ?? '';
$id = $id ?? '';
$attributes = $attributes ?? [];
$data = $data ?? [];

// Build class based on variant
$variantClasses = [
    'primary' => 'nexus-smart-btn nexus-smart-btn-primary',
    'secondary' => 'nexus-smart-btn nexus-smart-btn-secondary',
    'outline' => 'nexus-smart-btn nexus-smart-btn-outline',
    'ghost' => 'nexus-smart-btn nexus-smart-btn-ghost',
    'danger' => 'nexus-smart-btn nexus-smart-btn-danger',
];

$sizeClasses = [
    'sm' => 'btn-sm',
    'md' => '',
    'lg' => 'btn-lg',
];

$baseClass = $variantClasses[$variant] ?? $variantClasses['primary'];
$sizeClass = $sizeClasses[$size] ?? '';

$cssClass = trim(implode(' ', array_filter([
    $baseClass,
    $sizeClass,
    $fullWidth ? 'btn-full-width' : '',
    $loading ? 'btn-loading' : '',
    $class
])));

// Build attributes string
$attrString = '';
foreach ($attributes as $key => $val) {
    $attrString .= ' ' . e($key) . '="' . e($val) . '"';
}

// Build data attributes string
$dataString = '';
foreach ($data as $key => $val) {
    $dataString .= ' data-' . e($key) . '="' . e($val) . '"';
}

$tag = $href ? 'a' : 'button';
?>

<<?= $tag ?>
    <?php if ($tag === 'button'): ?>
        type="<?= e($type) ?>"
    <?php else: ?>
        href="<?= e($href) ?>"
    <?php endif; ?>
    <?php if ($id): ?>id="<?= e($id) ?>"<?php endif; ?>
    class="<?= e($cssClass) ?>"
    <?php if ($disabled): ?>disabled aria-disabled="true"<?php endif; ?>
    <?= $attrString ?>
    <?= $dataString ?>
>
    <?php if ($loading): ?>
        <span class="btn-spinner">
            <i class="fa-solid fa-spinner fa-spin"></i>
        </span>
    <?php elseif ($icon): ?>
        <i class="fa-solid fa-<?= e($icon) ?>"></i>
    <?php endif; ?>

    <?php if ($label): ?>
        <span class="btn-label"><?= e($label) ?></span>
    <?php endif; ?>

    <?php if ($iconRight && !$loading): ?>
        <i class="fa-solid fa-<?= e($iconRight) ?>"></i>
    <?php endif; ?>
</<?= $tag ?>>
