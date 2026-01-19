<?php
/**
 * Mutual Matches Page - Dedicated view for reciprocal matches
 */
$hero_title = $page_title ?? "Mutual Matches";
$hero_subtitle = "Exchange opportunities where you both benefit";
$hero_gradient = 'htb-hero-gradient-matches';
$hero_type = 'Matches';

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$matches = $matches ?? [];
?>


<div class="mutual-matches-bg"></div>

<div class="mutual-container">
    <a href="<?= $basePath ?>/matches" class="mutual-back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to All Matches
    </a>

    <div class="mutual-header">
        <h1 class="mutual-title">
            <span>Mutual Matches</span>
        </h1>
        <p class="mutual-subtitle">Win-win connections where you can both help each other</p>
    </div>

    <div class="mutual-info-card">
        <div class="mutual-info-icon">🤝</div>
        <div class="mutual-info-text">
            <h4>What are Mutual Matches?</h4>
            <p>These are special matches where you have something they need, AND they have something you need. It's the perfect opportunity for a fair exchange!</p>
        </div>
    </div>

    <div style="text-align: center;">
        <div class="mutual-count-badge">
            <span>🤝</span>
            <?= count($matches) ?> Mutual Match<?= count($matches) !== 1 ? 'es' : '' ?> Found
        </div>
    </div>

    <?php if (empty($matches)): ?>
        <div class="mutual-empty">
            <div class="mutual-empty-icon">🤝</div>
            <h3>No Mutual Matches Yet</h3>
            <p>Mutual matches happen when you can both help each other. Add both offers and requests to find reciprocal exchange opportunities!</p>
            <a href="<?= $basePath ?>/listings/create?type=offer" class="mutual-empty-btn">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Offer a Skill
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
