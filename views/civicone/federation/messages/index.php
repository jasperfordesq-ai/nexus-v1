<?php
// Federation Messages Inbox - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Messages";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Messages - Partner Timebank Conversations');
Nexus\Core\SEO::setDescription('View and manage your conversations with members from partner timebanks.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$conversations = $conversations ?? [];
$unreadCount = $unreadCount ?? 0;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-messages-wrapper">

<!-- Back to Messages -->
        <a href="<?= $basePath ?>/messages" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Messages
        </a>

        <!-- Header -->
        <div class="messages-header">
            <h1>
                <i class="fa-solid fa-globe"></i>
                Federated Messages
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </h1>
            <a href="<?= $basePath ?>/federation/members" class="find-members-btn">
                <i class="fa-solid fa-user-plus"></i>
                Find Members
            </a>
        </div>

        <!-- Conversations List -->
        <?php if (!empty($conversations)): ?>
            <div class="conversations-list">
                <?php foreach ($conversations as $conv): ?>
                    <?php
                    $isUnread = ($conv['unread_count'] ?? 0) > 0;
                    $fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($conv['sender_name'] ?? 'User') . '&background=8b5cf6&color=fff&size=100';
                    $avatar = !empty($conv['sender_avatar']) ? $conv['sender_avatar'] : $fallbackAvatar;
                    $threadUrl = $basePath . '/federation/messages/' . $conv['sender_user_id'] . '?tenant=' . $conv['sender_tenant_id'];
                    $timeAgo = timeAgo($conv['created_at'] ?? '');
                    ?>
                    <a href="<?= $threadUrl ?>" class="conversation-card <?= $isUnread ? 'unread' : '' ?>">
                        <div class="conv-avatar">
                            <img src="<?= htmlspecialchars($avatar) ?>"
                                 onerror="this.src='<?= $fallbackAvatar ?>'"
                                 alt="<?= htmlspecialchars($conv['sender_name'] ?? 'User') ?>">
                            <?php if ($isUnread): ?>
                                <span class="conv-unread-dot"></span>
                            <?php endif; ?>
                        </div>
                        <div class="conv-content">
                            <div class="conv-header">
                                <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                    <h3 class="conv-name"><?= htmlspecialchars($conv['sender_name'] ?? 'Unknown') ?></h3>
                                    <span class="conv-tenant">
                                        <i class="fa-solid fa-building"></i>
                                        <?= htmlspecialchars($conv['sender_tenant_name'] ?? 'Partner') ?>
                                    </span>
                                </div>
                                <span class="conv-time"><?= $timeAgo ?></span>
                            </div>
                            <p class="conv-preview <?= $isUnread ? 'unread' : '' ?>">
                                <?= htmlspecialchars(mb_substr($conv['body'] ?? '', 0, 80)) ?><?= mb_strlen($conv['body'] ?? '') > 80 ? '...' : '' ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-solid fa-envelope-open"></i>
                </div>
                <h3>No Federated Messages Yet</h3>
                <p>Start connecting with members from partner timebanks!</p>
                <a href="<?= $basePath ?>/federation/members" class="find-members-btn">
                    <i class="fa-solid fa-users"></i>
                    Browse Federated Members
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php
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

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
