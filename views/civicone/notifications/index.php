<?php
// CivicOne View: Notifications - WCAG 2.1 AA Compliant
// CSS extracted to civicone-messages.css
$hTitle = 'Notifications';
$hSubtitle = 'Stay updated on your community activity';
$hType = 'Dashboard';

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Action Bar -->
<div class="civic-action-bar">
    <a href="<?= $basePath ?>/dashboard" class="civic-btn civic-btn--outline">
        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        Back to Dashboard
    </a>

    <?php if (!empty($notifications)): ?>
        <form action="<?= $basePath ?>/notifications/mark-all-read" method="POST" class="civic-action-form">
            <?= \Nexus\Core\Csrf::input() ?>
            <button type="submit" class="civic-btn civic-btn--outline">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                Mark All Read
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div class="civic-empty-state">
        <div class="civic-empty-state-icon">
            <span class="dashicons dashicons-bell icon-size-48" aria-hidden="true"></span>
        </div>
        <h3 class="civic-empty-state-title">No notifications yet</h3>
        <p class="civic-empty-state-text">When you receive messages, connection requests, or other updates, they'll appear here.</p>
        <a href="<?= $basePath ?>/listings" class="civic-btn">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            Browse Listings
        </a>
    </div>
<?php else: ?>

    <div class="civic-notifications-list">
        <?php foreach ($notifications as $notif): ?>
            <?php
            $isUnread = empty($notif['read_at']);
            $cardClass = $isUnread ? 'civic-notification-card civic-notification--unread' : 'civic-notification-card';

            // Icon mapping
            $iconMap = [
                'message' => 'dashicons-email-alt',
                'connection_request' => 'dashicons-groups',
                'connection_accepted' => 'dashicons-yes-alt',
                'transaction' => 'dashicons-money-alt',
                'review' => 'dashicons-star-filled',
                'listing' => 'dashicons-format-aside',
                'system' => 'dashicons-info',
            ];
            $icon = $iconMap[$notif['type'] ?? 'system'] ?? 'dashicons-bell';
            ?>
            <article class="<?= $cardClass ?>">
                <div class="civic-notification-icon">
                    <span class="dashicons <?= $icon ?>" aria-hidden="true"></span>
                </div>
                <div class="civic-notification-content">
                    <p class="civic-notification-message">
                        <?= htmlspecialchars($notif['message']) ?>
                    </p>
                    <time class="civic-notification-time" datetime="<?= $notif['created_at'] ?>">
                        <?= \Nexus\Helpers\Time::ago($notif['created_at']) ?>
                    </time>
                </div>
                <div class="civic-notification-actions">
                    <?php if (!empty($notif['link'])): ?>
                        <a href="<?= htmlspecialchars($notif['link']) ?>" class="civic-btn civic-btn--sm civic-btn--outline">
                            View
                        </a>
                    <?php endif; ?>
                    <?php if ($isUnread): ?>
                        <form action="<?= $basePath ?>/notifications/mark-read" method="POST" class="civic-action-form">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                            <button type="submit" class="civic-btn civic-btn--sm civic-btn--outline" title="Mark as read">
                                <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
        <nav class="civic-pagination" aria-label="Notification pages">
            <?php if ($pagination['current_page'] > 1): ?>
                <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="civic-pagination-btn civic-pagination-prev">
                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                    Previous
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <?php if ($i == $pagination['current_page']): ?>
                    <span class="civic-pagination-btn civic-pagination-current" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>" class="civic-pagination-btn"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="civic-pagination-btn civic-pagination-next">
                    Next
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
