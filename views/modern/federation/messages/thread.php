<?php
// Federation Messages Thread - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Chat";
$hideHero = true;

Nexus\Core\SEO::setTitle($pageTitle);
Nexus\Core\SEO::setDescription('Private conversation with a member from a partner timebank.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$messages = $messages ?? [];
$otherUser = $otherUser ?? [];
$otherTenantId = $otherTenantId ?? 0;
$canMessage = $canMessage ?? false;
$cannotMessageReason = $cannotMessageReason ?? '';

$otherName = $otherUser['name'] ?? 'Member';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($otherName) . '&background=8b5cf6&color=fff&size=200';
$otherAvatar = !empty($otherUser['avatar_url']) ? $otherUser['avatar_url'] : $fallbackAvatar;
$currentUserId = $_SESSION['user_id'] ?? 0;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-thread-wrapper">

        <style>
            /* ============================================
               FEDERATED MESSAGE THREAD - Glassmorphism
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

            #fed-thread-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px 0;
                display: flex;
                flex-direction: column;
                height: calc(100vh - 160px);
            }

            /* Back Link */
            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 16px;
                transition: color 0.2s;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            /* Thread Header */
            .thread-header {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 20px;
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.4);
                border-radius: 20px;
                margin-bottom: 16px;
            }

            [data-theme="dark"] .thread-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.15) 0%,
                        rgba(168, 85, 247, 0.15) 50%,
                        rgba(192, 132, 252, 0.1) 100%);
                border-color: rgba(255, 255, 255, 0.1);
            }

            .thread-avatar {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                border: 3px solid rgba(139, 92, 246, 0.3);
                object-fit: cover;
            }

            .thread-info {
                flex: 1;
            }

            .thread-name {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 4px 0;
            }

            .thread-tenant {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 12px;
                background: rgba(139, 92, 246, 0.1);
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 600;
                color: #8b5cf6;
            }

            [data-theme="dark"] .thread-tenant {
                background: rgba(139, 92, 246, 0.2);
                color: #a78bfa;
            }

            .view-profile-btn {
                padding: 10px 20px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(168, 85, 247, 0.1));
                color: #8b5cf6;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 600;
                font-size: 0.9rem;
                transition: all 0.3s ease;
                border: 2px solid rgba(139, 92, 246, 0.3);
            }

            .view-profile-btn:hover {
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                border-color: transparent;
            }

            [data-theme="dark"] .view-profile-btn {
                background: rgba(139, 92, 246, 0.2);
                color: #a78bfa;
            }

            /* Messages Container */
            .messages-container {
                flex: 1;
                overflow-y: auto;
                padding: 20px;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.6),
                        rgba(255, 255, 255, 0.4));
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 20px;
                margin-bottom: 16px;
            }

            [data-theme="dark"] .messages-container {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            /* Message Bubbles */
            .message-group {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 20px;
            }

            .message-group.sent {
                align-items: flex-end;
            }

            .message-group.received {
                align-items: flex-start;
            }

            .message-bubble {
                max-width: 75%;
                padding: 12px 16px;
                border-radius: 18px;
                font-size: 0.95rem;
                line-height: 1.5;
            }

            .message-group.sent .message-bubble {
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                border-bottom-right-radius: 4px;
            }

            .message-group.received .message-bubble {
                background: rgba(139, 92, 246, 0.1);
                color: var(--htb-text-main);
                border-bottom-left-radius: 4px;
            }

            [data-theme="dark"] .message-group.received .message-bubble {
                background: rgba(139, 92, 246, 0.2);
            }

            .message-time {
                font-size: 0.75rem;
                color: var(--htb-text-muted);
                margin-top: 4px;
            }

            .message-group.sent .message-time {
                text-align: right;
            }

            /* Empty Messages */
            .empty-messages {
                text-align: center;
                padding: 40px 20px;
                color: var(--htb-text-muted);
            }

            .empty-messages i {
                font-size: 3rem;
                color: #8b5cf6;
                margin-bottom: 16px;
            }

            /* Compose Form */
            .compose-form {
                display: flex;
                gap: 12px;
                padding: 20px;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 20px;
            }

            [data-theme="dark"] .compose-form {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .compose-input {
                flex: 1;
                padding: 14px 18px;
                border: 2px solid rgba(139, 92, 246, 0.2);
                border-radius: 16px;
                font-size: 1rem;
                background: rgba(255, 255, 255, 0.8);
                color: var(--htb-text-main);
                resize: none;
                min-height: 52px;
                max-height: 150px;
            }

            .compose-input:focus {
                outline: none;
                border-color: rgba(139, 92, 246, 0.5);
                box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
            }

            [data-theme="dark"] .compose-input {
                background: rgba(15, 23, 42, 0.8);
                border-color: rgba(139, 92, 246, 0.3);
            }

            .send-btn {
                padding: 14px 24px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                border: none;
                border-radius: 16px;
                font-weight: 700;
                font-size: 0.95rem;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .send-btn:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            }

            .send-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            /* Messaging Disabled Notice */
            .messaging-disabled {
                text-align: center;
                padding: 20px;
                background: rgba(234, 179, 8, 0.1);
                border: 1px solid rgba(234, 179, 8, 0.3);
                border-radius: 16px;
                color: #ca8a04;
            }

            [data-theme="dark"] .messaging-disabled {
                background: rgba(234, 179, 8, 0.15);
                color: #fbbf24;
            }

            /* Touch Targets */
            .send-btn,
            .view-profile-btn,
            .compose-input {
                min-height: 44px;
            }

            .compose-input {
                font-size: 16px !important;
            }

            /* Focus Visible */
            .send-btn:focus-visible,
            .view-profile-btn:focus-visible,
            .compose-input:focus-visible,
            .back-link:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            /* Responsive */
            @media (max-width: 640px) {
                #fed-thread-wrapper {
                    height: calc(100vh - 200px);
                    padding: 10px;
                }

                .thread-header {
                    padding: 16px;
                }

                .thread-avatar {
                    width: 48px;
                    height: 48px;
                }

                .thread-name {
                    font-size: 1.1rem;
                }

                .view-profile-btn {
                    display: none;
                }

                .message-bubble {
                    max-width: 85%;
                }

                .compose-form {
                    padding: 12px;
                }
            }
        </style>

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation/messages" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Inbox
        </a>

        <!-- Thread Header -->
        <div class="thread-header">
            <img src="<?= htmlspecialchars($otherAvatar) ?>"
                 onerror="this.src='<?= $fallbackAvatar ?>'"
                 alt="<?= htmlspecialchars($otherName) ?>"
                 class="thread-avatar">
            <div class="thread-info">
                <h2 class="thread-name"><?= htmlspecialchars($otherName) ?></h2>
                <span class="thread-tenant">
                    <i class="fa-solid fa-building"></i>
                    <?= htmlspecialchars($otherUser['tenant_name'] ?? 'Partner Timebank') ?>
                </span>
            </div>
            <a href="<?= $basePath ?>/federation/members/<?= $otherUser['id'] ?? 0 ?>" class="view-profile-btn">
                <i class="fa-solid fa-user"></i>
                View Profile
            </a>
        </div>

        <!-- Messages Container -->
        <div class="messages-container" id="messages-container">
            <?php if (!empty($messages)): ?>
                <?php
                $lastType = null;
                foreach ($messages as $msg):
                    $isSent = $msg['message_type'] === 'sent';
                    $groupClass = $isSent ? 'sent' : 'received';

                    // Start new group if direction changed
                    if ($lastType !== $groupClass):
                        if ($lastType !== null) echo '</div>';
                ?>
                    <div class="message-group <?= $groupClass ?>">
                <?php
                    endif;
                    $lastType = $groupClass;
                ?>
                        <div class="message-bubble">
                            <?= nl2br(htmlspecialchars($msg['body'] ?? '')) ?>
                        </div>
                        <span class="message-time">
                            <?= date('M j, g:i a', strtotime($msg['created_at'])) ?>
                        </span>
                <?php endforeach; ?>
                    </div>
            <?php else: ?>
                <div class="empty-messages">
                    <i class="fa-solid fa-comment-dots"></i>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Compose Form -->
        <?php if ($canMessage): ?>
            <form action="<?= $basePath ?>/federation/messages/send" method="POST" class="compose-form">
                <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?? 0 ?>">
                <input type="hidden" name="receiver_tenant_id" value="<?= $otherTenantId ?>">

                <textarea name="body"
                          class="compose-input"
                          placeholder="Type your message..."
                          required
                          rows="1"
                          id="message-input"></textarea>

                <button type="submit" class="send-btn">
                    <i class="fa-solid fa-paper-plane"></i>
                    Send
                </button>
            </form>
        <?php else: ?>
            <div class="messaging-disabled">
                <i class="fa-solid fa-ban" style="margin-right: 8px;"></i>
                <?= htmlspecialchars($cannotMessageReason ?: 'Messaging is not available with this member.') ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to bottom
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }

    // Auto-expand textarea
    const textarea = document.getElementById('message-input');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        });

        // Submit on Enter (but not Shift+Enter)
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    }
});

// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/footer.php'; ?>
