<?php

/**
 * Component: Icon
 *
 * FontAwesome icon wrapper with optional styling.
 *
 * @param string $name Icon name (without fa- prefix)
 * @param string $style Icon style: 'solid', 'regular', 'brands', 'light', 'duotone' (default: 'solid')
 * @param string $size Size: 'xs', 'sm', 'md', 'lg', 'xl', '2x', '3x' (default: 'md')
 * @param string $color Color (CSS value or variant name)
 * @param string $class Additional CSS classes
 * @param string $label Accessible label (aria-label)
 * @param bool $spin Add spin animation (default: false)
 * @param bool $pulse Add pulse animation (default: false)
 * @param string $rotation Rotation: '90', '180', '270', 'flip-horizontal', 'flip-vertical' (default: none)
 */

$name = $name ?? 'circle';
$style = $style ?? 'solid';
$size = $size ?? 'md';
$color = $color ?? '';
$class = $class ?? '';
$label = $label ?? '';
$spin = $spin ?? false;
$pulse = $pulse ?? false;
$rotation = $rotation ?? '';

$styleClasses = [
    'solid' => 'fa-solid',
    'regular' => 'fa-regular',
    'brands' => 'fa-brands',
    'light' => 'fa-light',
    'duotone' => 'fa-duotone',
];

$sizeClasses = [
    'xs' => 'fa-xs',
    'sm' => 'fa-sm',
    'md' => '',
    'lg' => 'fa-lg',
    'xl' => 'fa-xl',
    '2x' => 'fa-2x',
    '3x' => 'fa-3x',
];

$rotationClasses = [
    '90' => 'fa-rotate-90',
    '180' => 'fa-rotate-180',
    '270' => 'fa-rotate-270',
    'flip-horizontal' => 'fa-flip-horizontal',
    'flip-vertical' => 'fa-flip-vertical',
];

// Map color variants to CSS values
$colorVariants = [
    'primary' => 'var(--color-primary-500)',
    'secondary' => 'var(--color-secondary-500)',
    'success' => 'var(--color-success)',
    'warning' => 'var(--color-warning)',
    'danger' => 'var(--color-danger)',
    'info' => 'var(--color-info)',
    'muted' => 'var(--color-text-muted)',
];

$styleClass = $styleClasses[$style] ?? $styleClasses['solid'];
$sizeClass = $sizeClasses[$size] ?? '';
$rotationClass = $rotationClasses[$rotation] ?? '';

$cssClass = trim(implode(' ', array_filter([
    $styleClass,
    'fa-' . $name,
    $sizeClass,
    $spin ? 'fa-spin' : '',
    $pulse ? 'fa-pulse' : '',
    $rotationClass,
    $class
])));

$colorStyle = '';
if ($color) {
    $colorValue = $colorVariants[$color] ?? $color;
    $colorStyle = "color: {$colorValue};";
}
?>

<i
    class="<?= e($cssClass) ?>"
    <?php if ($colorStyle): ?>style="<?= e($colorStyle) ?>"<?php endif; ?>
    <?php if ($label): ?>aria-label="<?= e($label) ?>"<?php else: ?>aria-hidden="true"<?php endif; ?>
></i>
