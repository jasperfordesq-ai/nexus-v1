<?php
/**
 * CivicOne Volunteering Index - GOV.UK Frontend v5.14.0 Compliant
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
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Volunteering</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">Volunteering Opportunities</h1>
        <p class="govuk-body-l">Find volunteering opportunities and make a difference in your community.</p>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="govuk-grid-column-one-third">
        <a href="<?= $basePath ?>/volunteering/my-applications" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0">
            My Applications
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Directory Layout: 1/3 Filters + 2/3 Results -->
<div class="govuk-grid-row">

    <!-- Filters Panel (1/3) -->
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4 civicone-panel-bg" style="margin-bottom: 1.5rem;">
            <h2 class="govuk-heading-m">Filter opportunities</h2>

            <form method="get" action="<?= $basePath ?>/volunteering">
                <!-- Search Input -->
                <div class="govuk-form-group">
                    <label class="govuk-label" for="opportunity-search">
                        Search by role, skill, or location
                    </label>
                    <input
                        type="text"
                        id="opportunity-search"
                        name="q"
                        class="govuk-input"
                        placeholder="Enter keywords..."
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                    >
                </div>

                <!-- Location Checkboxes -->
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend">Location</legend>
                        <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="location-remote" name="location[]" value="remote" class="govuk-checkboxes__input" <?= in_array('remote', $_GET['location'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="location-remote">Remote</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="location-local" name="location[]" value="local" class="govuk-checkboxes__input" <?= in_array('local', $_GET['location'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="location-local">In-person</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <!-- Time Commitment Checkboxes -->
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend">Time commitment</legend>
                        <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="commit-oneoff" name="commitment[]" value="oneoff" class="govuk-checkboxes__input" <?= in_array('oneoff', $_GET['commitment'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="commit-oneoff">One-off</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="commit-regular" name="commitment[]" value="regular" class="govuk-checkboxes__input" <?= in_array('regular', $_GET['commitment'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="commit-regular">Regular</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="commit-flexible" name="commitment[]" value="flexible" class="govuk-checkboxes__input" <?= in_array('flexible', $_GET['commitment'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="commit-flexible">Flexible</label>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <button type="submit" class="govuk-button govuk-button--secondary">
                    Apply filters
                </button>
            </form>

            <!-- Selected Filters -->
            <?php
            $hasFilters = !empty($_GET['q']) || !empty($_GET['location']) || !empty($_GET['commitment']);
            if ($hasFilters):
            ?>
            <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">
            <h3 class="govuk-heading-s">Active filters</h3>
            <p class="govuk-body">
                <?php if (!empty($_GET['q'])): ?>
                    <strong class="govuk-tag">Search: <?= htmlspecialchars($_GET['q']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($_GET['location'])): ?>
                    <?php foreach ($_GET['location'] as $loc): ?>
                        <strong class="govuk-tag govuk-tag--grey">Location: <?= htmlspecialchars(ucfirst($loc)) ?></strong>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($_GET['commitment'])): ?>
                    <?php foreach ($_GET['commitment'] as $commit): ?>
                        <strong class="govuk-tag govuk-tag--grey">Time: <?= htmlspecialchars(ucfirst($commit)) ?></strong>
                    <?php endforeach; ?>
                <?php endif; ?>
            </p>
            <a href="<?= $basePath ?>/volunteering" class="govuk-link">Clear all filters</a>
            <?php endif; ?>
        </div>

        <!-- Organization Dashboard Link -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="govuk-!-padding-4 civicone-panel-bg">
            <h3 class="govuk-heading-s">Organization dashboard</h3>
            <p class="govuk-body-s">Manage your opportunities and applications</p>
            <a href="<?= $basePath ?>/volunteering/dashboard" class="govuk-button govuk-button--secondary">
                View Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Results Panel (2/3) -->
    <div class="govuk-grid-column-two-thirds">

        <!-- Results Count -->
        <p class="govuk-body govuk-!-margin-bottom-4">
            Showing <strong><?= count($opportunities ?? []) ?></strong> <?= count($opportunities ?? []) === 1 ? 'opportunity' : 'opportunities' ?>
        </p>

        <!-- Results List -->
        <?php if (empty($opportunities)): ?>
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-m">No opportunities found</h2>
                <p class="govuk-body">
                    <?php if (!empty($_GET['q'])): ?>
                        No opportunities match your search. Try a different term or check back later.
                    <?php else: ?>
                        There are no volunteer opportunities available at the moment. Check back soon!
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <ul class="govuk-list" role="list">
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
                    $postedDate = !empty($opp['created_at']) ? date('j M Y', strtotime($opp['created_at'])) : '';
                    ?>
                    <li class="govuk-!-margin-bottom-4 govuk-!-padding-bottom-4" style="border-bottom: 1px solid #b1b4b6;">
                        <!-- Organization & Date -->
                        <p class="govuk-body-s govuk-!-margin-bottom-1" style="color: #505a5f;">
                            <?= $orgName ?>
                            <?php if ($postedDate): ?>
                                &middot; Posted <?= $postedDate ?>
                            <?php endif; ?>
                        </p>

                        <!-- Title -->
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                            <a href="<?= $basePath ?>/volunteering/<?= $opp['id'] ?>" class="govuk-link">
                                <?= $oppTitle ?>
                            </a>
                        </h3>

                        <!-- Description -->
                        <p class="govuk-body govuk-!-margin-bottom-2"><?= $oppDesc ?></p>

                        <!-- Tags -->
                        <p class="govuk-body-s govuk-!-margin-bottom-2">
                            <strong class="govuk-tag <?= $isRemote ? 'govuk-tag--green' : 'govuk-tag--grey' ?>"><?= $location ?></strong>
                            <strong class="govuk-tag govuk-tag--grey"><?= $commitment ?></strong>
                        </p>

                        <!-- Action Link -->
                        <a href="<?= $basePath ?>/volunteering/<?= $opp['id'] ?>"
                           class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0"
                           aria-label="View details for <?= $oppTitle ?>">
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
