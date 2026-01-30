<?php
/**
 * CivicOne View: Notifications
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$hTitle = 'Notifications';
$hSubtitle = 'Stay updated on your community activity';
$hType = 'Dashboard';

require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Dashboard', 'href' => $basePath . '/dashboard'],
        ['text' => 'Notifications']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-bell govuk-!-margin-right-2" aria-hidden="true"></i>
            Notifications
        </h1>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <div class="govuk-button-group civicone-justify-end">
            <a href="<?= $basePath ?>/dashboard" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i> Back to Dashboard
            </a>
            <?php if (!empty($notifications)): ?>
                <form action="<?= $basePath ?>/notifications/mark-all-read" method="POST" class="civicone-inline-form">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        <i class="fa-solid fa-check-double govuk-!-margin-right-1" aria-hidden="true"></i> Mark All Read
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (empty($notifications)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <i class="fa-solid fa-bell-slash govuk-!-margin-right-2" aria-hidden="true"></i>
            <strong>No notifications yet</strong>
        </p>
        <p class="govuk-body govuk-!-margin-bottom-4">When you receive messages, connection requests, or other updates, they'll appear here.</p>
        <a href="<?= $basePath ?>/listings" class="govuk-button govuk-button--start" data-module="govuk-button">
            Browse Listings
            <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
            </svg>
        </a>
    </div>
<?php else: ?>
    <ul class="govuk-list" role="list">
        <?php foreach ($notifications as $notif): ?>
            <?php
            $isUnread = empty($notif['read_at']);

            // Icon mapping
            $iconMap = [
                'message' => 'fa-envelope',
                'connection_request' => 'fa-user-plus',
                'connection_accepted' => 'fa-user-check',
                'transaction' => 'fa-coins',
                'review' => 'fa-star',
                'listing' => 'fa-list',
                'system' => 'fa-info-circle',
            ];
            $icon = $iconMap[$notif['type'] ?? 'system'] ?? 'fa-bell';
            ?>
            <li class="govuk-!-margin-bottom-4 govuk-!-padding-4 civicone-notification-item <?= $isUnread ? 'govuk-!-font-weight-bold civicone-notification-item--unread' : '' ?>">
                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-three-quarters">
                        <p class="govuk-body govuk-!-margin-bottom-2">
                            <i class="fa-solid <?= $icon ?> govuk-!-margin-right-2" aria-hidden="true"></i>
                            <?= htmlspecialchars($notif['message']) ?>
                        </p>
                        <time class="govuk-body-s civicone-secondary-text" datetime="<?= $notif['created_at'] ?>">
                            <i class="fa-regular fa-clock govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= \Nexus\Helpers\Time::ago($notif['created_at']) ?>
                        </time>
                    </div>
                    <div class="govuk-grid-column-one-quarter govuk-!-text-align-right">
                        <div class="govuk-button-group civicone-justify-end">
                            <?php if (!empty($notif['link'])): ?>
                                <a href="<?= htmlspecialchars($notif['link']) ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                    View
                                </a>
                            <?php endif; ?>
                            <?php if ($isUnread): ?>
                                <form action="<?= $basePath ?>/notifications/mark-read" method="POST" class="civicone-inline-form">
                                    <?= \Nexus\Core\Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                                    <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button" title="Mark as read">
                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
        <nav class="govuk-pagination" aria-label="Notification pages">
            <?php if ($pagination['current_page'] > 1): ?>
                <div class="govuk-pagination__prev">
                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $pagination['current_page'] - 1 ?>" rel="prev">
                        <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                        </svg>
                        <span class="govuk-pagination__link-title">Previous<span class="govuk-visually-hidden"> page</span></span>
                    </a>
                </div>
            <?php endif; ?>

            <ul class="govuk-pagination__list">
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <li class="govuk-pagination__item <?= $i == $pagination['current_page'] ? 'govuk-pagination__item--current' : '' ?>">
                        <?php if ($i == $pagination['current_page']): ?>
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $i ?>" aria-current="page"><?= $i ?></a>
                        <?php else: ?>
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>
            </ul>

            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                <div class="govuk-pagination__next">
                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $pagination['current_page'] + 1 ?>" rel="next">
                        <span class="govuk-pagination__link-title">Next<span class="govuk-visually-hidden"> page</span></span>
                        <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                            <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                        </svg>
                    </a>
                </div>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
