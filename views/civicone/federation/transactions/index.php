<?php
/**
 * Federation Transactions History
 * CivicOne Theme - WCAG 2.1 AA Compliant
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

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/wallet" class="civic-fed-back-link" aria-label="Return to wallet">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Wallet
    </a>

    <!-- Header -->
    <header class="civic-fed-header">
        <h1>
            <i class="fa-solid fa-globe" aria-hidden="true"></i>
            Federated Transactions
        </h1>
    </header>

    <!-- Stats Grid -->
    <div class="civic-fed-stats-grid" role="region" aria-label="Transaction statistics">
        <div class="civic-fed-stat-card civic-fed-stat-card--highlight">
            <div class="civic-fed-stat-icon" aria-hidden="true">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div class="civic-fed-stat-content">
                <div class="civic-fed-stat-value"><?= number_format($balance, 1) ?></div>
                <div class="civic-fed-stat-label">Current Balance</div>
            </div>
        </div>
        <div class="civic-fed-stat-card">
            <div class="civic-fed-stat-icon" aria-hidden="true">
                <i class="fa-solid fa-arrow-up"></i>
            </div>
            <div class="civic-fed-stat-content">
                <div class="civic-fed-stat-value"><?= number_format($stats['total_sent_hours'] ?? 0, 1) ?></div>
                <div class="civic-fed-stat-label">Hours Sent</div>
            </div>
        </div>
        <div class="civic-fed-stat-card">
            <div class="civic-fed-stat-icon" aria-hidden="true">
                <i class="fa-solid fa-arrow-down"></i>
            </div>
            <div class="civic-fed-stat-content">
                <div class="civic-fed-stat-value"><?= number_format($stats['total_received_hours'] ?? 0, 1) ?></div>
                <div class="civic-fed-stat-label">Hours Received</div>
            </div>
        </div>
        <div class="civic-fed-stat-card">
            <div class="civic-fed-stat-icon" aria-hidden="true">
                <i class="fa-solid fa-exchange-alt"></i>
            </div>
            <div class="civic-fed-stat-content">
                <div class="civic-fed-stat-value"><?= ($stats['total_sent_count'] ?? 0) + ($stats['total_received_count'] ?? 0) ?></div>
                <div class="civic-fed-stat-label">Total Exchanges</div>
            </div>
        </div>
    </div>

    <!-- Transactions List -->
    <?php if (!empty($transactions)): ?>
        <div class="civic-fed-transactions-list" role="list" aria-label="Transaction history">
            <?php foreach ($transactions as $tx): ?>
                <?php
                $isSent = ($tx['direction'] ?? '') === 'sent';
                $iconClass = $isSent ? 'civic-fed-transaction--sent' : 'civic-fed-transaction--received';
                $icon = $isSent ? 'fa-arrow-up' : 'fa-arrow-down';
                $amountPrefix = $isSent ? '-' : '+';
                $status = $tx['status'] ?? 'completed';
                $isCompleted = ($status === 'completed');

                // Check if current user has reviewed this transaction
                $hasReviewed = $isSent
                    ? (($tx['sender_reviewed'] ?? 0) == 1)
                    : (($tx['receiver_reviewed'] ?? 0) == 1);
                ?>
                <article class="civic-fed-transaction-card <?= $iconClass ?>" role="listitem" aria-label="<?= $isSent ? 'Sent' : 'Received' ?> <?= number_format($tx['amount'], 1) ?> hours <?= $isSent ? 'to' : 'from' ?> <?= htmlspecialchars($tx['other_user_name'] ?? 'Unknown') ?>">
                    <div class="civic-fed-transaction-icon" aria-hidden="true">
                        <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div class="civic-fed-transaction-details">
                        <h3 class="civic-fed-transaction-user">
                            <?= $isSent ? 'To' : 'From' ?>: <?= htmlspecialchars($tx['other_user_name'] ?? 'Unknown') ?>
                        </h3>
                        <span class="civic-fed-transaction-tenant">
                            <i class="fa-solid fa-building" aria-hidden="true"></i>
                            <?= htmlspecialchars($tx['other_tenant_name'] ?? 'Partner Timebank') ?>
                        </span>
                        <?php if (!empty($tx['description'])): ?>
                            <p class="civic-fed-transaction-desc"><?= htmlspecialchars($tx['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="civic-fed-transaction-amount">
                        <div class="civic-fed-amount <?= $isSent ? 'civic-fed-amount--sent' : 'civic-fed-amount--received' ?>" aria-label="<?= $isSent ? 'Sent' : 'Received' ?> <?= number_format($tx['amount'], 1) ?> hours">
                            <?= $amountPrefix ?><?= number_format($tx['amount'], 1) ?> hrs
                        </div>
                        <time class="civic-fed-transaction-time" datetime="<?= date('Y-m-d', strtotime($tx['created_at'])) ?>">
                            <?= date('M j, Y', strtotime($tx['created_at'])) ?>
                        </time>
                        <?php if (!$isCompleted): ?>
                            <span class="civic-fed-status-badge civic-fed-status-badge--pending" role="status">
                                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                <?= ucfirst($status) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($isCompleted): ?>
                        <div class="civic-fed-transaction-actions">
                            <?php if ($hasReviewed): ?>
                                <span class="civic-fed-btn civic-fed-btn--small civic-fed-btn--disabled" aria-label="Review submitted">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                                    Reviewed
                                </span>
                            <?php else: ?>
                                <a href="<?= $basePath ?>/federation/review/<?= $tx['id'] ?>" class="civic-fed-btn civic-fed-btn--small civic-fed-btn--accent" aria-label="Leave a review for this transaction">
                                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                                    Leave Review
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="civic-fed-empty" role="status">
            <div class="civic-fed-empty-icon" aria-hidden="true">
                <i class="fa-solid fa-exchange-alt"></i>
            </div>
            <h3>No Federated Transactions Yet</h3>
            <p>Exchange hours with members from partner timebanks!</p>
            <a href="<?= $basePath ?>/federation/members" class="civic-fed-btn civic-fed-btn--primary">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                Browse Federated Members
            </a>
        </div>
    <?php endif; ?>
</div>

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
