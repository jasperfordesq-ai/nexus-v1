<?php
// Phoenix View: Wallet (Glassmorphism Upgrade)
// Path: views/modern/wallet/index.php

$hTitle = 'My Wallet';
$hSubtitle = 'Time Credits & Transactions';
$hGradient = 'htb-hero-gradient-wallet'; // Indigo/Violet/Purple
$hType = 'Finance';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<!-- WALLET GLASSMORPHISM -->
<style>
/* Animated Background */
.wallet-glass-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 25%, #e0e7ff 50%, #eef2ff 75%, #f8fafc 100%);
}

.wallet-glass-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 20% 30%, rgba(79, 70, 229, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 80%, rgba(99, 102, 241, 0.08) 0%, transparent 50%);
    animation: walletFloat 20s ease-in-out infinite;
}

[data-theme="dark"] .wallet-glass-bg {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
}

[data-theme="dark"] .wallet-glass-bg::before {
    background:
        radial-gradient(ellipse at 20% 30%, rgba(79, 70, 229, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 45%);
}

@keyframes walletFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-1%, 1%) scale(1.02); }
}

/* Container */
.wallet-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 120px 24px 40px 24px;
    position: relative;
    z-index: 10;
}

/* Glass Cards */
.wallet-glass-card {
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

[data-theme="dark"] .wallet-glass-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.9) 0%,
        rgba(30, 41, 59, 0.75) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

/* Balance Card - Special */
.wallet-balance-card {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #8b5cf6 100%);
    border: none;
    color: white;
    text-align: center;
    padding: 40px 24px;
    position: relative;
    overflow: hidden;
}

.wallet-balance-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 30% 30%, rgba(255, 255, 255, 0.15) 0%, transparent 40%),
        radial-gradient(ellipse at 70% 70%, rgba(255, 255, 255, 0.1) 0%, transparent 40%);
    animation: balanceShine 8s ease-in-out infinite;
}

@keyframes balanceShine {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(2%, -2%); }
}

.wallet-balance-label {
    color: rgba(255, 255, 255, 0.8);
    text-transform: uppercase;
    letter-spacing: 2px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 12px;
    position: relative;
    z-index: 1;
}

.wallet-balance-amount {
    font-size: 4rem;
    font-weight: 800;
    line-height: 1;
    position: relative;
    z-index: 1;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.wallet-balance-unit {
    font-size: 1.5rem;
    font-weight: 400;
    opacity: 0.8;
    margin-left: 8px;
}

.wallet-insights-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 20px;
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    color: white;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.2s;
    position: relative;
    z-index: 1;
}

.wallet-insights-link:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

/* Grid Layout */
.wallet-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 24px;
}

/* Transfer Form */
.wallet-transfer-form {
    padding: 24px;
}

.wallet-form-title {
    margin: 0 0 20px 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .wallet-form-title {
    color: #f1f5f9;
}

.wallet-form-group {
    margin-bottom: 16px;
}

.wallet-form-label {
    display: block;
    font-weight: 600;
    font-size: 0.9rem;
    color: #374151;
    margin-bottom: 6px;
}

[data-theme="dark"] .wallet-form-label {
    color: #e2e8f0;
}

.wallet-form-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid rgba(79, 70, 229, 0.2);
    border-radius: 12px;
    font-size: 1rem;
    background: white;
    color: #1f2937;
    transition: all 0.2s;
    outline: none;
}

.wallet-form-input:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

[data-theme="dark"] .wallet-form-input {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

[data-theme="dark"] .wallet-form-input:focus {
    border-color: #6366f1;
}

/* User Search Autocomplete */
.wallet-user-search-wrapper {
    position: relative;
}

.wallet-user-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 2px solid rgba(79, 70, 229, 0.2);
    border-top: none;
    border-radius: 0 0 12px 12px;
    max-height: 280px;
    overflow-y: auto;
    z-index: 100;
    display: none;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.wallet-user-results.show {
    display: block;
}

[data-theme="dark"] .wallet-user-results {
    background: #1e293b;
    border-color: rgba(255, 255, 255, 0.1);
}

.wallet-user-result {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    cursor: pointer;
    transition: background 0.15s;
    gap: 12px;
}

.wallet-user-result:hover,
.wallet-user-result.selected {
    background: rgba(79, 70, 229, 0.08);
}

[data-theme="dark"] .wallet-user-result:hover,
[data-theme="dark"] .wallet-user-result.selected {
    background: rgba(99, 102, 241, 0.15);
}

.wallet-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
    overflow: hidden;
}

.wallet-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.wallet-user-info {
    flex: 1;
    min-width: 0;
}

.wallet-user-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .wallet-user-name {
    color: #f1f5f9;
}

.wallet-user-username {
    font-size: 0.85rem;
    color: #6b7280;
}

[data-theme="dark"] .wallet-user-username {
    color: #94a3b8;
}

.wallet-user-no-results {
    padding: 16px;
    text-align: center;
    color: #9ca3af;
    font-size: 0.9rem;
}

/* Selected user chip */
.wallet-selected-user {
    display: none;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: rgba(79, 70, 229, 0.1);
    border: 2px solid rgba(79, 70, 229, 0.3);
    border-radius: 12px;
    margin-bottom: 8px;
}

.wallet-selected-user.show {
    display: flex;
}

.wallet-selected-user .wallet-user-avatar {
    width: 32px;
    height: 32px;
    font-size: 14px;
}

.wallet-selected-user .wallet-user-info {
    flex: 1;
}

.wallet-selected-clear {
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
}

.wallet-selected-clear:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.wallet-submit-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    font-weight: 700;
    font-size: 1rem;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
}

.wallet-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
}

/* History Section */
.wallet-history {
    padding: 24px;
}

.wallet-history-title {
    margin: 0 0 20px 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .wallet-history-title {
    color: #f1f5f9;
}

/* Transaction Item */
.wallet-transaction {
    display: flex;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid rgba(229, 231, 235, 0.5);
    gap: 16px;
}

[data-theme="dark"] .wallet-transaction {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.wallet-transaction:last-child {
    border-bottom: none;
}

.wallet-tx-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.wallet-tx-icon.sent {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.wallet-tx-icon.received {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.wallet-tx-details {
    flex: 1;
    min-width: 0;
}

.wallet-tx-title {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
}

[data-theme="dark"] .wallet-tx-title {
    color: #f1f5f9;
}

.wallet-tx-desc {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 2px;
}

[data-theme="dark"] .wallet-tx-desc {
    color: #94a3b8;
}

.wallet-tx-date {
    font-size: 0.8rem;
    color: #9ca3af;
    margin-top: 4px;
}

.wallet-tx-amount {
    font-weight: 700;
    font-size: 1.1rem;
    text-align: right;
}

.wallet-tx-amount.sent {
    color: #ef4444;
}

.wallet-tx-amount.received {
    color: #10b981;
}

.wallet-tx-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.wallet-tx-rate {
    padding: 6px 12px;
    background: rgba(79, 70, 229, 0.1);
    color: #4f46e5;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s;
}

.wallet-tx-rate:hover {
    background: rgba(79, 70, 229, 0.2);
}

.wallet-tx-delete {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: transparent;
    border: none;
    color: #cbd5e1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.wallet-tx-delete:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

/* Empty State */
.wallet-empty {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.wallet-empty-icon {
    font-size: 3rem;
    opacity: 0.3;
    margin-bottom: 16px;
}

/* FAB */
.wallet-fab {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 100;
    display: flex;
    flex-direction: column-reverse;
    align-items: flex-end;
    gap: 12px;
}

.wallet-fab-main {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 20px rgba(79, 70, 229, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.wallet-fab-main:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 30px rgba(79, 70, 229, 0.5);
}

.wallet-fab-main.active {
    transform: rotate(45deg);
    background: linear-gradient(135deg, #ef4444 0%, #f97316 100%);
}

.wallet-fab-menu {
    display: none;
    flex-direction: column;
    gap: 10px;
    align-items: flex-end;
}

.wallet-fab-menu.show {
    display: flex;
    animation: walletFabSlide 0.2s ease;
}

@keyframes walletFabSlide {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.wallet-fab-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    text-decoration: none;
    color: #1f2937;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    white-space: nowrap;
}

.wallet-fab-item:hover {
    transform: translateX(-4px);
}

.wallet-fab-item i {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: white;
}

.wallet-fab-item .icon-send { background: linear-gradient(135deg, #4f46e5, #7c3aed); }
.wallet-fab-item .icon-history { background: linear-gradient(135deg, #10b981, #059669); }
.wallet-fab-item .icon-qr { background: linear-gradient(135deg, #f59e0b, #d97706); }

[data-theme="dark"] .wallet-fab-item {
    background: rgba(30, 41, 59, 0.95);
    color: #f1f5f9;
}

/* Mobile Responsiveness */
@media (max-width: 900px) {
    .wallet-container {
        padding: 100px 16px 100px 16px;
    }

    .wallet-grid {
        grid-template-columns: 1fr;
    }

    .wallet-balance-amount {
        font-size: 3rem;
    }

    .wallet-fab {
        bottom: 80px; /* Above sticky bottom nav bar */
        right: 16px;
    }

    .wallet-fab-main {
        width: 52px;
        height: 52px;
    }

    .wallet-transaction {
        flex-wrap: wrap;
    }

    .wallet-tx-actions {
        width: 100%;
        justify-content: flex-end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid rgba(229, 231, 235, 0.3);
    }
}

@media (max-width: 480px) {
    .wallet-balance-amount {
        font-size: 2.5rem;
    }

    .wallet-form-input {
        font-size: 16px; /* Prevent iOS zoom */
    }
}
</style>

<div class="wallet-glass-bg"></div>

<div class="wallet-container">
    <div class="wallet-grid">

        <!-- Left Column: Balance & Transfer -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- Balance Card -->
            <div class="wallet-glass-card wallet-balance-card">
                <div class="wallet-balance-label">Current Balance</div>
                <div class="wallet-balance-amount">
                    <?= $user['balance'] ?><span class="wallet-balance-unit">HRS</span>
                </div>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet/insights" class="wallet-insights-link">
                    <i class="fa-solid fa-chart-line"></i> View My Insights
                </a>
            </div>

            <!-- Transfer Form -->
            <div class="wallet-glass-card">
                <div class="wallet-transfer-form">
                    <h3 class="wallet-form-title">
                        <i class="fa-solid fa-paper-plane" style="color: #4f46e5;"></i>
                        Send Credits
                    </h3>
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet/transfer" method="POST" onsubmit="return validateWalletForm(this);" id="walletTransferForm">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="username" id="walletRecipientUsername" value="">
                        <input type="hidden" name="recipient_id" id="walletRecipientId" value="">

                        <div class="wallet-form-group">
                            <label class="wallet-form-label">Recipient</label>

                            <!-- Selected User Chip (shown when user is selected) -->
                            <div class="wallet-selected-user" id="walletSelectedUser">
                                <div class="wallet-user-avatar" id="walletSelectedAvatar">
                                    <span id="walletSelectedInitial">?</span>
                                </div>
                                <div class="wallet-user-info">
                                    <div class="wallet-user-name" id="walletSelectedName">-</div>
                                    <div class="wallet-user-username" id="walletSelectedUsername">-</div>
                                </div>
                                <button type="button" class="wallet-selected-clear" onclick="clearWalletSelection()" title="Clear">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>

                            <!-- Search Input -->
                            <div class="wallet-user-search-wrapper" id="walletSearchWrapper">
                                <input type="text" id="walletUserSearch" placeholder="Search by name or username..."
                                    class="wallet-form-input" autocomplete="off">
                                <div class="wallet-user-results" id="walletUserResults"></div>
                            </div>
                        </div>
                        <div class="wallet-form-group">
                            <label class="wallet-form-label">Amount (Hours)</label>
                            <input type="number" name="amount" min="1" required placeholder="1" class="wallet-form-input">
                        </div>
                        <div class="wallet-form-group">
                            <label class="wallet-form-label">Description</label>
                            <input type="text" name="description" required placeholder="e.g. Gardening help" class="wallet-form-input">
                        </div>
                        <button type="submit" class="wallet-submit-btn">
                            <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i>
                            Send Credits
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <!-- Right Column: Transaction History -->
        <div class="wallet-glass-card">
            <div class="wallet-history">
                <h3 class="wallet-history-title">
                    <i class="fa-solid fa-clock-rotate-left" style="color: #10b981;"></i>
                    Transaction History
                </h3>

                <?php if (empty($transactions)): ?>
                    <div class="wallet-empty">
                        <div class="wallet-empty-icon">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <p>No transactions yet.</p>
                        <p style="font-size: 0.9rem; margin-top: 8px;">Send or receive credits to see your history here.</p>
                    </div>
                <?php else: ?>
                    <div>
                        <?php foreach ($transactions as $t): ?>
                            <?php
                            $isSender = ($t['sender_id'] == $_SESSION['user_id']);
                            $typeClass = $isSender ? 'sent' : 'received';
                            $amountSign = $isSender ? '-' : '+';
                            $icon = $isSender ? 'fa-arrow-up' : 'fa-arrow-down';
                            ?>
                            <div class="wallet-transaction">
                                <div class="wallet-tx-icon <?= $typeClass ?>">
                                    <i class="fa-solid <?= $icon ?>"></i>
                                </div>
                                <div class="wallet-tx-details">
                                    <div class="wallet-tx-title">
                                        <?php if ($isSender): ?>
                                            Sent to <strong><?= htmlspecialchars($t['receiver_name']) ?></strong>
                                        <?php else: ?>
                                            Received from <strong><?= htmlspecialchars($t['sender_name']) ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <div class="wallet-tx-desc"><?= htmlspecialchars($t['description'] ?? 'Transfer') ?></div>
                                    <div class="wallet-tx-date"><?= date('M d, Y · g:i A', strtotime($t['created_at'])) ?></div>
                                </div>
                                <div class="wallet-tx-amount <?= $typeClass ?>">
                                    <?= $amountSign ?><?= $t['amount'] ?> hrs
                                </div>
                                <div class="wallet-tx-actions">
                                    <?php if ($isSender): ?>
                                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/reviews/create/<?= $t['id'] ?>?receiver=<?= $t['receiver_id'] ?>" class="wallet-tx-rate">
                                            <i class="fa-solid fa-star" style="margin-right: 4px;"></i> Rate
                                        </a>
                                    <?php endif; ?>
                                    <button onclick="deleteTransaction(<?= $t['id'] ?>)" class="wallet-tx-delete" title="Delete">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Floating Action Button -->
<div class="wallet-fab">
    <button class="wallet-fab-main" onclick="toggleWalletFab()" aria-label="Quick Actions">
        <i class="fa-solid fa-plus"></i>
    </button>
    <div class="wallet-fab-menu" id="walletFabMenu">
        <a href="#" onclick="document.querySelector('.wallet-form-input[name=email]').focus(); toggleWalletFab(); return false;" class="wallet-fab-item">
            <i class="fa-solid fa-paper-plane icon-send"></i>
            <span>Send Credits</span>
        </a>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet/insights" class="wallet-fab-item">
            <i class="fa-solid fa-chart-line icon-history"></i>
            <span>My Insights</span>
        </a>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/members" class="wallet-fab-item">
            <i class="fa-solid fa-users icon-qr"></i>
            <span>Find Members</span>
        </a>
    </div>
</div>

<script>
function toggleWalletFab() {
    const btn = document.querySelector('.wallet-fab-main');
    const menu = document.getElementById('walletFabMenu');
    btn.classList.toggle('active');
    menu.classList.toggle('show');
}

// Close FAB when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.wallet-fab')) {
        document.querySelector('.wallet-fab-main')?.classList.remove('active');
        document.getElementById('walletFabMenu')?.classList.remove('show');
    }
});

function deleteTransaction(id) {
    if (!confirm('Delete this transaction record?')) return;

    fetch('<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/wallet/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: id
            })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                location.reload();
            } else {
                alert('Error: ' + (res.error || 'Failed'));
            }
        });
}

// ============================================
// WALLET USER SEARCH AUTOCOMPLETE
// ============================================
let walletSearchTimeout = null;
let walletSelectedIndex = -1;

const walletSearchInput = document.getElementById('walletUserSearch');
const walletResultsContainer = document.getElementById('walletUserResults');
const walletSearchWrapper = document.getElementById('walletSearchWrapper');
const walletSelectedUser = document.getElementById('walletSelectedUser');
const walletUsernameInput = document.getElementById('walletRecipientUsername');

if (walletSearchInput) {
    // Search on input
    walletSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(walletSearchTimeout);

        if (query.length < 1) {
            walletResultsContainer.classList.remove('show');
            walletResultsContainer.innerHTML = '';
            return;
        }

        walletSearchTimeout = setTimeout(() => {
            searchWalletUsers(query);
        }, 200);
    });

    // Keyboard navigation
    walletSearchInput.addEventListener('keydown', function(e) {
        const results = walletResultsContainer.querySelectorAll('.wallet-user-result');
        if (!results.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            walletSelectedIndex = Math.min(walletSelectedIndex + 1, results.length - 1);
            updateWalletResultsSelection(results);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            walletSelectedIndex = Math.max(walletSelectedIndex - 1, 0);
            updateWalletResultsSelection(results);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (walletSelectedIndex >= 0 && results[walletSelectedIndex]) {
                results[walletSelectedIndex].click();
            }
        } else if (e.key === 'Escape') {
            walletResultsContainer.classList.remove('show');
        }
    });

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.wallet-user-search-wrapper')) {
            walletResultsContainer.classList.remove('show');
        }
    });
}

function updateWalletResultsSelection(results) {
    results.forEach((r, i) => {
        r.classList.toggle('selected', i === walletSelectedIndex);
    });
    if (results[walletSelectedIndex]) {
        results[walletSelectedIndex].scrollIntoView({ block: 'nearest' });
    }
}

function searchWalletUsers(query) {
    fetch('<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/wallet/user-search', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query: query })
    })
    .then(res => res.json())
    .then(data => {
        walletSelectedIndex = -1;

        if (data.status === 'success' && data.users && data.users.length > 0) {
            walletResultsContainer.innerHTML = data.users.map(user => {
                const initial = (user.display_name || '?')[0].toUpperCase();
                const avatarHtml = user.avatar_url
                    ? `<img src="${user.avatar_url}" alt="" loading="lazy">`
                    : `<span>${initial}</span>`;

                return `
                    <div class="wallet-user-result" onclick="selectWalletUser('${escapeHtml(user.username || '')}', '${escapeHtml(user.display_name)}', '${escapeHtml(user.avatar_url || '')}', '${user.id}')">
                        <div class="wallet-user-avatar">${avatarHtml}</div>
                        <div class="wallet-user-info">
                            <div class="wallet-user-name">${escapeHtml(user.display_name)}</div>
                            <div class="wallet-user-username">${user.username ? '@' + escapeHtml(user.username) : '<em>No username set</em>'}</div>
                        </div>
                    </div>
                `;
            }).join('');
            walletResultsContainer.classList.add('show');
        } else {
            walletResultsContainer.innerHTML = '<div class="wallet-user-no-results">No users found</div>';
            walletResultsContainer.classList.add('show');
        }
    })
    .catch(err => {
        console.error('Wallet user search error:', err);
        walletResultsContainer.innerHTML = '<div class="wallet-user-no-results">Search error</div>';
        walletResultsContainer.classList.add('show');
    });
}

function selectWalletUser(username, displayName, avatarUrl, userId) {
    // Store username and user ID in hidden inputs
    walletUsernameInput.value = username;
    document.getElementById('walletRecipientId').value = userId || '';

    // Update selected user display
    const initial = (displayName || '?')[0].toUpperCase();
    document.getElementById('walletSelectedAvatar').innerHTML = avatarUrl
        ? `<img src="${avatarUrl}" alt="" loading="lazy">`
        : `<span>${initial}</span>`;
    document.getElementById('walletSelectedName').textContent = displayName;
    document.getElementById('walletSelectedUsername').textContent = username ? '@' + username : 'No username';

    // Show selected user, hide search
    walletSelectedUser.classList.add('show');
    walletSearchWrapper.style.display = 'none';
    walletResultsContainer.classList.remove('show');
}

function clearWalletSelection() {
    walletUsernameInput.value = '';
    walletSelectedUser.classList.remove('show');
    walletSearchWrapper.style.display = 'block';
    walletSearchInput.value = '';
    walletSearchInput.focus();
}

function validateWalletForm(form) {
    const username = walletUsernameInput.value.trim();
    const recipientId = document.getElementById('walletRecipientId').value.trim();

    if (!username && !recipientId) {
        alert('Please select a recipient from the search results.');
        walletSearchInput.focus();
        return false;
    }

    // Disable button to prevent double submit
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

    return true;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Pre-fill recipient from profile page (when navigating via ?to=userId)
<?php if (!empty($prefillRecipient)): ?>
document.addEventListener('DOMContentLoaded', function() {
    selectWalletUser(
        <?= json_encode($prefillRecipient['username'] ?? '') ?>,
        <?= json_encode($prefillRecipient['display_name'] ?? '') ?>,
        <?= json_encode($prefillRecipient['avatar_url'] ?? '') ?>,
        <?= json_encode($prefillRecipient['id'] ?? '') ?>
    );
});
<?php endif; ?>
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
