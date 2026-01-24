<?php

/**
 * Component: Dropdown
 *
 * Dropdown menu with trigger button and items.
 *
 * @param string $trigger Trigger button content (HTML allowed)
 * @param array $items Array of menu items ['label' => '', 'href' => '', 'icon' => '', 'divider' => false, 'danger' => false]
 * @param string $class Additional CSS classes
 * @param string $position Dropdown position: 'left', 'right' (default: 'left')
 * @param string $id Optional ID for the dropdown
 */

$trigger = $trigger ?? 'Menu';
$items = $items ?? [];
$class = $class ?? '';
$position = $position ?? 'left';
$id = $id ?? 'dropdown-' . md5(microtime());

$cssClass = trim('htb-dropdown ' . $class);
$menuClass = 'htb-dropdown-content' . ($position === 'right' ? ' dropdown-right' : '');
?>

<div class="<?= e($cssClass) ?>" id="<?= e($id) ?>">
    <button type="button" class="htb-dropdown-trigger" aria-haspopup="true" aria-expanded="false">
        <?= $trigger ?>
    </button>
    <div class="<?= e($menuClass) ?>" role="menu">
        <?php foreach ($items as $item): ?>
            <?php if (!empty($item['divider'])): ?>
                <div class="dropdown-divider" role="separator"></div>
            <?php else: ?>
                <?php
                $itemClass = 'dropdown-item';
                if (!empty($item['danger'])) {
                    $itemClass .= ' dropdown-item-danger';
                }
                ?>
                <a href="<?= e($item['href'] ?? '#') ?>" class="<?= e($itemClass) ?>" role="menuitem">
                    <?php if (!empty($item['icon'])): ?>
                        <i class="fa-solid fa-<?= e($item['icon']) ?>"></i>
                    <?php endif; ?>
                    <span><?= e($item['label'] ?? '') ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
