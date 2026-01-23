<?php

/**
 * Modern Feed - Mobile-First Layout
 * A clean, modern feed interface with native mobile feel
 */

// Get user/session data FIRST (needed for AJAX handlers)
$userId = $_SESSION['user_id'] ?? null;
$isLoggedIn = !empty($userId);
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
$userName = $_SESSION['user_name'] ?? 'Guest';
$tenantId = $_SESSION['current_tenant_id'] ?? (\Nexus\Core\TenantContext::getId() ?? 1);

$hero_title = "Feed";
$hero_subtitle = "What's happening in your community";
$hero_gradient = 'htb-hero-gradient-brand';
$hero_type = 'Social';

// ---------------------------------------------------------
// AJAX ACTION HANDLERS (for comments, likes, shares)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    if (!$isLoggedIn) {
        echo json_encode(['error' => 'Login required', 'redirect' => '/login']);
        exit;
    }

    $targetType = $_POST['target_type'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);

    try {
        $dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';

        // TOGGLE LIKE
        if ($_POST['action'] === 'toggle_like') {
            $existing = $dbClass::query("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?", [$userId, $targetType, $targetId])->fetch();
            if ($existing) {
                $dbClass::query("DELETE FROM likes WHERE id = ?", [$existing['id']]);
                if ($targetType === 'post') $dbClass::query("UPDATE feed_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?", [$targetId]);
                $countResult = $dbClass::query("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?", [$targetType, $targetId])->fetch();
                echo json_encode(['status' => 'unliked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
            } else {
                $dbClass::query("INSERT INTO likes (user_id, target_type, target_id, tenant_id) VALUES (?, ?, ?, ?)", [$userId, $tenantId, $targetType, $targetId]);
                if ($targetType === 'post') $dbClass::query("UPDATE feed_posts SET likes_count = likes_count + 1 WHERE id = ?", [$targetId]);

                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        $contentPreview = \Nexus\Services\SocialNotificationService::getContentPreview($targetType, $targetId);
                        \Nexus\Services\SocialNotificationService::notifyLike($contentOwnerId, $userId, $targetType, $targetId, $contentPreview);
                    }
                }

                $countResult = $dbClass::query("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?", [$targetType, $targetId])->fetch();
                echo json_encode(['status' => 'liked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
            }
        }

        // SUBMIT COMMENT
        elseif ($_POST['action'] === 'submit_comment') {
            $content = trim($_POST['content']);
            if (empty($content)) exit;

            if (class_exists('\Nexus\Services\CommentService')) {
                $result = \Nexus\Services\CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content);

                if ($result['status'] === 'success' && class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyComment($contentOwnerId, $userId, $targetType, $targetId, $content);
                    }
                }
                echo json_encode($result);
            } else {
                $dbClass::query(
                    "INSERT INTO comments (user_id, tenant_id, target_type, target_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?)",
                    [$userId, $tenantId, $targetType, $targetId, $content, date('Y-m-d H:i:s')]
                );

                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyComment($contentOwnerId, $userId, $targetType, $targetId, $content);
                    }
                }

                echo json_encode(['status' => 'success', 'comment' => [
                    'author_name' => $_SESSION['user_name'] ?? 'Me',
                    'author_avatar' => $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                    'content' => $content
                ]]);
            }
        }

        // FETCH COMMENTS - Use Database directly (bypasses tenant filter)
        elseif ($_POST['action'] === 'fetch_comments') {
            $sql = "SELECT c.content, c.created_at,
                           COALESCE(u.name, 'Unknown') as author_name,
                           COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.webp') as author_avatar
                    FROM comments c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.target_type = ? AND c.target_id = ?
                    ORDER BY c.created_at ASC";
            // Use Database directly - comments are already scoped by target_type + target_id
            $stmt = \Nexus\Core\Database::query($sql, [$targetType, $targetId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // DEBUG: Log the query params and results
            error_log("FETCH_COMMENTS DEBUG: type={$targetType} id={$targetId} count=" . count($comments));
            echo json_encode(['status' => 'success', 'comments' => $comments]);
        }

        // SHARE (REPOST)
        elseif ($_POST['action'] === 'share_repost') {
            $parentId = (int)($_POST['parent_id'] ?? 0);
            $parentType = $_POST['parent_type'] ?? 'post';

            if ($parentId <= 0) {
                echo json_encode(['error' => 'Invalid Post ID']);
                exit;
            }

            $newContent = trim($_POST['content'] ?? '');

            if (class_exists('\Nexus\Models\FeedPost')) {
                \Nexus\Models\FeedPost::create($userId, $newContent, null, null, $parentId, $parentType);
            } else {
                $dbClass::query(
                    "INSERT INTO feed_posts (user_id, tenant_id, content, likes_count, visibility, created_at, parent_id, parent_type) VALUES (?, ?, ?, 0, 'public', ?, ?, ?)",
                    [$userId, $tenantId, $newContent, date('Y-m-d H:i:s'), $parentId, $parentType]
                );
            }

            if (class_exists('\Nexus\Services\SocialNotificationService')) {
                $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($parentType, $parentId);
                if ($contentOwnerId && $contentOwnerId != $userId) {
                    \Nexus\Services\SocialNotificationService::notifyShare($contentOwnerId, $userId, $parentType, $parentId);
                }
            }

            echo json_encode(['status' => 'success']);
        }

        // DELETE POST
        elseif ($_POST['action'] === 'delete_post') {
            if (($_SESSION['user_role'] ?? '') !== 'admin') exit;
            $table = ($targetType === 'post') ? 'feed_posts' : 'listings';
            $dbClass::query("DELETE FROM `$table` WHERE id = ?", [$targetId]);
            echo json_encode(['status' => 'deleted']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

require __DIR__ . '/../../layouts/header.php';

// Fetch unified feed (same logic as home.php)
$feedItems = [];

try {
    $dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';

    if (class_exists($dbClass) && method_exists($dbClass, 'query')) {
        $uid = $userId ?: 0;

        // Fetch posts with engagement counts
        $feedSql = "SELECT p.*, u.name as author_name, u.avatar_url as author_avatar,
                    (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = p.id) as is_liked,
                    (SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id) as likes_count,
                    (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count
                    FROM feed_posts p
                    JOIN users u ON p.user_id = u.id
                    WHERE p.tenant_id = ?
                    AND (p.visibility = 'public' OR (p.user_id = ? AND p.visibility != 'private'))
                    ORDER BY p.created_at DESC LIMIT 50";
        $rawPosts = $dbClass::query($feedSql, [$uid, $tenantId, $uid])->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rawPosts as $p) {
            $feedItems[] = [
                'type' => 'post',
                'id' => $p['id'],
                'user_id' => $p['user_id'],
                'author_name' => $p['author_name'],
                'author_avatar' => $p['author_avatar'],
                'title' => null,
                'content' => $p['content'],
                'body' => $p['content'],
                'created_at' => $p['created_at'],
                'likes_count' => $p['likes_count'] ?? 0,
                'comments_count' => $p['comments_count'] ?? 0,
                'is_liked' => $p['is_liked'] ?? 0,
                'image_url' => $p['image_url'],
                'extra_3' => $p['image_url'],
                'parent_id' => $p['parent_id'] ?? null,
                'parent_type' => $p['parent_type'] ?? 'post'
            ];
        }

        // Fetch listings with engagement counts (including comments)
        $listingSql = "SELECT l.*,
                    COALESCE(NULLIF(u.name, ''), CONCAT(u.first_name, ' ', u.last_name), 'Unknown') as author_name,
                    u.avatar_url as author_avatar,
                    u.location as author_location,
                    (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'listing' AND target_id = l.id) as is_liked,
                    (SELECT COUNT(*) FROM likes WHERE target_type = 'listing' AND target_id = l.id) as likes_count,
                    (SELECT COUNT(*) FROM comments WHERE target_type = 'listing' AND target_id = l.id) as comments_count
                    FROM listings l
                    LEFT JOIN users u ON l.user_id = u.id
                    WHERE l.tenant_id = ? AND l.status = 'active'
                    ORDER BY l.created_at DESC LIMIT 30";
        $rawListings = $dbClass::query($listingSql, [$uid, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rawListings as $l) {
            $feedItems[] = [
                'type' => 'listing',
                'id' => $l['id'],
                'user_id' => $l['user_id'],
                'author_name' => $l['author_name'],
                'author_avatar' => $l['author_avatar'],
                'title' => $l['title'],
                'content' => $l['description'],
                'body' => $l['description'],
                'created_at' => $l['created_at'],
                'likes_count' => $l['likes_count'] ?? 0,
                'comments_count' => $l['comments_count'] ?? 0,
                'is_liked' => $l['is_liked'] ?? 0,
                'location' => $l['location'] ?? $l['author_location'],
                'extra_1' => $l['title'],
                'extra_2' => $l['type'],
                'extra_3' => $l['image_url']
            ];
        }

        // Sort all items by created_at descending
        usort($feedItems, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // Limit to 50 items
        $feedItems = array_slice($feedItems, 0, 50);
    }
} catch (\Throwable $e) {
    error_log("Feed fetch error: " . $e->getMessage());
}
?>

<!-- CSS moved to /assets/css/feed-page.css -->

<div class="feed-glass-bg"></div>

<div class="feed-container">

    <!-- Stories Bar -->
    <div class="feed-stories">
        <div class="story-item">
            <div class="story-avatar add-story">
                <i class="fa-solid fa-plus"></i>
            </div>
            <span class="story-name">Add Story</span>
        </div>
        <?php
        // Sample stories - in production, fetch from database
        $sampleStories = [
            ['name' => 'Sarah M.', 'avatar' => '/assets/img/defaults/default_avatar.webp'],
            ['name' => 'John D.', 'avatar' => '/assets/img/defaults/default_avatar.webp'],
            ['name' => 'Emma W.', 'avatar' => '/assets/img/defaults/default_avatar.webp'],
            ['name' => 'Mike R.', 'avatar' => '/assets/img/defaults/default_avatar.webp'],
            ['name' => 'Lisa K.', 'avatar' => '/assets/img/defaults/default_avatar.webp'],
        ];
        foreach ($sampleStories as $story):
        ?>
            <div class="story-item">
                <div class="story-avatar">
                    <?= webp_avatar($story['avatar'], $story['name'], 56) ?>
                </div>
                <span class="story-name"><?= htmlspecialchars($story['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Compose Box -->
    <?php if ($isLoggedIn): ?>
        <div class="feed-compose">
            <div class="compose-header">
                <?= webp_avatar($userAvatar, $userName ?? 'User', 40) ?>
                <div class="compose-input" onclick="openComposeModal()">
                    What's on your mind, <?= htmlspecialchars(explode(' ', $userName)[0]) ?>?
                </div>
            </div>
            <div class="compose-actions">
                <button class="compose-action photo" onclick="openComposeModal('photo')">
                    <i class="fa-solid fa-image"></i>
                    <span>Photo</span>
                </button>
                <button class="compose-action video" onclick="openComposeModal('video')">
                    <i class="fa-solid fa-video"></i>
                    <span>Video</span>
                </button>
                <button class="compose-action event" onclick="window.location.href='<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/create'">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span>Event</span>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="feed-filters">
        <a href="?filter=all" class="filter-chip active">
            <i class="fa-solid fa-stream"></i> All
        </a>
        <a href="?filter=posts" class="filter-chip">
            <i class="fa-solid fa-comment"></i> Posts
        </a>
        <a href="?filter=listings" class="filter-chip">
            <i class="fa-solid fa-hand-holding-heart"></i> Listings
        </a>
        <a href="?filter=events" class="filter-chip">
            <i class="fa-solid fa-calendar"></i> Events
        </a>
        <a href="?filter=volunteering" class="filter-chip">
            <i class="fa-solid fa-hands-helping"></i> Volunteer
        </a>
    </div>

    <!-- Feed Items -->
    <?php if (empty($feedItems)): ?>
        <div class="feed-empty">
            <div class="feed-empty-icon">
                <i class="fa-regular fa-newspaper"></i>
            </div>
            <h3>No posts yet</h3>
            <p>Be the first to share something with your community!</p>
        </div>
    <?php else: ?>
        <?php
        foreach ($feedItems as $item):
            // Render using existing partial
            $isLoggedInPartial = $isLoggedIn;
            $userIdPartial = $userId;
            include __DIR__ . '/../partials/feed_item.php';
        endforeach;
        ?>
    <?php endif; ?>

    <!-- Load More -->
    <?php if (count($feedItems) >= 20): ?>
        <div style="text-align: center; padding: 20px;">
            <button onclick="loadMoreFeed()" class="htb-btn" style="background: white; border: 1px solid #e2e8f0; color: #64748b; font-weight: 600; padding: 12px 24px; border-radius: 12px;">
                <i class="fa-solid fa-arrows-rotate" style="margin-right: 8px;"></i> Load More
            </button>
        </div>
    <?php endif; ?>

</div>

<!-- FAB for Quick Post -->
<?php if ($isLoggedIn): ?>
    <div class="feed-fab">
        <button class="feed-fab-btn" onclick="openComposeModal()" aria-label="Create new post">
            <i class="fa-solid fa-pen"></i>
        </button>
    </div>
<?php endif; ?>

<!-- Compose Modal -->
<dialog id="compose-modal" style="border: none; border-radius: 20px; padding: 0; width: 500px; max-width: 94vw; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); background: white;">
    <form id="compose-form" method="dialog" style="display: flex; flex-direction: column;">
        <div style="padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #1e293b;">Create Post</h3>
            <button type="button" onclick="closeComposeModal()" style="background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 18px; color: #64748b; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div style="padding: 16px 20px;">
            <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                <?= webp_avatar($userAvatar ?? null, $userName ?? 'You', 44) ?>
                <div>
                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($userName ?? 'You') ?></div>
                    <div style="font-size: 0.8rem; color: #64748b;"><i class="fa-solid fa-globe"></i> Public</div>
                </div>
            </div>
            <textarea id="compose-content" name="content" placeholder="What's on your mind?" style="width: 100%; min-height: 120px; border: none; resize: none; font-size: 1rem; color: #1e293b; line-height: 1.5; font-family: inherit;" required></textarea>
            <div id="compose-preview" style="display: none; margin-top: 12px;"></div>
        </div>
        <div style="padding: 12px 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 8px;">
                <button type="button" onclick="addPhoto()" style="background: none; border: none; color: #10b981; font-size: 1.2rem; cursor: pointer; padding: 8px;"><i class="fa-solid fa-image"></i></button>
                <button type="button" onclick="addVideo()" style="background: none; border: none; color: #ef4444; font-size: 1.2rem; cursor: pointer; padding: 8px;"><i class="fa-solid fa-video"></i></button>
                <button type="button" onclick="addEmoji()" style="background: none; border: none; color: #f59e0b; font-size: 1.2rem; cursor: pointer; padding: 8px;"><i class="fa-regular fa-face-smile"></i></button>
            </div>
            <button type="submit" id="compose-submit" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                Post
            </button>
        </div>
    </form>
</dialog>

<script>
    // Compose Modal Functions
    function openComposeModal(type) {
        const modal = document.getElementById('compose-modal');
        if (typeof modal.showModal === 'function') {
            modal.showModal();
        }
        document.getElementById('compose-content').focus();
    }

    function closeComposeModal() {
        const modal = document.getElementById('compose-modal');
        modal.close();
        document.getElementById('compose-content').value = '';
        document.getElementById('compose-preview').style.display = 'none';
    }

    // Form submission
    document.getElementById('compose-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const content = document.getElementById('compose-content').value.trim();
        if (!content) return;

        const btn = document.getElementById('compose-submit');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('content', content);

            const response = await fetch(NEXUS_BASE + '/api/feed/create', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            const data = await response.json();

            if (data.success) {
                closeComposeModal();
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to create post'));
            }
        } catch (err) {
            alert('Network error. Please try again.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // Placeholder functions for media
    function addPhoto() {
        alert('Photo upload coming soon!');
    }

    function addVideo() {
        alert('Video upload coming soon!');
    }

    function addEmoji() {
        // Simple emoji picker placeholder
        const emojis = ['😀', '❤️', '👍', '🎉', '🔥', '✨', '💪', '🙏'];
        const emoji = emojis[Math.floor(Math.random() * emojis.length)];
        document.getElementById('compose-content').value += emoji;
    }

    // Filter chip active state
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.addEventListener('click', function(e) {
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // ============================================
    // SOCIAL INTERACTIONS (Likes, Comments, Share)
    // ============================================

    // Toggle Like
    function toggleLike(btn, type, id) {
        const formData = new FormData();
        formData.append("action", "toggle_like");
        formData.append("target_type", type);
        formData.append("target_id", id);

        fetch(window.location.href, {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'liked' || data.status === 'unliked') {
                    const icon = btn.querySelector('i');
                    if (data.status === 'liked') {
                        btn.style.color = '#4f46e5';
                        btn.style.fontWeight = '600';
                        if (icon) icon.className = 'fa-solid fa-thumbs-up';
                    } else {
                        btn.style.color = '#6b7280';
                        btn.style.fontWeight = 'normal';
                        if (icon) icon.className = 'fa-regular fa-thumbs-up';
                    }
                }
            });
    }

    // Toggle Comment Section
    // Use window assignment (not function declaration) so mobile-sheets.php can intercept
    window.toggleCommentSection = function(type, id) {
        const section = document.getElementById(`comments-section-${type}-${id}`);
        if (!section) return;

        if (window.getComputedStyle(section).display === 'none') {
            section.style.display = 'block';
            const input = section.querySelector("input");
            if (input) input.focus();
            fetchComments(type, id);
        } else {
            section.style.display = 'none';
        }
    };

    // Fetch Comments
    function fetchComments(type, id) {
        const list = document.querySelector(`#comments-section-${type}-${id} .comments-list`);
        if (!list) return;
        list.innerHTML = '<div style="color:#6b7280; padding:10px;">Loading...</div>';

        const formData = new FormData();
        formData.append("action", "fetch_comments");
        formData.append("target_type", type);
        formData.append("target_id", id);

        fetch(window.location.href, {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.comments && data.comments.length > 0) {
                    list.innerHTML = data.comments.map(c => renderComment(c)).join('');
                } else {
                    list.innerHTML = '<div style="color:#9ca3af; text-align:center; padding:16px; font-size:0.9rem;">No comments yet. Be the first to comment!</div>';
                }
            })
            .catch(() => {
                list.innerHTML = '<div style="color:#ef4444; padding:10px;">Failed to load comments</div>';
            });
    }

    // Render Comment - Simple format matching Nexus Social
    function renderComment(c) {
        return `
    <div style="padding:8px 0; border-bottom:1px solid #f3f4f6;">
        <div style="display:flex; gap:8px;">
            <img src="${c.author_avatar || '/assets/img/defaults/default_avatar.webp'}" style="width:28px; height:28px; border-radius:50%; object-fit:cover;" loading="lazy">
            <div style="flex:1;">
                <div style="background:#f3f4f6; padding:8px 12px; border-radius:12px;">
                    <div style="font-weight:600; font-size:13px; color:#1f2937;">${c.author_name || 'Unknown'}</div>
                    <div style="font-size:14px; color:#374151; margin-top:2px;">${c.content || ''}</div>
                </div>
            </div>
        </div>
    </div>`;
    }

    // Submit Comment
    function submitComment(input, type, id) {
        const content = input.value.trim();
        if (!content) return;

        input.disabled = true;

        const formData = new FormData();
        formData.append("action", "submit_comment");
        formData.append("target_type", type);
        formData.append("target_id", id);
        formData.append("content", content);

        fetch(window.location.href, {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                input.disabled = false;
                if (data.status === 'success') {
                    input.value = '';
                    fetchComments(type, id);
                }
            })
            .catch(() => {
                input.disabled = false;
            });
    }

    // Delete Post (Admin)
    function deletePost(type, id) {
        if (!confirm('Delete this post?')) return;

        const formData = new FormData();
        formData.append("action", "delete_post");
        formData.append("target_type", type);
        formData.append("target_id", id);

        fetch(window.location.href, {
            method: "POST",
            body: formData
        }).then(() => location.reload());
    }

    // Repost to Feed
    function repostToFeed(type, id, author) {
        const caption = prompt(`Share ${author}'s post with a comment (optional):`, '');
        if (caption === null) return;

        const formData = new FormData();
        formData.append("action", "share_repost");
        formData.append("parent_id", id);
        formData.append("parent_type", type);
        formData.append("content", caption);

        fetch(window.location.href, {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Shared to your feed!');
                    setTimeout(() => location.reload(), 500);
                } else {
                    alert('Share failed: ' + (data.error || 'Unknown error'));
                }
            });
    }

    // Infinite scroll (placeholder)
    let feedPage = 1;

    function loadMoreFeed() {
        feedPage++;
        // In production, fetch more items via AJAX
        console.log('Loading page', feedPage);
    }

    // DISABLED: Pull-to-refresh for mobile - causing unwanted page reloads
    // This feature was triggering window.location.reload() on touch gestures
    // To re-enable, uncomment the code below
    /*
    (function() {
        if (!('ontouchstart' in window)) return;

        let startY = 0;
        let pulling = false;
        const container = document.querySelector('.feed-container');

        container.addEventListener('touchstart', function(e) {
            if (window.scrollY === 0) {
                startY = e.touches[0].pageY;
                pulling = true;
            }
        }, { passive: true });

        container.addEventListener('touchend', function(e) {
            if (!pulling) return;
            pulling = false;

            const endY = e.changedTouches[0].pageY;
            if (endY - startY > 100 && window.scrollY === 0) {
                window.location.reload();
            }
        }, { passive: true });
    })();
    */
    console.log('[NEXUS] Pull-to-refresh disabled on feed page');
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>