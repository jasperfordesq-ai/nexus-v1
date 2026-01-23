<?php
// Phoenix View: User Profile
// Path: views/modern/profile/show.php

// ---------------------------------------------------------
// PORTED LOGIC: FEED & POST BOX (Nexus Social v4.2 Logic)
// ---------------------------------------------------------

// 2. SETUP & AUTH
if (session_status() === PHP_SESSION_NONE) session_start();
$currentUserId = $_SESSION['user_id'] ?? 0;
$isLoggedIn = !empty($currentUserId);
// $user is passed from Controller
$targetUserId = $user['id'] ?? 0;
$isOwner = ($currentUserId == $targetUserId);
$tenantId = $_SESSION['current_tenant_id'] ?? 1;

// 3. BACKEND HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. HANDLE PROFILE POST SUBMISSION
    if (isset($_POST['content']) && !isset($_POST['action'])) {
        $content = trim($_POST['content']);
        $vis = $_POST['visibility'] ?? 'public';
        if (!in_array($vis, ['public', 'private', 'connections'])) $vis = 'public';

        $imageUrl = null;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // SECURITY: Validate file type using finfo (not just extension)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['image']['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (in_array($mimeType, $allowedMimes)) {
                // SECURITY: Validate it's actually an image
                $imageInfo = @getimagesize($_FILES['image']['tmp_name']);
                if ($imageInfo !== false && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
                    $uploadDir = __DIR__ . '/../../../httpdocs/uploads/posts/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                    // SECURITY: Generate safe filename with proper extension
                    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                    $ext = $extensions[$mimeType] ?? 'jpg';
                    $fileName = bin2hex(random_bytes(16)) . '.' . $ext;
                    $targetFile = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                        $imageUrl = '/uploads/posts/' . $fileName;
                    }
                }
            }
        }

        if ($content || $imageUrl) {
            if (class_exists('\Nexus\Core\DatabaseWrapper')) {
                \Nexus\Core\DatabaseWrapper::insert('feed_posts', [
                    'user_id' => $currentUserId,
                    'tenant_id' => $tenantId,
                    'content' => $content,
                    'image_url' => $imageUrl,
                    'visibility' => $vis,
                    'likes_count' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    if (isset($_POST['action'])) {
        ob_clean();
        header('Content-Type: application/json');
        if (!$currentUserId) {
            echo json_encode(['error' => 'Login required']);
            exit;
        }
        $targetType = $_POST['target_type'] ?? '';
        $targetId = (int)($_POST['target_id'] ?? 0);
        try {
            if ($_POST['action'] === 'toggle_like') {
                $existing = \Nexus\Core\DatabaseWrapper::query("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?", [$currentUserId, $targetType, $targetId])->fetch();
                if ($existing) {
                    \Nexus\Core\DatabaseWrapper::query("DELETE FROM likes WHERE id = ?", [$existing['id']]);
                    if ($targetType === 'post') \Nexus\Core\DatabaseWrapper::query("UPDATE feed_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?", [$targetId]);
                    $countResult = \Nexus\Core\DatabaseWrapper::query("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?", [$targetType, $targetId])->fetch();
                    echo json_encode(['status' => 'unliked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                } else {
                    \Nexus\Core\DatabaseWrapper::query("INSERT INTO likes (user_id, target_type, target_id, tenant_id) VALUES (?, ?, ?, ?)", [$currentUserId, $targetType, $targetId, $tenantId]);
                    if ($targetType === 'post') \Nexus\Core\DatabaseWrapper::query("UPDATE feed_posts SET likes_count = likes_count + 1 WHERE id = ?", [$targetId]);

                    // Send notification (platform + email) to content owner
                    if (class_exists('\Nexus\Services\SocialNotificationService')) {
                        $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                        if ($contentOwnerId && $contentOwnerId != $currentUserId) {
                            $contentPreview = \Nexus\Services\SocialNotificationService::getContentPreview($targetType, $targetId);
                            \Nexus\Services\SocialNotificationService::notifyLike($contentOwnerId, $currentUserId, $targetType, $targetId, $contentPreview);
                        }
                    }

                    $countResult = \Nexus\Core\DatabaseWrapper::query("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?", [$targetType, $targetId])->fetch();
                    echo json_encode(['status' => 'liked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                }
            } elseif ($_POST['action'] === 'submit_comment') {
                $content = trim($_POST['content']);
                if ($content) {
                    // Use CommentService for @mention support
                    if (class_exists('\Nexus\Services\CommentService')) {
                        $result = \Nexus\Services\CommentService::addComment($currentUserId, $tenantId, $targetType, $targetId, $content);

                        // Send notification to content owner
                        if ($result['status'] === 'success' && class_exists('\Nexus\Services\SocialNotificationService')) {
                            $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                            if ($contentOwnerId && $contentOwnerId != $currentUserId) {
                                \Nexus\Services\SocialNotificationService::notifyComment($contentOwnerId, $currentUserId, $targetType, $targetId, $content);
                            }
                        }
                        echo json_encode($result);
                    } else {
                        \Nexus\Core\DatabaseWrapper::insert('comments', ['user_id' => $currentUserId, 'tenant_id' => $tenantId, 'target_type' => $targetType, 'target_id' => $targetId, 'content' => $content, 'created_at' => date('Y-m-d H:i:s')]);

                        // Send notification (platform + email) to content owner
                        if (class_exists('\Nexus\Services\SocialNotificationService')) {
                            $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($targetType, $targetId);
                            if ($contentOwnerId && $contentOwnerId != $currentUserId) {
                                \Nexus\Services\SocialNotificationService::notifyComment($contentOwnerId, $currentUserId, $targetType, $targetId, $content);
                            }
                        }
                        echo json_encode(['status' => 'success', 'comment' => ['author_name' => $_SESSION['user_name'] ?? 'Me', 'author_avatar' => $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp', 'content' => $content]]);
                    }
                }
            } elseif ($_POST['action'] === 'fetch_comments') {
                // Use Database directly (bypasses tenant filter) - comments already scoped by target
                $stmt = \Nexus\Core\Database::query("SELECT c.content, c.created_at, COALESCE(u.name, 'Unknown') as author_name, COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.webp') as author_avatar FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.target_type = ? AND c.target_id = ? ORDER BY c.created_at ASC", [$targetType, $targetId]);
                echo json_encode(['status' => 'success', 'comments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
            // DELETE COMMENT
            elseif ($_POST['action'] === 'delete_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $isSuperAdmin = !empty($_SESSION['is_super_admin']) || ($_SESSION['user_role'] ?? '') === 'admin';
                if (class_exists('\Nexus\Services\CommentService')) {
                    $result = \Nexus\Services\CommentService::deleteComment($commentId, $currentUserId, $isSuperAdmin);
                    echo json_encode($result);
                } else {
                    echo json_encode(['error' => 'CommentService not available']);
                }
            }
            // EDIT COMMENT
            elseif ($_POST['action'] === 'edit_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $newContent = $_POST['content'] ?? '';
                if (class_exists('\Nexus\Services\CommentService')) {
                    $result = \Nexus\Services\CommentService::editComment($commentId, $currentUserId, $newContent);
                    echo json_encode($result);
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

                    // Notify parent comment author
                    if ($result['status'] === 'success' && $result['is_reply'] && class_exists('\Nexus\Services\SocialNotificationService')) {
                        $pdo = \Nexus\Core\Database::getInstance();
                        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
                        $stmt->execute([$parentId]);
                        $parentComment = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($parentComment && $parentComment['user_id'] != $currentUserId) {
                            \Nexus\Services\SocialNotificationService::notifyComment(
                                $parentComment['user_id'], $currentUserId, $targetType, $targetId, "replied to your comment: " . substr($content, 0, 50)
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
                    $result = \Nexus\Services\CommentService::toggleReaction($currentUserId, $tenantId, $commentId, $emoji);
                    echo json_encode($result);
                } else {
                    echo json_encode(['error' => 'CommentService not available']);
                }
            }
            // SEARCH USERS FOR @MENTION
            elseif ($_POST['action'] === 'search_users') {
                $query = trim($_POST['query'] ?? '');
                if (class_exists('\Nexus\Services\CommentService') && strlen($query) >= 1) {
                    $users = \Nexus\Services\CommentService::searchUsersForMention($query, $tenantId);
                    echo json_encode(['status' => 'success', 'users' => $users]);
                } else {
                    echo json_encode(['status' => 'success', 'users' => []]);
                }
            } elseif ($_POST['action'] === 'delete_post') {
                if (($_SESSION['user_role'] ?? '') === 'admin' || $isOwner) {
                    \Nexus\Core\DatabaseWrapper::query("DELETE FROM feed_posts WHERE id = ?", [$targetId]);
                    echo json_encode(['status' => 'deleted']);
                }
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
                    \Nexus\Models\FeedPost::create($currentUserId, $newContent, null, null, $parentId, $parentType);
                } else {
                    \Nexus\Core\DatabaseWrapper::query(
                        "INSERT INTO feed_posts (user_id, tenant_id, content, likes_count, visibility, created_at, parent_id, parent_type) VALUES (?, ?, ?, 0, 'public', ?, ?, ?)",
                        [$currentUserId, $tenantId, $newContent, date('Y-m-d H:i:s'), $parentId, $parentType]
                    );
                }

                // Send notification (platform + email) to original content owner
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = \Nexus\Services\SocialNotificationService::getContentOwnerId($parentType, $parentId);
                    if ($contentOwnerId && $contentOwnerId != $currentUserId) {
                        \Nexus\Services\SocialNotificationService::notifyShare($contentOwnerId, $currentUserId, $parentType, $parentId);
                    }
                }

                echo json_encode(['status' => 'success']);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}

// 4. FETCH DATA
$wallPosts = [];
if (class_exists('\Nexus\Core\DatabaseWrapper')) {
    try {
        if ($currentUserId == $targetUserId) {
            // Owner sees everything
            $wallSql = "SELECT p.*, u.name as author_name, u.avatar_url as author_avatar, u.location as author_location,
                (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = p.id) as is_liked,
                (SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count,
                p.parent_id, p.parent_type
                FROM feed_posts p JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ?
                ORDER BY p.created_at DESC LIMIT 20";
            $wallPosts = \Nexus\Core\DatabaseWrapper::query($wallSql, [$currentUserId, $targetUserId], 'p')->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Viewer sees Public + (Connections if Friends)
            $visParams = [$currentUserId, $targetUserId];
            $visClause = " AND (p.visibility = 'public'";

            // Check connection status passed from Controller or calculated here
            // We use the $connection variable available in the view scope (passed from Controller)
            if (isset($connection) && is_array($connection) && isset($connection['status']) && $connection['status'] === 'accepted') {
                $visClause .= " OR p.visibility = 'connections'";
            }
            $visClause .= ")";

            $wallSql = "SELECT p.*, u.name as author_name, u.avatar_url as author_avatar, u.location as author_location,
                (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = p.id) as is_liked,
                (SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count,
                p.parent_id, p.parent_type
                FROM feed_posts p JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ? $visClause
                ORDER BY p.created_at DESC LIMIT 20";
            $wallPosts = \Nexus\Core\DatabaseWrapper::query($wallSql, $visParams, 'p')->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
    }
}

// Time Helper
if (!function_exists('nexus_time_elapsed')) {
    function nexus_time_elapsed($datetime)
    {
        $diff = (new DateTime)->diff(new DateTime($datetime));
        if ($diff->y) return $diff->y . 'y';
        if ($diff->m) return $diff->m . 'm';
        if ($diff->d >= 7) return floor($diff->d / 7) . 'w';
        if ($diff->d) return $diff->d . 'd';
        if ($diff->h) return $diff->h . 'h';
        if ($diff->i) return $diff->i . 'm';
        return 'Just now';
    }
}

// ---------------------------------------------------------
// END PORTED LOGIC
// ---------------------------------------------------------

$displayName = htmlspecialchars($user['first_name']);
if ((isset($_SESSION['user_id']) && $user['id'] == $_SESSION['user_id']) ||
    (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') ||
    !empty($_SESSION['is_super_admin'])
) {
    $displayName .= ' ' . htmlspecialchars($user['last_name']);
}

// Hide hero section on profile pages - glassmorphism design doesn't need it
$hideHero = true;
$pageTitle = $displayName . ' - Profile';

// Load holographic glassmorphism profile CSS (use min version for production)
$profileCssFile = defined('DEBUG_MODE') && DEBUG_MODE ? 'profile-holographic.css' : 'profile-holographic.min.css';
$additionalCSS = '<link rel="stylesheet" href="/assets/css/' . $profileCssFile . '?v=' . time() . '">';

require dirname(__DIR__, 2) . '/layouts/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- HOLOGRAPHIC GLASSMORPHISM PROFILE 2025 -->

<!-- Holographic Background Layer -->
<div class="holo-profile-bg"></div>

<?php
// Calculate online status for this user
$profileLastActiveAt = $user['last_active_at'] ?? null;
$profileIsOnline = $profileLastActiveAt && (strtotime($profileLastActiveAt) > strtotime('-5 minutes'));
$profileIsRecentlyActive = !$profileIsOnline && ($user['last_login_at'] ?? null) && (strtotime($user['last_login_at']) > strtotime('-1 day'));
$profileStatusText = \Nexus\Models\User::getOnlineStatusText($profileLastActiveAt);

// Fetch friends for this profile user
$profileFriends = [];
try {
    $profileFriends = \Nexus\Core\Database::query(
        "SELECT u.id, u.first_name, u.last_name, u.organization_name, u.profile_type, u.avatar_url, u.location, u.last_active_at
         FROM connections c
         JOIN users u ON (CASE WHEN c.requester_id = ? THEN c.receiver_id ELSE c.requester_id END) = u.id
         WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.status = 'accepted'
         ORDER BY u.last_active_at DESC
         LIMIT 6",
        [$targetUserId, $targetUserId, $targetUserId]
    )->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) { /* connections table may not exist */ }

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<main role="main" aria-label="User profile">
<div class="htb-container profile-container">
    <div class="glass-profile-card">
        <div class="profile-header-flex">
            <div class="profile-avatar-wrapper">
                <img src="<?= htmlspecialchars($user['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>"
                     alt="<?= htmlspecialchars($displayName) ?>"
                     class="glass-avatar"
                     width="140"
                     height="140"
                     loading="lazy">
                <?php if ($profileIsOnline): ?>
                    <span class="profile-online-indicator online" style="background:#10b981;animation:pulse-online 2s infinite;" title="Active now"></span>
                <?php elseif ($profileIsRecentlyActive): ?>
                    <span class="profile-online-indicator recent" style="background:#f59e0b;" title="Active today"></span>
                <?php endif; ?>
            </div>

            <div class="profile-info-section">
                <h2 class="profile-display-name"><?= $displayName ?></h2>

                <?php
                // Get rating stats for header display
                $headerReviewStats = \Nexus\Models\Review::getAverageForUser($user['id']);
                $headerAvgRating = round($headerReviewStats['avg_rating'] ?? 0, 1);
                $headerTotalReviews = (int)($headerReviewStats['total_count'] ?? 0);
                ?>
                <div class="profile-badges-wrapper">
                    <!-- Online Status Badge -->
                    <?php if ($profileIsOnline): ?>
                        <span class="glass-info-badge badge-online">
                            <span class="status-dot online"></span>
                            <strong>Online now</strong>
                        </span>
                    <?php elseif ($profileIsRecentlyActive): ?>
                        <span class="glass-info-badge badge-recent">
                            <i class="fa-solid fa-circle"></i>
                            <span><?= $profileStatusText ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($user['location']): ?>
                        <span class="glass-info-badge">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= htmlspecialchars($user['location']) ?>
                        </span>
                    <?php endif; ?>
                    <span class="glass-info-badge">
                        <i class="fa-solid fa-clock"></i>
                        Joined <?= date('F Y', strtotime($user['created_at'])) ?>
                    </span>
                    <span class="glass-info-badge badge-credits">
                        <i class="fa-solid fa-coins"></i>
                        <strong><?= number_format($user['balance']) ?> Credits</strong>
                    </span>
                    <!-- Organization Roles -->
                    <?php if (!empty($userOrganizations)): ?>
                        <?php foreach ($userOrganizations as $org): ?>
                            <?php if ($org['member_role'] === 'owner'): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet" class="glass-info-badge badge-owner">
                                <i class="fa-solid fa-crown"></i>
                                <span>Owner: <?= htmlspecialchars($org['name']) ?></span>
                            </a>
                            <?php elseif ($org['member_role'] === 'admin'): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet" class="glass-info-badge badge-admin-role">
                                <i class="fa-solid fa-shield"></i>
                                <span>Admin: <?= htmlspecialchars($org['name']) ?></span>
                            </a>
                            <?php else: ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet" class="glass-info-badge">
                                <i class="fa-solid fa-building"></i>
                                <span><?= htmlspecialchars($org['name']) ?></span>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- Rating Badge in Header -->
                    <?php if ($headerTotalReviews > 0): ?>
                    <a href="#reviews-section" class="glass-info-badge badge-rating" onclick="event.preventDefault(); document.getElementById('reviews-section').scrollIntoView({behavior: 'smooth'})">
                        <i class="fa-solid fa-star"></i>
                        <strong><?= $headerAvgRating ?></strong>
                        <span class="badge-count">(<?= $headerTotalReviews ?>)</span>
                    </a>
                    <?php else: ?>
                    <span class="glass-info-badge badge-no-reviews">
                        <i class="fa-regular fa-star"></i>
                        <span>No reviews</span>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($user['phone']) && (($_SESSION['user_role'] ?? '') === 'admin' || !empty($_SESSION['is_super_admin']))): ?>
                        <span class="glass-info-badge badge-admin-phone">
                            <i class="fa-solid fa-shield-halved"></i>
                            <strong>Admin: <?= htmlspecialchars($user['phone']) ?></strong>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="profile-actions-wrapper">
                    <?php
                    // Admin Impersonation Button - Show if viewing another user and current user is admin
                    $isAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin', 'tenant_admin']);
                    $isSuperAdmin = !empty($_SESSION['is_super_admin']);
                    $canImpersonate = ($isAdmin || $isSuperAdmin) && isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user['id'];

                    if ($canImpersonate):
                    ?>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/impersonate" method="POST" onsubmit="return confirm('You are about to login as <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>. Continue?');" class="inline-form">
                            <?= Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button type="submit" class="glass-btn btn-warning">
                                <i class="fa-solid fa-user-secret"></i> Login As User
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/edit" class="glass-btn glass-btn-secondary">
                            <i class="fa-solid fa-pen"></i> Edit Profile
                        </a>
                        <?php if (\Nexus\Core\TenantContext::hasFeature('timebanking')): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/wallet/insights" class="glass-btn btn-purple">
                            <i class="fa-solid fa-chart-line"></i> My Insights
                        </a>
                        <?php endif; ?>
                    <?php elseif (isset($_SESSION['user_id'])): ?>
                        <!-- Viewing another user's profile -->
                        <?php if (!$connection): ?>
                            <!-- Not friends - show Add Friend button -->
                            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/connections/add" method="POST" class="inline-form">
                                <input type="hidden" name="receiver_id" value="<?= $user['id'] ?>">
                                <button type="submit" class="glass-btn">
                                    <i class="fa-solid fa-user-plus"></i> Add Friend
                                </button>
                            </form>
                        <?php elseif ($connection['status'] === 'pending' && $connection['requester_id'] == $_SESSION['user_id']): ?>
                            <!-- Friend request sent -->
                            <button disabled class="glass-btn glass-btn-secondary btn-disabled">
                                <i class="fa-solid fa-clock"></i> Request Sent
                            </button>
                        <?php elseif ($connection['status'] === 'pending' && $connection['receiver_id'] == $_SESSION['user_id']): ?>
                            <!-- Accept friend request -->
                            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/connections/accept" method="POST" class="inline-form">
                                <input type="hidden" name="connection_id" value="<?= $connection['id'] ?>">
                                <button type="submit" class="glass-btn glass-btn-success">
                                    <i class="fa-solid fa-check"></i> Accept Request
                                </button>
                            </form>
                        <?php elseif ($connection['status'] === 'accepted'): ?>
                            <!-- Already friends -->
                            <span class="glass-btn glass-btn-success btn-static">
                                <i class="fa-solid fa-check"></i> Friends
                            </span>
                        <?php endif; ?>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages/<?= $user['id'] ?>" class="glass-btn">
                            <i class="fa-solid fa-message"></i> Message
                        </a>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/wallet?to=<?= $user['id'] ?>" class="glass-btn glass-btn-secondary">
                            <i class="fa-solid fa-coins"></i> Send Credits
                        </a>
                        <button type="button" onclick="openReviewModal()" class="glass-btn btn-warning">
                            <i class="fa-solid fa-star"></i> Leave Review
                        </button>
                    <?php endif; ?>

                    <?php if ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/admin/users/<?= $user['id'] ?>/edit" class="glass-btn btn-danger">
                            <i class="fa-solid fa-shield"></i> Admin
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Showcased Badges with Accordion -->
        <?php if (!empty($showcasedBadges)): ?>
            <?php
            // Get badge definitions for descriptions (for showcased badges)
            if (!isset($badgeDefinitions)) {
                $badgeDefinitions = \Nexus\Services\GamificationService::getBadgeDefinitions();
                $badgeDescMap = [];
                foreach ($badgeDefinitions as $def) {
                    $badgeDescMap[$def['key']] = $def['msg'] ?? '';
                }
                $badgeRarityStats = \Nexus\Models\UserBadge::getBadgeRarityStats();
            }
            $featuredPreview = array_slice($showcasedBadges, 0, 3);
            ?>
            <div class="glass-divider"></div>
            <div class="badges-section badges-accordion featured" data-accordion="featured-badges">
                <button type="button" class="badges-accordion-header" aria-expanded="false" aria-controls="featured-badges-content">
                    <h3>
                        <i class="fa-solid fa-star"></i>
                        Featured Badges (<?= count($showcasedBadges) ?>)
                    </h3>
                    <div class="badges-preview" aria-hidden="true">
                        <?php foreach ($featuredPreview as $previewBadge): ?>
                        <span class="badge-preview-pill" title="<?= htmlspecialchars($previewBadge['name']) ?>"><?= $previewBadge['icon'] ?></span>
                        <?php endforeach; ?>
                    </div>
                    <span class="badges-accordion-toggle" aria-hidden="true">
                        <i class="fa-solid fa-chevron-down"></i>
                    </span>
                </button>
                <div class="badges-accordion-content" id="featured-badges-content">
                    <div class="badges-grid featured">
                        <?php foreach ($showcasedBadges as $badge):
                            $badgeKey = $badge['badge_key'] ?? '';
                            $badgeDesc = $badgeDescMap[$badgeKey] ?? 'earning this achievement';
                            $rarityInfo = $badgeRarityStats[$badgeKey] ?? null;
                            $rarityLabel = $rarityInfo['label'] ?? 'Common';
                            $rarityPercent = $rarityInfo['percent'] ?? 100;
                        ?>
                            <div class="featured-badge badge-clickable"
                                 data-badge-name="<?= htmlspecialchars($badge['name']) ?>"
                                 data-badge-icon="<?= htmlspecialchars($badge['icon']) ?>"
                                 data-badge-desc="<?= htmlspecialchars($badgeDesc) ?>"
                                 data-badge-date="<?= date('F j, Y', strtotime($badge['awarded_at'])) ?>"
                                 data-badge-rarity="<?= htmlspecialchars($rarityLabel) ?>"
                                 data-badge-percent="<?= htmlspecialchars($rarityPercent) ?>"
                                 data-badge-featured="true"
                                 onclick="openBadgeModal(this)"
                                 role="button"
                                 tabindex="0"
                                 title="Tap for details">
                                <span class="badge-icon"><?= $badge['icon'] ?></span>
                                <span class="badge-name"><?= htmlspecialchars($badge['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- All Badges with Accordion -->
        <?php if (!empty($badges)): ?>
            <?php
            // Get badge definitions for descriptions (if not already loaded by showcased badges)
            if (!isset($badgeDefinitions)) {
                $badgeDefinitions = \Nexus\Services\GamificationService::getBadgeDefinitions();
                $badgeDescMap = [];
                foreach ($badgeDefinitions as $def) {
                    $badgeDescMap[$def['key']] = $def['msg'] ?? '';
                }
                $badgeRarityStats = \Nexus\Models\UserBadge::getBadgeRarityStats();
            }
            $badgesPreview = array_slice($badges, 0, 4);
            $badgesRemaining = max(0, count($badges) - 4);
            ?>
            <div class="glass-divider"></div>
            <div class="badges-section badges-accordion" data-accordion="all-badges">
                <button type="button" class="badges-accordion-header" aria-expanded="false" aria-controls="all-badges-content">
                    <h3>
                        <i class="fa-solid fa-trophy"></i>
                        Achievements (<?= count($badges) ?>)
                    </h3>
                    <div class="badges-preview" aria-hidden="true">
                        <?php foreach ($badgesPreview as $previewBadge): ?>
                        <span class="badge-preview-pill" title="<?= htmlspecialchars($previewBadge['name']) ?>"><?= $previewBadge['icon'] ?></span>
                        <?php endforeach; ?>
                        <?php if ($badgesRemaining > 0): ?>
                        <span class="badge-preview-count">+<?= $badgesRemaining ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="badges-accordion-toggle" aria-hidden="true">
                        <i class="fa-solid fa-chevron-down"></i>
                    </span>
                </button>
                <div class="badges-accordion-content" id="all-badges-content">
                    <div class="badges-grid">
                        <?php foreach ($badges as $badge):
                            $badgeKey = $badge['badge_key'] ?? '';
                            $badgeDesc = $badgeDescMap[$badgeKey] ?? 'earning this achievement';
                            $rarityInfo = $badgeRarityStats[$badgeKey] ?? null;
                            $rarityLabel = $rarityInfo['label'] ?? 'Common';
                            $rarityPercent = $rarityInfo['percent'] ?? 100;
                        ?>
                            <div class="glass-badge badge-clickable"
                                 data-badge-name="<?= htmlspecialchars($badge['name']) ?>"
                                 data-badge-icon="<?= htmlspecialchars($badge['icon']) ?>"
                                 data-badge-desc="<?= htmlspecialchars($badgeDesc) ?>"
                                 data-badge-date="<?= date('F j, Y', strtotime($badge['awarded_at'])) ?>"
                                 data-badge-rarity="<?= htmlspecialchars($rarityLabel) ?>"
                                 data-badge-percent="<?= htmlspecialchars($rarityPercent) ?>"
                                 onclick="openBadgeModal(this)"
                                 role="button"
                                 tabindex="0"
                                 title="Tap for details">
                                <span class="badge-icon"><?= $badge['icon'] ?></span>
                                <span class="badge-name"><?= htmlspecialchars($badge['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Friends Section -->
    <?php if (!empty($profileFriends)): ?>
    <div class="profile-friends-card">
        <div class="profile-friends-header">
            <h3><i class="fa-solid fa-user-group" style="color: #6366f1;"></i> Friends</h3>
            <a href="<?= $basePath ?>/connections" class="profile-friends-link">See All</a>
        </div>
        <div class="profile-friends-body">
            <?php foreach ($profileFriends as $friend):
                $friendName = $friend['profile_type'] === 'organization'
                    ? ($friend['organization_name'] ?: 'Organization')
                    : (trim(($friend['first_name'] ?? '') . ' ' . ($friend['last_name'] ?? '')) ?: 'Member');
                $friendIsOnline = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-5 minutes');
                $friendIsRecent = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-24 hours');
            ?>
                <a href="<?= $basePath ?>/profile/<?= $friend['id'] ?>" class="profile-friend-item">
                    <div class="profile-friend-avatar">
                        <?= webp_avatar($friend['avatar_url'] ?: null, $friendName, 48) ?>
                        <?php if ($friendIsOnline): ?>
                            <span class="profile-friend-online" style="background: #10b981;" title="Online now"></span>
                        <?php elseif ($friendIsRecent): ?>
                            <span class="profile-friend-online" style="background: #f59e0b;" title="Active today"></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-friend-info">
                        <span class="profile-friend-name"><?= htmlspecialchars($friendName) ?></span>
                        <span class="profile-friend-meta"><?= htmlspecialchars($friend['location'] ?: 'Community Member') ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Post Composer Section -->
    <?php if ($isOwner): ?>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/compose?type=post" class="glass-composer">
            <div class="composer-inner">
                <div class="composer-icon">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div class="composer-text">
                    <h3>Share an Update</h3>
                    <p>What's on your mind, <?= htmlspecialchars($user['first_name']) ?>?</p>
                </div>
                <div class="composer-arrow">
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </div>
        </a>
    <?php endif; ?>

    <!-- Recent Activity Section -->
    <div class="glass-activity-card">
        <h3>
            <i class="fa-solid fa-clock-rotate-left"></i>
            Recent Activity
        </h3>

        <?php if (empty($wallPosts)): ?>
            <div class="empty-state">
                <div class="empty-emoji">👋</div>
                <div class="empty-text">No activity yet</div>
            </div>
        <?php else: ?>
            <?php
            // Use the modern feed_item.php partial for consistent rendering
            $timeElapsed = function ($datetime) {
                return nexus_time_elapsed($datetime);
            };
            $isLoggedIn = !empty($currentUserId);
            $userId = $currentUserId;

            foreach ($wallPosts as $item):
                // Transform wall post data to feed item format
                $item['type'] = 'post';
                $item['body'] = $item['content'];
                include __DIR__ . '/../partials/feed_item.php';
            endforeach;
            ?>
        <?php endif; ?>
    </div>

    <!-- About Me / Bio Section -->
    <?php if (!empty($user['bio'])): ?>
        <div class="glass-profile-card">
            <h3 class="section-header">
                <i class="fa-solid fa-user"></i>
                About Me
            </h3>
            <div class="bio-content">
                <?= $user['bio'] ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reviews Section -->
    <?php
    $reviewStats = \Nexus\Models\Review::getAverageForUser($user['id']);
    $avgRating = round($reviewStats['avg_rating'] ?? 0, 1);
    $totalReviews = (int)($reviewStats['total_count'] ?? 0);
    ?>
    <div id="reviews-section" class="glass-profile-card reviews-section">
        <div class="reviews-header">
            <h3 class="section-header">
                <i class="fa-solid fa-star"></i>
                Reviews & Reputation
            </h3>

            <!-- Rating Summary Badge + Write Review Button -->
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <?php if ($totalReviews > 0): ?>
                <div class="glass-badge" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.1)); border-color: rgba(245, 158, 11, 0.3);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 1.8rem; font-weight: 800; color: #f59e0b;"><?= $avgRating ?></span>
                        <div>
                            <div style="display: flex; gap: 2px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= floor($avgRating)): ?>
                                        <i class="fa-solid fa-star" style="color: #f59e0b; font-size: 0.9rem;"></i>
                                    <?php elseif ($i - 0.5 <= $avgRating): ?>
                                        <i class="fa-solid fa-star-half-stroke" style="color: #f59e0b; font-size: 0.9rem;"></i>
                                    <?php else: ?>
                                        <i class="fa-regular fa-star" style="color: #cbd5e1; font-size: 0.9rem;"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <span style="font-size: 0.75rem; color: #6b7280;"><?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="glass-badge" style="opacity: 0.7;">
                    <i class="fa-regular fa-star" style="color: #9ca3af;"></i>
                    <span style="color: #6b7280; font-size: 0.9rem;">No reviews yet</span>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user['id']): ?>
                <button type="button" onclick="openReviewModal()" class="glass-btn" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.9), rgba(217, 119, 6, 0.9)); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3); padding: 8px 16px; font-size: 0.9rem;">
                    <i class="fa-solid fa-pen"></i> Write a Review
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($reviews)): ?>
            <!-- Reviews List -->
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($reviews as $review): ?>
                <div class="glass-review-card" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.25)); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 16px; padding: 20px; transition: all 0.3s ease;">
                    <div style="display: flex; gap: 16px;">
                        <!-- Reviewer Avatar -->
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $review['reviewer_id'] ?>" style="text-decoration: none; flex-shrink: 0;">
                            <?= webp_avatar($review['reviewer_avatar'] ?: null, $review['reviewer_name'], 52) ?>
                        </a>

                        <div style="flex: 1; min-width: 0;">
                            <!-- Header Row -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                                <div>
                                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $review['reviewer_id'] ?>" style="text-decoration: none;">
                                        <span style="font-weight: 700; font-size: 1rem; color: #1f2937;"><?= htmlspecialchars($review['reviewer_name']) ?></span>
                                    </a>
                                    <?php if (!empty($review['group_name'])): ?>
                                    <span style="margin-left: 8px; padding: 3px 10px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1)); border-radius: 20px; font-size: 0.75rem; color: #6366f1; font-weight: 600;">
                                        <i class="fa-solid fa-users" style="margin-right: 4px;"></i><?= htmlspecialchars($review['group_name']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <span style="font-size: 0.8rem; color: #9ca3af;">
                                    <i class="fa-regular fa-clock" style="margin-right: 4px;"></i>
                                    <?= date('M j, Y', strtotime($review['created_at'])) ?>
                                </span>
                            </div>

                            <!-- Star Rating -->
                            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 10px;">
                                <div style="display: flex; gap: 2px;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $review['rating']): ?>
                                            <i class="fa-solid fa-star" style="color: #f59e0b; font-size: 1rem;"></i>
                                        <?php else: ?>
                                            <i class="fa-regular fa-star" style="color: #e5e7eb; font-size: 1rem;"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <span style="font-size: 0.85rem; font-weight: 600; color: #374151;"><?= $review['rating'] ?>/5</span>
                            </div>

                            <!-- Review Comment -->
                            <?php if (!empty($review['comment'])): ?>
                            <div style="color: #4b5563; font-size: 0.95rem; line-height: 1.6; background: rgba(255, 255, 255, 0.4); padding: 12px 16px; border-radius: 12px; border-left: 4px solid rgba(245, 158, 11, 0.5);">
                                <i class="fa-solid fa-quote-left" style="color: #d1d5db; margin-right: 8px; font-size: 0.8rem;"></i>
                                <?= nl2br(htmlspecialchars($review['comment'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div style="text-align: center; padding: 48px 32px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0.2)); backdrop-filter: blur(10px); border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.3);">
                <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.1)); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-regular fa-star" style="font-size: 2rem; color: #f59e0b;"></i>
                </div>
                <h4 style="margin: 0 0 8px 0; font-size: 1.1rem; font-weight: 700; color: #374151;">No Reviews Yet</h4>
                <p style="margin: 0; color: #6b7280; font-size: 0.95rem;">
                    <?php if ($isOwner): ?>
                        Complete exchanges with other members to receive reviews.
                    <?php else: ?>
                        Be the first to review <?= htmlspecialchars($user['first_name']) ?>!
                    <?php endif; ?>
                </p>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user['id']): ?>
                <button onclick="openReviewModal()" class="glass-btn" style="margin-top: 20px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.9), rgba(217, 119, 6, 0.9)); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);">
                    <i class="fa-solid fa-star"></i> Leave a Review
                </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Using Master Platform Social Media Module -->
    <script>
        const IS_LOGGED_IN = <?= json_encode(!empty($currentUserId)) ?>;
        const BASE_URL = "<?= \Nexus\Core\TenantContext::getBasePath() ?>";
        const API_BASE = BASE_URL + '/api/social';

        function toggleLike(btn, type, id) {
            if (!IS_LOGGED_IN) return;
            let icon = btn.querySelector("i");
            let isLiked = icon.classList.contains("fa-solid");

            // Optimistic UI update
            if (isLiked) {
                icon.classList.remove("fa-solid");
                icon.classList.add("fa-regular");
                btn.style.color = "#6b7280";
                btn.style.fontWeight = "normal";
            } else {
                icon.classList.remove("fa-regular");
                icon.classList.add("fa-solid");
                btn.style.color = "#4f46e5";
                btn.style.fontWeight = "600";
            }

            fetch(API_BASE + '/like', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    target_type: type,
                    target_id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'liked' || data.status === 'unliked') {
                    // Update likes count if available
                    const countEl = btn.querySelector('.likes-count') || btn.parentElement.querySelector('.likes-count');
                    if (countEl && data.likes_count !== undefined) {
                        countEl.textContent = data.likes_count > 0 ? data.likes_count : '';
                    }
                } else {
                    // Revert UI on failure
                    revertLikeUI(icon, btn, isLiked);
                    console.error('Like failed:', data);
                }
            })
            .catch(err => {
                // Revert UI on error
                revertLikeUI(icon, btn, isLiked);
                console.error('Like error:', err);
            });
        }

        function revertLikeUI(icon, btn, wasLiked) {
            if (wasLiked) {
                icon.classList.remove("fa-regular");
                icon.classList.add("fa-solid");
                btn.style.color = "#4f46e5";
                btn.style.fontWeight = "600";
            } else {
                icon.classList.remove("fa-solid");
                icon.classList.add("fa-regular");
                btn.style.color = "#6b7280";
                btn.style.fontWeight = "normal";
            }
        }

        // Use window assignment (not function declaration) so mobile-sheets.php can intercept
        window.toggleCommentSection = function(type, id, unused) {
            const section = document.getElementById(`comments-section-${type}-${id}`);
            if (!section) {
                console.error('Comments section not found:', `comments-section-${type}-${id}`);
                return;
            }

            // Check current visibility using both methods
            const isHidden = section.style.display === 'none' || section.style.display === '' || window.getComputedStyle(section).display === 'none';

            if (isHidden) {
                section.style.display = 'block';
                section.style.visibility = 'visible';
                if (section.querySelector("input")) section.querySelector("input").focus();
                fetchComments(type, id);
            } else {
                section.style.display = 'none';
            }
        };

        // Enhanced comment system state
        let availableReactions = [];
        let currentCommentTargetType = '';
        let currentCommentTargetId = 0;

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function renderComment(c, depth) {
            const indent = depth * 20;
            const isEdited = c.is_edited ? '<span style="font-size: 0.7rem; color: #9ca3af;"> (edited)</span>' : '';
            const ownerActions = c.is_owner ? `
                <span onclick="editComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'").replace(/\n/g, "\\n")}')" style="cursor: pointer; margin-left: 10px; color: #6b7280; font-size: 12px;" title="Edit">✏️</span>
                <span onclick="deleteComment(${c.id})" style="cursor: pointer; margin-left: 5px; color: #6b7280; font-size: 12px;" title="Delete">🗑️</span>
            ` : '';

            // Reactions display
            const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
                const isUserReaction = (c.user_reactions || []).includes(emoji);
                return `<span onclick="toggleReaction(${c.id}, '${emoji}')" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 12px; background: ${isUserReaction ? 'rgba(99, 102, 241, 0.2)' : 'rgba(243, 244, 246, 0.8)'}; border: 1px solid ${isUserReaction ? 'rgba(99, 102, 241, 0.4)' : 'rgba(229, 231, 235, 0.8)'}; margin-right: 4px;">${emoji} ${count}</span>`;
            }).join('');

            // Reaction picker
            const reactionPicker = IS_LOGGED_IN ? `
                <div class="reaction-picker" style="display: inline-block; position: relative;">
                    <span onclick="toggleReactionPicker(${c.id})" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 12px; background: rgba(243, 244, 246, 0.8); border: 1px solid rgba(229, 231, 235, 0.8);">+</span>
                    <div id="reaction-picker-${c.id}" style="display: none; position: absolute; bottom: 24px; left: 0; background: white; border-radius: 20px; padding: 4px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); white-space: nowrap; z-index: 100;">
                        ${availableReactions.map(emoji => `<span onclick="toggleReaction(${c.id}, '${emoji}')" style="cursor: pointer; padding: 4px; font-size: 18px; transition: transform 0.1s;" onmouseover="this.style.transform='scale(1.3)'" onmouseout="this.style.transform='scale(1)'">${emoji}</span>`).join('')}
                    </div>
                </div>
            ` : '';

            const replyButton = IS_LOGGED_IN ? `<span onclick="showReplyForm(${c.id})" style="cursor: pointer; margin-left: 10px; color: #6b7280; font-size: 12px;">Reply</span>` : '';

            const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

            // Highlight @mentions in content
            const contentHtml = escapeHtml(c.content).replace(/@(\w+)/g, '<span style="color: #6366f1; font-weight: 600;">@$1</span>');

            return `
                <div style="margin-left: ${indent}px; margin-bottom: 12px;" id="comment-${c.id}">
                    <div style="display: flex; gap: 8px;">
                        <img src="${c.author_avatar}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" loading="lazy">
                        <div style="flex-grow: 1;">
                            <div style="background: rgba(243, 244, 246, 0.8); backdrop-filter: blur(8px); padding: 8px 12px; border-radius: 18px; display: inline-block; max-width: 100%;">
                                <div style="font-weight: 600; font-size: 13px; color: #1f2937;">${escapeHtml(c.author_name)}${isEdited}</div>
                                <div style="font-size: 14px; color: #1f2937; word-wrap: break-word;">${contentHtml}</div>
                            </div>
                            <div style="margin-top: 4px; display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                                ${reactions}
                                ${reactionPicker}
                                ${replyButton}
                                ${ownerActions}
                            </div>
                            <div id="reply-form-${c.id}" style="display: none; margin-top: 8px;">
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <input type="text" class="fds-input" placeholder="Write a reply..." style="flex-grow: 1; border-radius: 20px; padding: 8px 12px; font-size: 13px;" onkeydown="if(event.key === 'Enter') submitReply(${c.id}, this)">
                                    <button onclick="submitReply(${c.id}, this.previousElementSibling)" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.9), rgba(139, 92, 246, 0.9)); color: white; border: none; border-radius: 20px; padding: 8px 16px; cursor: pointer; font-size: 13px;">Reply</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    ${replies}
                </div>
            `;
        }

        function fetchComments(type, id) {
            const section = document.getElementById(`comments-section-${type}-${id}`);
            if (!section) return;

            currentCommentTargetType = type;
            currentCommentTargetId = id;

            let list = section.querySelector('.comments-list');
            if (!list) {
                list = document.createElement('div');
                list.className = 'comments-list';
                section.appendChild(list);
            }

            list.innerHTML = '<div style="color:#6b7280; padding:10px; text-align:center;">Loading comments...</div>';

            fetch(API_BASE + '/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'fetch',
                    target_type: type,
                    target_id: id
                })
            })
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                if (data.available_reactions) {
                    availableReactions = data.available_reactions;
                }
                if (data.status === 'success' && data.comments && data.comments.length > 0) {
                    // Check if enhanced format (has replies/reactions)
                    if (data.comments[0].hasOwnProperty('replies') || data.comments[0].hasOwnProperty('reactions')) {
                        list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');
                    } else {
                        // Fallback to basic rendering
                        list.innerHTML = data.comments.map(c => `
                        <div style="display:flex; gap:8px; margin-bottom:10px;">
                            <img src="${c.author_avatar || '/assets/img/defaults/default_avatar.webp'}" style="width:32px; height:32px; border-radius:50%; object-fit:cover;" loading="lazy">
                            <div style="background:#f3f4f6; padding:8px 12px; border-radius:18px; flex:1;">
                                <div style="font-weight:600; font-size:13px; color:#1f2937;">${c.author_name || 'Unknown'}</div>
                                <div style="font-size:14px; color:#1f2937;">${c.content}</div>
                            </div>
                        </div>`).join('');
                    }
                } else if (data.error) {
                    list.innerHTML = '<div style="color:#ef4444; padding:10px; text-align:center;">Error: ' + data.error + '</div>';
                } else {
                    list.innerHTML = '<div style="color:#9ca3af; padding:10px; font-size:13px; text-align:center;">No comments yet. Be the first to comment!</div>';
                }
            })
            .catch(err => {
                console.error('Fetch comments error:', err);
                list.innerHTML = '<div style="color:#ef4444; padding:10px; text-align:center;">Failed to load comments. Please try again.</div>';
            });
        }

        function toggleReactionPicker(commentId) {
            const picker = document.getElementById(`reaction-picker-${commentId}`);
            if (picker) {
                picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
            }
        }

        function toggleReaction(commentId, emoji) {
            if (!IS_LOGGED_IN) {
                alert("Please log in to react.");
                return;
            }

            const picker = document.getElementById(`reaction-picker-${commentId}`);
            if (picker) picker.style.display = 'none';

            fetch(API_BASE + '/reaction', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    emoji: emoji
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' || data.status === 'added' || data.status === 'removed') {
                    fetchComments(currentCommentTargetType, currentCommentTargetId);
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

            fetch(API_BASE + '/reply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    target_type: currentCommentTargetType,
                    target_id: currentCommentTargetId,
                    parent_id: parentId,
                    content: content
                })
            })
            .then(res => res.json())
            .then(data => {
                input.disabled = false;
                input.value = '';
                if (data.status === 'success') {
                    fetchComments(currentCommentTargetType, currentCommentTargetId);
                    showToast("Reply posted!");
                } else if (data.error) {
                    alert(data.error);
                }
            });
        }

        function editComment(commentId, currentContent) {
            const newContent = prompt("Edit your comment:", currentContent.replace(/\\n/g, "\n"));
            if (newContent === null || newContent.trim() === '' || newContent === currentContent) return;

            fetch(API_BASE + '/edit-comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    content: newContent.trim()
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchComments(currentCommentTargetType, currentCommentTargetId);
                    showToast("Comment updated!");
                } else if (data.error) {
                    alert(data.error);
                }
            });
        }

        function deleteComment(commentId) {
            if (!confirm("Delete this comment?")) return;

            fetch(API_BASE + '/delete-comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    comment_id: commentId
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchComments(currentCommentTargetType, currentCommentTargetId);
                    showToast("Comment deleted!");
                } else if (data.error) {
                    alert(data.error);
                }
            });
        }

        function showToast(message) {
            const toast = document.createElement('div');
            toast.textContent = message;
            toast.style.cssText = 'position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, rgba(99, 102, 241, 0.95), rgba(139, 92, 246, 0.95)); color: white; padding: 12px 24px; border-radius: 12px; z-index: 10000; font-weight: 600; box-shadow: 0 8px 32px rgba(99, 102, 241, 0.4);';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function submitComment(input, type, id) {
            const content = input.value.trim();
            if (!content) return;
            input.disabled = true;

            // Store context for enhanced comment refresh
            currentCommentTargetType = type;
            currentCommentTargetId = id;

            fetch(API_BASE + '/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'submit',
                    target_type: type,
                    target_id: id,
                    content: content
                })
            })
            .then(res => res.json())
            .then(data => {
                input.disabled = false;
                if (data.status === 'success') {
                    input.value = '';
                    input.focus();
                    // Refresh comments to show the new comment with full features
                    fetchComments(type, id);
                    showToast("Comment posted!");
                }
            })
            .catch(err => {
                input.disabled = false;
                console.error('Submit comment error:', err);
            });
        }

        function deletePost(type, id) {
            if (!confirm("Delete this post?")) return;
            fetch(API_BASE + '/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    target_type: type,
                    target_id: id
                })
            }).then(() => location.reload());
        }

        // ============================================
        // FEED ITEM 3-DOT MENU FUNCTIONS
        // ============================================
        function toggleFeedItemMenu(btn) {
            const dropdown = btn.nextElementSibling;
            const wasOpen = dropdown.classList.contains('show');

            // Close all other menus first
            closeFeedMenus();

            // Toggle this menu
            if (!wasOpen) {
                dropdown.classList.add('show');
                // Add click outside listener
                setTimeout(() => {
                    document.addEventListener('click', closeFeedMenusOnOutsideClick);
                }, 10);
            }
        }

        function closeFeedMenus() {
            document.querySelectorAll('.feed-item-menu-dropdown.show').forEach(d => d.classList.remove('show'));
            document.removeEventListener('click', closeFeedMenusOnOutsideClick);
        }

        function closeFeedMenusOnOutsideClick(e) {
            if (!e.target.closest('.feed-item-menu-container')) {
                closeFeedMenus();
            }
        }

        // Hide post function
        function hidePost(postId) {
            showToast("Post hidden from your feed");
            const card = document.querySelector(`[data-post-id="${postId}"]`) || event.target.closest('.fb-card');
            if (card) {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(() => card.remove(), 300);
            }
        }

        // Mute user function
        function muteUser(userId) {
            showToast("User muted. You won't see their posts.");
        }

        // Report post function
        function reportPost(postId) {
            if (confirm("Report this post as inappropriate?")) {
                showToast("Post reported. Thank you for keeping our community safe.");
            }
        }

        // Share/Repost function for feed_item.php
        function repostToFeed(type, id, author) {
            if (!IS_LOGGED_IN) {
                alert("Please log in to share.");
                return;
            }

            if (!confirm("Share this post to your feed?")) return;

            fetch(API_BASE + '/share', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    parent_id: id,
                    parent_type: type || 'post',
                    original_author: author
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast("Shared to your feed!");
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        alert("Share failed: " + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Share error:', err);
                    alert("Share failed. Please try again.");
                });
        }

        // ============================================
        // HOLOGRAPHIC REVIEW MODAL SYSTEM
        // Premium Star Rating with Visual Feedback
        // ============================================

        let currentRating = 0;
        const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Great', 'Excellent'];

        function openReviewModal() {
            const modal = document.getElementById('profileReviewModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            // Reset on open
            currentRating = 0;
            document.getElementById('profileRatingInput').value = '';
            updateStarDisplay(0);
            updateRatingLabel(0);
        }

        function closeReviewModal() {
            const modal = document.getElementById('profileReviewModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        function updateStarDisplay(rating, isHover = false) {
            const stars = document.querySelectorAll('.holo-star');
            stars.forEach((star, index) => {
                const starNum = index + 1;
                if (starNum <= rating) {
                    star.classList.add('active');
                    star.classList.remove('inactive');
                    if (isHover && starNum <= rating) {
                        star.classList.add('hover');
                    }
                } else {
                    star.classList.remove('active', 'hover');
                    star.classList.add('inactive');
                }
            });
        }

        function updateRatingLabel(rating) {
            const label = document.getElementById('ratingLabel');
            const indicator = document.getElementById('ratingIndicator');
            if (rating > 0) {
                label.textContent = ratingLabels[rating];
                label.style.opacity = '1';
                indicator.style.opacity = '1';
                indicator.textContent = rating + '/5';
            } else {
                label.textContent = 'Select a rating';
                label.style.opacity = '0.6';
                indicator.style.opacity = '0';
            }
        }

        function selectProfileRating(rating) {
            currentRating = rating;
            document.getElementById('profileRatingInput').value = rating;
            updateStarDisplay(rating);
            updateRatingLabel(rating);

            // Add selection animation
            const stars = document.querySelectorAll('.holo-star');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.style.animation = 'none';
                    star.offsetHeight; // Trigger reflow
                    star.style.animation = 'starBounce 0.4s ease';
                }
            });
        }

        function hoverStar(rating) {
            if (currentRating === 0) {
                updateStarDisplay(rating, true);
                updateRatingLabel(rating);
            }
        }

        function unhoverStar() {
            updateStarDisplay(currentRating);
            updateRatingLabel(currentRating);
        }

        function submitProfileReview() {
            const rating = document.getElementById('profileRatingInput').value;

            if (!rating || rating < 1) {
                // Shake animation for error
                const container = document.getElementById('starRatingContainer');
                container.style.animation = 'shake 0.5s ease';
                setTimeout(() => container.style.animation = '', 500);
                return;
            }

            document.getElementById('profileReviewForm').submit();
        }

        // Close modal on backdrop click
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('profileReviewModal');
            if (e.target === modal) {
                closeReviewModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeReviewModal();
            }
        });
    </script>

    <!-- Holographic Review Modal -->
    <div id="profileReviewModal" class="holo-review-modal">
        <div class="holo-review-card">
            <!-- Modal Header -->
            <div class="holo-review-header">
                <button type="button" onclick="closeReviewModal()" class="modal" role="dialog" aria-modal="true"-close-btn">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <h3>
                    <i class="fa-solid fa-star" style="margin-right: 10px;"></i>
                    Rate <?= htmlspecialchars($user['first_name']) ?>
                </h3>
            </div>

            <!-- Modal Body -->
            <div class="holo-review-body">
                <form id="profileReviewForm" action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/reviews/store" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="receiver_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="rating" id="profileRatingInput" value="">

                    <!-- Star Rating Section -->
                    <div class="star-rating-section">
                        <label class="star-rating-label">How was your experience?</label>

                        <div id="starRatingContainer" class="star-rating-container">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button"
                                    class="holo-star inactive"
                                    onclick="selectProfileRating(<?= $i ?>)"
                                    onmouseenter="hoverStar(<?= $i ?>)"
                                    onmouseleave="unhoverStar()">
                                <i class="fa-solid fa-star"></i>
                            </button>
                            <?php endfor; ?>
                        </div>

                        <div class="rating-feedback">
                            <span id="ratingLabel" class="rating-label" style="opacity: 0.6;">Select a rating</span>
                            <span id="ratingIndicator" class="rating-indicator" style="opacity: 0;">0/5</span>
                        </div>
                    </div>

                    <!-- Comment -->
                    <div style="margin-bottom: 8px;">
                        <label style="font-weight: 700; font-size: 0.9rem; color: #374151; display: block; margin-bottom: 10px;">
                            <i class="fa-solid fa-comment" style="margin-right: 6px; color: #64748b;"></i>
                            Comment (Optional)
                        </label>
                        <textarea id="profileReviewComment"
                                  name="comment"
                                  class="holo-textarea"
                                  placeholder="Share details about your experience..."></textarea>
                    </div>

                    <!-- Buttons -->
                    <div class="holo-modal-buttons">
                        <button type="button" onclick="closeReviewModal()" class="holo-btn-cancel">
                            Cancel
                        </button>
                        <button type="button" onclick="submitProfileReview()" class="holo-btn-submit">
                            <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i>
                            Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- Profile FAB -->
<?php if ($isLoggedIn): ?>
<div class="profile-fab">
    <button class="profile-fab-main" onclick="toggleProfileFab()" aria-label="Quick Actions">
        <i class="fa-solid fa-ellipsis-vertical"></i>
    </button>
    <div class="profile-fab-menu" id="profileFabMenu">
        <?php if ($isOwner): ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/edit" class="profile-fab-item">
                <i class="fa-solid fa-pen icon-edit"></i>
                <span>Edit Profile</span>
            </a>
        <?php else: ?>
            <?php if ($canImpersonate): ?>
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin/impersonate" method="POST" onsubmit="return confirm('You are about to login as <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>. Continue?');" style="display:inline; width: 100%;">
                <?= Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                <button type="submit" class="profile-fab-item" style="width: 100%; text-align: left; background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.2)); border: 1px solid rgba(245, 158, 11, 0.3);">
                    <i class="fa-solid fa-user-secret" style="color: #fbbf24;"></i>
                    <span style="color: #fbbf24;">Login As User</span>
                </button>
            </form>
            <?php endif; ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/messages/thread/<?= $user['id'] ?>" class="profile-fab-item">
                <i class="fa-solid fa-paper-plane icon-message"></i>
                <span>Send Message</span>
            </a>
            <button onclick="openReviewModal(); toggleProfileFab();" class="profile-fab-item">
                <i class="fa-solid fa-star icon-review"></i>
                <span>Write Review</span>
            </button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
function toggleProfileFab() {
    const menu = document.getElementById('profileFabMenu');
    const btn = document.querySelector('.profile-fab-main');
    menu.classList.toggle('show');
    btn.classList.toggle('active');
}

document.addEventListener('click', function(e) {
    const fab = document.querySelector('.profile-fab');
    if (fab && !fab.contains(e.target)) {
        document.getElementById('profileFabMenu')?.classList.remove('show');
        document.querySelector('.profile-fab-main')?.classList.remove('active');
    }
});

// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.htb-btn, button, .glass-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#6366f1';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#6366f1');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<!-- Badge Detail Modal/Drawer -->
<div id="badgeModal" class="badge-modal-overlay" onclick="closeBadgeModalOnBackdrop(event)">
    <div class="badge-modal-content">
        <div class="badge-modal-handle"></div>
        <div class="badge-modal-header" id="badgeModalHeader">
            <button type="button" class="badge-modal-close" onclick="closeBadgeModal()" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="badge-modal-icon" id="badgeModalIcon"></div>
            <h3 class="badge-modal-name" id="badgeModalName"></h3>
            <div class="badge-rarity-tag" id="badgeModalRarity"></div>
        </div>
        <div class="badge-modal-body">
            <div class="badge-modal-section">
                <div class="badge-modal-label">
                    <i class="fa-solid fa-trophy"></i> Achievement Unlocked For
                </div>
                <div class="badge-modal-text description" id="badgeModalDesc"></div>
            </div>
            <div class="badge-modal-section">
                <div class="badge-modal-label">
                    <i class="fa-solid fa-calendar"></i> Awarded On
                </div>
                <div class="badge-modal-text" id="badgeModalDate"></div>
            </div>
            <div class="badge-modal-section">
                <div class="badge-modal-label">
                    <i class="fa-solid fa-gem"></i> Rarity
                </div>
                <div class="badge-modal-text" id="badgeModalRarityText"></div>
                <div class="badge-rarity-bar">
                    <div class="badge-rarity-fill" id="badgeModalRarityBar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Badge Modal Functions
function openBadgeModal(element) {
    const modal = document.getElementById('badgeModal');
    const header = document.getElementById('badgeModalHeader');
    const icon = document.getElementById('badgeModalIcon');
    const name = document.getElementById('badgeModalName');
    const desc = document.getElementById('badgeModalDesc');
    const date = document.getElementById('badgeModalDate');
    const rarityTag = document.getElementById('badgeModalRarity');
    const rarityText = document.getElementById('badgeModalRarityText');
    const rarityBar = document.getElementById('badgeModalRarityBar');

    // Get data from clicked element
    const badgeName = element.dataset.badgeName || 'Badge';
    const badgeIcon = element.dataset.badgeIcon || '🏆';
    const badgeDesc = element.dataset.badgeDesc || 'earning this achievement';
    const badgeDate = element.dataset.badgeDate || 'Unknown';
    const badgeRarity = element.dataset.badgeRarity || 'Common';
    const badgePercent = parseFloat(element.dataset.badgePercent) || 100;
    const isFeatured = element.dataset.badgeFeatured === 'true';

    // Populate modal
    icon.textContent = badgeIcon;
    name.textContent = badgeName;
    desc.textContent = badgeDesc.charAt(0).toUpperCase() + badgeDesc.slice(1);
    date.textContent = badgeDate;

    // Handle featured badge header
    if (isFeatured) {
        header.classList.add('featured');
    } else {
        header.classList.remove('featured');
    }

    // Set rarity tag
    const rarityLower = badgeRarity.toLowerCase();
    rarityTag.className = 'badge-rarity-tag ' + rarityLower;
    rarityTag.innerHTML = getRarityIcon(rarityLower) + ' ' + badgeRarity;

    // Set rarity text
    if (badgePercent <= 1) {
        rarityText.textContent = `Only ${badgePercent.toFixed(1)}% of members have this badge`;
    } else if (badgePercent <= 5) {
        rarityText.textContent = `Top ${badgePercent.toFixed(1)}% of members`;
    } else if (badgePercent <= 15) {
        rarityText.textContent = `${badgePercent.toFixed(1)}% of members have earned this`;
    } else if (badgePercent <= 40) {
        rarityText.textContent = `Earned by ${badgePercent.toFixed(0)}% of active members`;
    } else {
        rarityText.textContent = `A common achievement (${badgePercent.toFixed(0)}% have it)`;
    }

    // Set rarity bar
    rarityBar.className = 'badge-rarity-fill ' + rarityLower;
    rarityBar.style.width = '0%';

    // Show modal
    modal.classList.add('visible');
    document.body.style.overflow = 'hidden';

    // Animate rarity bar
    setTimeout(() => {
        // Invert percentage for visual (rarer = less fill = more impressive)
        const fillWidth = Math.max(5, 100 - badgePercent);
        rarityBar.style.width = fillWidth + '%';
    }, 100);

    // Haptic feedback on mobile
    if (navigator.vibrate) {
        navigator.vibrate(10);
    }
}

function getRarityIcon(rarity) {
    switch(rarity) {
        case 'legendary': return '<i class="fa-solid fa-crown"></i>';
        case 'epic': return '<i class="fa-solid fa-gem"></i>';
        case 'rare': return '<i class="fa-solid fa-star"></i>';
        case 'uncommon': return '<i class="fa-solid fa-circle-up"></i>';
        default: return '<i class="fa-solid fa-circle"></i>';
    }
}

function closeBadgeModal() {
    const modal = document.getElementById('badgeModal');
    const content = modal.querySelector('.badge-modal-content');

    // On mobile, animate drawer closing
    if (window.innerWidth <= 640) {
        content.classList.add('closing');
        setTimeout(() => {
            modal.classList.remove('visible');
            content.classList.remove('closing');
            document.body.style.overflow = '';
        }, 200);
    } else {
        modal.classList.remove('visible');
        document.body.style.overflow = '';
    }
}

function closeBadgeModalOnBackdrop(event) {
    if (event.target === event.currentTarget) {
        closeBadgeModal();
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const badgeModal = document.getElementById('badgeModal');
        if (badgeModal && badgeModal.classList.contains('visible')) {
            closeBadgeModal();
        }
    }
});

// Safety: restore scroll if modal somehow gets stuck
(function() {
    // Check periodically if body overflow is stuck
    setInterval(function() {
        const modal = document.getElementById('badgeModal');
        if (document.body.style.overflow === 'hidden' && modal && !modal.classList.contains('visible')) {
            document.body.style.overflow = '';
        }
    }, 2000);

    // Also restore on any touch/click if modal not visible
    document.addEventListener('touchstart', function() {
        const modal = document.getElementById('badgeModal');
        if (document.body.style.overflow === 'hidden' && modal && !modal.classList.contains('visible')) {
            document.body.style.overflow = '';
        }
    }, { passive: true });
})();

// Handle keyboard activation for badges
document.querySelectorAll('.badge-clickable').forEach(badge => {
    badge.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openBadgeModal(this);
        }
    });
});

// Profile Badges Accordion
(function() {
    const accordions = document.querySelectorAll('.badges-accordion');

    accordions.forEach(accordion => {
        const header = accordion.querySelector('.badges-accordion-header');
        if (!header) return;

        // Start collapsed on all screen sizes
        accordion.classList.remove('open');
        header.setAttribute('aria-expanded', 'false');

        // Toggle on click
        header.addEventListener('click', function(e) {
            e.preventDefault();
            const isOpen = accordion.classList.contains('open');

            if (isOpen) {
                accordion.classList.remove('open');
                header.setAttribute('aria-expanded', 'false');
            } else {
                accordion.classList.add('open');
                header.setAttribute('aria-expanded', 'true');
            }
        });

        // Keyboard accessibility
        header.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                header.click();
            }
        });
    });
})();
</script>
</main>

<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>