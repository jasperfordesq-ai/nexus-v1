<?php
/**
 * CivicOne View: Wallet
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Wallet</li>
    </ol>
</nav>

<h1 class="govuk-heading-xl">
    <i class="fa-solid fa-wallet govuk-!-margin-right-2" aria-hidden="true"></i>
    Your Wallet
</h1>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <!-- Balance Card -->
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-6 civicone-panel-bg civicone-border-left-green">
            <h2 class="govuk-heading-m">Current Balance</h2>
            <p class="govuk-heading-xl govuk-!-margin-bottom-2 civicone-heading-green">
                <?= number_format($user['balance'] ?? 0) ?>
            </p>
            <p class="govuk-body"><strong>Time Credits</strong> available to spend.</p>
        </div>
    </div>

    <!-- Transfer Card -->
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-6 civicone-sidebar-card">
            <h2 class="govuk-heading-m">Make a Transfer</h2>

            <form action="<?= $basePath ?>/wallet/transfer" method="POST" id="civiconeWalletForm" onsubmit="return window.civiconeWallet.validateForm(this);">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="username" id="civiconeRecipientUsername" value="">
                <input type="hidden" name="recipient_id" id="civiconeRecipientId" value="">

                <!-- Selected User Display -->
                <div class="govuk-!-padding-3 govuk-!-margin-bottom-4 civicone-panel-bg" id="civiconeSelectedUser">
                    <div class="civicone-selected-user">
                        <div id="civiconeSelectedAvatar" class="civicone-selected-avatar">?</div>
                        <div class="civicone-selected-info">
                            <p class="govuk-body govuk-!-margin-bottom-0"><strong id="civiconeSelectedName">-</strong></p>
                            <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text" id="civiconeSelectedUsername">-</p>
                        </div>
                        <button type="button" onclick="window.civiconeWallet.clearSelection()" class="govuk-button govuk-button--secondary" data-module="govuk-button" title="Clear selection">
                            <i class="fa-solid fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <!-- Search Input -->
                <div class="govuk-form-group civicone-user-search-wrapper" id="civiconeSearchWrapper">
                    <label class="govuk-label" for="civiconeUserSearch">Recipient</label>
                    <input type="text" id="civiconeUserSearch" class="govuk-input" placeholder="Search by name or username..." autocomplete="off">
                    <div id="civiconeUserResults" class="civicone-search-results"></div>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="amount">Amount (Credits)</label>
                    <input type="number" name="amount" id="amount" class="govuk-input govuk-input--width-4" placeholder="1" min="1" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="description">What was this for?</label>
                    <input type="text" name="description" id="description" class="govuk-input" placeholder="Gardening help..." required>
                </div>

                <button type="submit" class="govuk-button" data-module="govuk-button" id="civiconeSubmitBtn">
                    <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i> Send Credits
                </button>
            </form>
        </div>
    </div>
</div>

<h2 class="govuk-heading-l">Recent Activity</h2>

<?php if (empty($transactions)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body">No transactions yet.</p>
    </div>
<?php else: ?>
    <table class="govuk-table" aria-label="Transaction history">
        <caption class="govuk-table__caption govuk-visually-hidden">Your recent time credit transactions</caption>
        <thead class="govuk-table__head">
            <tr class="govuk-table__row">
                <th scope="col" class="govuk-table__header">Date</th>
                <th scope="col" class="govuk-table__header">Description</th>
                <th scope="col" class="govuk-table__header">Participants</th>
                <th scope="col" class="govuk-table__header govuk-table__header--numeric">Amount</th>
            </tr>
        </thead>
        <tbody class="govuk-table__body">
            <?php foreach ($transactions as $t):
                $isOutgoing = $t['sender_id'] == $_SESSION['user_id'];
            ?>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell">
                        <time datetime="<?= $t['created_at'] ?>"><?= htmlspecialchars(date('M j, Y', strtotime($t['created_at']))) ?></time>
                    </td>
                    <td class="govuk-table__cell"><?= htmlspecialchars($t['description']) ?></td>
                    <td class="govuk-table__cell">
                        <?php if ($isOutgoing): ?>
                            To: <?= htmlspecialchars($t['receiver_name']) ?>
                        <?php else: ?>
                            From: <?= htmlspecialchars($t['sender_name']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="govuk-table__cell govuk-table__cell--numeric">
                        <span class="govuk-tag <?= $isOutgoing ? 'govuk-tag--red' : 'govuk-tag--green' ?>">
                            <?= $isOutgoing ? '-' : '+' ?><?= $t['amount'] ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($prefillRecipient)): ?>
<!-- Prefill data for external JS -->
<script type="application/json" id="civicone-prefill-data">
<?= json_encode([
    'username' => $prefillRecipient['username'] ?? '',
    'display_name' => $prefillRecipient['display_name'] ?? '',
    'avatar_url' => $prefillRecipient['avatar_url'] ?? '',
    'id' => $prefillRecipient['id'] ?? ''
]) ?>
</script>
<?php endif; ?>
<!-- User search handled by civicone-wallet-index.min.js -->

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
