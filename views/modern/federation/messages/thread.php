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
