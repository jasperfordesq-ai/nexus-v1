<?php
// Phoenix View: Organization Wallet (Glassmorphism)
// Path: views/modern/organizations/wallet.php

$hTitle = $org['name'] . ' - Wallet';
$hSubtitle = 'Organization Credits & Transfers';
$hGradient = 'htb-hero-gradient-wallet';
$hType = 'Organization Finance';
$hideHero = true;

// Set variables for the shared utility bar
$activeTab = 'wallet';
$isMember = $isMember ?? true; // They're viewing wallet, so they're a member
$isOwner = $isOwner ?? ($role === 'owner');
$pendingCount = $summary['pending_requests'] ?? 0;

// Calculate balance percentage for gauge
$thresholds = \Nexus\Services\BalanceAlertService::getThresholds($org['id']);
$maxBalance = max($thresholds['low'] * 3, $summary['balance'] * 1.2, 100);
$balancePercent = min(100, ($summary['balance'] / $maxBalance) * 100);

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

// Include shared UI components
include dirname(__DIR__) . '/components/org-ui-components.php';
?>

<!-- ORG WALLET GLASSMORPHISM -->
<style>
/* Animated Background */
.org-wallet-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #ecfdf5 25%, #d1fae5 50%, #ecfdf5 75%, #f8fafc 100%);
}

.org-wallet-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 20% 30%, rgba(16, 185, 129, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(5, 150, 105, 0.1) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 80%, rgba(52, 211, 153, 0.08) 0%, transparent 50%);
    animation: orgWalletFloat 20s ease-in-out infinite;
}

[data-theme="dark"] .org-wallet-bg {
    background: linear-gradient(135deg, #0f172a 0%, #064e3b 50%, #0f172a 100%);
}

[data-theme="dark"] .org-wallet-bg::before {
    background:
        radial-gradient(ellipse at 20% 30%, rgba(16, 185, 129, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(5, 150, 105, 0.15) 0%, transparent 45%);
}

@keyframes orgWalletFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-1%, 1%) scale(1.02); }
}

/* Container */
.org-wallet-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 120px 24px 40px 24px;
    position: relative;
    z-index: 10;
}

/* Page Header */
.org-wallet-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.org-wallet-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.org-wallet-title h1 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 800;
    color: #1f2937;
}

[data-theme="dark"] .org-wallet-title h1 {
    color: #f1f5f9;
}

.org-wallet-badge {
    padding: 6px 12px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.org-wallet-nav {
    display: flex;
    gap: 8px;
}

.org-wallet-nav-link {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 12px;
    color: #374151;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.org-wallet-nav-link:hover {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.4);
}

.org-wallet-nav-link.active {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border-color: transparent;
}

[data-theme="dark"] .org-wallet-nav-link {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(16, 185, 129, 0.3);
    color: #e2e8f0;
}

[data-theme="dark"] .org-wallet-nav-link:hover {
    background: rgba(16, 185, 129, 0.2);
}

/* Glass Cards */
.org-glass-card {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.9) 0%,
        rgba(255, 255, 255, 0.75) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
    overflow: hidden;
}

[data-theme="dark"] .org-glass-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.9) 0%,
        rgba(30, 41, 59, 0.75) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

/* Stats Grid */
.org-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.org-stat-card {
    padding: 24px;
    text-align: center;
}

.org-stat-card.balance {
    background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
    border: none;
    color: white;
}

.org-stat-card.balance::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 30% 30%, rgba(255, 255, 255, 0.15) 0%, transparent 40%);
    animation: balanceShine 8s ease-in-out infinite;
}

.org-stat-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.org-stat-card.balance .org-stat-icon {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.org-stat-card:not(.balance) .org-stat-icon {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.org-stat-value {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 4px;
}

.org-stat-card.balance .org-stat-value {
    color: white;
}

.org-stat-card:not(.balance) .org-stat-value {
    color: #1f2937;
}

[data-theme="dark"] .org-stat-card:not(.balance) .org-stat-value {
    color: #f1f5f9;
}

.org-stat-label {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.org-stat-card.balance .org-stat-label {
    color: rgba(255, 255, 255, 0.8);
}

.org-stat-card:not(.balance) .org-stat-label {
    color: #6b7280;
}

/* Main Grid Layout */
.org-wallet-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

/* Section Titles */
.org-section-title {
    margin: 0 0 20px 0;
    padding: 24px 24px 0 24px;
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .org-section-title {
    color: #f1f5f9;
}

/* Forms */
.org-form-content {
    padding: 0 24px 24px 24px;
}

.org-form-group {
    margin-bottom: 16px;
}

.org-form-label {
    display: block;
    font-weight: 600;
    font-size: 0.9rem;
    color: #374151;
    margin-bottom: 6px;
}

[data-theme="dark"] .org-form-label {
    color: #e2e8f0;
}

.org-form-input, .org-form-select, .org-form-textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid rgba(16, 185, 129, 0.2);
    border-radius: 12px;
    font-size: 1rem;
    background: white;
    color: #1f2937;
    transition: all 0.2s;
    outline: none;
}

.org-form-input:focus, .org-form-select:focus, .org-form-textarea:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

[data-theme="dark"] .org-form-input,
[data-theme="dark"] .org-form-select,
[data-theme="dark"] .org-form-textarea {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

[data-theme="dark"] .org-form-input:focus,
[data-theme="dark"] .org-form-select:focus,
[data-theme="dark"] .org-form-textarea:focus {
    border-color: #10b981;
}

.org-form-textarea {
    min-height: 80px;
    resize: vertical;
}

.org-form-hint {
    font-size: 0.8rem;
    color: #9ca3af;
    margin-top: 4px;
}

.org-submit-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    font-weight: 700;
    font-size: 1rem;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.org-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.org-submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.org-submit-btn.secondary {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.org-submit-btn.secondary:hover {
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

/* Member Select Autocomplete */
.org-member-search-wrapper {
    position: relative;
}

.org-member-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 2px solid rgba(16, 185, 129, 0.2);
    border-top: none;
    border-radius: 0 0 12px 12px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 100;
    display: none;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.org-member-results.show {
    display: block;
}

[data-theme="dark"] .org-member-results {
    background: #1e293b;
    border-color: rgba(255, 255, 255, 0.1);
}

.org-member-result {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    cursor: pointer;
    transition: background 0.15s;
    gap: 10px;
}

.org-member-result:hover {
    background: rgba(16, 185, 129, 0.08);
}

[data-theme="dark"] .org-member-result:hover {
    background: rgba(16, 185, 129, 0.15);
}

.org-member-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
    overflow: hidden;
}

.org-member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.org-member-info {
    flex: 1;
    min-width: 0;
}

.org-member-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.9rem;
}

[data-theme="dark"] .org-member-name {
    color: #f1f5f9;
}

.org-member-role {
    font-size: 0.75rem;
    color: #6b7280;
}

/* Selected member chip */
.org-selected-member {
    display: none;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: rgba(16, 185, 129, 0.1);
    border: 2px solid rgba(16, 185, 129, 0.3);
    border-radius: 12px;
    margin-bottom: 8px;
}

.org-selected-member.show {
    display: flex;
}

.org-selected-clear {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: transparent;
    border: none;
    color: #6b7280;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    margin-left: auto;
}

.org-selected-clear:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* Pending Requests */
.org-requests-list {
    padding: 0 24px 24px 24px;
}

.org-request-item {
    display: flex;
    align-items: center;
    padding: 16px;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 12px;
    margin-bottom: 12px;
    gap: 16px;
}

[data-theme="dark"] .org-request-item {
    background: rgba(30, 41, 59, 0.5);
}

.org-request-item:last-child {
    margin-bottom: 0;
}

.org-request-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    flex-shrink: 0;
}

.org-request-details {
    flex: 1;
    min-width: 0;
}

.org-request-title {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
}

[data-theme="dark"] .org-request-title {
    color: #f1f5f9;
}

.org-request-desc {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 2px;
}

.org-request-meta {
    font-size: 0.8rem;
    color: #9ca3af;
    margin-top: 4px;
}

.org-request-amount {
    font-weight: 700;
    font-size: 1.1rem;
    color: #f59e0b;
    margin-right: 16px;
}

.org-request-actions {
    display: flex;
    gap: 8px;
}

.org-request-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.org-request-btn.approve {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.org-request-btn.approve:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.org-request-btn.reject {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.org-request-btn.reject:hover {
    background: rgba(239, 68, 68, 0.2);
}

/* Transaction History */
.org-transactions-list {
    padding: 0 24px 24px 24px;
    max-height: 500px;
    overflow-y: auto;
}

.org-transaction {
    display: flex;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid rgba(229, 231, 235, 0.5);
    gap: 14px;
}

[data-theme="dark"] .org-transaction {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.org-transaction:last-child {
    border-bottom: none;
}

.org-tx-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.org-tx-icon.deposit {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.org-tx-icon.withdrawal {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.org-tx-details {
    flex: 1;
    min-width: 0;
}

.org-tx-title {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.9rem;
}

[data-theme="dark"] .org-tx-title {
    color: #f1f5f9;
}

.org-tx-desc {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 2px;
}

.org-tx-date {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 2px;
}

.org-tx-amount {
    font-weight: 700;
    font-size: 1rem;
}

.org-tx-amount.positive {
    color: #10b981;
}

.org-tx-amount.negative {
    color: #ef4444;
}

/* Empty State */
.org-empty {
    text-align: center;
    padding: 40px 20px;
    color: #9ca3af;
}

.org-empty-icon {
    font-size: 2.5rem;
    opacity: 0.3;
    margin-bottom: 12px;
}

/* Chart Container */
.org-chart-container {
    padding: 24px;
}

.org-chart-title {
    margin: 0 0 16px 0;
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
}

[data-theme="dark"] .org-chart-title {
    color: #f1f5f9;
}

/* Info Box */
.org-info-box {
    padding: 16px;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.org-info-box i {
    color: #10b981;
    font-size: 1.1rem;
    margin-top: 2px;
}

.org-info-box p {
    margin: 0;
    font-size: 0.9rem;
    color: #374151;
    line-height: 1.5;
}

[data-theme="dark"] .org-info-box {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.3);
}

[data-theme="dark"] .org-info-box p {
    color: #e2e8f0;
}

/* User Balance Hint */
.org-user-balance {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 10px;
    margin-bottom: 16px;
}

.org-user-balance-label {
    font-size: 0.85rem;
    color: #6366f1;
    font-weight: 500;
}

.org-user-balance-value {
    font-weight: 700;
    color: #4f46e5;
}

/* Role Badge */
.org-role-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.org-role-badge.owner {
    background: rgba(251, 191, 36, 0.2);
    color: #b45309;
}

.org-role-badge.admin {
    background: rgba(99, 102, 241, 0.2);
    color: #4f46e5;
}

.org-role-badge.member {
    background: rgba(107, 114, 128, 0.2);
    color: #6b7280;
}

/* Export Button */
.org-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 8px;
    color: #10b981;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.org-export-btn:hover {
    background: rgba(16, 185, 129, 0.2);
    border-color: rgba(16, 185, 129, 0.5);
}

[data-theme="dark"] .org-export-btn {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.4);
}

/* Mobile Responsive */
@media (max-width: 1024px) {
    .org-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .org-wallet-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .org-wallet-container {
        padding: 100px 16px 100px 16px;
    }

    .org-wallet-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .org-wallet-nav {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .org-wallet-nav-link {
        white-space: nowrap;
        padding: 8px 14px;
        font-size: 0.85rem;
    }

    .org-stats-grid {
        grid-template-columns: 1fr 1fr;
    }

    .org-stat-value {
        font-size: 1.5rem;
    }

    .org-request-item {
        flex-wrap: wrap;
    }

    .org-request-actions {
        width: 100%;
        margin-top: 12px;
    }

    .org-request-btn {
        flex: 1;
    }
}

@media (max-width: 480px) {
    .org-stats-grid {
        grid-template-columns: 1fr;
    }

    .org-form-input {
        font-size: 16px; /* Prevent iOS zoom */
    }
}
</style>

<div class="org-wallet-bg"></div>

<div class="org-wallet-container">
    <!-- Shared Organization Utility Bar -->
    <?php include __DIR__ . '/_org-utility-bar.php'; ?>

    <!-- Stats Grid -->
    <div class="org-stats-grid">
        <?php
        // Get balance status for indicator
        $balanceStatus = \Nexus\Services\BalanceAlertService::getBalanceStatus($org['id']);
        $statusColors = [
            'critical' => ['bg' => 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)', 'icon' => 'fa-triangle-exclamation'],
            'low' => ['bg' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)', 'icon' => 'fa-exclamation-circle'],
            'healthy' => ['bg' => 'linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%)', 'icon' => 'fa-coins']
        ];
        $statusStyle = $statusColors[$balanceStatus['status']] ?? $statusColors['healthy'];
        ?>
        <div class="org-glass-card org-stat-card balance" style="background: <?= $statusStyle['bg'] ?>; position: relative;" role="region" aria-label="Current Balance">
            <?php if ($balanceStatus['status'] !== 'healthy'): ?>
            <div style="position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.25); padding: 4px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase;" role="status">
                <?= $balanceStatus['label'] ?>
            </div>
            <?php endif; ?>
            <!-- Live update indicator -->
            <div class="org-live-indicator" id="liveIndicator" style="position: absolute; top: 12px; left: 12px; display: none;">
                <span class="org-live-dot"></span>
                <span>Live</span>
            </div>
            <div class="org-stat-icon">
                <i class="fa-solid <?= $statusStyle['icon'] ?>" aria-hidden="true"></i>
            </div>
            <div class="org-stat-value" id="balanceValue" aria-live="polite"><?= number_format($summary['balance'], 1) ?></div>
            <div class="org-stat-label">Current Balance</div>
            <!-- Balance Gauge -->
            <div class="org-balance-gauge" role="progressbar" aria-valuenow="<?= $summary['balance'] ?>" aria-valuemin="0" aria-valuemax="<?= $maxBalance ?>">
                <div class="org-balance-gauge-fill <?= $balanceStatus['status'] ?>" style="width: <?= $balancePercent ?>%;"></div>
            </div>
            <div class="org-balance-thresholds">
                <span>0</span>
                <span>Low: <?= number_format($thresholds['low']) ?></span>
                <span>Critical: <?= number_format($thresholds['critical']) ?></span>
            </div>
            <?php if ($balanceStatus['status'] !== 'healthy'): ?>
            <div style="font-size: 0.75rem; opacity: 0.9; margin-top: 4px;"><?= $balanceStatus['message'] ?></div>
            <?php endif; ?>
        </div>
        <div class="org-glass-card org-stat-card">
            <div class="org-stat-icon">
                <i class="fa-solid fa-arrow-down"></i>
            </div>
            <div class="org-stat-value"><?= number_format($summary['total_received'], 1) ?></div>
            <div class="org-stat-label">Total Received</div>
        </div>
        <div class="org-glass-card org-stat-card">
            <div class="org-stat-icon">
                <i class="fa-solid fa-arrow-up"></i>
            </div>
            <div class="org-stat-value"><?= number_format($summary['total_paid_out'], 1) ?></div>
            <div class="org-stat-label">Total Paid Out</div>
        </div>
        <div class="org-glass-card org-stat-card">
            <div class="org-stat-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="org-stat-value"><?= $summary['transaction_count'] ?></div>
            <div class="org-stat-label">Transactions</div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="org-wallet-grid">
        <!-- Left Column: Actions -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- Deposit Form -->
            <div class="org-glass-card">
                <h3 class="org-section-title">
                    <i class="fa-solid fa-arrow-down-to-bracket" style="color: #10b981;"></i>
                    Deposit to Organization
                </h3>
                <div class="org-form-content">
                    <div class="org-info-box">
                        <i class="fa-solid fa-info-circle"></i>
                        <p>Deposit credits from your personal wallet to the organization's shared wallet.</p>
                    </div>

                    <div class="org-user-balance">
                        <span class="org-user-balance-label">Your Balance</span>
                        <span class="org-user-balance-value"><?= number_format($user['balance'], 1) ?> HRS</span>
                    </div>

                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet/deposit" method="POST" id="depositForm" onsubmit="return validateAndSubmitDeposit(event)">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <div class="org-form-group" id="depositAmountGroup">
                            <label class="org-form-label" for="depositAmount">Amount (Hours) <span style="color: #ef4444;">*</span></label>
                            <input type="number" name="amount" id="depositAmount" min="0.5" max="<?= $user['balance'] ?>" step="0.5" required
                                   placeholder="Enter amount" class="org-form-input" aria-describedby="depositAmountHint depositAmountError">
                            <i class="fa-solid fa-check org-validation-icon valid-icon" aria-hidden="true"></i>
                            <i class="fa-solid fa-times org-validation-icon invalid-icon" aria-hidden="true"></i>
                            <div class="org-validation-message" id="depositAmountError" role="alert"></div>
                            <div class="org-form-hint" id="depositAmountHint">Max: <?= number_format($user['balance'], 1) ?> HRS</div>
                        </div>
                        <div class="org-form-group">
                            <label class="org-form-label" for="depositDesc">Description (Optional)</label>
                            <input type="text" name="description" id="depositDesc" placeholder="e.g. Monthly contribution" class="org-form-input">
                        </div>
                        <button type="submit" class="org-submit-btn" id="depositBtn" <?= $user['balance'] <= 0 ? 'disabled' : '' ?>>
                            <span class="org-btn-text">
                                <i class="fa-solid fa-arrow-down" style="margin-right: 8px;"></i>
                                Deposit Credits
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Request Transfer Form -->
            <div class="org-glass-card">
                <h3 class="org-section-title">
                    <i class="fa-solid fa-hand-holding-dollar" style="color: #6366f1;"></i>
                    Request Transfer
                </h3>
                <div class="org-form-content">
                    <div class="org-info-box">
                        <i class="fa-solid fa-info-circle"></i>
                        <p>Request credits from the organization wallet. An admin will need to approve your request.</p>
                    </div>

                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet/request" method="POST" id="requestTransferForm">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="recipient_id" id="requestRecipientId" value="0">

                        <div class="org-form-group">
                            <label class="org-form-label">Recipient</label>
                            <select name="recipient_type" class="org-form-select" onchange="toggleRecipientSelect(this)">
                                <option value="self">Myself</option>
                                <option value="other">Another Member</option>
                            </select>
                        </div>

                        <div class="org-form-group" id="memberSelectGroup" style="display: none;">
                            <label class="org-form-label">Select Member</label>
                            <div class="org-selected-member" id="selectedMember">
                                <div class="org-member-avatar" id="selectedMemberAvatar">?</div>
                                <div class="org-member-info">
                                    <div class="org-member-name" id="selectedMemberName">-</div>
                                    <div class="org-member-role" id="selectedMemberRole">-</div>
                                </div>
                                <button type="button" class="org-selected-clear" onclick="clearMemberSelection()">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                            <div class="org-member-search-wrapper" id="memberSearchWrapper">
                                <input type="text" id="memberSearch" placeholder="Search members..."
                                       class="org-form-input" autocomplete="off">
                                <div class="org-member-results" id="memberResults"></div>
                            </div>
                        </div>

                        <div class="org-form-group" id="requestAmountGroup">
                            <label class="org-form-label" for="requestAmount">Amount (Hours) <span style="color: #ef4444;">*</span></label>
                            <input type="number" name="amount" id="requestAmount" min="0.5" max="<?= $summary['balance'] ?>" step="0.5" required
                                   placeholder="Enter amount" class="org-form-input" aria-describedby="requestAmountHint requestAmountError">
                            <i class="fa-solid fa-check org-validation-icon valid-icon" aria-hidden="true"></i>
                            <i class="fa-solid fa-times org-validation-icon invalid-icon" aria-hidden="true"></i>
                            <div class="org-validation-message" id="requestAmountError" role="alert"></div>
                            <div class="org-form-hint" id="requestAmountHint">Organization balance: <?= number_format($summary['balance'], 1) ?> HRS</div>
                        </div>
                        <div class="org-form-group" id="requestDescGroup">
                            <label class="org-form-label" for="requestDesc">Reason for Request <span style="color: #ef4444;">*</span></label>
                            <textarea name="description" id="requestDesc" required placeholder="Explain why you need these credits..."
                                      class="org-form-textarea" aria-describedby="requestDescError" minlength="10"></textarea>
                            <i class="fa-solid fa-check org-validation-icon valid-icon" aria-hidden="true"></i>
                            <i class="fa-solid fa-times org-validation-icon invalid-icon" aria-hidden="true"></i>
                            <div class="org-validation-message" id="requestDescError" role="alert"></div>
                        </div>
                        <button type="submit" class="org-submit-btn secondary" id="requestBtn">
                            <span class="org-btn-text">
                                <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i>
                                Submit Request
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($isAdmin): ?>
            <!-- Direct Transfer (Admin Only) -->
            <div class="org-glass-card">
                <h3 class="org-section-title">
                    <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i>
                    Direct Transfer (Admin)
                </h3>
                <div class="org-form-content">
                    <div class="org-info-box" style="background: rgba(251, 191, 36, 0.1); border-color: rgba(251, 191, 36, 0.3);">
                        <i class="fa-solid fa-shield-halved" style="color: #f59e0b;"></i>
                        <p>As an admin, you can directly transfer credits without approval.</p>
                    </div>

                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet/direct-transfer" method="POST" id="directTransferForm">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="recipient_id" id="directRecipientId" value="">

                        <div class="org-form-group">
                            <label class="org-form-label">Recipient</label>
                            <div class="org-selected-member" id="directSelectedMember">
                                <div class="org-member-avatar" id="directSelectedAvatar">?</div>
                                <div class="org-member-info">
                                    <div class="org-member-name" id="directSelectedName">-</div>
                                    <div class="org-member-role" id="directSelectedRole">-</div>
                                </div>
                                <button type="button" class="org-selected-clear" onclick="clearDirectSelection()">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                            <div class="org-member-search-wrapper" id="directSearchWrapper">
                                <input type="text" id="directMemberSearch" placeholder="Search members..."
                                       class="org-form-input" autocomplete="off">
                                <div class="org-member-results" id="directMemberResults"></div>
                            </div>
                        </div>

                        <div class="org-form-group">
                            <label class="org-form-label">Amount (Hours)</label>
                            <input type="number" name="amount" min="0.5" max="<?= $summary['balance'] ?>" step="0.5" required
                                   placeholder="Enter amount" class="org-form-input">
                        </div>
                        <div class="org-form-group">
                            <label class="org-form-label">Description</label>
                            <input type="text" name="description" required placeholder="e.g. Volunteer reward" class="org-form-input">
                        </div>
                        <button type="submit" class="org-submit-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);"
                                <?= $summary['balance'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-bolt" style="margin-right: 8px;"></i>
                            Transfer Now
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Requests & History -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <?php if ($isAdmin && !empty($pendingRequests)): ?>
            <!-- Pending Requests (Admin) -->
            <div class="org-glass-card" id="pendingRequestsContainer">
                <h3 class="org-section-title">
                    <i class="fa-solid fa-inbox" style="color: #f59e0b;"></i>
                    Pending Requests
                    <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-left: auto;">
                        <?= count($pendingRequests) ?>
                    </span>
                </h3>

                <!-- Bulk Selection Bar -->
                <div class="org-select-all-bar" id="bulkActionBar">
                    <div class="org-checkbox-wrapper">
                        <input type="checkbox" class="org-checkbox org-select-all" id="selectAllRequests" aria-label="Select all requests">
                        <span class="org-select-count">0 selected</span>
                    </div>
                    <div class="org-bulk-actions">
                        <button type="button" class="org-bulk-btn approve" onclick="bulkApprove()" aria-label="Approve selected requests">
                            <i class="fa-solid fa-check"></i> Approve All
                        </button>
                        <button type="button" class="org-bulk-btn reject" onclick="bulkReject()" aria-label="Reject selected requests">
                            <i class="fa-solid fa-times"></i> Reject All
                        </button>
                    </div>
                </div>

                <div class="org-requests-list" role="list" aria-label="Pending transfer requests">
                    <?php foreach ($pendingRequests as $request): ?>
                    <div class="org-request-item" role="listitem" data-request-id="<?= $request['id'] ?>">
                        <div class="org-checkbox-wrapper">
                            <input type="checkbox" class="org-checkbox org-bulk-checkbox" data-id="<?= $request['id'] ?>"
                                   aria-label="Select request from <?= htmlspecialchars($request['requester_name']) ?>">
                        </div>
                        <div class="org-request-avatar" aria-hidden="true">
                            <?= strtoupper(substr($request['requester_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="org-request-details">
                            <div class="org-request-title">
                                <?= htmlspecialchars($request['requester_name']) ?>
                                <i class="fa-solid fa-arrow-right" style="font-size: 0.7rem; color: #9ca3af; margin: 0 4px;" aria-hidden="true"></i>
                                <?= htmlspecialchars($request['recipient_name']) ?>
                            </div>
                            <div class="org-request-desc"><?= htmlspecialchars($request['description'] ?? 'No description') ?></div>
                            <div class="org-request-meta"><?= date('M d, Y g:i A', strtotime($request['created_at'])) ?></div>
                        </div>
                        <div class="org-request-amount" aria-label="Amount"><?= number_format($request['amount'], 1) ?> HRS</div>
                        <div class="org-request-actions">
                            <button type="button" class="org-request-btn approve"
                                    onclick="confirmApprove(<?= $request['id'] ?>, '<?= htmlspecialchars($request['requester_name'], ENT_QUOTES) ?>', <?= $request['amount'] ?>)"
                                    aria-label="Approve request for <?= number_format($request['amount'], 1) ?> HRS">
                                <i class="fa-solid fa-check" aria-hidden="true"></i> Approve
                            </button>
                            <button type="button" class="org-request-btn reject"
                                    onclick="confirmReject(<?= $request['id'] ?>, '<?= htmlspecialchars($request['requester_name'], ENT_QUOTES) ?>')"
                                    aria-label="Reject request">
                                <i class="fa-solid fa-times" aria-hidden="true"></i> Reject
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Hidden forms for submission -->
                <form id="approveForm" action="" method="POST" style="display: none;">
                    <?= \Nexus\Core\Csrf::input() ?>
                </form>
                <form id="rejectForm" action="" method="POST" style="display: none;">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="reason" id="rejectReasonInput">
                </form>
            </div>
            <?php endif; ?>

            <!-- Transaction History -->
            <div class="org-glass-card">
                <h3 class="org-section-title">
                    <i class="fa-solid fa-clock-rotate-left" style="color: #10b981;"></i>
                    Transaction History
                    <?php if ($isAdmin): ?>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet/export"
                       class="org-export-btn" title="Export transactions to CSV" style="margin-left: auto;">
                        <i class="fa-solid fa-download"></i> Export
                    </a>
                    <?php endif; ?>
                </h3>
                <div class="org-transactions-list" role="list" aria-label="Transaction history">
                    <?php if (empty($transactions)): ?>
                    <div class="org-empty-state">
                        <div class="org-empty-illustration">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <h4 class="org-empty-title">No transactions yet</h4>
                        <p class="org-empty-description">
                            Start by depositing credits from your personal wallet or request a transfer from the organization.
                        </p>
                        <div class="org-empty-actions">
                            <a href="#depositForm" class="org-empty-btn primary" onclick="document.getElementById('depositAmount').focus(); return false;">
                                <i class="fa-solid fa-plus"></i>
                                Make First Deposit
                            </a>
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet" class="org-empty-btn secondary">
                                <i class="fa-solid fa-wallet"></i>
                                View My Wallet
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <?php
                        $isDeposit = ($tx['sender_type'] ?? '') === 'user' || ($tx['type'] ?? '') === 'deposit';
                        $typeClass = $isDeposit ? 'deposit' : 'withdrawal';
                        $icon = $isDeposit ? 'fa-arrow-down' : 'fa-arrow-up';
                        $sign = $isDeposit ? '+' : '-';
                        ?>
                        <div class="org-transaction">
                            <div class="org-tx-icon <?= $typeClass ?>">
                                <i class="fa-solid <?= $icon ?>"></i>
                            </div>
                            <div class="org-tx-details">
                                <div class="org-tx-title">
                                    <?php if ($isDeposit): ?>
                                        Deposit from <?= htmlspecialchars($tx['sender_name'] ?? 'Member') ?>
                                    <?php else: ?>
                                        Transfer to <?= htmlspecialchars($tx['receiver_name'] ?? 'Member') ?>
                                    <?php endif; ?>
                                </div>
                                <div class="org-tx-desc"><?= htmlspecialchars($tx['description'] ?? '-') ?></div>
                                <div class="org-tx-date"><?= date('M d, Y g:i A', strtotime($tx['created_at'])) ?></div>
                            </div>
                            <div class="org-tx-amount <?= $isDeposit ? 'positive' : 'negative' ?>">
                                <?= $sign ?><?= number_format($tx['amount'], 1) ?> HRS
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Monthly Stats Chart -->
            <?php if (!empty($monthlyStats)): ?>
            <div class="org-glass-card">
                <div class="org-chart-container">
                    <h4 class="org-chart-title">
                        <i class="fa-solid fa-chart-line" style="color: #10b981; margin-right: 8px;"></i>
                        Monthly Activity
                    </h4>
                    <div id="monthlyChartWrapper" style="position: relative; height: 220px;">
                        <!-- SVG Chart - more reliable than Canvas -->
                        <svg id="monthlyChart" width="100%" height="200" viewBox="0 0 400 200" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Monthly activity chart"></svg>
                        <div id="chartLegend" style="display: flex; justify-content: center; gap: 24px; margin-top: 12px; font-size: 0.8rem; color: #6b7280;">
                            <span><i class="fa-solid fa-circle" style="color: #10b981; font-size: 8px; margin-right: 6px;"></i>Received</span>
                            <span><i class="fa-solid fa-circle" style="color: #ef4444; font-size: 8px; margin-right: 6px;"></i>Paid Out</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Configuration
const orgMembers = <?= json_encode($members) ?>;
const basePath = '<?= \Nexus\Core\TenantContext::getBasePath() ?>';
const orgId = <?= $org['id'] ?>;
const userBalance = <?= $user['balance'] ?>;
const orgBalance = <?= $summary['balance'] ?>;
const monthlyStats = <?= !empty($monthlyStats) ? json_encode($monthlyStats) : '[]' ?>;

// Toggle recipient select
function toggleRecipientSelect(select) {
    const group = document.getElementById('memberSelectGroup');
    const recipientId = document.getElementById('requestRecipientId');

    if (select.value === 'other') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
        recipientId.value = '0';
        clearMemberSelection();
    }
}

// Member search autocomplete for request form
const memberSearch = document.getElementById('memberSearch');
const memberResults = document.getElementById('memberResults');

if (memberSearch) {
    memberSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        if (query.length < 1) {
            memberResults.classList.remove('show');
            return;
        }

        const filtered = orgMembers.filter(m =>
            (m.display_name || '').toLowerCase().includes(query) ||
            (m.email || '').toLowerCase().includes(query)
        );

        if (filtered.length > 0) {
            memberResults.innerHTML = filtered.map(m => `
                <div class="org-member-result" onclick="selectMember(${m.user_id}, '${escapeHtml(m.display_name)}', '${m.role}', '${escapeHtml(m.avatar_url || '')}')">
                    <div class="org-member-avatar">
                        ${m.avatar_url ? `<img src="${m.avatar_url}" alt="" loading="lazy">` : (m.display_name || '?')[0].toUpperCase()}
                    </div>
                    <div class="org-member-info">
                        <div class="org-member-name">${escapeHtml(m.display_name)}</div>
                        <div class="org-member-role">${m.role}</div>
                    </div>
                </div>
            `).join('');
            memberResults.classList.add('show');
        } else {
            memberResults.innerHTML = '<div style="padding: 12px; text-align: center; color: #9ca3af;">No members found</div>';
            memberResults.classList.add('show');
        }
    });
}

function selectMember(id, name, role, avatar) {
    document.getElementById('requestRecipientId').value = id;
    document.getElementById('selectedMemberName').textContent = name;
    document.getElementById('selectedMemberRole').textContent = role;
    document.getElementById('selectedMemberAvatar').innerHTML = avatar
        ? `<img src="${avatar}" alt="" loading="lazy">`
        : name[0].toUpperCase();

    document.getElementById('selectedMember').classList.add('show');
    document.getElementById('memberSearchWrapper').style.display = 'none';
    memberResults.classList.remove('show');
}

function clearMemberSelection() {
    document.getElementById('requestRecipientId').value = '';
    document.getElementById('selectedMember').classList.remove('show');
    document.getElementById('memberSearchWrapper').style.display = 'block';
    document.getElementById('memberSearch').value = '';
}

// Direct transfer member search (Admin)
const directSearch = document.getElementById('directMemberSearch');
const directResults = document.getElementById('directMemberResults');

if (directSearch) {
    directSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        if (query.length < 1) {
            directResults.classList.remove('show');
            return;
        }

        const filtered = orgMembers.filter(m =>
            (m.display_name || '').toLowerCase().includes(query) ||
            (m.email || '').toLowerCase().includes(query)
        );

        if (filtered.length > 0) {
            directResults.innerHTML = filtered.map(m => `
                <div class="org-member-result" onclick="selectDirectMember(${m.user_id}, '${escapeHtml(m.display_name)}', '${m.role}', '${escapeHtml(m.avatar_url || '')}')">
                    <div class="org-member-avatar">
                        ${m.avatar_url ? `<img src="${m.avatar_url}" alt="" loading="lazy">` : (m.display_name || '?')[0].toUpperCase()}
                    </div>
                    <div class="org-member-info">
                        <div class="org-member-name">${escapeHtml(m.display_name)}</div>
                        <div class="org-member-role">${m.role}</div>
                    </div>
                </div>
            `).join('');
            directResults.classList.add('show');
        } else {
            directResults.innerHTML = '<div style="padding: 12px; text-align: center; color: #9ca3af;">No members found</div>';
            directResults.classList.add('show');
        }
    });
}

function selectDirectMember(id, name, role, avatar) {
    document.getElementById('directRecipientId').value = id;
    document.getElementById('directSelectedName').textContent = name;
    document.getElementById('directSelectedRole').textContent = role;
    document.getElementById('directSelectedAvatar').innerHTML = avatar
        ? `<img src="${avatar}" alt="" loading="lazy">`
        : name[0].toUpperCase();

    document.getElementById('directSelectedMember').classList.add('show');
    document.getElementById('directSearchWrapper').style.display = 'none';
    directResults.classList.remove('show');
}

function clearDirectSelection() {
    document.getElementById('directRecipientId').value = '';
    document.getElementById('directSelectedMember').classList.remove('show');
    document.getElementById('directSearchWrapper').style.display = 'block';
    document.getElementById('directMemberSearch').value = '';
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.org-member-search-wrapper')) {
        memberResults?.classList.remove('show');
        directResults?.classList.remove('show');
    }
});

// ==============================================
// FORM VALIDATION & SUBMISSION
// ==============================================

// Deposit form validation
function validateAndSubmitDeposit(event) {
    event.preventDefault();
    const form = document.getElementById('depositForm');
    const amountInput = document.getElementById('depositAmount');
    const amount = parseFloat(amountInput.value);
    const btn = document.getElementById('depositBtn');

    // Validate amount
    let isValid = true;
    if (isNaN(amount) || amount < 0.5) {
        OrgUI.validation.setInvalid(document.getElementById('depositAmountGroup'), 'Minimum amount is 0.5 HRS');
        isValid = false;
    } else if (amount > userBalance) {
        OrgUI.validation.setInvalid(document.getElementById('depositAmountGroup'), `You only have ${userBalance.toFixed(1)} HRS available`);
        isValid = false;
    } else {
        OrgUI.validation.setValid(document.getElementById('depositAmountGroup'));
    }

    if (!isValid) return false;

    // Show loading state
    OrgUI.loading.setButton(btn, true);

    // Submit form
    form.submit();
    return true;
}

// Request form validation (add to form submit)
document.getElementById('requestTransferForm')?.addEventListener('submit', function(event) {
    event.preventDefault();
    const form = this;
    const amountInput = document.getElementById('requestAmount');
    const descInput = document.getElementById('requestDesc');
    const amount = parseFloat(amountInput.value);
    const desc = descInput.value.trim();
    const btn = document.getElementById('requestBtn');

    let isValid = true;

    // Validate amount
    if (isNaN(amount) || amount < 0.5) {
        OrgUI.validation.setInvalid(document.getElementById('requestAmountGroup'), 'Minimum amount is 0.5 HRS');
        isValid = false;
    } else if (amount > orgBalance) {
        OrgUI.validation.setInvalid(document.getElementById('requestAmountGroup'), `Organization only has ${orgBalance.toFixed(1)} HRS available`);
        isValid = false;
    } else {
        OrgUI.validation.setValid(document.getElementById('requestAmountGroup'));
    }

    // Validate description
    if (desc.length < 10) {
        OrgUI.validation.setInvalid(document.getElementById('requestDescGroup'), 'Please provide a reason (at least 10 characters)');
        isValid = false;
    } else {
        OrgUI.validation.setValid(document.getElementById('requestDescGroup'));
    }

    // Check recipient if "other" is selected
    const recipientType = form.querySelector('select[name="recipient_type"]')?.value;
    if (recipientType === 'other' && !document.getElementById('requestRecipientId').value) {
        OrgUI.toast.error('Missing recipient', 'Please select a member to receive the transfer');
        isValid = false;
    }

    if (!isValid) return false;

    // Show loading state
    OrgUI.loading.setButton(btn, true);

    // Submit form
    form.submit();
    return true;
});

// ==============================================
// CONFIRMATION MODALS FOR APPROVE/REJECT
// ==============================================

async function confirmApprove(requestId, requesterName, amount) {
    const confirmed = await OrgUI.modal.confirm(
        `Approve transfer of ${amount.toFixed(1)} HRS requested by ${requesterName}?`,
        'Approve Transfer Request'
    );

    if (confirmed) {
        const form = document.getElementById('approveForm');
        form.action = `${basePath}/organizations/${orgId}/wallet/approve/${requestId}`;
        form.submit();
    }
}

async function confirmReject(requestId, requesterName) {
    const reason = await OrgUI.modal.prompt(
        `Please provide a reason for rejecting ${requesterName}'s request (optional):`,
        'Reject Transfer Request',
        'e.g. Insufficient documentation'
    );

    if (reason !== null) {
        const form = document.getElementById('rejectForm');
        form.action = `${basePath}/organizations/${orgId}/wallet/reject/${requestId}`;
        document.getElementById('rejectReasonInput').value = reason;
        form.submit();
    }
}

// ==============================================
// BULK SELECTION FOR REQUESTS
// ==============================================

// Initialize bulk selection
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('pendingRequestsContainer');
    if (container) {
        OrgUI.bulkSelect.init('#pendingRequestsContainer', {
            onSelectionChange: (selected) => {
                const bar = document.getElementById('bulkActionBar');
                const count = container.querySelector('.org-select-count');
                if (selected.length > 0) {
                    bar.classList.add('show');
                    count.textContent = `${selected.length} selected`;
                } else {
                    bar.classList.remove('show');
                }
            }
        });
    }
});

async function bulkApprove() {
    const selected = OrgUI.bulkSelect.getSelected();
    if (selected.length === 0) {
        OrgUI.toast.warning('No selection', 'Please select at least one request');
        return;
    }

    const confirmed = await OrgUI.modal.confirm(
        `Approve ${selected.length} transfer request(s)?`,
        'Bulk Approve'
    );

    if (confirmed) {
        // Submit bulk approve
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/organizations/${orgId}/wallet/bulk-approve`;
        form.innerHTML = `<input type="hidden" name="csrf_token" value="${document.querySelector('[name=csrf_token]')?.value || ''}">`;
        selected.forEach(id => {
            form.innerHTML += `<input type="hidden" name="request_ids[]" value="${id}">`;
        });
        document.body.appendChild(form);
        form.submit();
    }
}

async function bulkReject() {
    const selected = OrgUI.bulkSelect.getSelected();
    if (selected.length === 0) {
        OrgUI.toast.warning('No selection', 'Please select at least one request');
        return;
    }

    const reason = await OrgUI.modal.prompt(
        `Reject ${selected.length} request(s)? Enter a reason (optional):`,
        'Bulk Reject',
        'e.g. Budget constraints'
    );

    if (reason !== null) {
        // Submit bulk reject
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${basePath}/organizations/${orgId}/wallet/bulk-reject`;
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${document.querySelector('[name=csrf_token]')?.value || ''}">
            <input type="hidden" name="reason" value="${escapeHtml(reason)}">
        `;
        selected.forEach(id => {
            form.innerHTML += `<input type="hidden" name="request_ids[]" value="${id}">`;
        });
        document.body.appendChild(form);
        form.submit();
    }
}

// ==============================================
// LIVE UPDATES (Polling)
// ==============================================

document.addEventListener('DOMContentLoaded', function() {
    const indicator = document.getElementById('liveIndicator');
    const balanceValue = document.getElementById('balanceValue');

    if (indicator && balanceValue) {
        // Start polling for balance updates every 30 seconds
        OrgUI.liveUpdate.start({
            url: `${basePath}/api/organizations/${orgId}/wallet/balance`,
            interval: 30000,
            indicator: indicator,
            onUpdate: (data) => {
                if (data && data.balance !== undefined) {
                    const newBalance = parseFloat(data.balance);
                    const currentBalance = parseFloat(balanceValue.textContent.replace(/,/g, ''));
                    if (newBalance !== currentBalance) {
                        balanceValue.textContent = newBalance.toFixed(1);
                        OrgUI.toast.info('Balance updated', `New balance: ${newBalance.toFixed(1)} HRS`);
                    }
                }
            }
        });
    }
});

// ==============================================
// SVG MONTHLY CHART
// ==============================================

function renderMonthlyChart() {
    if (!monthlyStats || monthlyStats.length === 0) return;

    const svg = document.getElementById('monthlyChart');
    if (!svg) return;

    const width = 400;
    const height = 180;
    const padding = { top: 20, right: 20, bottom: 30, left: 50 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;

    // Find max value for scaling
    const maxReceived = Math.max(...monthlyStats.map(d => parseFloat(d.received) || 0));
    const maxPaid = Math.max(...monthlyStats.map(d => parseFloat(d.paid_out) || 0));
    const maxValue = Math.max(maxReceived, maxPaid, 10);

    // Scale functions
    const xScale = (i) => padding.left + (i / (monthlyStats.length - 1 || 1)) * chartWidth;
    const yScale = (v) => padding.top + chartHeight - (v / maxValue) * chartHeight;

    // Generate path for received line
    const receivedPath = monthlyStats.map((d, i) => {
        const x = xScale(i);
        const y = yScale(parseFloat(d.received) || 0);
        return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');

    // Generate path for paid out line
    const paidPath = monthlyStats.map((d, i) => {
        const x = xScale(i);
        const y = yScale(parseFloat(d.paid_out) || 0);
        return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');

    // Generate area fill for received
    const receivedArea = receivedPath +
        ` L ${xScale(monthlyStats.length - 1)} ${padding.top + chartHeight}` +
        ` L ${padding.left} ${padding.top + chartHeight} Z`;

    // Generate month labels
    const labels = monthlyStats.map((d, i) => {
        const x = xScale(i);
        return `<text x="${x}" y="${height - 5}" text-anchor="middle" fill="#9ca3af" font-size="10">${d.month || ''}</text>`;
    }).join('');

    // Generate Y-axis labels
    const yLabels = [0, maxValue / 2, maxValue].map(v => {
        const y = yScale(v);
        return `<text x="${padding.left - 10}" y="${y + 4}" text-anchor="end" fill="#9ca3af" font-size="10">${v.toFixed(0)}</text>`;
    }).join('');

    // Grid lines
    const gridLines = [0, maxValue / 2, maxValue].map(v => {
        const y = yScale(v);
        return `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="rgba(156, 163, 175, 0.2)" stroke-dasharray="4"/>`;
    }).join('');

    // Generate dots for received
    const receivedDots = monthlyStats.map((d, i) => {
        const x = xScale(i);
        const y = yScale(parseFloat(d.received) || 0);
        return `<circle cx="${x}" cy="${y}" r="4" fill="#10b981"/>`;
    }).join('');

    // Generate dots for paid out
    const paidDots = monthlyStats.map((d, i) => {
        const x = xScale(i);
        const y = yScale(parseFloat(d.paid_out) || 0);
        return `<circle cx="${x}" cy="${y}" r="4" fill="#ef4444"/>`;
    }).join('');

    svg.innerHTML = `
        ${gridLines}
        ${yLabels}
        ${labels}
        <path d="${receivedArea}" fill="rgba(16, 185, 129, 0.1)"/>
        <path d="${receivedPath}" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="${paidPath}" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        ${receivedDots}
        ${paidDots}
    `;
}

// Render chart on load
document.addEventListener('DOMContentLoaded', renderMonthlyChart);

// ==============================================
// MEMBER SEARCH AUTOCOMPLETE
// ==============================================

// Escape HTML helper
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
