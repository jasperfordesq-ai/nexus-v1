<?php
// Goal Detail View - High-End Adaptive Holographic Glassmorphism Edition
// ISOLATED LAYOUT: Uses #unique-glass-page-wrapper and html[data-theme] selectors.

$isAuthor = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $goal['user_id'];
$hasMentor = !empty($goal['mentor_id']);
$isMentor = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $goal['mentor_id'];

require __DIR__ . '/../../layouts/header.php';
?>
<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-goals-show.min.css?v=<?= time() ?>">

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div id="unique-glass-page-wrapper">
    <div class="glass-box">

        <!-- Header -->
        <div class="page-header">
            <div>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals" class="back-link">
                    <span>←</span> &nbsp; Back to Goals
                </a>
                <br>
                <div class="status-badge">
                    <?= $goal['status'] === 'completed' ? '✅ COMPLETED' : '🎯 GOAL' ?>
                </div>
                <h1><?= htmlspecialchars($goal['title']) ?></h1>
            </div>

            <?php if ($isAuthor): ?>
                <div class="goal-header-actions">
                    <?php if ($goal['status'] === 'active'): ?>
                        <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>/complete" method="POST" onsubmit="return confirm('Mark as achieved? Great job!')">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <button type="submit" class="glass-pill-btn btn-success">✅ Mark Complete</button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>/edit" class="glass-pill-btn btn-secondary">⚙️ Edit</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Description -->
        <div class="goal-description"><?= htmlspecialchars($goal['description']) ?></div>

        <!-- Buddy Status Section -->
        <?php if ($hasMentor): ?>
            <!-- Matched State -->
            <div class="status-card status-matched">
                <h3 class="goal-buddy-heading">🤝 Matched with Buddy</h3>
                <div class="goal-buddy-info">
                    <div class="goal-emoji-box">🎉</div>
                    <div>
                        <div class="goal-buddy-label">Accountability Partner</div>
                        <div class="goal-buddy-name"><?= htmlspecialchars($goal['mentor_name']) ?></div>
                    </div>
                    <?php if ($isAuthor || $isMentor): ?>
                        <div class="goal-buddy-action">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages?create=1&to=<?= $isAuthor ? $goal['mentor_id'] : $goal['user_id'] ?>" class="glass-pill-btn btn-primary goal-buddy-message-btn">Message</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($goal['is_public']): ?>
            <!-- Looking State -->
            <div class="status-card status-looking">
                <div class="goal-looking-center">
                    <div class="goal-looking-emoji">🔍</div>
                    <h3 class="goal-looking-heading">Looking for a Buddy</h3>
                    <p class="goal-looking-description">This goal is public! Waiting for a community member to support you.</p>

                    <?php if (!$isAuthor && isset($_SESSION['user_id'])): ?>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals/buddy" method="POST" onsubmit="return confirm('Are you sure you want to be the accountability partner for this goal?');">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
                            <button class="glass-pill-btn btn-primary">Become Buddy 🤝</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Private State -->
            <div class="status-card status-private">
                <div class="goal-private-info">
                    <div class="goal-private-emoji">🔒</div>
                    <div>
                        <strong class="goal-private-label">Private Goal</strong>
                        <span class="goal-private-description">Only you can see this goal.</span>
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <!-- Social Engagement Section - Master Platform Social Media Module -->
        <?php
        $goalId = $goal['id'];
        $likesCount = $likesCount ?? 0;
        $commentsCount = $commentsCount ?? 0;
        $isLiked = $isLiked ?? false;
        $isLoggedIn = $isLoggedIn ?? !empty($_SESSION['user_id']);
        ?>
        <div class="goal-social-section">
            <!-- Like & Comment Buttons -->
            <div class="goal-social-buttons">
                <button id="like-btn" onclick="goalToggleLike()" class="glass-pill-btn <?= $isLiked ? 'btn-primary' : 'btn-secondary' ?>">
                    <i class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart" id="like-icon"></i>
                    <span id="like-count"><?= $likesCount ?></span>
                    <span><?= $likesCount === 1 ? 'Like' : 'Likes' ?></span>
                </button>
                <button onclick="goalToggleComments()" class="glass-pill-btn btn-secondary">
                    <i class="fa-regular fa-comment"></i>
                    <span id="comment-count"><?= $commentsCount ?></span>
                    <span><?= $commentsCount === 1 ? 'Comment' : 'Comments' ?></span>
                </button>
                <?php if ($isLoggedIn): ?>
                <button onclick="shareToFeed()" class="glass-pill-btn btn-secondary">
                    <i class="fa-solid fa-share"></i> Share
                </button>
                <?php endif; ?>
            </div>

            <!-- Comments Section (Hidden by Default) -->
            <div id="comments-section" class="goal-comments-section">
                <?php if ($isLoggedIn): ?>
                <form onsubmit="goalSubmitComment(event)" class="goal-comment-form">
                    <div class="goal-comment-input-wrapper">
                        <img src="<?= $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp' ?>" loading="lazy" class="goal-comment-avatar">
                        <div class="goal-comment-input-container">
                            <textarea id="comment-input" placeholder="Write a comment..." class="goal-comment-textarea"></textarea>
                            <button type="submit" class="glass-pill-btn btn-primary goal-comment-submit-btn">Post Comment</button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <p class="goal-login-prompt">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="goal-login-link">Log in</a> to leave a comment.
                </p>
                <?php endif; ?>
                <div id="comments-list">
                    <p class="goal-comments-loading">Loading comments...</p>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
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

// Button Press States - handled by CSS :active state in civicone-goals-show.css

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#db2777';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#db2777');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();

// ============================================
// MASTER PLATFORM SOCIAL MEDIA MODULE
// ============================================
(function() {
    const goalId = <?= $goalId ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    let isLiked = <?= $isLiked ? 'true' : 'false' ?>;
    let commentsLoaded = false;
    let availableReactions = [];

    const API_BASE = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/social';

    // Unique function names to avoid conflict with social-interactions.js
    window.goalToggleLike = async function() {
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
                    target_type: 'goal',
                    target_id: goalId
                })
            });

            if (!response.ok) {
                alert('Like failed. Please try again.');
                return;
            }

            const data = await response.json();

            if (data.error) {
                if (data.redirect) window.location.href = data.redirect;
                else alert('Like failed: ' + data.error);
                return;
            }

            isLiked = (data.status === 'liked');
            countEl.textContent = data.likes_count;

            if (isLiked) {
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-primary');
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-secondary');
                icon.classList.remove('fa-solid');
                icon.classList.add('fa-regular');
            }

        } catch (err) {
            console.error('Like error:', err);
        } finally {
            btn.disabled = false;
        }
    };

    window.goalToggleComments = function() {
        // Check if mobile (screen width <= 768px or touch device)
        const isMobile = window.innerWidth <= 768 || ('ontouchstart' in window);

        if (isMobile && typeof openMobileCommentSheet === 'function') {
            // Use mobile drawer on mobile devices
            openMobileCommentSheet('goal', goalId, '');
            return;
        }

        // Desktop: use inline comments section
        const section = document.getElementById('comments-section');
        const isHidden = !section.classList.contains('visible');

        section.classList.toggle('visible');

        if (isHidden && !commentsLoaded) {
            loadComments();
        }
    };

    async function loadComments() {
        const list = document.getElementById('comments-list');
        list.innerHTML = '<p class="goal-comments-message">Loading comments...</p>';

        try {
            const response = await fetch(API_BASE + '/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'fetch',
                    target_type: 'goal',
                    target_id: goalId
                })
            });

            if (!response.ok) {
                list.innerHTML = '<p class="goal-comments-message">Failed to load comments.</p>';
                return;
            }

            const data = await response.json();

            if (data.error) {
                list.innerHTML = '<p class="goal-comments-message">Failed to load comments.</p>';
                return;
            }

            commentsLoaded = true;
            availableReactions = data.available_reactions || [];

            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<p class="goal-comments-message">No comments yet. Be the first to comment!</p>';
                return;
            }

            list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');

        } catch (err) {
            console.error('Load comments error:', err);
            list.innerHTML = '<p class="goal-comments-message">Failed to load comments.</p>';
        }
    }

    function renderComment(c, depth) {
        const indentStyle = depth > 0 ? `margin-left: ${depth * 20}px;` : '';
        const isEdited = c.is_edited ? '<span class="goal-comment-edited"> (edited)</span>' : '';
        const ownerActions = c.is_owner ? `
            <span class="goal-comment-actions" onclick="goalEditComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'")}')" title="Edit">✏️</span>
            <span class="goal-comment-actions" onclick="goalDeleteComment(${c.id})" title="Delete">🗑️</span>
        ` : '';

        const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
            const isUserReaction = (c.user_reactions || []).includes(emoji);
            const activeClass = isUserReaction ? ' active' : '';
            return `<span class="goal-reaction-pill${activeClass}" onclick="goalToggleReaction(${c.id}, '${emoji}')">${emoji} ${count}</span>`;
        }).join(' ');

        const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

        return `
            <div class="goal-comment-box" style="${indentStyle}">
                <div class="goal-comment-header">
                    <img src="${c.avatar || '/assets/img/defaults/default_avatar.webp'}" class="goal-comment-box-avatar" loading="lazy" alt="">
                    <div>
                        <strong class="goal-comment-author">${escapeHtml(c.author_name)}</strong>${isEdited}
                        <div class="goal-comment-time">${c.time_ago}</div>
                    </div>
                    ${ownerActions}
                </div>
                <div id="content-${c.id}" class="goal-comment-content">${escapeHtml(c.content)}</div>
                <div class="goal-comment-reactions">
                    ${reactions}
                    <span class="goal-reply-btn" onclick="goalShowReplyForm(${c.id})">↩️ Reply</span>
                </div>
                <div id="reply-form-${c.id}" class="goal-reply-form">
                    <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." class="goal-reply-input">
                    <button onclick="goalSubmitReply(${c.id})" class="glass-pill-btn btn-primary goal-reply-submit">Reply</button>
                </div>
                ${replies}
            </div>
        `;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    window.goalSubmitComment = async function(e) {
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
                    target_type: 'goal',
                    target_id: goalId,
                    content: content
                })
            });

            const data = await response.json();

            if (data.error) { alert(data.error); return; }

            input.value = '';
            const countEl = document.getElementById('comment-count');
            countEl.textContent = parseInt(countEl.textContent) + 1;
            loadComments();
        } catch (err) {
            console.error('Submit comment error:', err);
            alert('Failed to post comment');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Post Comment';
        }
    };

    window.goalShowReplyForm = function(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        const wasHidden = !form.classList.contains('visible');
        form.classList.toggle('visible');
        if (wasHidden) {
            document.getElementById(`reply-input-${commentId}`).focus();
        }
    };

    window.goalSubmitReply = async function(parentId) {
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
                    target_type: 'goal',
                    target_id: goalId,
                    parent_id: parentId,
                    content: content
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            input.value = '';
            document.getElementById(`reply-form-${parentId}`).classList.remove('visible');
            const countEl = document.getElementById('comment-count');
            countEl.textContent = parseInt(countEl.textContent) + 1;
            loadComments();
        } catch (err) { console.error('Reply error:', err); }
    };

    window.goalToggleReaction = async function(commentId, emoji) {
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

    window.goalDeleteComment = async function(commentId) {
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

    window.goalEditComment = function(commentId, currentContent) {
        const contentEl = document.getElementById(`content-${commentId}`);
        const originalHtml = contentEl.innerHTML;

        contentEl.innerHTML = `
            <div class="goal-edit-form">
                <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" class="goal-edit-input">
                <button onclick="goalSaveEdit(${commentId})" class="glass-pill-btn btn-primary goal-edit-btn">Save</button>
                <button onclick="goalCancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" class="glass-pill-btn btn-secondary goal-edit-btn">Cancel</button>
            </div>
        `;
        document.getElementById(`edit-input-${commentId}`).focus();
    };

    window.goalCancelEdit = function(commentId, originalHtml) {
        document.getElementById(`content-${commentId}`).innerHTML = originalHtml;
    };

    window.goalSaveEdit = async function(commentId) {
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

    window.shareToFeed = async function() {
        if (!confirm('Share this goal to your feed?')) return;

        try {
            const response = await fetch(API_BASE + '/share', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    parent_type: 'goal',
                    parent_id: goalId
                })
            });

            const data = await response.json();

            if (data.error) { alert(data.error); return; }
            if (data.status === 'success') {
                alert('Goal shared to your feed!');
            }
        } catch (err) {
            console.error('Share error:', err);
            alert('Failed to share');
        }
    };
})();
</script>

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>