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
            $dbClass::query("DELETE FROM $table WHERE id = ?", [$targetId]);
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

<style>
    /* ============================================
   MODERN MOBILE FEED - DESIGN SYSTEM
   ============================================ */

    /* Animated Background */
    .feed-glass-bg {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: -1;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%, #f8fafc 100%);
    }

    .feed-glass-bg::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background:
            radial-gradient(ellipse at 20% 30%, rgba(99, 102, 241, 0.08) 0%, transparent 50%),
            radial-gradient(ellipse at 80% 20%, rgba(236, 72, 153, 0.06) 0%, transparent 45%),
            radial-gradient(ellipse at 60% 80%, rgba(59, 130, 246, 0.06) 0%, transparent 50%);
        animation: feedFloat 25s ease-in-out infinite;
    }

    [data-theme="dark"] .feed-glass-bg {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    }

    [data-theme="dark"] .feed-glass-bg::before {
        background:
            radial-gradient(ellipse at 20% 30%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
            radial-gradient(ellipse at 80% 20%, rgba(236, 72, 153, 0.1) 0%, transparent 45%);
    }

    @keyframes feedFloat {

        0%,
        100% {
            transform: translate(0, 0) scale(1);
        }

        50% {
            transform: translate(-1%, 0.5%) scale(1.01);
        }
    }

    /* Feed Container */
    .feed-container {
        max-width: 680px;
        margin: 0 auto;
        padding: 100px 16px 100px 16px;
        position: relative;
        z-index: 1;
    }

    /* Story Bar - Horizontal Scroll */
    .feed-stories {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        padding: 16px 0;
        margin: 0 -16px;
        padding-left: 16px;
        padding-right: 16px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .feed-stories::-webkit-scrollbar {
        display: none;
    }

    .story-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        cursor: pointer;
    }

    .story-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        padding: 3px;
        background: linear-gradient(135deg, #6366f1 0%, #ec4899 50%, #f59e0b 100%);
    }

    .story-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid white;
    }

    [data-theme="dark"] .story-avatar img {
        border-color: #1e293b;
    }

    .story-avatar.add-story {
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }

    .story-avatar.add-story i {
        font-size: 24px;
        color: #6366f1;
    }

    [data-theme="dark"] .story-avatar.add-story {
        background: #334155;
    }

    .story-name {
        font-size: 0.75rem;
        font-weight: 500;
        color: #475569;
        max-width: 64px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        text-align: center;
    }

    [data-theme="dark"] .story-name {
        color: #94a3b8;
    }

    /* Compose Box */
    .feed-compose {
        background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.9) 0%,
                rgba(255, 255, 255, 0.75) 100%);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 20px;
        box-shadow: 0 4px 24px rgba(31, 38, 135, 0.08);
        padding: 16px;
        margin-bottom: 16px;
    }

    [data-theme="dark"] .feed-compose {
        background: linear-gradient(135deg,
                rgba(30, 41, 59, 0.9) 0%,
                rgba(30, 41, 59, 0.75) 100%);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .compose-header {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .compose-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .compose-input {
        flex: 1;
        background: #f1f5f9;
        border: none;
        border-radius: 24px;
        padding: 12px 20px;
        font-size: 0.95rem;
        color: #1e293b;
        cursor: pointer;
        transition: all 0.2s;
    }

    .compose-input:hover {
        background: #e2e8f0;
    }

    [data-theme="dark"] .compose-input {
        background: #334155;
        color: #f1f5f9;
    }

    [data-theme="dark"] .compose-input:hover {
        background: #475569;
    }

    .compose-actions {
        display: flex;
        gap: 4px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #f1f5f9;
    }

    [data-theme="dark"] .compose-actions {
        border-color: #334155;
    }

    .compose-action {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px;
        border-radius: 12px;
        background: transparent;
        border: none;
        color: #64748b;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .compose-action:hover {
        background: #f1f5f9;
    }

    [data-theme="dark"] .compose-action:hover {
        background: #334155;
    }

    .compose-action i {
        font-size: 1.1rem;
    }

    .compose-action.photo i {
        color: #10b981;
    }

    .compose-action.video i {
        color: #ef4444;
    }

    .compose-action.event i {
        color: #f59e0b;
    }

    /* Filter Tabs */
    .feed-filters {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 8px 0 16px 0;
        margin: 0 -16px;
        padding-left: 16px;
        padding-right: 16px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    .feed-filters::-webkit-scrollbar {
        display: none;
    }

    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        font-size: 0.85rem;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }

    .filter-chip:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .filter-chip.active {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border-color: transparent;
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    [data-theme="dark"] .filter-chip {
        background: rgba(30, 41, 59, 0.8);
        border-color: #475569;
        color: #94a3b8;
    }

    [data-theme="dark"] .filter-chip:hover {
        background: #334155;
    }

    /* Feed Cards */
    .feed-card {
        background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.95) 0%,
                rgba(255, 255, 255, 0.85) 100%);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 20px;
        box-shadow: 0 4px 24px rgba(31, 38, 135, 0.06);
        margin-bottom: 16px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .feed-card:hover {
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
    }

    [data-theme="dark"] .feed-card {
        background: linear-gradient(135deg,
                rgba(30, 41, 59, 0.95) 0%,
                rgba(30, 41, 59, 0.85) 100%);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .feed-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 16px;
    }

    .feed-card-author {
        display: flex;
        gap: 12px;
    }

    .feed-card-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e2e8f0;
        flex-shrink: 0;
    }

    [data-theme="dark"] .feed-card-avatar {
        border-color: #475569;
    }

    .feed-card-meta {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .feed-card-name {
        font-weight: 700;
        font-size: 0.95rem;
        color: #1e293b;
    }

    [data-theme="dark"] .feed-card-name {
        color: #f1f5f9;
    }

    .feed-card-name span {
        font-weight: 400;
        color: #64748b;
    }

    .feed-card-time {
        font-size: 0.8rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .feed-card-menu {
        background: none;
        border: none;
        color: #94a3b8;
        padding: 8px;
        cursor: pointer;
        border-radius: 50%;
        transition: all 0.2s;
    }

    .feed-card-menu:hover {
        background: #f1f5f9;
        color: #64748b;
    }

    [data-theme="dark"] .feed-card-menu:hover {
        background: #334155;
    }

    .feed-card-content {
        padding: 0 16px 16px 16px;
        font-size: 0.95rem;
        line-height: 1.6;
        color: #334155;
    }

    [data-theme="dark"] .feed-card-content {
        color: #cbd5e1;
    }

    .feed-card-content a {
        color: #6366f1;
        text-decoration: none;
        font-weight: 500;
    }

    .feed-card-content a:hover {
        text-decoration: underline;
    }

    .feed-card-image {
        width: 100%;
        aspect-ratio: 16/9;
        object-fit: cover;
        border-top: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
    }

    [data-theme="dark"] .feed-card-image {
        border-color: #334155;
    }

    .feed-card-stats {
        display: flex;
        justify-content: space-between;
        padding: 10px 16px;
        font-size: 0.85rem;
        color: #64748b;
        border-bottom: 1px solid #f1f5f9;
    }

    [data-theme="dark"] .feed-card-stats {
        border-color: #334155;
    }

    .feed-card-stats .likes {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .feed-card-stats .likes i {
        color: #6366f1;
    }

    .feed-card-actions {
        display: flex;
        padding: 4px 8px;
    }

    .feed-action-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px;
        border-radius: 12px;
        background: transparent;
        border: none;
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
        min-height: 48px;
        -webkit-tap-highlight-color: transparent;
    }

    .feed-action-btn:hover {
        background: #f1f5f9;
        color: #6366f1;
    }

    .feed-action-btn:active {
        transform: scale(0.96);
    }

    .feed-action-btn.liked {
        color: #6366f1;
    }

    [data-theme="dark"] .feed-action-btn:hover {
        background: #334155;
    }

    /* Type Badges */
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .type-badge.listing-offer {
        background: #ecfdf5;
        color: #059669;
    }

    .type-badge.listing-request {
        background: #fef3c7;
        color: #d97706;
    }

    .type-badge.event {
        background: #fce7f3;
        color: #db2777;
    }

    .type-badge.goal {
        background: #ede9fe;
        color: #7c3aed;
    }

    .type-badge.poll {
        background: #e0f2fe;
        color: #0284c7;
    }

    .type-badge.volunteering {
        background: #dbeafe;
        color: #2563eb;
    }

    /* Special Cards */
    .feed-card-special {
        padding: 16px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    [data-theme="dark"] .feed-card-special {
        border-color: #334155;
    }

    .feed-card-special.event {
        background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
        border-left: 4px solid #ec4899;
    }

    .feed-card-special.listing {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border-left: 4px solid #22c55e;
    }

    .feed-card-special.goal {
        background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
        border-left: 4px solid #a855f7;
    }

    .feed-card-special.volunteering {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border-left: 4px solid #3b82f6;
    }

    [data-theme="dark"] .feed-card-special.event {
        background: linear-gradient(135deg, rgba(236, 72, 153, 0.15) 0%, rgba(219, 39, 119, 0.1) 100%);
    }

    [data-theme="dark"] .feed-card-special.listing {
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(22, 163, 74, 0.1) 100%);
    }

    .special-date {
        width: 52px;
        height: 52px;
        background: white;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        flex-shrink: 0;
    }

    .special-date .month {
        font-size: 0.65rem;
        font-weight: 700;
        color: #ef4444;
        text-transform: uppercase;
    }

    .special-date .day {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1e293b;
        line-height: 1;
    }

    .special-info {
        flex: 1;
        min-width: 0;
    }

    .special-title {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.95rem;
        margin-bottom: 2px;
    }

    [data-theme="dark"] .special-title {
        color: #f1f5f9;
    }

    .special-detail {
        font-size: 0.8rem;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .special-action {
        padding: 10px 20px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.85rem;
        color: #6366f1;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        white-space: nowrap;
    }

    .special-action:hover {
        background: #6366f1;
        color: white;
        border-color: #6366f1;
    }

    /* Comments Section */
    .feed-comments {
        display: none;
        padding: 12px 16px;
        border-top: 1px solid #f1f5f9;
        background: #fafbfc;
    }

    [data-theme="dark"] .feed-comments {
        background: rgba(15, 23, 42, 0.5);
        border-color: #334155;
    }

    .feed-comments.show {
        display: block;
    }

    .comment-input-wrap {
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }

    .comment-input-wrap img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .comment-input {
        flex: 1;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        padding: 10px 44px 10px 16px;
        font-size: 0.9rem;
        color: #1e293b;
        transition: all 0.2s;
        position: relative;
    }

    .comment-input:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    [data-theme="dark"] .comment-input {
        background: rgba(15, 23, 42, 0.6);
        border-color: rgba(139, 92, 246, 0.25);
        color: #f1f5f9;
    }

    [data-theme="dark"] .comment-input:focus {
        background: rgba(15, 23, 42, 0.75);
        border-color: rgba(139, 92, 246, 0.5);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
    }

    [data-theme="dark"] .comment-input::placeholder {
        color: #94a3b8;
    }

    .comment-send {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        transition: color 0.2s;
    }

    .comment-send:hover {
        color: #6366f1;
    }

    /* Empty State */
    .feed-empty {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }

    .feed-empty-icon {
        font-size: 4rem;
        margin-bottom: 16px;
        opacity: 0.4;
    }

    .feed-empty h3 {
        font-size: 1.2rem;
        color: #64748b;
        margin-bottom: 8px;
    }

    /* FAB */
    .feed-fab {
        position: fixed;
        bottom: 90px;
        right: 20px;
        z-index: 100;
    }

    .feed-fab-btn {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border: none;
        color: white;
        font-size: 22px;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .feed-fab-btn:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 30px rgba(99, 102, 241, 0.5);
    }

    .feed-fab-btn:active {
        transform: scale(0.95);
    }

    /* Loading Skeleton */
    .skeleton {
        background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 8px;
    }

    @keyframes shimmer {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }

    [data-theme="dark"] .skeleton {
        background: linear-gradient(90deg, #334155 0%, #475569 50%, #334155 100%);
        background-size: 200% 100%;
    }

    /* Mobile Responsiveness */
    @media (max-width: 600px) {
        .feed-container {
            padding: 90px 12px 100px 12px;
        }

        .feed-compose {
            border-radius: 16px;
            padding: 14px;
        }

        .compose-input {
            padding: 10px 16px;
            font-size: 0.9rem;
        }

        .compose-actions {
            margin-top: 10px;
            padding-top: 10px;
        }

        .compose-action span {
            display: none;
        }

        .compose-action i {
            font-size: 1.25rem;
        }

        .feed-card {
            border-radius: 16px;
        }

        .feed-card-header {
            padding: 14px;
        }

        .feed-card-content {
            padding: 0 14px 14px 14px;
        }

        .feed-card-stats {
            padding: 8px 14px;
        }

        .feed-action-btn {
            padding: 10px;
            font-size: 0.85rem;
        }

        .feed-action-btn span {
            display: none;
        }

        .feed-fab {
            bottom: calc(80px + env(safe-area-inset-bottom, 0px));
            right: 16px;
        }

        .story-avatar {
            width: 56px;
            height: 56px;
        }

        .story-name {
            max-width: 56px;
        }
    }

    /* Safe area padding for iOS */
    @supports (padding-bottom: env(safe-area-inset-bottom)) {
        .feed-container {
            padding-bottom: calc(100px + env(safe-area-inset-bottom));
        }
    }
</style>

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