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
                    <i class="fa-solid fa-globe govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
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
                            $borderClass = $isUnread ? 'civicone-border-left-blue' : 'civicone-border-left-grey';
                            ?>
                            <a href="<?= $threadUrl ?>"
                               class="govuk-!-padding-4 govuk-!-margin-bottom-3 civicone-conversation-link <?= $borderClass ?>"
                               role="listitem">
                                <div class="civicone-conversation-row">
                                    <div class="civicone-avatar-wrapper">
                                        <img src="<?= htmlspecialchars($avatar) ?>"
                                             onerror="this.src='<?= $fallbackAvatar ?>'"
                                             alt=""
                                             class="civicone-avatar-md"
                                             loading="lazy">
                                        <?php if ($isUnread): ?>
                                            <span class="civicone-unread-dot" aria-hidden="true"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="civicone-conversation-content">
                                        <div class="civicone-conversation-header">
                                            <div>
                                                <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-1<?= $isUnread ? '' : '' ?>">
                                                    <?= htmlspecialchars($senderName) ?>
                                                </p>
                                                <p class="govuk-body-s govuk-!-margin-bottom-2 civicone-secondary-text">
                                                    <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                                                    <?= htmlspecialchars($conv['sender_tenant_name'] ?? 'Partner') ?>
                                                </p>
                                            </div>
                                            <time class="govuk-body-s civicone-secondary-text civicone-nowrap" datetime="<?= htmlspecialchars($conv['created_at'] ?? '') ?>">
                                                <?= $timeAgo ?>
                                            </time>
                                        </div>
                                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-message-preview <?= $isUnread ? 'civicone-unread-text' : 'civicone-secondary-text' ?>">
                                            <?= htmlspecialchars(mb_substr($conv['body'] ?? '', 0, 80)) ?><?= mb_strlen($conv['body'] ?? '') > 80 ? '...' : '' ?>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg civicone-border-left-blue">
                        <i class="fa-solid fa-envelope-open fa-3x govuk-!-margin-bottom-4 civicone-icon-blue" aria-hidden="true"></i>
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
<!-- Offline indicator handled by civicone-common.js -->

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
