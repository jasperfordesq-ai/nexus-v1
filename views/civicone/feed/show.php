<?php
/**
 * Modern Feed Post Detail - Gold Standard v2.0
 * Full Master Platform Social Media Module Integration
 * Holographic Glassmorphism Design System
 */

$userId = $_SESSION['user_id'] ?? 0;
$isLoggedIn = !empty($userId);
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
$userName = $_SESSION['user_name'] ?? 'Guest';
$tenantId = $_SESSION['current_tenant_id'] ?? (\Nexus\Core\TenantContext::getId() ?? 1);
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Social interaction target
$socialTargetType = 'post';
$socialTargetId = $post['id'];

require __DIR__ . '/../../layouts/header.php';
?>

<!-- Feed Show CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-feed-show.min.css">

<div class="post-detail-bg"></div>

<div class="post-detail-container">

    <!-- Back Button -->
    <a href="<?= $basePath ?>/" class="glass-button">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to Feed</span>
    </a>

    <!-- Post Card -->
    <div class="glass-card">

        <!-- Header -->
        <div class="post-header">
            <div class="post-author-info">
                <a href="<?= $basePath ?>/profile/<?= $post['user_id'] ?>">
                    <img src="<?= !empty($post['author_avatar']) ? htmlspecialchars($post['author_avatar']) : '/assets/img/defaults/default_avatar.webp' ?>" loading="lazy"
                         alt="<?= htmlspecialchars($post['author_name']) ?>"
                         class="post-avatar">
                </a>
                <div class="post-meta">
                    <div class="post-author-name">
                        <?= htmlspecialchars($post['author_name']) ?>
                    </div>
                    <div class="post-timestamp">
                        <i class="fa-regular fa-clock"></i>
                        <?= date('M j, Y \a\t g:i a', strtotime($post['created_at'])) ?>
                        <?php if (isset($post['visibility'])): ?>
                            <span class="post-visibility">
                                <?= $post['visibility'] === 'public' ? '<i class="fa-solid fa-globe"></i> Public' : '<i class="fa-solid fa-lock"></i> Private' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <button class="post-menu-btn" onclick="showPostMenu(<?= $post['id'] ?>)">
                <i class="fa-solid fa-ellipsis"></i>
            </button>
        </div>

        <!-- Content -->
        <div class="post-content">
            <?php
            $escapedContent = htmlspecialchars($post['content']);
            $content = preg_replace_callback('/(https?:\/\/[^\s]+)/', function($m) {
                $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars($url) . '</a>';
            }, $escapedContent);
            echo nl2br($content);
            ?>
        </div>

        <!-- Image -->
        <?php if (!empty($post['image_url'])): ?>
            <img src="<?= htmlspecialchars($post['image_url']) ?>" loading="lazy" alt="Post image" class="post-image">
        <?php endif; ?>

        <!-- Stats Bar -->
        <div class="post-stats">
            <div class="stat-item" onclick="showLikers('<?= $socialTargetType ?>', <?= $socialTargetId ?>)">
                <i class="fa-solid fa-heart"></i>
                <span id="likes-count-<?= $socialTargetId ?>"><?= $post['likes_count'] ?? 0 ?></span>
                <span>Likes</span>
            </div>
            <div class="stat-item" onclick="document.getElementById('comment-input').focus()">
                <i class="fa-solid fa-comment"></i>
                <span id="comments-count-<?= $socialTargetId ?>">0</span>
                <span>Comments</span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="post-actions">
            <button class="action-btn <?= ($post['is_liked'] ?? 0) ? 'liked' : '' ?>"
                    onclick="toggleLike(this, '<?= $socialTargetType ?>', <?= $socialTargetId ?>)">
                <i class="<?= ($post['is_liked'] ?? 0) ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                <span>Like</span>
            </button>
            <button class="action-btn" onclick="toggleCommentSection('<?= $socialTargetType ?>', <?= $socialTargetId ?>)">
                <i class="fa-regular fa-comment"></i>
                <span>Comment</span>
            </button>
            <button class="action-btn" onclick="repostToFeed('<?= $socialTargetType ?>', <?= $socialTargetId ?>, '<?= addslashes($post['author_name']) ?>')">
                <i class="fa-regular fa-share-from-square"></i>
                <span>Share</span>
            </button>
        </div>

        <!-- Comments Section -->
        <div id="comments-section-<?= $socialTargetType ?>-<?= $socialTargetId ?>" class="comments-section">
            <div class="comments-header">
                <i class="fa-solid fa-comments"></i>
                <span>Comments</span>
            </div>

            <!-- Comment Compose -->
            <?php if ($isLoggedIn): ?>
            <div class="comment-compose">
                <img src="<?= htmlspecialchars($userAvatar) ?>" loading="lazy" alt="Your avatar">
                <div class="comment-input-wrapper">
                    <input type="text"
                           id="comment-input"
                           class="comment-input"
                           placeholder="Write a comment..."
                           onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); submitComment(this, '<?= $socialTargetType ?>', <?= $socialTargetId ?>); }">
                    <button class="comment-send-btn" onclick="submitComment(document.getElementById('comment-input'), '<?= $socialTargetType ?>', <?= $socialTargetId ?>)">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Comments List -->
            <div class="comments-list">
                <div class="loading-skeleton skeleton-80"></div>
            </div>
        </div>

    </div>

</div>

<!-- Master Platform Social Media Module -->
<!-- Feed Show JavaScript -->
<script src="<?= NexusCoreTenantContext::getBasePath() ?>/assets/js/civicone-feed-show.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
