/**
 * MASTER PLATFORM SOCIAL MEDIA MODULE - Unified JavaScript Library
 * =================================================================
 * Central social interaction library for ALL platform layouts:
 * - Modern Layout
 * - Platform Social Layout
 * - CivicOne Layout
 * - Future layouts
 *
 * This file provides core functionality for:
 * - Like/Unlike (toggle_like)
 * - Comments (submit, fetch, delete, edit, reply)
 * - Reactions (emoji reactions on comments)
 * - Repost/Share (share_repost)
 * - Delete Post (admin/owner)
 * - @Mention search and autocomplete
 * - Feed loading with pagination
 * - Post creation
 *
 * API Endpoints (centralized):
 * - POST /api/social/like
 * - POST /api/social/comments
 * - POST /api/social/reply
 * - POST /api/social/edit-comment
 * - POST /api/social/delete-comment
 * - POST /api/social/reaction
 * - POST /api/social/share
 * - POST /api/social/delete
 * - POST /api/social/mention-search
 * - POST /api/social/feed
 * - POST /api/social/create-post
 *
 * Usage:
 * 1. Include this script in your layout footer
 * 2. Set window.SocialInteractions.isLoggedIn before using (required)
 * 3. Optionally configure features via window.SocialInteractions.config
 *
 * Example:
 *   window.SocialInteractions = window.SocialInteractions || {};
 *   window.SocialInteractions.isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
 *   window.SocialInteractions.config = { enableReactions: true, enableReplies: true };
 */

(function(global) {
    'use strict';

    // ============================================
    // CONFIGURATION & STATE
    // ============================================
    const SocialInteractions = global.SocialInteractions || {};

    // API Base URL - centralized endpoints for all layouts
    // Use window.BASE_URL for tenant-aware routing
    SocialInteractions.apiBase = (window.BASE_URL || '') + '/api/social';

    // Defaults (can be overridden by layout)
    SocialInteractions.config = SocialInteractions.config || {
        // Colors
        likedColor: '#4f46e5',       // Indigo (Modern)
        unlikedColor: '#6b7280',     // Gray
        primaryColor: '#4f46e5',

        // CSS Variable fallbacks (for all platform layouts)
        useCssVariables: false,      // Set true to use var(--primary-color) etc.

        // Features
        enableHeartBurst: true,      // Heart emoji animation on like
        enableHaptics: true,         // Vibration feedback
        enableRipple: true,          // Ripple effect on like button

        // Toast
        toastDuration: 3000,

        // Enhanced comments (Modern features)
        enableReactions: true,
        enableReplies: true,
        enableMentions: true,
        enableEditDelete: true
    };

    // State for enhanced comment system
    SocialInteractions.state = {
        availableReactions: ['❤️', '👍', '😂', '😮', '😢', '😡'],
        currentCommentTargetType: '',
        currentCommentTargetId: 0
    };

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getColor(type) {
        const cfg = SocialInteractions.config;
        if (cfg.useCssVariables) {
            return type === 'liked' ? 'var(--primary-color)' : 'var(--text-muted)';
        }
        return type === 'liked' ? cfg.likedColor : cfg.unlikedColor;
    }

    /**
     * Show toast notification
     * Uses the modern Toast system if available, falls back to basic snackbar
     */
    SocialInteractions.showToast = function(message, type) {
        type = type || 'success';

        // Use modern Toast system if available
        if (typeof Toast !== 'undefined' && Toast[type]) {
            Toast[type](message);
            return;
        }

        // Fallback to basic snackbar
        let toast = document.getElementById("snackbar") || document.getElementById("toast");
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'snackbar';
            toast.style.cssText = `
                visibility: hidden;
                min-width: 250px;
                margin-left: -125px;
                background-color: #333;
                color: #fff;
                text-align: center;
                border-radius: 8px;
                padding: 16px;
                position: fixed;
                z-index: 10001;
                left: 50%;
                bottom: 30px;
                font-size: 14px;
            `;
            document.body.appendChild(toast);
        }

        toast.innerText = message;
        toast.className = "show";
        toast.style.visibility = "visible";
        toast.style.opacity = "1";

        setTimeout(function() {
            toast.className = toast.className.replace("show", "");
            toast.style.visibility = "hidden";
            toast.style.opacity = "0";
        }, SocialInteractions.config.toastDuration);
    };

    /**
     * Show error toast
     */
    SocialInteractions.showError = function(message) {
        SocialInteractions.showToast(message, 'error');
    };

    // ============================================
    // HEART BURST ANIMATION
    // ============================================

    SocialInteractions.createHeartBurst = function(btn) {
        if (!SocialInteractions.config.enableHeartBurst) return;

        const rect = btn.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const hearts = ['❤️', '💜', '💙', '🧡', '💗'];

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

    // Inject heartBurst keyframes if not present
    (function injectHeartBurstCSS() {
        if (document.getElementById('social-heart-burst-css')) return;
        const style = document.createElement('style');
        style.id = 'social-heart-burst-css';
        style.textContent = `
            @keyframes heartBurst {
                0% { transform: translate(0, 0) scale(1); opacity: 1; }
                100% { transform: translate(var(--tx), var(--ty)) scale(0.5); opacity: 0; }
            }
            @keyframes like-pop {
                0% { transform: scale(1); }
                50% { transform: scale(1.4); }
                100% { transform: scale(1); }
            }
            .like-pop { animation: like-pop 0.4s ease; }
            .rippling { animation: ripple 0.6s ease-out; }
            @keyframes ripple {
                0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
                100% { box-shadow: 0 0 0 15px rgba(99, 102, 241, 0); }
            }
        `;
        document.head.appendChild(style);
    })();

    // ============================================
    // LIKE FUNCTIONALITY
    // ============================================

    function revertLikeUI(icon, btn, wasLiked) {
        // Check if using CSS-class-based styling (pill or feed-action buttons)
        const usesCssClasses = btn.classList.contains('feed-action-pill') || btn.classList.contains('feed-action-btn');

        if (wasLiked) {
            icon.classList.remove("fa-regular");
            icon.classList.add("fa-solid");
            btn.classList.add("liked");
            if (!usesCssClasses) {
                // Only set inline styles for legacy buttons
                // eslint-disable-next-line no-restricted-syntax
                btn.style.color = getColor('liked');
                // eslint-disable-next-line no-restricted-syntax
                btn.style.fontWeight = "600";
            }
        } else {
            icon.classList.remove("fa-solid");
            icon.classList.add("fa-regular");
            btn.classList.remove("liked");
            if (!usesCssClasses) {
                // Only set inline styles for legacy buttons
                // eslint-disable-next-line no-restricted-syntax
                btn.style.color = getColor('unliked');
                // eslint-disable-next-line no-restricted-syntax
                btn.style.fontWeight = "normal";
            }
        }
    }

    // Debounce tracking for like buttons
    SocialInteractions._likeDebounce = {};

    /**
     * Toggle like on content
     * @param {HTMLElement} btn - The like button element
     * @param {string} type - Content type (post, listing, poll, goal, etc.)
     * @param {number|string} id - Content ID
     */
    SocialInteractions.toggleLike = function(btn, type, id) {
        if (!SocialInteractions.isLoggedIn) {
            // Redirect to login with a friendly message
            window.location.href = (window.BASE_URL || '') + '/login?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        const icon = btn.querySelector("i");
        if (!icon) return;

        // Debounce: prevent rapid clicks (500ms cooldown)
        const debounceKey = type + '-' + id;
        if (SocialInteractions._likeDebounce[debounceKey]) {
            return; // Ignore click if within debounce period
        }
        SocialInteractions._likeDebounce[debounceKey] = true;
        setTimeout(function() {
            delete SocialInteractions._likeDebounce[debounceKey];
        }, 500);

        const isLiked = icon.classList.contains("fa-solid");
        const cfg = SocialInteractions.config;
        // Check if using CSS-class-based styling (pill or feed-action buttons)
        // These button types use CSS classes for styling, not inline styles
        const usesCssClasses = btn.classList.contains('feed-action-pill') || btn.classList.contains('feed-action-btn');

        // Ripple effect
        if (cfg.enableRipple) {
            btn.classList.add('rippling');
            setTimeout(() => btn.classList.remove('rippling'), 600);
        }

        // Optimistic UI update
        if (isLiked) {
            // Unlike action
            icon.classList.remove("fa-solid");
            icon.classList.add("fa-regular");
            btn.classList.remove("liked");
            if (!usesCssClasses) {
                // Only set inline styles for legacy buttons that don't use CSS classes
                // eslint-disable-next-line no-restricted-syntax
                btn.style.color = getColor('unliked');
                // eslint-disable-next-line no-restricted-syntax
                btn.style.fontWeight = "normal";
            }
        } else {
            // Like action
            icon.classList.remove("fa-regular");
            icon.classList.add("fa-solid");
            btn.classList.add("liked");
            if (!usesCssClasses) {
                // Only set inline styles for legacy buttons that don't use CSS classes
                // eslint-disable-next-line no-restricted-syntax
                btn.style.color = getColor('liked');
                // eslint-disable-next-line no-restricted-syntax
                btn.style.fontWeight = "600";
            }

            // Heart burst animation
            SocialInteractions.createHeartBurst(btn);

            // Pop animation on icon
            icon.classList.add('like-pop');
            setTimeout(() => icon.classList.remove('like-pop'), 400);

            // Haptic feedback
            if (cfg.enableHaptics && navigator.vibrate) {
                navigator.vibrate([10, 50, 20]);
            }
        }

        // Send to centralized API endpoint
        fetch(SocialInteractions.apiBase + '/like', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target_type: type,
                target_id: id
            })
        })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.status === 'liked' || data.status === 'unliked') {
                // Update likes count display
                const countEl = btn.querySelector('.likes-count') || btn.parentElement.querySelector('.likes-count');
                if (countEl && data.likes_count !== undefined) {
                    countEl.textContent = data.likes_count > 0 ? data.likes_count : '';
                }
                // Also update pill-style label if present
                const labelEl = btn.querySelector('.like-label');
                if (labelEl && data.likes_count !== undefined) {
                    const count = data.likes_count;
                    const word = count === 1 ? 'Like' : 'Likes';
                    labelEl.textContent = count > 0 ? count + ' ' + word : word;
                }
                // Show toast feedback for like action
                if (data.status === 'liked') {
                    SocialInteractions.showToast('Liked!', 'success');
                }
            } else {
                // Revert UI on failure
                revertLikeUI(icon, btn, isLiked);
                SocialInteractions.showError('Failed to update like');
                console.error('Like failed:', data);
            }
        })
        .catch(err => {
            // Revert UI on error
            revertLikeUI(icon, btn, isLiked);
            SocialInteractions.showError('Connection error. Please try again.');
            console.error('Like error:', err);
        });
    };

    // ============================================
    // COMMENT SECTION TOGGLE
    // ============================================

    /**
     * Toggle comment section visibility
     * @param {string} type - Content type
     * @param {number|string} id - Content ID
     */
    SocialInteractions.toggleCommentSection = function(type, id) {
        const section = document.getElementById(`comments-section-${type}-${id}`);
        if (!section) return;

        // Support both class-based and style-based toggling
        const isHidden = section.classList.contains('fds-comments-section')
            ? !section.classList.contains('active')
            : window.getComputedStyle(section).display === 'none';

        if (isHidden) {
            // Show section
            if (section.classList.contains('fds-comments-section')) {
                section.classList.add('active');
            } else {
                // Legacy comment sections use inline display toggle
                section.classList.remove('hidden');
            }
            const input = section.querySelector("input");
            if (input) input.focus();
            SocialInteractions.fetchComments(type, id);
        } else {
            // Hide section
            if (section.classList.contains('fds-comments-section')) {
                section.classList.remove('active');
            } else {
                // Legacy comment sections use inline display toggle
                section.classList.add('hidden');
            }
        }
    };

    // ============================================
    // FETCH COMMENTS
    // ============================================

    /**
     * Render a single comment (basic version)
     */
    function renderCommentBasic(c) {
        return `
            <div class="comment-item" style="display:flex; gap:8px; margin-bottom:10px;">
                <img src="${escapeHtml(c.author_avatar)}" class="comment-avatar" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                <div class="comment-bubble" style="background:var(--comment-bg, rgba(243, 244, 246, 0.8)); backdrop-filter:blur(8px); padding:8px 12px; border-radius:18px; border: 1px solid var(--comment-border, transparent);">
                    <div class="comment-author" style="font-weight:600; font-size:13px; color:var(--comment-text, #1f2937);">${escapeHtml(c.author_name)}</div>
                    <div class="comment-text" style="font-size:14px; color:var(--comment-text, #1f2937);">${escapeHtml(c.content)}</div>
                </div>
            </div>`;
    }

    /**
     * Render a comment with enhanced features (replies, reactions, edit/delete)
     */
    function renderCommentEnhanced(c, depth) {
        depth = depth || 0;
        const indent = depth * 20;
        const cfg = SocialInteractions.config;
        const isEdited = c.is_edited ? '<span style="font-size: 0.7rem; color: #9ca3af;"> (edited)</span>' : '';

        // Owner actions (edit/delete)
        const ownerActions = (cfg.enableEditDelete && c.is_owner) ? `
            <span onclick="SocialInteractions.editComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'").replace(/\n/g, "\\n")}')" style="cursor: pointer; margin-left: 10px; color: #6b7280; font-size: 12px;" title="Edit">✏️</span>
            <span onclick="SocialInteractions.deleteComment(${c.id})" style="cursor: pointer; margin-left: 5px; color: #6b7280; font-size: 12px;" title="Delete">🗑️</span>
        ` : '';

        // Reactions display
        let reactions = '';
        if (cfg.enableReactions && c.reactions) {
            reactions = Object.entries(c.reactions).map(([emoji, count]) => {
                const isUserReaction = (c.user_reactions || []).includes(emoji);
                return `<span class="comment-reaction ${isUserReaction ? 'active' : ''}" onclick="SocialInteractions.toggleReaction(${c.id}, '${emoji}')" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 12px; background: ${isUserReaction ? 'rgba(99, 102, 241, 0.2)' : 'var(--reaction-bg, rgba(243, 244, 246, 0.8))'}; border: 1px solid ${isUserReaction ? 'rgba(99, 102, 241, 0.4)' : 'var(--reaction-border, rgba(229, 231, 235, 0.8))'}; margin-right: 4px;">${emoji} ${count}</span>`;
            }).join('');
        }

        // Reaction picker
        let reactionPicker = '';
        if (cfg.enableReactions && SocialInteractions.isLoggedIn) {
            const availableReactions = SocialInteractions.state.availableReactions;
            reactionPicker = `
                <div class="reaction-picker" style="display: inline-block; position: relative;">
                    <span class="reaction-add-btn" onclick="SocialInteractions.toggleReactionPicker(${c.id})" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 12px; background: var(--reaction-bg, rgba(243, 244, 246, 0.8)); border: 1px solid var(--reaction-border, rgba(229, 231, 235, 0.8));">+</span>
                    <div id="reaction-picker-${c.id}" class="reaction-picker-popup" style="display: none; position: absolute; bottom: 24px; left: 0; background: var(--picker-bg, white); border-radius: 20px; padding: 4px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); white-space: nowrap; z-index: 100; border: 1px solid var(--picker-border, transparent);">
                        ${availableReactions.map(emoji => `<span onclick="SocialInteractions.toggleReaction(${c.id}, '${emoji}')" style="cursor: pointer; padding: 4px; font-size: 18px; transition: transform 0.1s;" onmouseover="this.style.transform='scale(1.3)'" onmouseout="this.style.transform='scale(1)'">${emoji}</span>`).join('')}
                    </div>
                </div>
            `;
        }

        // Reply button
        const replyButton = (cfg.enableReplies && SocialInteractions.isLoggedIn)
            ? `<span class="comment-reply-link" onclick="SocialInteractions.showReplyForm(${c.id})" style="cursor: pointer; margin-left: 10px; color: var(--action-text, #6b7280); font-size: 12px;">Reply</span>`
            : '';

        // Nested replies
        const replies = (cfg.enableReplies && c.replies)
            ? c.replies.map(r => renderCommentEnhanced(r, depth + 1)).join('')
            : '';

        // Highlight @mentions
        let contentHtml = escapeHtml(c.content);
        if (cfg.enableMentions) {
            contentHtml = contentHtml.replace(/@(\w+)/g, '<span style="color: #4f46e5; font-weight: 600;">@$1</span>');
        }

        return `
            <div class="comment-item" style="margin-left: ${indent}px; margin-bottom: 12px;" id="comment-${c.id}">
                <div style="display: flex; gap: 8px;">
                    <img src="${escapeHtml(c.author_avatar)}" class="comment-avatar" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                    <div style="flex-grow: 1;">
                        <div class="comment-bubble" style="background: var(--comment-bg, rgba(243, 244, 246, 0.8)); backdrop-filter: blur(8px); padding: 8px 12px; border-radius: 18px; display: inline-block; max-width: 100%; border: 1px solid var(--comment-border, transparent);">
                            <div class="comment-author" style="font-weight: 600; font-size: 13px; color: var(--comment-text, #1f2937);">${escapeHtml(c.author_name)}${isEdited}</div>
                            <div class="comment-text" style="font-size: 14px; color: var(--comment-text, #1f2937); word-wrap: break-word;">${contentHtml}</div>
                        </div>
                        <div class="comment-actions" style="margin-top: 4px; display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
                            ${reactions}
                            ${reactionPicker}
                            ${replyButton}
                            ${ownerActions}
                        </div>
                        <div id="reply-form-${c.id}" class="comment-reply-form" style="display: none; margin-top: 8px;">
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" class="fds-input comment-reply-input" placeholder="Write a reply..." style="flex-grow: 1; border-radius: 20px; padding: 8px 12px; font-size: 13px; background: var(--comment-input-bg, #fff); color: var(--comment-text, #1f2937); border: 1px solid var(--comment-border, #e5e7eb);" onkeydown="if(event.key === 'Enter') SocialInteractions.submitReply(${c.id}, this)">
                                <button class="comment-reply-btn" onclick="SocialInteractions.submitReply(${c.id}, this.previousElementSibling)" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.9), rgba(139, 92, 246, 0.9)); color: white; border: none; border-radius: 20px; padding: 8px 16px; cursor: pointer; font-size: 13px;">Reply</button>
                            </div>
                        </div>
                    </div>
                </div>
                ${replies}
            </div>
        `;
    }

    /**
     * Fetch and display comments
     * @param {string} type - Content type
     * @param {number|string} id - Content ID
     */
    SocialInteractions.fetchComments = function(type, id) {
        const list = document.querySelector(`#comments-section-${type}-${id} .comments-list`);
        if (!list) return;
        list.innerHTML = '<div style="color:#6b7280; padding:10px;">Loading...</div>';

        // Store context for enhanced features
        SocialInteractions.state.currentCommentTargetType = type;
        SocialInteractions.state.currentCommentTargetId = id;

        fetch(SocialInteractions.apiBase + '/comments', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'fetch',
                target_type: type,
                target_id: id
            })
        })
        .then(res => res.json())
        .then(data => {
            // Store available reactions if provided
            if (data.available_reactions) {
                SocialInteractions.state.availableReactions = data.available_reactions;
            }

            if (data.status === 'success' && data.comments && data.comments.length > 0) {
                const cfg = SocialInteractions.config;
                const useEnhanced = cfg.enableReactions || cfg.enableReplies || cfg.enableEditDelete;

                if (useEnhanced) {
                    list.innerHTML = data.comments.map(c => renderCommentEnhanced(c, 0)).join('');
                } else {
                    list.innerHTML = data.comments.map(c => renderCommentBasic(c)).join('');
                }
            } else {
                list.innerHTML = '<div style="color:#9ca3af; padding:10px; font-size:14px;">No comments yet. Be the first!</div>';
            }
        })
        .catch(err => {
            list.innerHTML = '<div style="color:#ef4444; padding:10px; font-size:13px;">Error loading comments.</div>';
            console.error('Fetch comments error:', err);
        });
    };

    // ============================================
    // SUBMIT COMMENT
    // ============================================

    /**
     * Submit a new comment
     * @param {HTMLInputElement} input - The comment input field
     * @param {string} type - Content type
     * @param {number|string} id - Content ID
     */
    SocialInteractions.submitComment = function(input, type, id) {
        if (!SocialInteractions.isLoggedIn) {
            window.location.href = (window.BASE_URL || '') + '/login?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        const content = input.value.trim();
        if (!content) return;
        input.disabled = true;

        // Store context
        SocialInteractions.state.currentCommentTargetType = type;
        SocialInteractions.state.currentCommentTargetId = id;

        fetch(SocialInteractions.apiBase + '/comments', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'submit',
                target_type: type,
                target_id: id,
                content: content
            })
        })
        .then(res => res.json())
        .then(data => {
            input.disabled = false;
            if (data.status === 'success') {
                input.value = '';
                input.focus();
                // Refresh comments to show the new comment
                SocialInteractions.fetchComments(type, id);
                SocialInteractions.showToast("Comment posted!", 'success');
            } else {
                SocialInteractions.showError(data.error || data.message || 'Failed to post comment');
            }
        })
        .catch(err => {
            input.disabled = false;
            console.error('Submit comment error:', err);
            SocialInteractions.showError('Failed to post comment. Please try again.');
        });
    };

    // ============================================
    // ENHANCED COMMENT FEATURES
    // ============================================

    /**
     * Toggle reaction picker visibility
     */
    SocialInteractions.toggleReactionPicker = function(commentId) {
        const picker = document.getElementById(`reaction-picker-${commentId}`);
        if (picker) {
            picker.classList.toggle('hidden');
        }
    };

    /**
     * Toggle reaction on a comment
     */
    SocialInteractions.toggleReaction = function(commentId, emoji) {
        if (!SocialInteractions.isLoggedIn) {
            window.location.href = (window.BASE_URL || '') + '/login?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        // Hide picker
        const picker = document.getElementById(`reaction-picker-${commentId}`);
        if (picker) picker.classList.add('hidden');

        fetch(SocialInteractions.apiBase + '/reaction', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                comment_id: commentId,
                emoji: emoji
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' || data.status === 'added' || data.status === 'removed') {
                // Refresh comments
                SocialInteractions.fetchComments(
                    SocialInteractions.state.currentCommentTargetType,
                    SocialInteractions.state.currentCommentTargetId
                );
            }
        });
    };

    /**
     * Show reply form for a comment
     */
    SocialInteractions.showReplyForm = function(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        if (form) {
            form.classList.toggle('hidden');
            const input = form.querySelector('input');
            if (input && !form.classList.contains('hidden')) input.focus();
        }
    };

    /**
     * Submit a reply to a comment
     */
    SocialInteractions.submitReply = function(parentId, input) {
        const content = input.value.trim();
        if (!content) return;
        input.disabled = true;

        fetch(SocialInteractions.apiBase + '/reply', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target_type: SocialInteractions.state.currentCommentTargetType,
                target_id: SocialInteractions.state.currentCommentTargetId,
                parent_id: parentId,
                content: content
            })
        })
        .then(res => res.json())
        .then(data => {
            input.disabled = false;
            input.value = '';
            if (data.status === 'success') {
                SocialInteractions.fetchComments(
                    SocialInteractions.state.currentCommentTargetType,
                    SocialInteractions.state.currentCommentTargetId
                );
                SocialInteractions.showToast("Reply posted!", 'success');
            } else if (data.error) {
                SocialInteractions.showError(data.error);
            }
        });
    };

    /**
     * Edit a comment
     */
    SocialInteractions.editComment = function(commentId, currentContent) {
        const newContent = prompt("Edit your comment:", currentContent.replace(/\\n/g, "\n"));
        if (newContent === null || newContent.trim() === '' || newContent === currentContent) return;

        fetch(SocialInteractions.apiBase + '/edit-comment', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                comment_id: commentId,
                content: newContent.trim()
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                SocialInteractions.fetchComments(
                    SocialInteractions.state.currentCommentTargetType,
                    SocialInteractions.state.currentCommentTargetId
                );
                SocialInteractions.showToast("Comment updated!", 'success');
            } else if (data.error) {
                SocialInteractions.showError(data.error);
            }
        });
    };

    /**
     * Delete a comment
     */
    SocialInteractions.deleteComment = function(commentId) {
        if (!confirm("Delete this comment?")) return;

        fetch(SocialInteractions.apiBase + '/delete-comment', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                comment_id: commentId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                SocialInteractions.fetchComments(
                    SocialInteractions.state.currentCommentTargetType,
                    SocialInteractions.state.currentCommentTargetId
                );
                SocialInteractions.showToast("Comment deleted!", 'success');
            } else if (data.error) {
                SocialInteractions.showError(data.error);
            }
        });
    };

    // ============================================
    // REPOST / SHARE
    // ============================================

    /**
     * Repost content to user's feed
     * @param {string} type - Content type (post, listing, etc.)
     * @param {number|string} id - Content ID
     * @param {string} author - Original author name
     */
    SocialInteractions.repostToFeed = function(type, id, author) {
        if (!SocialInteractions.isLoggedIn) {
            window.location.href = (window.BASE_URL || '') + '/login?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        if (!confirm("Share this post to your feed?")) return;

        fetch(SocialInteractions.apiBase + '/share', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                parent_id: id,
                parent_type: type || 'post',
                original_author: author
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                SocialInteractions.showToast("Shared to your feed!", 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                SocialInteractions.showError("Share failed: " + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Share error:', err);
            SocialInteractions.showError("Share failed. Please try again.");
        });
    };

    // ============================================
    // DELETE POST (Admin)
    // ============================================

    /**
     * Delete a post (admin/owner function)
     * @param {string} type - Content type
     * @param {number|string} id - Content ID
     */
    SocialInteractions.deletePost = function(type, id) {
        if (!confirm("Delete this post?")) return;

        fetch(SocialInteractions.apiBase + '/delete', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target_type: type,
                target_id: id
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'deleted' || data.status === 'success') {
                SocialInteractions.showToast("Post deleted!", 'success');
                setTimeout(() => location.reload(), 500);
            } else if (data.error) {
                SocialInteractions.showError("Delete failed: " + data.error);
            }
        });
    };

    // ============================================
    // @MENTION SEARCH / AUTOCOMPLETE
    // ============================================

    /**
     * Search for users to mention
     * @param {string} query - Search query
     * @param {function} callback - Callback with results
     */
    SocialInteractions.searchMentions = function(query, callback) {
        if (!query || query.length < 2) {
            callback([]);
            return;
        }

        fetch(SocialInteractions.apiBase + '/mention-search', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                query: query
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.users) {
                callback(data.users);
            } else {
                callback([]);
            }
        })
        .catch(() => callback([]));
    };

    /**
     * Initialize @mention autocomplete on an input
     * @param {HTMLInputElement|HTMLTextAreaElement} input - Input element
     */
    SocialInteractions.initMentionAutocomplete = function(input) {
        if (!SocialInteractions.config.enableMentions || !input) return;

        let dropdown = null;
        let mentionStart = -1;

        input.addEventListener('input', function(e) {
            const text = this.value;
            const cursorPos = this.selectionStart;

            // Find @ symbol before cursor
            const beforeCursor = text.substring(0, cursorPos);
            const atIndex = beforeCursor.lastIndexOf('@');

            if (atIndex >= 0) {
                const query = beforeCursor.substring(atIndex + 1);
                // Check if there's no space in query (still typing username)
                if (query.length >= 2 && !query.includes(' ')) {
                    mentionStart = atIndex;
                    SocialInteractions.searchMentions(query, function(users) {
                        if (users.length > 0) {
                            showMentionDropdown(input, users);
                        } else {
                            hideMentionDropdown();
                        }
                    });
                } else {
                    hideMentionDropdown();
                }
            } else {
                hideMentionDropdown();
            }
        });

        function showMentionDropdown(input, users) {
            hideMentionDropdown();

            dropdown = document.createElement('div');
            dropdown.className = 'mention-dropdown';
            dropdown.style.cssText = `
                position: absolute;
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
                min-width: 200px;
            `;

            users.forEach(user => {
                const item = document.createElement('div');
                item.style.cssText = `
                    padding: 8px 12px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                `;
                item.innerHTML = `
                    <img src="${escapeHtml(user.avatar || '/assets/img/defaults/default_avatar.png')}"
                         style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                    <span style="font-weight: 600;">${escapeHtml(user.name)}</span>
                    <span style="color: #6b7280; font-size: 12px;">@${escapeHtml(user.username)}</span>
                `;
                // eslint-disable-next-line no-restricted-syntax -- dynamic hover effect for mention dropdown
                item.addEventListener('mouseover', () => item.style.background = '#f3f4f6');
                // eslint-disable-next-line no-restricted-syntax -- dynamic hover effect for mention dropdown
                item.addEventListener('mouseout', () => item.style.background = 'white');
                item.addEventListener('click', () => {
                    insertMention(input, user.username);
                    hideMentionDropdown();
                });
                dropdown.appendChild(item);
            });

            // Position dropdown below input
            const rect = input.getBoundingClientRect();
            dropdown.style.left = rect.left + 'px';
            dropdown.style.top = (rect.bottom + 5) + 'px';
            document.body.appendChild(dropdown);
        }

        function hideMentionDropdown() {
            if (dropdown) {
                dropdown.remove();
                dropdown = null;
            }
        }

        function insertMention(input, username) {
            const text = input.value;
            const cursorPos = input.selectionStart;
            const beforeCursor = text.substring(0, mentionStart);
            const afterCursor = text.substring(cursorPos);

            input.value = beforeCursor + '@' + username + ' ' + afterCursor;
            const newPos = mentionStart + username.length + 2;
            input.setSelectionRange(newPos, newPos);
            input.focus();
        }

        // Close dropdown on click outside
        document.addEventListener('click', function(e) {
            if (dropdown && !dropdown.contains(e.target) && e.target !== input) {
                hideMentionDropdown();
            }
        });
    };

    // ============================================
    // VIEW LIKERS MODAL
    // ============================================

    /**
     * Show modal with users who liked content
     * @param {string} type - Content type (post, listing, etc.)
     * @param {number|string} id - Content ID
     */
    SocialInteractions.showLikers = function(type, id) {
        // Create or get existing modal
        let modal = document.getElementById('likers-modal');
        if (!modal) {
            modal = createLikersModal();
        }

        const modalContent = modal.querySelector('.likers-modal-body');
        const modalTitle = modal.querySelector('.likers-modal-title');
        const loadMoreBtn = modal.querySelector('.likers-load-more');

        // Reset state
        modalTitle.textContent = 'Likes';
        modalContent.innerHTML = '<div class="likers-loading">Loading...</div>';
        loadMoreBtn.classList.add('hidden');
        modal.dataset.type = type;
        modal.dataset.id = id;
        modal.dataset.page = 1;

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Fetch likers
        fetchLikers(type, id, 1, modalContent, loadMoreBtn, modalTitle);
    };

    /**
     * Create the likers modal element
     */
    function createLikersModal() {
        const modal = document.createElement('div');
        modal.id = 'likers-modal';
        modal.className = 'likers-modal';
        modal.innerHTML = `
            <div class="likers-modal-overlay" onclick="SocialInteractions.closeLikersModal()"></div>
            <div class="likers-modal-container">
                <div class="likers-modal-header">
                    <h3 class="likers-modal-title">Likes</h3>
                    <button class="likers-modal-close" onclick="SocialInteractions.closeLikersModal()" aria-label="Close">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="likers-modal-body"></div>
                <div class="likers-modal-footer">
                    <button class="likers-load-more" onclick="SocialInteractions.loadMoreLikers()" style="display: none;">Load More</button>
                </div>
            </div>
        `;

        // Inject styles
        injectLikersModalStyles();

        document.body.appendChild(modal);
        return modal;
    }

    /**
     * Inject CSS styles for the likers modal
     */
    function injectLikersModalStyles() {
        if (document.getElementById('likers-modal-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'likers-modal-styles';
        styles.textContent = `
            .likers-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 10000;
                align-items: center;
                justify-content: center;
            }
            .likers-modal.active {
                display: flex;
            }
            .likers-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
            }
            .likers-modal-container {
                position: relative;
                background: var(--modal-bg, #fff);
                border-radius: 16px;
                width: 90%;
                max-width: 400px;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: likers-modal-in 0.2s ease-out;
            }
            @keyframes likers-modal-in {
                from { opacity: 0; transform: scale(0.95) translateY(10px); }
                to { opacity: 1; transform: scale(1) translateY(0); }
            }
            .likers-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border-bottom: 1px solid var(--border-color, #e5e7eb);
            }
            .likers-modal-title {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                color: var(--text-primary, #1f2937);
            }
            .likers-modal-close {
                background: none;
                border: none;
                cursor: pointer;
                padding: 4px;
                color: var(--text-muted, #6b7280);
                border-radius: 8px;
                transition: background 0.2s, color 0.2s;
            }
            .likers-modal-close:hover {
                background: var(--hover-bg, #f3f4f6);
                color: var(--text-primary, #1f2937);
            }
            .likers-modal-body {
                flex: 1;
                overflow-y: auto;
                padding: 8px 0;
                min-height: 100px;
            }
            .likers-modal-footer {
                padding: 12px 20px;
                border-top: 1px solid var(--border-color, #e5e7eb);
                text-align: center;
            }
            .likers-loading {
                text-align: center;
                padding: 40px 20px;
                color: var(--text-muted, #6b7280);
            }
            .likers-empty {
                text-align: center;
                padding: 40px 20px;
                color: var(--text-muted, #6b7280);
            }
            .likers-empty svg {
                width: 48px;
                height: 48px;
                margin-bottom: 12px;
                opacity: 0.5;
            }
            .liker-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 20px;
                transition: background 0.2s;
                cursor: pointer;
                text-decoration: none;
                color: inherit;
            }
            .liker-item:hover {
                background: var(--hover-bg, #f9fafb);
            }
            .liker-avatar {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                object-fit: cover;
                flex-shrink: 0;
            }
            .liker-info {
                flex: 1;
                min-width: 0;
            }
            .liker-name {
                font-weight: 600;
                font-size: 15px;
                color: var(--text-primary, #1f2937);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .liker-date {
                font-size: 13px;
                color: var(--text-muted, #6b7280);
            }
            .likers-load-more {
                background: var(--primary-color, #4f46e5);
                color: white;
                border: none;
                border-radius: 8px;
                padding: 10px 24px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: background 0.2s, transform 0.1s;
            }
            .likers-load-more:hover {
                background: var(--primary-hover, #4338ca);
            }
            .likers-load-more:active {
                transform: scale(0.98);
            }
            .likes-count-clickable {
                cursor: pointer;
                transition: opacity 0.2s;
            }
            .likes-count-clickable:hover {
                opacity: 0.7;
            }

            /* Dark mode styles for likers modal */
            [data-theme="dark"] .likers-modal-container,
            .dark .likers-modal-container {
                background: #1e293b;
                border: 1px solid rgba(139, 92, 246, 0.3);
            }
            [data-theme="dark"] .likers-modal-header,
            .dark .likers-modal-header {
                border-bottom-color: rgba(139, 92, 246, 0.2);
            }
            [data-theme="dark"] .likers-modal-title,
            .dark .likers-modal-title {
                color: #f1f5f9;
            }
            [data-theme="dark"] .likers-modal-close,
            .dark .likers-modal-close {
                color: #94a3b8;
            }
            [data-theme="dark"] .likers-modal-close:hover,
            .dark .likers-modal-close:hover {
                background: rgba(139, 92, 246, 0.2);
                color: #f1f5f9;
            }
            [data-theme="dark"] .likers-modal-footer,
            .dark .likers-modal-footer {
                border-top-color: rgba(139, 92, 246, 0.2);
            }
            [data-theme="dark"] .likers-loading,
            [data-theme="dark"] .likers-empty,
            .dark .likers-loading,
            .dark .likers-empty {
                color: #94a3b8;
            }
            [data-theme="dark"] .liker-item:hover,
            .dark .liker-item:hover {
                background: rgba(139, 92, 246, 0.1);
            }
            [data-theme="dark"] .liker-name,
            .dark .liker-name {
                color: #f1f5f9;
            }
            [data-theme="dark"] .liker-date,
            .dark .liker-date {
                color: #94a3b8;
            }

            /* System dark mode preference */
            @media (prefers-color-scheme: dark) {
                body:not([data-theme="light"]) .likers-modal-container {
                    background: #1e293b;
                    border: 1px solid rgba(139, 92, 246, 0.3);
                }
                body:not([data-theme="light"]) .likers-modal-header {
                    border-bottom-color: rgba(139, 92, 246, 0.2);
                }
                body:not([data-theme="light"]) .likers-modal-title {
                    color: #f1f5f9;
                }
                body:not([data-theme="light"]) .likers-modal-close {
                    color: #94a3b8;
                }
                body:not([data-theme="light"]) .likers-modal-close:hover {
                    background: rgba(139, 92, 246, 0.2);
                    color: #f1f5f9;
                }
                body:not([data-theme="light"]) .likers-modal-footer {
                    border-top-color: rgba(139, 92, 246, 0.2);
                }
                body:not([data-theme="light"]) .likers-loading,
                body:not([data-theme="light"]) .likers-empty {
                    color: #94a3b8;
                }
                body:not([data-theme="light"]) .liker-item:hover {
                    background: rgba(139, 92, 246, 0.1);
                }
                body:not([data-theme="light"]) .liker-name {
                    color: #f1f5f9;
                }
                body:not([data-theme="light"]) .liker-date {
                    color: #94a3b8;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Fetch likers from API
     */
    function fetchLikers(type, id, page, container, loadMoreBtn, titleEl) {
        fetch(SocialInteractions.apiBase + '/likers', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                target_type: type,
                target_id: id,
                page: page
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                // Update title with count
                const count = data.total_count || 0;
                titleEl.textContent = count === 1 ? '1 Like' : count + ' Likes';

                if (page === 1) {
                    container.innerHTML = '';
                }

                if (data.likers && data.likers.length > 0) {
                    const baseUrl = window.BASE_URL || '';
                    data.likers.forEach(liker => {
                        const item = document.createElement('a');
                        item.href = baseUrl + '/profile/' + liker.id;
                        item.className = 'liker-item';
                        item.innerHTML = `
                            <img src="${escapeHtml(liker.avatar_url)}" alt="${escapeHtml(liker.name)}" class="liker-avatar">
                            <div class="liker-info">
                                <div class="liker-name">${escapeHtml(liker.name)}</div>
                                <div class="liker-date">Liked ${escapeHtml(liker.liked_at_formatted)}</div>
                            </div>
                        `;
                        container.appendChild(item);
                    });

                    // Show/hide load more button
                    if (data.has_more) {
                        loadMoreBtn.classList.remove('hidden');
                        const modal = document.getElementById('likers-modal');
                        modal.dataset.page = page + 1;
                    } else {
                        loadMoreBtn.classList.add('hidden');
                    }
                } else if (page === 1) {
                    container.innerHTML = `
                        <div class="likers-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            <p>No likes yet</p>
                        </div>
                    `;
                }
            } else {
                container.innerHTML = '<div class="likers-empty">Failed to load likes</div>';
            }
        })
        .catch(err => {
            console.error('Fetch likers error:', err);
            container.innerHTML = '<div class="likers-empty">Failed to load likes</div>';
        });
    }

    /**
     * Load more likers (pagination)
     */
    SocialInteractions.loadMoreLikers = function() {
        const modal = document.getElementById('likers-modal');
        if (!modal) return;

        const type = modal.dataset.type;
        const id = modal.dataset.id;
        const page = parseInt(modal.dataset.page || 1);
        const modalContent = modal.querySelector('.likers-modal-body');
        const loadMoreBtn = modal.querySelector('.likers-load-more');
        const modalTitle = modal.querySelector('.likers-modal-title');

        fetchLikers(type, id, page, modalContent, loadMoreBtn, modalTitle);
    };

    /**
     * Close the likers modal
     */
    SocialInteractions.closeLikersModal = function() {
        const modal = document.getElementById('likers-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            SocialInteractions.closeLikersModal();
        }
    });

    // ============================================
    // FEED LOADING
    // ============================================

    /**
     * Load feed items with pagination
     * @param {object} options - Load options
     * @param {number} options.page - Page number
     * @param {number} options.limit - Items per page
     * @param {string} options.type - Filter by content type (optional)
     * @param {function} callback - Callback with results
     */
    SocialInteractions.loadFeed = function(options, callback) {
        const page = options.page || 1;
        const limit = options.limit || 10;
        const type = options.type || null;

        const payload = { page, limit };
        if (type) payload.type = type;

        fetch(SocialInteractions.apiBase + '/feed', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                callback(null, {
                    items: data.items || [],
                    page: data.page,
                    limit: data.limit,
                    hasMore: data.has_more
                });
            } else {
                callback(data.error || 'Failed to load feed');
            }
        })
        .catch(err => {
            callback(err.message || 'Network error');
        });
    };

    // ============================================
    // POST CREATION
    // ============================================

    /**
     * Create a new post
     * @param {object} postData - Post data
     * @param {string} postData.content - Post content
     * @param {string} postData.image_url - Image URL (optional)
     * @param {string} postData.visibility - Visibility level (optional)
     * @param {function} callback - Callback with result
     */
    SocialInteractions.createPost = function(postData, callback) {
        if (!SocialInteractions.isLoggedIn) {
            window.location.href = (window.BASE_URL || '') + '/login?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        const content = (postData.content || '').trim();
        if (!content) {
            if (callback) callback('Post content cannot be empty.');
            else alert('Post content cannot be empty.');
            return;
        }

        fetch(SocialInteractions.apiBase + '/create-post', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                content: content,
                image_url: postData.image_url || null,
                visibility: postData.visibility || 'public'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                SocialInteractions.showToast("Post created!", 'success');
                if (callback) callback(null, data);
                else setTimeout(() => location.reload(), 500);
            } else {
                const error = data.error || 'Failed to create post.';
                if (callback) callback(error);
                else SocialInteractions.showError(error);
            }
        })
        .catch(err => {
            const error = err.message || 'Network error';
            if (callback) callback(error);
            else SocialInteractions.showError('Failed to create post. Please try again.');
        });
    };

    // ============================================
    // GLOBAL FUNCTION ALIASES (for backward compatibility)
    // ============================================

    // These allow existing onclick="toggleLike(...)" to continue working
    global.toggleLike = SocialInteractions.toggleLike;
    global.toggleCommentSection = SocialInteractions.toggleCommentSection;
    global.fetchComments = SocialInteractions.fetchComments;
    global.submitComment = SocialInteractions.submitComment;
    global.repostToFeed = SocialInteractions.repostToFeed;
    global.deletePost = SocialInteractions.deletePost;
    global.showToast = SocialInteractions.showToast;

    // Enhanced comment function aliases
    global.toggleReactionPicker = SocialInteractions.toggleReactionPicker;
    global.toggleReaction = SocialInteractions.toggleReaction;
    global.showReplyForm = SocialInteractions.showReplyForm;
    global.submitReply = SocialInteractions.submitReply;
    global.editComment = SocialInteractions.editComment;
    global.deleteComment = SocialInteractions.deleteComment;

    // New Master Module function aliases
    global.searchMentions = SocialInteractions.searchMentions;
    global.initMentionAutocomplete = SocialInteractions.initMentionAutocomplete;
    global.loadFeed = SocialInteractions.loadFeed;
    global.createPost = SocialInteractions.createPost;

    // Likers modal function aliases
    global.showLikers = SocialInteractions.showLikers;
    global.closeLikersModal = SocialInteractions.closeLikersModal;
    global.loadMoreLikers = SocialInteractions.loadMoreLikers;

    // Log that the module loaded successfully (for debugging)
    console.log('[SocialInteractions] Module loaded, showLikers available:', typeof SocialInteractions.showLikers);

    // Export to global
    global.SocialInteractions = SocialInteractions;

})(typeof window !== 'undefined' ? window : this);
