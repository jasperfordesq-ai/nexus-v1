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

        <style>
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

            #fed-transactions-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 900px;
                margin: 0 auto;
                padding: 20px 0;
            }

            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 20px;
                transition: color 0.2s;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            /* Header Card */
            .transactions-header {
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
            }

            [data-theme="dark"] .transactions-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.15) 0%,
                        rgba(168, 85, 247, 0.15) 50%,
                        rgba(192, 132, 252, 0.1) 100%);
                border-color: rgba(255, 255, 255, 0.1);
            }

            .transactions-header h1 {
                font-size: 1.5rem;
                font-weight: 800;
                background: linear-gradient(135deg, #7c3aed, #8b5cf6, #a78bfa);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin: 0 0 16px 0;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            /* Stats Grid */
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 16px;
            }

            .stat-card {
                background: rgba(255, 255, 255, 0.5);
                border-radius: 14px;
                padding: 16px;
                text-align: center;
            }

            [data-theme="dark"] .stat-card {
                background: rgba(255, 255, 255, 0.1);
            }

            .stat-value {
                font-size: 1.5rem;
                font-weight: 800;
                color: #8b5cf6;
            }

            .stat-label {
                font-size: 0.8rem;
                color: var(--htb-text-muted);
                margin-top: 4px;
            }

            .stat-card.balance .stat-value {
                color: #10b981;
            }

            /* Transaction List */
            .transactions-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .transaction-card {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 20px;
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 16px;
                transition: all 0.3s ease;
            }

            [data-theme="dark"] .transaction-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .transaction-icon {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.2rem;
                flex-shrink: 0;
            }

            .transaction-icon.sent {
                background: rgba(239, 68, 68, 0.15);
                color: #ef4444;
            }

            .transaction-icon.received {
                background: rgba(16, 185, 129, 0.15);
                color: #10b981;
            }

            .transaction-details {
                flex: 1;
                min-width: 0;
            }

            .transaction-user {
                font-size: 1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 4px 0;
            }

            .transaction-tenant {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 0.75rem;
                color: #8b5cf6;
                background: rgba(139, 92, 246, 0.1);
                padding: 2px 8px;
                border-radius: 8px;
                margin-bottom: 4px;
            }

            .transaction-desc {
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .transaction-amount {
                text-align: right;
            }

            .transaction-amount .amount {
                font-size: 1.25rem;
                font-weight: 800;
            }

            .transaction-amount .amount.sent {
                color: #ef4444;
            }

            .transaction-amount .amount.received {
                color: #10b981;
            }

            .transaction-amount .time {
                font-size: 0.75rem;
                color: var(--htb-text-muted);
            }

            /* Transaction Actions */
            .transaction-actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-left: 12px;
            }

            .review-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                background: linear-gradient(135deg, #f59e0b, #fbbf24);
                color: #1a1a1a;
                text-decoration: none;
                border-radius: 10px;
                font-size: 0.8rem;
                font-weight: 700;
                transition: all 0.2s ease;
                white-space: nowrap;
            }

            .review-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
                color: #1a1a1a;
            }

            .review-btn.reviewed {
                background: linear-gradient(135deg, #10b981, #34d399);
                color: white;
                pointer-events: none;
            }

            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 4px 10px;
                border-radius: 8px;
                font-size: 0.7rem;
                font-weight: 600;
            }

            .status-badge.completed {
                background: rgba(16, 185, 129, 0.15);
                color: #10b981;
            }

            .status-badge.pending {
                background: rgba(245, 158, 11, 0.15);
                color: #f59e0b;
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
                margin-top: 16px;
            }

            .find-members-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            }

            /* Touch Targets */
            .find-members-btn {
                min-height: 44px;
            }

            /* Focus Visible */
            .find-members-btn:focus-visible,
            .back-link:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            @media (max-width: 640px) {
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }

                .transaction-card {
                    flex-wrap: wrap;
                }

                .transaction-amount {
                    width: 100%;
                    text-align: left;
                    margin-top: 8px;
                    padding-top: 8px;
                    border-top: 1px solid rgba(139, 92, 246, 0.1);
                }
            }
        </style>

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
