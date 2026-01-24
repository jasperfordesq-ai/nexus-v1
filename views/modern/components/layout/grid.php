<?php

/**
 * Component: Grid
 *
 * Responsive grid layout for cards and other content.
 *
 * @param int $columns Number of columns (default: 3)
 * @param string $gap Gap size: 'sm', 'md', 'lg' (default: 'md')
 * @param string $content Grid content
 * @param string $class Additional CSS classes
 * @param string $id Optional ID attribute
 */

$columns = $columns ?? 3;
$gap = $gap ?? 'md';
$content = $content ?? '';
$class = $class ?? '';
$id = $id ?? '';

$gapSizes = [
    'sm' => '12px',
    'md' => '20px',
    'lg' => '30px',
];
$gapValue = $gapSizes[$gap] ?? $gapSizes['md'];

$cssClass = trim('glass-cards-grid ' . $class);
?>

<?php
$gridClass = $cssClass . ' component-grid component-grid--gap-' . $gap;
?>
<div class="<?= e($gridClass) ?>"<?php if ($id): ?> id="<?= e($id) ?>"<?php endif; ?>>
    <?= $content ?>
</div>
