<?php
/**
 * Timebanking User Report - Gold Standard Admin UI
 * Holographic Glassmorphism Design
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();

$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'User #' . $user['id'];

// Admin header configuration
$adminPageTitle = 'User Activity Report';
$adminPageSubtitle = 'Detailed timebanking history for ' . htmlspecialchars($userName);
$adminPageIcon = 'fa-solid fa-user-clock';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="user-report-container">
    <!-- Navigation -->
    <div class="nav-bar">
        <a href="<?= $basePath ?>/admin-legacy/timebanking/user-report" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Search
        </a>
        <a href="<?= $basePath ?>/admin-legacy/users/edit/<?= $user['id'] ?>" class="edit-link">
            <i class="fa-solid fa-user-pen"></i> Edit User Profile
        </a>
    </div>

    <!-- User Profile Card -->
    <div class="glass-card profile-card">
        <div class="profile-header">
            <div class="profile-avatar">
                <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?><?= strtoupper(substr($user['last_name'] ?? '', 0, 1)) ?>
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?= htmlspecialchars($userName) ?></h1>
                <div class="profile-email"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                <div class="profile-meta">
                    <span class="meta-item">
                        <i class="fa-solid fa-calendar"></i>
                        Joined <?= date('M j, Y', strtotime($user['created_at'] ?? 'now')) ?>
                    </span>
                    <span class="meta-item">
                        <i class="fa-solid fa-id-badge"></i>
                        ID: <?= $user['id'] ?>
                    </span>
                    <?php if (!empty($user['role'])): ?>
                    <span class="meta-item role-badge <?= $user['role'] === 'admin' ? 'admin' : 'user' ?>">
                        <i class="fa-solid fa-<?= $user['role'] === 'admin' ? 'shield-halved' : 'user' ?>"></i>
                        <?= ucfirst($user['role']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-balance">
                <div class="balance-value"><?= number_format($user['balance'] ?? 0, 1) ?></div>
                <div class="balance-label">Hours Balance</div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card sent">
            <div class="stat-icon">
                <i class="fa-solid fa-arrow-up"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($stats['sent_total'], 1) ?>h</div>
                <div class="stat-label">Hours Sent</div>
            </div>
        </div>

        <div class="stat-card received">
            <div class="stat-icon">
                <i class="fa-solid fa-arrow-down"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($stats['received_total'], 1) ?>h</div>
                <div class="stat-label">Hours Received</div>
            </div>
        </div>

        <div class="stat-card transactions">
            <div class="stat-icon">
                <i class="fa-solid fa-exchange-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($stats['transaction_count']) ?></div>
                <div class="stat-label">Total Transactions</div>
            </div>
        </div>

        <div class="stat-card <?= $stats['net_change'] >= 0 ? 'positive' : 'negative' ?>">
            <div class="stat-icon">
                <i class="fa-solid fa-<?= $stats['net_change'] >= 0 ? 'trending-up' : 'trending-down' ?>"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= ($stats['net_change'] >= 0 ? '+' : '') . number_format($stats['net_change'], 1) ?>h</div>
                <div class="stat-label">Net Change</div>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <!-- Transaction History -->
        <div class="glass-card transactions-card">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <h2>Transaction History</h2>
                <span class="transaction-count"><?= count($transactions) ?> transactions</span>
            </div>

            <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-receipt"></i>
                <h3>No Transactions</h3>
                <p>This user hasn't made any timebanking transactions yet.</p>
            </div>
            <?php else: ?>
            <div class="transactions-list">
                <?php foreach ($transactions as $tx): ?>
                <?php
                $isSender = $tx['sender_id'] == $user['id'];
                $otherUser = $isSender ? $tx['receiver_name'] : $tx['sender_name'];
                $amount = floatval($tx['amount']);
                ?>
                <div class="transaction-item">
                    <div class="tx-direction <?= $isSender ? 'sent' : 'received' ?>">
                        <i class="fa-solid fa-arrow-<?= $isSender ? 'up' : 'down' ?>"></i>
                    </div>
                    <div class="tx-details">
                        <div class="tx-description">
                            <?php if ($isSender): ?>
                                Sent to <strong><?= htmlspecialchars($otherUser) ?></strong>
                            <?php else: ?>
                                Received from <strong><?= htmlspecialchars($otherUser) ?></strong>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($tx['description'])): ?>
                        <div class="tx-note"><?= htmlspecialchars($tx['description']) ?></div>
                        <?php endif; ?>
                        <div class="tx-date">
                            <?= date('M j, Y \a\t g:i A', strtotime($tx['created_at'])) ?>
                        </div>
                    </div>
                    <div class="tx-amount <?= $isSender ? 'negative' : 'positive' ?>">
                        <?= $isSender ? '-' : '+' ?><?= number_format($amount, 1) ?>h
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Alerts & Sidebar -->
        <div class="sidebar-column">
            <!-- Alerts Card -->
            <div class="glass-card alerts-card">
                <div class="card-header">
                    <div class="card-icon alerts">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h2>Abuse Alerts</h2>
                </div>

                <?php if (empty($alerts)): ?>
                <div class="empty-state small">
                    <i class="fa-solid fa-shield-check"></i>
                    <p>No alerts for this user</p>
                </div>
                <?php else: ?>
                <div class="alerts-list">
                    <?php foreach ($alerts as $alert): ?>
                    <a href="<?= $basePath ?>/admin-legacy/timebanking/alerts/<?= $alert['id'] ?>" class="alert-item">
                        <div class="alert-type <?= $alert['status'] ?? 'pending' ?>">
                            <i class="fa-solid fa-<?= $alert['status'] === 'resolved' ? 'check' : 'exclamation' ?>"></i>
                        </div>
                        <div class="alert-content">
                            <div class="alert-title"><?= htmlspecialchars($alert['alert_type'] ?? 'Alert') ?></div>
                            <div class="alert-date"><?= date('M j, Y', strtotime($alert['created_at'])) ?></div>
                        </div>
                        <span class="alert-status <?= $alert['status'] ?? 'pending' ?>">
                            <?= ucfirst($alert['status'] ?? 'Pending') ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-icon actions">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <h2>Quick Actions</h2>
                </div>

                <div class="action-buttons">
                    <a href="<?= $basePath ?>/admin-legacy/users/edit/<?= $user['id'] ?>" class="action-btn edit">
                        <i class="fa-solid fa-user-pen"></i>
                        Edit Profile
                    </a>
                    <a href="<?= $basePath ?>/messages/<?= $user['id'] ?>" class="action-btn message">
                        <i class="fa-solid fa-message"></i>
                        Send Message
                    </a>
                    <button type="button" class="action-btn adjust" onclick="openBalanceModal()">
                        <i class="fa-solid fa-scale-balanced"></i>
                        Adjust Balance
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Balance Adjustment Modal -->
<div id="balanceModal" class="modal" role="dialog" aria-modal="true"-overlay" style="display: none;">
    <div class="modal" role="dialog" aria-modal="true"-content">
        <div class="modal" role="dialog" aria-modal="true"-header">
            <h3><i class="fa-solid fa-scale-balanced"></i> Adjust Balance</h3>
            <button type="button" class="modal" role="dialog" aria-modal="true"-close" onclick="closeBalanceModal()">&times;</button>
        </div>
        <form action="<?= $basePath ?>/admin-legacy/timebanking/adjust-balance" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

            <div class="modal" role="dialog" aria-modal="true"-body">
                <p class="current-balance">
                    Current Balance: <strong><?= number_format($user['balance'] ?? 0, 1) ?> hours</strong>
                </p>

                <div class="form-group">
                    <label class="form-label">Adjustment Type</label>
                    <div class="adjustment-type-selector">
                        <label class="radio-option">
                            <input type="radio" name="type" value="add" checked>
                            <span class="radio-content add">
                                <i class="fa-solid fa-plus"></i> Add Hours
                            </span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="type" value="subtract">
                            <span class="radio-content subtract">
                                <i class="fa-solid fa-minus"></i> Subtract Hours
                            </span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Amount (hours)</label>
                    <input type="number" name="amount" step="0.5" min="0.5" required class="form-input" placeholder="e.g., 5.0">
                </div>

                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-textarea" rows="3" required placeholder="Explain the reason for this adjustment..."></textarea>
                </div>
            </div>

            <div class="modal" role="dialog" aria-modal="true"-footer">
                <button type="button" class="btn-cancel" onclick="closeBalanceModal()">Cancel</button>
                <button type="submit" class="btn-submit">Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Container */
.user-report-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px 60px;
}

/* Navigation */
.nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.back-link,
.edit-link {
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: color 0.2s ease;
}

.back-link:hover,
.edit-link:hover {
    color: #a5b4fc;
}

/* Glass Card */
.glass-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 24px;
    backdrop-filter: blur(10px);
    margin-bottom: 24px;
}

/* Profile Card */
.profile-card {
    margin-bottom: 24px;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 24px;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.75rem;
    flex-shrink: 0;
}

.profile-info {
    flex: 1;
}

.profile-name {
    margin: 0 0 4px;
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
}

.profile-email {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
    margin-bottom: 12px;
}

.profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

.role-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 500;
}

.role-badge.admin {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.role-badge.user {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.profile-balance {
    text-align: right;
    padding: 16px 24px;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(22, 163, 74, 0.1) 100%);
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 16px;
}

.balance-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: #86efac;
    line-height: 1;
}

.balance-label {
    color: rgba(134, 239, 172, 0.7);
    font-size: 0.85rem;
    margin-top: 4px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.15);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.stat-card.sent .stat-icon {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.stat-card.received .stat-icon {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(22, 163, 74, 0.2) 100%);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.stat-card.transactions .stat-icon {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

.stat-card.positive .stat-icon {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2) 0%, rgba(22, 163, 74, 0.2) 100%);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.stat-card.negative .stat-icon {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
    line-height: 1;
}

.stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 4px;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 24px;
}

/* Card Headers */
.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.card-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.card-icon {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

.card-icon.alerts {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%);
    border: 1px solid rgba(251, 191, 36, 0.3);
    color: #fcd34d;
}

.card-icon.actions {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.2) 0%, rgba(6, 182, 212, 0.2) 100%);
    border: 1px solid rgba(14, 165, 233, 0.3);
    color: #7dd3fc;
}

.card-header h2 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #f1f5f9;
    flex: 1;
}

.transaction-count {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.4);
    background: rgba(255, 255, 255, 0.05);
    padding: 4px 12px;
    border-radius: 20px;
}

/* Transactions List */
.transactions-card {
    margin-bottom: 0;
}

.transactions-list {
    max-height: 500px;
    overflow-y: auto;
}

.transaction-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.transaction-item:last-child {
    border-bottom: none;
}

.tx-direction {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.tx-direction.sent {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
}

.tx-direction.received {
    background: rgba(34, 197, 94, 0.15);
    color: #86efac;
}

.tx-details {
    flex: 1;
    min-width: 0;
}

.tx-description {
    color: #f1f5f9;
    font-size: 0.9rem;
    margin-bottom: 2px;
}

.tx-description strong {
    color: #a5b4fc;
}

.tx-note {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    margin-bottom: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tx-date {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.75rem;
}

.tx-amount {
    font-weight: 700;
    font-size: 1rem;
    flex-shrink: 0;
}

.tx-amount.positive {
    color: #86efac;
}

.tx-amount.negative {
    color: #fca5a5;
}

/* Sidebar */
.sidebar-column {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.sidebar-column .glass-card {
    margin-bottom: 0;
}

/* Alerts List */
.alerts-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.alert-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.alert-item:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.12);
}

.alert-type {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.alert-type.pending {
    background: rgba(251, 191, 36, 0.2);
    color: #fcd34d;
}

.alert-type.resolved {
    background: rgba(34, 197, 94, 0.2);
    color: #86efac;
}

.alert-content {
    flex: 1;
}

.alert-title {
    color: #f1f5f9;
    font-size: 0.9rem;
    font-weight: 500;
}

.alert-date {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.75rem;
}

.alert-status {
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.alert-status.pending {
    background: rgba(251, 191, 36, 0.15);
    color: #fcd34d;
}

.alert-status.resolved {
    background: rgba(34, 197, 94, 0.15);
    color: #86efac;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 0.9rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    width: 100%;
}

.action-btn.edit {
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

.action-btn.edit:hover {
    background: rgba(99, 102, 241, 0.25);
}

.action-btn.message {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.action-btn.message:hover {
    background: rgba(34, 197, 94, 0.25);
}

.action-btn.adjust {
    background: rgba(251, 191, 36, 0.15);
    border: 1px solid rgba(251, 191, 36, 0.3);
    color: #fcd34d;
}

.action-btn.adjust:hover {
    background: rgba(251, 191, 36, 0.25);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 24px;
    color: rgba(255, 255, 255, 0.4);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.3;
}

.empty-state h3 {
    margin: 0 0 8px;
    color: #f1f5f9;
    font-size: 1.1rem;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

.empty-state.small {
    padding: 24px 16px;
}

.empty-state.small i {
    font-size: 2rem;
    margin-bottom: 10px;
}

.empty-state.small p {
    font-size: 0.85rem;
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.98) 0%, rgba(15, 23, 42, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    width: 90%;
    max-width: 480px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.5);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.modal-body {
    padding: 24px;
}

.current-balance {
    background: rgba(255, 255, 255, 0.05);
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    color: rgba(255, 255, 255, 0.7);
}

.current-balance strong {
    color: #86efac;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 10px;
    color: #f1f5f9;
    font-size: 0.95rem;
    box-sizing: border-box;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.adjustment-type-selector {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.radio-option input {
    display: none;
}

.radio-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: rgba(255, 255, 255, 0.6);
}

.radio-option input:checked + .radio-content.add {
    background: rgba(34, 197, 94, 0.15);
    border-color: rgba(34, 197, 94, 0.4);
    color: #86efac;
}

.radio-option input:checked + .radio-content.subtract {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.4);
    color: #fca5a5;
}

.modal-footer {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding: 16px 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.btn-cancel {
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
}

.btn-submit {
    padding: 12px 24px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

/* Responsive */
@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .user-report-container {
        padding: 0 16px 40px;
    }

    .profile-header {
        flex-direction: column;
        text-align: center;
    }

    .profile-balance {
        width: 100%;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .nav-bar {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
}

/* Scrollbar */
.transactions-list::-webkit-scrollbar {
    width: 6px;
}

.transactions-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.02);
}

.transactions-list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 3px;
}
</style>

<script>
function openBalanceModal() {
    document.getElementById('balanceModal').style.display = 'flex';
}

function closeBalanceModal() {
    document.getElementById('balanceModal').style.display = 'none';
}

// Close modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBalanceModal();
});

// Close modal on backdrop click
document.getElementById('balanceModal').addEventListener('click', function(e) {
    if (e.target === this) closeBalanceModal();
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
