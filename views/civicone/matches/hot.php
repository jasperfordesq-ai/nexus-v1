<?php
/**
 * Hot Matches Page - Dedicated view for high-score matches (85%+)
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$hTitle = $page_title ?? "Hot Matches";
$hSubtitle = "High-compatibility matches nearby - act fast!";
$hGradient = 'mt-hero-gradient-accent';
$hType = 'Matches';

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$matches = $matches ?? [];
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/matches">Matches</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Hot Matches</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/matches" class="govuk-back-link govuk-!-margin-bottom-6">Back to All Matches</a>

<h1 class="govuk-heading-xl">
    <span aria-hidden="true">🔥</span> Hot Matches
</h1>
<p class="govuk-body-l govuk-!-margin-bottom-6">These are your highest compatibility matches - 85% or above!</p>

<div class="govuk-!-margin-bottom-6" role="status">
    <span class="govuk-tag govuk-tag--green govuk-tag--large">
        🔥 <?= count($matches) ?> Hot Match<?= count($matches) !== 1 ? 'es' : '' ?> Found
    </span>
</div>

<?php if (empty($matches)): ?>
    <div class="govuk-inset-text" role="status">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <span aria-hidden="true">🔥</span>
            <strong>No Hot Matches Yet</strong>
        </p>
        <p class="govuk-body govuk-!-margin-bottom-4">Hot matches are listings with 85%+ compatibility and close proximity. Try adding more listings or expanding your preferences!</p>
        <a href="<?= $basePath ?>/listings/create" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Create a Listing
        </a>
    </div>
<?php else: ?>
    <div class="govuk-grid-row" role="list" aria-label="Hot matches">
        <?php foreach ($matches as $match): ?>
            <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                <?php include __DIR__ . '/_match_card.php'; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
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
