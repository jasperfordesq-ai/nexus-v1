<?php
/**
 * CivicOne Groups Directory - GOV.UK Frontend v5.14.0 Compliant
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
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Groups</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">Groups Directory</h1>
        <p class="govuk-body-l">Join groups and connect with people who share your interests.</p>
    </div>
    <div class="govuk-grid-column-one-third">
        <a href="<?= $basePath ?>/create-group" class="govuk-button govuk-!-margin-bottom-0">
            Start a Hub
        </a>
    </div>
</div>

<!-- Directory Layout: 1/3 Filters + 2/3 Results -->
<div class="govuk-grid-row">

    <!-- Filters Panel (1/3) -->
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-panel-bg">
            <h2 class="govuk-heading-m">Filter hubs</h2>

            <form method="get" action="<?= $basePath ?>/groups">
                <!-- Search Input -->
                <div class="govuk-form-group">
                    <label class="govuk-label" for="group-search">
                        Search by name or interest
                    </label>
                    <input
                        type="text"
                        id="group-search"
                        name="q"
                        class="govuk-input"
                        placeholder="Enter keywords..."
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                    >
                </div>

                <!-- Hub Type Checkboxes -->
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend">Hub type</legend>
                        <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="type-community" name="type[]" value="community" class="govuk-checkboxes__input" <?= in_array('community', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="type-community">Community</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="type-interest" name="type[]" value="interest" class="govuk-checkboxes__input" <?= in_array('interest', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="type-interest">Interest</label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" id="type-skill" name="type[]" value="skill" class="govuk-checkboxes__input" <?= in_array('skill', $_GET['type'] ?? []) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="type-skill">Skill Share</label>
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
            $hasFilters = !empty($_GET['q']) || !empty($_GET['type']);
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
            </p>
            <a href="<?= $basePath ?>/groups" class="govuk-link">Clear all filters</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results Panel (2/3) -->
    <div class="govuk-grid-column-two-thirds">

        <!-- Results Count -->
        <p class="govuk-body govuk-!-margin-bottom-4">
            Showing <strong><?= count($groups) ?></strong> <?= count($groups) === 1 ? 'hub' : 'hubs' ?>
        </p>

        <!-- Results List -->
        <?php if (empty($groups)): ?>
            <div class="govuk-inset-text">
                <h2 class="govuk-heading-m">No hubs found</h2>
                <p class="govuk-body">Try adjusting your filters or check back later.</p>
            </div>
        <?php else: ?>
            <ul class="govuk-list" id="groups-list" role="list">
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
                    <li class="govuk-!-margin-bottom-4 govuk-!-padding-bottom-4 civicone-listing-item civicone-card-row">
                        <!-- Group Image/Avatar -->
                        <div class="civicone-card-row__media">
                            <?php if ($hasImg): ?>
                                <img src="<?= htmlspecialchars($group['image_path']) ?>" alt="" width="64" height="64" class="civicone-card-row__image">
                            <?php else: ?>
                                <div class="civicone-card-row__placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Group Content -->
                        <div class="civicone-card-row__content">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                                <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="govuk-link">
                                    <?= $gName ?>
                                </a>
                            </h3>
                            <p class="govuk-body-s govuk-!-margin-bottom-1"><?= $gDesc ?></p>
                            <?php if ($memberCount > 0): ?>
                            <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                <?= $memberCount ?> <?= $memberCount === 1 ? 'member' : 'members' ?>
                            </p>
                            <?php endif; ?>
                        </div>

                        <!-- Group Actions -->
                        <div class="civicone-card-row__actions">
                            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>"
                               class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0"
                               aria-label="Visit <?= $gName ?> hub">
                                Visit Hub
                            </a>
                        </div>
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
