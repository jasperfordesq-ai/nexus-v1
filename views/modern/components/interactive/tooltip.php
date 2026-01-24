<?php

/**
 * Component: Tooltip
 *
 * Hover/focus tooltip for additional information.
 * Used on: messages, admin dashboard, federation analytics, pages builder
 *
 * @param string $content Tooltip content (HTML supported)
 * @param string $position Position: 'top', 'bottom', 'left', 'right' (default: 'top')
 * @param string $trigger Trigger: 'hover', 'click', 'focus' (default: 'hover')
 * @param string $id Element ID (default: auto-generated)
 * @param int $delay Show delay in ms (default: 200)
 * @param int $maxWidth Max width in px (default: 250) - kept as inline for truly dynamic value
 * @param string $class Additional CSS classes for wrapper
 * @param string $theme Theme: 'dark', 'light' (default: 'dark')
 */

$content = $content ?? '';
$position = $position ?? 'top';
$trigger = $trigger ?? 'hover';
$id = $id ?? 'tooltip-' . md5($content . microtime());
$delay = $delay ?? 200;
$maxWidth = $maxWidth ?? 250;
$class = $class ?? '';
$theme = $theme ?? 'dark';

if (empty($content)) {
    return;
}

// Position classes
$positionClasses = [
    'top' => 'component-tooltip__content--top',
    'bottom' => 'component-tooltip__content--bottom',
    'left' => 'component-tooltip__content--left',
    'right' => 'component-tooltip__content--right',
];
$positionClass = $positionClasses[$position] ?? $positionClasses['top'];

// Theme classes
$themeClasses = [
    'dark' => 'component-tooltip__content--dark',
    'light' => 'component-tooltip__content--light',
];
$themeClass = $themeClasses[$theme] ?? $themeClasses['dark'];

// Arrow classes
$arrowClasses = [
    'top' => 'component-tooltip__arrow--top',
    'bottom' => 'component-tooltip__arrow--bottom',
    'left' => 'component-tooltip__arrow--left',
    'right' => 'component-tooltip__arrow--right',
];
$arrowClass = $arrowClasses[$position] ?? $arrowClasses['top'];

$wrapperClass = trim('component-tooltip ' . $class);
$contentClass = trim('component-tooltip__content ' . $positionClass . ' ' . $themeClass);

// Dynamic max-width is acceptable as inline style
$maxWidthStyle = "max-width: {$maxWidth}px;";
?>

<span class="<?= htmlspecialchars($wrapperClass) ?>" id="<?= htmlspecialchars($id) ?>-wrapper">
    <span class="component-tooltip__trigger" id="<?= htmlspecialchars($id) ?>-trigger" tabindex="0">
        <?php /* Content slot - use with: $trigger = '<i class="fa-solid fa-info-circle"></i>'; include tooltip.php; */ ?>
    </span>

    <span
        class="<?= htmlspecialchars($contentClass) ?>"
        id="<?= htmlspecialchars($id) ?>"
        role="tooltip"
        style="<?= $maxWidthStyle ?>"
    >
        <?= $content ?>
        <span class="component-tooltip__arrow <?= htmlspecialchars($arrowClass) ?> <?= htmlspecialchars($themeClass) ?>"></span>
    </span>
</span>

<?php if ($trigger === 'click'): ?>
<script>
(function() {
    const wrapper = document.getElementById('<?= htmlspecialchars($id) ?>-wrapper');
    const trigger = document.getElementById('<?= htmlspecialchars($id) ?>-trigger');

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        wrapper.classList.toggle('component-tooltip--active');
    });

    document.addEventListener('click', function() {
        wrapper.classList.remove('component-tooltip--active');
    });
})();
</script>
<?php elseif ($delay > 0): ?>
<script>
(function() {
    const wrapper = document.getElementById('<?= htmlspecialchars($id) ?>-wrapper');
    let timeout;

    wrapper.addEventListener('mouseenter', function() {
        timeout = setTimeout(function() {
            wrapper.classList.add('component-tooltip--active');
        }, <?= (int)$delay ?>);
    });

    wrapper.addEventListener('mouseleave', function() {
        clearTimeout(timeout);
        wrapper.classList.remove('component-tooltip--active');
    });
})();
</script>
<?php endif; ?>
