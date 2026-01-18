<?php
/**
 * Match Card Partial
 * Expects $match array with listing and match data
 */
$listingId = $match['id'] ?? 0;
$title = htmlspecialchars($match['title'] ?? 'Untitled');
$userName = htmlspecialchars($match['user_name'] ?? 'Unknown');
$userAvatar = $match['user_avatar'] ?? null;
$matchScore = (int)($match['match_score'] ?? 0);
$distanceKm = $match['distance_km'] ?? null;
$matchType = $match['match_type'] ?? 'one_way';
$matchReasons = $match['match_reasons'] ?? [];
$categoryName = htmlspecialchars($match['category_name'] ?? '');
$listingType = $match['type'] ?? 'offer';
$createdAt = $match['created_at'] ?? null;

// Determine card class
$cardClass = 'match-card';
if ($matchScore >= 85) {
    $cardClass .= ' hot-match';
} elseif ($matchType === 'mutual') {
    $cardClass .= ' mutual-match';
}

// Score badge class
$scoreBadgeClass = 'match-score-badge ';
if ($matchScore >= 85) {
    $scoreBadgeClass .= 'hot';
} elseif ($matchScore >= 70) {
    $scoreBadgeClass .= 'good';
} else {
    $scoreBadgeClass .= 'moderate';
}

// Distance badge class
$distanceBadgeClass = 'distance-badge ';
if ($distanceKm !== null) {
    if ($distanceKm <= 5) {
        $distanceBadgeClass .= 'walking';
    } elseif ($distanceKm <= 15) {
        $distanceBadgeClass .= 'local';
    } elseif ($distanceKm <= 30) {
        $distanceBadgeClass .= 'city';
    } else {
        $distanceBadgeClass .= 'regional';
    }
}

// Avatar fallback
$avatarUrl = $userAvatar ? htmlspecialchars($userAvatar) : 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=6366f1&color=fff';

// Time ago
$timeAgo = '';
if ($createdAt) {
    $diff = time() - strtotime($createdAt);
    if ($diff < 3600) {
        $timeAgo = floor($diff / 60) . 'm ago';
    } elseif ($diff < 86400) {
        $timeAgo = floor($diff / 3600) . 'h ago';
    } else {
        $timeAgo = floor($diff / 86400) . 'd ago';
    }
}

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<div class="<?= $cardClass ?>"
     data-listing-id="<?= $listingId ?>"
     data-match-score="<?= $matchScore ?>"
     data-distance="<?= $distanceKm ?>">

    <!-- Match Score Badge -->
    <div class="<?= $scoreBadgeClass ?>">
        <?php if ($matchScore >= 85): ?>
            🔥
        <?php elseif ($matchScore >= 70): ?>
            ⭐
        <?php else: ?>
            ✓
        <?php endif; ?>
        <?= $matchScore ?>%
    </div>

    <div class="match-card-body">
        <!-- Header with Avatar -->
        <div class="match-card-header">
            <img src="<?= $avatarUrl ?>" loading="lazy" alt="<?= $userName ?>" class="match-avatar">
            <div class="match-info">
                <h3 class="match-title"><?= $title ?></h3>
                <div class="match-user"><?= $userName ?></div>
                <div class="match-meta">
                    <?php if ($distanceKm !== null): ?>
                        <span class="<?= $distanceBadgeClass ?>">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <?= number_format($distanceKm, 1) ?> km
                        </span>
                    <?php endif; ?>
                    <?php if ($categoryName): ?>
                        <span class="match-meta-item">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            <?= $categoryName ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($timeAgo): ?>
                        <span class="match-meta-item">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <?= $timeAgo ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Match Reasons -->
        <?php if (!empty($matchReasons)): ?>
            <div class="match-reasons">
                <?php foreach (array_slice($matchReasons, 0, 4) as $reason): ?>
                    <?php
                    $reasonClass = 'match-reason';
                    if (stripos($reason, 'categor') !== false) $reasonClass .= ' category';
                    elseif (stripos($reason, 'distance') !== false || stripos($reason, 'nearby') !== false || stripos($reason, 'local') !== false) $reasonClass .= ' distance';
                    elseif (stripos($reason, 'mutual') !== false || stripos($reason, 'recipro') !== false) $reasonClass .= ' reciprocal';
                    ?>
                    <span class="<?= $reasonClass ?>"><?= htmlspecialchars($reason) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Mutual Match Indicator -->
        <?php if ($matchType === 'mutual'): ?>
            <div class="match-reasons">
                <span class="match-reason reciprocal">🤝 Mutual Exchange Possible</span>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="match-actions">
            <a href="<?= $basePath ?>/listings/<?= $listingId ?>"
               class="match-action-btn primary"
               onclick="trackMatchInteraction(<?= $listingId ?>, 'viewed', <?= $matchScore ?>, <?= $distanceKm ?? 'null' ?>)">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                View
            </a>
            <a href="<?= $basePath ?>/messages/compose?to=<?= $match['user_id'] ?? 0 ?>&listing=<?= $listingId ?>"
               class="match-action-btn secondary"
               onclick="trackMatchInteraction(<?= $listingId ?>, 'contacted', <?= $matchScore ?>, <?= $distanceKm ?? 'null' ?>)">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                Contact
            </a>
        </div>
    </div>
</div>
