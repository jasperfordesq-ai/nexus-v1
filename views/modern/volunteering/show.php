<?php
// Volunteering Opportunity Show - Glassmorphism Design
// Features: Glass cards, Like/Comment, Apply Form, Shift Selection

if (session_status() === PHP_SESSION_NONE) session_start();

// Auth Check
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$tenantId = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::get()['id'] : ($_SESSION['current_tenant_id'] ?? 1);
$opportunityId = $opportunity['id'] ?? 0;

// Fetch Like/Comment Counts
$likesCount = 0;
$commentsCount = 0;
$isLiked = false;

try {
    // Use PDO directly - DatabaseWrapper adds tenant constraints that can cause issues
    $pdo = \Nexus\Core\Database::getInstance();

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = 'volunteering' AND target_id = ?");
    $stmt->execute([$opportunityId]);
    $likesResult = $stmt->fetch();
    $likesCount = (int)($likesResult['cnt'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM comments WHERE target_type = 'volunteering' AND target_id = ?");
    $stmt->execute([$opportunityId]);
    $commentsResult = $stmt->fetch();
    $commentsCount = (int)($commentsResult['cnt'] ?? 0);

    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = 'volunteering' AND target_id = ?");
        $stmt->execute([$userId, $opportunityId]);
        $likedResult = $stmt->fetch();
        $isLiked = !empty($likedResult);
    }
} catch (\Throwable $e) {
    error_log("Volunteering stats error: " . $e->getMessage());
}

$hero_title = $opportunity['title'];
$hero_subtitle = "Volunteer with " . htmlspecialchars($opportunity['org_name']);
$hero_gradient = 'htb-hero-gradient-teal';
$hero_type = 'Volunteering Opportunity';

require __DIR__ . '/../../layouts/header.php';

$accentColor = '#14b8a6'; // Teal for volunteering
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div id="vol-show-glass-wrapper">


<div class="vol-show-wrapper">

    <!-- Back Navigation -->
    <div class="vol-back-nav">
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="vol-back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Opportunities
        </a>
    </div>

    <div class="page-grid vol-page-grid">

        <!-- LEFT COLUMN: Content -->
        <main>
            <!-- Header Card -->
            <div class="glass-card">
                <div class="vol-header-flex">
                    <div>
                        <span class="glass-badge">
                            <i class="fa-solid fa-hand-holding-heart"></i> Volunteering
                        </span>
                        <?php if ($opportunity['location']): ?>
                            <span class="vol-location-text">
                                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($opportunity['location']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <h1 class="vol-title">
                    <?= htmlspecialchars($opportunity['title']) ?>
                </h1>

                <div class="vol-meta-row">
                    <span><i class="fa-solid fa-building"></i> <?= htmlspecialchars($opportunity['org_name']) ?></span>
                    <?php if ($opportunity['org_website']): ?>
                        <span>&bull;</span>
                        <a href="<?= htmlspecialchars($opportunity['org_website']) ?>" target="_blank" class="vol-website-link">
                            Visit Website <i class="fa-solid fa-arrow-up-right-from-square vol-external-icon"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description Card -->
            <div class="glass-card">
                <h3 class="vol-section-heading">
                    <i class="fa-solid fa-info-circle vol-section-icon"></i> About the Role
                </h3>
                <div class="vol-description">
                    <?= nl2br(htmlspecialchars($opportunity['description'])) ?>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="glass-card">
                <h3 class="vol-section-heading vol-section-heading--lg">
                    <i class="fa-solid fa-clipboard-list vol-section-icon"></i> Details
                </h3>
                <div class="vol-details-grid">
                    <div class="glass-info-pill">
                        <i class="fa-solid fa-tools vol-pill-icon"></i>
                        <div>
                            <div class="vol-pill-label">Skills Needed</div>
                            <div class="vol-pill-value">
                                <?= htmlspecialchars($opportunity['skills_needed'] ?? 'None specified') ?>
                            </div>
                        </div>
                    </div>
                    <div class="glass-info-pill">
                        <i class="fa-solid fa-calendar-days vol-pill-icon"></i>
                        <div>
                            <div class="vol-pill-label">Dates</div>
                            <div class="vol-pill-value">
                                <?php if ($opportunity['start_date']): ?>
                                    <?= date('M d, Y', strtotime($opportunity['start_date'])) ?>
                                    <?= $opportunity['end_date'] ? ' - ' . date('M d, Y', strtotime($opportunity['end_date'])) : ' (Ongoing)' ?>
                                <?php else: ?>
                                    Flexible / Ongoing
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Like & Comment Section -->
            <div class="glass-card" id="vol-engagement-section">
                <!-- Like Button Row -->
                <div class="vol-engagement-row">
                    <button id="like-btn" onclick="volToggleLike()" class="vol-action-btn<?= $isLiked ? ' vol-action-btn--liked' : '' ?>">
                        <i class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart" id="like-icon"></i>
                        <span id="like-count"><?= $likesCount ?></span>
                        <span><?= $likesCount === 1 ? 'Like' : 'Likes' ?></span>
                    </button>
                    <button onclick="volToggleComments()" class="vol-action-btn">
                        <i class="fa-regular fa-comment"></i>
                        <span id="comment-count"><?= $commentsCount ?></span>
                        <span><?= $commentsCount === 1 ? 'Comment' : 'Comments' ?></span>
                    </button>
                    <?php if ($isLoggedIn): ?>
                    <button onclick="shareToFeed()" class="vol-action-btn">
                        <i class="fa-solid fa-share"></i> Share
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Comments Section (Initially Hidden) -->
                <div id="comments-section" class="vol-comments-section">
                    <h4 class="vol-comments-heading">Comments</h4>

                    <?php if ($isLoggedIn): ?>
                        <form id="comment-form" onsubmit="volunteeringSubmitComment(event)" class="vol-comment-form">
                            <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp') ?>" loading="lazy" class="vol-avatar">
                            <div class="vol-input-wrapper">
                                <textarea id="comment-input" placeholder="Write a comment..." class="vol-textarea"></textarea>
                                <button type="submit" class="vol-submit-btn">
                                    Post Comment
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="vol-login-prompt">
                            <p>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="vol-login-link">Sign in</a> to leave a comment
                            </p>
                        </div>
                    <?php endif; ?>

                    <div id="comments-list" class="vol-comments-list">
                        <div class="vol-loading-text">
                            <i class="fa-solid fa-spinner fa-spin"></i> Loading comments...
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- RIGHT COLUMN: Sidebar -->
        <aside>
            <div class="glass-sidebar">
                <!-- Organization Info -->
                <div class="vol-org-header">
                    <div class="vol-org-icon-wrapper">
                        <i class="fa-solid fa-building vol-org-icon"></i>
                    </div>
                    <h3 class="vol-org-name">
                        <?= htmlspecialchars($opportunity['org_name']) ?>
                    </h3>
                    <p class="vol-org-label">Organization</p>
                </div>

                <!-- Application Status / Form -->
                <?php if (isset($_GET['msg']) && $_GET['msg'] == 'applied'): ?>
                    <div class="vol-status-card vol-status-card--success">
                        <i class="fa-solid fa-circle-check vol-status-icon vol-status-icon--success"></i>
                        <p class="vol-status-title vol-status-title--success">Application Sent!</p>
                        <p class="vol-status-text vol-status-text--success">The organisation will contact you shortly.</p>
                    </div>
                <?php elseif ($hasApplied): ?>
                    <div class="vol-status-card vol-status-card--pending">
                        <i class="fa-solid fa-clock vol-status-icon vol-status-icon--pending"></i>
                        <p class="vol-status-title vol-status-title--pending">Already Applied</p>
                        <p class="vol-status-text vol-status-text--pending">You've applied for this opportunity.</p>
                    </div>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/apply" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="opportunity_id" value="<?= $opportunity['id'] ?>">

                        <?php if (!empty($shifts)): ?>
                            <div class="vol-form-group">
                                <label class="vol-form-label vol-form-label--shift">
                                    <i class="fa-solid fa-clock vol-section-icon"></i> Select a Shift
                                </label>
                                <div class="vol-shift-list">
                                    <?php foreach ($shifts as $shift): ?>
                                        <label class="glass-shift-card" onclick="this.querySelector('input').checked = true; document.querySelectorAll('.glass-shift-card').forEach(c => c.classList.remove('selected')); this.classList.add('selected');">
                                            <input type="radio" name="shift_id" value="<?= $shift['id'] ?>" required class="hidden">
                                            <div class="vol-shift-row">
                                                <div>
                                                    <div class="vol-shift-date">
                                                        <?= date('M d', strtotime($shift['start_time'])) ?>
                                                    </div>
                                                    <div class="vol-shift-time">
                                                        <?= date('g:i A', strtotime($shift['start_time'])) ?> - <?= date('g:i A', strtotime($shift['end_time'])) ?>
                                                    </div>
                                                </div>
                                                <div class="vol-shift-spots">
                                                    <?= $shift['capacity'] ?> spots
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="vol-form-group">
                            <label class="vol-form-label">
                                <i class="fa-solid fa-message vol-section-icon"></i> Message (Optional)
                            </label>
                            <textarea name="message" rows="3" placeholder="Tell them why you'd like to volunteer..." class="vol-form-textarea"></textarea>
                        </div>

                        <button type="submit" class="btn btn--primary">
                            <i class="fa-solid fa-paper-plane"></i> Apply Now
                        </button>
                    </form>
                <?php else: ?>
                    <div class="vol-status-card vol-status-card--login">
                        <i class="fa-solid fa-user-lock vol-status-icon vol-status-icon--locked"></i>
                        <p class="vol-status-text vol-status-text--muted">Join our community to volunteer.</p>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="btn btn--primary vol-login-btn">
                            Login to Apply
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

    </div>
</div>

</div><!-- #vol-show-glass-wrapper -->

<!-- JavaScript for Like/Comment Functionality - Using Master Platform Social Media Module -->
<script>
(function() {
    const opportunityId = <?= $opportunityId ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    let isLiked = <?= $isLiked ? 'true' : 'false' ?>;
    let commentsLoaded = false;
    let availableReactions = [];
    const API_BASE = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/social';

    // Unique function name to avoid conflict with social-interactions.js global aliases
    window.volToggleLike = async function() {
        <?php if (!$isLoggedIn): ?>
        window.location.href = '<?= Nexus\Core\TenantContext::getBasePath() ?>/login';
        return;
        <?php endif; ?>

        // Offline protection
        if (!navigator.onLine) {
            alert('You are offline. Please connect to the internet to like this opportunity.');
            return;
        }

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
                    target_type: 'volunteering',
                    target_id: opportunityId
                })
            });

            const data = await response.json();

            if (data.error) {
                if (data.redirect) window.location.href = data.redirect;
                else console.error(data.error);
                return;
            }

            isLiked = (data.status === 'liked');
            countEl.textContent = data.likes_count;

            if (isLiked) {
                btn.classList.add('vol-action-btn--liked');
                icon.className = 'fa-solid fa-heart';
            } else {
                btn.classList.remove('vol-action-btn--liked');
                icon.className = 'fa-regular fa-heart';
            }

        } catch (err) {
            console.error('Like error:', err);
        } finally {
            btn.disabled = false;
        }
    };

    window.volToggleComments = function() {
        // Check if mobile (screen width <= 768px or touch device)
        const isMobile = window.innerWidth <= 768 || ('ontouchstart' in window);

        if (isMobile && typeof openMobileCommentSheet === 'function') {
            // Use mobile drawer on mobile devices
            openMobileCommentSheet('volunteering', opportunityId, '');
            return;
        }

        // Desktop: use inline comments section
        const section = document.getElementById('comments-section');
        const isHidden = !section.classList.contains('vol-comments-section--visible');

        section.classList.toggle('vol-comments-section--visible');

        if (isHidden && !commentsLoaded) {
            loadComments();
        }
    };

    async function loadComments() {
        const list = document.getElementById('comments-list');

        try {
            const response = await fetch(API_BASE + '/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'fetch',
                    target_type: 'volunteering',
                    target_id: opportunityId
                })
            });

            const data = await response.json();

            if (data.error) {
                list.innerHTML = '<p class="vol-loading-text">Failed to load comments</p>';
                return;
            }

            commentsLoaded = true;
            availableReactions = data.available_reactions || [];

            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<p class="vol-loading-text">No comments yet. Be the first to comment!</p>';
                return;
            }

            list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');

        } catch (err) {
            console.error('Load comments error:', err);
            list.innerHTML = '<p class="vol-loading-text">Error loading comments</p>';
        }
    }

    function renderComment(c, depth) {
        const indent = depth * 20;
        const isEdited = c.is_edited ? '<span class="vol-edited-tag"> (edited)</span>' : '';
        const ownerActions = c.is_owner ? `
            <span onclick="volunteeringEditComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'")}')" class="vol-owner-action vol-owner-action--edit" title="Edit">✏️</span>
            <span onclick="volunteeringDeleteComment(${c.id})" class="vol-owner-action vol-owner-action--delete" title="Delete">🗑️</span>
        ` : '';

        const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
            const isUserReaction = (c.user_reactions || []).includes(emoji);
            return `<span onclick="volunteeringToggleReaction(${c.id}, '${emoji}')" class="vol-reaction${isUserReaction ? ' vol-reaction--active' : ''}">${emoji} ${count}</span>`;
        }).join(' ');

        const reactionPicker = isLoggedIn ? `
            <div class="vol-reaction-picker">
                <span onclick="volunteeringShowReactionPicker(${c.id})" class="vol-reaction-add">+</span>
                <div id="picker-${c.id}" class="vol-picker-dropdown">
                    ${availableReactions.map(e => `<span onclick="volunteeringToggleReaction(${c.id}, '${e}')" class="vol-picker-emoji">${e}</span>`).join('')}
                </div>
            </div>
        ` : '';

        const replyButton = isLoggedIn ? `<span onclick="volunteeringShowReplyForm(${c.id})" class="vol-reply-link">Reply</span>` : '';

        const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

        const avatarClass = depth > 0 ? 'vol-avatar vol-avatar--sm' : 'vol-avatar vol-avatar--md';

        return `
            <div class="vol-comment" style="margin-left: ${indent}px;" id="comment-${c.id}">
                <div class="vol-comment-inner">
                    <img src="${c.author_avatar}" loading="lazy" class="${avatarClass}">
                    <div class="vol-comment-body">
                        <div class="vol-comment-author">
                            ${escapeHtml(c.author_name)}${isEdited}
                            ${ownerActions}
                        </div>
                        <div id="content-${c.id}" class="vol-comment-content">${formatContent(c.content)}</div>
                        <div class="vol-comment-meta">
                            ${formatTime(c.created_at)}
                            ${replyButton}
                        </div>
                        <div class="vol-comment-reactions">
                            ${reactions}
                            ${reactionPicker}
                        </div>
                        <div id="reply-form-${c.id}" class="vol-reply-form">
                            <div class="vol-reply-input-wrapper">
                                <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." class="vol-reply-input">
                                <button onclick="volunteeringSubmitReply(${c.id})" class="vol-reply-btn">Reply</button>
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
        return escapeHtml(content).replace(/@(\w+)/g, '<span class="vol-mention">@$1</span>');
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

    window.volunteeringShowReactionPicker = function(commentId) {
        const picker = document.getElementById(`picker-${commentId}`);
        picker.classList.toggle('vol-picker-dropdown--visible');
    };

    window.volunteeringShowReplyForm = function(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        const isVisible = form.classList.contains('vol-reply-form--visible');
        form.classList.toggle('vol-reply-form--visible');
        if (!isVisible) {
            document.getElementById(`reply-input-${commentId}`).focus();
        }
    };

    window.volunteeringToggleReaction = async function(commentId, emoji) {
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

    window.volunteeringSubmitReply = async function(parentId) {
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
                    target_type: 'volunteering',
                    target_id: opportunityId,
                    parent_id: parentId,
                    content: content
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            input.value = '';
            document.getElementById(`reply-form-${parentId}`).classList.remove('vol-reply-form--visible');
            const countEl = document.getElementById('comment-count');
            countEl.textContent = parseInt(countEl.textContent) + 1;
            loadComments();
        } catch (err) { console.error('Reply error:', err); }
    };

    window.volunteeringDeleteComment = async function(commentId) {
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

    window.volunteeringEditComment = function(commentId, currentContent) {
        const contentEl = document.getElementById(`content-${commentId}`);
        const originalHtml = contentEl.innerHTML;

        contentEl.innerHTML = `
            <div class="vol-edit-wrapper">
                <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" class="vol-edit-input">
                <button onclick="saveEdit(${commentId})" class="vol-edit-save">Save</button>
                <button onclick="cancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" class="vol-edit-cancel">Cancel</button>
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

    // Volunteering-specific comment submit (unique name to avoid conflict with social-interactions.js)
    window.volunteeringSubmitComment = async function(e) {
        e.preventDefault();

        // Offline protection
        if (!navigator.onLine) {
            alert('You are offline. Please connect to the internet to post comments.');
            return;
        }

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
                    target_type: 'volunteering',
                    target_id: opportunityId,
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

    window.shareToFeed = async function() {
        // Offline protection
        if (!navigator.onLine) {
            alert('You are offline. Please connect to the internet to share.');
            return;
        }

        if (!confirm('Share this opportunity to your feed?')) return;

        try {
            const response = await fetch(API_BASE + '/share', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    parent_type: 'volunteering',
                    parent_id: opportunityId
                })
            });

            const data = await response.json();

            if (data.error) { alert(data.error); return; }
            if (data.status === 'success') {
                alert('Opportunity shared to your feed!');
            }
        } catch (err) {
            console.error('Share error:', err);
            alert('Failed to share opportunity');
        }
    };

    // ============================================
    // GOLD STANDARD - Native App Features
    // ============================================

    // Heart Burst Animation for Likes
    window.createHeartBurst = function(element) {
        const rect = element.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const hearts = ['❤️', '💚', '💙', '🧡', '💗'];

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

    // Enhanced toggleLike with animation
    const originalVolToggleLike = window.volToggleLike;
    window.volToggleLike = async function() {
        const btn = document.getElementById('like-btn');
        const wasLiked = btn.style.background.includes('linear-gradient');

        await originalVolToggleLike();

        // Check if we just liked it
        const nowLiked = btn.style.background.includes('linear-gradient');
        if (!wasLiked && nowLiked) {
            btn.classList.add('like-pop');
            setTimeout(() => btn.classList.remove('like-pop'), 300);
            createHeartBurst(btn);
        }
    };

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

    // Button Press States
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#vol-show-glass-wrapper button, #vol-show-glass-wrapper .btn--primary').forEach(btn => {
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
    });

    // Dynamic Theme Color
    (function initDynamicThemeColor() {
        const metaTheme = document.querySelector('meta[name="theme-color"]');
        if (!metaTheme) {
            const meta = document.createElement('meta');
            meta.name = 'theme-color';
            meta.content = '#14b8a6';
            document.head.appendChild(meta);
        }

        function updateThemeColor() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) {
                meta.setAttribute('content', isDark ? '#0f172a' : '#14b8a6');
            }
        }

        const observer = new MutationObserver(updateThemeColor);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });

        updateThemeColor();
    })();
})();
</script>

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
