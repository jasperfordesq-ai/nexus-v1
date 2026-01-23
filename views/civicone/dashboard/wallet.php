<?php
/**
 * CivicOne Dashboard - Wallet Page
 * WCAG 2.1 AA Compliant
 * Template: Account Area Template (Template G)
 * Pattern: GOV.UK Summary list + Table for transactions
 */

$hTitle = "Wallet";
$hSubtitle = "Manage your time credits";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="civic-dashboard civicone-account-area">

    <!-- Account Area Secondary Navigation -->
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

    <!-- WALLET CONTENT -->
    <div class="civic-wallet-grid">
        <!-- Left: Balance & Transfer -->
        <div class="civic-wallet-main">
            <!-- Balance Summary (GOV.UK Summary List - WCAG 2.1 AA) -->
            <h2 class="civicone-heading-l">Balance</h2>
            <dl class="govuk-summary-list">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Current balance</dt>
                    <dd class="govuk-summary-list__value">
                        <strong class="civicone-wallet-balance"><?= number_format($user['balance']) ?> Credits</strong>
                        <div class="civic-wallet-balance-note">1 Credit = 1 Hour of Service</div>
                    </dd>
                    <dd class="govuk-summary-list__actions">
                        <a class="govuk-link" href="#transfer-form">
                            Send credits<span class="civicone-visually-hidden"> to another user</span>
                        </a>
                    </dd>
                </div>
            </dl>

            <!-- Transfer Widget -->
            <section class="civic-dash-card" aria-labelledby="send-credits-heading">
                <div class="civic-dash-card-header">
                    <h2 id="send-credits-heading" class="civic-dash-card-title">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        Send Credits
                    </h2>
                </div>
                <form id="transfer-form" action="<?= $basePath ?>/wallet/transfer" method="POST" onsubmit="return validateDashTransfer(this);" class="civic-transfer-form">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="username" id="dashRecipientUsername" value="">
                    <input type="hidden" name="recipient_id" id="dashRecipientId" value="">

                    <div class="civic-form-group">
                        <label for="dashUserSearch" class="civic-label">Recipient</label>
                        <div id="dashUserSearch-hint" class="civic-hint">
                            Search by name or username
                        </div>

                        <!-- Selected User Chip -->
                        <div id="dashSelectedUser" class="civic-selected-user" hidden role="status" aria-live="polite">
                            <div id="dashSelectedAvatar" class="civic-selected-avatar" aria-hidden="true">
                                <span id="dashSelectedInitial">?</span>
                            </div>
                            <div class="civic-selected-info">
                                <div id="dashSelectedName" class="civic-selected-name">-</div>
                                <div id="dashSelectedUsername" class="civic-selected-username">-</div>
                            </div>
                            <button type="button" onclick="clearDashSelection()" class="civic-selected-clear" aria-label="Clear recipient selection and search again">
                                <i class="fa-solid fa-times" aria-hidden="true"></i>
                            </button>
                        </div>

                        <!-- Search Input with ARIA Combobox Pattern -->
                        <div id="dashSearchWrapper" class="civic-search-wrapper">
                            <input type="text"
                                   id="dashUserSearch"
                                   class="civic-input"
                                   autocomplete="off"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-expanded="false"
                                   aria-controls="dashUserResults"
                                   aria-describedby="dashUserSearch-hint"
                                   aria-haspopup="listbox">
                            <div id="dashUserResults"
                                 class="civic-search-results"
                                 role="listbox"
                                 aria-label="User search results"
                                 hidden></div>
                        </div>
                    </div>

                    <div class="civic-form-group">
                        <label for="transfer-amount" class="civic-label">Amount (credits)</label>
                        <div id="transfer-amount-hint" class="civic-hint">
                            Minimum transfer is 1 credit (1 hour of service)
                        </div>
                        <input type="number"
                               id="transfer-amount"
                               name="amount"
                               class="civic-input civic-input--width-5"
                               min="1"
                               required
                               aria-describedby="transfer-amount-hint">
                    </div>

                    <div class="civic-form-group">
                        <label for="transfer-desc" class="civic-label">Description (optional)</label>
                        <div id="transfer-desc-hint" class="civic-hint">
                            What is this transfer for?
                        </div>
                        <textarea id="transfer-desc"
                                  name="description"
                                  class="civic-textarea"
                                  rows="3"
                                  aria-describedby="transfer-desc-hint"></textarea>
                    </div>

                    <button type="submit" id="transfer-btn" class="civic-button civic-button--full-width">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send Credits
                    </button>
                </form>
            </section>
        </div>

        <!-- Right: Transaction History -->
        <section class="civic-dash-card" aria-labelledby="transactions-heading">
            <div class="civic-dash-card-header">
                <h2 id="transactions-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    Recent Transactions
                </h2>
            </div>
            <?php if (empty($wallet_transactions)): ?>
                <div class="civic-empty-state">
                    <div class="civic-empty-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></div>
                    <p class="civic-empty-text">No transactions found.</p>
                </div>
            <?php else: ?>
                <div class="civic-transactions-table" role="table" aria-label="Transaction history">
                    <div class="civic-transactions-header" role="row">
                        <div role="columnheader">Date</div>
                        <div role="columnheader">Description</div>
                        <div role="columnheader" class="civic-col-right">Amount</div>
                    </div>
                    <?php foreach (array_slice($wallet_transactions, 0, 10) as $t):
                        $isIncoming = $t['receiver_id'] == $_SESSION['user_id'];
                    ?>
                        <div class="civic-transaction-row" role="row">
                            <div role="cell" class="civic-transaction-date">
                                <?= date('M j, Y', strtotime($t['created_at'])) ?>
                            </div>
                            <div role="cell" class="civic-transaction-desc">
                                <div class="civic-transaction-title">
                                    <?= $isIncoming ? 'Received from ' . htmlspecialchars($t['sender_name']) : 'Sent to ' . htmlspecialchars($t['receiver_name']) ?>
                                </div>
                                <?php if (!empty($t['description'])): ?>
                                    <div class="civic-transaction-note">"<?= htmlspecialchars($t['description']) ?>"</div>
                                <?php endif; ?>
                            </div>
                            <div role="cell" class="civic-transaction-amount <?= $isIncoming ? 'incoming' : 'outgoing' ?>">
                                <?= $isIncoming ? '+' : '-' ?><?= number_format($t['amount']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

</div>

<script src="/assets/js/civicone-dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initCivicOneDashboard === 'function') {
        initCivicOneDashboard('<?= $basePath ?>');
    }
});
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
