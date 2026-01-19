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
                    Hosted by <strong style="color: var(--text-color);"><?= htmlspecialchars($event['organizer_name']) ?></strong>
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
                <div class="sidebar-card" style="margin-top: 30px;">
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap;">
                        <button id="likeBtn" onclick="eventToggleLike()" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; background: <?= $isLiked ? 'rgba(239, 68, 68, 0.15)' : 'var(--pill-bg)' ?>; border: 1px solid <?= $isLiked ? 'rgba(239, 68, 68, 0.3)' : 'var(--glass-border)' ?>; color: var(--text-color); font-size: 1rem; transition: all 0.2s;">
                            <span id="likeIcon"><?= $isLiked ? '❤️' : '🤍' ?></span>
                            <span id="likesCount"><?= $likesCount ?></span> Likes
                        </button>
                        <button onclick="eventToggleComments()" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; background: var(--pill-bg); border: 1px solid var(--glass-border); color: var(--text-color); font-size: 1rem;">
                            💬 <span id="commentsCount"><?= $commentsCount ?></span> Comments
                        </button>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <button onclick="shareToFeed()" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; background: var(--pill-bg); border: 1px solid var(--glass-border); color: var(--text-color); font-size: 1rem;">
                            🔗 Share
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Comments Section (Hidden by default) -->
                    <div id="commentsSection" style="display: none;">
                        <div id="commentsList" style="max-height: 300px; overflow-y: auto; margin-bottom: 15px;">
                            <p style="color: var(--text-muted); text-align: center;">Loading comments...</p>
                        </div>

                        <?php if (isset($_SESSION['user_id'])): ?>
                        <form id="commentForm" onsubmit="eventSubmitComment(event)" style="display: flex; gap: 10px;">
                            <input type="text" id="commentInput" placeholder="Write a comment..." required
                                   style="flex: 1; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--glass-border); background: var(--pill-bg); color: var(--text-color); font-size: 0.95rem;">
                            <button type="submit" class="glass-btn primary" style="padding: 12px 20px;">Post</button>
                        </form>
                        <?php else: ?>
                        <p style="text-align: center; color: var(--text-muted);"><a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" style="color: var(--accent-color);">Login</a> to comment</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Sidebar Column -->
            <div class="sidebar">

                <!-- Invite Banner -->
                <?php if ($myStatus === 'invited'): ?>
                    <div class="sidebar-card" style="border-color: #3b82f6; background: rgba(59, 130, 246, 0.1);">
                        <h4 class="sidebar-title" style="color: #3b82f6;">📩 You're Invited!</h4>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/rsvp" method="POST" class="rsvp-actions">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                            <button name="status" value="going" class="glass-btn primary">Accept</button>
                            <button name="status" value="declined" class="glass-btn">Decline</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- RSVP Status -->
                <div class="sidebar-card">
                    <h4 class="sidebar-title">Your RSVP</h4>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/login" class="glass-btn primary" style="width:100%; display:block; text-decoration:none;">Login to RSVP</a>
                    <?php else: ?>
                        <?php if ($myStatus == 'going'): ?>
                            <div style="margin-bottom: 15px; color: #16a34a; font-weight: bold; font-size: 1.1rem; text-align: center;">
                                ✅ You are going!
                            </div>
                        <?php endif; ?>

                        <?php if ($myStatus !== 'invited'): ?>
                            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/rsvp" method="POST" class="rsvp-actions">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                <button name="status" value="going" class="glass-btn <?= $myStatus == 'going' ? 'primary' : '' ?>">Yes</button>
                                <button name="status" value="declined" class="glass-btn <?= $myStatus == 'declined' ? 'danger' : '' ?>">No</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Attendees -->
                <div class="sidebar-card">
                    <h4 class="sidebar-title" style="display:flex; justify-content:space-between;">
                        Attendees <span style="opacity:0.6;"><?= $count ?></span>
                    </h4>
                    <?php if (empty($attendees)): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">Be the first!</p>
                    <?php else: ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($attendees as $att): ?>
                                <div class="attendee-item">
                                    <?= webp_avatar($att['avatar_url'] ?? null, $att['name'], 40) ?>
                                    <div style="flex:1;">
                                        <div style="font-weight: 600; font-size: 0.95rem;"><?= htmlspecialchars($att['name']) ?></div>
                                        <?php if ($att['status'] == 'attended'): ?>
                                            <div style="font-size: 0.75rem; color: #16a34a;">✅ Attended</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($canInvite && $att['status'] !== 'attended'): ?>
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/check-in" method="POST" onsubmit="return confirm('Confirm?');">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $att['user_id'] ?>">
                                            <button type="submit" class="glass-btn primary" style="padding: 4px 8px; font-size: 0.7rem;">Check In</button>
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
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php if (!empty($canInvite)): ?>
                                <button onclick="document.getElementById('inviteModal').style.display='flex'" class="glass-btn">📩 Invite People</button>
                            <?php endif; ?>

                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>/edit" class="glass-btn" style="text-decoration:none;">⚙️ Edit Event</a>

                            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>/delete" method="POST" onsubmit="return confirm('Cancel event?');">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <button type="submit" class="glass-btn danger" style="width:100%;">Cancel Event</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Glass Overlay Modal for Invites -->
        <?php if (!empty($canInvite)): ?>
            <div id="inviteModal" class="glass-modal-overlay" style="display: none;"> <!-- Explicit inline style as backup -->
                <div class="glass-modal-content">
                    <h3 style="margin-top: 0; color: var(--text-color);">Invite Members</h3>

                    <?php if (!empty($potentialInvitees)): ?>
                        <input type="text" id="inviteSearch" placeholder="🔍 Search members..."
                            style="width: 100%; padding: 10px 15px; border-radius: 12px; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.5); margin-bottom: 15px; color: var(--text-color); font-family: inherit;">

                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/invite" method="POST">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">

                            <div id="inviteList" style="margin-bottom: 25px; max-height: 200px; overflow-y: auto;">
                                <?php foreach ($potentialInvitees as $pm): ?>
                                    <label class="invite-item" style="display: flex; align-items: center; gap: 15px; padding: 10px 0; border-bottom: 1px solid var(--glass-border); cursor: pointer; color: var(--text-color);">
                                        <input type="checkbox" name="user_ids[]" value="<?= $pm['id'] ?>" style="width: 18px; height: 18px; accent-color: var(--accent-color);">
                                        <?= webp_avatar($pm['avatar_url'] ?? null, $pm['name'], 30) ?>
                                        <span class="invite-name"><?= htmlspecialchars($pm['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div style="display: flex; gap: 15px;">
                                <button type="submit" class="glass-btn primary" style="flex: 1;">Send Invites</button>
                                <button type="button" onclick="document.getElementById('inviteModal').style.display='none'" class="glass-btn" style="flex: 1;">Cancel</button>
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
                                        item.style.display = 'flex';
                                    } else {
                                        item.style.display = 'none';
                                    }
                                });
                            });
                        </script>

                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-muted);">
                            <p>No eligible members found to invite.</p>
                            <button type="button" onclick="document.getElementById('inviteModal').style.display='none'" class="glass-btn" style="margin-top: 10px;">Close</button>
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
        btn.style.background = isLiked ? 'rgba(239, 68, 68, 0.15)' : 'var(--pill-bg)';
        btn.style.borderColor = isLiked ? 'rgba(239, 68, 68, 0.3)' : 'var(--glass-border)';
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
    const isHidden = section.style.display === 'none';
    section.style.display = isHidden ? 'block' : 'none';

    if (isHidden && !commentsLoaded) {
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
            list.innerHTML = '<p style="color: var(--text-muted); text-align: center;">Failed to load comments</p>';
            return;
        }

        commentsLoaded = true;
        availableReactions = data.available_reactions || [];

        if (!data.comments || data.comments.length === 0) {
            list.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 20px;">No comments yet. Be the first to comment!</p>';
            return;
        }

        list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');
    } catch (err) {
        console.error('Comments error:', err);
        list.innerHTML = '<p style="color: var(--text-muted); text-align: center;">Failed to load comments</p>';
    }
}

function renderComment(c, depth) {
    const indent = depth * 20;
    const isEdited = c.is_edited ? '<span style="font-size: 0.7rem; color: var(--text-muted);"> (edited)</span>' : '';
    const ownerActions = c.is_owner ? `
        <span onclick="eventEditComment(${c.id}, '${escapeHtml(c.content)}')" style="cursor: pointer; margin-left: 10px;" title="Edit">✏️</span>
        <span onclick="eventDeleteComment(${c.id})" style="cursor: pointer; margin-left: 5px;" title="Delete">🗑️</span>
    ` : '';

    const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
        const isUserReaction = (c.user_reactions || []).includes(emoji);
        return `<span onclick="eventToggleReaction(${c.id}, '${emoji}')" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 0.8rem; background: ${isUserReaction ? 'rgba(99, 102, 241, 0.2)' : 'var(--pill-bg)'}; border: 1px solid ${isUserReaction ? 'rgba(99, 102, 241, 0.4)' : 'var(--glass-border)'};">${emoji} ${count}</span>`;
    }).join(' ');

    const reactionPicker = isLoggedIn ? `
        <div class="reaction-picker" style="display: inline-block; position: relative;">
            <span onclick="eventShowReactionPicker(${c.id})" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 0.8rem; background: var(--pill-bg); border: 1px solid var(--glass-border);">+</span>
            <div id="picker-${c.id}" style="display: none; position: absolute; bottom: 100%; left: 0; background: var(--card-bg, #fff); border: 1px solid var(--glass-border); border-radius: 8px; padding: 5px; z-index: 100; white-space: nowrap;">
                ${availableReactions.map(e => `<span onclick="eventToggleReaction(${c.id}, '${e}')" style="cursor: pointer; padding: 3px; font-size: 1.2rem;">${e}</span>`).join('')}
            </div>
        </div>
    ` : '';

    const replyButton = isLoggedIn ? `<span onclick="eventShowReplyForm(${c.id})" style="cursor: pointer; color: var(--accent-color); font-size: 0.8rem; margin-left: 10px;">Reply</span>` : '';

    const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

    return `
        <div style="margin-left: ${indent}px; padding: 12px 0; border-bottom: 1px solid var(--glass-border);" id="comment-${c.id}">
            <div style="display: flex; gap: 12px;">
                <img src="${c.author_avatar}" style="width: ${depth loading="lazy"> 0 ? 28 : 36}px; height: ${depth > 0 ? 28 : 36}px; border-radius: 50%; object-fit: cover;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-color);">
                        ${escapeHtml(c.author_name)}${isEdited}
                        ${ownerActions}
                    </div>
                    <div id="content-${c.id}" style="color: var(--text-color); margin-top: 4px;">${formatContent(c.content)}</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        ${new Date(c.created_at).toLocaleString()}
                        ${replyButton}
                    </div>
                    <div style="margin-top: 6px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                        ${reactions}
                        ${reactionPicker}
                    </div>
                    <div id="reply-form-${c.id}" style="display: none; margin-top: 10px;">
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." style="flex: 1; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--pill-bg); color: var(--text-color); font-size: 0.85rem;">
                            <button onclick="eventSubmitReply(${c.id})" style="padding: 8px 16px; border-radius: 8px; background: var(--accent-color); color: white; border: none; cursor: pointer; font-size: 0.85rem;">Reply</button>
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
    return escapeHtml(content).replace(/@(\w+)/g, '<span style="color: var(--accent-color); font-weight: 600;">@$1</span>');
}

function eventShowReactionPicker(commentId) {
    const picker = document.getElementById(`picker-${commentId}`);
    picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
}

function eventShowReplyForm(commentId) {
    const form = document.getElementById(`reply-form-${commentId}`);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
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
        document.getElementById(`reply-form-${parentId}`).style.display = 'none';
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
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" style="flex: 1; min-width: 200px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--pill-bg); color: var(--text-color);">
            <button onclick="saveEdit(${commentId})" style="padding: 8px 16px; border-radius: 8px; background: var(--accent-color); color: white; border: none; cursor: pointer;">Save</button>
            <button onclick="cancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" style="padding: 8px 16px; border-radius: 8px; background: var(--pill-bg); border: 1px solid var(--glass-border); color: var(--text-color); cursor: pointer;">Cancel</button>
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
document.querySelectorAll('.glass-btn, button').forEach(btn => {
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