<?php
/**
 * Mutual Matches Page - Dedicated view for reciprocal matches
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$hTitle = $page_title ?? "Mutual Matches";
$hSubtitle = "Exchange opportunities where you both benefit";
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
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Mutual Matches</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/matches" class="govuk-back-link govuk-!-margin-bottom-6">Back to All Matches</a>

<h1 class="govuk-heading-xl">
    <span aria-hidden="true">🤝</span> Mutual Matches
</h1>
<p class="govuk-body-l govuk-!-margin-bottom-6">Win-win connections where you can both help each other</p>

<div class="govuk-inset-text govuk-!-margin-bottom-6" role="note">
    <h2 class="govuk-heading-m"><span aria-hidden="true">🤝</span> What are Mutual Matches?</h2>
    <p class="govuk-body">These are special matches where you have something they need, AND they have something you need. It's the perfect opportunity for a fair exchange!</p>
</div>

<div class="govuk-!-margin-bottom-6" role="status">
    <span class="govuk-tag govuk-tag--purple govuk-tag--large">
        🤝 <?= count($matches) ?> Mutual Match<?= count($matches) !== 1 ? 'es' : '' ?> Found
    </span>
</div>

<?php if (empty($matches)): ?>
    <div class="govuk-inset-text" role="status">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <span aria-hidden="true">🤝</span>
            <strong>No Mutual Matches Yet</strong>
        </p>
        <p class="govuk-body govuk-!-margin-bottom-4">Mutual matches happen when you can both help each other. Add both offers and requests to find reciprocal exchange opportunities!</p>
        <a href="<?= $basePath ?>/listings/create?type=offer" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Offer a Skill
        </a>
    </div>
<?php else: ?>
    <div class="govuk-grid-row" role="list" aria-label="Mutual matches">
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
