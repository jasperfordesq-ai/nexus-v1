<?php
/**
 * Match Card Partial - GOV.UK Design System
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

// Score tag color
$scoreTagClass = 'govuk-tag';
if ($matchScore >= 85) {
    $scoreTagClass .= ' govuk-tag--green';
} elseif ($matchScore >= 70) {
    $scoreTagClass .= ' govuk-tag--light-blue';
} else {
    $scoreTagClass .= ' govuk-tag--grey';
}

// Distance tag
$distanceTagClass = 'govuk-tag govuk-tag--grey';
if ($distanceKm !== null && $distanceKm <= 5) {
    $distanceTagClass = 'govuk-tag govuk-tag--green';
} elseif ($distanceKm !== null && $distanceKm <= 15) {
    $distanceTagClass = 'govuk-tag govuk-tag--light-blue';
}

// Avatar fallback
$avatarUrl = $userAvatar ? htmlspecialchars($userAvatar) : 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=1d70b8&color=fff';

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

<div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid <?= $matchScore >= 85 ? '#00703c' : ($matchScore >= 70 ? '#1d70b8' : '#b1b4b6') ?>;"
     data-listing-id="<?= $listingId ?>"
     data-match-score="<?= $matchScore ?>"
     data-distance="<?= $distanceKm ?>">

    <!-- Match Score Badge -->
    <p class="govuk-body-s govuk-!-margin-bottom-3">
        <span class="<?= $scoreTagClass ?>">
            <?php if ($matchScore >= 85): ?>🔥<?php elseif ($matchScore >= 70): ?>⭐<?php else: ?>✓<?php endif; ?>
            <?= $matchScore ?>% match
        </span>
        <?php if ($matchType === 'mutual'): ?>
            <span class="govuk-tag govuk-tag--purple govuk-!-margin-left-1">🤝 Mutual</span>
        <?php endif; ?>
    </p>

    <!-- Header with Avatar -->
    <div class="civicone-flex-gap govuk-!-margin-bottom-3">
        <img src="<?= $avatarUrl ?>" loading="lazy" alt="" class="civicone-avatar-md">
        <div>
            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                <a href="<?= $basePath ?>/listings/<?= $listingId ?>" class="govuk-link"><?= $title ?></a>
            </h3>
            <p class="govuk-body-s govuk-!-margin-bottom-0"><?= $userName ?></p>
        </div>
    </div>

    <!-- Meta info -->
    <p class="govuk-body-s govuk-!-margin-bottom-3">
        <?php if ($distanceKm !== null): ?>
            <span class="<?= $distanceTagClass ?> govuk-!-margin-right-1">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <?= number_format($distanceKm, 1) ?> km
            </span>
        <?php endif; ?>
        <?php if ($categoryName): ?>
            <span class="govuk-tag govuk-tag--grey govuk-!-margin-right-1"><?= $categoryName ?></span>
        <?php endif; ?>
        <?php if ($timeAgo): ?>
            <span class="civicone-secondary-text"><?= $timeAgo ?></span>
        <?php endif; ?>
    </p>

    <!-- Match Reasons -->
    <?php if (!empty($matchReasons)): ?>
        <ul class="govuk-list govuk-list--bullet govuk-body-s govuk-!-margin-bottom-3">
            <?php foreach (array_slice($matchReasons, 0, 3) as $reason): ?>
                <li><?= htmlspecialchars($reason) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <!-- Actions -->
    <div class="govuk-button-group">
        <a href="<?= $basePath ?>/listings/<?= $listingId ?>"
           class="govuk-button govuk-button--secondary"
           data-module="govuk-button"
           onclick="trackMatchInteraction(<?= $listingId ?>, 'viewed', <?= $matchScore ?>, <?= $distanceKm ?? 'null' ?>)">
            <i class="fa-solid fa-eye govuk-!-margin-right-1" aria-hidden="true"></i> View
        </a>
        <a href="<?= $basePath ?>/messages/compose?to=<?= $match['user_id'] ?? 0 ?>&listing=<?= $listingId ?>"
           class="govuk-button"
           data-module="govuk-button"
           onclick="trackMatchInteraction(<?= $listingId ?>, 'contacted', <?= $matchScore ?>, <?= $distanceKm ?? 'null' ?>)">
            <i class="fa-solid fa-comment govuk-!-margin-right-1" aria-hidden="true"></i> Contact
        </a>
    </div>
</div>
