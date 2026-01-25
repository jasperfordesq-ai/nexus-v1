<?php
/**
 * Federation Activity Feed
 * CivicOne Theme - GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Federation Activity";
$hideHero = true;
$bodyClass = 'civicone--federation';

Nexus\Core\SEO::setTitle('Federation Activity - Recent Updates');
Nexus\Core\SEO::setDescription('View your recent federation activity including messages, transactions, and new partner connections.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$activities = $activities ?? [];
$stats = $stats ?? [];
$userOptedIn = $userOptedIn ?? false;
?>

<!-- Offline Banner -->
<div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-margin-bottom-4 hidden" id="offlineBanner" role="alert" aria-live="polite">
    <div class="govuk-notification-banner__content">
        <p class="govuk-body">
            <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
            No internet connection
        </p>
    </div>
</div>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/federation">Federation</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Activity</li>
    </ol>
</nav>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-4">
            <i class="fa-solid fa-bell govuk-!-margin-right-2" aria-hidden="true"></i>
            Federation Activity
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">
            View your recent messages, transactions, and updates from partner timebanks.
        </p>
    </div>
</div>

<?php $currentPage = 'activity'; require dirname(__DIR__) . '/partials/federation-nav.php'; ?>

    <!-- Stats Cards -->
    <?php if ($userOptedIn && !empty($stats)): ?>
    <div class="govuk-grid-row govuk-!-margin-bottom-6">
        <div class="govuk-grid-column-one-fifth">
            <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-stat-blue-bg">
                <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= $stats['unread_messages'] ?? 0 ?></p>
                <p class="govuk-body-s govuk-!-margin-bottom-0">Unread</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-fifth">
            <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg">
                <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= $stats['total_messages'] ?? 0 ?></p>
                <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Messages</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-fifth">
            <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg">
                <p class="govuk-heading-l govuk-!-margin-bottom-1 civicone-heading-red"><?= number_format($stats['hours_sent'] ?? 0, 1) ?></p>
                <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Hrs Sent</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-fifth">
            <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg">
                <p class="govuk-heading-l govuk-!-margin-bottom-1 civicone-heading-green"><?= number_format($stats['hours_received'] ?? 0, 1) ?></p>
                <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Hrs Received</p>
            </div>
        </div>
        <div class="govuk-grid-column-one-fifth">
            <div class="govuk-!-padding-3 govuk-!-text-align-center civicone-panel-bg">
                <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= $stats['partner_count'] ?? 0 ?></p>
                <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Partners</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$userOptedIn): ?>
    <!-- Not Opted In Notice -->
    <div class="govuk-notification-banner govuk-!-margin-bottom-6" role="region" aria-labelledby="govuk-notice-title">
        <div class="govuk-notification-banner__header">
            <h2 class="govuk-notification-banner__title" id="govuk-notice-title">
                <i class="fa-solid fa-user-shield govuk-!-margin-right-2" aria-hidden="true"></i>
                Enable Federation
            </h2>
        </div>
        <div class="govuk-notification-banner__content">
            <h3 class="govuk-notification-banner__heading">Enable Federation to See Full Activity</h3>
            <p class="govuk-body govuk-!-margin-bottom-4">
                You can browse partner timebanks, but to receive messages, send transactions,
                and participate fully in the federation network, enable federation in your settings.
            </p>
            <a href="<?= $basePath ?>/settings?section=federation" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-toggle-on govuk-!-margin-right-1" aria-hidden="true"></i>
                Enable Federation
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="govuk-tabs govuk-!-margin-bottom-6" data-module="govuk-tabs">
        <ul class="govuk-tabs__list" role="tablist" aria-label="Filter activity">
            <li class="govuk-tabs__list-item govuk-tabs__list-item--selected">
                <button class="govuk-tabs__tab" data-filter="all" role="tab" aria-selected="true">
                    <i class="fa-solid fa-stream govuk-!-margin-right-1" aria-hidden="true"></i> All Activity
                </button>
            </li>
            <li class="govuk-tabs__list-item">
                <button class="govuk-tabs__tab" data-filter="message" role="tab" aria-selected="false">
                    <i class="fa-solid fa-envelope govuk-!-margin-right-1" aria-hidden="true"></i> Messages
                    <?php if (($stats['unread_messages'] ?? 0) > 0): ?>
                    <span class="govuk-tag govuk-tag--red govuk-!-margin-left-1"><?= $stats['unread_messages'] ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="govuk-tabs__list-item">
                <button class="govuk-tabs__tab" data-filter="transaction" role="tab" aria-selected="false">
                    <i class="fa-solid fa-exchange-alt govuk-!-margin-right-1" aria-hidden="true"></i> Transactions
                </button>
            </li>
            <li class="govuk-tabs__list-item">
                <button class="govuk-tabs__tab" data-filter="new_partner" role="tab" aria-selected="false">
                    <i class="fa-solid fa-handshake govuk-!-margin-right-1" aria-hidden="true"></i> Partners
                </button>
            </li>
        </ul>
    </div>

    <!-- Activity List -->
    <?php if (!empty($activities)): ?>
    <ul class="govuk-list govuk-!-margin-bottom-6" id="activity-list">
        <?php foreach ($activities as $activity): ?>
        <?php
        // Determine color variant based on type
        $colorVariant = 'blue'; // Default blue
        if ($activity['type'] === 'message') {
            $colorVariant = 'blue';
        } elseif ($activity['type'] === 'transaction') {
            $colorVariant = ($activity['meta']['direction'] ?? '') === 'sent' ? 'red' : 'green';
        }
        $isUnread = $activity['is_unread'] ?? false;
        ?>
        <li class="govuk-!-margin-bottom-2" data-type="<?= htmlspecialchars($activity['type']) ?>">
            <a href="<?= $basePath . ($activity['link'] ?? '/federation') ?>" class="govuk-link govuk-link--no-underline">
                <div class="govuk-!-padding-3 civicone-activity-card civicone-activity-card--<?= $colorVariant ?><?= $isUnread ? ' civicone-activity-card--unread' : '' ?>">
                    <div class="govuk-!-padding-2 civicone-activity-icon-circle civicone-activity-icon-circle--<?= $colorVariant ?>">
                        <i class="fa-solid <?= htmlspecialchars($activity['icon'] ?? 'fa-bell') ?> civicone-icon-<?= $colorVariant ?>" aria-hidden="true"></i>
                    </div>
                    <div class="civicone-activity-content">
                        <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-1"><?= htmlspecialchars($activity['title'] ?? '') ?></p>
                        <?php if (!empty($activity['subtitle'])): ?>
                        <p class="govuk-body-s govuk-!-margin-bottom-1 civicone-secondary-text">
                            <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= htmlspecialchars($activity['subtitle']) ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($activity['description'])): ?>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text"><?= htmlspecialchars($activity['description']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($activity['preview'])): ?>
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text civicone-text-italic">"<?= htmlspecialchars($activity['preview']) ?>..."</p>
                        <?php endif; ?>
                    </div>
                    <div class="civicone-activity-meta">
                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text"><?= formatActivityTime($activity['timestamp'] ?? '') ?></p>
                        <?php if ($isUnread): ?>
                        <span class="govuk-tag govuk-tag--light-blue govuk-!-margin-top-1">New</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="govuk-inset-text govuk-!-margin-bottom-6 govuk-!-text-align-center">
        <p class="govuk-body govuk-!-margin-bottom-2">
            <i class="fa-solid fa-bell-slash fa-2x civicone-icon-grey" aria-hidden="true"></i>
        </p>
        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">No Federation Activity Yet</h3>
        <p class="govuk-body govuk-!-margin-bottom-4">
            <?php if (!$userOptedIn): ?>
            Enable federation to start connecting with partner timebanks!
            <?php else: ?>
            Start connecting with members from partner timebanks to see activity here.
            <?php endif; ?>
        </p>
        <a href="<?= $basePath ?>/federation/members" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i>
            Browse Federated Members
        </a>
    </div>
    <?php endif; ?>
</div>

<?php
function formatActivityTime($timestamp) {
    if (empty($timestamp)) return '';

    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . 'm ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . 'h ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . 'd ago';
    } else {
        return date('M j', $time);
    }
}
?>

<!-- Filter tabs handled by civicone-common.js initFilterTabs() -->
<!-- Offline indicator handled by civicone-common.js initOfflineBanner() -->

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
