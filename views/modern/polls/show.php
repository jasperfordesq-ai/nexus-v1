<?php
// Phoenix View: Poll Detail - Holographic Glassmorphism 2025
// ISOLATED LAYOUT: Uses #poll-holo-wrapper and html[data-theme] selectors.

// Fetch Like/Comment Counts for Display
$pollId = $poll['id'];
$userId = $_SESSION['user_id'] ?? 0;
$likesCount = 0;
$commentsCount = 0;
$isLiked = false;

try {
    $pdo = \Nexus\Core\Database::getInstance();

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = 'poll' AND target_id = ?");
    $stmt->execute([$pollId]);
    $likesResult = $stmt->fetch();
    $likesCount = (int)($likesResult['cnt'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM comments WHERE target_type = 'poll' AND target_id = ?");
    $stmt->execute([$pollId]);
    $commentsResult = $stmt->fetch();
    $commentsCount = (int)($commentsResult['cnt'] ?? 0);

    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = 'poll' AND target_id = ?");
        $stmt->execute([$userId, $pollId]);
        $likedResult = $stmt->fetch();
        $isLiked = !empty($likedResult);
    }
} catch (\Throwable $e) {
    error_log("Poll stats error: " . $e->getMessage());
}

$pageTitle = $poll['question'];
$hideHero = true;
$basePath = \Nexus\Core\TenantContext::getBasePath();

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>


<div id="poll-holo-wrapper">
    <!-- Holographic Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="poll-inner">

        <!-- Poll Header -->
        <header class="poll-header-section">
            <a href="<?= $basePath ?>/polls" class="poll-back-link">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Polls
            </a>

            <div class="poll-badge">
                <i class="fa-solid fa-chart-bar"></i>
                Community Poll
            </div>

            <h1 class="poll-title"><?= htmlspecialchars($poll['question']) ?></h1>

            <?php if (!empty($poll['description'])): ?>
                <p class="poll-description"><?= nl2br(htmlspecialchars($poll['description'])) ?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id']) && ($poll['user_id'] == $_SESSION['user_id'] || !empty($_SESSION['is_super_admin']))): ?>
                <a href="<?= $basePath ?>/polls/<?= $poll['id'] ?>/edit" class="poll-manage-btn">
                    <i class="fa-solid fa-gear"></i>
                    Manage Poll
                </a>
            <?php endif; ?>
        </header>

        <!-- Poll Content Card -->
        <div class="poll-content-card">
            <?php if ($hasVoted): ?>
                <!-- Results View -->
                <div class="poll-results">
                    <h3 class="poll-results-header">
                        Thank you for voting!
                        <i class="fa-solid fa-chart-pie"></i>
                    </h3>

                    <?php foreach ($options as $opt): ?>
                        <?php $percent = $totalVotes > 0 ? round(($opt['vote_count'] / $totalVotes) * 100) : 0; ?>
                        <div class="result-item">
                            <div class="result-header">
                                <span class="result-label"><?= htmlspecialchars($opt['label']) ?></span>
                                <span class="result-stats"><?= $percent ?>% (<?= $opt['vote_count'] ?>)</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: <?= $percent ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="poll-total-votes">
                        Total Votes: <strong><?= $totalVotes ?></strong>
                    </div>
                </div>

            <?php else: ?>
                <!-- Voting Form -->
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="poll-login-prompt">
                        <p>Join the community to cast your vote.</p>
                        <a href="<?= $basePath ?>/login" class="poll-btn">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            Login to Vote
                        </a>
                    </div>
                <?php else: ?>
                    <form action="<?= $basePath ?>/polls/vote" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">

                        <div class="poll-options">
                            <?php foreach ($options as $opt): ?>
                                <label class="poll-option-label">
                                    <input type="radio" name="option_id" value="<?= $opt['id'] ?>" required>
                                    <span class="option-text"><?= htmlspecialchars($opt['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="poll-btn poll-btn-block">
                            <i class="fa-solid fa-check-to-slot"></i>
                            Submit Vote
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Social Section -->
        <div class="poll-social-section">
            <div class="social-actions">
                <button id="likeBtn" onclick="pollToggleLike()" class="social-btn <?= $isLiked ? 'liked' : '' ?>">
                    <span id="likeIcon"><?= $isLiked ? '❤️' : '🤍' ?></span>
                    <span id="likesCount"><?= $likesCount ?></span> Likes
                </button>

                <button onclick="pollToggleComments()" class="social-btn">
                    <i class="fa-regular fa-comment"></i>
                    <span id="commentsCount"><?= $commentsCount ?></span> Comments
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <button onclick="shareToFeed()" class="share-btn share-feed" title="Share to Feed">
                        <i class="fa-solid fa-share-nodes"></i>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Comments Section -->
            <div id="commentsSection" class="comments-wrapper">
                <div id="commentsList" class="comments-list">
                    <div class="comments-empty">
                        <i class="fa-regular fa-comments"></i>
                        <p>Loading comments...</p>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <form id="commentForm" class="comment-form" onsubmit="pollSubmitComment(event)">
                        <input type="text" id="commentInput" class="comment-input" placeholder="Write a comment..." required>
                        <button type="submit" class="comment-submit-btn">Post</button>
                    </form>
                <?php else: ?>
                    <p class="comment-login-prompt">
                        <a href="<?= $basePath ?>/login">Login</a> to comment
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
const pollId = <?= $pollId ?>;
const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
let isLiked = <?= $isLiked ? 'true' : 'false' ?>;
let commentsLoaded = false;
let availableReactions = [];
const API_BASE = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/social';

// Toggle Like
async function pollToggleLike() {
    const btn = document.getElementById('likeBtn');
    const icon = document.getElementById('likeIcon');
    const countEl = document.getElementById('likesCount');

    try {
        const response = await fetch(API_BASE + '/like', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target_type: 'poll',
                target_id: pollId
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
        icon.textContent = isLiked ? '❤️' : '🤍';
        btn.classList.toggle('liked', isLiked);

        // Haptic feedback
        if (navigator.vibrate) navigator.vibrate(50);
    } catch (err) {
        console.error('Like error:', err);
    }
}

// Toggle Comments
function pollToggleComments() {
    // Check if mobile (screen width <= 768px or touch device)
    const isMobile = window.innerWidth <= 768 || ('ontouchstart' in window);

    if (isMobile && typeof openMobileCommentSheet === 'function') {
        // Use mobile drawer on mobile devices
        openMobileCommentSheet('poll', pollId, '');
        return;
    }

    // Desktop: use inline comments section
    const section = document.getElementById('commentsSection');
    section.classList.toggle('visible');

    if (section.classList.contains('visible') && !commentsLoaded) {
        loadComments();
    }
}

// Load Comments
async function loadComments() {
    const list = document.getElementById('commentsList');

    try {
        const response = await fetch(API_BASE + '/comments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'fetch',
                target_type: 'poll',
                target_id: pollId
            })
        });

        const data = await response.json();

        if (data.error) {
            list.innerHTML = '<div class="comments-empty"><p>Failed to load comments</p></div>';
            return;
        }

        commentsLoaded = true;
        availableReactions = data.available_reactions || [];

        if (!data.comments || data.comments.length === 0) {
            list.innerHTML = '<div class="comments-empty"><i class="fa-regular fa-comments"></i><p>No comments yet. Be the first to comment!</p></div>';
            return;
        }

        list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');
    } catch (err) {
        console.error('Comments error:', err);
        list.innerHTML = '<div class="comments-empty"><p>Failed to load comments</p></div>';
    }
}

// Render Comment
function renderComment(c, depth) {
    const isEdited = c.is_edited ? '<span style="font-size: 0.75rem; opacity: 0.6;"> (edited)</span>' : '';
    const ownerActions = c.is_owner ? `
        <span class="comment-author-actions">
            <span class="comment-action-btn" onclick="pollEditComment(${c.id}, '${escapeHtml(c.content)}')" title="Edit">✏️</span>
            <span class="comment-action-btn" onclick="pollDeleteComment(${c.id})" title="Delete">🗑️</span>
        </span>
    ` : '';

    const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
        const isUserReaction = (c.user_reactions || []).includes(emoji);
        return `<span class="reaction-badge ${isUserReaction ? 'active' : ''}" onclick="pollToggleReaction(${c.id}, '${emoji}')">${emoji} ${count}</span>`;
    }).join('');

    const reactionPicker = isLoggedIn ? `
        <div style="position: relative; display: inline-block;">
            <span class="reaction-picker-toggle" onclick="pollShowReactionPicker(${c.id})">+</span>
            <div id="picker-${c.id}" class="reaction-picker-dropdown">
                ${availableReactions.map(e => `<span onclick="pollToggleReaction(${c.id}, '${e}')">${e}</span>`).join('')}
            </div>
        </div>
    ` : '';

    const replyButton = isLoggedIn ? `<span class="comment-reply-btn" onclick="pollShowReplyForm(${c.id})">Reply</span>` : '';

    const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

    const avatarClass = depth > 0 ? 'comment-avatar comment-avatar-small' : 'comment-avatar';

    return `
        <div class="comment-item" id="comment-${c.id}">
            <div class="comment-header">
                <img src="${c.author_avatar}" class="${avatarClass}" alt="" loading="lazy">
                <div class="comment-body">
                    <div class="comment-author">
                        ${escapeHtml(c.author_name)}${isEdited}${ownerActions}
                    </div>
                    <div class="comment-content" id="content-${c.id}">${formatContent(c.content)}</div>
                    <div class="comment-meta">
                        <span>${new Date(c.created_at).toLocaleString()}</span>
                        ${replyButton}
                    </div>
                    <div class="comment-reactions">
                        ${reactions}
                        ${reactionPicker}
                    </div>
                    <div id="reply-form-${c.id}" class="reply-form">
                        <input type="text" id="reply-input-${c.id}" class="comment-input" placeholder="Write a reply..." style="flex: 1;">
                        <button onclick="pollSubmitReply(${c.id})" class="comment-submit-btn">Reply</button>
                    </div>
                </div>
            </div>
            ${replies ? `<div class="comment-replies">${replies}</div>` : ''}
        </div>
    `;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatContent(content) {
    return escapeHtml(content).replace(/@(\w+)/g, '<span style="color: var(--poll-secondary); font-weight: 600;">@$1</span>');
}

function pollShowReactionPicker(commentId) {
    // Close all other pickers first
    document.querySelectorAll('.reaction-picker-dropdown.visible').forEach(p => p.classList.remove('visible'));
    const picker = document.getElementById(`picker-${commentId}`);
    picker.classList.toggle('visible');
}

function pollShowReplyForm(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    form.classList.toggle('visible');
    if (form.classList.contains('visible')) {
        document.getElementById(`reply-input-${commentId}`).focus();
    }
}

async function pollToggleReaction(commentId, emoji) {
    if (!isLoggedIn) { alert('Please log in to react'); return; }

    // Close picker
    document.querySelectorAll('.reaction-picker-dropdown.visible').forEach(p => p.classList.remove('visible'));

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
    } catch (err) {
        console.error('Reaction error:', err);
    }
}

async function pollSubmitReply(parentId) {
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
                target_type: 'poll',
                target_id: pollId,
                parent_id: parentId,
                content: content
            })
        });
        const data = await response.json();
        if (data.error) { alert(data.error); return; }
        input.value = '';
        document.getElementById(`reply-form-${parentId}`).classList.remove('visible');
        document.getElementById('commentsCount').textContent = parseInt(document.getElementById('commentsCount').textContent) + 1;
        loadComments();
    } catch (err) {
        console.error('Reply error:', err);
    }
}

async function pollDeleteComment(commentId) {
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
        document.getElementById('commentsCount').textContent = Math.max(0, parseInt(document.getElementById('commentsCount').textContent) - 1);
        loadComments();
    } catch (err) {
        console.error('Delete error:', err);
    }
}

function pollEditComment(commentId, currentContent) {
    const contentEl = document.getElementById(`content-${commentId}`);
    const originalHtml = contentEl.innerHTML;

    contentEl.innerHTML = `
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" class="comment-input" style="flex: 1; min-width: 200px;">
            <button onclick="saveEdit(${commentId})" class="comment-submit-btn">Save</button>
            <button onclick="cancelEdit(${commentId})" style="padding: 10px 16px; border-radius: 10px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: inherit; cursor: pointer;">Cancel</button>
        </div>
    `;
    document.getElementById(`edit-input-${commentId}`).focus();

    window.cancelEdit = function(id) {
        document.getElementById(`content-${id}`).innerHTML = originalHtml;
    };
}

async function saveEdit(commentId) {
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
    } catch (err) {
        console.error('Edit error:', err);
    }
}

async function pollSubmitComment(e) {
    e.preventDefault();
    const input = document.getElementById('commentInput');
    const content = input.value.trim();
    if (!content) return;

    try {
        const response = await fetch(API_BASE + '/comments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'submit',
                target_type: 'poll',
                target_id: pollId,
                content: content
            })
        });

        const data = await response.json();

        if (data.error) {
            alert(data.error);
            return;
        }

        input.value = '';
        document.getElementById('commentsCount').textContent = parseInt(document.getElementById('commentsCount').textContent) + 1;
        commentsLoaded = false;
        loadComments();
    } catch (err) {
        console.error('Comment submit error:', err);
    }
}

async function shareToFeed() {
    if (!confirm('Share this poll to your feed?')) return;

    try {
        const response = await fetch(API_BASE + '/share', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                parent_type: 'poll',
                parent_id: pollId
            })
        });

        const data = await response.json();

        if (data.error) {
            alert(data.error);
            return;
        }

        if (data.status === 'success') {
            alert('Poll shared to your feed!');
        }
    } catch (err) {
        console.error('Share error:', err);
        alert('Failed to share poll');
    }
}

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
document.querySelectorAll('#poll-holo-wrapper .poll-btn, #poll-holo-wrapper .social-btn, #poll-holo-wrapper .share-btn').forEach(btn => {
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
        meta.content = '#302b63';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#302b63');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();

// Parallax effect on orbs
(function initParallaxOrbs() {
    const orbs = document.querySelectorAll('.holo-orb');
    if (orbs.length === 0) return;

    let ticking = false;

    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                const scrollY = window.scrollY;
                orbs.forEach((orb, index) => {
                    const speed = 0.03 * (index + 1);
                    orb.style.transform = `translateY(${scrollY * speed}px)`;
                });
                ticking = false;
            });
            ticking = true;
        }
    });
})();

// Close reaction pickers when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.reaction-picker-toggle') && !e.target.closest('.reaction-picker-dropdown')) {
        document.querySelectorAll('.reaction-picker-dropdown.visible').forEach(p => p.classList.remove('visible'));
    }
});
</script>

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
