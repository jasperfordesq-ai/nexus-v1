<?php
/**
 * Federation Listings Directory
 * CivicOne Theme - WCAG 2.1 AA Compliant
 * Template: Directory/List (MOJ Filter a list pattern)
 */
$pageTitle = $pageTitle ?? "Federated Listings";
$pageSubtitle = "Browse offers and requests from partner timebanks";
$hideHero = true;
$bodyClass = 'civicone--federation';
$currentPage = 'listings';

\Nexus\Core\SEO::setTitle('Federated Listings - Partner Timebank Services');
\Nexus\Core\SEO::setDescription('Browse offers and requests from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

$listings = $listings ?? [];
$partnerTenants = $partnerTenants ?? [];
$categories = $categories ?? [];
$filters = $filters ?? [];
$partnerCommunities = $partnerCommunities ?? $partnerTenants;
$currentScope = $currentScope ?? 'all';

// Build active filters array for tags
$activeFilters = [];
if (!empty($filters['tenant_id'])) {
    $tenantName = '';
    foreach ($partnerTenants as $t) {
        if ($t['id'] == $filters['tenant_id']) {
            $tenantName = $t['name'];
            break;
        }
    }
    $activeFilters[] = [
        'label' => 'Community: ' . $tenantName,
        'remove_url' => $basePath . '/federation/listings?' . http_build_query(array_diff_key($filters, ['tenant_id' => '']))
    ];
}
if (!empty($filters['type'])) {
    $typeLabel = $filters['type'] === 'offer' ? 'Offers' : 'Requests';
    $activeFilters[] = [
        'label' => 'Type: ' . $typeLabel,
        'remove_url' => $basePath . '/federation/listings?' . http_build_query(array_diff_key($filters, ['type' => '']))
    ];
}
if (!empty($filters['category'])) {
    $categoryName = '';
    foreach ($categories as $cat) {
        if ($cat['id'] == $filters['category']) {
            $categoryName = $cat['name'];
            break;
        }
    }
    $activeFilters[] = [
        'label' => 'Category: ' . $categoryName,
        'remove_url' => $basePath . '/federation/listings?' . http_build_query(array_diff_key($filters, ['category' => '']))
    ];
}
if (!empty($filters['search'])) {
    $activeFilters[] = [
        'label' => 'Search: "' . htmlspecialchars($filters['search']) . '"',
        'remove_url' => $basePath . '/federation/listings?' . http_build_query(array_diff_key($filters, ['search' => '']))
    ];
}
?>

<!-- Federation Scope Switcher (only if user has 2+ communities) -->
<?php if (count($partnerCommunities) >= 2): ?>
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-scope-switcher.php'; ?>
<?php endif; ?>

<!-- Federation Service Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-service-navigation.php'; ?>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Page Header -->
        <h1 class="govuk-heading-xl">Federated Listings</h1>

        <p class="govuk-body-l">
            Discover offers and requests from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
        </p>

        <!-- Selected Filters (MOJ Filter component) -->
        <?php if (!empty($activeFilters)): ?>
        <div class="moj-filter-tags">
            <h2 class="govuk-heading-s">Selected filters</h2>
            <div class="moj-filter-tags__wrapper">
                <?php foreach ($activeFilters as $filter): ?>
                <a href="<?= $filter['remove_url'] ?>" class="moj-filter-tag">
                    <?= htmlspecialchars($filter['label']) ?>
                    <span class="govuk-visually-hidden">Remove filter</span>
                </a>
                <?php endforeach; ?>
                <a href="<?= $basePath ?>/federation/listings" class="moj-filter-tag moj-filter-tag--clear-all">
                    Clear all filters
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Two-column layout: Filters (1/3) + Results (2/3) -->
        <div class="moj-filter-layout">

            <!-- Filter Panel (1/3 width) -->
            <div class="moj-filter-layout__filter">
                <form method="GET" action="<?= $basePath ?>/federation/listings">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                            Filter listings
                        </legend>

                        <!-- Search Input -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="search-input">
                                Search titles and descriptions
                            </label>
                            <input class="govuk-input" id="search-input" name="q" type="text"
                                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                                   placeholder="Enter keywords...">
                        </div>

                        <!-- Source Community Filter (MANDATORY) -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="tenant-filter">
                                Source community
                            </label>
                            <select id="tenant-filter" name="tenant" class="govuk-select">
                                <option value="">All partner communities</option>
                                <?php foreach ($partnerTenants as $tenant): ?>
                                    <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tenant['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Type Filter (Offer/Request) -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="type-filter">
                                Type
                            </label>
                            <select id="type-filter" name="type" class="govuk-select">
                                <option value="">All types</option>
                                <option value="offer" <?= ($filters['type'] ?? '') === 'offer' ? 'selected' : '' ?>>
                                    Offers
                                </option>
                                <option value="request" <?= ($filters['type'] ?? '') === 'request' ? 'selected' : '' ?>>
                                    Requests
                                </option>
                            </select>
                        </div>

                        <!-- Category Filter -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="category-filter">
                                Category
                            </label>
                            <select id="category-filter" name="category" class="govuk-select">
                                <option value="">All categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($filters['category'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Apply Filters Button -->
                        <button type="submit" class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">
                            Apply filters
                        </button>
                    </fieldset>
                </form>
            </div>

            <!-- Results Panel (2/3 width) -->
            <div class="moj-filter-layout__content" aria-live="polite" aria-busy="false">

                <!-- Results Count -->
                <div class="moj-action-bar">
                    <div class="moj-action-bar__filter">
                        <p class="govuk-body">
                            <strong><?= count($listings) ?></strong> listing<?= count($listings) !== 1 ? 's' : '' ?> found
                        </p>
                    </div>
                </div>

                <!-- Listings List -->
                <?php if (!empty($listings)): ?>
                    <ul class="govuk-list">
                        <?php foreach ($listings as $listing): ?>
                        <li class="govuk-!-margin-bottom-6">
                            <div class="govuk-summary-card">
                                <div class="govuk-summary-card__title-wrapper">
                                    <h3 class="govuk-summary-card__title">
                                        <a href="<?= $basePath ?>/federation/listings/<?= $listing['id'] ?>" class="govuk-link">
                                            <?= htmlspecialchars($listing['title'] ?? 'Untitled') ?>
                                        </a>
                                    </h3>
                                    <div class="civicone-federation-badges">
                                        <!-- Type Badge -->
                                        <span class="govuk-tag <?= ($listing['type'] ?? 'offer') === 'request' ? 'govuk-tag--blue' : '' ?>">
                                            <?= ucfirst($listing['type'] ?? 'offer') ?>
                                        </span>
                                        <!-- PROVENANCE LABEL (MANDATORY) -->
                                        <span class="govuk-tag govuk-tag--grey">
                                            Shared from <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner') ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="govuk-summary-card__content">
                                    <?php if (!empty($listing['description'])): ?>
                                    <p class="govuk-body-s">
                                        <?= htmlspecialchars(mb_substr($listing['description'], 0, 200)) ?><?= mb_strlen($listing['description']) > 200 ? '...' : '' ?>
                                    </p>
                                    <?php endif; ?>

                                    <dl class="govuk-summary-list govuk-summary-list--no-border">
                                        <?php if (!empty($listing['category_name'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Category</dt>
                                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($listing['category_name']) ?></dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($listing['owner_name'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Offered by</dt>
                                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($listing['owner_name']) ?></dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($listing['service_reach'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Service reach</dt>
                                            <dd class="govuk-summary-list__value">
                                                <?php if ($listing['service_reach'] === 'remote_ok'): ?>
                                                    Remote services available
                                                <?php elseif ($listing['service_reach'] === 'travel_ok'): ?>
                                                    Will travel for services
                                                <?php else: ?>
                                                    Local only
                                                <?php endif; ?>
                                            </dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($listing['created_at'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Posted</dt>
                                            <dd class="govuk-summary-list__value">
                                                <?= date('d M Y', strtotime($listing['created_at'])) ?>
                                            </dd>
                                        </div>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                                <div class="govuk-summary-card__actions">
                                    <a href="<?= $basePath ?>/federation/listings/<?= $listing['id'] ?>" class="govuk-link">
                                        View listing<span class="govuk-visually-hidden"> for <?= htmlspecialchars($listing['title'] ?? 'listing') ?></span>
                                    </a>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Pagination (GOV.UK Pattern - MANDATORY) -->
                    <?php
                    $currentPage = (int)(($_GET['page'] ?? 1));
                    $totalResults = count($listings); // In production, this would come from controller
                    $perPage = 30;
                    $totalPages = max(1, ceil($totalResults / $perPage));

                    // Build query string without page param
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                    $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                    ?>
                    <?php if ($totalPages > 1): ?>
                    <nav class="govuk-pagination" role="navigation" aria-label="Results navigation">
                        <span class="govuk-visually-hidden">Page <?= $currentPage ?> of <?= $totalPages ?></span>
                        <?php if ($currentPage > 1): ?>
                        <div class="govuk-pagination__prev">
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $currentPage - 1 ?><?= $queryString ?>" rel="prev">
                                <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                                </svg>
                                <span class="govuk-pagination__link-title">Previous</span>
                            </a>
                        </div>
                        <?php endif; ?>

                        <ul class="govuk-pagination__list">
                            <?php for ($i = 1; $i <= min(5, $totalPages); $i++): ?>
                            <li class="govuk-pagination__item <?= $i === $currentPage ? 'govuk-pagination__item--current' : '' ?>">
                                <?php if ($i === $currentPage): ?>
                                    <span class="govuk-pagination__link-label" aria-current="page"><?= $i ?></span>
                                <?php else: ?>
                                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $i ?><?= $queryString ?>" aria-label="Page <?= $i ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                            <?php endfor; ?>
                        </ul>

                        <?php if ($currentPage < $totalPages): ?>
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $currentPage + 1 ?><?= $queryString ?>" rel="next">
                                <span class="govuk-pagination__link-title">Next</span>
                                <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                                </svg>
                            </a>
                        </div>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="govuk-panel govuk-panel--bordered" role="status" aria-live="polite">
                        <p class="govuk-body">No listings found matching your filters.</p>
                        <p class="govuk-body">
                            Try adjusting your filters or <a href="<?= $basePath ?>/federation/listings" class="govuk-link">clear all filters</a> to see all listings.
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
