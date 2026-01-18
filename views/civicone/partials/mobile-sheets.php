<?php

/**
 * Mobile Bottom Sheets
 * Native app-like slide-up sheets for mobile devices only
 *
 * Features:
 * - Comment sheet (focused comment input with like/reply)
 * - Drag to dismiss
 * - Backdrop tap to close
 * - Haptic feedback
 *
 * Only activates on screens < 768px or touch devices
 *
 * CSS extracted to: /assets/css/mobile-sheets.css
 * CSS loaded via layout headers for proper caching
 */

$isLoggedIn = isset($_SESSION['user_id']);
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
$userName = $_SESSION['user_name'] ?? 'User';
$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>

<!-- Comment Sheet -->
<div class="mobile-sheet-backdrop" id="commentBackdrop" onclick="closeMobileCommentSheet()"></div>
<div class="mobile-sheet" id="commentSheet">
    <div class="mobile-sheet-handle" id="commentHandle"></div>
    <div class="mobile-sheet-header">
        <button type="button" class="mobile-sheet-close" onclick="closeMobileCommentSheet()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <span class="mobile-sheet-title">Comments</span>
        <span style="width: 32px;"></span>
    </div>
    <div class="mobile-sheet-body" style="display: flex; flex-direction: column;">
        <!-- Comments list ABOVE the input (like Facebook) -->
        <div class="mobile-comments-list" id="mobileCommentsList" style="flex: 1; margin-bottom: 12px;">
            <!-- Comments loaded dynamically -->
        </div>

        <?php if ($isLoggedIn): ?>
            <div class="mobile-comment-input-wrap" style="flex-shrink: 0; border-top: 1px solid var(--feed-border, #e5e7eb); padding-top: 12px; margin-top: auto;">
                <img src="<?= htmlspecialchars($userAvatar) ?>" loading="lazy" alt="" class="mobile-comment-avatar">
                <textarea class="mobile-comment-input" id="mobileCommentInput" placeholder="Write a comment..." rows="1" oninput="autoResizeCommentInput(this)"></textarea>
                <button type="button" class="mobile-comment-send" id="mobileCommentSend" onclick="submitMobileComment()">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 16px; margin-top: auto;">
                <a href="<?= $basePath ?>/login" style="color: #6366f1; font-weight: 500;">Sign in to comment</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    (function() {
        'use strict';

        // Check if mobile
        const isMobile = () => window.innerWidth <= 768 || ('ontouchstart' in window);

        // Haptic feedback
        const haptic = {
            light: () => {
                if (navigator.vibrate) navigator.vibrate(10);
                if (window.Capacitor?.Plugins?.Haptics) {
                    window.Capacitor.Plugins.Haptics.impact({
                        style: 'light'
                    });
                }
            },
            medium: () => {
                if (navigator.vibrate) navigator.vibrate(20);
                if (window.Capacitor?.Plugins?.Haptics) {
                    window.Capacitor.Plugins.Haptics.impact({
                        style: 'medium'
                    });
                }
            }
        };

        // Sheet state
        let currentCommentTarget = {
            type: null,
            id: null
        };
        let isDragging = false;
        let startY = 0;
        let currentY = 0;

        // ===== COMMENT SHEET =====
        window.openMobileCommentSheet = function(targetType, targetId, postPreview) {
            if (!isMobile()) return false;

            const backdrop = document.getElementById('commentBackdrop');
            const sheet = document.getElementById('commentSheet');
            if (!backdrop || !sheet) return false;

            haptic.light();

            currentCommentTarget = {
                type: targetType,
                id: targetId
            };

            // Load existing comments
            loadMobileComments(targetType, targetId);

            // Add active class
            backdrop.classList.add('active');
            sheet.classList.add('active');
            document.body.classList.add('mobile-sheet-open');
            document.body.style.overflow = 'hidden';

            setTimeout(() => {
                document.getElementById('mobileCommentInput')?.focus();
            }, 300);

            return true;
        };

        window.closeMobileCommentSheet = function() {
            haptic.light();
            document.getElementById('commentBackdrop').classList.remove('active');
            document.getElementById('commentSheet').classList.remove('active');
            document.body.classList.remove('mobile-sheet-open');
            document.body.style.overflow = '';
            currentCommentTarget = {
                type: null,
                id: null
            };
        };

        window.autoResizeCommentInput = function(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        };

        window.loadMobileComments = function(targetType, targetId) {
            const listEl = document.getElementById('mobileCommentsList');
            if (!listEl) return;

            listEl.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--feed-text-muted);"><i class="fa-solid fa-spinner fa-spin"></i></div>';

            const apiBase = (window.BASE_URL || window.BASE_PATH || '') + '/api/social';

            fetch(apiBase + '/comments', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'fetch',
                        target_type: targetType,
                        target_id: targetId
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' && data.comments) {
                        if (data.comments.length === 0) {
                            listEl.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--feed-text-muted);">No comments yet. Be the first!</div>';
                        } else {
                            listEl.innerHTML = data.comments.map(c => renderMobileComment(c, targetType, targetId)).join('');
                        }
                    }
                })
                .catch(() => {
                    listEl.innerHTML = '<div style="text-align: center; padding: 20px; color: #ef4444;">Failed to load comments</div>';
                });
        };

        // Render a single comment with like/reply buttons
        function renderMobileComment(c, targetType, targetId, isReply = false) {
            const isLiked = c.is_liked || false;
            const likesCount = parseInt(c.likes_count || c.reaction_count || 0);
            const commentId = c.id;
            const replies = c.replies || [];
            // API returns author_name/author_avatar, normalize field names
            const authorName = c.author_name || c.name || 'User';
            const authorAvatar = c.author_avatar || c.avatar_url || '/assets/img/defaults/default_avatar.webp';

            let html = `
            <div class="mobile-comment-item" data-comment-id="${commentId}">
                <img src="${authorAvatar}" class="mobile-comment-avatar" alt="" loading="lazy">
                <div class="mobile-comment-content">
                    <div class="mobile-comment-author">
                        ${escapeHtml(authorName)}
                        ${likesCount > 0 ? `<span class="mobile-comment-likes"><i class="fa-solid fa-heart"></i> ${likesCount}</span>` : ''}
                    </div>
                    <div class="mobile-comment-text">${escapeHtml(c.content)}</div>
                    <div class="mobile-comment-actions">
                        <span class="mobile-comment-time">${formatTimeAgo(c.created_at)}</span>
                        <button type="button" class="mobile-comment-action ${isLiked ? 'liked' : ''}" onclick="toggleMobileCommentLike(${commentId}, this)">
                            <i class="fa-${isLiked ? 'solid' : 'regular'} fa-heart"></i> Like
                        </button>
                        ${!isReply ? `<button type="button" class="mobile-comment-action" onclick="showMobileReplyForm(${commentId})">
                            <i class="fa-regular fa-comment"></i> Reply
                        </button>` : ''}
                    </div>
                </div>
            </div>
            ${!isReply ? `<div class="mobile-reply-form" id="reply-form-${commentId}">
                <input type="text" class="mobile-reply-input" id="reply-input-${commentId}" placeholder="Write a reply..." onkeydown="if(event.key === 'Enter') submitMobileReply(${commentId}, '${targetType}', ${targetId})">
                <button type="button" class="mobile-reply-send" onclick="submitMobileReply(${commentId}, '${targetType}', ${targetId})">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>` : ''}
        `;

            // Render replies if any
            if (replies.length > 0 && !isReply) {
                html += `<div class="mobile-comment-replies">`;
                replies.forEach(reply => {
                    html += renderMobileComment(reply, targetType, targetId, true);
                });
                html += `</div>`;
            }

            return html;
        }

        // Toggle like on a comment
        window.toggleMobileCommentLike = function(commentId, btn) {
            haptic.light();

            const apiBase = (window.BASE_URL || window.BASE_PATH || '') + '/api/social';

            fetch(apiBase + '/reaction', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        comment_id: commentId,
                        emoji: '❤️'
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' || data.success) {
                        const isNowLiked = data.action === 'added';
                        btn.classList.toggle('liked', isNowLiked);
                        btn.innerHTML = `<i class="fa-${isNowLiked ? 'solid' : 'regular'} fa-heart"></i> Like`;

                        // Update likes badge - get heart emoji count from reactions
                        const authorEl = btn.closest('.mobile-comment-content')?.querySelector('.mobile-comment-author');
                        if (authorEl) {
                            let badge = authorEl.querySelector('.mobile-comment-likes');
                            const newCount = (data.reactions && data.reactions['❤️']) || 0;
                            if (newCount > 0) {
                                if (!badge) {
                                    badge = document.createElement('span');
                                    badge.className = 'mobile-comment-likes';
                                    authorEl.appendChild(badge);
                                }
                                badge.innerHTML = `<i class="fa-solid fa-heart"></i> ${newCount}`;
                            } else if (badge) {
                                badge.remove();
                            }
                        }
                    }
                })
                .catch(err => {
                    console.error('Like toggle error:', err);
                });
        };

        // Show reply input form
        window.showMobileReplyForm = function(commentId) {
            haptic.light();
            const form = document.getElementById(`reply-form-${commentId}`);
            if (form) {
                form.classList.toggle('active');
                if (form.classList.contains('active')) {
                    document.getElementById(`reply-input-${commentId}`)?.focus();
                }
            }
        };

        // Submit a reply
        window.submitMobileReply = function(parentCommentId, targetType, targetId) {
            const input = document.getElementById(`reply-input-${parentCommentId}`);
            const content = input?.value?.trim();
            if (!content) return;

            haptic.medium();

            const apiBase = (window.BASE_URL || window.BASE_PATH || '') + '/api/social';

            fetch(apiBase + '/reply', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        target_type: targetType,
                        target_id: targetId,
                        parent_id: parentCommentId,
                        content: content
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        input.value = '';
                        document.getElementById(`reply-form-${parentCommentId}`)?.classList.remove('active');
                        // Reload comments to show new reply
                        loadMobileComments(currentCommentTarget.type, currentCommentTarget.id);
                    }
                });
        };

        window.submitMobileComment = function() {
            const input = document.getElementById('mobileCommentInput');
            const content = input?.value?.trim();
            if (!content || !currentCommentTarget.type || !currentCommentTarget.id) return;

            haptic.medium();

            const apiBase = (window.BASE_URL || window.BASE_PATH || '') + '/api/social';

            // Disable send button
            const sendBtn = document.getElementById('mobileCommentSend');
            if (sendBtn) sendBtn.disabled = true;

            fetch(apiBase + '/comments', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'submit',
                        target_type: currentCommentTarget.type,
                        target_id: currentCommentTarget.id,
                        content: content
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        input.value = '';
                        input.style.height = 'auto';
                        loadMobileComments(currentCommentTarget.type, currentCommentTarget.id);

                        // Update comment count in feed if function exists
                        if (typeof updateCommentCount === 'function') {
                            updateCommentCount(currentCommentTarget.type, currentCommentTarget.id, 1);
                        }
                    }
                })
                .finally(() => {
                    if (sendBtn) sendBtn.disabled = false;
                });
        };

        // Helper functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatTimeAgo(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);

            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd';
            return Math.floor(diff / 604800) + 'w';
        }

        // ===== DRAG TO DISMISS =====
        function setupDragToDismiss(handleId, sheetId, closeFunc) {
            const handle = document.getElementById(handleId);
            const sheet = document.getElementById(sheetId);
            if (!handle || !sheet) return;

            handle.addEventListener('touchstart', function(e) {
                isDragging = true;
                startY = e.touches[0].clientY;
                sheet.classList.add('dragging');
            }, {
                passive: true
            });

            document.addEventListener('touchmove', function(e) {
                if (!isDragging) return;
                currentY = e.touches[0].clientY;
                const diff = currentY - startY;

                if (diff > 0) {
                    sheet.style.transform = `translateY(${diff}px)`;
                }
            }, {
                passive: true
            });

            document.addEventListener('touchend', function() {
                if (!isDragging) return;
                isDragging = false;
                sheet.classList.remove('dragging');

                const diff = currentY - startY;
                sheet.style.transform = '';

                if (diff > 100) {
                    closeFunc();
                }

                startY = 0;
                currentY = 0;
            }, {
                passive: true
            });
        }

        // Setup drag handlers
        setupDragToDismiss('commentHandle', 'commentSheet', closeMobileCommentSheet);

        // ===== MOBILE COMMENT INTERCEPT =====
        // Intercept toggleCommentSection to use mobile sheet on mobile devices
        // Must capture existing function AND intercept future assignments

        // Store original for desktop fallback - capture EXISTING function first
        let _desktopToggleCommentSection = typeof window.toggleCommentSection === 'function'
            ? window.toggleCommentSection
            : null;

        // Define our mobile-aware handler
        function mobileAwareToggleComment(targetType, targetId) {
            if (isMobile()) {
                const opened = openMobileCommentSheet(targetType, targetId, '');
                if (opened) return;
            }

            // Use desktop handler if available (check multiple fallbacks)
            if (typeof _desktopToggleCommentSection === 'function') {
                _desktopToggleCommentSection(targetType, targetId);
            } else if (typeof window._feedDesktopToggleComment === 'function') {
                window._feedDesktopToggleComment(targetType, targetId);
            } else if (typeof SocialInteractions !== 'undefined' && SocialInteractions.toggleCommentSection) {
                SocialInteractions.toggleCommentSection(targetType, targetId);
            }
        }

        // Set up getter/setter to intercept future overwrites
        Object.defineProperty(window, 'toggleCommentSection', {
            get: function() {
                return mobileAwareToggleComment;
            },
            set: function(fn) {
                _desktopToggleCommentSection = fn;
            },
            configurable: true
        });

    })();
</script>