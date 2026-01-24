<?php
/**
 * Shared Component: Post Card
 *
 * Reusable post display component for feed, profile, and other views
 * Date: 2026-01-11
 *
 * Features:
 * - Post content with image support
 * - Like, comment, share interactions
 * - User avatar and name with link to profile
 * - Timestamp with relative time
 * - Visibility badge (public/private/connections)
 * - Delete button for own posts
 * - Full ARIA accessibility
 *
 * Required Variables:
 * @var array $post - Post data with keys: id, user_id, content, image_url, likes_count, created_at, visibility
 * @var array $postAuthor - Author data with keys: id, first_name, last_name, avatar_url
 * @var int $currentUserId - Currently logged in user ID
 * @var bool $showActions - Whether to show like/comment/share buttons (default: true)
 */

// Validate required variables
if (!isset($post) || !isset($postAuthor)) {
    throw new Exception("post-card component requires \$post and \$postAuthor variables");
}

$currentUserId = $currentUserId ?? ($_SESSION['user_id'] ?? 0);
$showActions = $showActions ?? true;
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Calculate relative time
$timeAgo = \Nexus\Helpers\TimeHelper::timeAgo($post['created_at'] ?? 'now');
$isOwnPost = ($currentUserId == $post['user_id']);

// Check if user has liked this post
$hasLiked = false;
if ($currentUserId) {
    try {
        $likeCheck = \Nexus\Core\DatabaseWrapper::query(
            "SELECT id FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = ?",
            [$currentUserId, $post['id']]
        )->fetch();
        $hasLiked = !empty($likeCheck);
    } catch (\Exception $e) {
        // Likes table may not exist or query failed
    }
}

$likeClass = 'component-post-card__action-btn component-post-card__like-btn';
if ($hasLiked) $likeClass .= ' component-post-card__like-btn--liked';
?>

<article class="nexus-card component-post-card"
         data-post-id="<?= $post['id'] ?>"
         role="article"
         aria-label="Post by <?= htmlspecialchars($postAuthor['first_name'] . ' ' . $postAuthor['last_name']) ?>">

    <!-- Post Header -->
    <header class="component-post-card__header">
        <a href="<?= $basePath ?>/profile/<?= $postAuthor['id'] ?>"
           class="component-post-card__avatar-link"
           aria-label="View profile of <?= htmlspecialchars($postAuthor['first_name'] . ' ' . $postAuthor['last_name']) ?>">
            <?= webp_avatar($postAuthor['avatar_url'] ?: null, $postAuthor['first_name'] . ' ' . $postAuthor['last_name'], 48) ?>
        </a>

        <div class="component-post-card__author-info">
            <h3 class="component-post-card__author-name">
                <a href="<?= $basePath ?>/profile/<?= $postAuthor['id'] ?>"
                   class="component-post-card__author-link"
                   aria-label="<?= htmlspecialchars($postAuthor['first_name'] . ' ' . $postAuthor['last_name']) ?>'s profile">
                    <?= htmlspecialchars($postAuthor['first_name'] . ' ' . $postAuthor['last_name']) ?>
                </a>
            </h3>
            <div class="component-post-card__meta">
                <time datetime="<?= date('c', strtotime($post['created_at'])) ?>"
                      class="component-post-card__time"
                      aria-label="Posted <?= $timeAgo ?>">
                    <?= $timeAgo ?>
                </time>

                <?php if (!empty($post['visibility']) && $post['visibility'] !== 'public'): ?>
                <span class="component-post-card__visibility-badge"
                      aria-label="Visibility: <?= htmlspecialchars($post['visibility']) ?>">
                    <i class="fa-solid fa-<?= $post['visibility'] === 'private' ? 'lock' : 'user-group' ?>" aria-hidden="true"></i>
                    <?= ucfirst($post['visibility']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isOwnPost): ?>
        <button type="button"
                class="component-post-card__delete-btn"
                onclick="deletePost(<?= $post['id'] ?>)"
                aria-label="Delete this post"
                title="Delete post">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
        </button>
        <?php endif; ?>
    </header>

    <!-- Post Content -->
    <?php if (!empty($post['content'])): ?>
    <div class="component-post-card__content"
         role="region"
         aria-label="Post content">
        <?= nl2br(htmlspecialchars($post['content'])) ?>
    </div>
    <?php endif; ?>

    <!-- Post Image -->
    <?php if (!empty($post['image_url'])): ?>
    <figure class="component-post-card__image">
        <?= webp_image($post['image_url'], 'Post image', 'component-post-card__image-element') ?>
    </figure>
    <?php endif; ?>

    <?php if ($showActions): ?>
    <!-- Post Actions -->
    <footer class="component-post-card__actions"
            role="group"
            aria-label="Post interactions">

        <!-- Like Button -->
        <button type="button"
                class="<?= $likeClass ?>"
                data-post-id="<?= $post['id'] ?>"
                onclick="toggleLike(this, 'post', <?= $post['id'] ?>)"
                aria-label="<?= $hasLiked ? 'Unlike' : 'Like' ?> this post. Currently <?= $post['likes_count'] ?? 0 ?> likes"
                aria-pressed="<?= $hasLiked ? 'true' : 'false' ?>">
            <i class="fa-<?= $hasLiked ? 'solid' : 'regular' ?> fa-heart" aria-hidden="true"></i>
            <span class="like-count" aria-live="polite"><?= number_format($post['likes_count'] ?? 0) ?></span>
        </button>

        <!-- Comment Button -->
        <button type="button"
                class="component-post-card__action-btn component-post-card__comment-btn"
                onclick="toggleComments(<?= $post['id'] ?>)"
                aria-label="View comments for this post"
                aria-expanded="false"
                aria-controls="comments-<?= $post['id'] ?>">
            <i class="fa-regular fa-comment" aria-hidden="true"></i>
            <span>Comment</span>
        </button>

        <!-- Share Button -->
        <button type="button"
                class="component-post-card__action-btn component-post-card__share-btn"
                onclick="sharePost(<?= $post['id'] ?>)"
                aria-label="Share this post">
            <i class="fa-solid fa-share" aria-hidden="true"></i>
            <span>Share</span>
        </button>
    </footer>

    <!-- Comments Section (Initially Hidden) -->
    <div id="comments-<?= $post['id'] ?>"
         class="component-post-card__comments component-hidden"
         role="region"
         aria-label="Comments for this post"
         aria-hidden="true">
        <!-- Comments will be loaded dynamically -->
    </div>
    <?php endif; ?>
</article>

<!-- Post Card CSS now loaded via header.php: post-card.min.css -->
