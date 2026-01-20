<?php
/**
 * Federation Messages Inbox
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
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
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/messages" class="civic-fed-back-link" aria-label="Return to local messages">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Messages
    </a>

    <!-- Header -->
    <header class="civic-fed-header">
        <div class="civic-fed-header-content">
            <h1>
                <i class="fa-solid fa-globe" aria-hidden="true"></i>
                Federated Messages
                <?php if ($unreadCount > 0): ?>
                    <span class="civic-fed-count-badge" role="status" aria-label="<?= $unreadCount ?> unread message<?= $unreadCount !== 1 ? 's' : '' ?>"><?= $unreadCount ?></span>
                <?php endif; ?>
            </h1>
        </div>
        <a href="<?= $basePath ?>/federation/members" class="civic-fed-btn civic-fed-btn--secondary" aria-label="Find new members to message">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
            Find Members
        </a>
    </header>

    <!-- Conversations List -->
    <?php if (!empty($conversations)): ?>
        <div class="civic-fed-conversations-list" role="list" aria-label="Message conversations">
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
                   class="civic-fed-conversation-card <?= $isUnread ? 'civic-fed-conversation-card--unread' : '' ?>"
                   role="listitem"
                   aria-label="Conversation with <?= htmlspecialchars($senderName) ?><?= $isUnread ? ', unread' : '' ?>">
                    <div class="civic-fed-conversation-avatar">
                        <img src="<?= htmlspecialchars($avatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt=""
                             loading="lazy">
                        <?php if ($isUnread): ?>
                            <span class="civic-fed-unread-dot" aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                    <div class="civic-fed-conversation-content">
                        <div class="civic-fed-conversation-header">
                            <div class="civic-fed-conversation-info">
                                <h3 class="civic-fed-conversation-name"><?= htmlspecialchars($senderName) ?></h3>
                                <span class="civic-fed-conversation-tenant">
                                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                                    <?= htmlspecialchars($conv['sender_tenant_name'] ?? 'Partner') ?>
                                </span>
                            </div>
                            <time class="civic-fed-conversation-time" datetime="<?= htmlspecialchars($conv['created_at'] ?? '') ?>"><?= $timeAgo ?></time>
                        </div>
                        <p class="civic-fed-conversation-preview <?= $isUnread ? 'civic-fed-conversation-preview--unread' : '' ?>">
                            <?= htmlspecialchars(mb_substr($conv['body'] ?? '', 0, 80)) ?><?= mb_strlen($conv['body'] ?? '') > 80 ? '...' : '' ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="civic-fed-empty" role="status">
            <div class="civic-fed-empty-icon" aria-hidden="true">
                <i class="fa-solid fa-envelope-open"></i>
            </div>
            <h3>No Federated Messages Yet</h3>
            <p>Start connecting with members from partner timebanks!</p>
            <a href="<?= $basePath ?>/federation/members" class="civic-fed-btn civic-fed-btn--primary">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                Browse Federated Members
            </a>
        </div>
    <?php endif; ?>
</div>

<script src="/assets/js/federation-messages.js?v=<?= time() ?>"></script>
<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
    if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
})();
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
