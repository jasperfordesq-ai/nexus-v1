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
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Listings']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

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
        <?php
        $filterAction = $basePath . '/listings';
        require __DIR__ . '/../partials/listings-filter.php';
        ?>
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
                    <li class="govuk-!-margin-bottom-4 govuk-!-padding-bottom-4 civicone-listing-item">
                        <!-- Type Badge & Date -->
                        <p class="govuk-body-s govuk-!-margin-bottom-1">
                            <strong class="govuk-tag <?= $isOffer ? 'govuk-tag--green' : 'govuk-tag--grey' ?>"><?= ucfirst($listingType) ?></strong>
                            <?php if ($postedDate): ?>
                                <span class="civicone-secondary-text">&middot; Posted <?= $postedDate ?></span>
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
                        <p class="govuk-body-s govuk-!-margin-bottom-2 civicone-secondary-text">
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
