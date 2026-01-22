/**
 * CivicOne Profile Show Page - Social Interactions
 *
 * Features:
 * - Like/unlike posts
 * - Toggle comments visibility
 * - Fetch and render comments with nested replies
 * - Emoji reactions on comments
 * - Edit/delete comments
 * - Reply to comments
 * - @mention support
 *
 * WCAG 2.1 AA Compliant
 * Progressive enhancement - works without JS for basic viewing
 */

(function() {
    'use strict';

    // ==================================================
    // Global State
    // ==================================================

    let IS_LOGGED_IN = false; // Will be set from PHP
    let availableReactions = [];
    let currentTargetType = '';
    let currentTargetId = 0;

    // ==================================================
    // Initialize
    // ==================================================

    function init(isLoggedIn) {
        IS_LOGGED_IN = isLoggedIn;
        console.log('CivicOne Profile Show initialized (GOV.UK compliant)');
    }

    // ==================================================
    // Toast Notifications
    // ==================================================

    function showToast(message) {
        const toast = document.getElementById('civic-toast');
        if (!toast) return;

        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // ==================================================
    // Like/Unlike Posts
    // ==================================================

    function toggleLike(type, id, btn) {
        if (!IS_LOGGED_IN) {
            alert('Please log in to like posts.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'toggle_like');
        formData.append('target_type', type);
        formData.append('target_id', id);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                const countEl = btn.querySelector('.like-count');
                const icon = btn.querySelector('i');
                countEl.textContent = data.likes_count;

                if (data.status === 'liked') {
                    btn.classList.add('liked');
                    icon.className = 'fa-solid fa-heart';
                } else {
                    btn.classList.remove('liked');
                    icon.className = 'fa-regular fa-heart';
                }
            });
    }

    // ==================================================
    // Toggle Comments Visibility
    // ==================================================

    function toggleComments(type, id) {
        const section = document.getElementById(`comments-section-${type}-${id}`);
        if (!section) return;

        if (section.style.display === 'none' || !section.style.display) {
            section.style.display = 'block';
            fetchComments(type, id);
        } else {
            section.style.display = 'none';
        }
    }

    // ==================================================
    // Fetch Comments
    // ==================================================

    function fetchComments(type, id) {
        const section = document.getElementById(`comments-section-${type}-${id}`);
        const list = section.querySelector('.comments-list');
        list.innerHTML = '<div class="civicone-body-s civic-loading-message">Loading...</div>';

        currentTargetType = type;
        currentTargetId = id;

        const formData = new FormData();
        formData.append('action', 'fetch_comments');
        formData.append('target_type', type);
        formData.append('target_id', id);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.available_reactions) {
                    availableReactions = data.available_reactions;
                }
                if (data.status === 'success' && data.comments && data.comments.length > 0) {
                    list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');
                } else {
                    list.innerHTML = '<div class="civicone-body-s civic-empty-message">No comments yet. Be the first!</div>';
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                list.innerHTML = '<div class="civicone-body-s civic-error-message">Error loading comments</div>';
            });
    }

    // ==================================================
    // Render Comment (Recursive for Nested Replies)
    // ==================================================

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderComment(c, depth) {
        const marginLeft = depth * 40;
        const isEdited = c.is_edited ? '<span class="civic-comment-edited"> (edited)</span>' : '';
        const ownerActions = c.is_owner ? `
            <span onclick="window.CivicProfile.editComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'").replace(/\n/g, "\\n")}')"
                  class="civic-comment-action"
                  title="Edit"
                  tabindex="0"
                  role="button"
                  aria-label="Edit comment">✏️</span>
            <span onclick="window.CivicProfile.deleteComment(${c.id})"
                  class="civic-comment-action"
                  title="Delete"
                  tabindex="0"
                  role="button"
                  aria-label="Delete comment">🗑️</span>
        ` : '';

        // Reactions
        const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
            const isActive = (c.user_reactions || []).includes(emoji);
            return `<span class="civic-reaction ${isActive ? 'active' : ''}"
                          onclick="window.CivicProfile.toggleReaction(${c.id}, '${emoji}')"
                          role="button"
                          tabindex="0">${emoji} ${count}</span>`;
        }).join('');

        // Reaction picker
        const reactionPicker = IS_LOGGED_IN ? `
            <div class="civic-reaction-picker">
                <span class="civic-reaction"
                      onclick="window.CivicProfile.toggleReactionPicker(${c.id})"
                      role="button"
                      tabindex="0"
                      aria-label="Add reaction">+</span>
                <div class="civic-reaction-picker-menu" id="picker-${c.id}">
                    ${availableReactions.map(e => `<span onclick="window.CivicProfile.toggleReaction(${c.id}, '${e}')" role="button" tabindex="0">${e}</span>`).join('')}
                </div>
            </div>
        ` : '';

        const replyBtn = IS_LOGGED_IN ? `<span onclick="window.CivicProfile.showReplyForm(${c.id})" role="button" tabindex="0">Reply</span>` : '';

        const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

        // Highlight @mentions
        const contentHtml = escapeHtml(c.content).replace(/@(\w+)/g, '<span class="civic-mention">@$1</span>');

        return `
            <div class="civic-comment civic-comment--depth-${depth}" id="comment-${c.id}">
                <img src="${c.author_avatar}" class="civic-comment-avatar" alt="">
                <div class="civic-comment-bubble">
                    <div class="civic-comment-author">${escapeHtml(c.author_name)}${isEdited} ${ownerActions}</div>
                    <div class="civic-comment-text">${contentHtml}</div>
                    <div class="civic-comment-meta">
                        ${replyBtn}
                    </div>
                    <div class="civic-reactions">
                        ${reactions}
                        ${reactionPicker}
                    </div>
                    <div class="civic-reply-form" id="reply-form-${c.id}">
                        <div class="civic-reply-form-wrapper">
                            <input type="text"
                                   class="civic-reply-input civicone-input"
                                   placeholder="Write a reply..."
                                   onkeydown="if(event.key === 'Enter') window.CivicProfile.submitReply(${c.id}, this)">
                            <button class="civicone-button civic-comment-submit"
                                    onclick="window.CivicProfile.submitReply(${c.id}, this.previousElementSibling)">Reply</button>
                        </div>
                    </div>
                </div>
            </div>
            ${replies}
        `;
    }

    // ==================================================
    // Reaction Picker Toggle
    // ==================================================

    function toggleReactionPicker(commentId) {
        const picker = document.getElementById(`picker-${commentId}`);
        if (picker) {
            picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
        }
    }

    // ==================================================
    // Toggle Reaction on Comment
    // ==================================================

    function toggleReaction(commentId, emoji) {
        if (!IS_LOGGED_IN) {
            alert('Please log in to react.');
            return;
        }

        const picker = document.getElementById(`picker-${commentId}`);
        if (picker) picker.style.display = 'none';

        const formData = new FormData();
        formData.append('action', 'toggle_reaction');
        formData.append('comment_id', commentId);
        formData.append('emoji', emoji);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchComments(currentTargetType, currentTargetId);
                }
            });
    }

    // ==================================================
    // Show Reply Form
    // ==================================================

    function showReplyForm(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        if (form) {
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            const input = form.querySelector('input');
            if (input) input.focus();
        }
    }

    // ==================================================
    // Submit Reply to Comment
    // ==================================================

    function submitReply(parentId, input) {
        const content = input.value.trim();
        if (!content) return;
        input.disabled = true;

        const formData = new FormData();
        formData.append('action', 'reply_comment');
        formData.append('target_type', currentTargetType);
        formData.append('target_id', currentTargetId);
        formData.append('parent_id', parentId);
        formData.append('content', content);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                input.disabled = false;
                input.value = '';
                if (data.status === 'success') {
                    fetchComments(currentTargetType, currentTargetId);
                    showToast('Reply posted!');
                } else if (data.error) {
                    alert(data.error);
                }
            });
    }

    // ==================================================
    // Edit Comment
    // ==================================================

    function editComment(commentId, currentContent) {
        const newContent = prompt('Edit your comment:', currentContent.replace(/\\n/g, '\n'));
        if (newContent === null || newContent.trim() === '' || newContent === currentContent) return;

        const formData = new FormData();
        formData.append('action', 'edit_comment');
        formData.append('comment_id', commentId);
        formData.append('content', newContent.trim());

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchComments(currentTargetType, currentTargetId);
                    showToast('Comment updated!');
                } else if (data.error) {
                    alert(data.error);
                }
            });
    }

    // ==================================================
    // Delete Comment
    // ==================================================

    function deleteComment(commentId) {
        if (!confirm('Delete this comment?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_comment');
        formData.append('comment_id', commentId);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchComments(currentTargetType, currentTargetId);
                    showToast('Comment deleted!');
                } else if (data.error) {
                    alert(data.error);
                }
            });
    }

    // ==================================================
    // Submit Top-Level Comment
    // ==================================================

    function submitComment(input, type, id) {
        const content = input.value.trim();
        if (!content) return;
        input.disabled = true;

        currentTargetType = type;
        currentTargetId = id;

        const formData = new FormData();
        formData.append('action', 'submit_comment');
        formData.append('target_type', type);
        formData.append('target_id', id);
        formData.append('content', content);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                input.disabled = false;
                if (data.status === 'success') {
                    input.value = '';
                    fetchComments(type, id);
                    showToast('Comment posted!');

                    // Update comment count
                    const countEl = document.querySelector(`#post-${id} .comment-count`);
                    if (countEl) countEl.textContent = parseInt(countEl.textContent) + 1;
                }
            });
    }

    // ==================================================
    // Public API
    // ==================================================

    window.CivicProfile = {
        init: init,
        toggleLike: toggleLike,
        toggleComments: toggleComments,
        submitComment: submitComment,
        editComment: editComment,
        deleteComment: deleteComment,
        submitReply: submitReply,
        showReplyForm: showReplyForm,
        toggleReaction: toggleReaction,
        toggleReactionPicker: toggleReactionPicker
    };

})();
