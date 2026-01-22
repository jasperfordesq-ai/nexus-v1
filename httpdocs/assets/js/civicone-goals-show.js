/**
 * CivicOne Goals Show - Goal Detail Page Interactions
 * WCAG 2.1 AA Compliant
 * Features: Offline indicator, social interactions, comments, likes, reactions
 */

(function() {
    'use strict';

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
    document.querySelectorAll('.glass-pill-btn, button').forEach(btn => {
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

})();

// ============================================
// MASTER PLATFORM SOCIAL MEDIA MODULE
// ============================================
window.initGoalSocial = function(config) {
    const {goalId, isLoggedIn, isLiked: initialIsLiked, apiBase, basePath} = config;
    let isLiked = initialIsLiked;
    let commentsLoaded = false;
    let availableReactions = [];

    const API_BASE = apiBase;

    // Unique function names to avoid conflict with social-interactions.js
    window.goalToggleLike = async function() {
        if (!isLoggedIn) {
            window.location.href = basePath + '/login';
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
        const isHidden = section.style.display === 'none';

        section.style.display = isHidden ? 'block' : 'none';

        if (isHidden && !commentsLoaded) {
            loadComments();
        }
    };

    async function loadComments() {
        const list = document.getElementById('comments-list');
        list.innerHTML = '<p style="text-align: center; color: var(--text-muted);">Loading comments...</p>';

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
                list.innerHTML = '<p style="text-align: center; color: var(--text-muted);">Failed to load comments.</p>';
                return;
            }

            const data = await response.json();

            if (data.error) {
                list.innerHTML = '<p style="text-align: center; color: var(--text-muted);">Failed to load comments.</p>';
                return;
            }

            commentsLoaded = true;
            availableReactions = data.available_reactions || [];

            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<p style="text-align: center; color: var(--text-muted); padding: 20px;">No comments yet. Be the first to comment!</p>';
                return;
            }

            list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');

        } catch (err) {
            console.error('Load comments error:', err);
            list.innerHTML = '<p style="text-align: center; color: var(--text-muted);">Failed to load comments.</p>';
        }
    }

    function renderComment(c, depth) {
        const indent = depth * 20;
        const isEdited = c.is_edited ? '<span style="font-size: 0.7rem; color: var(--text-muted);"> (edited)</span>' : '';
        const ownerActions = c.is_owner ? `
            <span onclick="goalEditComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'")}')" style="cursor: pointer; margin-left: 10px;" title="Edit">✏️</span>
            <span onclick="goalDeleteComment(${c.id})" style="cursor: pointer; margin-left: 5px;" title="Delete">🗑️</span>
        ` : '';

        const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
            const isUserReaction = (c.user_reactions || []).includes(emoji);
            return `<span onclick="goalToggleReaction(${c.id}, '${emoji}')" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 0.8rem; background: ${isUserReaction ? 'rgba(219, 39, 119, 0.2)' : 'var(--pill-bg)'}; border: 1px solid ${isUserReaction ? 'rgba(219, 39, 119, 0.4)' : 'var(--glass-border)'};">${emoji} ${count}</span>`;
        }).join(' ');

        const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

        return `
            <div style="margin-left: ${indent}px; padding: 15px; margin-bottom: 10px; background: var(--pill-bg); border-radius: 12px; border: 1px solid var(--glass-border);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <img src="${c.avatar || '/assets/img/defaults/default_avatar.webp'}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" loading="lazy">
                    <div>
                        <strong style="color: var(--text-color);">${escapeHtml(c.author_name)}</strong>${isEdited}
                        <div style="font-size: 0.75rem; color: var(--text-muted);">${c.time_ago}</div>
                    </div>
                    ${ownerActions}
                </div>
                <div id="content-${c.id}" style="color: var(--text-color); margin-bottom: 10px;">${escapeHtml(c.content)}</div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    ${reactions}
                    <span onclick="goalShowReplyForm(${c.id})" style="cursor: pointer; color: var(--accent-color); font-size: 0.85rem;">↩️ Reply</span>
                </div>
                <div id="reply-form-${c.id}" style="display: none; margin-top: 10px;">
                    <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--pill-bg); color: var(--text-color);">
                    <button onclick="goalSubmitReply(${c.id})" class="glass-pill-btn btn-primary" style="margin-top: 8px; padding: 6px 12px; font-size: 0.85rem;">Reply</button>
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
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
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
            document.getElementById(`reply-form-${parentId}`).style.display = 'none';
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
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" style="flex: 1; min-width: 200px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--pill-bg); color: var(--text-color);">
                <button onclick="goalSaveEdit(${commentId})" class="glass-pill-btn btn-primary" style="padding: 6px 12px;">Save</button>
                <button onclick="goalCancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" class="glass-pill-btn btn-secondary" style="padding: 6px 12px;">Cancel</button>
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
};
