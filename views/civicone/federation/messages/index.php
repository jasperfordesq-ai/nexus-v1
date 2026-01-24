<?php
/**
 * Federation Messages Inbox
 * GOV.UK Design System (WCAG 2.1 AA)
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

<div class="govuk-width-container">
    <!-- Offline Banner -->
    <div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-display-none" id="offlineBanner" role="alert" aria-live="polite" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">
                <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
                No internet connection
            </p>
        </div>
    </div>

    <!-- Back Link -->
    <a href="<?= $basePath ?>/messages" class="govuk-back-link govuk-!-margin-top-4">
        Back to Messages
    </a>

    <main class="govuk-main-wrapper govuk-!-padding-top-4" id="main-content" role="main">
        <!-- Header -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">
                    <i class="fa-solid fa-globe govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    Federated Messages
                    <?php if ($unreadCount > 0): ?>
                        <span class="govuk-tag govuk-tag--red govuk-!-margin-left-2" role="status">
                            <?= $unreadCount ?> unread
                        </span>
                    <?php endif; ?>
                </h1>
            </div>
            <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                <a href="<?= $basePath ?>/federation/members" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-user-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                    Find Members
                </a>
            </div>
        </div>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <!-- Conversations List -->
                <?php if (!empty($conversations)): ?>
                    <div role="list" aria-label="Message conversations">
                        <?php foreach ($conversations as $conv): ?>
                            <?php
                            $isUnread = ($conv['unread_count'] ?? 0) > 0;
                            $fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($conv['sender_name'] ?? 'User') . '&background=1d70b8&color=fff&size=100';
                            $avatar = !empty($conv['sender_avatar']) ? $conv['sender_avatar'] : $fallbackAvatar;
                            $threadUrl = $basePath . '/federation/messages/' . $conv['sender_user_id'] . '?tenant=' . $conv['sender_tenant_id'];
                            $timeAgo = timeAgo($conv['created_at'] ?? '');
                            $senderName = $conv['sender_name'] ?? 'Unknown';
                            $borderColor = $isUnread ? '#1d70b8' : '#b1b4b6';
                            ?>
                            <a href="<?= $threadUrl ?>"
                               class="govuk-!-padding-4 govuk-!-margin-bottom-3"
                               style="display: block; background: #fff; border: 1px solid #b1b4b6; border-left: 5px solid <?= $borderColor ?>; text-decoration: none; color: inherit;"
                               role="listitem">
                                <div style="display: flex; align-items: flex-start; gap: 16px;">
                                    <div style="position: relative; flex-shrink: 0;">
                                        <img src="<?= htmlspecialchars($avatar) ?>"
                                             onerror="this.src='<?= $fallbackAvatar ?>'"
                                             alt=""
                                             style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;"
                                             loading="lazy">
                                        <?php if ($isUnread): ?>
                                            <span style="position: absolute; top: 0; right: 0; width: 12px; height: 12px; background: #1d70b8; border-radius: 50%; border: 2px solid #fff;" aria-hidden="true"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                            <div>
                                                <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-1" style="<?= $isUnread ? 'color: #0b0c0c;' : '' ?>">
                                                    <?= htmlspecialchars($senderName) ?>
                                                </p>
                                                <p class="govuk-body-s govuk-!-margin-bottom-2" style="color: #505a5f;">
                                                    <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                                                    <?= htmlspecialchars($conv['sender_tenant_name'] ?? 'Partner') ?>
                                                </p>
                                            </div>
                                            <time class="govuk-body-s" style="color: #505a5f; white-space: nowrap;" datetime="<?= htmlspecialchars($conv['created_at'] ?? '') ?>">
                                                <?= $timeAgo ?>
                                            </time>
                                        </div>
                                        <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: <?= $isUnread ? '#0b0c0c' : '#505a5f' ?>; <?= $isUnread ? 'font-weight: bold;' : '' ?> overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= htmlspecialchars(mb_substr($conv['body'] ?? '', 0, 80)) ?><?= mb_strlen($conv['body'] ?? '') > 80 ? '...' : '' ?>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                        <i class="fa-solid fa-envelope-open fa-3x govuk-!-margin-bottom-4" style="color: #1d70b8;" aria-hidden="true"></i>
                        <h2 class="govuk-heading-m">No Federated Messages Yet</h2>
                        <p class="govuk-body govuk-!-margin-bottom-4">Start connecting with members from partner timebanks!</p>
                        <a href="<?= $basePath ?>/federation/members" class="govuk-button" data-module="govuk-button">
                            <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                            Browse Federated Members
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="/assets/js/federation-messages.js?v=<?= time() ?>"></script>
<script>
(function() {
    'use strict';
    var banner = document.getElementById('offlineBanner');
    function updateOffline(offline) {
        if (banner) banner.classList.toggle('govuk-!-display-none', !offline);
    }
    window.addEventListener('online', function() { updateOffline(false); });
    window.addEventListener('offline', function() { updateOffline(true); });
    if (!navigator.onLine) updateOffline(true);
})();
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
