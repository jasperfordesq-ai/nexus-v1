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

    <div class="dash-wallet-grid mte-wallet--grid">
        <!-- Left: Balance & Actions -->
        <div class="mte-wallet--col">
            <!-- Balance Card -->
            <div class="htb-card mte-wallet--balance-card">
                <div class="htb-card-body">
                    <div class="mte-wallet--balance-label">Current Balance</div>
                    <div class="dash-wallet-balance-amount mte-wallet--balance-amount">
                        <?= number_format($user['balance']) ?> <span class="mte-wallet--balance-unit">Credits</span>
                    </div>
                    <div class="mte-wallet--balance-note">
                        1 Credit = 1 Hour of Service
                    </div>
                </div>
            </div>

            <!-- Transfer Widget -->
            <div class="htb-card">
                <div class="htb-card-header mte-wallet--card-header">
                    <i class="fa-solid fa-paper-plane mte-wallet--card-header-icon"></i> Send Credits
                </div>
                <div class="htb-card-body">
                    <form id="transfer-form" class="dash-transfer-form" action="<?= $basePath ?>/wallet/transfer" method="POST" onsubmit="return validateDashTransfer(this);">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="username" id="dashRecipientUsername" value="">
                        <input type="hidden" name="recipient_id" id="dashRecipientId" value="">

                        <div class="mte-wallet--form-group">
                            <label class="mte-wallet--label">Recipient</label>

                            <!-- Selected User Chip -->
                            <div id="dashSelectedUser" class="mte-wallet--selected-user hidden">
                                <div id="dashSelectedAvatar" class="mte-wallet--selected-avatar">
                                    <span id="dashSelectedInitial">?</span>
                                </div>
                                <div class="mte-wallet--selected-info">
                                    <div id="dashSelectedName" class="mte-wallet--selected-name">-</div>
                                    <div id="dashSelectedUsername" class="mte-wallet--selected-username">-</div>
                                </div>
                                <button type="button" onclick="clearDashSelection()" class="mte-wallet--clear-btn" title="Clear">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>

                            <!-- Search Input -->
                            <div id="dashSearchWrapper" class="mte-wallet--search-wrapper">
                                <input type="text" id="dashUserSearch" placeholder="Search by name or username..." autocomplete="off" class="mte-wallet--input">
                                <div id="dashUserResults" class="mte-wallet--search-results hidden"></div>
                            </div>
                        </div>
                        <div class="mte-wallet--form-group">
                            <label class="mte-wallet--label">Amount</label>
                            <input type="number" name="amount" min="1" required placeholder="0" class="mte-wallet--input">
                        </div>
                        <div class="mte-wallet--form-group-lg">
                            <label class="mte-wallet--label">Description (Optional)</label>
                            <textarea name="description" rows="2" placeholder="What is this for?" class="mte-wallet--input"></textarea>
                        </div>
                        <button type="submit" id="transfer-btn" class="htb-btn htb-btn-primary mte-wallet--submit-btn"><i class="fa-solid fa-paper-plane"></i> Send Credits</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Transaction History -->
        <div class="htb-card mte-wallet--tx-card">
            <div class="htb-card-header mte-wallet--tx-header">
                <i class="fa-solid fa-clock-rotate-left mte-wallet--card-header-icon-gray"></i> Recent Transactions
            </div>
            <div class="dash-transactions-table">
                <?php if (empty($wallet_transactions)): ?>
                    <div class="mte-wallet--empty">
                        <div class="mte-wallet--empty-icon"><i class="fa-solid fa-receipt"></i></div>
                        <p>No transactions found.</p>
                    </div>
                <?php else: ?>
                    <table class="htb-table mte-wallet--table">
                        <thead>
                            <tr class="mte-wallet--table-head">
                                <th>Date</th>
                                <th>Description</th>
                                <th class="mte-wallet--table-amount">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($wallet_transactions, 0, 10) as $t):
                                $isIncoming = $t['receiver_id'] == $_SESSION['user_id'];
                            ?>
                                <tr>
                                    <td class="mte-wallet--table-date">
                                        <?= date('M j, Y', strtotime($t['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="mte-wallet--table-desc">
                                            <?= $isIncoming ? 'Received from ' . htmlspecialchars($t['sender_name']) : 'Sent to ' . htmlspecialchars($t['receiver_name']) ?>
                                        </div>
                                        <?php if (!empty($t['description'])): ?>
                                            <div class="mte-wallet--table-note">
                                                "<?= htmlspecialchars($t['description']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mte-wallet--table-amount <?= $isIncoming ? 'mte-wallet--amount-positive' : 'mte-wallet--amount-negative' ?>">
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
