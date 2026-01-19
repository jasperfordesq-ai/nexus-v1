<?php
/**
 * Hot Matches Page - Dedicated view for high-score matches (85%+)
 * CivicOne accessible version
 */
$hTitle = $page_title ?? "Hot Matches";
$hSubtitle = "High-compatibility matches nearby - act fast!";
$hGradient = 'mt-hero-gradient-accent';
$hType = 'Matches';

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$matches = $matches ?? [];
?>

<div class="hot-container">
    <a href="<?= $basePath ?>/matches" class="back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to All Matches
    </a>

    <div class="hot-header">
        <h1 class="hot-title">Hot Matches</h1>
        <p class="hot-subtitle">These are your highest compatibility matches - 85% or above!</p>
    </div>

    <div class="hot-count-badge" role="status">
        <span aria-hidden="true">🔥</span>
        <?= count($matches) ?> Hot Match<?= count($matches) !== 1 ? 'es' : '' ?> Found
    </div>

    <?php if (empty($matches)): ?>
        <div class="empty-state" role="status">
            <div class="empty-state-icon" aria-hidden="true">🔥</div>
            <h2>No Hot Matches Yet</h2>
            <p>Hot matches are listings with 85%+ compatibility and close proximity. Try adding more listings or expanding your preferences!</p>
            <a href="<?= $basePath ?>/listings/create" class="btn btn-primary">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Create a Listing
            </a>
        </div>
    <?php else: ?>
        <div class="match-cards-grid" role="list" aria-label="Hot matches">
            <?php foreach ($matches as $match): ?>
                <?php include __DIR__ . '/_match_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

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
    }).catch(console.error);
}
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
