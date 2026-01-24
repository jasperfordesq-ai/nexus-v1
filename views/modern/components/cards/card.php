<?php

/**
 * Component: Card (Base)
 *
 * Generic card component that serves as base for all card types.
 *
 * @param string $header Card header content (HTML allowed)
 * @param string $body Card body content (HTML allowed)
 * @param string $footer Card footer content (HTML allowed)
 * @param string $class Additional CSS classes
 * @param string $variant 'default', 'glass', 'elevated' (default: 'glass')
 * @param string $href Optional link URL (makes entire card clickable)
 * @param string $id Optional ID attribute
 * @param array $data Optional data attributes ['key' => 'value']
 */

$header = $header ?? '';
$body = $body ?? '';
$footer = $footer ?? '';
$class = $class ?? '';
$variant = $variant ?? 'glass';
$href = $href ?? '';
$id = $id ?? '';
$data = $data ?? [];

$variantClasses = [
    'default' => 'htb-card',
    'glass' => 'glass-card',
    'elevated' => 'nexus-card',
];
$baseClass = $variantClasses[$variant] ?? $variantClasses['glass'];
$cssClass = trim($baseClass . ' ' . $class);

// Build data attributes
$dataAttrs = '';
foreach ($data as $key => $value) {
    $dataAttrs .= ' data-' . e($key) . '="' . e($value) . '"';
}

$tag = $href ? 'a' : 'div';
$hrefAttr = $href ? ' href="' . e($href) . '"' : '';
?>

<<?= $tag ?> class="<?= e($cssClass) ?>"<?php if ($id): ?> id="<?= e($id) ?>"<?php endif; ?><?= $hrefAttr ?><?= $dataAttrs ?>>
    <?php if ($header): ?>
        <div class="card-header">
            <?= $header ?>
        </div>
    <?php endif; ?>

    <?php if ($body): ?>
        <div class="card-body">
            <?= $body ?>
        </div>
    <?php endif; ?>

    <?php if ($footer): ?>
        <div class="card-footer">
            <?= $footer ?>
        </div>
    <?php endif; ?>
</<?= $tag ?>>
