<?php
/**
 * CivicOne Social Interactions Partial
 * =====================================
 * Provides like, comment, and share functionality for any content type
 *
 * Required variables:
 * - $targetType: 'listing', 'event', 'poll', 'volunteering', 'goal'
 * - $targetId: The ID of the content
 * - $likesCount: Current like count (optional, defaults to 0)
 * - $commentsCount: Current comment count (optional, defaults to 0)
 * - $isLiked: Whether current user has liked (optional, defaults to false)
 *
 * Usage:
 * $targetType = 'listing';
 * $targetId = $listing['id'];
 * include __DIR__ . '/../partials/social_interactions.php';
 *
 * CSS extracted to: /assets/css/social-interactions.css
 * CSS loaded via layout headers for proper caching
 */

if (!isset($targetType) || !isset($targetId)) {
    return; // Required variables not set
}

$basePath = \Nexus\Core\TenantContext::getBasePath();
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$tenantId = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::get()['id'] : ($_SESSION['current_tenant_id'] ?? 1);

// Fetch current stats if not provided
if (!isset($likesCount) || !isset($commentsCount) || !isset($isLiked)) {
    try {
        $dbClass = class_exists('\Nexus\Core\Database') ? '\Nexus\Core\Database' : '\Nexus\Core\DatabaseWrapper';

        if (!isset($likesCount)) {
            $result = $dbClass::query("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?", [$targetType, $targetId])->fetch();
            $likesCount = $result['cnt'] ?? 0;
        }

        if (!isset($commentsCount)) {
            $result = $dbClass::query("SELECT COUNT(*) as cnt FROM comments WHERE target_type = ? AND target_id = ?", [$targetType, $targetId])->fetch();
            $commentsCount = $result['cnt'] ?? 0;
        }

        if (!isset($isLiked) && $isLoggedIn) {
            $result = $dbClass::query("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?", [$userId, $targetType, $targetId])->fetch();
            $isLiked = !empty($result);
        } else {
            $isLiked = false;
        }
    } catch (Exception $e) {
        $likesCount = 0;
        $commentsCount = 0;
        $isLiked = false;
    }
}

// Generate unique ID for this interaction block
$interactionId = $targetType . '-' . $targetId;
?>

<div class="civic-social-interactions" id="social-<?= $interactionId ?>">
    <!-- Stats Row -->
    <div class="civic-social-stats">
        <button type="button"
                class="civic-social-stats-left likes-count-clickable"
                onclick="event.stopPropagation(); showLikers('<?= $targetType ?>', <?= $targetId ?>)"
                aria-label="See who liked this (<?= $likesCount ?> like<?= $likesCount != 1 ? 's' : '' ?>)">
            <?php if ($likesCount > 0): ?>
                <span class="dashicons dashicons-heart civic-text-red" style="font-size: 16px;" aria-hidden="true"></span>
                <span id="likes-count-<?= $interactionId ?>"><?= $likesCount ?></span>
            <?php else: ?>
                <span id="likes-count-<?= $interactionId ?>" class="visually-hidden">0</span>
            <?php endif; ?>
        </button>
        <button type="button"
                class="civic-social-stats-right"
                onclick="toggleSocialComments('<?= $interactionId ?>')"
                aria-expanded="false"
                aria-controls="comments-<?= $interactionId ?>"
                aria-label="Toggle comments section (<?= $commentsCount ?> comment<?= $commentsCount != 1 ? 's' : '' ?>)">
            <span id="comments-count-<?= $interactionId ?>"><?= $commentsCount ?></span> Comment<?= $commentsCount != 1 ? 's' : '' ?>
        </button>
    </div>

    <!-- Action Buttons -->
    <div class="civic-social-actions">
        <button type="button"
                onclick="toggleSocialLike('<?= $interactionId ?>', '<?= $targetType ?>', <?= $targetId ?>)"
                class="civic-social-btn <?= $isLiked ? 'liked' : '' ?>"
                id="like-btn-<?= $interactionId ?>"
                aria-pressed="<?= $isLiked ? 'true' : 'false' ?>"
                aria-label="<?= $isLiked ? 'Unlike this content' : 'Like this content' ?>">
            <span class="dashicons dashicons-heart" aria-hidden="true"></span>
            <span>Like</span>
        </button>
        <button type="button"
                onclick="toggleSocialComments('<?= $interactionId ?>')"
                class="civic-social-btn"
                aria-expanded="false"
                aria-controls="comments-<?= $interactionId ?>"
                aria-label="Toggle comments">
            <span class="dashicons dashicons-admin-comments" aria-hidden="true"></span>
            <span>Comment</span>
        </button>
        <button type="button"
                onclick="openShareModal('<?= $targetType ?>', <?= $targetId ?>)"
                class="civic-social-btn"
                aria-haspopup="dialog"
                aria-label="Share this content">
            <span class="dashicons dashicons-share" aria-hidden="true"></span>
            <span>Share</span>
        </button>
    </div>

    <!-- Comments Section -->
    <div class="civic-social-comments" id="comments-<?= $interactionId ?>">
        <?php if ($isLoggedIn): ?>
            <div class="civic-comment-form">
                <img src="<?= htmlspecialchars($_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp') ?>"
                     class="civic-comment-avatar"
                     alt="Your profile picture">
                <div class="civic-comment-input-wrap">
                    <input type="text"
                           class="civic-comment-input"
                           id="comment-input-<?= $interactionId ?>"
                           placeholder="Write a comment..."
                           onkeydown="if(event.key === 'Enter') submitSocialComment('<?= $interactionId ?>', '<?= $targetType ?>', <?= $targetId ?>)"
                           aria-label="Write a comment">
                    <button type="button"
                            class="civic-comment-submit"
                            onclick="submitSocialComment('<?= $interactionId ?>', '<?= $targetType ?>', <?= $targetId ?>)"
                            aria-label="Submit comment">
                        <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <p class="civic-social-login-prompt">
                <a href="<?= $basePath ?>/login" class="civic-social-login-link">Sign in</a> to comment
            </p>
        <?php endif; ?>
        <div class="civic-comments-list"
             id="comments-list-<?= $interactionId ?>"
             role="region"
             aria-label="Comments"
             aria-live="polite">
            <p class="civic-social-status-message">
                Click to load comments...
            </p>
        </div>
    </div>
</div>

<!-- Share Modal (only rendered once per page) -->
<?php if (!defined('CIVIC_SHARE_MODAL_RENDERED')): ?>
<?php define('CIVIC_SHARE_MODAL_RENDERED', true); ?>
<div class="civic-share-modal"
     id="civic-share-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="civic-share-modal-title"
     aria-hidden="true">
    <div class="civic-share-content" role="document">
        <h2 class="civic-share-title" id="civic-share-modal-title">Share this content</h2>
        <div class="civic-share-options" role="group" aria-label="Share options">
            <button type="button"
                    id="share-to-feed"
                    class="civic-share-option"
                    onclick="shareToFeedFromModal()">
                <span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
                Share to your Feed
            </button>
            <button type="button"
                    id="share-copy-link"
                    class="civic-share-option"
                    onclick="copyShareLink()">
                <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                Copy Link
            </button>
        </div>
        <button type="button"
                class="civic-share-close"
                onclick="closeShareModal()"
                aria-label="Cancel and close share dialog">
            Cancel
        </button>
    </div>
</div>

<div class="civic-social-toast" id="civic-social-toast" role="alert" aria-live="polite"></div>

<script>
(function() {
    // Prevent duplicate script initialization
    if (window.civicSocialInitialized) return;
    window.civicSocialInitialized = true;

    const BASE_PATH = "<?= $basePath ?>";
    const IS_LOGGED_IN = <?= json_encode($isLoggedIn) ?>;
    let currentShareType = '';
    let currentShareId = 0;

    // Toast notification
    window.showSocialToast = function(message) {
        const toast = document.getElementById('civic-social-toast');
        if (toast) {
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
    };

    // Show Likers (stub - may be overridden by page-specific implementation)
    if (typeof window.showLikers === 'undefined') {
        window.showLikers = function(targetType, targetId) {
            showSocialToast('View likes coming soon');
        };
    }

    // Toggle Like
    window.toggleSocialLike = function(interactionId, targetType, targetId) {
        if (!IS_LOGGED_IN) {
            showSocialToast('Please sign in to like');
            return;
        }

        const btn = document.getElementById('like-btn-' + interactionId);
        const isLiked = btn.classList.contains('liked');

        // Optimistic UI update
        btn.classList.toggle('liked');
        btn.setAttribute('aria-pressed', !isLiked);
        btn.setAttribute('aria-label', !isLiked ? 'Unlike this content' : 'Like this content');

        const formData = new FormData();
        formData.append('action', 'toggle_like');
        formData.append('target_type', targetType);
        formData.append('target_id', targetId);

        fetch(BASE_PATH + '/api/social/like', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    // Revert on error
                    btn.classList.toggle('liked');
                    btn.setAttribute('aria-pressed', isLiked);
                    btn.setAttribute('aria-label', isLiked ? 'Unlike this content' : 'Like this content');
                    showSocialToast(data.error);
                } else {
                    // Update count
                    const countEl = document.getElementById('likes-count-' + interactionId);
                    if (countEl) {
                        countEl.textContent = data.likes_count || 0;
                        countEl.parentElement.style.display = data.likes_count > 0 ? 'flex' : 'none';
                    }
                }
            })
            .catch(() => {
                btn.classList.toggle('liked');
                btn.setAttribute('aria-pressed', isLiked);
                btn.setAttribute('aria-label', isLiked ? 'Unlike this content' : 'Like this content');
            });
    };

    // Toggle Comments Section
    window.toggleSocialComments = function(interactionId) {
        const section = document.getElementById('comments-' + interactionId);
        if (!section) return;

        const isExpanding = !section.classList.contains('active');

        if (isExpanding) {
            section.classList.add('active');
            const input = document.getElementById('comment-input-' + interactionId);
            if (input) input.focus();

            // Load comments
            const parts = interactionId.split('-');
            const targetType = parts[0];
            const targetId = parts.slice(1).join('-');
            fetchSocialComments(interactionId, targetType, targetId);
        } else {
            section.classList.remove('active');
        }

        // Update aria-expanded on all buttons that control this section
        const container = document.getElementById('social-' + interactionId);
        if (container) {
            const toggleButtons = container.querySelectorAll('[aria-controls="comments-' + interactionId + '"]');
            toggleButtons.forEach(function(btn) {
                btn.setAttribute('aria-expanded', isExpanding ? 'true' : 'false');
            });
        }
    };

    // Fetch Comments
    window.fetchSocialComments = function(interactionId, targetType, targetId) {
        const list = document.getElementById('comments-list-' + interactionId);
        if (!list) return;

        list.innerHTML = '<p class="civic-social-status-message" aria-live="polite">Loading comments...</p>';

        const formData = new FormData();
        formData.append('action', 'fetch_comments');
        formData.append('target_type', targetType);
        formData.append('target_id', targetId);

        fetch(BASE_PATH + '/api/social/comments', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.comments && data.comments.length > 0) {
                    list.innerHTML = data.comments.map(c => `
                        <article class="civic-comment-item">
                            <img src="${escapeHtml(c.author_avatar || '/assets/img/defaults/default_avatar.webp')}"
                                 class="civic-comment-avatar"
                                 alt="${escapeHtml(c.author_name || 'Unknown')}'s profile picture">
                            <div class="civic-comment-bubble">
                                <p class="civic-comment-author">${escapeHtml(c.author_name || 'Unknown')}</p>
                                <p class="civic-comment-text">${escapeHtml(c.content)}</p>
                            </div>
                        </article>
                    `).join('');
                } else {
                    list.innerHTML = '<p class="civic-social-status-message">No comments yet. Be the first!</p>';
                }
            })
            .catch(() => {
                list.innerHTML = '<p class="civic-social-status-message civic-social-error" role="alert">Error loading comments</p>';
            });
    };

    // Submit Comment
    window.submitSocialComment = function(interactionId, targetType, targetId) {
        const input = document.getElementById('comment-input-' + interactionId);
        const content = input ? input.value.trim() : '';
        if (!content) return;

        input.disabled = true;

        const formData = new FormData();
        formData.append('action', 'submit_comment');
        formData.append('target_type', targetType);
        formData.append('target_id', targetId);
        formData.append('content', content);

        fetch(BASE_PATH + '/api/social/comments', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                input.disabled = false;
                if (data.status === 'success') {
                    input.value = '';
                    fetchSocialComments(interactionId, targetType, targetId);
                    showSocialToast('Comment posted!');

                    // Update count
                    const countEl = document.getElementById('comments-count-' + interactionId);
                    if (countEl) {
                        const current = parseInt(countEl.textContent) || 0;
                        countEl.textContent = current + 1;
                    }
                } else {
                    showSocialToast(data.error || 'Failed to post comment');
                }
            })
            .catch(() => {
                input.disabled = false;
                showSocialToast('Failed to post comment');
            });
    };

    // Share Modal
    let lastFocusedElement = null;

    window.openShareModal = function(targetType, targetId) {
        currentShareType = targetType;
        currentShareId = targetId;

        // Store the currently focused element to restore focus on close
        lastFocusedElement = document.activeElement;

        const modal = document.getElementById('civic-share-modal');
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');

        // Focus the first interactive element in the modal
        const firstButton = modal.querySelector('button');
        if (firstButton) {
            firstButton.focus();
        }

        // Trap focus within modal
        modal.addEventListener('keydown', trapFocus);
    };

    window.closeShareModal = function() {
        const modal = document.getElementById('civic-share-modal');
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        modal.removeEventListener('keydown', trapFocus);

        // Restore focus to the element that opened the modal
        if (lastFocusedElement) {
            lastFocusedElement.focus();
            lastFocusedElement = null;
        }
    };

    // Focus trap for modal accessibility
    function trapFocus(e) {
        if (e.key === 'Escape') {
            closeShareModal();
            return;
        }

        if (e.key !== 'Tab') return;

        const modal = document.getElementById('civic-share-modal');
        const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (e.shiftKey && document.activeElement === firstElement) {
            e.preventDefault();
            lastElement.focus();
        } else if (!e.shiftKey && document.activeElement === lastElement) {
            e.preventDefault();
            firstElement.focus();
        }
    }

    window.shareToFeedFromModal = function() {
        if (!IS_LOGGED_IN) {
            showSocialToast('Please sign in to share');
            closeShareModal();
            return;
        }

        const formData = new FormData();
        formData.append('action', 'share_to_feed');
        formData.append('parent_type', currentShareType);
        formData.append('parent_id', currentShareId);

        fetch(BASE_PATH + '/api/social/share', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                closeShareModal();
                if (data.status === 'success') {
                    showSocialToast('Shared to your feed!');
                } else {
                    showSocialToast(data.error || 'Share failed');
                }
            })
            .catch(() => {
                closeShareModal();
                showSocialToast('Share failed');
            });
    };

    window.copyShareLink = function() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            closeShareModal();
            showSocialToast('Link copied to clipboard!');
        }).catch(() => {
            closeShareModal();
            showSocialToast('Failed to copy link');
        });
    };

    // Utility
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Close modal on outside click
    document.getElementById('civic-share-modal')?.addEventListener('click', function(e) {
        if (e.target === this) closeShareModal();
    });
})();
</script>
<?php endif; ?>
