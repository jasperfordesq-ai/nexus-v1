<?php

/**
 * Component: Group Card
 *
 * Card for displaying community groups.
 *
 * @param array $group Group data with keys: id, name, description, cover_image, member_count, is_member, privacy
 * @param bool $showJoin Show join button (default: true)
 * @param bool $showMemberCount Show member count (default: true)
 * @param string $class Additional CSS classes
 * @param string $baseUrl Base URL for group links (default: '')
 */

$group = $group ?? [];
$showJoin = $showJoin ?? true;
$showMemberCount = $showMemberCount ?? true;
$class = $class ?? '';
$baseUrl = $baseUrl ?? '';

// Extract group data with defaults
$id = $group['id'] ?? 0;
$name = $group['name'] ?? 'Untitled Group';
$description = $group['description'] ?? '';
$coverImage = $group['cover_image'] ?? $group['image'] ?? '';
$memberCount = $group['member_count'] ?? $group['members_count'] ?? 0;
$isMember = $group['is_member'] ?? false;
$privacy = $group['privacy'] ?? 'public'; // 'public', 'private', 'secret'

$groupUrl = $baseUrl . '/groups/' . $id;
$cssClass = trim('glass-group-card ' . $class);
?>

<article class="<?= e($cssClass) ?>">
    <?php if ($coverImage): ?>
        <div class="group-cover">
            <a href="<?= e($groupUrl) ?>">
                <?= webp_image($coverImage, e($name), 'group-cover-img') ?>
            </a>
            <?php if ($privacy !== 'public'): ?>
                <span class="group-privacy-badge">
                    <i class="fa-solid fa-<?= $privacy === 'private' ? 'lock' : 'eye-slash' ?>"></i>
                    <?= ucfirst($privacy) ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="group-content">
        <h3 class="group-name">
            <a href="<?= e($groupUrl) ?>"><?= e($name) ?></a>
        </h3>

        <?php if ($description): ?>
            <p class="group-description"><?= e(mb_strimwidth(strip_tags($description), 0, 100, '...')) ?></p>
        <?php endif; ?>

        <div class="group-footer">
            <?php if ($showMemberCount): ?>
                <div class="group-members">
                    <i class="fa-solid fa-users"></i>
                    <span><?= number_format($memberCount) ?> member<?= $memberCount !== 1 ? 's' : '' ?></span>
                </div>
            <?php endif; ?>

            <?php if ($showJoin): ?>
                <?php if ($isMember): ?>
                    <a href="<?= e($groupUrl) ?>" class="group-view-btn">
                        <i class="fa-solid fa-arrow-right"></i> View Group
                    </a>
                <?php else: ?>
                    <button type="button" class="group-join-btn" data-group-id="<?= $id ?>">
                        <i class="fa-solid fa-plus"></i> Join
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</article>
