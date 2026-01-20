<?php
// Federation Messages Thread - CivicOne WCAG 2.1 AA
$pageTitle = $pageTitle ?? "Federated Chat";
$hideHero = true;

Nexus\Core\SEO::setTitle($pageTitle);
Nexus\Core\SEO::setDescription('Private conversation with a member from a partner timebank.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$messages = $messages ?? [];
$otherUser = $otherUser ?? [];
$otherTenantId = $otherTenantId ?? 0;
$canMessage = $canMessage ?? false;
$cannotMessageReason = $cannotMessageReason ?? '';

$otherName = $otherUser['name'] ?? 'Member';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($otherName) . '&background=00796B&color=fff&size=200';
$otherAvatar = !empty($otherUser['avatar_url']) ? $otherUser['avatar_url'] : $fallbackAvatar;
$currentUserId = $_SESSION['user_id'] ?? 0;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-thread-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation/messages" class="back-link" aria-label="Return to inbox">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Inbox
        </a>

        <!-- Thread Header -->
        <header class="thread-header" role="banner">
            <img src="<?= htmlspecialchars($otherAvatar) ?>"
                 onerror="this.src='<?= $fallbackAvatar ?>'"
                 alt=""
                 class="thread-avatar"
                 loading="lazy">
            <div class="thread-info">
                <h1 class="thread-name"><?= htmlspecialchars($otherName) ?></h1>
                <span class="thread-tenant">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <?= htmlspecialchars($otherUser['tenant_name'] ?? 'Partner Timebank') ?>
                </span>
            </div>
            <a href="<?= $basePath ?>/federation/members/<?= $otherUser['id'] ?? 0 ?>" class="view-profile-btn" aria-label="View <?= htmlspecialchars($otherName) ?>'s profile">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                <span>View Profile</span>
            </a>
        </header>

        <!-- Messages Container -->
        <div class="messages-container" id="messages-container" role="log" aria-label="Message history" aria-live="polite">
            <?php if (!empty($messages)): ?>
                <?php
                $lastType = null;
                foreach ($messages as $msg):
                    $isSent = $msg['message_type'] === 'sent';
                    $groupClass = $isSent ? 'sent' : 'received';
                    $msgTime = strtotime($msg['created_at']);
                    $isoTime = date('c', $msgTime);
                    $displayTime = date('M j, g:i a', $msgTime);

                    // Start new group if direction changed
                    if ($lastType !== $groupClass):
                        if ($lastType !== null) echo '</div>';
                ?>
                    <div class="message-group <?= $groupClass ?>" role="group" aria-label="<?= $isSent ? 'Your messages' : 'Messages from ' . htmlspecialchars($otherName) ?>">
                <?php
                    endif;
                    $lastType = $groupClass;
                ?>
                        <div class="message-bubble" aria-label="<?= $isSent ? 'You wrote' : htmlspecialchars($otherName) . ' wrote' ?>">
                            <?= nl2br(htmlspecialchars($msg['body'] ?? '')) ?>
                        </div>
                        <time class="message-time" datetime="<?= $isoTime ?>">
                            <?= $displayTime ?>
                        </time>
                <?php endforeach; ?>
                    </div>
            <?php else: ?>
                <div class="empty-messages" role="status">
                    <i class="fa-solid fa-comment-dots" aria-hidden="true"></i>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Compose Form -->
        <?php if ($canMessage): ?>
            <form action="<?= $basePath ?>/federation/messages/send" method="POST" class="compose-form" aria-label="Compose message">
                <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?? 0 ?>">
                <input type="hidden" name="receiver_tenant_id" value="<?= $otherTenantId ?>">

                <label for="message-input" class="visually-hidden">Type your message</label>
                <textarea name="body"
                          class="compose-input"
                          placeholder="Type your message..."
                          required
                          rows="1"
                          id="message-input"
                          aria-describedby="message-help"></textarea>
                <span id="message-help" class="visually-hidden">Press Enter to send, Shift+Enter for new line</span>

                <button type="submit" class="send-btn" aria-label="Send message">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <span>Send</span>
                </button>
            </form>
        <?php else: ?>
            <div class="messaging-disabled" role="alert">
                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                <span><?= htmlspecialchars($cannotMessageReason ?: 'Messaging is not available with this member.') ?></span>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="/assets/js/federation-thread.js?v=<?= time() ?>"></script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
