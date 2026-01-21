<?php
// CivicOne Volunteering Index - WCAG 2.1 AA Compliant
// GOV.UK Directory/List Template (Section 10.2)
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?php
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Volunteering']
];
require __DIR__ . '/../../layouts/civicone/partials/breadcrumb.php';
?>

<!-- GOV.UK Page Template Boilerplate -->
<div class="civicone-width-container civicone--govuk">
    <main class="civicone-main-wrapper" id="main-content" role="main">

        <!-- Hero (auto-resolves from config/heroes.php for /volunteering route) -->
        <?php require __DIR__ . '/../../layouts/civicone/partials/render-hero.php'; ?>

        <!-- Page Header (Note: Hero replaces manual H1, keeping for backward compatibility) -->
        <div class="civicone-grid-row civicone-hidden">
            <div class="civicone-grid-column-two-thirds">
                <h1 class="civicone-heading-xl">Volunteer Opportunities</h1>
                <p class="civicone-body-l">Connect with local organizations and make a difference in your community.</p>
            </div>
            <div class="civicone-grid-column-one-third">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= $basePath ?>/volunteering/my-applications" class="civicone-button civicone-button--secondary civicone-action-row civicone-button--full-width">
                        My Applications
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Directory Layout: 1/3 Filters + 2/3 Results -->
        <div class="civicone-grid-row">

            <!-- Filters Panel (1/3) -->
            <div class="civicone-grid-column-one-third">
                <div class="civicone-filter-panel" role="search" aria-label="Filter volunteer opportunities">

                    <div class="civicone-filter-header">
                        <h2 class="civicone-heading-m">Filter opportunities</h2>
                    </div>

                    <form method="get" action="<?= $basePath ?>/volunteering">
                        <div class="civicone-filter-group">
                            <label for="opportunity-search" class="civicone-label">
                                Search by role, skill, or location
                            </label>
                            <div class="civicone-search-wrapper">
                                <input
                                    type="text"
                                    id="opportunity-search"
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
                                <legend class="civicone-label">Location</legend>
                                <div class="civicone-checkboxes">
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="location-remote" name="location[]" value="remote" class="civicone-checkbox" <?= in_array('remote', $_GET['location'] ?? []) ? 'checked' : '' ?>>
                                        <label for="location-remote" class="civicone-checkbox-label">Remote</label>
                                    </div>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="location-local" name="location[]" value="local" class="civicone-checkbox" <?= in_array('local', $_GET['location'] ?? []) ? 'checked' : '' ?>>
                                        <label for="location-local" class="civicone-checkbox-label">In-person</label>
                                    </div>
                                </div>
                            </fieldset>
                        </div>

                        <div class="civicone-filter-group">
                            <fieldset class="civicone-fieldset">
                                <legend class="civicone-label">Time commitment</legend>
                                <div class="civicone-checkboxes">
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="commit-oneoff" name="commitment[]" value="oneoff" class="civicone-checkbox" <?= in_array('oneoff', $_GET['commitment'] ?? []) ? 'checked' : '' ?>>
                                        <label for="commit-oneoff" class="civicone-checkbox-label">One-off</label>
                                    </div>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="commit-regular" name="commitment[]" value="regular" class="civicone-checkbox" <?= in_array('regular', $_GET['commitment'] ?? []) ? 'checked' : '' ?>>
                                        <label for="commit-regular" class="civicone-checkbox-label">Regular</label>
                                    </div>
                                    <div class="civicone-checkbox-item">
                                        <input type="checkbox" id="commit-flexible" name="commitment[]" value="flexible" class="civicone-checkbox" <?= in_array('flexible', $_GET['commitment'] ?? []) ? 'checked' : '' ?>>
                                        <label for="commit-flexible" class="civicone-checkbox-label">Flexible</label>
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
                    $hasFilters = !empty($_GET['q']) || !empty($_GET['location']) || !empty($_GET['commitment']);
                    if ($hasFilters):
                    ?>
                    <div class="civicone-selected-filters">
                        <h3 class="civicone-heading-s">Active filters</h3>
                        <div class="civicone-filter-tags">
                            <?php if (!empty($_GET['q'])): ?>
                            <a href="<?= $basePath ?>/volunteering" class="civicone-tag civicone-tag--removable">
                                Search: <?= htmlspecialchars($_GET['q']) ?>
                                <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($_GET['location'])): ?>
                                <?php foreach ($_GET['location'] as $loc): ?>
                                <a href="<?= $basePath ?>/volunteering?q=<?= urlencode($_GET['q'] ?? '') ?>" class="civicone-tag civicone-tag--removable">
                                    Location: <?= htmlspecialchars(ucfirst($loc)) ?>
                                    <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($_GET['commitment'])): ?>
                                <?php foreach ($_GET['commitment'] as $commit): ?>
                                <a href="<?= $basePath ?>/volunteering?q=<?= urlencode($_GET['q'] ?? '') ?>" class="civicone-tag civicone-tag--removable">
                                    Commitment: <?= htmlspecialchars(ucfirst($commit)) ?>
                                    <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="<?= $basePath ?>/volunteering" class="civicone-link">Clear all filters</a>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- Secondary Actions -->
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="civicone-filter-panel civicone-secondary-panel">
                    <h3 class="civicone-heading-s">Organization dashboard</h3>
                    <p class="civicone-body-s civicone-secondary-panel-description">Manage your opportunities and applications</p>
                    <a href="<?= $basePath ?>/volunteering/dashboard" class="civicone-button civicone-button--secondary civicone-button--full-width">
                        View Dashboard
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Results Panel (2/3) -->
            <div class="civicone-grid-column-two-thirds">

                <!-- Results Header with Count -->
                <div class="civicone-results-header">
                    <p class="civicone-results-count" id="results-count">
                        Showing <strong><?= count($opportunities ?? []) ?></strong> <?= count($opportunities ?? []) === 1 ? 'opportunity' : 'opportunities' ?>
                    </p>
                </div>

                <!-- Results: LIST LAYOUT (structured result rows) -->
                <?php if (empty($opportunities)): ?>
                    <div class="civicone-empty-state">
                        <svg class="civicone-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <h2 class="civicone-heading-m">No opportunities found</h2>
                        <p class="civicone-body">
                            <?php if (!empty($_GET['q'])): ?>
                                No opportunities match your search. Try a different term or check back later.
                            <?php else: ?>
                                There are no volunteer opportunities available at the moment. Check back soon!
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <ul class="civicone-opportunities-list" role="list">
                        <?php foreach ($opportunities as $opp): ?>
                            <?php
                            $oppTitle = htmlspecialchars($opp['title']);
                            $oppDesc = htmlspecialchars(substr($opp['description'] ?? 'No description available.', 0, 180));
                            if (strlen($opp['description'] ?? '') > 180) {
                                $oppDesc .= '...';
                            }
                            $isRemote = empty($opp['location']);
                            $location = $isRemote ? 'Remote' : htmlspecialchars($opp['location']);
                            $commitment = htmlspecialchars($opp['commitment'] ?? 'Not specified');
                            $orgName = htmlspecialchars($opp['org_name'] ?? 'Organization');
                            $postedDate = !empty($opp['created_at']) ? date('M j, Y', strtotime($opp['created_at'])) : '';
                            ?>
                            <li class="civicone-opportunity-item" role="listitem">
                                <!-- Organization Name (Muted) -->
                                <div class="civicone-opportunity-item__meta-header">
                                    <span class="civicone-opportunity-item__org"><?= $orgName ?></span>
                                    <?php if ($postedDate): ?>
                                    <span class="civicone-opportunity-item__posted">Posted <?= $postedDate ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Title (Main Link) -->
                                <h3 class="civicone-opportunity-item__title">
                                    <a href="<?= $basePath ?>/volunteering/<?= $opp['id'] ?>" class="civicone-link">
                                        <?= $oppTitle ?>
                                    </a>
                                </h3>

                                <!-- Description Excerpt -->
                                <p class="civicone-opportunity-item__description"><?= $oppDesc ?></p>

                                <!-- Metadata Tags -->
                                <div class="civicone-opportunity-item__tags">
                                    <span class="civicone-tag <?= $isRemote ? 'civicone-tag--green' : '' ?>">
                                        <svg class="civicone-tag-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <?php if ($isRemote): ?>
                                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                            <line x1="8" y1="21" x2="16" y2="21"></line>
                                            <line x1="12" y1="17" x2="12" y2="21"></line>
                                            <?php else: ?>
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                            <?php endif; ?>
                                        </svg>
                                        <?= $location ?>
                                    </span>
                                    <span class="civicone-tag">
                                        <svg class="civicone-tag-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                        <?= $commitment ?>
                                    </span>
                                </div>

                                <!-- Action Link -->
                                <a href="<?= $basePath ?>/volunteering/<?= $opp['id'] ?>"
                                   class="civicone-button civicone-button--secondary civicone-opportunity-item__action"
                                   aria-label="View details for <?= $oppTitle ?>">
                                    View Details
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <nav class="civicone-pagination" aria-label="Opportunity list pagination">
                        <?php
                        $current = $pagination['current_page'];
                        $total = $pagination['total_pages'];
                        $base = $pagination['base_path'];
                        $range = 2;
                        $query = !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '';
                        if (!empty($_GET['location'])) {
                            foreach ($_GET['location'] as $loc) {
                                $query .= '&location[]=' . urlencode($loc);
                            }
                        }
                        if (!empty($_GET['commitment'])) {
                            foreach ($_GET['commitment'] as $commit) {
                                $query .= '&commitment[]=' . urlencode($commit);
                            }
                        }
                        ?>

                        <div class="civicone-pagination__results">
                            Showing <?= (($current - 1) * 20 + 1) ?> to <?= min($current * 20, $total_opportunities ?? count($opportunities ?? [])) ?> of <?= $total_opportunities ?? count($opportunities ?? []) ?> opportunities
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
