<?php
/**
 * Mutual Matches Page - Dedicated view for reciprocal matches
 * CivicOne accessible version
 */
$hTitle = $page_title ?? "Mutual Matches";
$hSubtitle = "Exchange opportunities where you both benefit";
$hGradient = 'mt-hero-gradient-accent';
$hType = 'Matches';

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$matches = $matches ?? [];
?>

<div class="mutual-container">
    <a href="<?= $basePath ?>/matches" class="back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to All Matches
    </a>

    <div class="mutual-header">
        <h1 class="mutual-title">Mutual Matches</h1>
        <p class="mutual-subtitle">Win-win connections where you can both help each other</p>
    </div>

    <div class="info-card" role="note">
        <div class="info-card-icon" aria-hidden="true">🤝</div>
        <div class="info-card-content">
            <h2>What are Mutual Matches?</h2>
            <p>These are special matches where you have something they need, AND they have something you need. It's the perfect opportunity for a fair exchange!</p>
        </div>
    </div>

    <div class="mutual-count-badge" role="status">
        <span aria-hidden="true">🤝</span>
        <?= count($matches) ?> Mutual Match<?= count($matches) !== 1 ? 'es' : '' ?> Found
    </div>

    <?php if (empty($matches)): ?>
        <div class="empty-state" role="status">
            <div class="empty-state-icon" aria-hidden="true">🤝</div>
            <h2>No Mutual Matches Yet</h2>
            <p>Mutual matches happen when you can both help each other. Add both offers and requests to find reciprocal exchange opportunities!</p>
            <a href="<?= $basePath ?>/listings/create?type=offer" class="btn btn-primary">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Offer a Skill
            </a>
        </div>
    <?php else: ?>
        <div class="match-cards-grid" role="list" aria-label="Mutual matches">
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
