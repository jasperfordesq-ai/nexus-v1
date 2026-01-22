<?php
/**
 * CivicOne Groups Directory
 * Template A: Directory/List Page (Section 10.2)
 * With Page Hero (Section 9C: Page Hero Contract)
 */

// CivicOne layout header
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Breadcrumbs (GOV.UK Template A requirement) -->
        <nav class="civicone-breadcrumbs" aria-label="Breadcrumb">
            <ol class="civicone-breadcrumbs__list">
                <li class="civicone-breadcrumbs__list-item">
                    <a class="civicone-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
                </li>
                <li class="civicone-breadcrumbs__list-item" aria-current="page">
                    Groups
                </li>
            </ol>
        </nav>

        <!-- Hero (auto-resolves from config/heroes.php for /groups route) -->
        <?php require __DIR__ . '/../../layouts/civicone/partials/render-hero.php'; ?>

        <!-- MOJ Filter Pattern: 1/3 Filters + 2/3 Results -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-one-third">
                <a href="<?= $basePath ?>/create-group" class="civicone-button civicone-button--primary civicone-button--full-width-spaced">
                    Start a Hub
                </a>
            </div>
        </div>

        <!-- Directory Layout: 1/3 Filters + 2/3 Results -->
        <div class="civicone-grid-row">

            <!-- Filters Panel (1/3) -->
            <div class="civicone-grid-column-one-third">
                <div class="civicone-filter-panel" role="search" aria-label="Filter hubs">

                    <div class="civicone-filter-header">
                        <h2 class="civicone-heading-m">Filter hubs</h2>
                    </div>

                    <form method="get" action="<?= $basePath ?>/groups">
                        <div class="civicone-filter-group">
                            <label for="group-search" class="civicone-label">
                                Search by name or interest
                            </label>
                            <div class="civicone-search-wrapper">
                                <input
                                    type="text"
                                    id="group-search"
                                    name="q"
                                    class="civicone-input civicone-search-input"
                                    placeholder="Enter keywords..."
                                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                                >
                                <span class="civicone-search-icon" aria-hidden="true"></span>
                            </div>
                        </div>

                        <div class="civicone-filter-group">
                            <fieldset class="civicone-fieldset">
                                <legend class="civicone-label">Hub type</legend>
                                <div class="civicone-checkboxes">
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="type-community" name="type[]" value="community" class="civicone-checkbox" <?= in_array('community', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                        <label for="type-community" class="civicone-checkbox-label">Community</label>
                                    </div>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="type-interest" name="type[]" value="interest" class="civicone-checkbox" <?= in_array('interest', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                        <label for="type-interest" class="civicone-checkbox-label">Interest</label>
                                    </div>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="type-skill" name="type[]" value="skill" class="civicone-checkbox" <?= in_array('skill', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                        <label for="type-skill" class="civicone-checkbox-label">Skill Share</label>
                                    </div>
                                </div>
                            </fieldset>
                        </div>

                        <button type="submit" class="civicone-button civicone-button--secondary civicone-button--full-width">
                            Apply filters
                        </button>
                    </form>

                    <!-- Selected Filters (shown when filters are active) -->
                    <?php
                    $hasFilters = !empty($_GET['q']) || !empty($_GET['type']);
                    if ($hasFilters):
                    ?>
                    <div class="civicone-selected-filters">
                        <h3 class="civicone-heading-s">Active filters</h3>
                        <div class="civicone-filter-tags">
                            <?php if (!empty($_GET['q'])): ?>
                            <a href="<?= $basePath ?>/groups" class="civicone-tag civicone-tag--removable">
                                Search: <?= htmlspecialchars($_GET['q']) ?>
                                <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($_GET['type'])): ?>
                                <?php foreach ($_GET['type'] as $type): ?>
                                <a href="<?= $basePath ?>/groups?q=<?= urlencode($_GET['q'] ?? '') ?>" class="civicone-tag civicone-tag--removable">
                                    Type: <?= htmlspecialchars(ucfirst($type)) ?>
                                    <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="<?= $basePath ?>/groups" class="civicone-link">Clear all filters</a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Results Panel (2/3) -->
            <div class="civicone-grid-column-two-thirds">

                <!-- Results Header with Count -->
                <div class="civicone-results-header">
                    <p class="civicone-results-count" id="results-count">
                        Showing <strong><?= count($groups) ?></strong> <?= count($groups) === 1 ? 'hub' : 'hubs' ?>
                    </p>
                </div>

                <!-- Results: List Layout (following WCAG 2.1 AA Directory/List Template) -->
                <?php if (empty($groups)): ?>
                    <div class="civicone-empty-state">
                        <svg class="civicone-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <h2 class="civicone-heading-m">No hubs found</h2>
                        <p class="civicone-body">Try adjusting your filters or check back later.</p>
                    </div>
                <?php else: ?>
                    <ul class="civicone-results-list" id="groups-list" role="list">
                        <?php foreach ($groups as $group): ?>
                            <?php
                            $gName = htmlspecialchars($group['name']);
                            $gDesc = htmlspecialchars(substr($group['description'] ?? 'No description available.', 0, 150));
                            if (strlen($group['description'] ?? '') > 150) {
                                $gDesc .= '...';
                            }
                            $hasImg = !empty($group['image_path']);
                            $memberCount = $group['member_count'] ?? 0;
                            ?>
                            <li class="civicone-group-item">
                                <!-- Group Image/Avatar -->
                                <div class="civicone-group-item__avatar">
                                    <?php if ($hasImg): ?>
                                        <img src="<?= htmlspecialchars($group['image_path']) ?>" alt="" class="civicone-avatar civicone-avatar--large">
                                    <?php else: ?>
                                        <div class="civicone-avatar civicone-avatar--large civicone-avatar--placeholder">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Group Content -->
                                <div class="civicone-group-item__content">
                                    <h3 class="civicone-group-item__name">
                                        <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="civicone-link">
                                            <?= $gName ?>
                                        </a>
                                    </h3>

                                    <p class="civicone-group-item__description"><?= $gDesc ?></p>

                                    <?php if ($memberCount > 0): ?>
                                    <p class="civicone-group-item__meta">
                                        <svg class="civicone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                        <?= $memberCount ?> <?= $memberCount === 1 ? 'member' : 'members' ?>
                                    </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Group Actions -->
                                <div class="civicone-group-item__actions">
                                    <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>"
                                       class="civicone-button civicone-button--secondary"
                                       aria-label="Visit <?= $gName ?> hub">
                                        Visit Hub
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <nav class="civicone-pagination" aria-label="Hub list pagination">
                        <?php
                        $current = $pagination['current_page'];
                        $total = $pagination['total_pages'];
                        $base = $pagination['base_path'];
                        $range = 2;
                        $query = !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '';
                        if (!empty($_GET['type'])) {
                            foreach ($_GET['type'] as $type) {
                                $query .= '&type[]=' . urlencode($type);
                            }
                        }
                        ?>

                        <div class="civicone-pagination__results">
                            Showing <?= (($current - 1) * 20 + 1) ?> to <?= min($current * 20, $total_groups ?? count($groups)) ?> of <?= $total_groups ?? count($groups) ?> hubs
                        </div>

                        <ul class="civicone-pagination__list">
                            <?php if ($current > 1): ?>
                                <li class="civicone-pagination__item civicone-pagination__item--prev">
                                    <a href="<?= $base ?>?page=<?= $current - 1 ?><?= $query ?>" class="civicone-pagination__link" aria-label="Go to previous page">
                                        <span aria-hidden="true">‹</span> Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total; $i++): ?>
                                <?php if ($i == 1 || $i == $total || ($i >= $current - $range && $i <= $current + $range)): ?>
                                    <li class="civicone-pagination__item">
                                        <?php if ($i == $current): ?>
                                            <span class="civicone-pagination__link civicone-pagination__link--current" aria-current="page">
                                                <?= $i ?>
                                            </span>
                                        <?php else: ?>
                                            <a href="<?= $base ?>?page=<?= $i ?><?= $query ?>" class="civicone-pagination__link" aria-label="Go to page <?= $i ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php elseif ($i == $current - $range - 1 || $i == $current + $range + 1): ?>
                                    <li class="civicone-pagination__item civicone-pagination__item--ellipsis" aria-hidden="true">
                                        <span>⋯</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($current < $total): ?>
                                <li class="civicone-pagination__item civicone-pagination__item--next">
                                    <a href="<?= $base ?>?page=<?= $current + 1 ?><?= $query ?>" class="civicone-pagination__link" aria-label="Go to next page">
                                        Next <span aria-hidden="true">›</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div><!-- /two-thirds -->
        </div><!-- /grid-row -->

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
