<?php
/**
 * CivicOne View: Goal Detail
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = htmlspecialchars($goal['title']);
$isAuthor = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $goal['user_id'];
$hasMentor = !empty($goal['mentor_id']);
$isMentor = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $goal['mentor_id'];

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/goals">Goals</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page"><?= htmlspecialchars($goal['title']) ?></li>
    </ol>
</nav>

<a href="<?= $basePath ?>/goals" class="govuk-back-link govuk-!-margin-bottom-6">Back to Goals</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <!-- Status Tag -->
        <?php if ($goal['status'] === 'completed'): ?>
            <span class="govuk-tag govuk-tag--green govuk-!-margin-bottom-4">Completed</span>
        <?php else: ?>
            <span class="govuk-tag govuk-tag--light-blue govuk-!-margin-bottom-4">Active Goal</span>
        <?php endif; ?>

        <h1 class="govuk-heading-xl govuk-!-margin-bottom-4"><?= htmlspecialchars($goal['title']) ?></h1>

        <?php if ($isAuthor): ?>
            <div class="govuk-button-group govuk-!-margin-bottom-6">
                <?php if ($goal['status'] === 'active'): ?>
                    <form action="<?= $basePath ?>/goals/<?= $goal['id'] ?>/complete" method="POST" class="civicone-inline-form"
                          onsubmit="return confirm('Mark as achieved? Great job!')">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <button type="submit" class="govuk-button" data-module="govuk-button">
                            <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                            Mark Complete
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= $basePath ?>/goals/<?= $goal['id'] ?>/edit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-pen govuk-!-margin-right-1" aria-hidden="true"></i>
                    Edit
                </a>
            </div>
        <?php endif; ?>

        <!-- Description -->
        <p class="govuk-body-l govuk-!-margin-bottom-6"><?= nl2br(htmlspecialchars($goal['description'])) ?></p>

        <!-- Buddy Status -->
        <?php if ($hasMentor): ?>
            <!-- Matched State -->
            <div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="buddy-heading">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="buddy-heading">Accountability Partner</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">
                        <i class="fa-solid fa-handshake govuk-!-margin-right-1" aria-hidden="true"></i>
                        Matched with <?= htmlspecialchars($goal['mentor_name']) ?>
                    </p>
                    <?php if ($isAuthor || $isMentor): ?>
                        <p class="govuk-body">
                            <a href="<?= $basePath ?>/messages?create=1&to=<?= $isAuthor ? $goal['mentor_id'] : $goal['user_id'] ?>" class="govuk-link">
                                Send a message
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($goal['is_public']): ?>
            <!-- Looking for Buddy -->
            <div class="govuk-inset-text govuk-!-margin-bottom-6">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                    <i class="fa-solid fa-magnifying-glass govuk-!-margin-right-1" aria-hidden="true"></i>
                    Looking for an Accountability Partner
                </h3>
                <p class="govuk-body govuk-!-margin-bottom-2">This goal is public. Waiting for a community member to offer support.</p>
                <?php if (!$isAuthor && isset($_SESSION['user_id'])): ?>
                    <form action="<?= $basePath ?>/goals/buddy" method="POST" class="civicone-inline-form"
                          onsubmit="return confirm('Are you sure you want to be the accountability partner for this goal?');">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
                        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                            <i class="fa-solid fa-handshake govuk-!-margin-right-1" aria-hidden="true"></i>
                            Become Buddy
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Private Goal -->
            <div class="govuk-inset-text govuk-!-margin-bottom-6">
                <p class="govuk-body govuk-!-margin-bottom-0">
                    <i class="fa-solid fa-lock govuk-!-margin-right-1" aria-hidden="true"></i>
                    <strong>Private Goal</strong> — Only you can see this goal.
                </p>
            </div>
        <?php endif; ?>

        <!-- Social Engagement Section -->
        <?php
        $goalId = $goal['id'];
        $likesCount = $likesCount ?? 0;
        $commentsCount = $commentsCount ?? 0;
        $isLiked = $isLiked ?? false;
        $isLoggedIn = $isLoggedIn ?? !empty($_SESSION['user_id']);
        ?>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <div class="govuk-!-margin-bottom-6">
            <div class="govuk-button-group">
                <button id="like-btn" type="button" onclick="goalToggleLike()"
                        class="govuk-button <?= $isLiked ? '' : 'govuk-button--secondary' ?>" data-module="govuk-button">
                    <i class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart govuk-!-margin-right-1" id="like-icon" aria-hidden="true"></i>
                    <span id="like-count"><?= $likesCount ?></span> <?= $likesCount === 1 ? 'Like' : 'Likes' ?>
                </button>
                <button type="button" onclick="goalToggleComments()" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-regular fa-comment govuk-!-margin-right-1" aria-hidden="true"></i>
                    <span id="comment-count"><?= $commentsCount ?></span> <?= $commentsCount === 1 ? 'Comment' : 'Comments' ?>
                </button>
                <?php if ($isLoggedIn): ?>
                <button type="button" onclick="shareToFeed()" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-share govuk-!-margin-right-1" aria-hidden="true"></i>
                    Share
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments Section -->
        <div id="comments-section" class="govuk-!-margin-bottom-6" hidden>
            <?php if ($isLoggedIn): ?>
            <form onsubmit="goalSubmitComment(event)" class="govuk-!-margin-bottom-4">
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-visually-hidden" for="comment-input">Write a comment</label>
                    <textarea id="comment-input" class="govuk-textarea" rows="3" placeholder="Write a comment..."></textarea>
                </div>
                <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    Post Comment
                </button>
            </form>
            <?php else: ?>
            <p class="govuk-body">
                <a href="<?= $basePath ?>/login" class="govuk-link">Log in</a> to leave a comment.
            </p>
            <?php endif; ?>
            <div id="comments-list">
                <p class="govuk-body civicone-secondary-text">Loading comments...</p>
            </div>
        </div>

    </div>
</div>

<script>
(function() {
    const goalId = <?= $goalId ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    let isLiked = <?= $isLiked ? 'true' : 'false' ?>;
    let commentsLoaded = false;
    let availableReactions = [];

    const API_BASE = '<?= $basePath ?>/api/social';

    window.goalToggleLike = async function() {
        <?php if (!$isLoggedIn): ?>
        window.location.href = '<?= $basePath ?>/login';
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
                btn.classList.remove('govuk-button--secondary');
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
            } else {
                btn.classList.add('govuk-button--secondary');
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
        const isMobile = window.innerWidth <= 768 || ('ontouchstart' in window);

        if (isMobile && typeof openMobileCommentSheet === 'function') {
            openMobileCommentSheet('goal', goalId, '');
            return;
        }

        const section = document.getElementById('comments-section');
        const isHidden = section.hidden;

        section.hidden = !isHidden;

        if (isHidden && !commentsLoaded) {
            loadComments();
        }
    };

    async function loadComments() {
        const list = document.getElementById('comments-list');
        list.innerHTML = '<p class="govuk-body civicone-secondary-text">Loading comments...</p>';

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
                list.innerHTML = '<p class="govuk-body civicone-secondary-text">Failed to load comments.</p>';
                return;
            }

            const data = await response.json();

            if (data.error) {
                list.innerHTML = '<p class="govuk-body civicone-secondary-text">Failed to load comments.</p>';
                return;
            }

            commentsLoaded = true;
            availableReactions = data.available_reactions || [];

            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<p class="govuk-body civicone-secondary-text">No comments yet. Be the first to comment!</p>';
                return;
            }

            list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');

        } catch (err) {
            console.error('Load comments error:', err);
            list.innerHTML = '<p class="govuk-body civicone-secondary-text">Failed to load comments.</p>';
        }
    }

    function renderComment(c, depth) {
        const depthClass = depth > 0 ? ` civicone-comment--depth-${Math.min(depth, 3)}` : '';
        const isEdited = c.is_edited ? '<span class="govuk-body-s civicone-secondary-text"> (edited)</span>' : '';
        const ownerActions = c.is_owner ? `
            <a href="#" onclick="goalEditComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'")}'); return false;" class="govuk-link govuk-body-s">Edit</a>
            <a href="#" onclick="goalDeleteComment(${c.id}); return false;" class="govuk-link govuk-body-s civicone-link-danger">Delete</a>
        ` : '';

        const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
            const isUserReaction = (c.user_reactions || []).includes(emoji);
            const activeClass = isUserReaction ? 'govuk-tag--light-blue' : 'govuk-tag--grey';
            return `<span class="govuk-tag ${activeClass} civicone-tag-clickable" onclick="goalToggleReaction(${c.id}, '${emoji}')">${emoji} ${count}</span>`;
        }).join(' ');

        const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

        return `
            <div class="govuk-!-padding-3 govuk-!-margin-bottom-3 civicone-comment${depthClass}">
                <p class="govuk-body-s govuk-!-margin-bottom-1">
                    <strong>${escapeHtml(c.author_name)}</strong>${isEdited}
                    <span class="civicone-secondary-text">&middot; ${c.time_ago}</span>
                    ${ownerActions}
                </p>
                <p id="content-${c.id}" class="govuk-body govuk-!-margin-bottom-2">${escapeHtml(c.content)}</p>
                <div class="govuk-!-margin-bottom-2">
                    ${reactions}
                    <a href="#" onclick="goalShowReplyForm(${c.id}); return false;" class="govuk-link govuk-body-s">Reply</a>
                </div>
                <div id="reply-form-${c.id}" class="govuk-!-margin-top-2" hidden>
                    <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." class="govuk-input govuk-!-margin-bottom-2">
                    <button onclick="goalSubmitReply(${c.id})" class="govuk-button govuk-button--secondary govuk-button--small" data-module="govuk-button">Reply</button>
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
        const wasHidden = form.hidden;
        form.hidden = !wasHidden;
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
            document.getElementById(`reply-form-${parentId}`).hidden = true;
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
            <div class="govuk-form-group govuk-!-margin-bottom-2">
                <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" class="govuk-input">
            </div>
            <button onclick="goalSaveEdit(${commentId})" class="govuk-button govuk-button--secondary govuk-button--small" data-module="govuk-button">Save</button>
            <button onclick="goalCancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" class="govuk-button govuk-button--secondary govuk-button--small" data-module="govuk-button">Cancel</button>
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

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
