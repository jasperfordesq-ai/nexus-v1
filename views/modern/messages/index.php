<?php
/**
 * Messages Index - Dual Interface
 * Path: views/modern/messages/index.php
 *
 * Desktop: Holographic Glassmorphism Interface (2025)
 * Mobile: Clean, minimal fullscreen design
 */

$hTitle = 'Messages';
$hSubtitle = 'Your Conversations';
$hGradient = 'htb-hero-gradient-members';
$hType = 'Direct Messages';
$hideHero = true;

// CRITICAL: Disable all PTR and enable fullscreen mode
$bodyClass = 'no-ptr messages-page messages-fullscreen';
$hideUtilityBar = true;
$hideBottomNav = true;

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Functions must be defined before any onclick handlers reference them -->
<script>
window.cleanupBeforeLeave = function() {
    document.documentElement.classList.remove('messages-page');
    document.body.classList.remove('messages-page', 'no-ptr', 'messages-fullscreen');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
};

// New message modal functions - defined early so onclick handlers work immediately
window.openNewMessage = function() {
    var modal = document.getElementById('newMessageModal');
    var nmSearchInput = document.getElementById('nmSearchInput');
    if (modal) modal.classList.add('active');
    if (nmSearchInput) setTimeout(function() { nmSearchInput.focus(); }, 300);
};

window.closeNewMessage = function() {
    var modal = document.getElementById('newMessageModal');
    var nmSearchInput = document.getElementById('nmSearchInput');
    var nmResults = document.getElementById('nmResults');
    if (modal) modal.classList.remove('active');
    if (nmSearchInput) nmSearchInput.value = '';
    if (nmResults) {
        nmResults.innerHTML = '<div class="nm-state"><i class="fa-solid fa-users" style="font-size: 1.5rem; margin-bottom: 12px; opacity: 0.5;"></i><p>Type a name to search</p></div>';
    }
};
</script>

<!-- CSS moved to /assets/css/messages-index.css -->

<!-- ==========================================
     DESKTOP HOLOGRAPHIC GLASSMORPHISM INTERFACE
     ========================================== -->
<!-- Main content wrapper (main tag opened in header.php) -->
<div class="messages-desktop-holo">
    <!-- Floating Orbs -->
    <div class="holo-orb-desktop holo-orb-desktop-1"></div>
    <div class="holo-orb-desktop holo-orb-desktop-2"></div>
    <div class="holo-orb-desktop holo-orb-desktop-3"></div>

    <div class="holo-messages-container">
        <!-- Sidebar - Conversations List -->
        <div class="holo-sidebar">
            <div class="holo-sidebar-header">
                <div class="holo-sidebar-top">
                    <a href="<?= $basePath ?>/" class="holo-back-btn no-transition" aria-label="Back to home" onclick="cleanupBeforeLeave()" data-turbo="false">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div class="holo-title-group">
                        <h1>Messages</h1>
                        <span id="holoConvCount"><?= count($threads ?? []) ?> conversation<?= count($threads ?? []) !== 1 ? 's' : '' ?></span>
                    </div>
                    <button type="button" class="holo-new-btn" onclick="openHoloNewMessage()" aria-label="New message">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                <div class="holo-search-wrap">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" class="holo-search-input" placeholder="Search conversations..." id="holoSearchInput">
                </div>
            </div>

            <?php if (empty($threads)): ?>
                <div class="holo-empty-state">
                    <div class="holo-empty-icon">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                    <h3>No messages yet</h3>
                    <p>Start connecting with community members</p>
                    <button type="button" class="holo-empty-btn" onclick="openHoloNewMessage()">
                        <i class="fa-solid fa-plus"></i>
                        Start a conversation
                    </button>
                </div>
            <?php else: ?>
                <div class="holo-thread-list" id="holoThreadList">
                    <?php foreach ($threads as $thread): ?>
                        <?php
                        $isUnread = ($thread['receiver_id'] == $_SESSION['user_id'] && !$thread['is_read']);
                        $avatarUrl = $thread['other_user_avatar'] ?? $thread['avatar_url'] ?? null;
                        $initial = strtoupper(substr($thread['other_user_name'], 0, 1));
                        $preview = htmlspecialchars(substr($thread['body'], 0, 60));
                        if (strlen($thread['body']) > 60) $preview .= '...';

                        $msgTime = strtotime($thread['created_at']);
                        $diff = time() - $msgTime;
                        if ($diff < 60) $timeDisplay = 'Now';
                        elseif ($diff < 3600) $timeDisplay = floor($diff / 60) . 'm';
                        elseif ($diff < 86400) $timeDisplay = floor($diff / 3600) . 'h';
                        elseif ($diff < 604800) $timeDisplay = date('D', $msgTime);
                        else $timeDisplay = date('M j', $msgTime);

                        $otherLastActive = $thread['other_user_last_active'] ?? null;
                        $isOtherOnline = $otherLastActive && (strtotime($otherLastActive) > strtotime('-5 minutes'));
                        ?>
                        <div class="holo-thread <?= $isUnread ? 'unread' : '' ?>"
                             data-user-id="<?= $thread['other_user_id'] ?>"
                             data-user-name="<?= htmlspecialchars($thread['other_user_name']) ?>"
                             data-avatar="<?= htmlspecialchars($avatarUrl ?? '') ?>"
                             data-initial="<?= $initial ?>"
                             data-online="<?= $isOtherOnline ? '1' : '0' ?>"
                             data-name="<?= htmlspecialchars(strtolower($thread['other_user_name'])) ?>">
                            <div class="holo-avatar">
                                <?php if ($avatarUrl): ?>
                                    <?= webp_avatar($avatarUrl, $thread['other_user_name'], 48) ?>
                                <?php else: ?>
                                    <?= $initial ?>
                                <?php endif; ?>
                                <?php if ($isOtherOnline): ?>
                                    <span class="holo-avatar-status"></span>
                                <?php endif; ?>
                            </div>
                            <div class="holo-thread-info">
                                <div class="holo-thread-row">
                                    <span class="holo-thread-name"><?= htmlspecialchars($thread['other_user_name']) ?></span>
                                    <span class="holo-thread-time"><?= $timeDisplay ?></span>
                                </div>
                                <div class="holo-thread-row">
                                    <span class="holo-thread-preview"><?= $preview ?></span>
                                    <?php if ($isUnread): ?>
                                        <span class="holo-thread-badge">1</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" class="holo-thread-delete" title="Delete conversation">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Chat Area -->
        <div class="holo-main" id="holoMainChat">
            <!-- Welcome State (shown by default) -->
            <div class="holo-welcome" id="holoWelcomeState">
                <div class="holo-welcome-icon">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <h2>Welcome to Messages</h2>
                <p>Select a conversation from the sidebar or start a new one to begin chatting with community members.</p>
                <button type="button" class="holo-welcome-btn" onclick="openHoloNewMessage()">
                    <i class="fa-solid fa-paper-plane"></i>
                    Start New Conversation
                </button>
            </div>

            <!-- Chat Area (hidden by default, shown when conversation selected) -->
            <div id="holoChatArea" style="display: none; flex-direction: column; height: 100%;">
                <!-- Chat Header -->
                <div class="holo-chat-header">
                    <div class="holo-chat-user">
                        <div class="holo-chat-avatar" id="holoChatAvatar">
                            <span id="holoChatInitial"></span>
                        </div>
                        <div class="holo-chat-info">
                            <h3 id="holoChatName">User Name</h3>
                            <div class="holo-chat-status" id="holoChatStatus">Offline</div>
                        </div>
                    </div>
                    <div class="holo-chat-actions">
                        <a href="#" id="holoChatProfileLink" class="holo-chat-action-btn" title="View profile">
                            <i class="fa-solid fa-user"></i>
                        </a>
                        <button type="button" class="holo-chat-action-btn danger" id="holoChatDeleteBtn" title="Delete conversation">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages Container -->
                <div class="holo-chat-messages" id="holoChatMessages">
                    <!-- Messages will be loaded here via AJAX -->
                </div>

                <!-- Typing Indicator -->
                <div class="holo-typing-indicator" id="holoTypingIndicator">
                    <div class="holo-typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="holo-chat-input-wrap">
                    <div class="holo-chat-input-row">
                        <button type="button" class="holo-voice-btn" id="holoVoiceBtn" title="Record voice message">
                            <i class="fa-solid fa-microphone"></i>
                        </button>
                        <div class="holo-chat-textarea-wrap">
                            <textarea class="holo-chat-textarea" id="holoChatInput" placeholder="Type a message..." rows="1"></textarea>
                        </div>
                        <button type="button" class="holo-send-btn" id="holoSendBtn" disabled>
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading State (Skeleton) -->
            <div class="holo-chat-loading" id="holoChatLoading" style="display: none;">
                <div class="message-skeleton">
                    <div class="message-skeleton-bubble">
                        <div class="skeleton skeleton-avatar"></div>
                        <div class="message-skeleton-content">
                            <div class="skeleton message-skeleton-line long"></div>
                            <div class="skeleton message-skeleton-time"></div>
                        </div>
                    </div>
                    <div class="message-skeleton-bubble sent">
                        <div class="message-skeleton-content">
                            <div class="skeleton message-skeleton-line medium"></div>
                            <div class="skeleton message-skeleton-time"></div>
                        </div>
                    </div>
                    <div class="message-skeleton-bubble">
                        <div class="skeleton skeleton-avatar"></div>
                        <div class="message-skeleton-content">
                            <div class="skeleton message-skeleton-line short"></div>
                            <div class="skeleton message-skeleton-time"></div>
                        </div>
                    </div>
                    <div class="message-skeleton-bubble sent">
                        <div class="message-skeleton-content">
                            <div class="skeleton message-skeleton-line long"></div>
                            <div class="skeleton message-skeleton-line medium"></div>
                            <div class="skeleton message-skeleton-time"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Desktop Delete Confirmation Modal -->
    <div class="holo-delete-overlay" id="holoDeleteOverlay">
        <div class="holo-delete-modal">
            <div class="holo-delete-icon">
                <i class="fa-solid fa-trash"></i>
            </div>
            <h4>Delete conversation?</h4>
            <p>This will permanently delete all messages with <span id="holoDeleteUserName">this person</span>.</p>
            <div class="holo-delete-actions">
                <button type="button" class="holo-cancel-btn" id="holoDeleteCancel">Cancel</button>
                <button type="button" class="holo-delete-btn" id="holoDeleteConfirm">Delete</button>
            </div>
        </div>
    </div>

    <!-- Context Menu -->
    <div class="holo-context-menu" id="holoContextMenu">
        <button type="button" class="holo-context-item" id="holoCtxOpen">
            <i class="fa-solid fa-comment"></i>
            <span>Open conversation</span>
        </button>
        <button type="button" class="holo-context-item" id="holoCtxProfile">
            <i class="fa-solid fa-user"></i>
            <span>View profile</span>
        </button>
        <div class="holo-context-divider"></div>
        <button type="button" class="holo-context-item danger" id="holoCtxDelete">
            <i class="fa-solid fa-trash"></i>
            <span>Delete conversation</span>
        </button>
    </div>

    <!-- Desktop New Message Modal -->
    <div class="holo-modal-overlay" id="holoNewMessageModal">
        <div class="holo-modal">
            <div class="holo-modal-header">
                <h3>New Message</h3>
                <button type="button" class="holo-modal-close" onclick="closeHoloNewMessage()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="holo-modal-search">
                <input type="text" placeholder="Search members..." id="holoNmSearchInput">
            </div>
            <div class="holo-modal-results" id="holoNmResults">
                <div class="holo-modal-state">
                    <i class="fa-solid fa-users" style="font-size: 1.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                    <p>Type a name to search</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Desktop Toast -->
    <div class="holo-toast" id="holoToast"></div>

    <!-- Voice Recording Overlay -->
    <div class="holo-voice-overlay" id="holoVoiceOverlay">
        <div class="holo-voice-visual">
            <i class="fa-solid fa-microphone"></i>
        </div>
        <div class="holo-voice-time" id="holoVoiceTime">0:00</div>
        <div class="holo-voice-hint">Recording voice message...</div>
        <div class="holo-voice-actions">
            <button type="button" class="holo-voice-cancel" id="holoVoiceCancel" aria-label="Cancel recording">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <button type="button" class="holo-voice-send" id="holoVoiceSend" aria-label="Send voice message">
                <i class="fa-solid fa-check"></i>
            </button>
        </div>
    </div>
</div>

<!-- ==========================================
     MOBILE FULLSCREEN INTERFACE
     ========================================== -->
<div class="messages-app mobile-interface">
    <!-- Header -->
    <header class="messages-header">
        <div class="messages-header-left">
            <a href="<?= $basePath ?>/" class="messages-back no-transition" aria-label="Back to home" onclick="cleanupBeforeLeave()" data-turbo="false">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="messages-title">Messages</h1>
                <div class="messages-count"><?= count($threads ?? []) ?> conversation<?= count($threads ?? []) !== 1 ? 's' : '' ?></div>
            </div>
        </div>
        <button type="button" class="messages-new-btn" onclick="openNewMessage()" aria-label="New message">
            <i class="fa-solid fa-plus"></i>
        </button>
    </header>

    <!-- Search -->
    <div class="messages-search">
        <div class="messages-search-wrap">
            <i class="fa-solid fa-search"></i>
            <input type="text" class="messages-search-input" placeholder="Search conversations..." id="searchInput">
        </div>
    </div>

    <!-- Thread List -->
    <?php if (empty($threads)): ?>
        <div class="messages-empty">
            <div class="messages-empty-icon">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <h3>No messages yet</h3>
            <p>Start connecting with members in your community.</p>
            <button type="button" class="messages-empty-btn" onclick="openNewMessage()">
                <i class="fa-solid fa-plus"></i>
                Start a conversation
            </button>
        </div>
    <?php else: ?>
        <div class="messages-list" id="threadList">
            <?php foreach ($threads as $thread): ?>
                <?php
                $isUnread = ($thread['receiver_id'] == $_SESSION['user_id'] && !$thread['is_read']);
                $avatarUrl = $thread['other_user_avatar'] ?? $thread['avatar_url'] ?? null;
                $initial = strtoupper(substr($thread['other_user_name'], 0, 1));
                $preview = htmlspecialchars(substr($thread['body'], 0, 50));
                if (strlen($thread['body']) > 50) $preview .= '...';

                $msgTime = strtotime($thread['created_at']);
                $diff = time() - $msgTime;
                if ($diff < 60) $timeDisplay = 'Now';
                elseif ($diff < 3600) $timeDisplay = floor($diff / 60) . 'm';
                elseif ($diff < 86400) $timeDisplay = floor($diff / 3600) . 'h';
                elseif ($diff < 604800) $timeDisplay = date('D', $msgTime);
                else $timeDisplay = date('M j', $msgTime);

                // Real-time online status
                $otherLastActive = $thread['other_user_last_active'] ?? null;
                $isOtherOnline = $otherLastActive && (strtotime($otherLastActive) > strtotime('-5 minutes'));
                ?>
                <div class="messages-thread-wrap"
                     data-user-id="<?= $thread['other_user_id'] ?>"
                     data-user-name="<?= htmlspecialchars($thread['other_user_name']) ?>"
                     data-avatar="<?= htmlspecialchars($avatarUrl ?? '') ?>"
                     data-initial="<?= $initial ?>">
                    <a href="<?= $basePath ?>/messages/<?= $thread['other_user_id'] ?>"
                       class="messages-thread <?= $isUnread ? 'unread' : '' ?>"
                       data-name="<?= htmlspecialchars(strtolower($thread['other_user_name'])) ?>">
                        <div class="messages-avatar" style="position: relative;">
                            <?php if ($avatarUrl): ?>
                                <?= webp_avatar($avatarUrl, $thread['other_user_name'], 48) ?>
                            <?php else: ?>
                                <?= $initial ?>
                            <?php endif; ?>
                            <?php if ($isOtherOnline): ?>
                                <span style="position:absolute;bottom:0;right:0;width:12px;height:12px;background:#10b981;border:2px solid var(--msg-surface);border-radius:50%;"></span>
                            <?php endif; ?>
                        </div>
                        <div class="messages-content">
                            <div class="messages-row">
                                <span class="messages-name"><?= htmlspecialchars($thread['other_user_name']) ?></span>
                                <span class="messages-time"><?= $timeDisplay ?></span>
                            </div>
                            <div class="messages-row">
                                <span class="messages-preview"><?= $preview ?></span>
                                <?php if ($isUnread): ?>
                                    <span class="messages-badge">1</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <div class="messages-thread-actions">
                        <button type="button" class="msg-delete-action" aria-label="Delete conversation">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                    <button type="button" class="messages-thread-options" aria-label="More options">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- New Message Modal -->
<div class="nm-overlay" id="newMessageModal">
    <div class="nm-sheet">
        <div class="nm-handle"></div>
        <div class="nm-header">
            <h3>New Message</h3>
            <button type="button" class="nm-close" onclick="closeNewMessage()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="nm-search">
            <input type="text" placeholder="Search members..." id="nmSearchInput">
        </div>
        <div class="nm-results" id="nmResults">
            <div class="nm-state">
                <i class="fa-solid fa-users" style="font-size: 1.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                <p>Type a name to search</p>
            </div>
        </div>
    </div>
</div>

<!-- Thread Options Menu -->
<div class="thread-menu-overlay" id="threadMenuOverlay">
    <div class="thread-menu-sheet">
        <div class="thread-menu-header" id="threadMenuHeader">
            <div class="messages-avatar" id="threadMenuAvatar"></div>
            <span id="threadMenuName"></span>
        </div>
        <button type="button" class="thread-menu-btn" id="threadMenuView">
            <i class="fa-solid fa-comment"></i>
            <span>View conversation</span>
        </button>
        <button type="button" class="thread-menu-btn delete" id="threadMenuDelete">
            <i class="fa-solid fa-trash"></i>
            <span>Delete conversation</span>
        </button>
    </div>
</div>

<!-- Delete Confirmation -->
<div class="delete-confirm-overlay" id="deleteConfirmOverlay">
    <div class="delete-confirm-box">
        <h4>Delete conversation?</h4>
        <p>This will permanently delete all messages with this person.</p>
        <div class="delete-confirm-actions">
            <button type="button" class="delete-cancel-btn" id="deleteCancelBtn">Cancel</button>
            <button type="button" class="delete-confirm-btn" id="deleteConfirmBtn">Delete</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="msg-toast" id="msgToast"></div>

<script>
(function() {
    'use strict';

    // Add messages-page class for CSS
    document.documentElement.classList.add('messages-page');
    document.body.classList.add('messages-page');

    // Disable PTR if NexusMobile is present
    if (window.NexusMobile && window.NexusMobile.ptrInstances) {
        window.NexusMobile.ptrInstances.forEach(function(instance) {
            if (instance && instance.destroy) instance.destroy();
        });
        window.NexusMobile.ptrInstances = [];
    }

    // Note: PTR prevention is handled by CSS (overscroll-behavior: none)
    // and the skip conditions in nexus-mobile.js

    // =============================================
    // MESSAGES LIST FUNCTIONALITY
    // =============================================

    const BASE_PATH = <?= json_encode(rtrim($basePath, '/')) ?>;
    const modal = document.getElementById('newMessageModal');
    const searchInput = document.getElementById('searchInput');
    const nmSearchInput = document.getElementById('nmSearchInput');
    const nmResults = document.getElementById('nmResults');
    const threadList = document.getElementById('threadList');
    let searchTimeout = null;

    // Filter threads
    if (searchInput && threadList) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            threadList.querySelectorAll('.messages-thread').forEach(t => {
                const name = t.dataset.name || '';
                t.style.display = name.includes(q) ? '' : 'none';
            });
        });
    }

    // Note: openNewMessage and closeNewMessage are defined early in the page (before onclick handlers)
    // for immediate availability. resetResults is local to this scope for enhanced functionality.
    function resetResults() {
        nmResults.innerHTML = `
            <div class="nm-state">
                <i class="fa-solid fa-users" style="font-size: 1.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                <p>Type a name to search</p>
            </div>
        `;
    }

    function showLoading() {
        nmResults.innerHTML = `
            <div class="nm-state">
                <div class="nm-spinner"></div>
                <p>Searching...</p>
            </div>
        `;
    }

    async function searchUsers(query) {
        if (query.length < 2) { resetResults(); return; }
        showLoading();
        try {
            const res = await fetch(`${BASE_PATH}/members?q=${encodeURIComponent(query)}&ajax=1`, {
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            const users = data.data || data || [];
            if (users.length) {
                nmResults.innerHTML = users.map(u => {
                    const initial = (u.name || 'U').charAt(0).toUpperCase();
                    const avatar = u.avatar_url
                        ? `<img src="${escapeHtml(u.avatar_url)}" alt="" loading="lazy">`
                        : initial;
                    return `
                        <div class="nm-user" onclick="window.location.href='${BASE_PATH}/messages/${u.id}'">
                            <div class="nm-user-avatar">${avatar}</div>
                            <div class="nm-user-name">${escapeHtml(u.name || 'Unknown')}</div>
                        </div>
                    `;
                }).join('');
            } else {
                nmResults.innerHTML = `<div class="nm-state"><p>No members found</p></div>`;
            }
        } catch (e) {
            nmResults.innerHTML = `<div class="nm-state"><p>Search failed</p></div>`;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    if (nmSearchInput) {
        nmSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => searchUsers(this.value.trim()), 300);
        });
    }

    // Close modal on backdrop click
    modal?.addEventListener('click', function(e) {
        if (e.target === this) closeNewMessage();
    });

    // =============================================
    // CONVERSATION DELETE FUNCTIONALITY
    // =============================================

    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const threadMenuOverlay = document.getElementById('threadMenuOverlay');
    const threadMenuAvatar = document.getElementById('threadMenuAvatar');
    const threadMenuName = document.getElementById('threadMenuName');
    const threadMenuView = document.getElementById('threadMenuView');
    const threadMenuDelete = document.getElementById('threadMenuDelete');
    const deleteConfirmOverlay = document.getElementById('deleteConfirmOverlay');
    const deleteCancelBtn = document.getElementById('deleteCancelBtn');
    const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
    const msgToast = document.getElementById('msgToast');

    let selectedUserId = null;
    let selectedWrap = null;
    let swipeStartX = 0;
    let swipeThreshold = 60;
    let isSwiping = false;

    // Show toast notification
    function showToast(message, duration = 2000) {
        msgToast.textContent = message;
        msgToast.classList.add('show');
        setTimeout(() => msgToast.classList.remove('show'), duration);
    }

    // Open thread menu
    function openThreadMenu(wrap) {
        selectedUserId = wrap.dataset.userId;
        selectedWrap = wrap;
        const userName = wrap.dataset.userName;
        const avatar = wrap.dataset.avatar;
        const initial = wrap.dataset.initial;

        threadMenuName.textContent = userName;
        if (avatar) {
            threadMenuAvatar.innerHTML = `<img src="${escapeHtml(avatar)}" alt="" loading="lazy">`;
        } else {
            threadMenuAvatar.textContent = initial;
        }

        threadMenuOverlay.classList.add('active');
    }

    // Close thread menu
    function closeThreadMenu() {
        threadMenuOverlay.classList.remove('active');
    }

    // Show delete confirmation
    function showDeleteConfirm() {
        closeThreadMenu();
        deleteConfirmOverlay.classList.add('active');
    }

    // Close delete confirmation
    function closeDeleteConfirm() {
        deleteConfirmOverlay.classList.remove('active');
        selectedUserId = null;
        selectedWrap = null;
    }

    // Delete conversation
    async function deleteConversation() {
        if (!selectedUserId || !selectedWrap) return;

        const userId = selectedUserId;
        const wrap = selectedWrap;

        closeDeleteConfirm();

        // Optimistic UI - fade out
        wrap.style.transition = 'opacity 0.3s, transform 0.3s, max-height 0.3s';
        wrap.style.opacity = '0.5';

        try {
            const res = await fetch(`${BASE_PATH}/api/messages/delete-conversation`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ other_user_id: userId })
            });

            const data = await res.json();

            if (data.success) {
                wrap.style.opacity = '0';
                wrap.style.transform = 'translateX(-100%)';
                wrap.style.maxHeight = '0';
                wrap.style.overflow = 'hidden';
                setTimeout(() => wrap.remove(), 300);
                showToast('Conversation deleted');

                // Update count
                const countEl = document.querySelector('.messages-count');
                if (countEl) {
                    const remaining = document.querySelectorAll('.messages-thread-wrap').length - 1;
                    countEl.textContent = `${remaining} conversation${remaining !== 1 ? 's' : ''}`;
                }
            } else {
                wrap.style.opacity = '1';
                showToast(data.error || 'Failed to delete');
            }
        } catch (e) {
            wrap.style.opacity = '1';
            showToast('Failed to delete conversation');
        }

        selectedUserId = null;
        selectedWrap = null;
    }

    // Close any swiped threads
    function closeSwipedThreads(except) {
        document.querySelectorAll('.messages-thread-wrap.swiped').forEach(w => {
            if (w !== except) w.classList.remove('swiped');
        });
    }

    // Handle swipe start
    function handleSwipeStart(e) {
        const wrap = e.target.closest('.messages-thread-wrap');
        if (!wrap) return;

        closeSwipedThreads(wrap);
        swipeStartX = e.touches ? e.touches[0].clientX : e.clientX;
        isSwiping = true;
    }

    // Handle swipe move
    function handleSwipeMove(e) {
        if (!isSwiping) return;
        const wrap = e.target.closest('.messages-thread-wrap');
        if (!wrap) return;

        const currentX = e.touches ? e.touches[0].clientX : e.clientX;
        const diff = swipeStartX - currentX;

        if (diff > swipeThreshold) {
            wrap.classList.add('swiped');
        } else if (diff < -20) {
            wrap.classList.remove('swiped');
        }
    }

    // Handle swipe end
    function handleSwipeEnd() {
        isSwiping = false;
    }

    // Attach swipe listeners
    if (threadList) {
        threadList.addEventListener('touchstart', handleSwipeStart, { passive: true });
        threadList.addEventListener('touchmove', handleSwipeMove, { passive: true });
        threadList.addEventListener('touchend', handleSwipeEnd);

        // Handle delete button click
        threadList.addEventListener('click', function(e) {
            const deleteBtn = e.target.closest('.msg-delete-action');
            if (deleteBtn) {
                e.preventDefault();
                e.stopPropagation();
                const wrap = deleteBtn.closest('.messages-thread-wrap');
                if (wrap) {
                    selectedUserId = wrap.dataset.userId;
                    selectedWrap = wrap;
                    showDeleteConfirm();
                }
                return;
            }

            // Handle options button click (desktop)
            const optionsBtn = e.target.closest('.messages-thread-options');
            if (optionsBtn) {
                e.preventDefault();
                e.stopPropagation();
                const wrap = optionsBtn.closest('.messages-thread-wrap');
                if (wrap) {
                    openThreadMenu(wrap);
                }
                return;
            }
        });

        // Long press for mobile (alternative to swipe)
        let longPressTimer = null;
        threadList.addEventListener('touchstart', function(e) {
            const wrap = e.target.closest('.messages-thread-wrap');
            if (!wrap) return;

            longPressTimer = setTimeout(() => {
                openThreadMenu(wrap);
            }, 600);
        }, { passive: true });

        threadList.addEventListener('touchend', () => clearTimeout(longPressTimer));
        threadList.addEventListener('touchmove', () => clearTimeout(longPressTimer));
    }

    // Menu button handlers
    threadMenuView?.addEventListener('click', function() {
        if (selectedUserId) {
            window.location.href = `${BASE_PATH}/messages/${selectedUserId}`;
        }
    });

    threadMenuDelete?.addEventListener('click', showDeleteConfirm);
    deleteCancelBtn?.addEventListener('click', closeDeleteConfirm);
    deleteConfirmBtn?.addEventListener('click', deleteConversation);

    // Close overlays on backdrop click
    threadMenuOverlay?.addEventListener('click', function(e) {
        if (e.target === this) closeThreadMenu();
    });
    deleteConfirmOverlay?.addEventListener('click', function(e) {
        if (e.target === this) closeDeleteConfirm();
    });

    // Escape key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (deleteConfirmOverlay?.classList.contains('active')) {
                closeDeleteConfirm();
            } else if (threadMenuOverlay?.classList.contains('active')) {
                closeThreadMenu();
            } else if (modal?.classList.contains('active')) {
                closeNewMessage();
            }
        }
    });

    // Close swiped threads when clicking elsewhere
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.messages-thread-wrap')) {
            closeSwipedThreads();
        }
    });
})();

// =============================================
// DESKTOP HOLOGRAPHIC INTERFACE FUNCTIONALITY
// =============================================
(function() {
    'use strict';

    const BASE_PATH = <?= json_encode(rtrim($basePath, '/')) ?>;
    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const CURRENT_USER_ID = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
    const CURRENT_USER_AVATAR = <?= json_encode($_SESSION['avatar_url'] ?? '') ?>;
    const CURRENT_USER_NAME = <?= json_encode($_SESSION['name'] ?? 'You') ?>;

    // Elements
    const holoModal = document.getElementById('holoNewMessageModal');
    const holoSearchInput = document.getElementById('holoSearchInput');
    const holoNmSearchInput = document.getElementById('holoNmSearchInput');
    const holoNmResults = document.getElementById('holoNmResults');
    const holoThreadList = document.getElementById('holoThreadList');
    const holoToast = document.getElementById('holoToast');
    const holoWelcomeState = document.getElementById('holoWelcomeState');
    const holoChatArea = document.getElementById('holoChatArea');
    const holoChatLoading = document.getElementById('holoChatLoading');
    const holoChatMessages = document.getElementById('holoChatMessages');
    const holoChatInput = document.getElementById('holoChatInput');
    const holoSendBtn = document.getElementById('holoSendBtn');
    const holoChatAvatar = document.getElementById('holoChatAvatar');
    const holoChatInitial = document.getElementById('holoChatInitial');
    const holoChatName = document.getElementById('holoChatName');
    const holoChatStatus = document.getElementById('holoChatStatus');
    const holoChatProfileLink = document.getElementById('holoChatProfileLink');
    const holoChatDeleteBtn = document.getElementById('holoChatDeleteBtn');
    const holoDeleteOverlay = document.getElementById('holoDeleteOverlay');
    const holoDeleteUserName = document.getElementById('holoDeleteUserName');
    const holoDeleteCancel = document.getElementById('holoDeleteCancel');
    const holoDeleteConfirm = document.getElementById('holoDeleteConfirm');
    const holoContextMenu = document.getElementById('holoContextMenu');
    const holoCtxOpen = document.getElementById('holoCtxOpen');
    const holoCtxProfile = document.getElementById('holoCtxProfile');
    const holoCtxDelete = document.getElementById('holoCtxDelete');

    let holoSearchTimeout = null;
    let currentChatUserId = null;
    let currentChatUserData = null;
    let messagePollingInterval = null;
    let lastMessageId = 0;

    // =============================================
    // UTILITY FUNCTIONS
    // =============================================

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatMessageTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000 && date.getDate() === now.getDate()) {
            return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }
        if (diff < 172800000) return 'Yesterday ' + date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' ' + date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function formatDateSeparator(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 86400000);

        if (diff === 0) return 'Today';
        if (diff === 1) return 'Yesterday';
        if (diff < 7) return date.toLocaleDateString([], { weekday: 'long' });
        return date.toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' });
    }

    // =============================================
    // TOAST NOTIFICATION
    // =============================================

    window.showHoloToast = function(message, duration = 2000) {
        holoToast.textContent = message;
        holoToast.classList.add('show');
        setTimeout(() => holoToast.classList.remove('show'), duration);
    };

    // =============================================
    // FILTER THREADS
    // =============================================

    if (holoSearchInput && holoThreadList) {
        holoSearchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            holoThreadList.querySelectorAll('.holo-thread').forEach(t => {
                const name = t.dataset.name || '';
                t.style.display = name.includes(q) ? '' : 'none';
            });
        });
    }

    // =============================================
    // NEW MESSAGE MODAL
    // =============================================

    window.openHoloNewMessage = function() {
        holoModal.classList.add('active');
        setTimeout(() => holoNmSearchInput?.focus(), 300);
    };

    window.closeHoloNewMessage = function() {
        holoModal.classList.remove('active');
        if (holoNmSearchInput) holoNmSearchInput.value = '';
        resetHoloResults();
    };

    function resetHoloResults() {
        holoNmResults.innerHTML = `
            <div class="holo-modal-state">
                <i class="fa-solid fa-users" style="font-size: 1.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
                <p>Type a name to search</p>
            </div>
        `;
    }

    function showHoloLoading() {
        holoNmResults.innerHTML = `
            <div class="holo-modal-state">
                <div class="holo-modal-spinner"></div>
                <p>Searching...</p>
            </div>
        `;
    }

    async function searchHoloUsers(query) {
        if (query.length < 2) { resetHoloResults(); return; }
        showHoloLoading();
        try {
            const res = await fetch(`${BASE_PATH}/members?q=${encodeURIComponent(query)}&ajax=1`, {
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            const users = data.data || data || [];
            if (users.length) {
                holoNmResults.innerHTML = users.map(u => {
                    const initial = (u.name || 'U').charAt(0).toUpperCase();
                    const avatar = u.avatar_url
                        ? `<img src="${escapeHtml(u.avatar_url)}" alt="" loading="lazy">`
                        : initial;
                    return `
                        <div class="holo-modal-user" onclick="openHoloChat(${u.id}, '${escapeHtml(u.name || 'Unknown')}', '${escapeHtml(u.avatar_url || '')}')">
                            <div class="holo-modal-user-avatar">${avatar}</div>
                            <div class="holo-modal-user-name">${escapeHtml(u.name || 'Unknown')}</div>
                        </div>
                    `;
                }).join('');
            } else {
                holoNmResults.innerHTML = `<div class="holo-modal-state"><p>No members found</p></div>`;
            }
        } catch (e) {
            holoNmResults.innerHTML = `<div class="holo-modal-state"><p>Search failed</p></div>`;
        }
    }

    if (holoNmSearchInput) {
        holoNmSearchInput.addEventListener('input', function() {
            clearTimeout(holoSearchTimeout);
            holoSearchTimeout = setTimeout(() => searchHoloUsers(this.value.trim()), 300);
        });
    }

    holoModal?.addEventListener('click', function(e) {
        if (e.target === this) closeHoloNewMessage();
    });

    // =============================================
    // INLINE CHAT FUNCTIONALITY
    // =============================================

    window.openHoloChat = async function(userId, userName, avatarUrl, isOnline = false) {
        // Close new message modal if open
        closeHoloNewMessage();

        // Set current chat user
        currentChatUserId = userId;
        currentChatUserData = { name: userName, avatar: avatarUrl, online: isOnline };

        // Update thread list to show active state
        holoThreadList?.querySelectorAll('.holo-thread').forEach(t => {
            t.classList.toggle('active', t.dataset.userId == userId);
        });

        // Update chat header
        const initial = (userName || 'U').charAt(0).toUpperCase();
        if (avatarUrl) {
            holoChatAvatar.innerHTML = `<img src="${escapeHtml(avatarUrl)}" alt="" loading="lazy">`;
        } else {
            holoChatAvatar.innerHTML = `<span>${initial}</span>`;
        }
        holoChatName.textContent = userName;
        holoChatStatus.textContent = isOnline ? 'Online' : 'Offline';
        holoChatStatus.className = 'holo-chat-status' + (isOnline ? ' online' : '');
        holoChatProfileLink.href = `${BASE_PATH}/members/${userId}`;

        // Show loading state
        holoWelcomeState.style.display = 'none';
        holoChatArea.style.display = 'none';
        holoChatLoading.style.display = 'flex';

        // Load messages
        await loadHoloMessages(userId);

        // Show chat area
        holoChatLoading.style.display = 'none';
        holoChatArea.style.display = 'flex';

        // Focus input
        holoChatInput?.focus();

        // Start polling for new messages
        startMessagePolling();
    };

    async function loadHoloMessages(userId, append = false) {
        try {
            // Use the poll endpoint with other_user_id parameter (matching thread.php)
            let url = `${BASE_PATH}/api/messages/poll?other_user_id=${userId}`;
            if (append && lastMessageId) {
                url += `&after=${lastMessageId}`;
            }

            const res = await fetch(url, {
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            // Handle both response formats: { success, messages } or { data }
            const messages = data.messages || data.data || [];

            if (messages.length > 0 || !append) {
                if (!append) {
                    holoChatMessages.innerHTML = '';
                    lastMessageId = 0;
                }

                if (messages.length > 0) {
                    renderMessages(messages, append);

                    // Update last message ID
                    lastMessageId = Math.max(...messages.map(m => m.id));
                }

                // Scroll to bottom
                if (!append || holoChatMessages.scrollTop + holoChatMessages.clientHeight >= holoChatMessages.scrollHeight - 100) {
                    holoChatMessages.scrollTop = holoChatMessages.scrollHeight;
                }

                // Mark messages as read
                if (!append && messages.length > 0) {
                    markConversationRead(userId);
                }
            }
        } catch (e) {
            console.error('Failed to load messages:', e);
            if (!append) {
                holoChatMessages.innerHTML = `
                    <div style="text-align: center; color: var(--holo-text-muted); padding: 40px;">
                        <i class="fa-solid fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p>Failed to load messages</p>
                    </div>
                `;
            }
        }
    }

    // Mark conversation as read when opening
    async function markConversationRead(userId) {
        try {
            await fetch(`${BASE_PATH}/messages/${userId}/read`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            // Update UI - remove unread badge from thread
            const thread = holoThreadList?.querySelector(`.holo-thread[data-user-id="${userId}"]`);
            if (thread) {
                thread.classList.remove('unread');
                const badge = thread.querySelector('.holo-thread-badge');
                if (badge) badge.remove();
            }
        } catch (e) {
            // Silent fail - not critical
        }
    }

    function renderMessages(messages, append = false) {
        let lastDate = null;
        const fragment = document.createDocumentFragment();

        messages.forEach(msg => {
            const msgDate = new Date(msg.created_at).toDateString();

            // Add date separator if new day
            if (msgDate !== lastDate && !append) {
                const sep = document.createElement('div');
                sep.className = 'holo-date-sep';
                sep.innerHTML = `<span>${formatDateSeparator(msg.created_at)}</span>`;
                fragment.appendChild(sep);
                lastDate = msgDate;
            }

            // Create message element
            const isSent = msg.sender_id == CURRENT_USER_ID;
            const msgEl = document.createElement('div');
            msgEl.className = `holo-message ${isSent ? 'sent' : 'received'}`;
            msgEl.dataset.messageId = msg.id;

            const avatarUrl = isSent ? CURRENT_USER_AVATAR : currentChatUserData.avatar;
            const initial = isSent ? CURRENT_USER_NAME.charAt(0).toUpperCase() : currentChatUserData.name.charAt(0).toUpperCase();
            const avatarHtml = avatarUrl
                ? `<img src="${escapeHtml(avatarUrl)}" alt="" loading="lazy">`
                : initial;

            // Check if this is a voice message
            let bubbleContent;
            if (msg.audio_url) {
                const duration = parseInt(msg.audio_duration) || 0;
                const mins = Math.floor(duration / 60);
                const secs = duration % 60;
                const durationStr = `${mins}:${secs.toString().padStart(2, '0')}`;
                bubbleContent = `
                    <div class="holo-voice-message" data-audio-url="${escapeHtml(msg.audio_url)}">
                        <button type="button" class="holo-voice-play-btn" onclick="playHoloVoiceMessage(this)">
                            <i class="fa-solid fa-play"></i>
                        </button>
                        <div class="holo-voice-waveform">
                            <span></span><span></span><span></span><span></span>
                            <span></span><span></span><span></span><span></span>
                        </div>
                        <span class="holo-voice-duration">${durationStr}</span>
                    </div>
                `;
            } else {
                bubbleContent = escapeHtml(msg.body);
            }

            msgEl.innerHTML = `
                <div class="holo-message-avatar">${avatarHtml}</div>
                <div class="holo-message-content">
                    <div class="holo-message-bubble">${bubbleContent}</div>
                    <div class="holo-message-time">${formatMessageTime(msg.created_at)}</div>
                </div>
            `;

            fragment.appendChild(msgEl);
        });

        if (append) {
            holoChatMessages.appendChild(fragment);
        } else {
            holoChatMessages.appendChild(fragment);
        }
    }

    function startMessagePolling() {
        stopMessagePolling();
        messagePollingInterval = setInterval(() => {
            if (currentChatUserId) {
                loadHoloMessages(currentChatUserId, true);
            }
        }, 5000);
    }

    function stopMessagePolling() {
        if (messagePollingInterval) {
            clearInterval(messagePollingInterval);
            messagePollingInterval = null;
        }
    }

    // =============================================
    // SEND MESSAGE
    // =============================================

    async function sendMessage() {
        const body = holoChatInput.value.trim();
        if (!body || !currentChatUserId) return;

        holoSendBtn.disabled = true;
        holoChatInput.disabled = true;

        try {
            const res = await fetch(`${BASE_PATH}/api/messages/send`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    receiver_id: currentChatUserId,
                    body: body
                })
            });

            const data = await res.json();

            if (data.success) {
                holoChatInput.value = '';
                holoChatInput.style.height = 'auto';

                // Add sent message to chat
                if (data.message) {
                    renderMessages([data.message], true);
                    lastMessageId = data.message.id;
                    holoChatMessages.scrollTop = holoChatMessages.scrollHeight;
                } else {
                    // Reload messages if no message returned
                    await loadHoloMessages(currentChatUserId);
                }

                // Update thread list preview
                updateThreadPreview(currentChatUserId, body);
            } else {
                showHoloToast(data.error || 'Failed to send message');
            }
        } catch (e) {
            showHoloToast('Failed to send message');
        }

        holoChatInput.disabled = false;
        holoSendBtn.disabled = false;
        holoChatInput.focus();
        updateSendButton();
    }

    function updateThreadPreview(userId, body) {
        const thread = holoThreadList?.querySelector(`.holo-thread[data-user-id="${userId}"]`);
        if (thread) {
            const preview = thread.querySelector('.holo-thread-preview');
            const time = thread.querySelector('.holo-thread-time');
            if (preview) preview.textContent = body.substring(0, 60) + (body.length > 60 ? '...' : '');
            if (time) time.textContent = 'Now';

            // Move thread to top
            if (holoThreadList && thread.parentNode === holoThreadList) {
                holoThreadList.insertBefore(thread, holoThreadList.firstChild);
            }
        }
    }

    function updateSendButton() {
        const hasContent = holoChatInput.value.trim().length > 0;
        holoSendBtn.disabled = !hasContent;
    }

    // Chat input event listeners
    holoChatInput?.addEventListener('input', function() {
        updateSendButton();
        // Auto-resize textarea
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 150) + 'px';
    });

    holoChatInput?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    holoSendBtn?.addEventListener('click', sendMessage);

    // =============================================
    // THREAD CLICK HANDLERS
    // =============================================

    holoThreadList?.addEventListener('click', function(e) {
        // Ignore clicks on delete button
        if (e.target.closest('.holo-thread-delete')) return;

        const thread = e.target.closest('.holo-thread');
        if (thread) {
            e.preventDefault();
            const userId = thread.dataset.userId;
            const userName = thread.dataset.userName;
            const avatar = thread.dataset.avatar;
            const online = thread.dataset.online === '1';
            openHoloChat(userId, userName, avatar, online);
        }
    });

    // =============================================
    // CONTEXT MENU
    // =============================================

    let contextMenuTarget = null;

    holoThreadList?.addEventListener('contextmenu', function(e) {
        const thread = e.target.closest('.holo-thread');
        if (thread) {
            e.preventDefault();
            showContextMenu(e.clientX, e.clientY, thread);
        }
    });

    function showContextMenu(x, y, thread) {
        contextMenuTarget = thread;
        holoContextMenu.style.left = Math.min(x, window.innerWidth - 200) + 'px';
        holoContextMenu.style.top = Math.min(y, window.innerHeight - 180) + 'px';
        holoContextMenu.classList.add('active');
    }

    function hideContextMenu() {
        holoContextMenu.classList.remove('active');
        contextMenuTarget = null;
    }

    document.addEventListener('click', function(e) {
        if (!holoContextMenu.contains(e.target)) {
            hideContextMenu();
        }
    });

    holoCtxOpen?.addEventListener('click', function() {
        if (contextMenuTarget) {
            const userId = contextMenuTarget.dataset.userId;
            const userName = contextMenuTarget.dataset.userName;
            const avatar = contextMenuTarget.dataset.avatar;
            const online = contextMenuTarget.dataset.online === '1';
            openHoloChat(userId, userName, avatar, online);
        }
        hideContextMenu();
    });

    holoCtxProfile?.addEventListener('click', function() {
        if (contextMenuTarget) {
            window.location.href = `${BASE_PATH}/members/${contextMenuTarget.dataset.userId}`;
        }
        hideContextMenu();
    });

    holoCtxDelete?.addEventListener('click', function() {
        if (contextMenuTarget) {
            showHoloDeleteConfirm(contextMenuTarget);
        }
        hideContextMenu();
    });

    // =============================================
    // DELETE FUNCTIONALITY
    // =============================================

    let deleteTarget = null;

    function showHoloDeleteConfirm(thread) {
        deleteTarget = thread;
        holoDeleteUserName.textContent = thread.dataset.userName;
        holoDeleteOverlay.classList.add('active');
    }

    function hideHoloDeleteConfirm() {
        holoDeleteOverlay.classList.remove('active');
        deleteTarget = null;
    }

    async function deleteHoloConversation() {
        if (!deleteTarget) return;

        const userId = deleteTarget.dataset.userId;
        const thread = deleteTarget;

        hideHoloDeleteConfirm();

        // Optimistic UI
        thread.style.opacity = '0.5';

        try {
            const res = await fetch(`${BASE_PATH}/api/messages/delete-conversation`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ other_user_id: userId })
            });

            const data = await res.json();

            if (data.success) {
                thread.style.transition = 'opacity 0.3s, transform 0.3s, max-height 0.3s';
                thread.style.opacity = '0';
                thread.style.transform = 'translateX(-100%)';
                setTimeout(() => thread.remove(), 300);

                // If currently viewing this conversation, go back to welcome
                if (currentChatUserId == userId) {
                    currentChatUserId = null;
                    currentChatUserData = null;
                    stopMessagePolling();
                    holoChatArea.style.display = 'none';
                    holoWelcomeState.style.display = 'flex';
                }

                // Update count
                const count = document.getElementById('holoConvCount');
                if (count) {
                    const remaining = holoThreadList.querySelectorAll('.holo-thread').length - 1;
                    count.textContent = `${remaining} conversation${remaining !== 1 ? 's' : ''}`;
                }

                showHoloToast('Conversation deleted');
            } else {
                thread.style.opacity = '1';
                showHoloToast(data.error || 'Failed to delete');
            }
        } catch (e) {
            thread.style.opacity = '1';
            showHoloToast('Failed to delete conversation');
        }
    }

    // Delete button in thread list (hover)
    holoThreadList?.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.holo-thread-delete');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();
            const thread = deleteBtn.closest('.holo-thread');
            if (thread) {
                showHoloDeleteConfirm(thread);
            }
        }
    });

    // Delete button in chat header
    holoChatDeleteBtn?.addEventListener('click', function() {
        const thread = holoThreadList?.querySelector(`.holo-thread[data-user-id="${currentChatUserId}"]`);
        if (thread) {
            showHoloDeleteConfirm(thread);
        } else if (currentChatUserId && currentChatUserData) {
            // Create a fake thread element for deletion
            deleteTarget = { dataset: { userId: currentChatUserId, userName: currentChatUserData.name } };
            holoDeleteUserName.textContent = currentChatUserData.name;
            holoDeleteOverlay.classList.add('active');
        }
    });

    holoDeleteCancel?.addEventListener('click', hideHoloDeleteConfirm);
    holoDeleteConfirm?.addEventListener('click', deleteHoloConversation);

    holoDeleteOverlay?.addEventListener('click', function(e) {
        if (e.target === this) hideHoloDeleteConfirm();
    });

    // =============================================
    // VOICE RECORDING FUNCTIONALITY
    // =============================================

    const holoVoiceBtn = document.getElementById('holoVoiceBtn');
    const holoVoiceOverlay = document.getElementById('holoVoiceOverlay');
    const holoVoiceTime = document.getElementById('holoVoiceTime');
    const holoVoiceCancel = document.getElementById('holoVoiceCancel');
    const holoVoiceSend = document.getElementById('holoVoiceSend');

    let mediaRecorder = null;
    let audioChunks = [];
    let audioStream = null;
    let recordingStartTime = 0;
    let recordingTimer = null;
    let currentAudio = null;
    let currentPlayingBtn = null;

    // Check if voice recording is supported
    function isVoiceRecordingSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
    }

    // Hide voice button if not supported
    if (!isVoiceRecordingSupported() && holoVoiceBtn) {
        holoVoiceBtn.style.display = 'none';
    }

    // Start recording
    async function startHoloRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            return;
        }

        try {
            if (!isVoiceRecordingSupported()) {
                showHoloToast('Voice recording is not supported in your browser');
                return;
            }

            audioStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: 44100
                }
            });

            let mimeType = 'audio/webm';
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mimeType = 'audio/webm;codecs=opus';
            } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                mimeType = 'audio/mp4';
            } else if (MediaRecorder.isTypeSupported('audio/ogg')) {
                mimeType = 'audio/ogg';
            }

            mediaRecorder = new MediaRecorder(audioStream, { mimeType });
            audioChunks = [];

            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) {
                    audioChunks.push(e.data);
                }
            };

            mediaRecorder.start(100);
            recordingStartTime = Date.now();

            // Show overlay and update button
            holoVoiceOverlay.classList.add('active');
            holoVoiceBtn.classList.add('recording');

            // Start timer
            recordingTimer = setInterval(updateHoloRecordingTime, 100);

        } catch (err) {
            console.error('Failed to start recording:', err);
            showHoloToast('Could not access microphone');
        }
    }

    // Update recording time display
    function updateHoloRecordingTime() {
        const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
        const mins = Math.floor(elapsed / 60);
        const secs = elapsed % 60;
        holoVoiceTime.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    // Cancel recording
    function cancelHoloRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
        }
        clearInterval(recordingTimer);
        holoVoiceOverlay.classList.remove('active');
        holoVoiceBtn.classList.remove('recording');
        audioChunks = [];
        holoVoiceTime.textContent = '0:00';

        // Stop audio stream
        if (audioStream) {
            audioStream.getTracks().forEach(track => track.stop());
            audioStream = null;
        }
    }

    // Send voice message
    async function sendHoloVoiceMessage() {
        if (!mediaRecorder || mediaRecorder.state !== 'recording') return;

        const duration = Math.floor((Date.now() - recordingStartTime) / 1000);
        clearInterval(recordingTimer);

        mediaRecorder.stop();
        mediaRecorder.onstop = async () => {
            holoVoiceOverlay.classList.remove('active');
            holoVoiceBtn.classList.remove('recording');

            if (audioChunks.length === 0 || !currentChatUserId) {
                holoVoiceTime.textContent = '0:00';
                return;
            }

            const audioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType });
            const reader = new FileReader();

            // Create temp blob URL for immediate playback
            const blobUrl = URL.createObjectURL(audioBlob);

            // Format duration
            const mins = Math.floor(duration / 60);
            const secs = duration % 60;
            const durationStr = `${mins}:${secs.toString().padStart(2, '0')}`;

            // Optimistic UI - add voice message bubble
            const tempId = 'temp-voice-' + Date.now();
            const avatarHtml = CURRENT_USER_AVATAR
                ? `<img src="${escapeHtml(CURRENT_USER_AVATAR)}" alt="" loading="lazy">`
                : CURRENT_USER_NAME.charAt(0).toUpperCase();

            const bubble = document.createElement('div');
            bubble.className = 'holo-message sent';
            bubble.id = tempId;
            bubble.innerHTML = `
                <div class="holo-message-avatar">${avatarHtml}</div>
                <div class="holo-message-content">
                    <div class="holo-message-bubble">
                        <div class="holo-voice-message" data-audio-url="${blobUrl}">
                            <button type="button" class="holo-voice-play-btn" onclick="playHoloVoiceMessage(this)">
                                <i class="fa-solid fa-play"></i>
                            </button>
                            <div class="holo-voice-waveform">
                                <span></span><span></span><span></span><span></span>
                                <span></span><span></span><span></span><span></span>
                            </div>
                            <span class="holo-voice-duration">${durationStr}</span>
                        </div>
                    </div>
                    <div class="holo-message-time">Sending...</div>
                </div>
            `;
            holoChatMessages.appendChild(bubble);
            holoChatMessages.scrollTop = holoChatMessages.scrollHeight;

            // Send to server using form-urlencoded (required by VoiceMessageController)
            reader.onloadend = async () => {
                const base64data = reader.result.split(',')[1];
                try {
                    const res = await fetch(`${BASE_PATH}/api/messages/voice`, {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            csrf_token: CSRF_TOKEN,
                            receiver_id: currentChatUserId,
                            audio_data: base64data,
                            mime_type: mediaRecorder.mimeType,
                            duration: duration
                        })
                    });

                    const data = await res.json();

                    if (data.success) {
                        // Update the temp bubble with real data
                        const timeEl = bubble.querySelector('.holo-message-time');
                        if (timeEl) timeEl.textContent = 'Just now';

                        const voiceMsg = bubble.querySelector('.holo-voice-message');
                        if (voiceMsg && data.audio_url) {
                            voiceMsg.dataset.audioUrl = data.audio_url;
                        }

                        if (data.message_id) {
                            bubble.dataset.messageId = data.message_id;
                            lastMessageId = Math.max(lastMessageId, data.message_id);
                        }

                        // Update thread preview
                        updateThreadPreview(currentChatUserId, '🎤 Voice message');
                    } else {
                        bubble.remove();
                        showHoloToast(data.error || 'Failed to send voice message');
                    }
                } catch (e) {
                    bubble.remove();
                    showHoloToast('Failed to send voice message');
                }
            };

            reader.readAsDataURL(audioBlob);
            audioChunks = [];
            holoVoiceTime.textContent = '0:00';

            // Stop audio stream
            if (audioStream) {
                audioStream.getTracks().forEach(track => track.stop());
                audioStream = null;
            }
        };
    }

    // Play voice message
    window.playHoloVoiceMessage = function(btn) {
        const voiceMessage = btn.closest('.holo-voice-message');
        const audioUrl = voiceMessage?.dataset.audioUrl;

        if (!audioUrl) {
            console.error('No audio URL found');
            return;
        }

        // If same audio is playing, pause it
        if (currentPlayingBtn === btn && currentAudio && !currentAudio.paused) {
            currentAudio.pause();
            btn.querySelector('i').className = 'fa-solid fa-play';
            btn.classList.remove('playing');
            return;
        }

        // Stop any currently playing audio
        if (currentAudio) {
            currentAudio.pause();
            if (currentPlayingBtn) {
                currentPlayingBtn.querySelector('i').className = 'fa-solid fa-play';
                currentPlayingBtn.classList.remove('playing');
            }
        }

        // Create and play new audio
        currentAudio = new Audio(audioUrl);
        currentPlayingBtn = btn;

        btn.querySelector('i').className = 'fa-solid fa-pause';
        btn.classList.add('playing');

        currentAudio.play().catch(err => {
            console.error('Audio playback failed:', err);
            btn.querySelector('i').className = 'fa-solid fa-play';
            btn.classList.remove('playing');
            showHoloToast('Failed to play voice message');
        });

        currentAudio.onended = () => {
            btn.querySelector('i').className = 'fa-solid fa-play';
            btn.classList.remove('playing');
            currentPlayingBtn = null;
        };
    };

    // Event listeners for voice recording
    holoVoiceBtn?.addEventListener('click', () => {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            // If already recording, don't start new
            return;
        }
        startHoloRecording();
    });

    holoVoiceCancel?.addEventListener('click', cancelHoloRecording);
    holoVoiceSend?.addEventListener('click', sendHoloVoiceMessage);

    // =============================================
    // KEYBOARD SHORTCUTS
    // =============================================

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (holoVoiceOverlay?.classList.contains('active')) {
                cancelHoloRecording();
            } else if (holoDeleteOverlay?.classList.contains('active')) {
                hideHoloDeleteConfirm();
            } else if (holoContextMenu?.classList.contains('active')) {
                hideContextMenu();
            } else if (holoModal?.classList.contains('active')) {
                closeHoloNewMessage();
            }
        }
    });

    // =============================================
    // CLEANUP ON PAGE UNLOAD
    // =============================================

    // Cleanup function to remove messages-page classes
    function cleanupMessagesPage() {
        document.documentElement.classList.remove('messages-page');
        document.body.classList.remove('messages-page', 'no-ptr', 'messages-fullscreen');
        stopMessagePolling();
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
        if (audioStream) {
            audioStream.getTracks().forEach(track => track.stop());
        }
    }

    // Clean up when navigating away
    window.addEventListener('beforeunload', cleanupMessagesPage);

    // Also handle pagehide for bfcache
    window.addEventListener('pagehide', cleanupMessagesPage);
})();
</script>

<!-- Clean up messages-page class on other pages (handles bfcache restoration) -->
<script>
(function() {
    // When returning to a cached page, remove messages-page classes if we're not on messages
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.getEntriesByType('navigation')[0]?.type === 'back_forward')) {
            // Page was restored from bfcache - classes should already be removed by pagehide
            // But double-check we're on the messages page before re-adding
            if (!window.location.pathname.includes('/messages')) {
                document.documentElement.classList.remove('messages-page');
                document.body.classList.remove('messages-page', 'no-ptr', 'messages-fullscreen');
            }
        }
    });
})();
</script>
</main>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
