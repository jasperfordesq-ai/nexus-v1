<?php
/**
 * Federation Transactions History
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Federated Transactions";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Transactions - Cross-Timebank Exchanges');
Nexus\Core\SEO::setDescription('View your cross-timebank transaction history and exchange stats.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$transactions = $transactions ?? [];
$stats = $stats ?? [];
$balance = $balance ?? 0;
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
    <a href="<?= $basePath ?>/wallet" class="govuk-back-link govuk-!-margin-top-4">
        Back to Wallet
    </a>

    <main class="govuk-main-wrapper govuk-!-padding-top-4" id="main-content" role="main">
        <!-- Header -->
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-6">
            <i class="fa-solid fa-globe govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
            Federated Transactions
        </h1>

        <!-- Stats Grid -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6" role="region" aria-label="Transaction statistics">
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-stat-highlight">
                    <i class="fa-solid fa-wallet fa-lg govuk-!-margin-bottom-2" aria-hidden="true"></i>
                    <p class="govuk-heading-l govuk-!-margin-bottom-1">
                        <?= number_format($balance, 1) ?>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">Current Balance</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-stat-card-top-border-red">
                    <i class="fa-solid fa-arrow-up fa-lg govuk-!-margin-bottom-2 civicone-icon-red" aria-hidden="true"></i>
                    <p class="govuk-heading-l govuk-!-margin-bottom-1 civicone-heading-red">
                        <?= number_format($stats['total_sent_hours'] ?? 0, 1) ?>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Hours Sent</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-stat-card-top-border-green">
                    <i class="fa-solid fa-arrow-down fa-lg govuk-!-margin-bottom-2 civicone-icon-green" aria-hidden="true"></i>
                    <p class="govuk-heading-l govuk-!-margin-bottom-1 civicone-heading-green">
                        <?= number_format($stats['total_received_hours'] ?? 0, 1) ?>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Hours Received</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-stat-card-top-border-blue">
                    <i class="fa-solid fa-exchange-alt fa-lg govuk-!-margin-bottom-2 civicone-icon-blue" aria-hidden="true"></i>
                    <p class="govuk-heading-l govuk-!-margin-bottom-1 civicone-heading-blue">
                        <?= ($stats['total_sent_count'] ?? 0) + ($stats['total_received_count'] ?? 0) ?>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Total Exchanges</p>
                </div>
            </div>
        </div>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <!-- Transactions List -->
                <?php if (!empty($transactions)): ?>
                    <div role="list" aria-label="Transaction history">
                        <?php foreach ($transactions as $tx): ?>
                            <?php
                            $isSent = ($tx['direction'] ?? '') === 'sent';
                            $icon = $isSent ? 'fa-arrow-up' : 'fa-arrow-down';
                            $amountPrefix = $isSent ? '-' : '+';
                            $status = $tx['status'] ?? 'completed';
                            $isCompleted = ($status === 'completed');
                            $cardClass = $isSent ? 'civicone-tx-card--sent' : 'civicone-tx-card--received';
                            $iconClass = $isSent ? 'civicone-tx-icon--sent' : 'civicone-tx-icon--received';
                            $amountClass = $isSent ? 'civicone-heading-red' : 'civicone-heading-green';

                            $hasReviewed = $isSent
                                ? (($tx['sender_reviewed'] ?? 0) == 1)
                                : (($tx['receiver_reviewed'] ?? 0) == 1);
                            ?>
                            <article class="govuk-!-padding-4 govuk-!-margin-bottom-3 civicone-tx-card <?= $cardClass ?>" role="listitem">
                                <div class="civicone-tx-card-content">
                                    <div class="civicone-tx-icon <?= $iconClass ?>">
                                        <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i>
                                    </div>
                                    <div class="civicone-tx-details">
                                        <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-1">
                                            <?= $isSent ? 'To' : 'From' ?>: <?= htmlspecialchars($tx['other_user_name'] ?? 'Unknown') ?>
                                        </p>
                                        <p class="govuk-body-s govuk-!-margin-bottom-1 civicone-secondary-text">
                                            <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                                            <?= htmlspecialchars($tx['other_tenant_name'] ?? 'Partner Timebank') ?>
                                        </p>
                                        <?php if (!empty($tx['description'])): ?>
                                            <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                                <?= htmlspecialchars($tx['description']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="civicone-tx-amount">
                                        <p class="govuk-heading-m govuk-!-margin-bottom-1 <?= $amountClass ?>">
                                            <?= $amountPrefix ?><?= number_format($tx['amount'], 1) ?> hrs
                                        </p>
                                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                            <time datetime="<?= date('Y-m-d', strtotime($tx['created_at'])) ?>">
                                                <?= date('M j, Y', strtotime($tx['created_at'])) ?>
                                            </time>
                                        </p>
                                        <?php if (!$isCompleted): ?>
                                            <span class="govuk-tag govuk-tag--yellow govuk-!-margin-top-1">
                                                <i class="fa-solid fa-clock govuk-!-margin-right-1" aria-hidden="true"></i>
                                                <?= ucfirst($status) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isCompleted): ?>
                                        <div class="civicone-tx-footer">
                                            <?php if ($hasReviewed): ?>
                                                <span class="govuk-tag govuk-tag--green">
                                                    <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                                                    Reviewed
                                                </span>
                                            <?php else: ?>
                                                <a href="<?= $basePath ?>/federation/review/<?= $tx['id'] ?>" class="govuk-button govuk-!-margin-bottom-0 civicone-btn-orange" data-module="govuk-button">
                                                    <i class="fa-solid fa-star govuk-!-margin-right-1" aria-hidden="true"></i>
                                                    Leave Review
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg civicone-border-left-blue">
                        <i class="fa-solid fa-exchange-alt fa-3x govuk-!-margin-bottom-4 civicone-icon-blue" aria-hidden="true"></i>
                        <h2 class="govuk-heading-m">No Federated Transactions Yet</h2>
                        <p class="govuk-body govuk-!-margin-bottom-4">Exchange hours with members from partner timebanks!</p>
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
