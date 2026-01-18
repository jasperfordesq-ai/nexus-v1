<?php
// CivicOne View: User Profile - Enhanced with Social Features
// =========================================================

if (session_status() === PHP_SESSION_NONE) session_start();

$currentUserId = $_SESSION['user_id'] ?? 0;
$targetUserId = $user['id'] ?? 0;
$isOwner = ($currentUserId == $targetUserId);
$tenantId = $_SESSION['current_tenant_id'] ?? 1;
$isLoggedIn = !empty($currentUserId);

// ---------------------------------------------------------
// AJAX HANDLERS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    if (!$isLoggedIn) {
        echo json_encode(['error' => 'Login required']);
        exit;
    }

    $targetType = $_POST['target_type'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);

    try {
        $pdo = \Nexus\Core\Database::getInstance();

        // TOGGLE LIKE
        if ($_POST['action'] === 'toggle_like') {
            $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?");
            $stmt->execute([$currentUserId, $targetType, $targetId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $pdo->prepare("DELETE FROM likes WHERE id = ?")->execute([$existing['id']]);
                if ($targetType === 'post') {
                    $pdo->prepare("UPDATE feed_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?")->execute([$targetId]);
                }
                $action = 'unliked';
            } else {
                $pdo->prepare("INSERT INTO likes (user_id, target_type, target_id, tenant_id) VALUES (?, ?, ?, ?)")
                    ->execute([$currentUserId, $targetType, $targetId, $tenantId]);
                if ($targetType === 'post') {
                    $pdo->prepare("UPDATE feed_posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$targetId]);
                }
                $action = 'liked';

                // Notify content owner
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                    if ($contentOwnerId && $contentOwnerId != $currentUserId) {
                        \Nexus\Services\SocialNotificationService::notifyLike($contentOwnerId, $currentUserId, $targetType, $targetId, '');
                    }
                }
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?");
            $stmt->execute([$targetType, $targetId]);
            $count = $stmt->fetch()['cnt'] ?? 0;

            echo json_encode(['status' => $action, 'likes_count' => (int)$count]);
        }

        // SUBMIT COMMENT
        elseif ($_POST['action'] === 'submit_comment') {
            $content = trim($_POST['content'] ?? '');
            if (empty($content)) {
                echo json_encode(['error' => 'Comment cannot be empty']);
                exit;
            }

            if (class_exists('\Nexus\Services\CommentService')) {
                $result = \Nexus\Services\CommentService::addComment($currentUserId, $tenantId, $targetType, $targetId, $content);

                if ($result['status'] === 'success' && class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                    if ($contentOwnerId && $contentOwnerId != $currentUserId) {
                        \Nexus\Services\SocialNotificationService::notifyComment($contentOwnerId, $currentUserId, $targetType, $targetId, $content);
                    }
                }
                echo json_encode($result);
            } else {
                $pdo->prepare("INSERT INTO comments (user_id, tenant_id, target_type, target_id, content, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
                    ->execute([$currentUserId, $tenantId, $targetType, $targetId, $content]);
                echo json_encode(['status' => 'success', 'comment' => [
                    'author_name' => $_SESSION['user_name'] ?? 'Me',
                    'author_avatar' => $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                    'content' => $content
                ]]);
            }
        }

        // FETCH COMMENTS (Enhanced with nested replies and reactions)
        elseif ($_POST['action'] === 'fetch_comments') {
            if (class_exists('\Nexus\Services\CommentService')) {
                $comments = \Nexus\Services\CommentService::fetchComments($targetType, $targetId, $currentUserId);
                echo json_encode([
                    'status' => 'success',
                    'comments' => $comments,
                    'available_reactions' => \Nexus\Services\CommentService::getAvailableReactions()
                ]);
            } else {
                $stmt = $pdo->prepare("SELECT c.content, c.created_at,
                    COALESCE(u.name, 'Unknown') as author_name,
                    COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.webp') as author_avatar
                    FROM comments c LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.target_type = ? AND c.target_id = ? ORDER BY c.created_at ASC");
                $stmt->execute([$targetType, $targetId]);
                echo json_encode(['status' => 'success', 'comments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
        }

        // DELETE COMMENT
        elseif ($_POST['action'] === 'delete_comment') {
            $commentId = (int)($_POST['comment_id'] ?? 0);
            $isSuperAdmin = !empty($_SESSION['is_super_admin']) || ($_SESSION['user_role'] ?? '') === 'admin';
            if (class_exists('\Nexus\Services\CommentService')) {
                echo json_encode(\Nexus\Services\CommentService::deleteComment($commentId, $currentUserId, $isSuperAdmin));
            } else {
                echo json_encode(['error' => 'CommentService not available']);
            }
        }

        // EDIT COMMENT
        elseif ($_POST['action'] === 'edit_comment') {
            $commentId = (int)($_POST['comment_id'] ?? 0);
            $newContent = $_POST['content'] ?? '';
            if (class_exists('\Nexus\Services\CommentService')) {
                echo json_encode(\Nexus\Services\CommentService::editComment($commentId, $currentUserId, $newContent));
            } else {
                echo json_encode(['error' => 'CommentService not available']);
            }
        }

        // REPLY TO COMMENT
        elseif ($_POST['action'] === 'reply_comment') {
            $parentId = (int)($_POST['parent_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            if (class_exists('\Nexus\Services\CommentService')) {
                $result = \Nexus\Services\CommentService::addComment($currentUserId, $tenantId, $targetType, $targetId, $content, $parentId);

                if ($result['status'] === 'success' && $result['is_reply'] && class_exists('\Nexus\Services\SocialNotificationService')) {
                    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
                    $stmt->execute([$parentId]);
                    $parent = $stmt->fetch();
                    if ($parent && $parent['user_id'] != $currentUserId) {
                        \Nexus\Services\SocialNotificationService::notifyComment(
                            $parent['user_id'], $currentUserId, $targetType, $targetId, "replied to your comment"
                        );
                    }
                }
                echo json_encode($result);
            } else {
                echo json_encode(['error' => 'CommentService not available']);
            }
        }

        // TOGGLE REACTION ON COMMENT
        elseif ($_POST['action'] === 'toggle_reaction') {
            $commentId = (int)($_POST['comment_id'] ?? 0);
            $emoji = $_POST['emoji'] ?? '';
            if (class_exists('\Nexus\Services\CommentService')) {
                echo json_encode(\Nexus\Services\CommentService::toggleReaction($currentUserId, $tenantId, $commentId, $emoji));
            } else {
                echo json_encode(['error' => 'CommentService not available']);
            }
        }

        // SEARCH USERS FOR @MENTION
        elseif ($_POST['action'] === 'search_users') {
            $query = trim($_POST['query'] ?? '');
            if (class_exists('\Nexus\Services\CommentService') && strlen($query) >= 1) {
                echo json_encode(['status' => 'success', 'users' => \Nexus\Services\CommentService::searchUsersForMention($query, $tenantId)]);
            } else {
                echo json_encode(['status' => 'success', 'users' => []]);
            }
        }

        // DELETE POST
        elseif ($_POST['action'] === 'delete_post') {
            if (($_SESSION['user_role'] ?? '') === 'admin' || $isOwner) {
                $pdo->prepare("DELETE FROM feed_posts WHERE id = ?")->execute([$targetId]);
                echo json_encode(['status' => 'deleted']);
            } else {
                echo json_encode(['error' => 'Unauthorized']);
            }
        }

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ---------------------------------------------------------
// FETCH USER'S POSTS
// ---------------------------------------------------------
$posts = [];
try {
    $pdo = \Nexus\Core\Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT fp.id, fp.user_id, fp.content, fp.image_url, fp.likes_count, fp.created_at,
               COALESCE(u.name, u.first_name, 'Unknown') as author_name,
               COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.webp') as author_avatar,
               (SELECT COUNT(*) FROM comments c WHERE c.target_type = 'post' AND c.target_id = fp.id) as comments_count
        FROM feed_posts fp
        LEFT JOIN users u ON fp.user_id = u.id
        WHERE fp.user_id = ? AND fp.tenant_id = ?
        ORDER BY fp.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$targetUserId, $tenantId]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if current user has liked each post
    foreach ($posts as &$post) {
        $post['is_liked'] = false;
        if ($isLoggedIn) {
            $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = ?");
            $stmt->execute([$currentUserId, $post['id']]);
            $post['is_liked'] = (bool)$stmt->fetch();
        }
    }
    unset($post);
} catch (Exception $e) {
    // Silently fail
}

// Calculate online status for this user
$profileLastActiveAt = $user['last_active_at'] ?? null;
$isProfileOnline = $profileLastActiveAt && (strtotime($profileLastActiveAt) > strtotime('-5 minutes'));

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<style>
/* CivicOne Profile - Enhanced Social Features */
.civic-profile-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 30px;
}

@media (max-width: 768px) {
    .civic-profile-grid {
        grid-template-columns: 1fr;
    }
}

.civic-profile-card {
    background: #fff;
    border: 2px solid #0B0C0C;
    padding: 25px;
    text-align: center;
}

.civic-profile-avatar-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 15px;
}

.civic-profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #0B0C0C;
}

.civic-online-indicator {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 20px;
    height: 20px;
    background: #10b981;
    border: 3px solid #fff;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.4);
    animation: civic-pulse-online 2s infinite;
}

@keyframes civic-pulse-online {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.85; transform: scale(1.1); }
}

.civic-profile-name {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.civic-profile-location {
    color: #505A5F;
    font-size: 0.9rem;
    margin-bottom: 20px;
}

.civic-profile-stats {
    background: #F3F4F6;
    padding: 15px;
    margin-bottom: 20px;
}

.civic-profile-balance {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--civic-brand, #0B0C0C);
}

.civic-post-card {
    background: #fff;
    border: 2px solid #0B0C0C;
    margin-bottom: 20px;
    transition: transform 0.2s ease;
}

.civic-post-card:hover {
    transform: translateY(-2px);
    box-shadow: 4px 4px 0 #0B0C0C;
}

.civic-post-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid #E5E7EB;
}

.civic-avatar-sm {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #0B0C0C;
}

.civic-post-content {
    padding: 16px;
    font-size: 1rem;
    line-height: 1.6;
    color: #0B0C0C;
}

.civic-post-image {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    border-top: 1px solid #E5E7EB;
    border-bottom: 1px solid #E5E7EB;
}

.civic-post-actions {
    display: flex;
    gap: 0;
    border-top: 1px solid #E5E7EB;
}

.civic-action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: transparent;
    border: none;
    border-right: 1px solid #E5E7EB;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    color: #505A5F;
    transition: all 0.2s ease;
}

.civic-action-btn:last-child {
    border-right: none;
}

.civic-action-btn:hover {
    background: #F3F4F6;
}

.civic-action-btn.liked {
    color: #D4351C;
    background: rgba(212, 53, 28, 0.1);
}

/* Comments Section */
.civic-comments-section {
    display: none;
    border-top: 2px solid #0B0C0C;
    background: #F8F8F8;
    padding: 16px;
}

.civic-comment {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
    padding: 12px;
    background: #fff;
    border: 1px solid #E5E7EB;
}

.civic-comment-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.civic-comment-bubble {
    flex: 1;
}

.civic-comment-author {
    font-weight: 700;
    font-size: 0.85rem;
    color: #0B0C0C;
    margin-bottom: 4px;
}

.civic-comment-text {
    font-size: 0.9rem;
    color: #0B0C0C;
    line-height: 1.5;
}

.civic-comment-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 6px;
    font-size: 0.75rem;
    color: #505A5F;
}

.civic-comment-meta span {
    cursor: pointer;
}

.civic-comment-meta span:hover {
    text-decoration: underline;
}

/* Reactions */
.civic-reactions {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.civic-reaction {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    font-size: 0.8rem;
    border: 1px solid #E5E7EB;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
}

.civic-reaction:hover {
    background: #F3F4F6;
}

.civic-reaction.active {
    background: rgba(212, 53, 28, 0.1);
    border-color: #D4351C;
}

.civic-reaction-picker {
    position: relative;
    display: inline-block;
}

.civic-reaction-picker-menu {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 0;
    background: #fff;
    border: 2px solid #0B0C0C;
    padding: 8px;
    white-space: nowrap;
    z-index: 100;
    box-shadow: 4px 4px 0 rgba(0,0,0,0.1);
}

.civic-reaction-picker-menu span {
    font-size: 1.3rem;
    cursor: pointer;
    padding: 4px;
    transition: transform 0.1s;
}

.civic-reaction-picker-menu span:hover {
    transform: scale(1.3);
}

/* Reply Form */
.civic-reply-form {
    display: none;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #E5E7EB;
}

.civic-reply-input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #0B0C0C;
    font-size: 0.85rem;
}

/* Comment Input */
.civic-comment-form {
    display: flex;
    gap: 10px;
    margin-top: 12px;
}

.civic-comment-input {
    flex: 1;
    padding: 10px 14px;
    border: 2px solid #0B0C0C;
    font-size: 0.9rem;
}

.civic-comment-submit {
    padding: 10px 20px;
    background: #0B0C0C;
    color: #fff;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.civic-comment-submit:hover {
    background: #D4351C;
}

/* Toast - positioned above bottom nav */
.civic-toast {
    position: fixed;
    bottom: calc(90px + env(safe-area-inset-bottom, 0px));
    left: 50%;
    transform: translateX(-50%);
    background: #0B0C0C;
    color: #fff;
    padding: 12px 24px;
    font-weight: 600;
    z-index: 10000;
    opacity: 0;
    transition: opacity 0.3s;
    max-width: calc(100% - 32px);
    text-align: center;
    border-radius: 8px;
}

.civic-toast.show {
    opacity: 1;
}

/* Composer */
.civic-composer {
    background: #fff;
    border: 2px solid #0B0C0C;
    margin-bottom: 20px;
    padding: 20px;
}

.civic-composer textarea {
    width: 100%;
    min-height: 80px;
    padding: 12px;
    border: 1px solid #E5E7EB;
    font-size: 1rem;
    resize: vertical;
    margin-bottom: 12px;
}

.civic-composer-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Mention highlight */
.civic-mention {
    color: #D4351C;
    font-weight: 600;
}
</style>

<div class="civic-container">
    <div class="civic-profile-grid">

        <!-- Sidebar: User Info -->
        <div>
            <div class="civic-profile-card">
                <div class="civic-profile-avatar-wrapper">
                    <img src="<?= htmlspecialchars($user['avatar_url'] ?? '/assets/images/default-avatar.svg') ?>" alt="Profile Photo" class="civic-profile-avatar">
                    <?php if ($isProfileOnline): ?>
                    <span class="civic-online-indicator" title="Active now"></span>
                    <?php endif; ?>
                </div>

                <div class="civic-profile-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="civic-profile-location">
                    <?= htmlspecialchars($user['location'] ?? 'Location Unknown') ?>
                </div>

                <div class="civic-profile-stats">
                    <span style="display: block; font-size: 14px; text-transform: uppercase; margin-bottom: 5px;">Balance</span>
                    <div class="civic-profile-balance"><?= number_format($user['balance'] ?? 0) ?> Credits</div>
                    <div style="margin-top: 10px; font-size: 14px; color: #505A5F;">
                        Exchanges: <strong><?= $exchangesCount ?? 0 ?></strong>
                    </div>
                </div>

                <?php if ($isLoggedIn && !$isOwner): ?>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/messages/create?to=<?= $user['id'] ?>" class="civic-btn" style="flex: 1; padding: 10px 5px; font-size: 0.9rem;">Message</a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet?to=<?= $user['id'] ?>" class="civic-btn" style="flex: 1; padding: 10px 5px; font-size: 0.9rem; background: #059669;">Pay</a>
                </div>

                <?php if (!$connection): ?>
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/connections/add" method="POST">
                        <input type="hidden" name="receiver_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="civic-btn" style="width: 100%; background: #505A5F; padding: 10px;">Add Friend</button>
                    </form>
                <?php elseif ($connection['status'] === 'pending' && $connection['requester_id'] == $currentUserId): ?>
                    <button disabled class="civic-btn" style="width: 100%; background: #E5E7EB; color: #9CA3AF; cursor: not-allowed; padding: 10px;">Request Sent</button>
                <?php elseif ($connection['status'] === 'pending' && $connection['receiver_id'] == $currentUserId): ?>
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/connections/accept" method="POST">
                        <input type="hidden" name="connection_id" value="<?= $connection['id'] ?>">
                        <button type="submit" class="civic-btn" style="width: 100%; padding: 10px;">Accept Request</button>
                    </form>
                <?php elseif ($connection['status'] === 'accepted'): ?>
                    <div style="margin-top: 10px; color: #059669; font-weight: bold;">✓ Connected</div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($isOwner): ?>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/edit" class="civic-btn" style="width: 100%; background: #505A5F;">Edit Profile</a>
                <?php endif; ?>
            </div>

            <!-- About Section -->
            <div class="civic-profile-card" style="margin-top: 20px;">
                <h3 style="text-align: left; border-bottom: 2px solid #0B0C0C; padding-bottom: 10px; margin-bottom: 15px;">About</h3>
                <?php if (!empty($user['bio'])): ?>
                    <div style="text-align: left;"><?= $user['bio'] ?></div>
                <?php else: ?>
                    <p style="text-align: left; color: #505A5F; font-style: italic;">No bio yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content: Posts & Reviews -->
        <div>
            <!-- Post Composer (Owner only) -->
            <?php if ($isOwner): ?>
            <div class="civic-composer">
                <form method="POST" enctype="multipart/form-data">
                    <textarea name="content" placeholder="What's on your mind?" required></textarea>
                    <div class="civic-composer-actions">
                        <label style="cursor: pointer; padding: 10px 16px; background: #F3F4F6; font-weight: 600;">
                            <i class="fa-solid fa-image"></i> Photo
                            <input type="file" name="image" accept="image/*" style="display: none;">
                        </label>
                        <button type="submit" class="civic-comment-submit">Post</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Posts Section -->
            <h3 style="border-bottom: 2px solid #0B0C0C; padding-bottom: 10px; margin-bottom: 20px;">
                <?= $isOwner ? 'Your Posts' : htmlspecialchars($user['name']) . "'s Posts" ?>
            </h3>

            <?php if (empty($posts)): ?>
                <div class="civic-card" style="text-align: center; padding: 40px; background: #fff; border: 2px solid #0B0C0C;">
                    <p style="color: #505A5F;">No posts yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="civic-post-card" id="post-<?= $post['id'] ?>">
                        <!-- Post Header -->
                        <div class="civic-post-header">
                            <img src="<?= htmlspecialchars($post['author_avatar']) ?>" alt="" class="civic-avatar-sm">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: #0B0C0C;">
                                    <?= htmlspecialchars($post['author_name']) ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #505A5F;">
                                    <?= date('M j, g:i a', strtotime($post['created_at'])) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Post Content -->
                        <div class="civic-post-content">
                            <?= nl2br(htmlspecialchars($post['content'])) ?>
                        </div>

                        <?php if (!empty($post['image_url'])): ?>
                            <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="" class="civic-post-image">
                        <?php endif; ?>

                        <!-- Post Actions -->
                        <div class="civic-post-actions">
                            <button class="civic-action-btn <?= $post['is_liked'] ? 'liked' : '' ?>"
                                    onclick="toggleLike('post', <?= $post['id'] ?>, this)">
                                <i class="<?= $post['is_liked'] ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                                <span class="like-count"><?= (int)$post['likes_count'] ?></span> Like
                            </button>
                            <button class="civic-action-btn" onclick="toggleComments('post', <?= $post['id'] ?>)">
                                <i class="fa-regular fa-comment"></i>
                                <span class="comment-count"><?= (int)$post['comments_count'] ?></span> Comment
                            </button>
                        </div>

                        <!-- Comments Section -->
                        <div class="civic-comments-section" id="comments-section-post-<?= $post['id'] ?>">
                            <div class="comments-list">
                                <div style="color: #505A5F; text-align: center; padding: 20px;">Click to load comments</div>
                            </div>

                            <?php if ($isLoggedIn): ?>
                            <div class="civic-comment-form">
                                <input type="text" class="civic-comment-input" placeholder="Write a comment..."
                                       onkeydown="if(event.key === 'Enter') submitComment(this, 'post', <?= $post['id'] ?>)">
                                <button class="civic-comment-submit" onclick="submitComment(this.previousElementSibling, 'post', <?= $post['id'] ?>)">Post</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Reviews Section -->
            <h3 style="margin-top: 40px; border-bottom: 2px solid #0B0C0C; padding-bottom: 10px; margin-bottom: 20px;">Reviews</h3>

            <?php if (empty($reviews)): ?>
                <div class="civic-card" style="text-align: center; padding: 40px; background: #fff; border: 2px solid #0B0C0C;">
                    <p style="color: #505A5F;">No reviews yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="civic-card" style="background: #fff; border: 2px solid #0B0C0C; padding: 20px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <strong><?= htmlspecialchars($review['reviewer_name'] ?? 'Anonymous') ?></strong>
                            <span style="color: #505A5F; font-size: 14px;"><?= $review['created_at'] ?></span>
                        </div>
                        <p><?= nl2br(htmlspecialchars($review['content'] ?? '')) ?></p>
                        <div style="color: #F59E0B; font-size: 1.2rem;">
                            <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Toast Notification -->
<div class="civic-toast" id="civic-toast"></div>

<script>
const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
let availableReactions = [];
let currentTargetType = '';
let currentTargetId = 0;

function showToast(message) {
    const toast = document.getElementById('civic-toast');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function toggleLike(type, id, btn) {
    if (!IS_LOGGED_IN) {
        alert('Please log in to like posts.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'toggle_like');
    formData.append('target_type', type);
    formData.append('target_id', id);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }

            const countEl = btn.querySelector('.like-count');
            const icon = btn.querySelector('i');
            countEl.textContent = data.likes_count;

            if (data.status === 'liked') {
                btn.classList.add('liked');
                icon.className = 'fa-solid fa-heart';
            } else {
                btn.classList.remove('liked');
                icon.className = 'fa-regular fa-heart';
            }
        });
}

function toggleComments(type, id) {
    const section = document.getElementById(`comments-section-${type}-${id}`);
    if (!section) return;

    if (section.style.display === 'none' || !section.style.display) {
        section.style.display = 'block';
        fetchComments(type, id);
    } else {
        section.style.display = 'none';
    }
}

function fetchComments(type, id) {
    const section = document.getElementById(`comments-section-${type}-${id}`);
    const list = section.querySelector('.comments-list');
    list.innerHTML = '<div style="color: #505A5F; text-align: center; padding: 20px;">Loading...</div>';

    currentTargetType = type;
    currentTargetId = id;

    const formData = new FormData();
    formData.append('action', 'fetch_comments');
    formData.append('target_type', type);
    formData.append('target_id', id);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.available_reactions) {
                availableReactions = data.available_reactions;
            }
            if (data.status === 'success' && data.comments && data.comments.length > 0) {
                list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');
            } else {
                list.innerHTML = '<div style="color: #505A5F; text-align: center; padding: 20px;">No comments yet. Be the first!</div>';
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            list.innerHTML = '<div style="color: #D4351C; text-align: center; padding: 20px;">Error loading comments</div>';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderComment(c, depth) {
    const marginLeft = depth * 40;
    const isEdited = c.is_edited ? '<span style="font-size: 0.7rem; color: #505A5F;"> (edited)</span>' : '';
    const ownerActions = c.is_owner ? `
        <span onclick="editComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'").replace(/\n/g, "\\n")}')" style="cursor: pointer;" title="Edit">✏️</span>
        <span onclick="deleteComment(${c.id})" style="cursor: pointer; margin-left: 5px;" title="Delete">🗑️</span>
    ` : '';

    // Reactions
    const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
        const isActive = (c.user_reactions || []).includes(emoji);
        return `<span class="civic-reaction ${isActive ? 'active' : ''}" onclick="toggleReaction(${c.id}, '${emoji}')">${emoji} ${count}</span>`;
    }).join('');

    // Reaction picker
    const reactionPicker = IS_LOGGED_IN ? `
        <div class="civic-reaction-picker">
            <span class="civic-reaction" onclick="toggleReactionPicker(${c.id})">+</span>
            <div class="civic-reaction-picker-menu" id="picker-${c.id}">
                ${availableReactions.map(e => `<span onclick="toggleReaction(${c.id}, '${e}')">${e}</span>`).join('')}
            </div>
        </div>
    ` : '';

    const replyBtn = IS_LOGGED_IN ? `<span onclick="showReplyForm(${c.id})">Reply</span>` : '';

    const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

    // Highlight @mentions
    const contentHtml = escapeHtml(c.content).replace(/@(\w+)/g, '<span class="civic-mention">@$1</span>');

    return `
        <div class="civic-comment" style="margin-left: ${marginLeft}px;" id="comment-${c.id}">
            <img src="${c.author_avatar}" class="civic-comment-avatar" alt="">
            <div class="civic-comment-bubble">
                <div class="civic-comment-author">${escapeHtml(c.author_name)}${isEdited} ${ownerActions}</div>
                <div class="civic-comment-text">${contentHtml}</div>
                <div class="civic-comment-meta">
                    ${replyBtn}
                </div>
                <div class="civic-reactions">
                    ${reactions}
                    ${reactionPicker}
                </div>
                <div class="civic-reply-form" id="reply-form-${c.id}">
                    <div style="display: flex; gap: 8px;">
                        <input type="text" class="civic-reply-input" placeholder="Write a reply..."
                               onkeydown="if(event.key === 'Enter') submitReply(${c.id}, this)">
                        <button class="civic-comment-submit" onclick="submitReply(${c.id}, this.previousElementSibling)" style="padding: 8px 16px;">Reply</button>
                    </div>
                </div>
            </div>
        </div>
        ${replies}
    `;
}

function toggleReactionPicker(commentId) {
    const picker = document.getElementById(`picker-${commentId}`);
    if (picker) {
        picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
    }
}

function toggleReaction(commentId, emoji) {
    if (!IS_LOGGED_IN) { alert('Please log in to react.'); return; }

    const picker = document.getElementById(`picker-${commentId}`);
    if (picker) picker.style.display = 'none';

    const formData = new FormData();
    formData.append('action', 'toggle_reaction');
    formData.append('comment_id', commentId);
    formData.append('emoji', emoji);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                fetchComments(currentTargetType, currentTargetId);
            }
        });
}

function showReplyForm(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        const input = form.querySelector('input');
        if (input) input.focus();
    }
}

function submitReply(parentId, input) {
    const content = input.value.trim();
    if (!content) return;
    input.disabled = true;

    const formData = new FormData();
    formData.append('action', 'reply_comment');
    formData.append('target_type', currentTargetType);
    formData.append('target_id', currentTargetId);
    formData.append('parent_id', parentId);
    formData.append('content', content);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            input.disabled = false;
            input.value = '';
            if (data.status === 'success') {
                fetchComments(currentTargetType, currentTargetId);
                showToast('Reply posted!');
            } else if (data.error) {
                alert(data.error);
            }
        });
}

function editComment(commentId, currentContent) {
    const newContent = prompt('Edit your comment:', currentContent.replace(/\\n/g, '\n'));
    if (newContent === null || newContent.trim() === '' || newContent === currentContent) return;

    const formData = new FormData();
    formData.append('action', 'edit_comment');
    formData.append('comment_id', commentId);
    formData.append('content', newContent.trim());

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                fetchComments(currentTargetType, currentTargetId);
                showToast('Comment updated!');
            } else if (data.error) {
                alert(data.error);
            }
        });
}

function deleteComment(commentId) {
    if (!confirm('Delete this comment?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_comment');
    formData.append('comment_id', commentId);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                fetchComments(currentTargetType, currentTargetId);
                showToast('Comment deleted!');
            } else if (data.error) {
                alert(data.error);
            }
        });
}

function submitComment(input, type, id) {
    const content = input.value.trim();
    if (!content) return;
    input.disabled = true;

    currentTargetType = type;
    currentTargetId = id;

    const formData = new FormData();
    formData.append('action', 'submit_comment');
    formData.append('target_type', type);
    formData.append('target_id', id);
    formData.append('content', content);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            input.disabled = false;
            if (data.status === 'success') {
                input.value = '';
                fetchComments(type, id);
                showToast('Comment posted!');

                // Update comment count
                const countEl = document.querySelector(`#post-${id} .comment-count`);
                if (countEl) countEl.textContent = parseInt(countEl.textContent) + 1;
            }
        });
}
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
