<?php
/**
 * CivicOne Search Results - Universal Search Interface
 * Template A: Directory/List Page (Section 10.2)
 * GOV.UK Design System v5.14.0 - WCAG 2.1 AA Compliant
 */

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$totalResults = count($results ?? []);
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
                    Search Results
                </li>
            </ol>
        </nav>

        <!-- Page Header (GOV.UK Typography) -->
        <h1 class="govuk-heading-xl">Search Results</h1>
        <?php if (!empty($corrected_query) && $corrected_query !== $query): ?>
            <p class="govuk-body-l">
                Showing results for "<strong><?= htmlspecialchars($corrected_query) ?></strong>"
                <span class="govuk-body-s govuk-!-margin-left-1">(corrected from "<?= htmlspecialchars($query) ?>")</span>
            </p>
        <?php else: ?>
            <p class="govuk-body-l">
                Found <strong><?= $totalResults ?></strong> <?= $totalResults === 1 ? 'result' : 'results' ?> for "<strong><?= htmlspecialchars($query) ?></strong>"
            </p>
        <?php endif; ?>

        <?php if (!empty($intent) && !empty($intent['ai_analyzed'])): ?>
            <p class="govuk-body-s">
                <strong class="govuk-tag govuk-tag--blue">AI-Enhanced Search</strong>
                <?php if (!empty($intent['location'])): ?>
                    <strong class="govuk-tag govuk-tag--grey"><?= htmlspecialchars($intent['location']) ?></strong>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <!-- Directory Layout: 1/3 Filters + 2/3 Results -->
        <div class="civicone-grid-row">

            <!-- Filters Panel (1/3) -->
            <div class="civicone-grid-column-one-third">
                <div class="civicone-filter-panel" role="search" aria-label="Filter search results">

                    <div class="civicone-filter-header">
                        <h2 class="civicone-heading-m">Filter results</h2>
                    </div>

                    <!-- Search Input -->
                    <form method="get" action="<?= $basePath ?>/search">
                        <div class="civicone-filter-group">
                            <label for="search-query" class="civicone-label">
                                Search term
                            </label>
                            <div class="civicone-search-wrapper">
                                <input
                                    type="text"
                                    id="search-query"
                                    name="q"
                                    class="civicone-input civicone-search-input"
                                    placeholder="Enter keywords..."
                                    value="<?= htmlspecialchars($query) ?>"
                                >
                                <span class="civicone-search-icon" aria-hidden="true"></span>
                            </div>
                        </div>

                        <button type="submit" class="civicone-button civicone-button--secondary civicone-button--full-width">
                            Search
                        </button>
                    </form>

                    <!-- Type Filter Tabs (Client-side filtering) -->
                    <?php if (!empty($results)): ?>
                        <div class="civicone-filter-group">
                            <fieldset class="civicone-fieldset">
                                <legend class="civicone-label">Result type</legend>
                                <div class="civicone-search-tabs" role="tablist" aria-label="Filter by result type">
                                    <button class="civicone-search-tab active"
                                            data-filter="all"
                                            role="tab"
                                            aria-selected="true"
                                            aria-controls="search-results-list">
                                        <span class="civicone-search-tab__text">All</span>
                                        <span class="civicone-search-tab__count" data-count-type="all"><?= $totalResults ?></span>
                                    </button>
                                    <button class="civicone-search-tab"
                                            data-filter="user"
                                            role="tab"
                                            aria-selected="false"
                                            aria-controls="search-results-list">
                                        <span class="civicone-search-tab__text">People</span>
                                        <span class="civicone-search-tab__count" data-count-type="user">
                                            <?= count(array_filter($results, fn($r) => $r['type'] === 'user')) ?>
                                        </span>
                                    </button>
                                    <button class="civicone-search-tab"
                                            data-filter="group"
                                            role="tab"
                                            aria-selected="false"
                                            aria-controls="search-results-list">
                                        <span class="civicone-search-tab__text">Hubs</span>
                                        <span class="civicone-search-tab__count" data-count-type="group">
                                            <?= count(array_filter($results, fn($r) => $r['type'] === 'group')) ?>
                                        </span>
                                    </button>
                                    <button class="civicone-search-tab"
                                            data-filter="listing"
                                            role="tab"
                                            aria-selected="false"
                                            aria-controls="search-results-list">
                                        <span class="civicone-search-tab__text">Listings</span>
                                        <span class="civicone-search-tab__count" data-count-type="listing">
                                            <?= count(array_filter($results, fn($r) => $r['type'] === 'listing')) ?>
                                        </span>
                                    </button>
                                    <?php if (!empty(array_filter($results, fn($r) => $r['type'] === 'page'))): ?>
                                    <button class="civicone-search-tab"
                                            data-filter="page"
                                            role="tab"
                                            aria-selected="false"
                                            aria-controls="search-results-list">
                                        <span class="civicone-search-tab__text">Pages</span>
                                        <span class="civicone-search-tab__count" data-count-type="page">
                                            <?= count(array_filter($results, fn($r) => $r['type'] === 'page')) ?>
                                        </span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </fieldset>
                        </div>

                        <!-- Sort Options -->
                        <div class="civicone-filter-group">
                            <label for="sort-by" class="civicone-label">Sort by</label>
                            <select id="sort-by" name="sort" class="civicone-select">
                                <option value="relevance" selected>Relevance</option>
                                <option value="recent">Most recent</option>
                                <option value="name">Name (A-Z)</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="civicone-filter-panel civicone-secondary-panel">
                        <h3 class="civicone-heading-s">Quick actions</h3>
                        <p class="civicone-body-s civicone-secondary-panel-description">Browse content by category</p>
                        <a href="<?= $basePath ?>/members" class="civicone-button civicone-button--secondary civicone-button--full-width">
                            Browse People
                        </a>
                        <a href="<?= $basePath ?>/groups" class="civicone-button civicone-button--secondary civicone-button--full-width">
                            Browse Hubs
                        </a>
                        <a href="<?= $basePath ?>/listings" class="civicone-button civicone-button--secondary civicone-button--full-width">
                            Browse Listings
                        </a>
                    </div>

                </div>
            </div>

            <!-- Results Panel (2/3) -->
            <div class="civicone-grid-column-two-thirds">

                <!-- Results Header with Count -->
                <div class="civicone-results-header">
                    <p class="civicone-results-count" id="results-count" role="status" aria-live="polite">
                        Showing <strong id="visible-count"><?= $totalResults ?></strong> <?= $totalResults === 1 ? 'result' : 'results' ?>
                    </p>
                </div>

                <!-- Empty State -->
                <?php if (empty($results)): ?>
                    <div class="civicone-empty-state">
                        <svg class="civicone-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <h2 class="civicone-heading-m">No results found</h2>
                        <p class="civicone-body">
                            We couldn't find anything matching "<strong><?= htmlspecialchars($query) ?></strong>".
                        </p>
                        <p class="civicone-body">
                            Try different keywords or browse content by category:
                        </p>
                        <div class="civicone-empty-state-actions">
                            <a href="<?= $basePath ?>/members" class="civicone-button civicone-button--primary">Browse People</a>
                            <a href="<?= $basePath ?>/groups" class="civicone-button civicone-button--secondary">Browse Hubs</a>
                            <a href="<?= $basePath ?>/listings" class="civicone-button civicone-button--secondary">Browse Listings</a>
                        </div>
                    </div>
                <?php else: ?>

                    <!-- Filter Empty State (Hidden by default) -->
                    <div id="no-filter-results" class="civicone-empty-state hidden" role="status" aria-live="polite">
                        <svg class="civicone-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"></path>
                        </svg>
                        <h2 class="civicone-heading-m">No results in this category</h2>
                        <p class="civicone-body" id="filter-empty-message">
                            No results found in this category. Try selecting a different filter.
                        </p>
                    </div>

                    <!-- Results: LIST LAYOUT (structured result rows) -->
                    <ul class="civicone-search-results-list" id="search-results-list" role="list">
                        <?php foreach ($results as $item): ?>
                            <?php
                            // Calculate URLs and metadata based on type
                            $url = '#';
                            $badgeColor = 'gray';
                            $typeLabel = 'ITEM';
                            $iconType = 'default';

                            switch ($item['type']) {
                                case 'user':
                                    $badgeColor = 'blue';
                                    $typeLabel = 'PERSON';
                                    $iconType = 'user';
                                    $url = $basePath . '/profile/' . $item['id'];
                                    break;
                                case 'listing':
                                    $badgeColor = 'green';
                                    $typeLabel = 'LISTING';
                                    $iconType = 'listing';
                                    $url = $basePath . '/listings/' . $item['id'];
                                    break;
                                case 'group':
                                    $badgeColor = 'purple';
                                    $typeLabel = 'HUB';
                                    $iconType = 'group';
                                    $url = $basePath . '/groups/' . $item['id'];
                                    break;
                                case 'page':
                                    $badgeColor = 'orange';
                                    $typeLabel = 'PAGE';
                                    $iconType = 'page';
                                    $url = $basePath . '/pages/' . $item['id'];
                                    break;
                            }

                            $itemTitle = htmlspecialchars($item['title'] ?? 'Untitled');
                            $itemDesc = '';
                            if (!empty($item['description'])) {
                                $itemDesc = htmlspecialchars(strip_tags($item['description']));
                                if (strlen($itemDesc) > 180) {
                                    $itemDesc = substr($itemDesc, 0, 180) . '...';
                                }
                            }
                            $hasImage = !empty($item['image']);
                            $isHighMatch = isset($item['relevance_score']) && $item['relevance_score'] > 0.7;
                            $hasLocation = !empty($item['location']);
                            ?>

                            <li class="civicone-search-result-item" data-type="<?= htmlspecialchars($item['type']) ?>" role="listitem">
                                <!-- Icon/Image -->
                                <div class="civicone-search-result-item__icon">
                                    <?php if ($hasImage): ?>
                                        <img src="<?= htmlspecialchars($item['image']) ?>"
                                             alt=""
                                             loading="lazy"
                                             class="civicone-search-result-item__image">
                                    <?php else: ?>
                                        <div class="civicone-search-result-item__icon-placeholder" aria-hidden="true">
                                            <?php if ($iconType === 'user'): ?>
                                                <svg class="civicone-icon-large" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                    <circle cx="12" cy="7" r="4"></circle>
                                                </svg>
                                            <?php elseif ($iconType === 'group'): ?>
                                                <svg class="civicone-icon-large" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                    <circle cx="9" cy="7" r="4"></circle>
                                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                                </svg>
                                            <?php elseif ($iconType === 'listing'): ?>
                                                <svg class="civicone-icon-large" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <line x1="8" y1="6" x2="21" y2="6"></line>
                                                    <line x1="8" y1="12" x2="21" y2="12"></line>
                                                    <line x1="8" y1="18" x2="21" y2="18"></line>
                                                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                                </svg>
                                            <?php else: ?>
                                                <svg class="civicone-icon-large" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                    <polyline points="14 2 14 8 20 8"></polyline>
                                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                                    <polyline points="10 9 9 9 8 9"></polyline>
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Content -->
                                <div class="civicone-search-result-item__content">
                                    <!-- Meta badges -->
                                    <div class="civicone-search-result-item__meta">
                                        <span class="civicone-badge civicone-badge--<?= $badgeColor ?>">
                                            <?= $typeLabel ?>
                                        </span>

                                        <?php if ($isHighMatch): ?>
                                            <span class="civicone-badge civicone-badge--high-match">
                                                <svg class="civicone-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                                </svg>
                                                <span class="govuk-visually-hidden">High relevance: </span>Top Match
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($hasLocation): ?>
                                            <span class="civicone-badge civicone-badge--muted">
                                                <svg class="civicone-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                    <circle cx="12" cy="10" r="3"></circle>
                                                </svg>
                                                <?= htmlspecialchars($item['location']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Title (Main Link) -->
                                    <h3 class="civicone-search-result-item__title">
                                        <a href="<?= $url ?>" class="civicone-link">
                                            <?= $itemTitle ?>
                                        </a>
                                    </h3>

                                    <!-- Description -->
                                    <?php if ($itemDesc): ?>
                                        <p class="civicone-search-result-item__description">
                                            <?= $itemDesc ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Button -->
                                <div class="civicone-search-result-item__action">
                                    <a href="<?= $url ?>"
                                       class="civicone-button civicone-button--secondary"
                                       aria-label="View <?= strtolower($typeLabel) ?>: <?= $itemTitle ?>">
                                        View
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Pagination -->
                    <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                        <nav class="civicone-pagination" aria-label="Search results pagination">
                            <?php
                            $current = $pagination['current_page'];
                            $total = $pagination['total_pages'];
                            $base = $basePath . '/search';
                            $range = 2;
                            $queryParam = '&q=' . urlencode($query);
                            ?>

                            <div class="civicone-pagination__results">
                                Showing <?= (($current - 1) * 20 + 1) ?> to <?= min($current * 20, $total_results ?? $totalResults) ?> of <?= $total_results ?? $totalResults ?> results
                            </div>

                            <ul class="civicone-pagination__list">
                                <?php if ($current > 1): ?>
                                    <li class="civicone-pagination__item civicone-pagination__item--prev">
                                        <a href="<?= $base ?>?page=<?= $current - 1 ?><?= $queryParam ?>" class="civicone-pagination__link" aria-label="Go to previous page">
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
                                                <a href="<?= $base ?>?page=<?= $i ?><?= $queryParam ?>" class="civicone-pagination__link" aria-label="Go to page <?= $i ?>">
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
                                        <a href="<?= $base ?>?page=<?= $current + 1 ?><?= $queryParam ?>" class="civicone-pagination__link" aria-label="Go to next page">
                                            Next <span aria-hidden="true">›</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                <?php endif; ?>

            </div><!-- /two-thirds -->
        </div><!-- /grid-row -->

    </main>
</div><!-- /civicone-width-container -->

<script src="/assets/js/civicone-search-results.js?v=<?= time() ?>"></script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
