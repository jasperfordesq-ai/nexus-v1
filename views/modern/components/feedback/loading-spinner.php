<?php

/**
 * Component: Loading Spinner
 *
 * Animated loading indicator.
 *
 * @param string $size Size: 'sm', 'md', 'lg', 'xl' (default: 'md')
 * @param string $message Optional loading message
 * @param string $class Additional CSS classes
 * @param bool $overlay Show as full-screen overlay (default: false)
 * @param string $variant Variant: 'spinner', 'dots', 'pulse' (default: 'spinner')
 */

$size = $size ?? 'md';
$message = $message ?? '';
$class = $class ?? '';
$overlay = $overlay ?? false;
$variant = $variant ?? 'spinner';

$sizeClass = 'component-loading--' . $size;
$variantClass = 'component-loading--' . $variant;
$cssClass = trim(implode(' ', array_filter(['component-loading', $sizeClass, $variantClass, $class])));
?>

<?php if ($overlay): ?>
<div class="component-loading__overlay">
<?php endif; ?>

<div class="<?= e($cssClass) ?>">
    <?php if ($variant === 'spinner'): ?>
        <div class="component-loading__spinner"></div>
    <?php elseif ($variant === 'dots'): ?>
        <div class="component-loading__dots">
            <div class="component-loading__dot"></div>
            <div class="component-loading__dot"></div>
            <div class="component-loading__dot"></div>
        </div>
    <?php elseif ($variant === 'pulse'): ?>
        <div class="component-loading__pulse"></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <span class="component-loading__message"><?= e($message) ?></span>
    <?php endif; ?>
</div>

<?php if ($overlay): ?>
</div>
<?php endif; ?>
