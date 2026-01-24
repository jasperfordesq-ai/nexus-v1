<?php

/**
 * Component: Container
 *
 * Page container wrapper with optional full-width mode.
 *
 * @param string $class Additional CSS classes
 * @param bool $fullWidth Use full-width container (default: false)
 * @param string $content Content to wrap (or use as wrapper with include)
 */

$class = $class ?? '';
$fullWidth = $fullWidth ?? false;
$content = $content ?? '';

$containerClass = $fullWidth ? 'htb-container-full' : 'htb-container';
$cssClass = trim($containerClass . ' ' . $class);
?>

<div class="<?= e($cssClass) ?>">
    <?= $content ?>
</div>
