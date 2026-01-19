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


<div style="max-width: 1100px; margin: 40px auto; padding: 0 20px; font-family: 'Inter', sans-serif;">

    <!-- Back Navigation -->
    <div style="margin-bottom: 20px;">
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" style="text-decoration: none; color: #64748b; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Opportunities
        </a>
    </div>

    <div class="page-grid" style="display: grid; grid-template-columns: 1fr 380px; gap: 40px; align-items: start;">

        <!-- LEFT COLUMN: Content -->
        <main>
            <!-- Header Card -->
            <div class="glass-card">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <span class="glass-badge">
                            <i class="fa-solid fa-hand-holding-heart"></i> Volunteering
                        </span>
                        <?php if ($opportunity['location']): ?>
                            <span style="margin-left: 12px; color: var(--htb-text-muted); font-size: 0.95rem;">
                                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($opportunity['location']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <h1 style="margin: 0 0 15px 0; font-size: 2.2rem; line-height: 1.2; color: var(--htb-text-main);">
                    <?= htmlspecialchars($opportunity['title']) ?>
                </h1>

                <div style="display: flex; align-items: center; gap: 12px; color: var(--htb-text-muted); font-size: 0.95rem;">
                    <span><i class="fa-solid fa-building"></i> <?= htmlspecialchars($opportunity['org_name']) ?></span>
                    <?php if ($opportunity['org_website']): ?>
                        <span>&bull;</span>
                        <a href="<?= htmlspecialchars($opportunity['org_website']) ?>" target="_blank" style="color: <?= $accentColor ?>;">
                            Visit Website <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.8rem;"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description Card -->
            <div class="glass-card">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 15px 0;">
                    <i class="fa-solid fa-info-circle" style="color: <?= $accentColor ?>;"></i> About the Role
                </h3>
                <div style="font-size: 1.1rem; line-height: 1.8; color: var(--htb-text-muted); white-space: pre-wrap;">
                    <?= nl2br(htmlspecialchars($opportunity['description'])) ?>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="glass-card">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 20px 0;">
                    <i class="fa-solid fa-clipboard-list" style="color: <?= $accentColor ?>;"></i> Details
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div class="glass-info-pill">
                        <i class="fa-solid fa-tools" style="font-size: 1.2rem; color: <?= $accentColor ?>;"></i>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--htb-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Skills Needed</div>
                            <div style="font-weight: 600; color: var(--htb-text-main);">
                                <?= htmlspecialchars($opportunity['skills_needed'] ?? 'None specified') ?>
                            </div>
                        </div>
                    </div>
                    <div class="glass-info-pill">
                        <i class="fa-solid fa-calendar-days" style="font-size: 1.2rem; color: <?= $accentColor ?>;"></i>
                        <div>
                            <div style="font-size: 0.8rem; color: var(--htb-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Dates</div>
                            <div style="font-weight: 600; color: var(--htb-text-main);">
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
                <div style="display: flex; align-items: center; gap: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(100,116,139,0.2); flex-wrap: wrap;">
                    <button id="like-btn" onclick="volToggleLike()" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.2s ease; background: <?= $isLiked ? 'linear-gradient(135deg, #14b8a6, #0d9488)' : 'rgba(100,116,139,0.1)' ?>; color: <?= $isLiked ? '#fff' : 'var(--htb-text-main)' ?>;">
                        <i class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart" id="like-icon"></i>
                        <span id="like-count"><?= $likesCount ?></span>
                        <span><?= $likesCount === 1 ? 'Like' : 'Likes' ?></span>
                    </button>
                    <button onclick="volToggleComments()" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.2s ease; background: rgba(100,116,139,0.1); color: var(--htb-text-main);">
                        <i class="fa-regular fa-comment"></i>
                        <span id="comment-count"><?= $commentsCount ?></span>
                        <span><?= $commentsCount === 1 ? 'Comment' : 'Comments' ?></span>
                    </button>
                    <?php if ($isLoggedIn): ?>
                    <button onclick="shareToFeed()" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.2s ease; background: rgba(100,116,139,0.1); color: var(--htb-text-main);">
                        <i class="fa-solid fa-share"></i> Share
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Comments Section (Initially Hidden) -->
                <div id="comments-section" style="display: none; padding-top: 20px;">
                    <h4 style="font-size: 1rem; font-weight: 700; color: var(--htb-text-main); margin-bottom: 15px;">Comments</h4>

                    <?php if ($isLoggedIn): ?>
                        <form id="comment-form" onsubmit="volunteeringSubmitComment(event)" style="display: flex; gap: 12px; margin-bottom: 20px;">
                            <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp') ?>" loading="lazy" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                            <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
                                <textarea id="comment-input" placeholder="Write a comment..." style="width: 100%; min-height: 60px; padding: 12px; border-radius: 12px; border: 1px solid rgba(100,116,139,0.3); background: rgba(255,255,255,0.5); font-size: 0.95rem; resize: vertical; font-family: inherit;"></textarea>
                                <button type="submit" style="align-self: flex-end; padding: 8px 20px; border-radius: 8px; border: none; background: <?= $accentColor ?>; color: #fff; font-weight: 600; cursor: pointer; transition: opacity 0.2s;">
                                    Post Comment
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; background: rgba(100,116,139,0.05); border-radius: 12px; margin-bottom: 20px;">
                            <p style="color: var(--htb-text-muted); margin: 0;">
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" style="color: <?= $accentColor ?>; font-weight: 600; text-decoration: none;">Sign in</a> to leave a comment
                            </p>
                        </div>
                    <?php endif; ?>

                    <div id="comments-list" style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="text-align: center; color: var(--htb-text-muted); padding: 20px;">
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
                <div style="text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid rgba(100,116,139,0.2);">
                    <div style="width: 70px; height: 70px; background: linear-gradient(135deg, <?= $accentColor ?>, #0d9488); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 16px rgba(20, 184, 166, 0.3);">
                        <i class="fa-solid fa-building" style="font-size: 1.8rem; color: #fff;"></i>
                    </div>
                    <h3 style="margin: 0 0 5px 0; font-size: 1.2rem; color: var(--htb-text-main);">
                        <?= htmlspecialchars($opportunity['org_name']) ?>
                    </h3>
                    <p style="margin: 0; color: var(--htb-text-muted); font-size: 0.9rem;">Organization</p>
                </div>

                <!-- Application Status / Form -->
                <?php if (isset($_GET['msg']) && $_GET['msg'] == 'applied'): ?>
                    <div style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(16, 185, 129, 0.1)); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(34, 197, 94, 0.3);">
                        <i class="fa-solid fa-circle-check" style="font-size: 2rem; color: #22c55e; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-weight: 600; color: #166534;">Application Sent!</p>
                        <p style="margin: 5px 0 0; font-size: 0.9rem; color: #15803d;">The organisation will contact you shortly.</p>
                    </div>
                <?php elseif ($hasApplied): ?>
                    <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(99, 102, 241, 0.1)); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid rgba(59, 130, 246, 0.3);">
                        <i class="fa-solid fa-clock" style="font-size: 2rem; color: #3b82f6; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-weight: 600; color: #1e40af;">Already Applied</p>
                        <p style="margin: 5px 0 0; font-size: 0.9rem; color: #1e3a8a;">You've applied for this opportunity.</p>
                    </div>
                <?php elseif (isset($_SESSION['user_id'])): ?>
                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/apply" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="opportunity_id" value="<?= $opportunity['id'] ?>">

                        <?php if (!empty($shifts)): ?>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 10px; font-weight: 600; color: var(--htb-text-main);">
                                    <i class="fa-solid fa-clock" style="color: <?= $accentColor ?>;"></i> Select a Shift
                                </label>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <?php foreach ($shifts as $shift): ?>
                                        <label class="glass-shift-card" onclick="this.querySelector('input').checked = true; document.querySelectorAll('.glass-shift-card').forEach(c => c.classList.remove('selected')); this.classList.add('selected');">
                                            <input type="radio" name="shift_id" value="<?= $shift['id'] ?>" required style="display: none;">
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <div>
                                                    <div style="font-weight: 600; color: var(--htb-text-main);">
                                                        <?= date('M d', strtotime($shift['start_time'])) ?>
                                                    </div>
                                                    <div style="font-size: 0.85rem; color: var(--htb-text-muted);">
                                                        <?= date('g:i A', strtotime($shift['start_time'])) ?> - <?= date('g:i A', strtotime($shift['end_time'])) ?>
                                                    </div>
                                                </div>
                                                <div style="font-size: 0.8rem; color: var(--htb-text-muted);">
                                                    <?= $shift['capacity'] ?> spots
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--htb-text-main);">
                                <i class="fa-solid fa-message" style="color: <?= $accentColor ?>;"></i> Message (Optional)
                            </label>
                            <textarea name="message" rows="3" placeholder="Tell them why you'd like to volunteer..." style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid rgba(100,116,139,0.3); background: rgba(255,255,255,0.5); font-size: 0.95rem; resize: vertical; font-family: inherit;"></textarea>
                        </div>

                        <button type="submit" class="glass-btn-primary">
                            <i class="fa-solid fa-paper-plane"></i> Apply Now
                        </button>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px; background: rgba(100,116,139,0.05); border-radius: 12px;">
                        <i class="fa-solid fa-user-lock" style="font-size: 2rem; color: var(--htb-text-muted); margin-bottom: 10px;"></i>
                        <p style="margin: 0 0 15px; color: var(--htb-text-muted);">Join our community to volunteer.</p>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="glass-btn-primary" style="display: block; text-decoration: none; text-align: center;">
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
                btn.style.background = 'linear-gradient(135deg, #14b8a6, #0d9488)';
                btn.style.color = '#fff';
                icon.className = 'fa-solid fa-heart';
            } else {
                btn.style.background = 'rgba(100,116,139,0.1)';
                btn.style.color = 'var(--htb-text-main)';
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
        const isHidden = section.style.display === 'none';

        section.style.display = isHidden ? 'block' : 'none';

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
                list.innerHTML = '<p style="color: var(--htb-text-muted); text-align: center;">Failed to load comments</p>';
                return;
            }

            commentsLoaded = true;
            availableReactions = data.available_reactions || [];

            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<p style="color: var(--htb-text-muted); text-align: center; padding: 20px;">No comments yet. Be the first to comment!</p>';
                return;
            }

            list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');

        } catch (err) {
            console.error('Load comments error:', err);
            list.innerHTML = '<p style="color: var(--htb-text-muted); text-align: center;">Error loading comments</p>';
        }
    }

    function renderComment(c, depth) {
        const indent = depth * 20;
        const isEdited = c.is_edited ? '<span style="font-size: 0.7rem; color: var(--htb-text-muted);"> (edited)</span>' : '';
        const ownerActions = c.is_owner ? `
            <span onclick="volunteeringEditComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'")}')" style="cursor: pointer; margin-left: 10px;" title="Edit">✏️</span>
            <span onclick="volunteeringDeleteComment(${c.id})" style="cursor: pointer; margin-left: 5px;" title="Delete">🗑️</span>
        ` : '';

        const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
            const isUserReaction = (c.user_reactions || []).includes(emoji);
            return `<span onclick="volunteeringToggleReaction(${c.id}, '${emoji}')" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 0.8rem; background: ${isUserReaction ? 'rgba(20, 184, 166, 0.2)' : 'rgba(100,116,139,0.1)'}; border: 1px solid ${isUserReaction ? 'rgba(20, 184, 166, 0.4)' : 'rgba(100,116,139,0.2)'};">${emoji} ${count}</span>`;
        }).join(' ');

        const reactionPicker = isLoggedIn ? `
            <div class="reaction-picker" style="display: inline-block; position: relative;">
                <span onclick="volunteeringShowReactionPicker(${c.id})" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 0.8rem; background: rgba(100,116,139,0.1); border: 1px solid rgba(100,116,139,0.2);">+</span>
                <div id="picker-${c.id}" style="display: none; position: absolute; bottom: 100%; left: 0; background: var(--htb-card-bg, #fff); border: 1px solid rgba(100,116,139,0.2); border-radius: 8px; padding: 5px; z-index: 100; white-space: nowrap; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    ${availableReactions.map(e => `<span onclick="volunteeringToggleReaction(${c.id}, '${e}')" style="cursor: pointer; padding: 3px; font-size: 1.2rem;">${e}</span>`).join('')}
                </div>
            </div>
        ` : '';

        const replyButton = isLoggedIn ? `<span onclick="volunteeringShowReplyForm(${c.id})" style="cursor: pointer; color: <?= $accentColor ?>; font-size: 0.8rem; margin-left: 10px;">Reply</span>` : '';

        const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

        return `
            <div style="margin-left: ${indent}px; padding: 12px; background: rgba(100,116,139,0.05); border-radius: 12px; margin-bottom: 10px;" id="comment-${c.id}">
                <div style="display: flex; gap: 12px;">
                    <img src="${c.author_avatar}" style="width: ${depth loading="lazy"> 0 ? 28 : 36}px; height: ${depth > 0 ? 28 : 36}px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; font-size: 0.9rem; color: var(--htb-text-main);">
                            ${escapeHtml(c.author_name)}${isEdited}
                            ${ownerActions}
                        </div>
                        <div id="content-${c.id}" style="color: var(--htb-text-main); margin-top: 4px;">${formatContent(c.content)}</div>
                        <div style="font-size: 0.75rem; color: var(--htb-text-muted); margin-top: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            ${formatTime(c.created_at)}
                            ${replyButton}
                        </div>
                        <div style="margin-top: 6px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            ${reactions}
                            ${reactionPicker}
                        </div>
                        <div id="reply-form-${c.id}" style="display: none; margin-top: 10px;">
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." style="flex: 1; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(100,116,139,0.3); background: rgba(255,255,255,0.5); color: var(--htb-text-main); font-size: 0.85rem;">
                                <button onclick="volunteeringSubmitReply(${c.id})" style="padding: 8px 16px; border-radius: 8px; background: <?= $accentColor ?>; color: white; border: none; cursor: pointer; font-size: 0.85rem;">Reply</button>
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
        return escapeHtml(content).replace(/@(\w+)/g, '<span style="color: <?= $accentColor ?>; font-weight: 600;">@$1</span>');
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
        picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
    };

    window.volunteeringShowReplyForm = function(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
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
            document.getElementById(`reply-form-${parentId}`).style.display = 'none';
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
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" style="flex: 1; min-width: 200px; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(100,116,139,0.3); background: rgba(255,255,255,0.5); color: var(--htb-text-main);">
                <button onclick="saveEdit(${commentId})" style="padding: 8px 16px; border-radius: 8px; background: <?= $accentColor ?>; color: white; border: none; cursor: pointer;">Save</button>
                <button onclick="cancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" style="padding: 8px 16px; border-radius: 8px; background: rgba(100,116,139,0.1); border: 1px solid rgba(100,116,139,0.2); color: var(--htb-text-main); cursor: pointer;">Cancel</button>
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
        document.querySelectorAll('#vol-show-glass-wrapper button, #vol-show-glass-wrapper .glass-btn-primary').forEach(btn => {
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
