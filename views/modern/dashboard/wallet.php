<?php
/**
 * Modern Dashboard - Wallet Page
 * Dedicated route version (replaces tab-based approach)
 */

$hero_title = "Wallet";
$hero_subtitle = "Manage your time credits";
$hero_gradient = 'htb-hero-gradient-wallet';
$hero_type = 'Wallet';

require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
?>

<div class="dashboard-glass-bg"></div>

<div class="dashboard-container">

    <!-- Glass Navigation -->
    <div class="dash-tabs-glass">
        <a href="<?= $basePath ?>/dashboard" class="dash-tab-glass">
            <i class="fa-solid fa-house"></i> Overview
        </a>
        <a href="<?= $basePath ?>/dashboard/notifications" class="dash-tab-glass">
            <i class="fa-solid fa-bell"></i> Notifications
            <?php
            $uCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
            if ($uCount > 0): ?>
                <span class="dash-notif-badge"><?= $uCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $basePath ?>/dashboard/hubs" class="dash-tab-glass">
            <i class="fa-solid fa-users"></i> My Hubs
        </a>
        <a href="<?= $basePath ?>/dashboard/listings" class="dash-tab-glass">
            <i class="fa-solid fa-list"></i> My Listings
        </a>
        <a href="<?= $basePath ?>/dashboard/wallet" class="dash-tab-glass active">
            <i class="fa-solid fa-wallet"></i> Wallet
        </a>
        <a href="<?= $basePath ?>/dashboard/events" class="dash-tab-glass">
            <i class="fa-solid fa-calendar"></i> Events
        </a>
    </div>

    <div class="dash-wallet-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Left: Balance & Actions -->
        <div style="display: flex; flex-direction: column; gap: 30px;">
            <!-- Balance Card -->
            <div class="htb-card" style="background: linear-gradient(135deg, #4f46e5, #818cf8); color: white;">
                <div class="htb-card-body">
                    <div style="font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Current Balance</div>
                    <div class="dash-wallet-balance-amount" style="font-size: 3.5rem; font-weight: 800; line-height: 1; margin: 10px 0;">
                        <?= number_format($user['balance']) ?> <span style="font-size: 1.5rem; font-weight: 400; opacity: 0.8;">Credits</span>
                    </div>
                    <div style="font-size: 0.85rem; opacity: 0.8;">
                        1 Credit = 1 Hour of Service
                    </div>
                </div>
            </div>

            <!-- Transfer Widget -->
            <div class="htb-card">
                <div class="htb-card-header" style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #334155;">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 8px; color: #4f46e5;"></i> Send Credits
                </div>
                <div class="htb-card-body">
                    <form id="transfer-form" class="dash-transfer-form" action="<?= $basePath ?>/wallet/transfer" method="POST" onsubmit="return validateDashTransfer(this);">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="username" id="dashRecipientUsername" value="">
                        <input type="hidden" name="recipient_id" id="dashRecipientId" value="">

                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 5px;">Recipient</label>

                            <!-- Selected User Chip -->
                            <div id="dashSelectedUser" style="display: none; align-items: center; gap: 10px; padding: 10px 14px; background: rgba(79, 70, 229, 0.1); border: 2px solid rgba(79, 70, 229, 0.3); border-radius: 8px; margin-bottom: 8px;">
                                <div id="dashSelectedAvatar" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #4f46e5, #7c3aed); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px; flex-shrink: 0; overflow: hidden;">
                                    <span id="dashSelectedInitial">?</span>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div id="dashSelectedName" style="font-weight: 600; color: #1f2937; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">-</div>
                                    <div id="dashSelectedUsername" style="font-size: 0.85rem; color: #6b7280;">-</div>
                                </div>
                                <button type="button" onclick="clearDashSelection()" style="width: 28px; height: 28px; border-radius: 50%; background: transparent; border: none; color: #6b7280; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s;" onmouseover="this.style.background='rgba(239, 68, 68, 0.1)'; this.style.color='#ef4444';" onmouseout="this.style.background='transparent'; this.style.color='#6b7280';" title="Clear">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>

                            <!-- Search Input -->
                            <div id="dashSearchWrapper" style="position: relative;">
                                <input type="text" id="dashUserSearch" placeholder="Search by name or username..." autocomplete="off" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 16px;">
                                <div id="dashUserResults" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 2px solid rgba(79, 70, 229, 0.2); border-top: none; border-radius: 0 0 8px 8px; max-height: 280px; overflow-y: auto; z-index: 100; display: none; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);"></div>
                            </div>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 5px;">Amount</label>
                            <input type="number" name="amount" min="1" required placeholder="0" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 16px;">
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 5px;">Description (Optional)</label>
                            <textarea name="description" rows="2" placeholder="What is this for?" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 16px;"></textarea>
                        </div>
                        <button type="submit" id="transfer-btn" class="htb-btn htb-btn-primary" style="width: 100%; justify-content: center; padding: 14px 24px;"><i class="fa-solid fa-paper-plane"></i> Send Credits</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Transaction History -->
        <div class="htb-card" style="height: fit-content;">
            <div class="htb-card-header" style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #334155;">
                <i class="fa-solid fa-clock-rotate-left" style="margin-right: 8px; color: #64748b;"></i> Recent Transactions
            </div>
            <div class="dash-transactions-table">
                <?php if (empty($wallet_transactions)): ?>
                    <div style="padding: 40px; text-align: center; color: #94a3b8;">
                        <div style="font-size: 2.5rem; margin-bottom: 10px; opacity: 0.3;"><i class="fa-solid fa-receipt"></i></div>
                        <p>No transactions found.</p>
                    </div>
                <?php else: ?>
                    <table class="htb-table" style="width: 100%">
                        <thead>
                            <tr style="background: #f8fafc; font-size: 0.8rem; text-transform: uppercase;">
                                <th>Date</th>
                                <th>Description</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($wallet_transactions, 0, 10) as $t):
                                $isIncoming = $t['receiver_id'] == $_SESSION['user_id'];
                            ?>
                                <tr>
                                    <td style="font-size: 0.85rem; color: #64748b;">
                                        <?= date('M j, Y', strtotime($t['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; font-size: 0.9rem; color: #334155;">
                                            <?= $isIncoming ? 'Received from ' . htmlspecialchars($t['sender_name']) : 'Sent to ' . htmlspecialchars($t['receiver_name']) ?>
                                        </div>
                                        <?php if (!empty($t['description'])): ?>
                                            <div style="font-size: 0.8rem; color: #94a3b8; font-style: italic;">
                                                "<?= htmlspecialchars($t['description']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: <?= $isIncoming ? '#10b981' : '#ef4444' ?>;">
                                        <?= $isIncoming ? '+' : '-' ?><?= number_format($t['amount']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/wallet-user-search.js"></script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
