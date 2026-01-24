<?php

/**
 * Component: Filter Bar
 *
 * Horizontal filter controls row with buttons and search.
 *
 * @param array $filters Array of filter buttons ['id' => '', 'label' => '', 'icon' => '', 'count' => 0]
 * @param string $active ID of the active filter
 * @param string $class Additional CSS classes
 * @param bool $showSearch Show search input (default: false)
 * @param string $searchQuery Current search query
 * @param string $searchPlaceholder Search placeholder text
 */

$filters = $filters ?? [];
$active = $active ?? '';
$class = $class ?? '';
$showSearch = $showSearch ?? false;
$searchQuery = $searchQuery ?? '';
$searchPlaceholder = $searchPlaceholder ?? 'Search...';

$cssClass = trim('filter-bar ' . $class);
?>

<div class="<?= e($cssClass) ?> component-filter-bar">
    <div class="filter-buttons component-btn-group">
        <?php foreach ($filters as $filter): ?>
            <?php
            $isActive = ($filter['id'] ?? '') === $active;
            $btnClass = 'notif-filter-btn' . ($isActive ? ' active' : '');
            ?>
            <button
                type="button"
                class="<?= e($btnClass) ?>"
                data-filter="<?= e($filter['id'] ?? '') ?>"
                <?php if ($isActive): ?>aria-pressed="true"<?php endif; ?>
            >
                <?php if (!empty($filter['icon'])): ?>
                    <i class="fa-solid fa-<?= e($filter['icon']) ?>"></i>
                <?php endif; ?>
                <span><?= e($filter['label'] ?? '') ?></span>
                <?php if (isset($filter['count'])): ?>
                    <span class="filter-count"><?= (int)$filter['count'] ?></span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if ($showSearch): ?>
        <div class="filter-search component-search-card__input-wrap">
            <input
                type="search"
                class="glass-search-input component-search-card__input"
                placeholder="<?= e($searchPlaceholder) ?>"
                value="<?= e($searchQuery) ?>"
            >
            <i class="fa-solid fa-search component-search-card__input-icon"></i>
        </div>
    <?php endif; ?>
</div>
