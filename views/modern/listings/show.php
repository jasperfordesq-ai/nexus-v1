<?php
// Phoenix Single Listing View - Modern Redesign v3
// Features: Full-Width Hero, Sidebar Info, Mapbox Integration, Attribute Grid, Likes & Comments
// NOTE: AJAX actions for likes/comments are handled in ListingController::handleListingAjax()

if (session_status() === PHP_SESSION_NONE) session_start();

// Auth Check
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$tenantId = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::get()['id'] : ($_SESSION['current_tenant_id'] ?? 1);
$listingId = $listing['id'] ?? 0;

// ---------------------------------------------------------
// Fetch Like/Comment Counts for Display
// ---------------------------------------------------------
$likesCount = 0;
$commentsCount = 0;
$isLiked = false;

try {
    // Use PDO directly - DatabaseWrapper adds tenant constraints that can cause issues
    $pdo = \Nexus\Core\Database::getInstance();

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = 'listing' AND target_id = ?");
    $stmt->execute([$listingId]);
    $likesResult = $stmt->fetch();
    $likesCount = (int)($likesResult['cnt'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM comments WHERE target_type = 'listing' AND target_id = ?");
    $stmt->execute([$listingId]);
    $commentsResult = $stmt->fetch();
    $commentsCount = (int)($commentsResult['cnt'] ?? 0);

    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = 'listing' AND target_id = ?");
        $stmt->execute([$userId, $listingId]);
        $likedResult = $stmt->fetch();
        $isLiked = !empty($likedResult);
    }
} catch (\Throwable $e) {
    error_log("Listing stats error: " . $e->getMessage());
}

$hero_title = $listing['title'];
require __DIR__ . '/../../layouts/header.php';

// Safe Defaults
$accentColor = $listing['type'] === 'offer' ? '#0ea5e9' : '#f97316';
$currentUser = isset($_SESSION['user_id']) ? \Nexus\Models\User::findById($_SESSION['user_id']) : null;
$isOwner = ($currentUser && $currentUser['id'] == $listing['user_id']);
$isAdmin = ($currentUser && ($currentUser['role'] === 'admin' || $currentUser['is_super_admin'] == 1));
$canEdit = ($isOwner || $isAdmin);
?>

<!-- Mapbox Assets -->
<link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
<script>
    window.NEXUS_MAPBOX_TOKEN = "<?= htmlspecialchars(getenv('MAPBOX_API_KEY') ?: '', ENT_QUOTES, 'UTF-8') ?>";
</script>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div id="listing-show-glass-wrapper">

<div class="htb-page-layout listing-page-wrapper">

    <div class="listing-grid-layout">

        <!-- LEFT COLUMN: Content -->
        <div class="listing-main-column">
            <!-- 1. Hero Image & Title -->
            <div class="glass-hero-card listing-content">
                <?php if (!empty($listing['image_url'])): ?>
                    <div class="listing-hero-image-wrapper">
                        <?= webp_image($listing['image_url'], htmlspecialchars($listing['title']), '') ?>
                        <div class="listing-type-badge-position">
                            <span class="glass-type-badge">
                                <?= ucfirst($listing['type']) ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="listing-card-padding">
                    <div class="glass-category-badge listing-category-badge" style="color: <?= $accentColor ?>;">
                        <?= htmlspecialchars($listing['category_name'] ?? 'General') ?>
                    </div>
                    <h1 class="listing-title"><?= htmlspecialchars($listing['title']) ?></h1>

                    <div class="listing-meta-row">
                        <span><i class="fa-regular fa-calendar"></i> <?= date('M j, Y', strtotime($listing['created_at'])) ?></span>
                        <?php if (!empty($listing['location'])): ?>
                            <span>&bull;</span>
                            <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($listing['location']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. Description -->
            <div class="glass-description-card">
                <h3 class="listing-section-heading">About this listing</h3>
                <div class="listing-description-text">
                    <?= htmlspecialchars($listing['description']) ?>
                </div>
            </div>

            <!-- 2.5. Like & Comment Section -->
            <div class="glass-description-card" id="listing-engagement-section" data-listing-id="<?= $listingId ?>">
                <!-- Like Button Row -->
                <div class="listing-engagement-row">
                    <button id="like-btn" onclick="listingToggleLike()" class="listing-action-btn <?= $isLiked ? 'listing-action-btn--liked' : '' ?>">
                        <i class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart" id="like-icon"></i>
                        <span id="like-count"><?= $likesCount ?></span>
                        <span><?= $likesCount === 1 ? 'Like' : 'Likes' ?></span>
                    </button>
                    <button onclick="listingToggleComments()" class="listing-action-btn">
                        <i class="fa-regular fa-comment"></i>
                        <span id="comment-count"><?= $commentsCount ?></span>
                        <span><?= $commentsCount === 1 ? 'Comment' : 'Comments' ?></span>
                    </button>
                    <?php if ($isLoggedIn): ?>
                    <button onclick="shareToFeed()" class="listing-action-btn">
                        <i class="fa-solid fa-share"></i> Share
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Comments Section (Initially Hidden) -->
                <div id="comments-section" class="listing-comments-section">
                    <h4 class="listing-comments-heading">Comments</h4>

                    <!-- Comment Form -->
                    <?php if ($isLoggedIn): ?>
                        <form id="comment-form" onsubmit="listingSubmitComment(event)" class="listing-comment-form">
                            <?= webp_avatar($_SESSION['user_avatar'] ?? null, $_SESSION['user_name'] ?? 'User', 40) ?>
                            <div class="listing-comment-form-inner">
                                <textarea id="comment-input" placeholder="Write a comment..." class="listing-comment-textarea"></textarea>
                                <button type="submit" class="listing-comment-submit-btn" style="background: <?= $accentColor ?>;">
                                    Post Comment
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="listing-login-prompt">
                            <p class="listing-login-prompt-text">
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="listing-login-link" style="color: <?= $accentColor ?>;">Sign in</a> to leave a comment
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Comments List -->
                    <div id="comments-list" class="listing-comments-list">
                        <div class="listing-loading-text">
                            <i class="fa-solid fa-spinner fa-spin"></i> Loading comments...
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Attributes Grid -->
            <?php if (!empty($attributes)): ?>
                <div class="listing-attributes-section">
                    <h3 class="listing-section-heading">Features</h3>
                    <div class="listing-attributes-grid">
                        <?php foreach ($attributes as $attr): ?>
                            <div class="glass-attribute-pill">
                                <i class="fa-solid fa-check" style="color: <?= $accentColor ?>;"></i>
                                <span class="listing-attribute-name"><?= htmlspecialchars($attr['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 4. Location Map -->
            <?php if (!empty($listing['location']) || (!empty($listing['latitude']) && !empty($listing['longitude']))): ?>
                <div class="glass-map-container">
                    <h3 class="listing-section-heading">Location</h3>

                    <?php if (!empty($listing['location'])): ?>
                        <div class="listing-location-text">
                            <i class="fa-solid fa-location-dot listing-location-icon"></i>
                            <span><?= htmlspecialchars($listing['location']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($listing['latitude']) && !empty($listing['longitude'])): ?>
                        <div id="listing-map" class="listing-map"></div>
                        <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                if (!window.mapboxgl) return;
                                mapboxgl.accessToken = window.NEXUS_MAPBOX_TOKEN;
                                const map = new mapboxgl.Map({
                                    container: 'listing-map',
                                    style: 'mapbox://styles/mapbox/streets-v11',
                                    center: [<?= $listing['longitude'] ?>, <?= $listing['latitude'] ?>],
                                    zoom: 13
                                });
                                new mapboxgl.Marker({
                                        color: '<?= $accentColor ?>'
                                    })
                                    .setLngLat([<?= $listing['longitude'] ?>, <?= $listing['latitude'] ?>])
                                    .addTo(map);
                            });
                        </script>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN: Sidebar -->
        <aside class="listing-sidebar">
            <!-- Author Card -->
            <div class="glass-author-card">
                <div class="listing-author-header">
                    <?= webp_avatar($listing['avatar_url'] ?? null, $listing['author_name'], 56) ?>
                    <div>
                        <div class="listing-author-name"><?= htmlspecialchars($listing['author_name']) ?></div>
                        <div class="listing-author-role">Member</div>
                    </div>
                </div>

                <?php if ($canEdit): ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/edit/<?= $listing['id'] ?>" class="listing-edit-btn listing-edit-btn-styled">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Listing
                    </a>
                <?php else: ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages/<?= $listing['user_id'] ?>?ref=<?= urlencode("Re: " . $listing['title']) ?>" class="listing-cta-btn" style="background: <?= $accentColor ?>;">
                        Message Author
                    </a>
                <?php endif; ?>
            </div>

            <!-- SDGs Sidebar -->
            <?php if (!empty($listing['sdg_goals'])):
                $goals = json_decode($listing['sdg_goals'], true);
                if (is_array($goals) && count($goals) > 0):
                    require_once __DIR__ . '/../../../src/Helpers/SDG.php';
            ?>
                    <div class="glass-sdg-card">
                        <h4 class="listing-sdg-heading">Social Impact</h4>
                        <div class="listing-sdg-list">
                            <?php foreach ($goals as $gid):
                                $goal = \Nexus\Helpers\SDG::get($gid);
                                if (!$goal) continue;
                            ?>
                                <div class="glass-sdg-item listing-sdg-item" style="border-left: 3px solid <?= $goal['color'] ?>;">
                                    <span><?= $goal['icon'] ?></span>
                                    <span class="listing-sdg-label"><?= $goal['label'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
            <?php endif;
            endif; ?>
        </aside>

    </div>
</div>

</div><!-- #listing-show-glass-wrapper -->

<!-- JavaScript for Like/Comment Functionality - Using Master Platform Social Media Module -->
<script>
(function() {
    const listingId = <?= $listingId ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    let isLiked = <?= $isLiked ? 'true' : 'false' ?>;
    let commentsLoaded = false;
    let availableReactions = [];

    // Master Platform Social Media Module API Base
    const API_BASE = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/social';

    // Toggle Like - Using Master Social Module API (unique name to avoid conflict with social-interactions.js)
    window.listingToggleLike = async function() {
        <?php if (!$isLoggedIn): ?>
        window.location.href = '<?= Nexus\Core\TenantContext::getBasePath() ?>/login';
        return;
        <?php endif; ?>

        const btn = document.getElementById('like-btn');
        const icon = document.getElementById('like-icon');
        const countEl = document.getElementById('like-count');

        btn.disabled = true;

        try {
            const response = await fetch(API_BASE + '/like', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    target_type: 'listing',
                    target_id: listingId
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Like API error response:', response.status, errorText);
                alert('Like failed: ' + (response.status === 401 ? 'Please log in' : 'Server error'));
                return;
            }

            const data = await response.json();

            if (data.error) {
                if (data.redirect) window.location.href = data.redirect;
                else {
                    console.error('Like error:', data.error);
                    alert('Like failed: ' + data.error);
                }
                return;
            }

            isLiked = (data.status === 'liked');
            countEl.textContent = data.likes_count;

            if (isLiked) {
                btn.classList.add('listing-action-btn--liked');
                icon.className = 'fa-solid fa-heart';
            } else {
                btn.classList.remove('listing-action-btn--liked');
                icon.className = 'fa-regular fa-heart';
            }

        } catch (err) {
            console.error('Like error:', err);
        } finally {
            btn.disabled = false;
        }
    };

    // Toggle Comments Section (unique name to avoid conflict with social-interactions.js)
    window.listingToggleComments = function() {
        // Check if mobile (screen width <= 768px or touch device)
        const isMobile = window.innerWidth <= 768 || ('ontouchstart' in window);

        if (isMobile && typeof openMobileCommentSheet === 'function') {
            // Use mobile drawer on mobile devices
            openMobileCommentSheet('listing', listingId, '');
            return;
        }

        // Desktop: use inline comments section
        const section = document.getElementById('comments-section');
        const isHidden = !section.classList.contains('listing-comments-section--visible');

        section.classList.toggle('listing-comments-section--visible');

        if (isHidden && !commentsLoaded) {
            loadComments();
        }
    };

    // Load Comments - Using Master Social Module API
    async function loadComments() {
        const list = document.getElementById('comments-list');
        list.innerHTML = '<p class="listing-loading-text">Loading comments...</p>';

        try {
            const response = await fetch(API_BASE + '/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'fetch',
                    target_type: 'listing',
                    target_id: listingId
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Comments API error:', response.status, errorText);
                list.innerHTML = '<p class="listing-loading-text">Failed to load comments (HTTP ' + response.status + ')</p>';
                return;
            }

            const data = await response.json();

            if (data.error) {
                list.innerHTML = '<p class="listing-loading-text">Failed to load comments: ' + data.error + '</p>';
                return;
            }

            commentsLoaded = true;
            availableReactions = data.available_reactions || [];

            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<p class="listing-loading-text">No comments yet. Be the first to comment!</p>';
                return;
            }

            list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');

        } catch (err) {
            console.error('Load comments error:', err);
            list.innerHTML = '<p class="listing-loading-text">Error loading comments</p>';
        }
    }

    // Render Comment with Nested Replies
    function renderComment(c, depth) {
        const indent = depth * 20;
        const isEdited = c.is_edited ? '<span class="listing-edited-tag"> (edited)</span>' : '';
        const ownerActions = c.is_owner ? `
            <span onclick="listingEditComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'")}')" class="listing-owner-action" title="Edit">✏️</span>
            <span onclick="listingDeleteComment(${c.id})" class="listing-owner-action listing-owner-action--delete" title="Delete">🗑️</span>
        ` : '';

        const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
            const isUserReaction = (c.user_reactions || []).includes(emoji);
            return `<span onclick="listingToggleReaction(${c.id}, '${emoji}')" class="listing-reaction-badge ${isUserReaction ? 'listing-reaction-badge--active' : ''}">${emoji} ${count}</span>`;
        }).join(' ');

        const reactionPicker = isLoggedIn ? `
            <div class="listing-reaction-picker">
                <span onclick="listingShowReactionPicker(${c.id})" class="listing-reaction-add-btn">+</span>
                <div id="picker-${c.id}" class="listing-reaction-picker-dropdown">
                    ${availableReactions.map(e => `<span onclick="listingToggleReaction(${c.id}, '${e}')" class="listing-reaction-emoji">${e}</span>`).join('')}
                </div>
            </div>
        ` : '';

        const replyButton = isLoggedIn ? `<span onclick="listingShowReplyForm(${c.id})" class="listing-comment-reply-link" style="color: <?= $accentColor ?>;">Reply</span>` : '';

        const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

        return `
            <div class="listing-comment-item" style="margin-left: ${indent}px;" id="comment-${c.id}">
                <div class="listing-comment-content">
                    <img src="${c.author_avatar}" loading="lazy" class="listing-comment-avatar ${depth > 0 ? 'listing-comment-avatar--small' : ''}">
                    <div class="listing-comment-body">
                        <div class="listing-comment-author">
                            ${escapeHtml(c.author_name)}${isEdited}
                            ${ownerActions}
                        </div>
                        <div id="content-${c.id}" class="listing-comment-text">${formatContent(c.content)}</div>
                        <div class="listing-comment-meta">
                            ${formatTime(c.created_at)}
                            ${replyButton}
                        </div>
                        <div class="listing-comment-reactions">
                            ${reactions}
                            ${reactionPicker}
                        </div>
                        <div id="reply-form-${c.id}" class="listing-reply-form">
                            <div class="listing-reply-form-inner">
                                <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." class="listing-reply-input">
                                <button onclick="listingSubmitReply(${c.id})" class="listing-reply-btn" style="background: <?= $accentColor ?>;">Reply</button>
                            </div>
                        </div>
                    </div>
                </div>
                ${replies}
            </div>
        `;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatContent(content) {
        return escapeHtml(content).replace(/@(\w+)/g, '<span class="listing-mention" style="color: <?= $accentColor ?>;">@$1</span>');
    }

    function formatTime(datetime) {
        try {
            const date = new Date(datetime);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        } catch (e) { return ''; }
    }

    window.listingShowReactionPicker = function(commentId) {
        const picker = document.getElementById(`picker-${commentId}`);
        picker.classList.toggle('listing-reaction-picker-dropdown--visible');
    };

    window.listingShowReplyForm = function(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        const isVisible = form.classList.toggle('listing-reply-form--visible');
        if (isVisible) {
            document.getElementById(`reply-input-${commentId}`).focus();
        }
    };

    window.listingToggleReaction = async function(commentId, emoji) {
        if (!isLoggedIn) { alert('Please log in to react'); return; }

        try {
            const response = await fetch(API_BASE + '/reaction', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    emoji: emoji
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            loadComments();
        } catch (err) { console.error('Reaction error:', err); }
    };

    window.listingSubmitReply = async function(parentId) {
        const input = document.getElementById(`reply-input-${parentId}`);
        const content = input.value.trim();
        if (!content) return;

        try {
            const response = await fetch(API_BASE + '/reply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    target_type: 'listing',
                    target_id: listingId,
                    parent_id: parentId,
                    content: content
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            input.value = '';
            document.getElementById(`reply-form-${parentId}`).classList.remove('listing-reply-form--visible');
            const countEl = document.getElementById('comment-count');
            countEl.textContent = parseInt(countEl.textContent) + 1;
            loadComments();
        } catch (err) { console.error('Reply error:', err); }
    };

    window.listingDeleteComment = async function(commentId) {
        if (!confirm('Delete this comment?')) return;

        try {
            const response = await fetch(API_BASE + '/delete-comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    comment_id: commentId
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            const countEl = document.getElementById('comment-count');
            countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
            loadComments();
        } catch (err) { console.error('Delete error:', err); }
    };

    window.listingEditComment = function(commentId, currentContent) {
        const contentEl = document.getElementById(`content-${commentId}`);
        const originalHtml = contentEl.innerHTML;

        contentEl.innerHTML = `
            <div class="listing-edit-form">
                <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" class="listing-edit-input">
                <button onclick="saveEdit(${commentId})" class="listing-edit-save-btn" style="background: <?= $accentColor ?>;">Save</button>
                <button onclick="cancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" class="listing-edit-cancel-btn">Cancel</button>
            </div>
        `;
        document.getElementById(`edit-input-${commentId}`).focus();
    };

    window.cancelEdit = function(commentId, originalHtml) {
        document.getElementById(`content-${commentId}`).innerHTML = originalHtml;
    };

    window.saveEdit = async function(commentId) {
        const input = document.getElementById(`edit-input-${commentId}`);
        const newContent = input.value.trim();
        if (!newContent) return;

        try {
            const response = await fetch(API_BASE + '/edit-comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    content: newContent
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            loadComments();
        } catch (err) { console.error('Edit error:', err); }
    };

    // Submit Comment - Using Master Social Module API (unique name to avoid conflict with social-interactions.js)
    window.listingSubmitComment = async function(e) {
        e.preventDefault();

        const input = document.getElementById('comment-input');
        const content = input.value.trim();
        if (!content) return;

        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Posting...';

        try {
            const response = await fetch(API_BASE + '/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'submit',
                    target_type: 'listing',
                    target_id: listingId,
                    content: content
                })
            });

            const data = await response.json();

            if (data.error) { alert(data.error); return; }

            input.value = '';
            const countEl = document.getElementById('comment-count');
            countEl.textContent = parseInt(countEl.textContent) + 1;
            commentsLoaded = false;
            loadComments();

        } catch (err) {
            console.error('Submit comment error:', err);
            alert('Failed to post comment');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Post Comment';
        }
    };

    // Share to Feed - Using Master Social Module API
    window.shareToFeed = async function() {
        if (!confirm('Share this listing to your feed?')) return;

        try {
            const response = await fetch(API_BASE + '/share', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    parent_type: 'listing',
                    parent_id: listingId
                })
            });

            const data = await response.json();

            if (data.error) { alert(data.error); return; }
            if (data.status === 'success') {
                alert('Listing shared to your feed!');
            }
        } catch (err) {
            console.error('Share error:', err);
            alert('Failed to share listing');
        }
    };

    // ============================================
    // GOLD STANDARD - Native App Features
    // ============================================

    // Offline Indicator
    (function initOfflineIndicator() {
        const banner = document.getElementById('offlineBanner');
        if (!banner) return;

        let wasOffline = false;

        function handleOffline() {
            wasOffline = true;
            banner.classList.add('visible');
            if (navigator.vibrate) navigator.vibrate(100);
        }

        function handleOnline() {
            banner.classList.remove('visible');
            wasOffline = false;
        }

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        if (!navigator.onLine) {
            handleOffline();
        }
    })();

    // Heart Burst Animation for Likes
    window.createHeartBurst = function(element) {
        const rect = element.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const hearts = ['❤️', '💜', '💙', '🧡', '💗'];

        for (let i = 0; i < 6; i++) {
            const heart = document.createElement('div');
            heart.textContent = hearts[Math.floor(Math.random() * hearts.length)];
            heart.style.cssText = `
                position: fixed;
                left: ${centerX}px;
                top: ${centerY}px;
                font-size: ${16 + Math.random() * 10}px;
                pointer-events: none;
                z-index: 10000;
                animation: heartBurst ${0.6 + Math.random() * 0.3}s ease-out forwards;
                --tx: ${(Math.random() - 0.5) * 120}px;
                --ty: ${-60 - Math.random() * 80}px;
            `;
            document.body.appendChild(heart);
            setTimeout(() => heart.remove(), 1000);
        }
    };

    // Enhanced Like Toggle with animation
    const originalToggleLike = window.toggleLike;
    window.toggleLike = async function() {
        const likeBtn = document.getElementById('like-btn');
        const icon = likeBtn.querySelector('i');
        const wasLiked = likeBtn.dataset.liked === 'true';

        // If liking, add animation
        if (!wasLiked) {
            icon.classList.add('like-pop');
            createHeartBurst(likeBtn);
            if (navigator.vibrate) {
                navigator.vibrate([10, 50, 20]);
            }
            setTimeout(() => icon.classList.remove('like-pop'), 400);
        }

        // Call original function
        await originalToggleLike();
    };

    // Dynamic Theme Color
    (function initDynamicThemeColor() {
        const themeColorMeta = document.querySelector('meta[name="theme-color"]');
        if (!themeColorMeta) return;

        function updateThemeColor() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            themeColorMeta.setAttribute('content', isDark ? '#0f172a' : '#ffffff');
        }

        const observer = new MutationObserver(updateThemeColor);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });

        updateThemeColor();
    })();

    // Button Press States
    document.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('pointerdown', function() {
            this.classList.add('listing-btn-pressed');
        });
        btn.addEventListener('pointerup', function() {
            this.classList.remove('listing-btn-pressed');
        });
        btn.addEventListener('pointerleave', function() {
            this.classList.remove('listing-btn-pressed');
        });
    });
})();
</script>

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>