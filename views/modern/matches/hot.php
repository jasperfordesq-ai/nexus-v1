<?php
/**
 * Hot Matches Page - Dedicated view for high-score matches (85%+)
 */
$hero_title = $page_title ?? "Hot Matches";
$hero_subtitle = "High-compatibility matches nearby - act fast!";
$hero_gradient = 'htb-hero-gradient-matches';
$hero_type = 'Matches';

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$matches = $matches ?? [];
?>


<div class="hot-matches-bg"></div>

<div class="hot-container">
    <a href="<?= $basePath ?>/matches" class="hot-back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to All Matches
    </a>

    <div class="hot-header">
        <h1 class="hot-title">
            <span>Hot Matches</span>
        </h1>
        <p class="hot-subtitle">These are your highest compatibility matches - 85% or above!</p>
    </div>

    <div style="text-align: center;">
        <div class="hot-count-badge">
            <span>🔥</span>
            <?= count($matches) ?> Hot Match<?= count($matches) !== 1 ? 'es' : '' ?> Found
        </div>
    </div>

    <?php if (empty($matches)): ?>
        <div class="hot-empty">
            <div class="hot-empty-icon">🔥</div>
            <h3>No Hot Matches Yet</h3>
            <p>Hot matches are listings with 85%+ compatibility and close proximity. Try adding more listings or expanding your preferences!</p>
            <a href="<?= $basePath ?>/listings/create" class="hot-empty-btn">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create a Listing
            </a>
        </div>
    <?php else: ?>
        <div class="match-cards-grid">
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

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
