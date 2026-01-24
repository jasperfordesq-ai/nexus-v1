<?php

/**
 * Component: Member Card
 *
 * Card for displaying community members/users.
 *
 * @param array $user User data with keys: id, name, avatar, bio, location, skills, connection_status
 * @param bool $showConnect Show connect button (default: true)
 * @param bool $showLocation Show location (default: true)
 * @param bool $showSkills Show skills tags (default: true)
 * @param string $class Additional CSS classes
 * @param string $baseUrl Base URL for member links (default: '')
 */

$user = $user ?? [];
$showConnect = $showConnect ?? true;
$showLocation = $showLocation ?? true;
$showSkills = $showSkills ?? true;
$class = $class ?? '';
$baseUrl = $baseUrl ?? '';

// Extract user data with defaults
$id = $user['id'] ?? 0;
$name = $user['name'] ?? $user['display_name'] ?? 'Unknown User';
$avatar = $user['avatar'] ?? $user['profile_image'] ?? '';
$bio = $user['bio'] ?? $user['about'] ?? '';
$location = $user['location'] ?? $user['city'] ?? '';
$skills = $user['skills'] ?? [];
$connectionStatus = $user['connection_status'] ?? 'none'; // 'none', 'pending', 'connected'
$distance = $user['distance'] ?? null;

$profileUrl = $baseUrl . '/members/' . $id;
$cssClass = trim('glass-member-card ' . $class);
?>

<article class="<?= e($cssClass) ?>">
    <div class="member-header">
        <a href="<?= e($profileUrl) ?>" class="member-avatar-link">
            <?= webp_avatar($avatar, $name, 64) ?>
        </a>
        <div class="member-info">
            <h3 class="member-name">
                <a href="<?= e($profileUrl) ?>"><?= e($name) ?></a>
            </h3>
            <?php if ($showLocation && $location): ?>
                <div class="member-location">
                    <i class="fa-solid fa-location-dot"></i>
                    <span><?= e($location) ?></span>
                    <?php if ($distance !== null): ?>
                        <span class="member-distance">(<?= number_format($distance, 1) ?> km)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($bio): ?>
        <p class="member-bio"><?= e(mb_strimwidth(strip_tags($bio), 0, 100, '...')) ?></p>
    <?php endif; ?>

    <?php if ($showSkills && !empty($skills)): ?>
        <div class="member-skills">
            <?php
            $displaySkills = array_slice($skills, 0, 3);
            foreach ($displaySkills as $skill):
            ?>
                <span class="skill-tag"><?= e(is_array($skill) ? $skill['name'] : $skill) ?></span>
            <?php endforeach; ?>
            <?php if (count($skills) > 3): ?>
                <span class="skill-tag skill-more">+<?= count($skills) - 3 ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($showConnect): ?>
        <div class="member-actions">
            <?php if ($connectionStatus === 'connected'): ?>
                <span class="member-connected-badge">
                    <i class="fa-solid fa-user-check"></i> Connected
                </span>
            <?php elseif ($connectionStatus === 'pending'): ?>
                <span class="member-pending-badge">
                    <i class="fa-solid fa-clock"></i> Pending
                </span>
            <?php else: ?>
                <button type="button" class="member-connect-btn" data-user-id="<?= $id ?>">
                    <i class="fa-solid fa-user-plus"></i> Connect
                </button>
            <?php endif; ?>
            <a href="<?= e($profileUrl) ?>" class="member-view-btn">View Profile</a>
        </div>
    <?php endif; ?>
</article>
