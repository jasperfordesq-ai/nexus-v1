<?php

/**
 * Component: Button Group
 *
 * Group of buttons displayed together.
 *
 * @param array $buttons Array of button configs (same params as button.php)
 * @param string $class Additional CSS classes
 * @param string $align Alignment: 'left', 'center', 'right', 'between', 'around' (default: 'left')
 * @param string $gap Gap size: 'sm', 'md', 'lg' (default: 'md')
 * @param bool $wrap Allow wrapping on small screens (default: true)
 */

$buttons = $buttons ?? [];
$class = $class ?? '';
$align = $align ?? 'left';
$gap = $gap ?? 'md';
$wrap = $wrap ?? true;

// Build classes for alignment, gap, and wrap
$alignClasses = [
    'left' => 'component-button-group--left',
    'center' => 'component-button-group--center',
    'right' => 'component-button-group--right',
    'between' => 'component-button-group--between',
    'around' => 'component-button-group--around',
];

$gapClasses = [
    'sm' => 'component-button-group--gap-sm',
    'md' => 'component-button-group--gap-md',
    'lg' => 'component-button-group--gap-lg',
];

$alignClass = $alignClasses[$align] ?? $alignClasses['left'];
$gapClass = $gapClasses[$gap] ?? $gapClasses['md'];
$wrapClass = $wrap ? 'component-button-group--wrap' : '';

$cssClass = trim(implode(' ', array_filter([
    'nexus-smart-buttons',
    'component-button-group',
    $alignClass,
    $gapClass,
    $wrapClass,
    $class
])));
?>

<div class="<?= e($cssClass) ?>">
    <?php foreach ($buttons as $button): ?>
        <?php
        // Extract button params
        $btnLabel = $button['label'] ?? '';
        $btnVariant = $button['variant'] ?? 'primary';
        $btnIcon = $button['icon'] ?? '';
        $btnIconRight = $button['iconRight'] ?? '';
        $btnHref = $button['href'] ?? '';
        $btnType = $button['type'] ?? 'button';
        $btnDisabled = $button['disabled'] ?? false;
        $btnSize = $button['size'] ?? 'md';
        $btnClass = $button['class'] ?? '';
        $btnData = $button['data'] ?? [];

        // Build class
        $variantClasses = [
            'primary' => 'nexus-smart-btn nexus-smart-btn-primary',
            'secondary' => 'nexus-smart-btn nexus-smart-btn-secondary',
            'outline' => 'nexus-smart-btn nexus-smart-btn-outline',
            'ghost' => 'nexus-smart-btn nexus-smart-btn-ghost',
            'danger' => 'nexus-smart-btn nexus-smart-btn-danger',
        ];
        $baseClass = $variantClasses[$btnVariant] ?? $variantClasses['primary'];
        $finalClass = trim($baseClass . ' ' . $btnClass);

        // Build data attributes
        $dataString = '';
        foreach ($btnData as $key => $val) {
            $dataString .= ' data-' . e($key) . '="' . e($val) . '"';
        }

        $tag = $btnHref ? 'a' : 'button';
        ?>
        <<?= $tag ?>
            <?php if ($tag === 'button'): ?>
                type="<?= e($btnType) ?>"
            <?php else: ?>
                href="<?= e($btnHref) ?>"
            <?php endif; ?>
            class="<?= e($finalClass) ?>"
            <?php if ($btnDisabled): ?>disabled aria-disabled="true"<?php endif; ?>
            <?= $dataString ?>
        >
            <?php if ($btnIcon): ?>
                <i class="fa-solid fa-<?= e($btnIcon) ?>"></i>
            <?php endif; ?>
            <?php if ($btnLabel): ?>
                <span><?= e($btnLabel) ?></span>
            <?php endif; ?>
            <?php if ($btnIconRight): ?>
                <i class="fa-solid fa-<?= e($btnIconRight) ?>"></i>
            <?php endif; ?>
        </<?= $tag ?>>
    <?php endforeach; ?>
</div>
