<?php
/**
 * Smart Matches Dashboard
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$hero_title = $page_title ?? "Smart Matches";
$hero_subtitle = "AI-powered matches based on your preferences, skills, and location";
$hero_gradient = 'htb-hero-gradient-matches';
$hero_type = 'Matches';

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$hotMatches = $hot_matches ?? [];
$goodMatches = $good_matches ?? [];
$mutualMatches = $mutual_matches ?? [];
$allMatches = $all_matches ?? [];
$stats = $stats ?? [];
$preferences = $preferences ?? [];
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Smart Matches</li>
    </ol>
</nav>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">Smart Matches</h1>
        <p class="govuk-body-l">AI-powered matching based on your preferences, skills, and location</p>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/matches/preferences" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-gear govuk-!-margin-right-1" aria-hidden="true"></i> Preferences
        </a>
    </div>
</div>

<!-- Stats Bar -->
<div class="govuk-grid-row govuk-!-margin-bottom-6" role="region" aria-label="Match statistics">
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4 govuk-!-text-align-centre civicone-panel-bg">
            <p class="govuk-heading-xl govuk-!-margin-bottom-1"><?= count($hotMatches) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0"><span aria-hidden="true">🔥</span> Hot Matches</p>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4 govuk-!-text-align-centre civicone-panel-bg">
            <p class="govuk-heading-xl govuk-!-margin-bottom-1"><?= count($mutualMatches) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0"><span aria-hidden="true">🤝</span> Mutual</p>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4 govuk-!-text-align-centre civicone-panel-bg">
            <p class="govuk-heading-xl govuk-!-margin-bottom-1"><?= count($goodMatches) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0"><span aria-hidden="true">⭐</span> Good Matches</p>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4 govuk-!-text-align-centre civicone-panel-bg">
            <p class="govuk-heading-xl govuk-!-margin-bottom-1"><?= $stats['total_matches'] ?? count($allMatches) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0"><span aria-hidden="true">📊</span> Total Found</p>
        </div>
    </div>
</div>

<!-- Preferences Bar -->
<div class="govuk-inset-text govuk-!-margin-bottom-6">
    <p class="govuk-body govuk-!-margin-bottom-0">
        <i class="fa-solid fa-sliders govuk-!-margin-right-2" aria-hidden="true"></i>
        <strong>Current settings:</strong> Max distance: <?= $preferences['max_distance_km'] ?? 25 ?>km | Min score: <?= $preferences['min_match_score'] ?? 50 ?>%
        <a href="<?= $basePath ?>/matches/preferences" class="govuk-link govuk-!-margin-left-4">Change preferences</a>
    </p>
</div>

<!-- Tab Navigation -->
<div class="govuk-tabs" data-module="govuk-tabs">
    <h2 class="govuk-tabs__title">Match categories</h2>
    <ul class="govuk-tabs__list" role="tablist">
        <li class="govuk-tabs__list-item govuk-tabs__list-item--selected" role="presentation">
            <a class="govuk-tabs__tab" href="#section-hot" role="tab" aria-selected="true">
                <span aria-hidden="true">🔥</span> Hot <span class="govuk-tag govuk-tag--grey govuk-!-margin-left-1"><?= count($hotMatches) ?></span>
            </a>
        </li>
        <li class="govuk-tabs__list-item" role="presentation">
            <a class="govuk-tabs__tab" href="#section-mutual" role="tab" aria-selected="false">
                <span aria-hidden="true">🤝</span> Mutual <span class="govuk-tag govuk-tag--grey govuk-!-margin-left-1"><?= count($mutualMatches) ?></span>
            </a>
        </li>
        <li class="govuk-tabs__list-item" role="presentation">
            <a class="govuk-tabs__tab" href="#section-good" role="tab" aria-selected="false">
                <span aria-hidden="true">⭐</span> Good <span class="govuk-tag govuk-tag--grey govuk-!-margin-left-1"><?= count($goodMatches) ?></span>
            </a>
        </li>
        <li class="govuk-tabs__list-item" role="presentation">
            <a class="govuk-tabs__tab" href="#section-all" role="tab" aria-selected="false">
                <span aria-hidden="true">📋</span> All <span class="govuk-tag govuk-tag--grey govuk-!-margin-left-1"><?= count($allMatches) ?></span>
            </a>
        </li>
    </ul>

    <!-- Hot Matches Section -->
    <div class="govuk-tabs__panel" id="section-hot" role="tabpanel">
        <h2 class="govuk-heading-l"><span aria-hidden="true">🔥</span> Hot Matches</h2>

        <?php if (empty($hotMatches)): ?>
            <div class="govuk-inset-text" role="status">
                <p class="govuk-body-l govuk-!-margin-bottom-2"><strong>No Hot Matches Yet</strong></p>
                <p class="govuk-body govuk-!-margin-bottom-4">Hot matches are listings with 85%+ compatibility. Try adjusting your preferences or add more listings!</p>
                <a href="<?= $basePath ?>/listings/create" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Create a Listing
                </a>
            </div>
        <?php else: ?>
            <div class="govuk-grid-row" role="list" aria-label="Hot matches">
                <?php foreach ($hotMatches as $match): ?>
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <?php include __DIR__ . '/_match_card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mutual Matches Section -->
    <div class="govuk-tabs__panel govuk-tabs__panel--hidden" id="section-mutual" role="tabpanel">
        <h2 class="govuk-heading-l"><span aria-hidden="true">🤝</span> Mutual Matches</h2>

        <?php if (empty($mutualMatches)): ?>
            <div class="govuk-inset-text" role="status">
                <p class="govuk-body-l govuk-!-margin-bottom-2"><strong>No Mutual Matches Yet</strong></p>
                <p class="govuk-body govuk-!-margin-bottom-4">Mutual matches happen when you can both help each other. Keep sharing your skills!</p>
                <a href="<?= $basePath ?>/listings/create?type=offer" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Offer a Skill
                </a>
            </div>
        <?php else: ?>
            <div class="govuk-grid-row" role="list" aria-label="Mutual matches">
                <?php foreach ($mutualMatches as $match): ?>
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <?php include __DIR__ . '/_match_card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Good Matches Section -->
    <div class="govuk-tabs__panel govuk-tabs__panel--hidden" id="section-good" role="tabpanel">
        <h2 class="govuk-heading-l"><span aria-hidden="true">⭐</span> Good Matches</h2>

        <?php if (empty($goodMatches)): ?>
            <div class="govuk-inset-text" role="status">
                <p class="govuk-body-l govuk-!-margin-bottom-2"><strong>No Good Matches Found</strong></p>
                <p class="govuk-body govuk-!-margin-bottom-4">Good matches are listings with 70-84% compatibility in your area.</p>
                <a href="<?= $basePath ?>/matches/preferences" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-gear govuk-!-margin-right-1" aria-hidden="true"></i> Adjust Preferences
                </a>
            </div>
        <?php else: ?>
            <div class="govuk-grid-row" role="list" aria-label="Good matches">
                <?php foreach ($goodMatches as $match): ?>
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <?php include __DIR__ . '/_match_card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- All Matches Section -->
    <div class="govuk-tabs__panel govuk-tabs__panel--hidden" id="section-all" role="tabpanel">
        <h2 class="govuk-heading-l"><span aria-hidden="true">📋</span> All Matches</h2>

        <?php if (empty($allMatches)): ?>
            <div class="govuk-inset-text" role="status">
                <p class="govuk-body-l govuk-!-margin-bottom-2"><strong>No Matches Found</strong></p>
                <p class="govuk-body govuk-!-margin-bottom-4">We couldn't find any matches based on your current preferences. Try expanding your search radius or adding more listings.</p>
                <a href="<?= $basePath ?>/listings/create" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Create a Listing
                </a>
            </div>
        <?php else: ?>
            <div class="govuk-grid-row" role="list" aria-label="All matches">
                <?php foreach ($allMatches as $match): ?>
                    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                        <?php include __DIR__ . '/_match_card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Track interactions
function trackMatchInteraction(listingId, action, matchScore, distance) {
    fetch('<?= $basePath ?>/matches/interact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            listing_id: listingId,
            action: action,
            match_score: matchScore,
            distance: distance
        })
    }).catch(function(err) { console.warn('Track error:', err); });
}
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
