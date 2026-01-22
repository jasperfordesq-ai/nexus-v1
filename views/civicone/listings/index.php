<?php
// CivicOne Listings Index - WCAG 2.1 AA Compliant
// GOV.UK Directory/List Template (Section 10.2)
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- GOV.UK Page Template Boilerplate -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Breadcrumbs (GOV.UK Template A requirement) -->
        <nav class="civicone-breadcrumbs" aria-label="Breadcrumb">
            <ol class="civicone-breadcrumbs__list">
                <li class="civicone-breadcrumbs__list-item">
                    <a class="civicone-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
                </li>
                <li class="civicone-breadcrumbs__list-item" aria-current="page">
                    Listings
                </li>
            </ol>
        </nav>

        <!-- Hero (auto-resolves from config/heroes.php for /listings route) -->
        <?php require __DIR__ . '/../../layouts/civicone/partials/render-hero.php'; ?>

        <!-- Action Button -->
        <div class="civicone-grid-row civicone-action-row">
            <div class="civicone-grid-column-one-third">
                <a href="<?= $basePath ?>/listings/create" class="civicone-button civicone-button--primary civicone-button--full-width">
                    Post an Ad
                </a>
            </div>
        </div>

        <!-- Directory Layout: 1/3 Filters + 2/3 Results -->
        <div class="civicone-grid-row">

            <!-- Filters Panel (1/3) -->
            <div class="civicone-grid-column-one-third">
                <div class="civicone-filter-panel" role="search" aria-label="Filter listings">

                    <div class="civicone-filter-header">
                        <h2 class="civicone-heading-m">Filter listings</h2>
                    </div>

                    <form method="get" action="<?= $basePath ?>/listings">
                        <div class="civicone-filter-group">
                            <label for="listing-search" class="civicone-label">
                                Search by title or description
                            </label>
                            <div class="civicone-search-wrapper">
                                <input
                                    type="text"
                                    id="listing-search"
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
                                <legend class="civicone-label">Type</legend>
                                <div class="civicone-checkboxes">
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="type-offer" name="type[]" value="offer" class="civicone-checkbox" <?= in_array('offer', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                        <label for="type-offer" class="civicone-checkbox-label">Offers</label>
                                    </div>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="type-request" name="type[]" value="request" class="civicone-checkbox" <?= in_array('request', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                        <label for="type-request" class="civicone-checkbox-label">Requests</label>
                                    </div>
                                </div>
                            </fieldset>
                        </div>

                        <?php if (!empty($categories)): ?>
                        <div class="civicone-filter-group">
                            <fieldset class="civicone-fieldset">
                                <legend class="civicone-label">Category</legend>
                                <div class="civicone-checkboxes">
                                    <?php foreach ($categories as $category): ?>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox"
                                               id="category-<?= htmlspecialchars($category['slug'] ?? $category['id']) ?>"
                                               name="category[]"
                                               value="<?= htmlspecialchars($category['slug'] ?? $category['id']) ?>"
                                               class="civicone-checkbox"
                                               <?= in_array($category['slug'] ?? $category['id'], $_GET['category'] ?? []) ? 'checked' : '' ?>>
                                        <label for="category-<?= htmlspecialchars($category['slug'] ?? $category['id']) ?>" class="civicone-checkbox-label">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="civicone-button civicone-button--secondary civicone-button--full-width">
                            Apply filters
                        </button>
                    </form>

                    <!-- Selected Filters (shown when filters are active) -->
                    <?php
                    $hasFilters = !empty($_GET['q']) || !empty($_GET['type']) || !empty($_GET['category']);
                    if ($hasFilters):
                    ?>
                    <div class="civicone-selected-filters">
                        <h3 class="civicone-heading-s">Active filters</h3>
                        <div class="civicone-filter-tags">
                            <?php if (!empty($_GET['q'])): ?>
                            <a href="<?= $basePath ?>/listings" class="civicone-tag civicone-tag--removable">
                                Search: <?= htmlspecialchars($_GET['q']) ?>
                                <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($_GET['type'])): ?>
                                <?php foreach ($_GET['type'] as $type): ?>
                                <a href="<?= $basePath ?>/listings?q=<?= urlencode($_GET['q'] ?? '') ?>" class="civicone-tag civicone-tag--removable">
                                    Type: <?= htmlspecialchars(ucfirst($type)) ?>
                                    <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($_GET['category'])): ?>
                                <?php foreach ($_GET['category'] as $cat): ?>
                                <a href="<?= $basePath ?>/listings?q=<?= urlencode($_GET['q'] ?? '') ?>" class="civicone-tag civicone-tag--removable">
                                    Category: <?= htmlspecialchars(ucfirst($cat)) ?>
                                    <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="<?= $basePath ?>/listings" class="civicone-link">Clear all filters</a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Results Panel (2/3) -->
            <div class="civicone-grid-column-two-thirds">

                <!-- Results Header with Count -->
                <div class="civicone-results-header">
                    <p class="civicone-results-count" id="results-count">
                        Showing <strong><?= count($listings ?? []) ?></strong> <?= count($listings ?? []) === 1 ? 'listing' : 'listings' ?>
                    </p>
                </div>

                <!-- Results: LIST LAYOUT (structured result rows) -->
                <?php if (empty($listings)): ?>
                    <div class="civicone-empty-state">
                        <svg class="civicone-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                        <h2 class="civicone-heading-m">No listings found</h2>
                        <p class="civicone-body">
                            <?php if (!empty($_GET['q'])): ?>
                                No listings match your search. Try different keywords or check back later.
                            <?php else: ?>
                                There are no active listings at the moment. Be the first to post!
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <ul class="civicone-listings-list" role="list">
                        <?php foreach ($listings as $listing): ?>
                            <?php
                            $listingTitle = htmlspecialchars($listing['title']);
                            $listingDesc = htmlspecialchars(substr($listing['description'] ?? 'No description available.', 0, 180));
                            if (strlen($listing['description'] ?? '') > 180) {
                                $listingDesc .= '...';
                            }
                            $listingType = htmlspecialchars($listing['type'] ?? 'listing');
                            $isOffer = $listingType === 'offer';
                            $authorName = htmlspecialchars($listing['author_name'] ?? 'User');
                            $postedDate = !empty($listing['created_at']) ? date('M j, Y', strtotime($listing['created_at'])) : '';
                            ?>
                            <li class="civicone-listing-item" role="listitem">
                                <!-- Type Badge + Posted Date -->
                                <div class="civicone-listing-item__meta-header">
                                    <span class="civicone-listing-item__type civicone-listing-item__type--<?= $isOffer ? 'offer' : 'request' ?>">
                                        <?= ucfirst($listingType) ?>
                                    </span>
                                    <?php if ($postedDate): ?>
                                    <span class="civicone-listing-item__posted">Posted <?= $postedDate ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Title (Main Link) -->
                                <h3 class="civicone-listing-item__title">
                                    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="civicone-link">
                                        <?= $listingTitle ?>
                                    </a>
                                </h3>

                                <!-- Description Excerpt -->
                                <p class="civicone-listing-item__description"><?= $listingDesc ?></p>

                                <!-- Metadata Footer (Author) -->
                                <div class="civicone-listing-item__footer">
                                    <span class="civicone-listing-item__author">
                                        By <strong><?= $authorName ?></strong>
                                    </span>
                                </div>

                                <!-- Action Link -->
                                <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>"
                                   class="civicone-button civicone-button--secondary civicone-listing-item__action"
                                   aria-label="View details for <?= $listingTitle ?>">
                                    View Details
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <nav class="civicone-pagination" aria-label="Listings pagination">
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
                        if (!empty($_GET['category'])) {
                            foreach ($_GET['category'] as $cat) {
                                $query .= '&category[]=' . urlencode($cat);
                            }
                        }
                        ?>

                        <div class="civicone-pagination__results">
                            Showing <?= (($current - 1) * 20 + 1) ?> to <?= min($current * 20, $total_listings ?? count($listings ?? [])) ?> of <?= $total_listings ?? count($listings ?? []) ?> listings
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
</div><!-- /width-container -->

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
