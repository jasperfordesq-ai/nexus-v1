<?php

/**
 * Component: Post Card
 *
 * Card for displaying feed posts/status updates.
 *
 * @param array $post Post data with keys: id, content, user, created_at, likes_count, comments_count, images, is_liked, visibility
 * @param bool $showActions Show like/comment/share buttons (default: true)
 * @param bool $showComments Show comments section (default: false)
 * @param string $class Additional CSS classes
 * @param string $baseUrl Base URL for post links (default: '')
 */

$post = $post ?? [];
$showActions = $showActions ?? true;
$showComments = $showComments ?? false;
$class = $class ?? '';
$baseUrl = $baseUrl ?? '';

// Extract post data with defaults
$id = $post['id'] ?? 0;
$content = $post['content'] ?? $post['body'] ?? '';
$user = $post['user'] ?? [];
$createdAt = $post['created_at'] ?? '';
$likesCount = $post['likes_count'] ?? 0;
$commentsCount = $post['comments_count'] ?? 0;
$images = $post['images'] ?? [];
$isLiked = $post['is_liked'] ?? false;
$visibility = $post['visibility'] ?? 'public';
$group = $post['group'] ?? null;

$postUrl = $baseUrl . '/feed/' . $id;
$cssClass = trim('fb-card post-card ' . $class);

// Format time ago
$timeAgo = '';
if ($createdAt) {
    $timestamp = is_string($createdAt) ? strtotime($createdAt) : $createdAt;
    $diff = time() - $timestamp;
    if ($diff < 60) $timeAgo = 'Just now';
    elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
    elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
    elseif ($diff < 604800) $timeAgo = floor($diff / 86400) . 'd ago';
    else $timeAgo = date('M j', $timestamp);
}
?>

<article class="<?= e($cssClass) ?>" data-post-id="<?= $id ?>">
    <div class="post-header">
        <a href="<?= e($baseUrl . '/members/' . ($user['id'] ?? 0)) ?>" class="post-avatar">
            <?= webp_avatar($user['avatar'] ?? '', $user['name'] ?? 'User', 44) ?>
        </a>
        <div class="post-meta">
            <div class="post-author">
                <a href="<?= e($baseUrl . '/members/' . ($user['id'] ?? 0)) ?>" class="post-author-name">
                    <?= e($user['name'] ?? 'Unknown User') ?>
                </a>
                <?php if ($group): ?>
                    <span class="post-in-group">
                        <i class="fa-solid fa-caret-right"></i>
                        <a href="<?= e($baseUrl . '/groups/' . ($group['id'] ?? 0)) ?>"><?= e($group['name'] ?? '') ?></a>
                    </span>
                <?php endif; ?>
            </div>
            <div class="post-timestamp">
                <a href="<?= e($postUrl) ?>"><?= e($timeAgo) ?></a>
                <?php if ($visibility !== 'public'): ?>
                    <i class="fa-solid fa-<?= $visibility === 'private' ? 'lock' : 'user-friends' ?>" title="<?= ucfirst(e($visibility)) ?>"></i>
                <?php endif; ?>
            </div>
        </div>
        <div class="post-options">
            <button type="button" class="post-options-btn" aria-label="More options">
                <i class="fa-solid fa-ellipsis-h"></i>
            </button>
        </div>
    </div>

    <?php if ($content): ?>
        <div class="post-content">
            <?= nl2br(e($content)) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($images)): ?>
        <div class="post-images <?= count($images) > 1 ? 'post-images-grid' : '' ?>">
            <?php foreach (array_slice($images, 0, 4) as $index => $image): ?>
                <div class="post-image-item">
                    <?= webp_image($image['url'] ?? $image, 'Post image', 'post-img') ?>
                    <?php if ($index === 3 && count($images) > 4): ?>
                        <div class="post-images-more">+<?= count($images) - 4 ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($showActions): ?>
        <div class="post-stats">
            <?php if ($likesCount > 0): ?>
                <span class="post-likes-count">
                    <i class="fa-solid fa-heart"></i> <?= number_format($likesCount) ?>
                </span>
            <?php endif; ?>
            <?php if ($commentsCount > 0): ?>
                <a href="<?= e($postUrl) ?>" class="post-comments-count">
                    <?= number_format($commentsCount) ?> comment<?= $commentsCount !== 1 ? 's' : '' ?>
                </a>
            <?php endif; ?>
        </div>

        <div class="post-actions">
            <button type="button" class="post-action-btn like-btn <?= $isLiked ? 'liked' : '' ?>" data-post-id="<?= $id ?>">
                <i class="fa-<?= $isLiked ? 'solid' : 'regular' ?> fa-heart"></i>
                <span>Like</span>
            </button>
            <a href="<?= e($postUrl) ?>#comments" class="post-action-btn">
                <i class="fa-regular fa-comment"></i>
                <span>Comment</span>
            </a>
            <button type="button" class="post-action-btn share-btn" data-post-id="<?= $id ?>">
                <i class="fa-solid fa-share"></i>
                <span>Share</span>
            </button>
        </div>
    <?php endif; ?>
</article>
