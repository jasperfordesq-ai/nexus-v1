<?php

/**
 * Component: Search Card
 *
 * Glass search card with search input and filters.
 * Replaces 10+ duplicate implementations across the codebase.
 *
 * @param string $title Card heading (e.g., "Find Events")
 * @param int $count Number of items available
 * @param string $countLabel Label for count (e.g., "events available")
 * @param string $action Form action URL
 * @param string $method Form method (default: 'GET')
 * @param string $query Current search query
 * @param string $placeholder Search input placeholder
 * @param array $filters Array of filter configs: ['name' => '', 'type' => 'select', 'label' => '', 'options' => [], 'selected' => '']
 * @param string $submitLabel Submit button label (default: 'Search')
 * @param bool $showCount Whether to show count (default: true)
 * @param string $class Additional CSS classes
 */

$title = $title ?? 'Search';
$count = $count ?? 0;
$countLabel = $countLabel ?? 'items available';
$action = $action ?? '';
$method = $method ?? 'GET';
$query = $query ?? '';
$placeholder = $placeholder ?? 'Search...';
$filters = $filters ?? [];
$submitLabel = $submitLabel ?? 'Search';
$showCount = $showCount ?? true;
$class = $class ?? '';

$cssClass = trim('glass-search-card component-search-card ' . $class);
?>

<div class="<?= e($cssClass) ?>">
    <div class="search-card-inner component-search-card__inner">
        <div class="search-card-header component-search-card__header">
            <h2 class="search-card-title component-search-card__title">
                <?= e($title) ?>
            </h2>
            <?php if ($showCount): ?>
                <span class="search-card-count component-search-card__count">
                    <?= number_format($count) ?> <?= e($countLabel) ?>
                </span>
            <?php endif; ?>
        </div>

        <form action="<?= e($action) ?>" method="<?= e($method) ?>" class="search-card-form component-search-card__form">
            <div class="search-input-row component-search-card__input-wrap">
                <input
                    type="search"
                    name="q"
                    class="glass-search-input component-search-card__input"
                    placeholder="<?= e($placeholder) ?>"
                    value="<?= e($query) ?>"
                >
                <i class="fa-solid fa-search component-search-card__input-icon"></i>
            </div>

            <?php if (!empty($filters)): ?>
                <div class="filter-row component-search-card__filters">
                    <?php foreach ($filters as $filter): ?>
                        <?php
                        $filterName = $filter['name'] ?? '';
                        $filterType = $filter['type'] ?? 'select';
                        $filterLabel = $filter['label'] ?? '';
                        $filterOptions = $filter['options'] ?? [];
                        $filterSelected = $filter['selected'] ?? '';
                        $filterPlaceholder = $filter['placeholder'] ?? 'All';
                        ?>

                        <?php if ($filterType === 'select'): ?>
                            <div class="filter-field component-search-card__filter-field">
                                <?php if ($filterLabel): ?>
                                    <label class="filter-label component-search-card__filter-label">
                                        <?= e($filterLabel) ?>
                                    </label>
                                <?php endif; ?>
                                <select name="<?= e($filterName) ?>" class="glass-select component-search-card__select">
                                    <option value=""><?= e($filterPlaceholder) ?></option>
                                    <?php foreach ($filterOptions as $optValue => $optLabel): ?>
                                        <?php if (is_array($optLabel)): ?>
                                            <option value="<?= e($optLabel['value'] ?? $optValue) ?>" <?= ($optLabel['value'] ?? $optValue) == $filterSelected ? 'selected' : '' ?>>
                                                <?= e($optLabel['label'] ?? $optValue) ?>
                                            </option>
                                        <?php else: ?>
                                            <option value="<?= e($optValue) ?>" <?= $optValue == $filterSelected ? 'selected' : '' ?>>
                                                <?= e($optLabel) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="filter-submit component-search-card__submit">
                        <button type="submit" class="nexus-smart-btn nexus-smart-btn-primary">
                            <i class="fa-solid fa-search"></i>
                            <?= e($submitLabel) ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="search-submit component-search-card__submit component-search-card__submit--right">
                    <button type="submit" class="nexus-smart-btn nexus-smart-btn-primary">
                        <i class="fa-solid fa-search"></i>
                        <?= e($submitLabel) ?>
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
