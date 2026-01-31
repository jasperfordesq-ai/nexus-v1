<?php
// Event Detail View - High-End Adaptive Holographic Glassmorphism Edition
// ISOLATED LAYOUT: Uses #unique-glass-page-wrapper and html[data-theme] selectors.

// ---------------------------------------------------------
// Fetch Like/Comment Counts for Display
// ---------------------------------------------------------
$eventId = $event['id'];
$userId = $_SESSION['user_id'] ?? 0;
$likesCount = 0;
$commentsCount = 0;
$isLiked = false;

try {
    $pdo = \Nexus\Core\Database::getInstance();

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = 'event' AND target_id = ?");
    $stmt->execute([$eventId]);
    $likesResult = $stmt->fetch();
    $likesCount = (int)($likesResult['cnt'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM comments WHERE target_type = 'event' AND target_id = ?");
    $stmt->execute([$eventId]);
    $commentsResult = $stmt->fetch();
    $commentsCount = (int)($commentsResult['cnt'] ?? 0);

    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = 'event' AND target_id = ?");
        $stmt->execute([$userId, $eventId]);
        $likedResult = $stmt->fetch();
        $isLiked = !empty($likedResult);
    }
} catch (\Throwable $e) {
    error_log("Event stats error: " . $e->getMessage());
}

require __DIR__ . '/../../layouts/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div id="unique-glass-page-wrapper">
    <div class="glass-box">

        <div class="event-layout">

            <!-- Main Column -->
            <div class="main-content">
                <h1><?= htmlspecialchars($event['title']) ?></h1>
                <div class="host-byline">
                    Hosted by <strong class="event-host-name"><?= htmlspecialchars($event['organizer_name']) ?></strong>
                </div>

                <!-- Date Badge -->
                <div class="glass-date-badge">
                    <div class="date-block">
                        <div class="date-month"><?= date('M', strtotime($event['start_time'])) ?></div>
                        <div class="date-day"><?= date('d', strtotime($event['start_time'])) ?></div>
                    </div>
                    <div class="time-loc">
                        <div class="time-text"><?= date('l, F jS @ g:i A', strtotime($event['start_time'])) ?></div>
                        <div class="loc-text">📍 <?= htmlspecialchars($event['location']) ?></div>
                    </div>
                </div>

                <!-- SDGs -->
                <?php if (!empty($event['sdg_goals'])): ?>
                    <?php
                    $goals = json_decode($event['sdg_goals'], true);
                    if (is_array($goals) && count($goals) > 0):
                        require_once __DIR__ . '/../../../src/Helpers/SDG.php';
                    ?>
                        <div class="sdg-container">
                            <?php foreach ($goals as $gid):
                                $goal = \Nexus\Helpers\SDG::get($gid);
                                if (!$goal) continue;
                            ?>
                                <span class="glass-sdg-pill" style="
                                    background: <?= $goal['color'] ?>20; 
                                    color: <?= $goal['color'] ?>; 
                                    border: 1px solid <?= $goal['color'] ?>50;">
                                    <?= $goal['icon'] ?> <?= $goal['label'] ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="description">
                    <?= nl2br(htmlspecialchars($event['description'])) ?>
                </div>

                <!-- Like & Comment Section -->
                <div class="sidebar-card event-social-card">
                    <div class="event-social-actions">
                        <button id="likeBtn" onclick="eventToggleLike()" class="event-action-btn<?= $isLiked ? ' liked' : '' ?>">
                            <span id="likeIcon"><?= $isLiked ? '❤️' : '🤍' ?></span>
                            <span id="likesCount"><?= $likesCount ?></span> Likes
                        </button>
                        <button onclick="eventToggleComments()" class="event-action-btn">
                            💬 <span id="commentsCount"><?= $commentsCount ?></span> Comments
                        </button>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <button onclick="shareToFeed()" class="event-action-btn">
                            🔗 Share
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Comments Section (Hidden by default) -->
                    <div id="commentsSection" class="event-comments-section">
                        <div id="commentsList" class="event-comments-list">
                            <p class="event-comments-loading">Loading comments...</p>
                        </div>

                        <?php if (isset($_SESSION['user_id'])): ?>
                        <form id="commentForm" onsubmit="eventSubmitComment(event)" class="event-comment-form">
                            <input type="text" id="commentInput" placeholder="Write a comment..." required class="event-comment-input">
                            <button type="submit" class="btn btn--primary event-comment-submit">Post</button>
                        </form>
                        <?php else: ?>
                        <p class="event-login-prompt"><a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" class="event-login-link">Login</a> to comment</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Sidebar Column -->
            <div class="sidebar">

                <!-- Invite Banner -->
                <?php if ($myStatus === 'invited'): ?>
                    <div class="sidebar-card event-invite-banner">
                        <h4 class="sidebar-title event-invite-title">📩 You're Invited!</h4>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/rsvp" method="POST" class="rsvp-actions">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                            <button name="status" value="going" class="btn btn--primary">Accept</button>
                            <button name="status" value="declined" class="btn btn--ghost">Decline</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- RSVP Status -->
                <div class="sidebar-card">
                    <h4 class="sidebar-title">Your RSVP</h4>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" class="btn btn--primary event-login-rsvp-btn">Login to RSVP</a>
                    <?php else: ?>
                        <?php if ($myStatus == 'going'): ?>
                            <div class="event-going-status">
                                ✅ You are going!
                            </div>
                        <?php endif; ?>

                        <?php if ($myStatus !== 'invited'): ?>
                            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/rsvp" method="POST" class="rsvp-actions">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                <button name="status" value="going" class="btn <?= $myStatus == 'going' ? 'btn--primary' : 'btn--ghost' ?>">Yes</button>
                                <button name="status" value="declined" class="btn <?= $myStatus == 'declined' ? 'btn--danger' : 'btn--ghost' ?>">No</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Attendees -->
                <div class="sidebar-card">
                    <h4 class="sidebar-title event-attendees-title">
                        Attendees <span class="event-attendees-count"><?= $count ?></span>
                    </h4>
                    <?php if (empty($attendees)): ?>
                        <p class="event-no-attendees">Be the first!</p>
                    <?php else: ?>
                        <div class="event-attendees-scroll">
                            <?php foreach ($attendees as $att): ?>
                                <div class="attendee-item">
                                    <?= webp_avatar($att['avatar_url'] ?? null, $att['name'], 40) ?>
                                    <div class="event-attendee-info">
                                        <div class="event-attendee-name"><?= htmlspecialchars($att['name']) ?></div>
                                        <?php if ($att['status'] == 'attended'): ?>
                                            <div class="event-attended-badge">✅ Attended</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($canInvite && $att['status'] !== 'attended'): ?>
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/check-in" method="POST" onsubmit="return confirm('Confirm?');">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $att['user_id'] ?>">
                                            <button type="submit" class="btn btn--primary event-checkin-btn">Check In</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Admin Management -->
                <?php if (isset($_SESSION['user_id']) && ($event['user_id'] == $_SESSION['user_id'] || !empty($_SESSION['is_super_admin']))): ?>
                    <div class="sidebar-card">
                        <h4 class="sidebar-title">Manage</h4>
                        <div class="event-manage-actions">
                            <?php if (!empty($canInvite)): ?>
                                <button onclick="document.getElementById('inviteModal').classList.add('visible')" class="btn btn--ghost">📩 Invite People</button>
                            <?php endif; ?>

                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>/edit" class="btn btn--ghost event-edit-link">⚙️ Edit Event</a>

                            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>/delete" method="POST" onsubmit="return confirm('Cancel event?');">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <button type="submit" class="btn btn--danger event-cancel-btn">Cancel Event</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Glass Overlay Modal for Invites -->
        <?php if (!empty($canInvite)): ?>
            <div id="inviteModal" class="glass-modal-overlay">
                <div class="glass-modal-content">
                    <h3 class="event-invite-modal-title">Invite Members</h3>

                    <?php if (!empty($potentialInvitees)): ?>
                        <input type="text" id="inviteSearch" placeholder="🔍 Search members..." class="event-invite-search">

                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/invite" method="POST">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">

                            <div id="inviteList" class="event-invite-list">
                                <?php foreach ($potentialInvitees as $pm): ?>
                                    <label class="invite-item event-invite-item">
                                        <input type="checkbox" name="user_ids[]" value="<?= $pm['id'] ?>" class="event-invite-checkbox">
                                        <?= webp_avatar($pm['avatar_url'] ?? null, $pm['name'], 30) ?>
                                        <span class="invite-name"><?= htmlspecialchars($pm['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="event-modal-buttons">
                                <button type="submit" class="btn btn--primary event-modal-btn-flex">Send Invites</button>
                                <button type="button" onclick="document.getElementById('inviteModal').classList.remove('visible')" class="btn btn--ghost event-modal-btn-flex">Cancel</button>
                            </div>
                        </form>

                        <script>
                            // Search Filter Logic
                            document.getElementById('inviteSearch').addEventListener('keyup', function(e) {
                                const term = e.target.value.toLowerCase();
                                const items = document.querySelectorAll('.invite-item');
                                items.forEach(item => {
                                    const name = item.querySelector('.invite-name').innerText.toLowerCase();
                                    if (name.includes(term)) {
                                        item.classList.remove('hidden');
                                    } else {
                                        item.classList.add('hidden');
                                    }
                                });
                            });
                        </script>

                    <?php else: ?>
                        <div class="event-no-invitees">
                            <p>No eligible members found to invite.</p>
                            <button type="button" onclick="document.getElementById('inviteModal').classList.remove('visible')" class="btn btn--ghost event-close-btn-margin">Close</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div> <!-- Closing #unique-glass-page-wrapper -->

<!-- Using Master Platform Social Media Module -->
<script>
const eventId = <?= $eventId ?>;
const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
let isLiked = <?= $isLiked ? 'true' : 'false' ?>;
let commentsLoaded = false;
let availableReactions = [];
const API_BASE = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/social';

// Unique function names to avoid conflict with social-interactions.js global aliases
async function eventToggleLike() {
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
                target_type: 'event',
                target_id: eventId
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
        if (isLiked) {
            btn.classList.add('liked');
        } else {
            btn.classList.remove('liked');
        }
    } catch (err) {
        console.error('Like error:', err);
    }
}

function eventToggleComments() {
    // Check if mobile (screen width <= 768px or touch device)
    const isMobile = window.innerWidth <= 768 || ('ontouchstart' in window);

    if (isMobile && typeof openMobileCommentSheet === 'function') {
        // Use mobile drawer on mobile devices
        openMobileCommentSheet('event', eventId, '');
        return;
    }

    // Desktop: use inline comments section
    const section = document.getElementById('commentsSection');
    const wasHidden = !section.classList.contains('visible');
    section.classList.toggle('visible');

    if (wasHidden && !commentsLoaded) {
        loadComments();
    }
}

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
                target_type: 'event',
                target_id: eventId
            })
        });

        const data = await response.json();

        if (data.error) {
            list.innerHTML = '<p class="event-comments-loading">Failed to load comments</p>';
            return;
        }

        commentsLoaded = true;
        availableReactions = data.available_reactions || [];

        if (!data.comments || data.comments.length === 0) {
            list.innerHTML = '<p class="event-comments-loading event-no-invitees">No comments yet. Be the first to comment!</p>';
            return;
        }

        list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');
    } catch (err) {
        console.error('Comments error:', err);
        list.innerHTML = '<p class="event-comments-loading">Failed to load comments</p>';
    }
}

function renderComment(c, depth) {
    const indent = depth * 20;
    const isEdited = c.is_edited ? '<span class="event-js-edited"> (edited)</span>' : '';
    const ownerActions = c.is_owner ? `
        <span onclick="eventEditComment(${c.id}, '${escapeHtml(c.content)}')" class="event-js-action-icon" title="Edit">✏️</span>
        <span onclick="eventDeleteComment(${c.id})" class="event-js-action-icon-small" title="Delete">🗑️</span>
    ` : '';

    const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
        const isUserReaction = (c.user_reactions || []).includes(emoji);
        return `<span onclick="eventToggleReaction(${c.id}, '${emoji}')" class="event-js-reaction${isUserReaction ? ' active' : ''}">${emoji} ${count}</span>`;
    }).join(' ');

    const reactionPicker = isLoggedIn ? `
        <div class="reaction-picker">
            <span onclick="eventShowReactionPicker(${c.id})" class="event-js-picker-toggle">+</span>
            <div id="picker-${c.id}" class="event-js-picker-dropdown">
                ${availableReactions.map(e => `<span onclick="eventToggleReaction(${c.id}, '${e}')" class="event-js-picker-emoji">${e}</span>`).join('')}
            </div>
        </div>
    ` : '';

    const replyButton = isLoggedIn ? `<span onclick="eventShowReplyForm(${c.id})" class="event-js-reply-link">Reply</span>` : '';

    const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');
    const avatarClass = depth > 0 ? 'event-js-avatar event-js-avatar-reply' : 'event-js-avatar event-js-avatar-main';

    return `
        <div class="event-js-comment" style="margin-left: ${indent}px;" id="comment-${c.id}">
            <div class="event-js-comment-wrapper">
                <img src="${c.author_avatar}" class="${avatarClass}" loading="lazy">
                <div class="event-js-comment-body">
                    <div class="event-js-author">
                        ${escapeHtml(c.author_name)}${isEdited}
                        ${ownerActions}
                    </div>
                    <div id="content-${c.id}" class="event-js-content">${formatContent(c.content)}</div>
                    <div class="event-js-meta">
                        ${new Date(c.created_at).toLocaleString()}
                        ${replyButton}
                    </div>
                    <div class="event-js-reactions">
                        ${reactions}
                        ${reactionPicker}
                    </div>
                    <div id="reply-form-${c.id}" class="event-js-reply-form">
                        <div class="event-js-reply-wrapper">
                            <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." class="event-js-reply-input">
                            <button onclick="eventSubmitReply(${c.id})" class="event-js-reply-submit">Reply</button>
                        </div>
                    </div>
                </div>
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

function formatContent(content) {
    return escapeHtml(content).replace(/@(\w+)/g, '<span class="event-js-mention">@$1</span>');
}

function eventShowReactionPicker(commentId) {
    const picker = document.getElementById(`picker-${commentId}`);
    picker.classList.toggle('visible');
}

function eventShowReplyForm(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    form.classList.toggle('visible');
    if (form.classList.contains('visible')) {
        document.getElementById(`reply-input-${commentId}`).focus();
    }
}

async function eventToggleReaction(commentId, emoji) {
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
    } catch (err) {
        console.error('Reaction error:', err);
    }
}

async function eventSubmitReply(parentId) {
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
                target_type: 'event',
                target_id: eventId,
                parent_id: parentId,
                content: content
            })
        });
        const data = await response.json();
        if (data.error) { alert(data.error); return; }
        input.value = '';
        document.getElementById(`reply-form-${parentId}`).classList.remove('visible');
        const countEl = document.getElementById('commentsCount');
        countEl.textContent = parseInt(countEl.textContent) + 1;
        loadComments();
    } catch (err) {
        console.error('Reply error:', err);
    }
}

async function eventDeleteComment(commentId) {
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
        const countEl = document.getElementById('commentsCount');
        countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
        loadComments();
    } catch (err) {
        console.error('Delete error:', err);
    }
}

function eventEditComment(commentId, currentContent) {
    const contentEl = document.getElementById(`content-${commentId}`);
    const originalHtml = contentEl.innerHTML;

    contentEl.innerHTML = `
        <div class="event-js-edit-wrapper">
            <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" class="event-js-edit-input">
            <button onclick="saveEdit(${commentId})" class="event-js-edit-save">Save</button>
            <button onclick="cancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" class="event-js-edit-cancel">Cancel</button>
        </div>
    `;
    document.getElementById(`edit-input-${commentId}`).focus();
}

function cancelEdit(commentId, originalHtml) {
    document.getElementById(`content-${commentId}`).innerHTML = originalHtml;
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

// Event-specific comment submit (unique name to avoid conflict with social-interactions.js)
async function eventSubmitComment(e) {
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
                target_type: 'event',
                target_id: eventId,
                content: content
            })
        });

        const data = await response.json();

        if (data.error) {
            alert(data.error);
            return;
        }

        input.value = '';
        const countEl = document.getElementById('commentsCount');
        countEl.textContent = parseInt(countEl.textContent) + 1;
        commentsLoaded = false;
        loadComments();
    } catch (err) {
        console.error('Comment submit error:', err);
    }
}

async function shareToFeed() {
    if (!confirm('Share this event to your feed?')) return;

    try {
        const response = await fetch(API_BASE + '/share', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                parent_type: 'event',
                parent_id: eventId
            })
        });

        const data = await response.json();

        if (data.error) {
            alert(data.error);
            return;
        }

        if (data.status === 'success') {
            alert('Event shared to your feed!');
        }
    } catch (err) {
        console.error('Share error:', err);
        alert('Failed to share event');
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
document.querySelectorAll('.btn, button').forEach(btn => {
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
        meta.content = '#f97316';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#f97316');
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

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

    <?php require __DIR__ . '/../../layouts/footer.php'; ?>