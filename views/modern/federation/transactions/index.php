<?php
// Federation Transactions History - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Transactions";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federated Transactions - Cross-Timebank Exchanges');
Nexus\Core\SEO::setDescription('View your cross-timebank transaction history and exchange stats.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$transactions = $transactions ?? [];
$stats = $stats ?? [];
$balance = $balance ?? 0;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-transactions-wrapper">

<!-- Back Link -->
        <a href="<?= $basePath ?>/wallet" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Wallet
        </a>

        <!-- Header with Stats -->
        <div class="transactions-header">
            <h1>
                <i class="fa-solid fa-globe"></i>
                Federated Transactions
            </h1>

            <div class="stats-grid">
                <div class="stat-card balance">
                    <div class="stat-value"><?= number_format($balance, 1) ?></div>
                    <div class="stat-label">Current Balance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($stats['total_sent_hours'] ?? 0, 1) ?></div>
                    <div class="stat-label">Hours Sent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($stats['total_received_hours'] ?? 0, 1) ?></div>
                    <div class="stat-label">Hours Received</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= ($stats['total_sent_count'] ?? 0) + ($stats['total_received_count'] ?? 0) ?></div>
                    <div class="stat-label">Total Exchanges</div>
                </div>
            </div>
        </div>

        <!-- Transactions List -->
        <?php if (!empty($transactions)): ?>
            <div class="transactions-list">
                <?php foreach ($transactions as $tx): ?>
                    <?php
                    $isSent = ($tx['direction'] ?? '') === 'sent';
                    $iconClass = $isSent ? 'sent' : 'received';
                    $icon = $isSent ? 'fa-arrow-up' : 'fa-arrow-down';
                    $amountPrefix = $isSent ? '-' : '+';
                    $status = $tx['status'] ?? 'completed';
                    $isCompleted = ($status === 'completed');

                    // Check if current user has reviewed this transaction
                    $hasReviewed = $isSent
                        ? (($tx['sender_reviewed'] ?? 0) == 1)
                        : (($tx['receiver_reviewed'] ?? 0) == 1);
                    ?>
                    <div class="transaction-card">
                        <div class="transaction-icon <?= $iconClass ?>">
                            <i class="fa-solid <?= $icon ?>"></i>
                        </div>
                        <div class="transaction-details">
                            <h3 class="transaction-user">
                                <?= $isSent ? 'To' : 'From' ?>: <?= htmlspecialchars($tx['other_user_name'] ?? 'Unknown') ?>
                            </h3>
                            <span class="transaction-tenant">
                                <i class="fa-solid fa-building"></i>
                                <?= htmlspecialchars($tx['other_tenant_name'] ?? 'Partner Timebank') ?>
                            </span>
                            <?php if (!empty($tx['description'])): ?>
                                <p class="transaction-desc"><?= htmlspecialchars($tx['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="transaction-amount">
                            <div class="amount <?= $iconClass ?>">
                                <?= $amountPrefix ?><?= number_format($tx['amount'], 1) ?> hrs
                            </div>
                            <div class="time">
                                <?= date('M j, Y', strtotime($tx['created_at'])) ?>
                            </div>
                            <?php if (!$isCompleted): ?>
                                <span class="status-badge pending">
                                    <i class="fa-solid fa-clock"></i>
                                    <?= ucfirst($status) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($isCompleted): ?>
                            <div class="transaction-actions">
                                <?php if ($hasReviewed): ?>
                                    <span class="review-btn reviewed">
                                        <i class="fa-solid fa-check"></i>
                                        Reviewed
                                    </span>
                                <?php else: ?>
                                    <a href="<?= $basePath ?>/federation/review/<?= $tx['id'] ?>" class="review-btn">
                                        <i class="fa-solid fa-star"></i>
                                        Leave Review
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-solid fa-exchange-alt"></i>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 10px 0;">
                    No Federated Transactions Yet
                </h3>
                <p style="color: var(--htb-text-muted); margin: 0;">
                    Exchange hours with members from partner timebanks!
                </p>
                <a href="<?= $basePath ?>/federation/members" class="find-members-btn">
                    <i class="fa-solid fa-users"></i>
                    Browse Federated Members
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

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
