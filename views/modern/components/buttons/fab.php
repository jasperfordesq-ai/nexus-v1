<?php

/**
 * Component: FAB (Floating Action Button)
 *
 * Floating action button with optional expandable menu.
 *
 * @param string $icon Main FAB icon (default: 'plus')
 * @param string $label Accessible label
 * @param array $items Optional menu items for expandable FAB: ['icon' => '', 'label' => '', 'href' => '', 'onClick' => '']
 * @param string $position Position: 'bottom-right', 'bottom-left', 'bottom-center' (default: 'bottom-right')
 * @param string $variant Style variant: 'primary', 'secondary' (default: 'primary')
 * @param string $class Additional CSS classes
 * @param string $id FAB ID
 */

$icon = $icon ?? 'plus';
$label = $label ?? 'Actions';
$items = $items ?? [];
$position = $position ?? 'bottom-right';
$variant = $variant ?? 'primary';
$class = $class ?? '';
$id = $id ?? 'fab-' . md5(microtime());

$positionClasses = [
    'bottom-right' => 'component-fab--bottom-right',
    'bottom-left' => 'component-fab--bottom-left',
    'bottom-center' => 'component-fab--bottom-center',
];

$variantClasses = [
    'primary' => 'wallet-fab-main',
    'secondary' => 'wallet-fab-main fab-secondary',
];

$positionClass = $positionClasses[$position] ?? $positionClasses['bottom-right'];
$variantClass = $variantClasses[$variant] ?? $variantClasses['primary'];
$hasMenu = !empty($items);

$cssClass = trim(implode(' ', array_filter(['wallet-fab', 'component-fab', $positionClass, $class])));
?>

<div class="<?= e($cssClass) ?>" id="<?= e($id) ?>">
    <?php if ($hasMenu): ?>
        <div class="wallet-fab-menu component-fab__menu component-hidden">
            <?php foreach ($items as $index => $item): ?>
                <?php
                $itemIcon = $item['icon'] ?? 'circle';
                $itemLabel = $item['label'] ?? '';
                $itemHref = $item['href'] ?? '#';
                $itemOnClick = $item['onClick'] ?? '';
                ?>
                <a
                    href="<?= e($itemHref) ?>"
                    class="wallet-fab-item"
                    title="<?= e($itemLabel) ?>"
                    <?php if ($itemOnClick): ?>onclick="<?= e($itemOnClick) ?>"<?php endif; ?>
                >
                    <i class="fa-solid fa-<?= e($itemIcon) ?>"></i>
                    <?php if ($itemLabel): ?>
                        <span class="fab-item-label"><?= e($itemLabel) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <button
        type="button"
        class="<?= e($variantClass) ?>"
        aria-label="<?= e($label) ?>"
        aria-expanded="false"
        <?php if ($hasMenu): ?>
        onclick="toggleFabMenu('<?= e($id) ?>')"
        <?php endif; ?>
    >
        <i class="fa-solid fa-<?= e($icon) ?> fab-icon-main"></i>
        <?php if ($hasMenu): ?>
            <i class="fa-solid fa-times fab-icon-close component-hidden"></i>
        <?php endif; ?>
    </button>
</div>

<?php if ($hasMenu): ?>
<script>
function toggleFabMenu(fabId) {
    const fab = document.getElementById(fabId);
    if (!fab) return;

    const menu = fab.querySelector('.wallet-fab-menu');
    const mainBtn = fab.querySelector('.wallet-fab-main');
    const iconMain = fab.querySelector('.fab-icon-main');
    const iconClose = fab.querySelector('.fab-icon-close');

    const isOpen = !menu.classList.contains('component-hidden');

    menu.classList.toggle('component-hidden');
    menu.classList.toggle('component-fab__menu--open');
    mainBtn.setAttribute('aria-expanded', !isOpen);

    if (iconMain && iconClose) {
        iconMain.classList.toggle('component-hidden');
        iconClose.classList.toggle('component-hidden');
    }
}
</script>
<?php endif; ?>
