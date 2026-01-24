<?php

/**
 * Component: Tabs
 *
 * Tab navigation for switching between content panels.
 *
 * @param array $tabs Array of tab items ['id' => '', 'label' => '', 'icon' => '', 'count' => 0]
 * @param string $activeTab ID of the active tab
 * @param string $class Additional CSS classes
 * @param string $variant 'default', 'glass', 'pills' (default: 'glass')
 */

$tabs = $tabs ?? [];
$activeTab = $activeTab ?? '';
$class = $class ?? '';
$variant = $variant ?? 'glass';

$variantClasses = [
    'default' => 'tab-navigation',
    'glass' => 'dash-tabs-glass',
    'pills' => 'nav-pills',
];
$baseClass = $variantClasses[$variant] ?? $variantClasses['glass'];
$cssClass = trim($baseClass . ' ' . $class);

$tabClass = $variant === 'glass' ? 'dash-tab-glass' : 'tab-item';
$activeClass = $variant === 'glass' ? 'active' : 'active';
?>

<nav class="<?= e($cssClass) ?>" role="tablist">
    <?php foreach ($tabs as $tab): ?>
        <?php
        $isActive = ($tab['id'] ?? '') === $activeTab;
        $itemClass = $tabClass . ($isActive ? ' ' . $activeClass : '');
        ?>
        <button
            type="button"
            role="tab"
            class="<?= e($itemClass) ?>"
            data-tab="<?= e($tab['id'] ?? '') ?>"
            aria-selected="<?= $isActive ? 'true' : 'false' ?>"
            <?php if (!$isActive): ?>tabindex="-1"<?php endif; ?>
        >
            <?php if (!empty($tab['icon'])): ?>
                <i class="fa-solid fa-<?= e($tab['icon']) ?>"></i>
            <?php endif; ?>
            <span><?= e($tab['label'] ?? '') ?></span>
            <?php if (isset($tab['count'])): ?>
                <span class="tab-count"><?= (int)$tab['count'] ?></span>
            <?php endif; ?>
        </button>
    <?php endforeach; ?>
</nav>
