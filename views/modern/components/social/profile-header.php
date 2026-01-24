<?php

/**
 * Component: Profile Header
 *
 * User profile header with avatar, info, and actions.
 * Used on: profile/show, federation/member-profile, federation/partner-profile
 *
 * @param array $user User data with keys: id, name, avatar, bio, location, joined_at, credits, rating, is_online
 * @param array $currentUser Current logged-in user (for determining action buttons)
 * @param string $connectionStatus Connection status: 'none', 'pending', 'connected', 'self'
 * @param array $badges User badges/achievements to display
 * @param array $stats User stats: ['listings' => X, 'hours' => Y, 'reviews' => Z]
 * @param bool $showActions Show action buttons (default: true)
 * @param bool $showStats Show stats section (default: true)
 * @param string $class Additional CSS classes
 * @param string $basePath Base path for links
 */

$user = $user ?? [];
$currentUser = $currentUser ?? [];
$connectionStatus = $connectionStatus ?? 'none';
$badges = $badges ?? [];
$stats = $stats ?? [];
$showActions = $showActions ?? true;
$showStats = $showStats ?? true;
$class = $class ?? '';
$basePath = $basePath ?? '';

// Extract user data
$userId = $user['id'] ?? 0;
$userName = $user['name'] ?? $user['display_name'] ?? 'Unknown User';
$userAvatar = $user['avatar'] ?? $user['profile_image'] ?? '';
$userBio = $user['bio'] ?? $user['about'] ?? '';
$userLocation = $user['location'] ?? $user['city'] ?? '';
$userJoinedAt = $user['joined_at'] ?? $user['created_at'] ?? '';
$userCredits = $user['credits'] ?? $user['time_credits'] ?? 0;
$userRating = $user['rating'] ?? $user['avg_rating'] ?? 0;
$userReviewCount = $user['review_count'] ?? 0;
$isOnline = $user['is_online'] ?? false;

$isSelf = ($currentUser['id'] ?? 0) === $userId;
if ($isSelf) {
    $connectionStatus = 'self';
}

// Format joined date
$joinedFormatted = '';
if ($userJoinedAt) {
    $joinedFormatted = date('F Y', strtotime($userJoinedAt));
}

$cssClass = trim('component-profile glass-profile-card ' . $class);
?>

<div class="<?= e($cssClass) ?>">
    <div class="component-profile__inner">
        <!-- Avatar -->
        <div class="component-profile__avatar-section">
            <div class="component-profile__avatar-wrapper">
                <?= webp_avatar($userAvatar, $userName, 120) ?>
                <?php if ($isOnline): ?>
                    <span class="component-profile__online-indicator"></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info -->
        <div class="component-profile__info">
            <div class="component-profile__name-row">
                <h1 class="component-profile__name">
                    <?= e($userName) ?>
                </h1>
                <?php if ($isOnline): ?>
                    <span class="component-profile__online-badge">
                        <span class="component-profile__online-dot"></span>
                        Online
                    </span>
                <?php endif; ?>
            </div>

            <!-- Meta Info -->
            <div class="component-profile__meta">
                <?php if ($userLocation): ?>
                    <span class="component-profile__meta-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <?= e($userLocation) ?>
                    </span>
                <?php endif; ?>
                <?php if ($joinedFormatted): ?>
                    <span class="component-profile__meta-item">
                        <i class="fa-solid fa-calendar"></i>
                        Joined <?= e($joinedFormatted) ?>
                    </span>
                <?php endif; ?>
                <?php if ($userCredits > 0): ?>
                    <span class="component-profile__meta-item">
                        <i class="fa-solid fa-clock"></i>
                        <?= number_format($userCredits) ?> time credits
                    </span>
                <?php endif; ?>
                <?php if ($userRating > 0): ?>
                    <span class="component-profile__meta-item">
                        <i class="fa-solid fa-star component-profile__star-icon"></i>
                        <?= number_format($userRating, 1) ?>
                        <?php if ($userReviewCount > 0): ?>
                            (<?= $userReviewCount ?> review<?= $userReviewCount !== 1 ? 's' : '' ?>)
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($userBio): ?>
                <p class="component-profile__bio">
                    <?= e($userBio) ?>
                </p>
            <?php endif; ?>

            <!-- Badges -->
            <?php if (!empty($badges)): ?>
                <div class="component-profile__badges">
                    <?php foreach (array_slice($badges, 0, 5) as $badge): ?>
                        <span class="component-profile__badge" title="<?= e($badge['name'] ?? '') ?>">
                            <i class="fa-solid fa-<?= e($badge['icon'] ?? 'award') ?> component-profile__badge-icon"></i>
                            <?= e($badge['name'] ?? '') ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (count($badges) > 5): ?>
                        <span class="component-profile__badge component-profile__badge--more">
                            +<?= count($badges) - 5 ?> more
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <?php if ($showActions): ?>
            <div class="component-profile__actions">
                <?php if ($connectionStatus === 'self'): ?>
                    <a href="<?= e($basePath) ?>/settings/profile" class="nexus-smart-btn nexus-smart-btn-outline">
                        <i class="fa-solid fa-pen"></i> Edit Profile
                    </a>
                <?php else: ?>
                    <?php if ($connectionStatus === 'none'): ?>
                        <button type="button" class="nexus-smart-btn nexus-smart-btn-primary" onclick="sendConnectionRequest(<?= $userId ?>)">
                            <i class="fa-solid fa-user-plus"></i> Connect
                        </button>
                    <?php elseif ($connectionStatus === 'pending'): ?>
                        <button type="button" class="nexus-smart-btn nexus-smart-btn-outline" disabled>
                            <i class="fa-solid fa-clock"></i> Pending
                        </button>
                    <?php elseif ($connectionStatus === 'connected'): ?>
                        <span class="component-profile__connected-badge nexus-smart-btn nexus-smart-btn-outline">
                            <i class="fa-solid fa-user-check"></i> Connected
                        </span>
                    <?php endif; ?>

                    <a href="<?= e($basePath) ?>/messages/new?to=<?= $userId ?>" class="nexus-smart-btn nexus-smart-btn-outline">
                        <i class="fa-solid fa-envelope"></i> Message
                    </a>

                    <a href="<?= e($basePath) ?>/wallet/send?to=<?= $userId ?>" class="nexus-smart-btn nexus-smart-btn-outline">
                        <i class="fa-solid fa-clock"></i> Send Credits
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <?php if ($showStats && !empty($stats)): ?>
        <div class="component-profile__stats">
            <?php foreach ($stats as $statKey => $statValue): ?>
                <?php
                $statLabels = [
                    'listings' => 'Listings',
                    'hours' => 'Hours Exchanged',
                    'reviews' => 'Reviews',
                    'connections' => 'Connections',
                    'posts' => 'Posts',
                    'events' => 'Events Attended',
                ];
                $statLabel = $statLabels[$statKey] ?? ucfirst($statKey);
                ?>
                <div class="component-profile__stat">
                    <div class="component-profile__stat-value">
                        <?= number_format($statValue) ?>
                    </div>
                    <div class="component-profile__stat-label">
                        <?= e($statLabel) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
