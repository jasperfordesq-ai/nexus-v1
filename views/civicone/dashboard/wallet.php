<?php
/**
 * CivicOne Dashboard - Wallet Page
 * WCAG 2.1 AA Compliant - GOV.UK Design System Components
 * Template: Account Area Template (Template G)
 * Pattern: GOV.UK Summary list + GOV.UK Table for transactions
 *
 * Updated: 2026-01-31 - Migrated to GOV.UK form components
 */

$hTitle = "Wallet";
$hSubtitle = "Manage your time credits";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';

// Load GOV.UK table component
require_once dirname(__DIR__) . '/components/govuk/table.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="civic-dashboard civicone-account-area">

    <!-- Account Area Secondary Navigation -->
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

    <!-- WALLET CONTENT -->
    <div class="govuk-grid-row">
        <!-- Left: Balance & Transfer -->
        <div class="govuk-grid-column-two-thirds">
            <!-- Balance Summary (GOV.UK Summary List - WCAG 2.1 AA) -->
            <h2 class="govuk-heading-l">Balance</h2>
            <dl class="govuk-summary-list">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Current balance</dt>
                    <dd class="govuk-summary-list__value">
                        <strong class="govuk-!-font-size-48"><?= number_format($user['balance']) ?> Credits</strong>
                        <p class="govuk-body-s govuk-!-margin-top-1">1 Credit = 1 Hour of Service</p>
                    </dd>
                    <dd class="govuk-summary-list__actions">
                        <a class="govuk-link" href="#transfer-form">
                            Send credits<span class="govuk-visually-hidden"> to another user</span>
                        </a>
                    </dd>
                </div>
            </dl>

            <!-- Transfer Widget -->
            <section aria-labelledby="send-credits-heading" class="govuk-!-margin-top-6">
                <h2 id="send-credits-heading" class="govuk-heading-m">
                    <span aria-hidden="true"><i class="fa-solid fa-paper-plane"></i></span>
                    Send Credits
                </h2>

                <form id="transfer-form" action="<?= $basePath ?>/wallet/transfer" method="POST" onsubmit="return validateDashTransfer(this);">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="username" id="dashRecipientUsername" value="">
                    <input type="hidden" name="recipient_id" id="dashRecipientId" value="">

                    <!-- Recipient Search (Custom component with ARIA Combobox) -->
                    <div class="govuk-form-group">
                        <label for="dashUserSearch" class="govuk-label">Recipient</label>
                        <div id="dashUserSearch-hint" class="govuk-hint">
                            Search by name or username
                        </div>

                        <!-- Selected User Chip -->
                        <div id="dashSelectedUser" class="civicone-selected-user" hidden role="status" aria-live="polite">
                            <div id="dashSelectedAvatar" class="civicone-selected-avatar" aria-hidden="true">
                                <span id="dashSelectedInitial">?</span>
                            </div>
                            <div class="civicone-selected-info">
                                <div id="dashSelectedName" class="civicone-selected-name">-</div>
                                <div id="dashSelectedUsername" class="civicone-selected-username">-</div>
                            </div>
                            <button type="button" onclick="clearDashSelection()" class="govuk-button govuk-button--secondary civicone-selected-clear" aria-label="Clear recipient selection and search again">
                                <i class="fa-solid fa-times" aria-hidden="true"></i>
                                <span class="govuk-visually-hidden">Clear selection</span>
                            </button>
                        </div>

                        <!-- Search Input with ARIA Combobox Pattern -->
                        <div id="dashSearchWrapper" class="civicone-search-wrapper">
                            <input type="text"
                                   id="dashUserSearch"
                                   class="govuk-input"
                                   autocomplete="off"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-expanded="false"
                                   aria-controls="dashUserResults"
                                   aria-describedby="dashUserSearch-hint"
                                   aria-haspopup="listbox">
                            <div id="dashUserResults"
                                 class="civicone-search-results"
                                 role="listbox"
                                 aria-label="User search results"
                                 hidden></div>
                        </div>
                    </div>

                    <!-- Amount Input (GOV.UK Form Input) -->
                    <div class="govuk-form-group">
                        <label for="transfer-amount" class="govuk-label">Amount (credits)</label>
                        <div id="transfer-amount-hint" class="govuk-hint">
                            Minimum transfer is 1 credit (1 hour of service)
                        </div>
                        <input type="number"
                               id="transfer-amount"
                               name="amount"
                               class="govuk-input govuk-input--width-5"
                               min="1"
                               required
                               aria-describedby="transfer-amount-hint">
                    </div>

                    <!-- Description Textarea (GOV.UK Textarea) -->
                    <div class="govuk-form-group">
                        <label for="transfer-desc" class="govuk-label">Description (optional)</label>
                        <div id="transfer-desc-hint" class="govuk-hint">
                            What is this transfer for?
                        </div>
                        <textarea id="transfer-desc"
                                  name="description"
                                  class="govuk-textarea"
                                  rows="3"
                                  aria-describedby="transfer-desc-hint"></textarea>
                    </div>

                    <!-- Submit Button (GOV.UK Button) -->
                    <button type="submit" id="transfer-btn" class="govuk-button" data-module="govuk-button">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send Credits
                    </button>
                </form>
            </section>
        </div>

        <!-- Right: Transaction History -->
        <div class="govuk-grid-column-one-third">
            <section aria-labelledby="transactions-heading">
                <h2 id="transactions-heading" class="govuk-heading-m">
                    <span aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    Recent Transactions
                </h2>

                <?php if (empty($wallet_transactions)): ?>
                    <div class="govuk-inset-text">
                        <p class="govuk-body">No transactions found.</p>
                        <p class="govuk-body-s">Your transaction history will appear here once you send or receive credits.</p>
                    </div>
                <?php else: ?>
                    <?php
                    // Build table rows for GOV.UK table component
                    $tableRows = [];
                    foreach (array_slice($wallet_transactions, 0, 10) as $t) {
                        $isIncoming = $t['receiver_id'] == $_SESSION['user_id'];
                        $amountClass = $isIncoming ? 'civicone-amount-incoming' : 'civicone-amount-outgoing';
                        $amountPrefix = $isIncoming ? '+' : '-';
                        $description = $isIncoming
                            ? 'Received from ' . htmlspecialchars($t['sender_name'])
                            : 'Sent to ' . htmlspecialchars($t['receiver_name']);

                        if (!empty($t['description'])) {
                            $description .= '<br><span class="govuk-body-s govuk-!-margin-top-1">"' . htmlspecialchars($t['description']) . '"</span>';
                        }

                        $tableRows[] = [
                            ['text' => date('M j', strtotime($t['created_at']))],
                            ['html' => $description],
                            ['html' => '<span class="' . $amountClass . '">' . $amountPrefix . number_format($t['amount']) . '</span>', 'format' => 'numeric']
                        ];
                    }

                    echo civicone_govuk_table([
                        'caption' => 'Transaction history',
                        'captionSize' => 's',
                        'head' => [
                            ['text' => 'Date'],
                            ['text' => 'Description'],
                            ['text' => 'Amount', 'format' => 'numeric']
                        ],
                        'rows' => $tableRows,
                        'class' => 'civicone-transactions-table'
                    ]);
                    ?>

                    <p class="govuk-body-s govuk-!-margin-top-3">
                        <a href="<?= $basePath ?>/wallet/history" class="govuk-link">View all transactions</a>
                    </p>
                <?php endif; ?>
            </section>
        </div>
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
