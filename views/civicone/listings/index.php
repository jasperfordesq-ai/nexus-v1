<?php
/**
 * CivicOne Listings Index - GOV.UK Frontend v5.14.0 Compliant
 * Template A: Directory/List Page (Section 10.2)
 *
 * v2.0.0 GOV.UK Polish Refactor (2026-01-24):
 * - Uses official GOV.UK Frontend v5.14.0 classes
 * - Proper govuk-grid-row/column layout
 * - GOV.UK form components (checkboxes, inputs)
 * - Mobile-first responsive design
 *
 * GOV.UK Compliance: Full (v5.14.0)
 */

// CivicOne layout header (provides govuk-width-container and govuk-main-wrapper)
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- GOV.UK Breadcrumbs -->
<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Listings</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">Listings</h1>
        <p class="govuk-body-l">Browse offers and requests from community members.</p>
    </div>
    <div class="govuk-grid-column-one-third">
        <a href="<?= $basePath ?>/listings/create" class="govuk-button govuk-!-margin-bottom-0">
            Post an Ad
        </a>
    </div>
</div>

<!-- Directory Layout: 1/3 Filters + 2/3 Results -->
<div class="govuk-grid-row">

    <!-- Filters Panel (1/3) -->
    <div class="govuk-grid-column-one-third">
        <div class="civicone-panel-bg">
            <h2 class="govuk-heading-m">Filter listings</h2>

            <form method="get" action="<?= $basePath ?>/listings">
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
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                    >
                </div>

                <!-- Type Checkboxes -->
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend">Type</legend>
                        <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="type-offer" name="type[]" value="offer" class="govuk-checkboxes__input" <?= in_array('offer', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="type-offer">Offers</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="type-request" name="type[]" value="request" class="govuk-checkboxes__input" <?= in_array('request', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="type-request">Requests</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <?php if (!empty($categories)): ?>
                <!-- Category Checkboxes -->
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend">Category</legend>
                        <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                            <?php foreach ($categories as $category): ?>
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox"
                                       id="category-<?= htmlspecialchars($category['slug'] ?? $category['id']) ?>"
                                       name="category[]"
                                       value="<?= htmlspecialchars($category['slug'] ?? $category['id']) ?>"
                                       class="govuk-checkboxes__input"
                                       <?= in_array($category['slug'] ?? $category['id'], $_GET['category'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="category-<?= htmlspecialchars($category['slug'] ?? $category['id']) ?>">
                                    <?= htmlspecialchars($category['name']) ?>
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

            <!-- Selected Filters -->
            <?php
            $hasFilters = !empty($_GET['q']) || !empty($_GET['type']) || !empty($_GET['category']);
            if ($hasFilters):
            ?>
            <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">
            <h3 class="govuk-heading-s">Active filters</h3>
            <p class="govuk-body">
                <?php if (!empty($_GET['q'])): ?>
                    <strong class="govuk-tag">Search: <?= htmlspecialchars($_GET['q']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($_GET['type'])): ?>
                    <?php foreach ($_GET['type'] as $type): ?>
                        <strong class="govuk-tag govuk-tag--grey">Type: <?= htmlspecialchars(ucfirst($type)) ?></strong>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($_GET['category'])): ?>
                    <?php foreach ($_GET['category'] as $cat): ?>
                        <strong class="govuk-tag govuk-tag--grey">Category: <?= htmlspecialchars(ucfirst($cat)) ?></strong>
                    <?php endforeach; ?>
                <?php endif; ?>
            </p>
            <a href="<?= $basePath ?>/listings" class="govuk-link">Clear all filters</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results Panel (2/3) -->
    <div class="govuk-grid-column-two-thirds">

        <!-- Results Count -->
        <p class="govuk-body govuk-!-margin-bottom-4">
            Showing <strong><?= count($listings ?? []) ?></strong> <?= count($listings ?? []) === 1 ? 'listing' : 'listings' ?>
        </p>

        <!-- Results List -->
        <?php if (empty($listings)): ?>
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-m">No listings found</h2>
                <p class="govuk-body">
                    <?php if (!empty($_GET['q'])): ?>
                        No listings match your search. Try different keywords or check back later.
                    <?php else: ?>
                        There are no active listings at the moment. Be the first to post!
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <ul class="govuk-list" role="list">
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
                    $postedDate = !empty($listing['created_at']) ? date('j M Y', strtotime($listing['created_at'])) : '';
                    ?>
                    <li class="govuk-!-margin-bottom-4 govuk-!-padding-bottom-4" style="border-bottom: 1px solid #b1b4b6;">
                        <!-- Type Badge & Date -->
                        <p class="govuk-body-s govuk-!-margin-bottom-1">
                            <strong class="govuk-tag <?= $isOffer ? 'govuk-tag--green' : 'govuk-tag--blue' ?>"><?= ucfirst($listingType) ?></strong>
                            <?php if ($postedDate): ?>
                                <span style="color: #505a5f;">&middot; Posted <?= $postedDate ?></span>
                            <?php endif; ?>
                        </p>

                        <!-- Title -->
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                            <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="govuk-link">
                                <?= $listingTitle ?>
                            </a>
                        </h3>

                        <!-- Description -->
                        <p class="govuk-body govuk-!-margin-bottom-2"><?= $listingDesc ?></p>

                        <!-- Author -->
                        <p class="govuk-body-s govuk-!-margin-bottom-2" style="color: #505a5f;">
                            By <strong><?= $authorName ?></strong>
                        </p>

                        <!-- Action Link -->
                        <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>"
                           class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0"
                           aria-label="View details for <?= $listingTitle ?>">
                            View Details
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- GOV.UK Pagination -->
        <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
            <?php
            $current = $pagination['current_page'];
            $total = $pagination['total_pages'];
            $base = $pagination['base_path'];
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
            <nav class="govuk-pagination" role="navigation" aria-label="Pagination">
                <?php if ($current > 1): ?>
                <div class="govuk-pagination__prev">
                    <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $current - 1 ?><?= $query ?>" rel="prev">
                        <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                        </svg>
                        <span class="govuk-pagination__link-title">Previous<span class="govuk-visually-hidden"> page</span></span>
                    </a>
                </div>
                <?php endif; ?>

                <ul class="govuk-pagination__list">
                    <?php for ($i = 1; $i <= $total; $i++): ?>
                        <?php if ($i == 1 || $i == $total || ($i >= $current - 1 && $i <= $current + 1)): ?>
                            <li class="govuk-pagination__item<?= $i == $current ? ' govuk-pagination__item--current' : '' ?>">
                                <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $i ?><?= $query ?>" aria-label="Page <?= $i ?>"<?= $i == $current ? ' aria-current="page"' : '' ?>>
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php elseif ($i == $current - 2 || $i == $current + 2): ?>
                            <li class="govuk-pagination__item govuk-pagination__item--ellipses">&ctdot;</li>
                        <?php endif; ?>
                    <?php endfor; ?>
                </ul>

                <?php if ($current < $total): ?>
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $current + 1 ?><?= $query ?>" rel="next">
                        <span class="govuk-pagination__link-title">Next<span class="govuk-visually-hidden"> page</span></span>
                        <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                        </svg>
                    </a>
                </div>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    </div><!-- /.govuk-grid-column-two-thirds -->
</div><!-- /.govuk-grid-row -->

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
