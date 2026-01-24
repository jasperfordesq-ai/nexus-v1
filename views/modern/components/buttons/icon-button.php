<?php

/**
 * Component: Icon Button
 *
 * Compact button with only an icon (no text label).
 *
 * @param string $icon FontAwesome icon name (required)
 * @param string $label Accessible label (for screen readers)
 * @param string $type Button type: 'button', 'submit' (default: 'button')
 * @param string $variant Style variant: 'default', 'primary', 'ghost', 'danger' (default: 'default')
 * @param string $size Size: 'sm', 'md', 'lg' (default: 'md')
 * @param string $href If set, renders as <a> instead of <button>
 * @param bool $disabled Disabled state
 * @param string $class Additional CSS classes
 * @param string $id Button ID
 * @param array $data Data attributes
 * @param string $title Tooltip text (defaults to label)
 */

$icon = $icon ?? 'ellipsis-h';
$label = $label ?? 'Button';
$type = $type ?? 'button';
$variant = $variant ?? 'default';
$size = $size ?? 'md';
$href = $href ?? '';
$disabled = $disabled ?? false;
$class = $class ?? '';
$id = $id ?? '';
$data = $data ?? [];
$title = $title ?? $label;

$variantClasses = [
    'default' => 'icon-btn',
    'primary' => 'icon-btn icon-btn-primary',
    'ghost' => 'icon-btn icon-btn-ghost',
    'danger' => 'icon-btn icon-btn-danger',
];

$sizeClasses = [
    'sm' => 'icon-btn-sm',
    'md' => '',
    'lg' => 'icon-btn-lg',
];

$baseClass = $variantClasses[$variant] ?? $variantClasses['default'];
$sizeClass = $sizeClasses[$size] ?? '';

$cssClass = trim(implode(' ', array_filter([$baseClass, $sizeClass, $class])));

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
    aria-label="<?= e($label) ?>"
    title="<?= e($title) ?>"
    <?php if ($disabled): ?>disabled aria-disabled="true"<?php endif; ?>
    <?= $dataString ?>
>
    <i class="fa-solid fa-<?= e($icon) ?>"></i>
</<?= $tag ?>>
