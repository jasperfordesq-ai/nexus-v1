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
                            <label class="civicone-button civicone-button--secondary" style="cursor: pointer;">
                                <i class="fa-solid fa-image" aria-hidden="true"></i> Add Photo
                                <input type="file" name="image" accept="image/*" style="display: none;">
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
                                <div style="flex: 1;">
                                    <div class="civicone-heading-s govuk-!-margin-bottom-1">
                                        <?= htmlspecialchars($post['author_name']) ?>
                                    </div>
                                    <div class="civicone-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
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
                                    <div class="civicone-body-s" style="color: #505a5f; text-align: center; padding: 20px;">Click to load comments</div>
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
                                    <span style="color: #f47738; font-size: 1.2rem;" aria-label="Rating: <?= $review['rating'] ?> out of 5 stars">
                                        <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="civicone-summary-card__content">
                                <p class="civicone-body"><?= nl2br(htmlspecialchars($review['content'] ?? '')) ?></p>
                                <p class="civicone-body-s" style="color: #505a5f; margin-top: 10px;">
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

<!-- JavaScript for social interactions -->
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
    list.innerHTML = '<div class="civicone-body-s" style="color: #505a5f; text-align: center; padding: 20px;">Loading...</div>';

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
                list.innerHTML = '<div class="civicone-body-s" style="color: #505a5f; text-align: center; padding: 20px;">No comments yet. Be the first!</div>';
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            list.innerHTML = '<div class="civicone-body-s" style="color: #d4351c; text-align: center; padding: 20px;">Error loading comments</div>';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderComment(c, depth) {
    const marginLeft = depth * 40;
    const isEdited = c.is_edited ? '<span style="font-size: 0.7rem; color: #505a5f;"> (edited)</span>' : '';
    const ownerActions = c.is_owner ? `
        <span onclick="editComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'").replace(/\n/g, "\\n")}')" style="cursor: pointer;" title="Edit" tabindex="0" role="button" aria-label="Edit comment">✏️</span>
        <span onclick="deleteComment(${c.id})" style="cursor: pointer; margin-left: 5px;" title="Delete" tabindex="0" role="button" aria-label="Delete comment">🗑️</span>
    ` : '';

    // Reactions
    const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
        const isActive = (c.user_reactions || []).includes(emoji);
        return `<span class="civic-reaction ${isActive ? 'active' : ''}" onclick="toggleReaction(${c.id}, '${emoji}')" role="button" tabindex="0">${emoji} ${count}</span>`;
    }).join('');

    // Reaction picker
    const reactionPicker = IS_LOGGED_IN ? `
        <div class="civic-reaction-picker">
            <span class="civic-reaction" onclick="toggleReactionPicker(${c.id})" role="button" tabindex="0" aria-label="Add reaction">+</span>
            <div class="civic-reaction-picker-menu" id="picker-${c.id}">
                ${availableReactions.map(e => `<span onclick="toggleReaction(${c.id}, '${e}')" role="button" tabindex="0">${e}</span>`).join('')}
            </div>
        </div>
    ` : '';

    const replyBtn = IS_LOGGED_IN ? `<span onclick="showReplyForm(${c.id})" role="button" tabindex="0">Reply</span>` : '';

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
                        <input type="text" class="civic-reply-input civicone-input" placeholder="Write a reply..."
                               onkeydown="if(event.key === 'Enter') submitReply(${c.id}, this)">
                        <button class="civicone-button civic-comment-submit" onclick="submitReply(${c.id}, this.previousElementSibling)" style="padding: 8px 16px;">Reply</button>
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
