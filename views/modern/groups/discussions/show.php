<?php
// Phoenix View: Show Discussion (Mobile-First Design)
// Path: views/modern/groups/discussions/show.php

$hTitle = 'Discussion';
$hSubtitle = htmlspecialchars($group['name']);
$hGradient = 'htb-hero-gradient-hub';

require __DIR__ . '/../../../layouts/header.php';

// Determine current user for bubble alignment
$currentUserId = $_SESSION['user_id'] ?? 0;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Mobile-First Discussion Styles -->
<style>
    /* ============================================
       GOLD STANDARD - Native App Features
       ============================================ */

    /* Offline Banner */
    .offline-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 10001;
        padding: 12px 20px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transform: translateY(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .offline-banner.visible {
        transform: translateY(0);
    }

    /* Content Reveal Animation */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .discussion-chat-card {
        animation: fadeInUp 0.4s ease-out;
    }

    /* Button Press States */
    .discussion-reply-btn:active,
    .discussion-action-btn:active,
    .discussion-back-btn:active,
    button:active {
        transform: scale(0.96) !important;
        transition: transform 0.1s ease !important;
    }

    /* Touch Targets - WCAG 2.1 AA (44px minimum) */
    .discussion-reply-btn,
    .discussion-action-btn,
    .discussion-back-btn,
    .discussion-reply-input,
    button {
        min-height: 44px;
    }

    .discussion-reply-input {
        font-size: 16px !important; /* Prevent iOS zoom */
    }

    /* Focus Visible */
    .discussion-reply-btn:focus-visible,
    .discussion-action-btn:focus-visible,
    .discussion-back-btn:focus-visible,
    .discussion-reply-input:focus-visible,
    button:focus-visible,
    a:focus-visible {
        outline: 3px solid rgba(219, 39, 119, 0.5);
        outline-offset: 2px;
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile Responsive - Gold Standard */
    @media (max-width: 768px) {
        .discussion-reply-btn,
        .discussion-back-btn,
        button {
            min-height: 48px;
        }
    }

    /* ============================================
       DISCUSSION PAGE - MOBILE FIRST DESIGN
       ============================================ */
    .discussion-page-wrapper {
        padding-top: 120px;
        padding-bottom: 40px;
        max-width: 900px;
        position: relative;
        z-index: 20;
    }

    /* Back Navigation */
    .discussion-back-nav {
        margin-bottom: 16px;
    }

    .discussion-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 12px;
        color: #64748b;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .discussion-back-btn:hover {
        background: white;
        color: #db2777;
    }

    /* Chat Card Container */
    .discussion-chat-card {
        height: calc(100vh - 200px);
        min-height: 400px;
        max-height: 700px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.6);
        border-radius: 20px;
    }

    /* Chat Header */
    .discussion-header {
        padding: 16px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        z-index: 10;
        flex-shrink: 0;
    }

    .discussion-header-info {
        flex: 1;
        min-width: 0;
    }

    .discussion-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 800;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .discussion-meta {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 4px;
    }

    .discussion-header-actions {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }

    .discussion-action-btn {
        width: 38px;
        height: 38px;
        padding: 0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
    }

    /* Chat Stream */
    .discussion-stream {
        flex-grow: 1;
        overflow-y: auto;
        padding: 20px;
        background: rgba(255, 255, 255, 0.4);
        display: flex;
        flex-direction: column;
        gap: 12px;
        -webkit-overflow-scrolling: touch;
    }

    .discussion-stream::-webkit-scrollbar {
        width: 4px;
    }

    .discussion-stream::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 2px;
    }

    /* Chat Message */
    .discussion-message {
        display: flex;
        gap: 10px;
        max-width: 85%;
        width: 100%;
    }

    .discussion-message.me {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .discussion-message.others {
        align-self: flex-start;
    }

    .discussion-avatar {
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        object-fit: cover;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .discussion-avatar-placeholder {
        width: 36px;
        height: 36px;
        background: white;
        color: #db2777;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .discussion-bubble-wrap {
        display: flex;
        flex-direction: column;
    }

    .discussion-message.me .discussion-bubble-wrap {
        align-items: flex-end;
    }

    .discussion-message.others .discussion-bubble-wrap {
        align-items: flex-start;
    }

    .discussion-author {
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 4px;
        margin-left: 4px;
    }

    .discussion-bubble {
        padding: 12px 16px;
        border-radius: 16px;
        font-size: 0.95rem;
        line-height: 1.5;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        word-wrap: break-word;
    }

    .discussion-bubble.me {
        background: linear-gradient(135deg, #db2777 0%, #be185d 100%);
        color: white;
        border-bottom-right-radius: 4px;
    }

    .discussion-bubble.others {
        background: white;
        color: #334155;
        border-bottom-left-radius: 4px;
    }

    .discussion-time {
        font-size: 0.65rem;
        margin-top: 4px;
        text-align: right;
        opacity: 0.7;
    }

    .discussion-bubble.me .discussion-time {
        color: rgba(255, 255, 255, 0.85);
    }

    .discussion-bubble.others .discussion-time {
        color: #94a3b8;
    }

    /* Reply Dock */
    .discussion-reply-dock {
        padding: 14px 16px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        flex-shrink: 0;
    }

    .discussion-reply-form {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }

    .discussion-reply-input {
        flex-grow: 1;
        width: 100%;
        padding: 12px 18px;
        border-radius: 24px;
        border: 2px solid transparent;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        resize: none;
        min-height: 48px;
        max-height: 120px;
        font-size: 0.95rem;
        font-family: inherit;
        outline: none;
        box-sizing: border-box;
        transition: all 0.2s ease;
    }

    .discussion-reply-input:focus {
        border-color: #fbcfe8;
        box-shadow: 0 0 0 4px rgba(219, 39, 119, 0.1);
    }

    .discussion-reply-btn {
        flex-shrink: 0;
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #db2777, #ec4899);
        border: none;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(219, 39, 119, 0.35);
        -webkit-tap-highlight-color: transparent;
        transition: transform 0.2s ease;
    }

    .discussion-reply-btn:active {
        transform: scale(0.95);
    }

    .discussion-locked-msg {
        text-align: center;
        color: #64748b;
        padding: 12px;
        font-size: 0.9rem;
    }

    .discussion-locked-msg a {
        color: #db2777;
        font-weight: 700;
        text-decoration: none;
    }

    /* Mobile Optimizations */
    @media (max-width: 768px) {
        .discussion-page-wrapper {
            padding: 100px 12px 30px 12px;
        }

        .discussion-chat-card {
            height: calc(100vh - 160px);
            min-height: 350px;
            max-height: none;
            border-radius: 16px;
        }

        .discussion-header {
            padding: 12px 14px;
        }

        .discussion-title {
            font-size: 0.95rem;
        }

        .discussion-meta {
            font-size: 0.75rem;
        }

        .discussion-header-actions {
            gap: 6px;
        }

        .discussion-action-btn {
            width: 34px;
            height: 34px;
        }

        .discussion-stream {
            padding: 14px;
            gap: 10px;
        }

        .discussion-message {
            max-width: 90%;
            gap: 8px;
        }

        .discussion-avatar,
        .discussion-avatar-placeholder {
            width: 32px;
            height: 32px;
            font-size: 0.8rem;
        }

        .discussion-bubble {
            padding: 10px 14px;
            font-size: 0.9rem;
        }

        .discussion-reply-dock {
            padding: 12px;
            padding-bottom: max(12px, env(safe-area-inset-bottom));
        }

        .discussion-reply-input {
            padding: 10px 16px;
            min-height: 44px;
        }

        .discussion-reply-btn {
            width: 44px;
            height: 44px;
        }

        .discussion-back-btn {
            padding: 8px 14px;
            font-size: 0.85rem;
        }
    }

    @media (max-width: 480px) {
        .discussion-page-wrapper {
            padding: 95px 8px 20px 8px;
        }

        .discussion-avatar,
        .discussion-avatar-placeholder {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            font-size: 0.75rem;
        }

        .discussion-message {
            gap: 6px;
        }
    }

    /* Dark Mode */
    [data-theme="dark"] .discussion-chat-card {
        border-color: rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] .discussion-header {
        background: rgba(30, 41, 59, 0.95);
    }

    [data-theme="dark"] .discussion-stream {
        background: rgba(15, 23, 42, 0.6);
    }

    [data-theme="dark"] .discussion-bubble.others {
        background: rgba(51, 65, 85, 0.9);
        color: #e2e8f0;
    }

    [data-theme="dark"] .discussion-reply-dock {
        background: rgba(30, 41, 59, 0.95);
    }

    [data-theme="dark"] .discussion-reply-input {
        background: rgba(51, 65, 85, 0.8);
        color: #e2e8f0;
    }
</style>

<div class="htb-container discussion-page-wrapper">

    <!-- Back Navigation -->
    <div class="discussion-back-nav">
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>?tab=discussions" class="discussion-back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Hub</span>
        </a>
    </div>

    <!-- MAIN CHAT CARD -->
    <div class="htb-card discussion-chat-card">

        <!-- HEADER -->
        <div class="discussion-header">
            <div class="discussion-header-info">
                <h1 class="discussion-title"><?= htmlspecialchars($discussion['title']) ?></h1>
                <div class="discussion-meta">
                    Started by <strong><?= htmlspecialchars($discussion['author_name']) ?></strong> &bull; <?= count($posts) ?> messages
                </div>
            </div>

            <div class="discussion-header-actions">
                <button class="discussion-action-btn" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                </button>
                <button class="discussion-action-btn" title="More options">
                    <i class="fa-solid fa-ellipsis"></i>
                </button>
            </div>
        </div>

        <!-- CHAT STREAM -->
        <div id="chatStream" class="discussion-stream">
            <?php foreach ($posts as $post):
                $isMe = ($post['user_id'] == $currentUserId);
                $msgClass = $isMe ? 'me' : 'others';
            ?>
                <div class="discussion-message <?= $msgClass ?>">
                    <?php if (!$isMe): ?>
                        <div>
                            <?php if (!empty($post['author_avatar'])): ?>
                                <img src="<?= htmlspecialchars($post['author_avatar']) ?>" loading="lazy" class="discussion-avatar" alt="">
                            <?php else: ?>
                                <div class="discussion-avatar-placeholder">
                                    <?= strtoupper(substr($post['author_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="discussion-bubble-wrap">
                        <?php if (!$isMe): ?>
                            <div class="discussion-author"><?= htmlspecialchars($post['author_name']) ?></div>
                        <?php endif; ?>

                        <div class="discussion-bubble <?= $msgClass ?>">
                            <?= nl2br(htmlspecialchars($post['content'])) ?>
                            <div class="discussion-time">
                                <?= date('g:i A', strtotime($post['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- REPLY DOCK -->
        <div class="discussion-reply-dock">
            <?php if (isset($_SESSION['user_id']) && $isMember): ?>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/<?= $discussion['id'] ?>/reply" method="POST" class="discussion-reply-form">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <textarea name="content" class="discussion-reply-input" rows="1" placeholder="Type your message..." required oninput="this.style.height = ''; this.style.height = Math.min(this.scrollHeight, 120) + 'px'"></textarea>
                    <button type="submit" class="discussion-reply-btn">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            <?php else: ?>
                <div class="discussion-locked-msg">
                    <i class="fa-solid fa-lock"></i> Only members can reply.
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>">Join Hub</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Auto-scroll to bottom -->
<script>
    const stream = document.getElementById('chatStream');
    if (stream) {
        stream.scrollTop = stream.scrollHeight;
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
            alert('You are offline. Please connect to the internet to post a reply.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.discussion-reply-btn, .discussion-action-btn, .discussion-back-btn, button').forEach(btn => {
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
</script>

<?php require __DIR__ . '/../../../layouts/footer.php'; ?>
