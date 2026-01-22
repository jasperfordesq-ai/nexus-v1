<?php
// CivicOne View: User Profile - GOV.UK Template C (Detail Page)
// ==============================================================
// Pattern: Template C - Detail page with 2/3 + 1/3 column split
// Components: GOV.UK Summary list for profile details
// WCAG 2.1 AA Compliant
// Refactored: 2026-01-20

if (session_status() === PHP_SESSION_NONE) session_start();

$currentUserId = $_SESSION['user_id'] ?? 0;
$targetUserId = $user['id'] ?? 0;
$isOwner = ($currentUserId == $targetUserId);
$tenantId = $_SESSION['current_tenant_id'] ?? 1;
$isLoggedIn = !empty($currentUserId);

// ---------------------------------------------------------
// AJAX HANDLERS (Preserved from original)
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
$profileIsOnline = $profileLastActiveAt && (strtotime($profileLastActiveAt) > strtotime('-5 minutes'));
$profileIsRecentlyActive = $profileLastActiveAt && (strtotime($profileLastActiveAt) > strtotime('-24 hours')) && !$profileIsOnline;
$profileStatusText = 'Offline';
if ($profileIsOnline) {
    $profileStatusText = 'Active now';
} elseif ($profileIsRecentlyActive) {
    $hours = floor((time() - strtotime($profileLastActiveAt)) / 3600);
    $profileStatusText = $hours < 1 ? 'Active recently' : "Active {$hours}h ago";
}

// Prepare variables for profile-header component
$displayName = $user['name'] ?? ($user['first_name'] . ' ' . $user['last_name']);
$userOrganizations = $userOrganizations ?? [];
$headerReviewStats = $headerReviewStats ?? [];
$headerAvgRating = $headerAvgRating ?? 0;
$headerTotalReviews = $headerTotalReviews ?? 0;

// Load header
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<!-- Profile Header Component (MOJ Identity Bar) -->
<?php require __DIR__ . '/components/profile-header.php'; ?>

<!-- Template C: Detail Page (2/3 + 1/3 layout) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Breadcrumbs (GOV.UK Template C requirement - DP-004) -->
        <nav class="civicone-breadcrumbs" aria-label="Breadcrumb">
            <ol class="civicone-breadcrumbs__list">
                <li class="civicone-breadcrumbs__list-item">
                    <a class="civicone-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
                </li>
                <li class="civicone-breadcrumbs__list-item">
                    <a class="civicone-breadcrumbs__link" href="<?= $basePath ?>/members">Members</a>
                </li>
                <li class="civicone-breadcrumbs__list-item" aria-current="page">
                    <?= htmlspecialchars($displayName) ?>
                </li>
            </ol>
        </nav>

        <div class="civicone-grid-row">

            <!-- Main Content: 2/3 Column -->
            <div class="civicone-grid-column-two-thirds">

                <!-- GOV.UK Summary List: Profile Details -->
                <h2 class="civicone-heading-l">Profile Information</h2>

                <dl class="civicone-summary-list">
                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Full name</dt>
                        <dd class="civicone-summary-list__value"><?= htmlspecialchars($displayName) ?></dd>
                    </div>

                    <?php if (!empty($user['location'])): ?>
                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Location</dt>
                        <dd class="civicone-summary-list__value"><?= htmlspecialchars($user['location']) ?></dd>
                    </div>
                    <?php endif; ?>

                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Member since</dt>
                        <dd class="civicone-summary-list__value"><?= date('F Y', strtotime($user['created_at'])) ?></dd>
                    </div>

                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Credit balance</dt>
                        <dd class="civicone-summary-list__value"><?= number_format($user['balance'] ?? 0) ?> Credits</dd>
                    </div>

                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Exchanges</dt>
                        <dd class="civicone-summary-list__value"><?= $exchangesCount ?? 0 ?></dd>
                    </div>

                    <?php if (!empty($user['bio'])): ?>
                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">About</dt>
                        <dd class="civicone-summary-list__value"><?= nl2br(htmlspecialchars($user['bio'])) ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>

                <!-- Post Composer (Owner only) -->
                <?php if ($isOwner): ?>
                <div class="civic-composer govuk-!-margin-top-6">
                    <h2 class="civicone-heading-m">Share an update</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="civicone-form-group">
                            <textarea name="content" class="civicone-textarea" rows="3" placeholder="What's on your mind?" required></textarea>
                        </div>
                        <div class="civic-composer-actions">
                            <label class="civicone-button civicone-button--secondary">
                                <i class="fa-solid fa-image" aria-hidden="true"></i> Add Photo
                                <input type="file" name="image" accept="image/*" class="civicone-visually-hidden">
                            </label>
                            <button type="submit" class="civicone-button">Post</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Posts Section -->
                <h2 class="civicone-heading-l govuk-!-margin-top-8">
                    <?= $isOwner ? 'Your Posts' : htmlspecialchars($displayName) . "'s Posts" ?>
                </h2>

                <?php if (empty($posts)): ?>
                    <div class="civicone-inset-text">
                        No posts yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="civic-post-card" id="post-<?= $post['id'] ?>">
                            <!-- Post Header -->
                            <div class="civic-post-header">
                                <img src="<?= htmlspecialchars($post['author_avatar']) ?>" alt="" class="civic-avatar-sm">
                                <div>
                                    <div class="civicone-heading-s govuk-!-margin-bottom-1">
                                        <?= htmlspecialchars($post['author_name']) ?>
                                    </div>
                                    <div class="civicone-body-s govuk-!-margin-bottom-0 civicone-text-secondary">
                                        <?= date('j F Y \a\t g:i a', strtotime($post['created_at'])) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Post Content -->
                            <div class="civic-post-content civicone-body">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>

                            <?php if (!empty($post['image_url'])): ?>
                                <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="" class="civic-post-image">
                            <?php endif; ?>

                            <!-- Post Actions -->
                            <div class="civic-post-actions">
                                <button class="civic-action-btn <?= $post['is_liked'] ? 'liked' : '' ?>"
                                        onclick="toggleLike('post', <?= $post['id'] ?>, this)">
                                    <i class="<?= $post['is_liked'] ? 'fa-solid' : 'fa-regular' ?> fa-heart" aria-hidden="true"></i>
                                    <span class="like-count"><?= (int)$post['likes_count'] ?></span> Like
                                </button>
                                <button class="civic-action-btn" onclick="toggleComments('post', <?= $post['id'] ?>)">
                                    <i class="fa-regular fa-comment" aria-hidden="true"></i>
                                    <span class="comment-count"><?= (int)$post['comments_count'] ?></span> Comment
                                </button>
                            </div>

                            <!-- Comments Section -->
                            <div class="civic-comments-section" id="comments-section-post-<?= $post['id'] ?>">
                                <div class="comments-list">
                                    <div class="civicone-body-s civic-loading-message">Click to load comments</div>
                                </div>

                                <?php if ($isLoggedIn): ?>
                                <div class="civic-comment-form">
                                    <input type="text" class="civic-comment-input civicone-input" placeholder="Write a comment..."
                                           onkeydown="if(event.key === 'Enter') submitComment(this, 'post', <?= $post['id'] ?>)">
                                    <button class="civicone-button civic-comment-submit" onclick="submitComment(this.previousElementSibling, 'post', <?= $post['id'] ?>)">Post</button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Reviews Section -->
                <h2 class="civicone-heading-l govuk-!-margin-top-8" id="reviews-section">Reviews</h2>

                <?php if (empty($reviews)): ?>
                    <div class="civicone-inset-text">
                        No reviews yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="civicone-summary-card">
                            <div class="civicone-summary-card__title-wrapper">
                                <h2 class="civicone-summary-card__title"><?= htmlspecialchars($review['reviewer_name'] ?? 'Anonymous') ?></h2>
                                <div class="civicone-summary-card__actions">
                                    <span class="civic-review-rating" aria-label="Rating: <?= $review['rating'] ?> out of 5 stars">
                                        <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="civicone-summary-card__content">
                                <p class="civicone-body"><?= nl2br(htmlspecialchars($review['content'] ?? '')) ?></p>
                                <p class="civicone-body-s civicone-text-secondary govuk-!-margin-top-2">
                                    <?= date('j F Y', strtotime($review['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Sidebar: 1/3 Column (Related Links/Actions) -->
            <div class="civicone-grid-column-one-third">
                <aside class="civicone-related-content">
                    <h2 class="civicone-heading-m">Related content</h2>

                    <nav role="navigation" aria-labelledby="subsection-title">
                        <ul class="civicone-list govuk-!-font-size-16">
                            <?php if ($isOwner): ?>
                                <li><a class="civicone-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/edit">Edit your profile</a></li>
                                <li><a class="civicone-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/settings">Account settings</a></li>
                                <?php if (\Nexus\Core\TenantContext::hasFeature('timebanking')): ?>
                                <li><a class="civicone-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet">View your wallet</a></li>
                                <li><a class="civicone-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet/insights">Wallet insights</a></li>
                                <?php endif; ?>
                            <?php else: ?>
                                <li><a class="civicone-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/messages/create?to=<?= $user['id'] ?>">Send a message</a></li>
                                <?php if (\Nexus\Core\TenantContext::hasFeature('timebanking')): ?>
                                <li><a class="civicone-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet?to=<?= $user['id'] ?>">Send credits</a></li>
                                <?php endif; ?>
                                <li><a class="civicone-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/members">Browse all members</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>

                    <?php if (!empty($userOrganizations)): ?>
                    <h3 class="civicone-heading-s govuk-!-margin-top-6">Organizations</h3>
                    <ul class="civicone-list govuk-!-font-size-16">
                        <?php foreach ($userOrganizations as $org): ?>
                            <li>
                                <a class="civicone-link" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>">
                                    <?= htmlspecialchars($org['name']) ?>
                                    <?php if ($org['member_role'] === 'owner'): ?>
                                        <span class="civicone-tag civicone-tag--yellow">Owner</span>
                                    <?php elseif ($org['member_role'] === 'admin'): ?>
                                        <span class="civicone-tag civicone-tag--purple">Admin</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </aside>
            </div>

        </div>
    </main>
</div>

<!-- Toast Notification -->
<div class="civic-toast" id="civic-toast"></div>

<!-- External JavaScript for social interactions (CLAUDE.md compliant) -->
<link rel="stylesheet" href="/assets/css/civicone-profile-show.css">
<script src="/assets/js/civicone-profile-show.js"></script>
<script>
    // Initialize with logged-in state from PHP
    window.CivicProfile.init(<?= $isLoggedIn ? 'true' : 'false' ?>);

    // Legacy function mappings for inline onclick handlers
    // TODO: Convert inline handlers to addEventListener in future refactor
    function toggleLike(type, id, btn) {
        window.CivicProfile.toggleLike(type, id, btn);
    }
    function toggleComments(type, id) {
        window.CivicProfile.toggleComments(type, id);
    }
    function submitComment(input, type, id) {
        window.CivicProfile.submitComment(input, type, id);
    }
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
