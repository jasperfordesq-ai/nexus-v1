<?php
// CivicOne View: Community Pulse Feed - MadeOpen Style
// =========================================================
// Full-featured social feed with listings, events, polls, volunteering, goals
// WCAG 2.1 AA Compliant | Dark Mode Support | GDS/FDS Standards

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$tenantId = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::get()['id'] : ($_SESSION['current_tenant_id'] ?? 1);
$basePath = \Nexus\Core\TenantContext::getBasePath();

// ---------------------------------------------------------
// AJAX HANDLERS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    header('Content-Type: application/json');

    // CSRF Validation - Check POST token or X-CSRF-TOKEN header
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!class_exists('\Nexus\Core\Csrf') || !\Nexus\Core\Csrf::validate($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token. Please refresh the page.']);
        exit;
    }

    if (!$isLoggedIn) {
        echo json_encode(['error' => 'Login required']);
        exit;
    }

    $targetType = $_POST['target_type'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);
    $dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';

    try {
        // TOGGLE LIKE
        if ($_POST['action'] === 'toggle_like') {
            $existing = $dbClass::query("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?", [$userId, $targetType, $targetId])->fetch();

            if ($existing) {
                $dbClass::query("DELETE FROM likes WHERE id = ?", [$existing['id']]);
                if ($targetType === 'post') {
                    $dbClass::query("UPDATE feed_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?", [$targetId]);
                }
                $action = 'unliked';
            } else {
                $dbClass::query("INSERT INTO likes (user_id, target_type, target_id, tenant_id) VALUES (?, ?, ?, ?)", [$userId, $targetType, $targetId, $tenantId]);
                if ($targetType === 'post') {
                    $dbClass::query("UPDATE feed_posts SET likes_count = likes_count + 1 WHERE id = ?", [$targetId]);
                }
                $action = 'liked';

                // Notify content owner
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyLike($contentOwnerId, $userId, $targetType, $targetId, '');
                    }
                }
            }

            $countResult = $dbClass::query("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?", [$targetType, $targetId])->fetch();
            $count = $countResult['cnt'] ?? 0;

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
                $comments = \Nexus\Services\CommentService::fetchComments($targetType, $targetId, $userId);
                echo json_encode([
                    'status' => 'success',
                    'comments' => $comments,
                    'available_reactions' => \Nexus\Services\CommentService::getAvailableReactions()
                ]);
            } else {
                $sql = "SELECT c.content, c.created_at,
                    COALESCE(u.name, 'Unknown') as author_name,
                    COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.webp') as author_avatar
                    FROM comments c LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.target_type = ? AND c.target_id = ? ORDER BY c.created_at ASC";
                $stmt = $dbClass::query($sql, [$targetType, $targetId]);
                echo json_encode(['status' => 'success', 'comments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
        }

        // DELETE COMMENT
        elseif ($_POST['action'] === 'delete_comment') {
            $commentId = (int)($_POST['comment_id'] ?? 0);
            $isSuperAdmin = !empty($_SESSION['is_super_admin']) || ($_SESSION['user_role'] ?? '') === 'admin';
            if (class_exists('\Nexus\Services\CommentService')) {
                echo json_encode(\Nexus\Services\CommentService::deleteComment($commentId, $userId, $isSuperAdmin));
            } else {
                echo json_encode(['error' => 'CommentService not available']);
            }
        }

        // EDIT COMMENT
        elseif ($_POST['action'] === 'edit_comment') {
            $commentId = (int)($_POST['comment_id'] ?? 0);
            $newContent = $_POST['content'] ?? '';
            if (class_exists('\Nexus\Services\CommentService')) {
                echo json_encode(\Nexus\Services\CommentService::editComment($commentId, $userId, $newContent));
            } else {
                echo json_encode(['error' => 'CommentService not available']);
            }
        }

        // REPLY TO COMMENT
        elseif ($_POST['action'] === 'reply_comment') {
            $parentId = (int)($_POST['parent_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            if (class_exists('\Nexus\Services\CommentService')) {
                $result = \Nexus\Services\CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content, $parentId);

                if ($result['status'] === 'success' && $result['is_reply'] && class_exists('\Nexus\Services\SocialNotificationService')) {
                    $parent = $dbClass::query("SELECT user_id FROM comments WHERE id = ?", [$parentId])->fetch();
                    if ($parent && $parent['user_id'] != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyComment(
                            $parent['user_id'], $userId, $targetType, $targetId, "replied to your comment"
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
                echo json_encode(\Nexus\Services\CommentService::toggleReaction($userId, $tenantId, $commentId, $emoji));
            } else {
                echo json_encode(['error' => 'CommentService not available']);
            }
        }

        // SHARE/REPOST
        elseif ($_POST['action'] === 'share_repost') {
            $parentId = (int)($_POST['parent_id'] ?? 0);
            $parentType = $_POST['parent_type'] ?? 'post';
            $newContent = trim($_POST['content'] ?? '');

            if ($parentId > 0) {
                if (class_exists('\Nexus\Models\FeedPost')) {
                    \Nexus\Models\FeedPost::create($userId, $newContent, null, null, $parentId, $parentType);
                } else {
                    $dbClass::query(
                        "INSERT INTO feed_posts (user_id, tenant_id, content, likes_count, visibility, created_at, parent_id, parent_type) VALUES (?, ?, ?, 0, 'public', ?, ?, ?)",
                        [$userId, $tenantId, $newContent, date('Y-m-d H:i:s'), $parentId, $parentType]
                    );
                }

                // Notify original content owner
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($parentType, $parentId);
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyShare($contentOwnerId, $userId, $parentType, $parentId);
                    }
                }

                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['error' => 'Invalid Post ID']);
            }
        }

        // DELETE POST
        elseif ($_POST['action'] === 'delete_post') {
            $postData = $dbClass::query("SELECT user_id FROM feed_posts WHERE id = ?", [$targetId])->fetch();
            if ($postData && ($postData['user_id'] == $userId || ($_SESSION['user_role'] ?? '') === 'admin')) {
                $dbClass::query("DELETE FROM feed_posts WHERE id = ?", [$targetId]);
                echo json_encode(['status' => 'deleted']);
            } else {
                echo json_encode(['error' => 'Access denied']);
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

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Handle Post Submission (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && (!empty($_POST['content']) || !empty($_FILES['image']['name']))) {
    if ($isLoggedIn) {
        try {
            $dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';
            $imageUrl = null;

            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // SECURITY: Validate file type using finfo (not just extension)
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($_FILES['image']['tmp_name']);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                if (!in_array($mimeType, $allowedMimes)) {
                    throw new \Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
                }

                // SECURITY: Validate it's actually an image
                $imageInfo = @getimagesize($_FILES['image']['tmp_name']);
                if ($imageInfo === false) {
                    throw new \Exception('Uploaded file is not a valid image.');
                }

                // SECURITY: Check file size (max 5MB)
                if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                    throw new \Exception('Image file is too large. Maximum size is 5MB.');
                }

                $uploadDir = dirname(__DIR__, 3) . '/httpdocs/uploads/posts/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                // SECURITY: Generate safe filename with proper extension based on MIME type
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                $ext = $extensions[$mimeType] ?? 'jpg';
                $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imageUrl = '/uploads/posts/' . $fileName;
                }
            }

            if (class_exists('\Nexus\Core\DatabaseWrapper')) {
                \Nexus\Core\DatabaseWrapper::insert('feed_posts', [
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'content' => trim($_POST['content']),
                    'image_url' => $imageUrl,
                    'visibility' => 'public',
                    'likes_count' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } else {
                $dbClass::query(
                    "INSERT INTO feed_posts (user_id, tenant_id, content, image_url, visibility, likes_count, created_at) VALUES (?, ?, ?, ?, 'public', 0, ?)",
                    [$userId, $tenantId, trim($_POST['content']), $imageUrl, date('Y-m-d H:i:s')]
                );
            }

            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Exception $e) {
            error_log("Post Error: " . $e->getMessage());
        }
    }
}

// ---------------------------------------------------------
// FETCH FEED DATA - All Content Types
// ---------------------------------------------------------
$feedItems = [];
$errorMsg = null;

// Time helper
$timeElapsed = function ($datetime) {
    try {
        if (empty($datetime)) return '';
        $diff = (new DateTime)->diff(new DateTime($datetime));
        if ($diff->y) return $diff->y . 'y';
        if ($diff->m) return $diff->m . 'mo';
        if ($diff->d >= 7) return floor($diff->d / 7) . 'w';
        if ($diff->d) return $diff->d . 'd';
        if ($diff->h) return $diff->h . 'h';
        if ($diff->i) return $diff->i . 'm';
        return 'Just now';
    } catch (Exception $e) {
        return '';
    }
};

try {
        $dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';
    $fetchLimit = 20;

        $feedSql = "SELECT p.*, u.name as author_name, u.avatar_url as author_avatar, u.location as author_location,
                (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = p.id) as is_liked,
                (SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count
                FROM feed_posts p LEFT JOIN users u ON p.user_id = u.id
                WHERE p.tenant_id = ? AND (p.visibility = 'public' OR p.user_id = ?)
                ORDER BY p.created_at DESC LIMIT $fetchLimit";
    $posts = $dbClass::query($feedSql, [$userId, $tenantId, $userId])->fetchAll(PDO::FETCH_ASSOC);

    foreach ($posts as $p) {
        $feedItems[] = [
            'type' => 'post',
            'id' => $p['id'],
            'user_id' => $p['user_id'],
            'author_name' => $p['author_name'] ?? 'Unknown',
            'author_avatar' => $p['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
            'title' => null,
            'body' => $p['content'],
            'created_at' => $p['created_at'],
            'likes_count' => $p['likes_count'] ?? 0,
            'comments_count' => $p['comments_count'] ?? 0,
            'is_liked' => $p['is_liked'] ?? false,
            'image_url' => $p['image_url'] ?? null,
            'location' => $p['author_location'] ?? null,
            'parent_id' => $p['parent_id'] ?? null,
            'parent_type' => $p['parent_type'] ?? 'post'
        ];
    }

        try {
        $sqlListings = "SELECT l.*, COALESCE(u.name, 'Unknown') as author_name, u.avatar_url as author_avatar, u.location as author_location
               FROM listings l LEFT JOIN users u ON l.user_id = u.id
               WHERE l.tenant_id = ? AND l.status = 'active' ORDER BY l.created_at DESC LIMIT $fetchLimit";
        $listings = $dbClass::query($sqlListings, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($listings as $l) {
            $feedItems[] = [
                'type' => 'listing',
                'id' => $l['id'],
                'user_id' => $l['user_id'],
                'author_name' => $l['author_name'],
                'author_avatar' => $l['author_avatar'],
                'title' => $l['title'],
                'body' => $l['description'],
                'created_at' => $l['created_at'],
                'likes_count' => 0,
                'comments_count' => 0,
                'is_liked' => false,
                'location' => $l['location'] ?? $l['author_location'],
                'listing_type' => $l['type'] ?? 'offer',
                'image_url' => $l['image_url'] ?? null
            ];
        }
    } catch (Exception $e) {}

        try {
        $sqlEvents = "SELECT e.*, COALESCE(u.name, 'Organizer') as author_name, u.avatar_url as author_avatar
                      FROM events e LEFT JOIN users u ON e.user_id = u.id
                      WHERE e.tenant_id = ? ORDER BY e.start_time ASC LIMIT $fetchLimit";
        $events = $dbClass::query($sqlEvents, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($events as $e) {
            $feedItems[] = [
                'type' => 'event',
                'id' => $e['id'],
                'user_id' => $e['user_id'],
                'author_name' => $e['author_name'],
                'author_avatar' => $e['author_avatar'],
                'title' => $e['title'],
                'body' => $e['description'] ?? '',
                'created_at' => $e['created_at'],
                'likes_count' => 0,
                'comments_count' => 0,
                'is_liked' => false,
                'location' => $e['location'],
                'event_date' => $e['start_time']
            ];
        }
    } catch (Exception $e) {}

        try {
        $sqlPolls = "SELECT p.*, COALESCE(u.name, 'Admin') as author_name, u.avatar_url as author_avatar
                     FROM polls p LEFT JOIN users u ON p.user_id = u.id
                     WHERE p.tenant_id = ? AND p.is_active = 1 ORDER BY p.created_at DESC LIMIT $fetchLimit";
        $polls = $dbClass::query($sqlPolls, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($polls as $p) {
            $feedItems[] = [
                'type' => 'poll',
                'id' => $p['id'],
                'user_id' => $p['user_id'],
                'author_name' => $p['author_name'],
                'author_avatar' => $p['author_avatar'],
                'title' => $p['question'],
                'body' => $p['description'] ?? '',
                'created_at' => $p['created_at'],
                'likes_count' => 0,
                'comments_count' => 0,
                'is_liked' => false,
                'vote_count' => 0
            ];
        }
    } catch (Exception $e) {}

        try {
        $sqlVols = "SELECT v.*, COALESCE(u.name, 'Organizer') as author_name, u.avatar_url as author_avatar
                    FROM vol_opportunities v LEFT JOIN users u ON v.created_by = u.id
                    WHERE v.tenant_id = ? ORDER BY v.created_at DESC LIMIT $fetchLimit";
        $vols = $dbClass::query($sqlVols, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($vols as $v) {
            $feedItems[] = [
                'type' => 'volunteering',
                'id' => $v['id'],
                'user_id' => $v['created_by'],
                'author_name' => $v['author_name'],
                'author_avatar' => $v['author_avatar'],
                'title' => $v['title'],
                'body' => $v['description'],
                'created_at' => $v['created_at'],
                'likes_count' => 0,
                'comments_count' => 0,
                'is_liked' => false,
                'location' => $v['location'],
                'credits' => $v['credits_offered'] ?? 0
            ];
        }
    } catch (Exception $e) {}

        try {
        $sqlGoals = "SELECT g.*, COALESCE(u.name, 'Unknown') as author_name, u.avatar_url as author_avatar
                     FROM goals g LEFT JOIN users u ON g.user_id = u.id
                     WHERE g.tenant_id = ? ORDER BY g.created_at DESC LIMIT $fetchLimit";
        $goals = $dbClass::query($sqlGoals, [$tenantId])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($goals as $g) {
            $feedItems[] = [
                'type' => 'goal',
                'id' => $g['id'],
                'user_id' => $g['user_id'],
                'author_name' => $g['author_name'],
                'author_avatar' => $g['author_avatar'],
                'title' => $g['title'],
                'body' => $g['description'],
                'created_at' => $g['created_at'],
                'likes_count' => 0,
                'comments_count' => 0,
                'is_liked' => false,
                'target_date' => $g['target_date'] ?? $g['deadline'] ?? null
            ];
        }
    } catch (Exception $e) {}

    // Sort all items by created_at
    usort($feedItems, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $feedItems = array_slice($feedItems, 0, 50);

} catch (Exception $e) {
    $errorMsg = $e->getMessage();
}

// Hero configuration for MadeOpen header
$hTitle = "Community Pulse";
$hSubtitle = "Stay connected with your community's latest updates, posts, and conversations";
$hType = 'Activity Feed';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<style>
/* ================================================================
   CIVICONE FEED - MadeOpen Style with GOV.UK/GDS Principles
   WCAG 2.1 AA Compliant | Dark Mode Support
   ================================================================ */

:root {
    --civic-feed-max-width: 680px;
    --civic-card-radius: 8px;
    --civic-transition: 0.2s ease;
}

/* Feed Container */
.civic-feed-container {
    max-width: var(--civic-feed-max-width);
    margin: 0 auto;
}

/* --------------------------------
   POST COMPOSER
   -------------------------------- */
.civic-composer {
    background: var(--civic-bg-card);
    border: 1px solid var(--civic-border);
    border-radius: var(--civic-card-radius);
    margin-bottom: 24px;
    overflow: hidden;
}

.civic-composer-collapsed {
    padding: 16px;
}

.civic-composer-top {
    display: flex;
    gap: 12px;
    align-items: center;
}

.civic-composer-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--civic-border);
    flex-shrink: 0;
}

.civic-composer-trigger {
    flex: 1;
    background: var(--civic-bg-page);
    border: 1px solid var(--civic-border);
    border-radius: 24px;
    padding: 12px 20px;
    color: var(--civic-text-muted);
    cursor: pointer;
    font-size: 1rem;
    text-align: left;
    transition: var(--civic-transition);
}

.civic-composer-trigger:hover {
    background: var(--civic-bg-card);
    border-color: var(--civic-brand);
}

.civic-composer-trigger:focus {
    outline: 3px solid var(--civic-brand);
    outline-offset: 2px;
}

.civic-composer-divider {
    height: 1px;
    background: var(--civic-border);
    margin: 12px 0;
}

.civic-composer-shortcuts {
    display: flex;
    justify-content: space-around;
    gap: 8px;
}

.civic-composer-shortcut {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--civic-text-secondary);
    cursor: pointer;
    transition: var(--civic-transition);
    background: transparent;
    border: none;
    text-decoration: none;
}

.civic-composer-shortcut:hover {
    background: var(--civic-bg-page);
}

.civic-composer-shortcut.photo .dashicons { color: #22C55E; }
.civic-composer-shortcut.event .dashicons { color: #EF4444; }
.civic-composer-shortcut.listing .dashicons { color: var(--civic-brand); }

/* Expanded Composer */
.civic-composer-expanded {
    display: none;
    padding: 20px;
}

.civic-composer-expanded.active {
    display: block;
}

.civic-composer-header {
    display: flex;
    align-items: center;
    justify-content: center;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--civic-border);
    margin-bottom: 16px;
    position: relative;
}

.civic-composer-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--civic-text-main);
}

.civic-composer-close {
    position: absolute;
    right: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--civic-bg-page);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--civic-text-muted);
    transition: var(--civic-transition);
}

.civic-composer-close:hover {
    background: var(--civic-border);
}

.civic-composer-close:focus {
    outline: 3px solid var(--civic-brand);
    outline-offset: 2px;
}

.civic-composer-user {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.civic-composer-user-name {
    font-weight: 700;
    color: var(--civic-text-main);
}

.civic-composer-textarea {
    width: 100%;
    min-height: 120px;
    border: none;
    background: transparent;
    resize: none;
    font-size: 1.125rem;
    color: var(--civic-text-main);
    outline: none;
    font-family: inherit;
    line-height: 1.5;
}

.civic-composer-textarea::placeholder {
    color: var(--civic-text-muted);
}

.civic-composer-textarea:focus {
    outline: none;
}

.civic-composer-media-preview {
    display: none;
    margin: 16px 0;
    position: relative;
    border-radius: var(--civic-card-radius);
    overflow: hidden;
}

.civic-composer-media-preview.active {
    display: block;
}

.civic-composer-media-preview img {
    max-width: 100%;
    max-height: 300px;
    display: block;
}

.civic-composer-media-remove {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 32px;
    height: 32px;
    background: rgba(0,0,0,0.7);
    color: #fff;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.civic-composer-tools {
    border: 1px solid var(--civic-border);
    border-radius: var(--civic-card-radius);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.civic-composer-tools-label {
    font-weight: 600;
    color: var(--civic-text-main);
}

.civic-composer-tools-icons {
    display: flex;
    gap: 8px;
}

.civic-composer-tool-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--civic-transition);
    background: transparent;
    border: none;
    color: var(--civic-text-secondary);
}

.civic-composer-tool-btn:hover {
    background: var(--civic-bg-page);
}

.civic-composer-tool-btn:focus {
    outline: 3px solid var(--civic-brand);
    outline-offset: 2px;
}

.civic-composer-submit {
    width: 100%;
    padding: 14px;
    background: var(--civic-brand);
    color: #fff;
    border: none;
    border-radius: var(--civic-card-radius);
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: var(--civic-transition);
}

.civic-composer-submit:hover:not(:disabled) {
    filter: brightness(0.9);
}

.civic-composer-submit:disabled {
    background: var(--civic-border);
    color: var(--civic-text-muted);
    cursor: not-allowed;
}

.civic-composer-submit:focus {
    outline: 3px solid var(--civic-brand);
    outline-offset: 2px;
}

/* Guest CTA */
.civic-composer-guest {
    padding: 32px;
    text-align: center;
}

.civic-composer-guest h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--civic-text-main);
    margin-bottom: 8px;
}

.civic-composer-guest p {
    color: var(--civic-text-muted);
    margin-bottom: 16px;
}

/* --------------------------------
   FEED CARDS
   -------------------------------- */
.civic-feed-card {
    background: var(--civic-bg-card);
    border: 1px solid var(--civic-border);
    border-radius: var(--civic-card-radius);
    margin-bottom: 16px;
    overflow: hidden;
    transition: var(--civic-transition);
}

.civic-feed-card:hover {
    box-shadow: var(--civic-shadow);
}

/* Feed Header */
.civic-feed-header {
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.civic-feed-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.civic-feed-meta {
    flex: 1;
    min-width: 0;
}

.civic-feed-author {
    font-size: 1rem;
    font-weight: 700;
    color: var(--civic-text-main);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
}

.civic-feed-author a {
    color: var(--civic-text-main);
    text-decoration: none;
}

.civic-feed-author a:hover {
    text-decoration: underline;
}

.civic-feed-verb {
    font-weight: 400;
    color: var(--civic-text-secondary);
}

.civic-feed-object {
    font-weight: 700;
    color: var(--civic-brand);
}

.civic-feed-time {
    font-size: 0.875rem;
    color: var(--civic-text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 2px;
}

.civic-feed-menu {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--civic-transition);
    background: transparent;
    border: none;
    color: var(--civic-text-muted);
}

.civic-feed-menu:hover {
    background: var(--civic-bg-page);
}

/* Feed Body */
.civic-feed-body {
    padding: 0 16px 16px;
}

.civic-feed-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--civic-text-main);
    margin-bottom: 8px;
}

.civic-feed-content {
    font-size: 1rem;
    line-height: 1.6;
    color: var(--civic-text-main);
    white-space: pre-wrap;
    word-wrap: break-word;
}

.civic-feed-content a {
    color: var(--civic-brand);
}

.civic-feed-image {
    width: 100%;
    display: block;
    border-top: 1px solid var(--civic-border);
}

/* Type-Specific Banners */
.civic-type-banner {
    padding: 20px 16px;
    border-top: 1px solid var(--civic-border);
}

.civic-type-banner-listing {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
    border-left: 4px solid var(--civic-brand);
}

.civic-type-banner-listing.request {
    background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
    border-left-color: #F59E0B;
}

.civic-type-banner-event {
    background: linear-gradient(135deg, #FDF2F8 0%, #FCE7F3 100%);
    border-left: 4px solid #DB2777;
}

.civic-type-banner-goal {
    background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
    border-left: 4px solid #22C55E;
}

.civic-type-banner-poll {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
    border-left: 4px solid #3B82F6;
}

.civic-type-banner-volunteering {
    background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
    border-left: 4px solid #0EA5E9;
}

body.dark-mode .civic-type-banner-listing {
    background: linear-gradient(135deg, #1E3A5F 0%, #1E40AF 100%);
}

body.dark-mode .civic-type-banner-listing.request {
    background: linear-gradient(135deg, #451A03 0%, #78350F 100%);
}

body.dark-mode .civic-type-banner-event {
    background: linear-gradient(135deg, #500724 0%, #831843 100%);
}

body.dark-mode .civic-type-banner-goal {
    background: linear-gradient(135deg, #052E16 0%, #14532D 100%);
}

body.dark-mode .civic-type-banner-poll {
    background: linear-gradient(135deg, #1E3A5F 0%, #1E40AF 100%);
}

body.dark-mode .civic-type-banner-volunteering {
    background: linear-gradient(135deg, #082F49 0%, #0C4A6E 100%);
}

.civic-type-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    margin-bottom: 8px;
}

.civic-type-title {
    font-weight: 700;
    font-size: 1.125rem;
    color: var(--civic-text-main);
    margin-bottom: 8px;
}

.civic-type-meta {
    font-size: 0.875rem;
    color: var(--civic-text-secondary);
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.civic-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    background: #fff;
    border-radius: 16px;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1px solid var(--civic-border);
}

body.dark-mode .civic-type-badge {
    background: var(--civic-bg-card);
}

.civic-view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    background: var(--civic-brand);
    color: #fff;
    border-radius: 6px;
    font-size: 0.9375rem;
    font-weight: 700;
    text-decoration: none;
    transition: var(--civic-transition);
    width: 100%;
}

.civic-view-btn:hover {
    filter: brightness(0.9);
    color: #fff;
}

.civic-view-btn:focus {
    outline: 3px solid var(--civic-brand);
    outline-offset: 2px;
}

.civic-view-btn--secondary {
    background: var(--civic-bg-card);
    color: var(--civic-text-main);
    border: 2px solid var(--civic-border);
}

.civic-view-btn--secondary:hover {
    background: var(--civic-bg-page);
    filter: none;
    color: var(--civic-text-main);
}

/* Feed Stats */
.civic-feed-stats {
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9375rem;
    color: var(--civic-text-secondary);
    border-top: 1px solid var(--civic-border);
    border-bottom: 1px solid var(--civic-border);
}

.civic-feed-stats-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.civic-feed-stats-right {
    cursor: pointer;
}

.civic-feed-stats-right:hover {
    text-decoration: underline;
}

/* Feed Actions */
.civic-feed-actions {
    display: flex;
    padding: 4px 8px;
}

.civic-feed-action-btn {
    flex: 1;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: var(--civic-transition);
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--civic-text-secondary);
    background: transparent;
    border: none;
}

.civic-feed-action-btn:hover {
    background: var(--civic-bg-page);
}

.civic-feed-action-btn:focus {
    outline: 3px solid var(--civic-brand);
    outline-offset: -3px;
}

.civic-feed-action-btn.liked {
    color: #D4351C;
}

/* Comments Section */
.civic-comments-section {
    display: none;
    padding: 16px;
    background: var(--civic-bg-page);
    border-top: 1px solid var(--civic-border);
}

.civic-comments-section.active {
    display: block;
}

.civic-comment-form {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}

.civic-comment-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.civic-comment-input-wrapper {
    flex: 1;
    position: relative;
}

.civic-comment-input {
    width: 100%;
    padding: 10px 44px 10px 16px;
    border: 1px solid var(--civic-border);
    background: var(--civic-bg-card);
    border-radius: 24px;
    font-size: 0.9375rem;
    color: var(--civic-text-main);
    outline: none;
}

.civic-comment-input:focus {
    border-color: var(--civic-brand);
    outline: 3px solid var(--civic-brand);
    outline-offset: 2px;
}

.civic-comment-input::placeholder {
    color: var(--civic-text-muted);
}

.civic-comment-submit {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--civic-brand);
    cursor: pointer;
    background: none;
    border: none;
    padding: 8px;
}

.civic-comment-submit:hover {
    opacity: 0.8;
}

.civic-comment-item {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
}

.civic-comment-bubble {
    background: var(--civic-bg-card);
    padding: 10px 16px;
    border-radius: 18px;
    border: 1px solid var(--civic-border);
}

.civic-comment-author {
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--civic-text-main);
    margin-bottom: 2px;
}

.civic-comment-text {
    font-size: 0.9375rem;
    color: var(--civic-text-main);
    line-height: 1.4;
}

/* Shared/Reposted Content */
.civic-shared-card {
    margin: 12px 16px;
    border: 1px solid var(--civic-border);
    border-radius: var(--civic-card-radius);
    overflow: hidden;
    background: var(--civic-bg-page);
}

.civic-shared-header {
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid var(--civic-border);
}

.civic-shared-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.civic-shared-author {
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--civic-text-main);
}

.civic-shared-label {
    font-weight: 400;
    color: var(--civic-text-muted);
    font-size: 0.75rem;
}

.civic-shared-time {
    font-size: 0.75rem;
    color: var(--civic-text-muted);
}

.civic-shared-body {
    padding: 12px;
}

.civic-shared-title {
    font-weight: 700;
    color: var(--civic-text-main);
    margin-bottom: 8px;
}

.civic-shared-content {
    font-size: 0.875rem;
    color: var(--civic-text-main);
    line-height: 1.4;
}

/* Empty State */
.civic-empty-state {
    background: var(--civic-bg-card);
    border: 1px solid var(--civic-border);
    border-radius: var(--civic-card-radius);
    padding: 60px 32px;
    text-align: center;
}

.civic-empty-icon {
    font-size: 3rem;
    margin-bottom: 16px;
}

.civic-empty-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--civic-text-main);
    margin-bottom: 8px;
}

.civic-empty-text {
    color: var(--civic-text-muted);
    margin-bottom: 24px;
}

/* Toast - positioned above bottom nav */
.civic-toast {
    position: fixed;
    bottom: calc(90px + env(safe-area-inset-bottom, 0px));
    left: 50%;
    transform: translateX(-50%);
    background: var(--civic-brand);
    color: #fff;
    padding: 14px 28px;
    border-radius: var(--civic-card-radius);
    font-weight: 600;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    max-width: calc(100% - 32px);
    text-align: center;
}

.civic-toast.show {
    opacity: 1;
    visibility: visible;
    bottom: calc(100px + env(safe-area-inset-bottom, 0px));
}

/* Mobile Responsive */
@media (max-width: 600px) {
    .civic-composer-shortcuts {
        flex-direction: column;
    }

    .civic-composer-shortcut span {
        display: none;
    }

    .civic-feed-action-btn span {
        display: none;
    }
}
</style>

<main id="main-content" role="main">
<div class="civic-container">
    <div class="civic-feed-container">

        <!-- Post Composer -->
        <?php if ($isLoggedIn): ?>
            <div class="civic-composer">
                <div class="civic-composer-collapsed" id="composer-collapsed">
                    <div class="civic-composer-top">
                        <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp') ?>"
                             alt="" class="civic-composer-avatar">
                        <button type="button" class="civic-composer-trigger" onclick="toggleComposer(true)" aria-expanded="false">
                            What's on your mind, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'there')[0]) ?>?
                        </button>
                    </div>
                    <div class="civic-composer-divider"></div>
                    <div class="civic-composer-shortcuts">
                        <button type="button" class="civic-composer-shortcut photo" onclick="toggleComposer(true); setTimeout(() => document.getElementById('post-image-input').click(), 100);">
                            <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                            <span>Photo</span>
                        </button>
                        <a href="<?= $basePath ?>/events/create" class="civic-composer-shortcut event">
                            <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                            <span>Event</span>
                        </a>
                        <a href="<?= $basePath ?>/listings/create" class="civic-composer-shortcut listing">
                            <span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
                            <span>Listing</span>
                        </a>
                    </div>
                </div>

                <div id="composer-expanded" class="civic-composer-expanded">
                    <div class="civic-composer-header">
                        <span class="civic-composer-title">Create Post</span>
                        <button type="button" class="civic-composer-close" onclick="toggleComposer(false)" aria-label="Close composer">
                            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                        </button>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="civic-composer-user">
                            <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp') ?>"
                                 alt="" class="civic-composer-avatar">
                            <span class="civic-composer-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                        </div>

                        <input type="file" id="post-image-input" name="image" accept="image/*" style="display:none;" onchange="previewImage(this)">

                        <div id="image-preview-area" class="civic-composer-media-preview">
                            <img id="image-preview-img" src="" alt="Preview">
                            <button type="button" class="civic-composer-media-remove" onclick="removeImage()" aria-label="Remove image">
                                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                            </button>
                        </div>

                        <textarea id="composer-input" name="content" class="civic-composer-textarea"
                                  placeholder="What's on your mind?" oninput="updateSubmitButton()"
                                  aria-label="Post content"></textarea>

                        <div class="civic-composer-tools">
                            <span class="civic-composer-tools-label">Add to your post</span>
                            <div class="civic-composer-tools-icons">
                                <button type="button" class="civic-composer-tool-btn" onclick="document.getElementById('post-image-input').click()" title="Add Photo" aria-label="Add photo">
                                    <span class="dashicons dashicons-format-image" style="color: #22C55E;" aria-hidden="true"></span>
                                </button>
                                <button type="button" class="civic-composer-tool-btn" onclick="showToast('Location feature coming soon!')" title="Add Location" aria-label="Add location">
                                    <span class="dashicons dashicons-location" style="color: #EF4444;" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>

                        <button type="submit" id="composer-submit" class="civic-composer-submit" disabled>Post</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="civic-composer">
                <div class="civic-composer-guest">
                    <h3>Join the conversation</h3>
                    <p>Sign in to post, comment, and connect with your community.</p>
                    <a href="<?= $basePath ?>/login" class="civic-btn">
                        <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                        Sign In
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Feed Stream -->
        <?php if (!empty($feedItems)): ?>
            <?php foreach ($feedItems as $item):
                $type = $item['type'];
                $postId = $item['id'];
                $authorName = $item['author_name'] ?? 'Unknown';
                $authorAvatar = $item['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
                $authorId = $item['user_id'] ?? 0;
                $createdAt = $item['created_at'];
                $isLiked = !empty($item['is_liked']);
                $likesCount = $item['likes_count'] ?? 0;
                $commentsCount = $item['comments_count'] ?? 0;
                $bodyContent = $item['body'] ?? '';
                $location = $item['location'] ?? null;

                // Activity verb & link
                $verb = '';
                $object = '';
                $viewLink = null;

                switch ($type) {
                    case 'listing':
                        $verb = 'posted a';
                        $object = strtoupper($item['listing_type'] ?? 'LISTING');
                        $viewLink = $basePath . '/listings/' . $postId;
                        break;
                    case 'event':
                        $verb = 'created an event';
                        $viewLink = $basePath . '/events/' . $postId;
                        break;
                    case 'goal':
                        $verb = 'set a goal';
                        $viewLink = $basePath . '/goals/' . $postId;
                        break;
                    case 'poll':
                        $verb = 'created a poll';
                        $viewLink = $basePath . '/polls/' . $postId;
                        break;
                    case 'volunteering':
                        $verb = 'needs volunteers';
                        $viewLink = $basePath . '/volunteering/' . $postId;
                        break;
                }
            ?>
                <article class="civic-feed-card" id="feed-<?= $type ?>-<?= $postId ?>">
                    <!-- Header -->
                    <div class="civic-feed-header">
                        <a href="<?= $basePath ?>/profile/<?= $authorId ?>">
                            <img src="<?= htmlspecialchars($authorAvatar) ?>" class="civic-feed-avatar" alt="<?= htmlspecialchars($authorName) ?>">
                        </a>
                        <div class="civic-feed-meta">
                            <div class="civic-feed-author">
                                <a href="<?= $basePath ?>/profile/<?= $authorId ?>"><?= htmlspecialchars($authorName) ?></a>
                                <?php if ($verb): ?>
                                    <span class="civic-feed-verb"><?= $verb ?></span>
                                    <?php if ($object): ?>
                                        <span class="civic-feed-object"><?= htmlspecialchars($object) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="civic-feed-time">
                                <time datetime="<?= $createdAt ?>"><?= $timeElapsed($createdAt) ?></time>
                                <?php if ($location): ?>
                                    <span aria-hidden="true">·</span>
                                    <span class="dashicons dashicons-location" style="font-size: 14px;" aria-hidden="true"></span>
                                    <?= htmlspecialchars($location) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($isLoggedIn && ($authorId == $userId || ($_SESSION['user_role'] ?? '') === 'admin') && $type === 'post'): ?>
                            <button class="civic-feed-menu" onclick="deletePost('<?= $type ?>', <?= $postId ?>)" title="Delete post" aria-label="Delete post">
                                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Body -->
                    <div class="civic-feed-body">
                        <?php if (!empty($item['title']) && $type !== 'post'): ?>
                            <h3 class="civic-feed-title"><?= htmlspecialchars($item['title']) ?></h3>
                        <?php endif; ?>

                        <?php
                        // Handle shared/reposted content
                        if ($type === 'post' && !empty($item['parent_id']) && $item['parent_id'] > 0) {
                            $parentId = (int) $item['parent_id'];
                            $parentType = $item['parent_type'] ?? 'post';
                            $sharedData = null;
                            $sharedDbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';

                            try {
                                if ($parentType === 'post') {
                                    $p = $sharedDbClass::query("SELECT p.*, u.name as author_name, u.avatar_url as author_avatar, u.id as user_id FROM feed_posts p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?", [$parentId])->fetch(PDO::FETCH_ASSOC);
                                    if ($p) {
                                        $sharedData = [
                                            'author' => $p['author_name'] ?? 'Unknown',
                                            'avatar' => $p['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                                            'user_id' => $p['user_id'],
                                            'time' => $timeElapsed($p['created_at']),
                                            'content' => $p['content'],
                                            'image' => $p['image_url'] ?? null,
                                            'title' => null,
                                            'label' => 'Shared Post',
                                            'link' => null
                                        ];
                                    }
                                } elseif ($parentType === 'listing') {
                                    $l = $sharedDbClass::query("SELECT l.*, u.name as author_name, u.avatar_url as author_avatar, u.id as user_id FROM listings l LEFT JOIN users u ON l.user_id = u.id WHERE l.id = ?", [$parentId])->fetch(PDO::FETCH_ASSOC);
                                    if ($l) {
                                        $sharedData = [
                                            'author' => $l['author_name'] ?? 'Unknown',
                                            'avatar' => $l['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                                            'user_id' => $l['user_id'],
                                            'time' => $timeElapsed($l['created_at']),
                                            'content' => $l['description'],
                                            'image' => $l['image_url'] ?? null,
                                            'title' => ($l['title'] ?? 'Listing') . ' (' . ucfirst($l['type'] ?? 'offer') . ')',
                                            'label' => 'Shared Listing',
                                            'link' => $basePath . '/listings/' . $parentId
                                        ];
                                    }
                                } elseif ($parentType === 'event') {
                                    $ev = $sharedDbClass::query("SELECT e.*, u.name as author_name, u.avatar_url as author_avatar, u.id as user_id FROM events e LEFT JOIN users u ON e.user_id = u.id WHERE e.id = ?", [$parentId])->fetch(PDO::FETCH_ASSOC);
                                    if ($ev) {
                                        $sharedData = [
                                            'author' => $ev['author_name'] ?? 'Unknown',
                                            'avatar' => $ev['author_avatar'] ?? '/assets/img/defaults/default_avatar.webp',
                                            'user_id' => $ev['user_id'],
                                            'time' => $timeElapsed($ev['created_at']),
                                            'content' => $ev['description'],
                                            'image' => $ev['cover_image'] ?? null,
                                            'title' => $ev['title'] ?? 'Event',
                                            'label' => 'Shared Event',
                                            'link' => $basePath . '/events/' . $parentId
                                        ];
                                    }
                                }
                            } catch (Exception $ex) {}

                            // User's caption
                            $content = htmlspecialchars($bodyContent);
                            $content = preg_replace_callback('/(https?:\/\/[^\s]+)/', function($m) {
                                $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                                return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
                            }, $content);
                            if (!empty(trim($content))) {
                                echo '<div class="civic-feed-content">' . nl2br($content) . '</div>';
                            }

                            // Render shared card
                            if ($sharedData): ?>
                                <div class="civic-shared-card">
                                    <div class="civic-shared-header">
                                        <a href="<?= $basePath ?>/profile/<?= $sharedData['user_id'] ?>">
                                            <img src="<?= htmlspecialchars($sharedData['avatar']) ?>" class="civic-shared-avatar" alt="">
                                        </a>
                                        <div>
                                            <div class="civic-shared-author">
                                                <a href="<?= $basePath ?>/profile/<?= $sharedData['user_id'] ?>" style="color: inherit; text-decoration: none;">
                                                    <?= htmlspecialchars($sharedData['author']) ?>
                                                </a>
                                                <span class="civic-shared-label">· <?= $sharedData['label'] ?></span>
                                            </div>
                                            <div class="civic-shared-time"><?= $sharedData['time'] ?></div>
                                        </div>
                                    </div>
                                    <div class="civic-shared-body">
                                        <?php if (!empty($sharedData['title'])): ?>
                                            <div class="civic-shared-title"><?= htmlspecialchars($sharedData['title']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($sharedData['content'])): ?>
                                            <div class="civic-shared-content"><?= nl2br(htmlspecialchars(substr($sharedData['content'], 0, 300))) ?><?= strlen($sharedData['content']) > 300 ? '...' : '' ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($sharedData['image'])): ?>
                                        <img src="<?= htmlspecialchars($sharedData['image']) ?>" style="width: 100%; display: block;" alt="">
                                    <?php endif; ?>
                                    <?php if (!empty($sharedData['link'])): ?>
                                        <div style="padding: 12px; border-top: 1px solid var(--civic-border);">
                                            <a href="<?= $sharedData['link'] ?>" class="civic-view-btn civic-view-btn--secondary">
                                                View Original
                                                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif;
                        } else {
                            // Standard content rendering
                            $content = htmlspecialchars($bodyContent);
                            $content = preg_replace_callback('/(https?:\/\/[^\s]+)/', function($m) {
                                $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                                return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
                            }, $content);
                            echo '<div class="civic-feed-content">' . nl2br($content) . '</div>';
                        }
                        ?>
                    </div>

                    <!-- Post Image -->
                    <?php if ($type === 'post' && !empty($item['image_url'])): ?>
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" class="civic-feed-image" alt="Post image">
                    <?php endif; ?>

                    <!-- Type-specific banners -->
                    <?php if ($type === 'listing'):
                        $listingType = $item['listing_type'] ?? 'offer';
                    ?>
                        <div class="civic-type-banner civic-type-banner-listing <?= $listingType === 'request' ? 'request' : '' ?>">
                            <div class="civic-type-label" style="color: <?= $listingType === 'offer' ? 'var(--civic-brand)' : '#F59E0B' ?>;">
                                <?= ucfirst($listingType) ?>
                            </div>
                            <div class="civic-type-title"><?= htmlspecialchars($item['title'] ?? '') ?></div>
                            <?php if ($location): ?>
                                <div class="civic-type-meta">
                                    <span class="dashicons dashicons-location" aria-hidden="true"></span>
                                    <?= htmlspecialchars($location) ?>
                                </div>
                            <?php endif; ?>
                            <a href="<?= $viewLink ?>" class="civic-view-btn">
                                View Listing
                                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($type === 'event'): ?>
                        <div class="civic-type-banner civic-type-banner-event">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div class="civic-type-label" style="color: #DB2777;">Upcoming Event</div>
                                <?php if (!empty($item['event_date'])): ?>
                                    <span class="civic-type-badge" style="color: #DB2777;">
                                        <span class="dashicons dashicons-calendar" aria-hidden="true"></span>
                                        <?= date('M j', strtotime($item['event_date'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="civic-type-title"><?= htmlspecialchars($item['title'] ?? 'Event') ?></div>
                            <div class="civic-type-meta">
                                <span class="dashicons dashicons-location" style="color: #DB2777;" aria-hidden="true"></span>
                                <?= htmlspecialchars($location ?? 'Location TBD') ?>
                            </div>
                            <a href="<?= $viewLink ?>" class="civic-view-btn" style="background: #DB2777;">
                                RSVP Now
                                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($type === 'goal'): ?>
                        <div class="civic-type-banner civic-type-banner-goal">
                            <div class="civic-type-label" style="color: #22C55E;">Community Goal</div>
                            <div class="civic-type-title"><?= htmlspecialchars($item['title'] ?? 'Goal') ?></div>
                            <div class="civic-type-meta">
                                <span class="dashicons dashicons-calendar-alt" style="color: #22C55E;" aria-hidden="true"></span>
                                Target: <?= !empty($item['target_date']) ? date('M j, Y', strtotime($item['target_date'])) : 'No Deadline' ?>
                            </div>
                            <a href="<?= $viewLink ?>" class="civic-view-btn civic-view-btn--secondary">
                                View Goal
                                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($type === 'poll'): ?>
                        <div class="civic-type-banner civic-type-banner-poll">
                            <div class="civic-type-label" style="color: #3B82F6;">Community Poll</div>
                            <div class="civic-type-title"><?= htmlspecialchars($item['title'] ?? 'Poll') ?></div>
                            <div class="civic-type-meta">
                                <span class="dashicons dashicons-chart-bar" style="color: #3B82F6;" aria-hidden="true"></span>
                                <?= $item['vote_count'] ?? 0 ?> votes
                            </div>
                            <a href="<?= $viewLink ?>" class="civic-view-btn" style="background: #3B82F6;">
                                Vote Now
                                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($type === 'volunteering'): ?>
                        <div class="civic-type-banner civic-type-banner-volunteering">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div class="civic-type-label" style="color: #0EA5E9;">Volunteer Opportunity</div>
                                <span class="civic-type-badge" style="color: #0EA5E9;">
                                    <?= $item['credits'] ?? 0 ?> Credits
                                </span>
                            </div>
                            <div class="civic-type-title"><?= htmlspecialchars($item['title'] ?? 'Opportunity') ?></div>
                            <div class="civic-type-meta">
                                <span class="dashicons dashicons-location" style="color: #0EA5E9;" aria-hidden="true"></span>
                                <?= htmlspecialchars($location ?? 'Remote') ?>
                            </div>
                            <a href="<?= $viewLink ?>" class="civic-view-btn" style="background: #0EA5E9;">
                                I'm Interested
                                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Stats -->
                    <div class="civic-feed-stats">
                        <div class="civic-feed-stats-left">
                            <?php if ($likesCount > 0): ?>
                                <span class="dashicons dashicons-heart" style="color: #D4351C; font-size: 16px;" aria-hidden="true"></span>
                                <span><?= $likesCount ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="civic-feed-stats-right" onclick="toggleCommentSection('<?= $type ?>', <?= $postId ?>)">
                            <?= $commentsCount > 0 ? $commentsCount . ' Comment' . ($commentsCount > 1 ? 's' : '') : '' ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="civic-feed-actions">
                        <button type="button" onclick="toggleLike(this, '<?= $type ?>', <?= $postId ?>)"
                                class="civic-feed-action-btn <?= $isLiked ? 'liked' : '' ?>"
                                aria-pressed="<?= $isLiked ? 'true' : 'false' ?>">
                            <span class="dashicons <?= $isLiked ? 'dashicons-heart' : 'dashicons-heart' ?>" aria-hidden="true"></span>
                            <span>Like</span>
                        </button>
                        <button type="button" onclick="toggleCommentSection('<?= $type ?>', <?= $postId ?>)" class="civic-feed-action-btn">
                            <span class="dashicons dashicons-admin-comments" aria-hidden="true"></span>
                            <span>Comment</span>
                        </button>
                        <button type="button" onclick="shareToFeed('<?= $type ?>', <?= $postId ?>, '<?= addslashes($authorName) ?>')" class="civic-feed-action-btn">
                            <span class="dashicons dashicons-share" aria-hidden="true"></span>
                            <span>Share</span>
                        </button>
                    </div>

                    <!-- Comments Section -->
                    <div id="comments-section-<?= $type ?>-<?= $postId ?>" class="civic-comments-section" aria-label="Comments">
                        <?php if ($isLoggedIn): ?>
                            <div class="civic-comment-form">
                                <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp') ?>"
                                     class="civic-comment-avatar" alt="">
                                <div class="civic-comment-input-wrapper">
                                    <input type="text" class="civic-comment-input"
                                           placeholder="Write a comment..."
                                           onkeydown="if(event.key === 'Enter') submitComment(this, '<?= $type ?>', <?= $postId ?>)"
                                           aria-label="Write a comment">
                                    <button type="button" class="civic-comment-submit" onclick="submitComment(this.parentElement.querySelector('input'), '<?= $type ?>', <?= $postId ?>)" aria-label="Submit comment">
                                        <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="comments-list"></div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="civic-empty-state">
                <div class="civic-empty-icon" aria-hidden="true">
                    <span class="dashicons dashicons-megaphone" style="font-size: 48px; width: 48px; height: 48px;"></span>
                </div>
                <h3 class="civic-empty-title">Welcome to the Community Pulse</h3>
                <p class="civic-empty-text">It's quiet here... Be the first to post something!</p>
                <?php if ($isLoggedIn): ?>
                    <button type="button" onclick="toggleComposer(true)" class="civic-btn">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                        Create Post
                    </button>
                <?php else: ?>
                    <a href="<?= $basePath ?>/login" class="civic-btn">
                        <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                        Sign In to Post
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>
</main>

<!-- Toast Notification -->
<div class="civic-toast" id="civic-toast" role="alert" aria-live="polite"></div>

<script>
const IS_LOGGED_IN = <?= json_encode($isLoggedIn) ?>;
const BASE_URL = "<?= $basePath ?>";
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
let currentTargetType = '';
let currentTargetId = 0;

// Helper to add CSRF token to FormData
function appendCsrf(formData) {
    formData.append('csrf_token', CSRF_TOKEN);
    return formData;
}

// Composer Functions
function toggleComposer(show) {
    const collapsed = document.getElementById('composer-collapsed');
    const expanded = document.getElementById('composer-expanded');
    const trigger = collapsed?.querySelector('.civic-composer-trigger');

    if (show) {
        if (collapsed) collapsed.style.display = 'none';
        if (expanded) {
            expanded.classList.add('active');
            document.getElementById('composer-input')?.focus();
        }
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
    } else {
        if (collapsed) collapsed.style.display = 'block';
        if (expanded) expanded.classList.remove('active');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }
}

function updateSubmitButton() {
    const input = document.getElementById('composer-input');
    const submit = document.getElementById('composer-submit');
    const hasImage = document.getElementById('image-preview-area')?.classList.contains('active');
    if (submit) {
        submit.disabled = !(input?.value.trim() || hasImage);
    }
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('image-preview-img');
            const area = document.getElementById('image-preview-area');
            if (preview) preview.src = e.target.result;
            if (area) area.classList.add('active');
            updateSubmitButton();
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    const input = document.getElementById('post-image-input');
    const area = document.getElementById('image-preview-area');
    if (input) input.value = '';
    if (area) area.classList.remove('active');
    updateSubmitButton();
}

// Toast
function showToast(message) {
    const toast = document.getElementById('civic-toast');
    if (toast) {
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
}

// Like Toggle
function toggleLike(btn, type, id) {
    if (!IS_LOGGED_IN) {
        showToast('Please sign in to like posts');
        return;
    }

    const icon = btn.querySelector('.dashicons');
    const isLiked = btn.classList.contains('liked');

    // Optimistic UI
    btn.classList.toggle('liked');
    btn.setAttribute('aria-pressed', !isLiked);

    const formData = new FormData();
    formData.append('action', 'toggle_like');
    formData.append('target_type', type);
    formData.append('target_id', id);

    fetch(window.location.href, { method: 'POST', body: appendCsrf(formData) })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                // Revert on error
                btn.classList.toggle('liked');
                btn.setAttribute('aria-pressed', isLiked);
                showToast(data.error);
            }
        })
        .catch(() => {
            btn.classList.toggle('liked');
            btn.setAttribute('aria-pressed', isLiked);
        });
}

// Comments - toggleCommentSection is defined globally by mobile-sheets.php
// It handles both mobile (opens sheet) and desktop (uses this fallback)
window._feedDesktopToggleComment = function(type, id) {
    const section = document.getElementById(`comments-section-${type}-${id}`);
    if (!section) return;

    if (section.classList.contains('active')) {
        section.classList.remove('active');
    } else {
        section.classList.add('active');
        const input = section.querySelector('input');
        if (input) input.focus();
        fetchComments(type, id);
    }
};
// Register this as the desktop handler for mobile-sheets to use
if (typeof window.toggleCommentSection === 'undefined') {
    // Mobile-sheets not loaded yet, define a temporary that will be captured
    window.toggleCommentSection = window._feedDesktopToggleComment;
}

function fetchComments(type, id) {
    const section = document.getElementById(`comments-section-${type}-${id}`);
    const list = section?.querySelector('.comments-list');
    if (!list) return;

    list.innerHTML = '<div style="color: var(--civic-text-muted); text-align: center; padding: 16px;">Loading comments...</div>';

    currentTargetType = type;
    currentTargetId = id;

    const formData = new FormData();
    formData.append('action', 'fetch_comments');
    formData.append('target_type', type);
    formData.append('target_id', id);

    fetch(window.location.href, { method: 'POST', body: appendCsrf(formData) })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.comments && data.comments.length > 0) {
                list.innerHTML = data.comments.map(c => `
                    <div class="civic-comment-item">
                        <img src="${escapeHtml(c.author_avatar)}" class="civic-comment-avatar" alt="">
                        <div class="civic-comment-bubble">
                            <div class="civic-comment-author">${escapeHtml(c.author_name)}</div>
                            <div class="civic-comment-text">${escapeHtml(c.content)}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                list.innerHTML = '<div style="color: var(--civic-text-muted); text-align: center; padding: 16px;">No comments yet. Be the first!</div>';
            }
        })
        .catch(() => {
            list.innerHTML = '<div style="color: #D4351C; text-align: center; padding: 16px;">Error loading comments</div>';
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

    fetch(window.location.href, { method: 'POST', body: appendCsrf(formData) })
        .then(res => res.json())
        .then(data => {
            input.disabled = false;
            if (data.status === 'success') {
                input.value = '';
                fetchComments(type, id);
                showToast('Comment posted!');
            } else if (data.error) {
                showToast(data.error);
            }
        })
        .catch(() => {
            input.disabled = false;
            showToast('Failed to post comment');
        });
}

// Share
function shareToFeed(type, id, author) {
    if (!IS_LOGGED_IN) {
        showToast('Please sign in to share');
        return;
    }

    if (!confirm('Share this to your feed?')) return;

    const formData = new FormData();
    formData.append('action', 'share_repost');
    formData.append('parent_id', id);
    formData.append('parent_type', type);

    fetch(window.location.href, { method: 'POST', body: appendCsrf(formData) })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('Shared to your feed!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.error || 'Share failed');
            }
        })
        .catch(() => showToast('Share failed'));
}

// Delete Post
function deletePost(type, id) {
    if (!confirm('Delete this post?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_post');
    formData.append('target_type', type);
    formData.append('target_id', id);

    fetch(window.location.href, { method: 'POST', body: appendCsrf(formData) })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'deleted') {
                showToast('Post deleted');
                const card = document.getElementById(`feed-${type}-${id}`);
                if (card) card.remove();
            } else {
                showToast(data.error || 'Delete failed');
            }
        })
        .catch(() => showToast('Delete failed'));
}

// Utility
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
