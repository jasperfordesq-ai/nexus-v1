<?php

/**
 * Component: Image
 *
 * Optimized image component using webp_image helper.
 *
 * @param string $src Image source URL
 * @param string $alt Alt text (required for accessibility)
 * @param string $class Additional CSS classes
 * @param bool $lazy Enable lazy loading (default: true)
 * @param string $fallback Fallback image URL if main fails
 * @param int $width Optional width attribute
 * @param int $height Optional height attribute
 * @param string $aspectRatio Aspect ratio: '16:9', '4:3', '1:1', 'auto' (default: 'auto')
 * @param bool $cover Use object-fit: cover (default: true)
 */

$src = $src ?? '';
$alt = $alt ?? '';
$class = $class ?? '';
$lazy = $lazy ?? true;
$fallback = $fallback ?? '/assets/images/placeholder.webp';
$width = $width ?? null;
$height = $height ?? null;
$aspectRatio = $aspectRatio ?? 'auto';
$cover = $cover ?? true;

$cssClass = trim('component-image ' . $class);

$aspectClasses = [
    '16:9' => 'component-image__wrapper--16-9',
    '4:3' => 'component-image__wrapper--4-3',
    '1:1' => 'component-image__wrapper--1-1',
    '3:2' => 'component-image__wrapper--3-2',
    '21:9' => 'component-image__wrapper--21-9',
];

$useAspectRatio = isset($aspectClasses[$aspectRatio]);
$aspectClass = $aspectClasses[$aspectRatio] ?? '';
$coverClass = $cover ? 'component-image--cover' : '';
?>

<?php if ($useAspectRatio): ?>
<div class="component-image__wrapper <?= e($aspectClass) ?>">
    <?php if ($src): ?>
        <?= webp_image($src, $alt, $cssClass . ' component-image--fill ' . $coverClass, $width, $height, $lazy) ?>
    <?php else: ?>
        <img
            src="<?= e($fallback) ?>"
            alt="<?= e($alt ?: 'Placeholder image') ?>"
            class="<?= e($cssClass) ?> component-image--fill <?= e($coverClass) ?>"
            <?php if ($lazy): ?>loading="lazy"<?php endif; ?>
        >
    <?php endif; ?>
</div>
<?php else: ?>
    <?php if ($src): ?>
        <?= webp_image($src, $alt, $cssClass, $width, $height, $lazy) ?>
    <?php else: ?>
        <img
            src="<?= e($fallback) ?>"
            alt="<?= e($alt ?: 'Placeholder image') ?>"
            class="<?= e($cssClass) ?>"
            <?php if ($width): ?>width="<?= (int)$width ?>"<?php endif; ?>
            <?php if ($height): ?>height="<?= (int)$height ?>"<?php endif; ?>
            <?php if ($lazy): ?>loading="lazy"<?php endif; ?>
        >
    <?php endif; ?>
<?php endif; ?>
