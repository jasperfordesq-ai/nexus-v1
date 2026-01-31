<?php
/**
 * Listings Filter Panel Partial
 *
 * A reusable GOV.UK-styled filter panel for listings pages.
 * Source: GOV.UK Design System checkboxes component
 * https://github.com/alphagov/govuk-frontend/tree/main/packages/govuk-frontend/src/govuk/components/checkboxes
 *
 * Required variables:
 * @var string $filterAction - Form action URL (e.g., $basePath . '/listings')
 * @var array $categories - Array of categories with 'id', 'slug', 'name' keys
 *
 * Optional variables:
 * @var string $clearUrl - URL to clear filters (defaults to $filterAction)
 * @var bool $showTypeFilter - Whether to show offer/request filter (default: true)
 * @var bool $showCategoryFilter - Whether to show category filter (default: true)
 */

// Defaults
$clearUrl = $clearUrl ?? $filterAction;
$showTypeFilter = $showTypeFilter ?? true;
$showCategoryFilter = $showCategoryFilter ?? true;

// Get current filter values from query string
$currentSearch = $_GET['q'] ?? '';
$currentTypes = $_GET['type'] ?? [];
$currentCategories = $_GET['category'] ?? [];
$hasFilters = !empty($currentSearch) || !empty($currentTypes) || !empty($currentCategories);
?>
<div class="civicone-panel-bg">
    <h2 class="govuk-heading-m">Filter listings</h2>

    <form method="get" action="<?= htmlspecialchars($filterAction) ?>">
        <!-- Search Input -->
        <div class="govuk-form-group">
            <label class="govuk-label" for="listing-search">
                Search by title or description
            </label>
            <input
                type="text"
                id="listing-search"
                name="q"
                class="govuk-input"
                placeholder="Enter keywords..."
                value="<?= htmlspecialchars($currentSearch) ?>"
            >
        </div>

        <?php if ($showTypeFilter): ?>
        <!-- Type Checkboxes -->
        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend">Type</legend>
                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input type="checkbox"
                               id="type-offer"
                               name="type[]"
                               value="offer"
                               class="govuk-checkboxes__input"
                               <?= in_array('offer', $currentTypes) ? 'checked' : '' ?>>
                        <label class="govuk-label govuk-checkboxes__label" for="type-offer">Offers</label>
                    </div>
                    <div class="govuk-checkboxes__item">
                        <input type="checkbox"
                               id="type-request"
                               name="type[]"
                               value="request"
                               class="govuk-checkboxes__input"
                               <?= in_array('request', $currentTypes) ? 'checked' : '' ?>>
                        <label class="govuk-label govuk-checkboxes__label" for="type-request">Requests</label>
                    </div>
                </div>
            </fieldset>
        </div>
        <?php endif; ?>

        <?php if ($showCategoryFilter && !empty($categories)): ?>
        <!-- Category Checkboxes -->
        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend">Category</legend>
                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                    <?php foreach ($categories as $category):
                        $catId = htmlspecialchars($category['slug'] ?? $category['id']);
                        $catName = htmlspecialchars($category['name']);
                    ?>
                    <div class="govuk-checkboxes__item">
                        <input type="checkbox"
                               id="category-<?= $catId ?>"
                               name="category[]"
                               value="<?= $catId ?>"
                               class="govuk-checkboxes__input"
                               <?= in_array($category['slug'] ?? $category['id'], $currentCategories) ? 'checked' : '' ?>>
                        <label class="govuk-label govuk-checkboxes__label" for="category-<?= $catId ?>">
                            <?= $catName ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>
        <?php endif; ?>

        <button type="submit" class="govuk-button govuk-button--secondary">
            Apply filters
        </button>
    </form>

    <?php if ($hasFilters): ?>
    <!-- Active Filters Summary -->
    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">
    <h3 class="govuk-heading-s">Active filters</h3>
    <p class="govuk-body">
        <?php if (!empty($currentSearch)): ?>
            <strong class="govuk-tag">Search: <?= htmlspecialchars($currentSearch) ?></strong>
        <?php endif; ?>
        <?php if (!empty($currentTypes)): ?>
            <?php foreach ($currentTypes as $type): ?>
                <strong class="govuk-tag govuk-tag--grey">Type: <?= htmlspecialchars(ucfirst($type)) ?></strong>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($currentCategories)): ?>
            <?php foreach ($currentCategories as $cat): ?>
                <strong class="govuk-tag govuk-tag--grey">Category: <?= htmlspecialchars(ucfirst(str_replace('-', ' ', $cat))) ?></strong>
            <?php endforeach; ?>
        <?php endif; ?>
    </p>
    <a href="<?= htmlspecialchars($clearUrl) ?>" class="govuk-link">Clear all filters</a>
    <?php endif; ?>
</div>
