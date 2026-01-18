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
?>

<article class="nexus-card post-card"
         data-post-id="<?= $post['id'] ?>"
         role="article"
         aria-label="Post by <?= htmlspecialchars($postAuthor['first_name'] . ' ' . $postAuthor['last_name']) ?>">

    <!-- Post Header -->
    <header class="post-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
        <a href="<?= $basePath ?>/profile/<?= $postAuthor['id'] ?>"
           aria-label="View profile of <?= htmlspecialchars($postAuthor['first_name'] . ' ' . $postAuthor['last_name']) ?>">
            <?= webp_avatar($postAuthor['avatar_url'] ?: null, $postAuthor['first_name'] . ' ' . $postAuthor['last_name'], 48) ?>
        </a>

        <div style="flex: 1;">
            <h3 style="margin: 0; font-size: 1rem; font-weight: 600;">
                <a href="<?= $basePath ?>/profile/<?= $postAuthor['id'] ?>"
                   style="color: var(--htb-text-main); text-decoration: none;"
                   aria-label="<?= htmlspecialchars($postAuthor['first_name'] . ' ' . $postAuthor['last_name']) ?>'s profile">
                    <?= htmlspecialchars($postAuthor['first_name'] . ' ' . $postAuthor['last_name']) ?>
                </a>
            </h3>
            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                <time datetime="<?= date('c', strtotime($post['created_at'])) ?>"
                      style="font-size: 0.85rem; color: var(--htb-text-muted);"
                      aria-label="Posted <?= $timeAgo ?>">
                    <?= $timeAgo ?>
                </time>

                <?php if (!empty($post['visibility']) && $post['visibility'] !== 'public'): ?>
                <span class="visibility-badge"
                      style="font-size: 0.75rem; padding: 2px 8px; border-radius: 12px; background: rgba(99, 102, 241, 0.1); color: #6366f1;"
                      aria-label="Visibility: <?= htmlspecialchars($post['visibility']) ?>">
                    <i class="fa-solid fa-<?= $post['visibility'] === 'private' ? 'lock' : 'user-group' ?>" aria-hidden="true"></i>
                    <?= ucfirst($post['visibility']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isOwnPost): ?>
        <button type="button"
                class="post-delete-btn"
                onclick="deletePost(<?= $post['id'] ?>)"
                style="padding: 8px; background: transparent; border: none; color: var(--htb-text-muted); cursor: pointer; border-radius: 8px; transition: all 0.2s;"
                aria-label="Delete this post"
                title="Delete post">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
        </button>
        <?php endif; ?>
    </header>

    <!-- Post Content -->
    <?php if (!empty($post['content'])): ?>
    <div class="post-content"
         style="margin-bottom: 16px; line-height: 1.6; color: var(--htb-text-main); word-wrap: break-word;"
         role="region"
         aria-label="Post content">
        <?= nl2br(htmlspecialchars($post['content'])) ?>
    </div>
    <?php endif; ?>

    <!-- Post Image -->
    <?php if (!empty($post['image_url'])): ?>
    <figure class="post-image" style="margin: 16px 0;">
        <?= webp_image($post['image_url'], 'Post image', '', ['style' => 'width: 100%; max-height: 500px; object-fit: cover; border-radius: var(--htb-radius);']) ?>
    </figure>
    <?php endif; ?>

    <?php if ($showActions): ?>
    <!-- Post Actions -->
    <footer class="post-actions"
            style="display: flex; align-items: center; gap: 16px; padding-top: 16px; border-top: 1px solid var(--htb-border-color);"
            role="group"
            aria-label="Post interactions">

        <!-- Like Button -->
        <button type="button"
                class="post-action-btn like-btn <?= $hasLiked ? 'active' : '' ?>"
                data-post-id="<?= $post['id'] ?>"
                onclick="toggleLike(this, 'post', <?= $post['id'] ?>)"
                style="display: flex; align-items: center; gap: 6px; padding: 8px 12px; background: transparent; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s; color: <?= $hasLiked ? '#ef4444' : 'var(--htb-text-muted)' ?>;"
                aria-label="<?= $hasLiked ? 'Unlike' : 'Like' ?> this post. Currently <?= $post['likes_count'] ?? 0 ?> likes"
                aria-pressed="<?= $hasLiked ? 'true' : 'false' ?>">
            <i class="fa-<?= $hasLiked ? 'solid' : 'regular' ?> fa-heart" aria-hidden="true"></i>
            <span class="like-count" aria-live="polite"><?= number_format($post['likes_count'] ?? 0) ?></span>
        </button>

        <!-- Comment Button -->
        <button type="button"
                class="post-action-btn comment-btn"
                onclick="toggleComments(<?= $post['id'] ?>)"
                style="display: flex; align-items: center; gap: 6px; padding: 8px 12px; background: transparent; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s; color: var(--htb-text-muted);"
                aria-label="View comments for this post"
                aria-expanded="false"
                aria-controls="comments-<?= $post['id'] ?>">
            <i class="fa-regular fa-comment" aria-hidden="true"></i>
            <span>Comment</span>
        </button>

        <!-- Share Button -->
        <button type="button"
                class="post-action-btn share-btn"
                onclick="sharePost(<?= $post['id'] ?>)"
                style="display: flex; align-items: center; gap: 6px; padding: 8px 12px; background: transparent; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s; color: var(--htb-text-muted);"
                aria-label="Share this post">
            <i class="fa-solid fa-share" aria-hidden="true"></i>
            <span>Share</span>
        </button>
    </footer>

    <!-- Comments Section (Initially Hidden) -->
    <div id="comments-<?= $post['id'] ?>"
         class="post-comments"
         style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--htb-border-color);"
         role="region"
         aria-label="Comments for this post"
         aria-hidden="true">
        <!-- Comments will be loaded dynamically -->
    </div>
    <?php endif; ?>
</article>

<style>
.post-card {
    padding: 20px;
    margin-bottom: 20px;
}

.post-action-btn:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

.post-action-btn.like-btn.active {
    color: #ef4444;
}

.post-action-btn.like-btn:hover {
    color: #ef4444;
}

.post-delete-btn:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

@media (max-width: 600px) {
    .post-card {
        padding: 16px;
    }

    .post-action-btn span:not(.like-count) {
        display: none;
    }
}
</style>
