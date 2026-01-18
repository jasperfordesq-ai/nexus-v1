<?php
// Federation Messages Inbox - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Messages";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Messages - Partner Timebank Conversations');
Nexus\Core\SEO::setDescription('View and manage your conversations with members from partner timebanks.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/header.php';
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

        <style>
            /* ============================================
               FEDERATED MESSAGES - Glassmorphism Theme
               Purple/Violet for Federation Features
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

            #fed-messages-wrapper {
                max-width: 900px;
                margin: 0 auto;
                padding: 20px 0;
            }

            /* Back Link */
            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 20px;
                transition: color 0.2s;
                animation: fadeInUp 0.4s ease-out;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            /* Header Card */
            .messages-header {
                animation: fadeInUp 0.4s ease-out 0.05s both;
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.4);
                border-radius: 20px;
                padding: 24px;
                margin-bottom: 24px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 16px;
            }

            [data-theme="dark"] .messages-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.15) 0%,
                        rgba(168, 85, 247, 0.15) 50%,
                        rgba(192, 132, 252, 0.1) 100%);
                border-color: rgba(255, 255, 255, 0.1);
            }

            .messages-header h1 {
                font-size: 1.5rem;
                font-weight: 800;
                background: linear-gradient(135deg, #7c3aed, #8b5cf6, #a78bfa);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .unread-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 24px;
                height: 24px;
                padding: 0 8px;
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                font-size: 0.8rem;
                font-weight: 700;
                border-radius: 12px;
            }

            /* Conversation List */
            .conversations-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
                animation: fadeInUp 0.4s ease-out 0.1s both;
            }

            .conversation-card {
                animation: fadeInUp 0.4s ease-out both;
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 20px;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 16px;
                box-shadow: 0 4px 16px rgba(31, 38, 135, 0.1);
                text-decoration: none;
                transition: all 0.3s ease;
            }

            .conversation-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(139, 92, 246, 0.15);
                border-color: rgba(139, 92, 246, 0.3);
            }

            .conversation-card.unread {
                border-left: 4px solid #8b5cf6;
            }

            [data-theme="dark"] .conversation-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            }

            [data-theme="dark"] .conversation-card:hover {
                box-shadow: 0 8px 24px rgba(139, 92, 246, 0.25);
            }

            /* Avatar */
            .conv-avatar {
                position: relative;
                width: 56px;
                height: 56px;
                flex-shrink: 0;
            }

            .conv-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
                border: 3px solid rgba(139, 92, 246, 0.3);
            }

            .conv-unread-dot {
                position: absolute;
                top: 0;
                right: 0;
                width: 14px;
                height: 14px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                border: 2px solid white;
                border-radius: 50%;
            }

            [data-theme="dark"] .conv-unread-dot {
                border-color: #1e293b;
            }

            /* Conversation Content */
            .conv-content {
                flex: 1;
                min-width: 0;
            }

            .conv-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                margin-bottom: 4px;
            }

            .conv-name {
                font-size: 1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .conv-tenant {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 8px;
                background: rgba(139, 92, 246, 0.1);
                border-radius: 10px;
                font-size: 0.7rem;
                font-weight: 600;
                color: #8b5cf6;
                white-space: nowrap;
            }

            [data-theme="dark"] .conv-tenant {
                background: rgba(139, 92, 246, 0.2);
                color: #a78bfa;
            }

            .conv-time {
                font-size: 0.8rem;
                color: var(--htb-text-muted);
                white-space: nowrap;
            }

            .conv-preview {
                font-size: 0.9rem;
                color: var(--htb-text-muted);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .conv-preview.unread {
                color: var(--htb-text-main);
                font-weight: 600;
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.7),
                        rgba(255, 255, 255, 0.5));
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 20px;
            }

            [data-theme="dark"] .empty-state {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .empty-state-icon {
                font-size: 4rem;
                color: #8b5cf6;
                margin-bottom: 20px;
            }

            .empty-state h3 {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 10px 0;
            }

            .empty-state p {
                color: var(--htb-text-muted);
                margin: 0 0 20px 0;
            }

            .find-members-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 700;
                transition: all 0.3s ease;
            }

            .find-members-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            }

            /* Touch Targets */
            .conversation-card,
            .find-members-btn {
                min-height: 44px;
            }

            /* Focus Visible */
            .conversation-card:focus-visible,
            .find-members-btn:focus-visible,
            .back-link:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            /* Responsive */
            @media (max-width: 640px) {
                .messages-header {
                    flex-direction: column;
                    text-align: center;
                }

                .conversation-card {
                    padding: 16px;
                }

                .conversation-card:hover {
                    transform: none;
                }

                .conv-avatar {
                    width: 48px;
                    height: 48px;
                }
            }
        </style>

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

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/footer.php'; ?>
