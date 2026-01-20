<?php
// Federation Messages Inbox - CivicOne WCAG 2.1 AA
$pageTitle = $pageTitle ?? "Federated Messages";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Messages - Partner Timebank Conversations');
Nexus\Core\SEO::setDescription('View and manage your conversations with members from partner timebanks.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$conversations = $conversations ?? [];
$unreadCount = $unreadCount ?? 0;

function timeAgo($datetime) {
    if (empty($datetime)) return '';
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'd';
    return date('M j', $time);
}
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-messages-wrapper">

        <!-- Back to Messages -->
        <a href="<?= $basePath ?>/messages" class="back-link" aria-label="Return to local messages">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Messages
        </a>

        <!-- Header -->
        <header class="messages-header" role="banner">
            <h1>
                <i class="fa-solid fa-globe" aria-hidden="true"></i>
                Federated Messages
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge" role="status" aria-label="<?= $unreadCount ?> unread message<?= $unreadCount !== 1 ? 's' : '' ?>"><?= $unreadCount ?></span>
                <?php endif; ?>
            </h1>
            <a href="<?= $basePath ?>/federation/members" class="find-members-btn" aria-label="Find new members to message">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                Find Members
            </a>
        </header>

        <!-- Conversations List -->
        <?php if (!empty($conversations)): ?>
            <div class="conversations-list" role="list" aria-label="Message conversations">
                <?php foreach ($conversations as $conv): ?>
                    <?php
                    $isUnread = ($conv['unread_count'] ?? 0) > 0;
                    $fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($conv['sender_name'] ?? 'User') . '&background=00796B&color=fff&size=100';
                    $avatar = !empty($conv['sender_avatar']) ? $conv['sender_avatar'] : $fallbackAvatar;
                    $threadUrl = $basePath . '/federation/messages/' . $conv['sender_user_id'] . '?tenant=' . $conv['sender_tenant_id'];
                    $timeAgo = timeAgo($conv['created_at'] ?? '');
                    $senderName = $conv['sender_name'] ?? 'Unknown';
                    ?>
                    <a href="<?= $threadUrl ?>"
                       class="conversation-card <?= $isUnread ? 'unread' : '' ?>"
                       role="listitem"
                       aria-label="Conversation with <?= htmlspecialchars($senderName) ?><?= $isUnread ? ', unread' : '' ?>">
                        <div class="conv-avatar">
                            <img src="<?= htmlspecialchars($avatar) ?>"
                                 onerror="this.src='<?= $fallbackAvatar ?>'"
                                 alt=""
                                 loading="lazy">
                            <?php if ($isUnread): ?>
                                <span class="conv-unread-dot" aria-hidden="true"></span>
                            <?php endif; ?>
                        </div>
                        <div class="conv-content">
                            <div class="conv-header">
                                <div class="conv-header-left">
                                    <h3 class="conv-name"><?= htmlspecialchars($senderName) ?></h3>
                                    <span class="conv-tenant">
                                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                                        <?= htmlspecialchars($conv['sender_tenant_name'] ?? 'Partner') ?>
                                    </span>
                                </div>
                                <time class="conv-time" datetime="<?= htmlspecialchars($conv['created_at'] ?? '') ?>"><?= $timeAgo ?></time>
                            </div>
                            <p class="conv-preview <?= $isUnread ? 'unread' : '' ?>">
                                <?= htmlspecialchars(mb_substr($conv['body'] ?? '', 0, 80)) ?><?= mb_strlen($conv['body'] ?? '') > 80 ? '...' : '' ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" role="status">
                <div class="empty-state-icon" aria-hidden="true">
                    <i class="fa-solid fa-envelope-open"></i>
                </div>
                <h3 class="empty-state-title">No Federated Messages Yet</h3>
                <p class="empty-state-text">Start connecting with members from partner timebanks!</p>
                <a href="<?= $basePath ?>/federation/members" class="find-members-btn">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    Browse Federated Members
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="/assets/js/federation-messages.js?v=<?= time() ?>"></script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
