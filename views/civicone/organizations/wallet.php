<?php
/**
 * Organization Wallet - GOV.UK Design System
 * WCAG 2.1 AA Compliant
 */

$pageTitle = $org['name'] . ' - Wallet';
\Nexus\Core\SEO::setTitle($org['name'] . ' - Wallet');

// Set variables for the shared utility bar
$activeTab = 'wallet';
$isMember = $isMember ?? true;
$isOwner = $isOwner ?? ($role === 'owner');
$pendingCount = $summary['pending_requests'] ?? 0;

// Calculate balance percentage for gauge
$thresholds = \Nexus\Services\BalanceAlertService::getThresholds($org['id']);
$maxBalance = max($thresholds['low'] * 3, $summary['balance'] * 1.2, 100);
$balancePercent = min(100, ($summary['balance'] / $maxBalance) * 100);
$balanceStatus = \Nexus\Services\BalanceAlertService::getBalanceStatus($org['id']);
$basePath = \Nexus\Core\TenantContext::getBasePath();

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper">
        <!-- Shared Organization Utility Bar -->
        <?php include __DIR__ . '/_org-utility-bar.php'; ?>

        <!-- Stats Grid -->
        <?php
        $balanceStatusClasses = [
            'critical' => ['bg' => 'civicone-balance-bg--critical', 'border' => 'civicone-border-left-red', 'heading' => 'civicone-heading-red', 'gauge' => 'civicone-balance-gauge-fill--critical', 'tag' => 'govuk-tag--red'],
            'low' => ['bg' => 'civicone-balance-bg--low', 'border' => 'civicone-border-left-orange', 'heading' => 'civicone-heading-orange', 'gauge' => 'civicone-balance-gauge-fill--low', 'tag' => 'govuk-tag--orange'],
            'healthy' => ['bg' => 'civicone-balance-bg--healthy', 'border' => 'civicone-border-left-green', 'heading' => 'civicone-heading-green', 'gauge' => 'civicone-balance-gauge-fill--healthy', 'tag' => 'govuk-tag--green']
        ];
        $statusStyle = $balanceStatusClasses[$balanceStatus['status']] ?? $balanceStatusClasses['healthy'];
        ?>
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-!-padding-4 govuk-!-text-align-center <?= $statusStyle['bg'] ?> <?= $statusStyle['border'] ?>">
                    <?php if ($balanceStatus['status'] !== 'healthy'): ?>
                    <strong class="govuk-tag govuk-!-margin-bottom-2 <?= $statusStyle['tag'] ?>">
                        <?= $balanceStatus['label'] ?>
                    </strong>
                    <?php endif; ?>
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1 <?= $statusStyle['heading'] ?>" id="balanceValue" aria-live="polite">
                        <?= number_format($summary['balance'], 1) ?>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-2">Current Balance</p>
                    <div class="civicone-balance-gauge">
                        <div class="civicone-balance-gauge-fill <?= $statusStyle['gauge'] ?>" style="width: <?= $balancePercent ?>%;"></div>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-border-left-green">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1 civicone-heading-green">
                        <i class="fa-solid fa-arrow-down govuk-!-margin-right-1" aria-hidden="true"></i>
                        <?= number_format($summary['total_received'], 1) ?>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">Total Received</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-border-left-red">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1 civicone-heading-red">
                        <i class="fa-solid fa-arrow-up govuk-!-margin-right-1" aria-hidden="true"></i>
                        <?= number_format($summary['total_paid_out'], 1) ?>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">Total Paid Out</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-border-left-blue">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1 civicone-heading-blue">
                        <i class="fa-solid fa-clock-rotate-left govuk-!-margin-right-1" aria-hidden="true"></i>
                        <?= $summary['transaction_count'] ?>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">Transactions</p>
                </div>
            </div>
        </div>

        <div class="govuk-grid-row">
            <!-- Left Column: Actions -->
            <div class="govuk-grid-column-one-half">
                <!-- Deposit Form -->
                <div class="govuk-!-margin-bottom-6 civicone-sidebar-card">
                    <div class="govuk-!-padding-3 civicone-panel-bg civicone-section-header">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-arrow-down-to-bracket govuk-!-margin-right-2 civicone-icon-green" aria-hidden="true"></i>
                            Deposit to Organization
                        </h3>
                    </div>
                    <div class="govuk-!-padding-4">
                        <div class="govuk-inset-text govuk-!-margin-top-0">
                            Deposit credits from your personal wallet to the organization's shared wallet.
                        </div>

                        <div class="govuk-!-padding-3 govuk-!-margin-bottom-4 civicone-panel-bg civicone-border-left-blue">
                            <p class="govuk-body-s govuk-!-margin-bottom-0">
                                <strong>Your Balance:</strong> <?= number_format($user['balance'], 1) ?> HRS
                            </p>
                        </div>

                        <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet/deposit" method="POST" id="depositForm">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="depositAmount">Amount (Hours)</label>
                                <div class="govuk-hint">Max: <?= number_format($user['balance'], 1) ?> HRS</div>
                                <input type="number" name="amount" id="depositAmount" class="govuk-input govuk-input--width-10" min="0.5" max="<?= $user['balance'] ?>" step="0.5" required placeholder="0.0">
                            </div>
                            <div class="govuk-form-group">
                                <label class="govuk-label" for="depositDesc">Description (Optional)</label>
                                <input type="text" name="description" id="depositDesc" class="govuk-input" placeholder="e.g. Monthly contribution">
                            </div>
                            <button type="submit" class="govuk-button" data-module="govuk-button" <?= $user['balance'] <= 0 ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-arrow-down govuk-!-margin-right-2" aria-hidden="true"></i>
                                Deposit Credits
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Request Transfer Form -->
                <div class="govuk-!-margin-bottom-6 civicone-sidebar-card">
                    <div class="govuk-!-padding-3 civicone-panel-bg civicone-section-header">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-hand-holding-dollar govuk-!-margin-right-2 civicone-heading-purple" aria-hidden="true"></i>
                            Request Transfer
                        </h3>
                    </div>
                    <div class="govuk-!-padding-4">
                        <div class="govuk-inset-text govuk-!-margin-top-0">
                            Request credits from the organization wallet. An admin will need to approve your request.
                        </div>

                        <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet/request" method="POST" id="requestTransferForm">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="recipient_id" id="requestRecipientId" value="0">

                            <div class="govuk-form-group">
                                <label class="govuk-label" for="recipientType">Recipient</label>
                                <select name="recipient_type" id="recipientType" class="govuk-select" onchange="toggleRecipientSelect(this)">
                                    <option value="self">Myself</option>
                                    <option value="other">Another Member</option>
                                </select>
                            </div>

                            <div class="govuk-form-group govuk-!-display-none" id="memberSelectGroup">
                                <label class="govuk-label" for="memberSearch">Select Member</label>
                                <input type="text" id="memberSearch" class="govuk-input" placeholder="Search members..." autocomplete="off">
                                <div id="memberResults" class="civicone-search-results govuk-!-display-none"></div>
                            </div>

                            <div class="govuk-form-group">
                                <label class="govuk-label" for="requestAmount">Amount (Hours)</label>
                                <div class="govuk-hint">Organization balance: <?= number_format($summary['balance'], 1) ?> HRS</div>
                                <input type="number" name="amount" id="requestAmount" class="govuk-input govuk-input--width-10" min="0.5" max="<?= $summary['balance'] ?>" step="0.5" required placeholder="0.0">
                            </div>

                            <div class="govuk-form-group">
                                <label class="govuk-label" for="requestDesc">Reason for Request</label>
                                <textarea name="description" id="requestDesc" class="govuk-textarea" rows="3" required placeholder="Explain why you need these credits..."></textarea>
                            </div>

                            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                <i class="fa-solid fa-paper-plane govuk-!-margin-right-2" aria-hidden="true"></i>
                                Submit Request
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($isAdmin): ?>
                <!-- Direct Transfer (Admin Only) -->
                <div class="govuk-!-margin-bottom-6 civicone-sidebar-card">
                    <div class="govuk-!-padding-3 civicone-section-header--admin">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-bolt govuk-!-margin-right-2" aria-hidden="true"></i>
                            Direct Transfer (Admin)
                        </h3>
                    </div>
                    <div class="govuk-!-padding-4">
                        <div class="govuk-warning-text">
                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                            <strong class="govuk-warning-text__text">
                                <span class="govuk-visually-hidden">Warning</span>
                                As an admin, you can directly transfer credits without approval.
                            </strong>
                        </div>

                        <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet/direct-transfer" method="POST" id="directTransferForm">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="recipient_id" id="directRecipientId" value="">

                            <div class="govuk-form-group">
                                <label class="govuk-label" for="directMemberSearch">Recipient</label>
                                <input type="text" id="directMemberSearch" class="govuk-input" placeholder="Search members..." autocomplete="off">
                                <div id="directMemberResults" class="civicone-search-results govuk-!-display-none"></div>
                            </div>

                            <div class="govuk-form-group">
                                <label class="govuk-label" for="directAmount">Amount (Hours)</label>
                                <input type="number" name="amount" id="directAmount" class="govuk-input govuk-input--width-10" min="0.5" max="<?= $summary['balance'] ?>" step="0.5" required placeholder="0.0">
                            </div>

                            <div class="govuk-form-group">
                                <label class="govuk-label" for="directDesc">Description</label>
                                <input type="text" name="description" id="directDesc" class="govuk-input" required placeholder="e.g. Volunteer reward">
                            </div>

                            <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button" <?= $summary['balance'] <= 0 ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-bolt govuk-!-margin-right-2" aria-hidden="true"></i>
                                Transfer Now
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Requests & History -->
            <div class="govuk-grid-column-one-half">
                <?php if ($isAdmin && !empty($pendingRequests)): ?>
                <!-- Pending Requests (Admin) -->
                <div class="govuk-!-margin-bottom-6 civicone-sidebar-card">
                    <div class="govuk-!-padding-3 civicone-panel-bg civicone-section-header">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-inbox govuk-!-margin-right-2 civicone-icon-orange" aria-hidden="true"></i>
                            Pending Requests
                            <strong class="govuk-tag govuk-tag--orange govuk-!-margin-left-2"><?= count($pendingRequests) ?></strong>
                        </h3>
                    </div>

                    <div class="govuk-!-padding-0">
                        <?php foreach ($pendingRequests as $request): ?>
                        <div class="govuk-!-padding-3 civicone-transaction-item">
                            <div class="govuk-grid-row">
                                <div class="govuk-grid-column-two-thirds">
                                    <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-1">
                                        <?= htmlspecialchars($request['requester_name']) ?>
                                        <i class="fa-solid fa-arrow-right govuk-!-margin-left-1 govuk-!-margin-right-1 civicone-secondary-text" aria-hidden="true"></i>
                                        <?= htmlspecialchars($request['recipient_name']) ?>
                                    </p>
                                    <p class="govuk-body-s govuk-!-margin-bottom-1"><?= htmlspecialchars($request['description'] ?? 'No description') ?></p>
                                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text"><?= date('M d, Y g:i A', strtotime($request['created_at'])) ?></p>
                                </div>
                                <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                                    <strong class="govuk-tag govuk-tag--light-blue"><?= number_format($request['amount'], 1) ?> HRS</strong>
                                    <div class="govuk-!-margin-top-2">
                                        <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet/approve/<?= $request['id'] ?>" method="POST" class="civicone-inline-form">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0 civicone-btn-inline">
                                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <form action="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet/reject/<?= $request['id'] ?>" method="POST" class="civicone-inline-form">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0 civicone-btn-inline">
                                                <i class="fa-solid fa-times" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Transaction History -->
                <div class="civicone-sidebar-card">
                    <div class="govuk-!-padding-3 civicone-panel-bg civicone-section-header">
                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-two-thirds">
                                <h3 class="govuk-heading-s govuk-!-margin-bottom-0">
                                    <i class="fa-solid fa-clock-rotate-left govuk-!-margin-right-2 civicone-icon-green" aria-hidden="true"></i>
                                    Transaction History
                                </h3>
                            </div>
                            <?php if ($isAdmin): ?>
                            <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                                <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet/export" class="govuk-link">
                                    <i class="fa-solid fa-download govuk-!-margin-right-1" aria-hidden="true"></i>
                                    Export
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="govuk-!-padding-0 civicone-scrollable-list">
                        <?php if (empty($transactions)): ?>
                        <div class="govuk-!-padding-6 govuk-!-text-align-center">
                            <p class="govuk-body govuk-!-margin-bottom-4">
                                <i class="fa-solid fa-receipt fa-3x civicone-secondary-text" aria-hidden="true"></i>
                            </p>
                            <h4 class="govuk-heading-s">No transactions yet</h4>
                            <p class="govuk-body-s">Start by depositing credits from your personal wallet.</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx):
                                $isDeposit = ($tx['sender_type'] ?? '') === 'user' || ($tx['type'] ?? '') === 'deposit';
                            ?>
                            <div class="govuk-!-padding-3 civicone-transaction-item">
                                <div class="civicone-transaction-icon <?= $isDeposit ? 'civicone-transaction-icon--deposit' : 'civicone-transaction-icon--withdrawal' ?>">
                                    <i class="fa-solid <?= $isDeposit ? 'fa-arrow-down' : 'fa-arrow-up' ?>" aria-hidden="true"></i>
                                </div>
                                <div class="civicone-transaction-content">
                                    <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0">
                                        <?php if ($isDeposit): ?>
                                            Deposit from <?= htmlspecialchars($tx['sender_name'] ?? 'Member') ?>
                                        <?php else: ?>
                                            Transfer to <?= htmlspecialchars($tx['receiver_name'] ?? 'Member') ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                        <?= htmlspecialchars($tx['description'] ?? '-') ?>
                                    </p>
                                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text civicone-text-tiny">
                                        <?= date('M d, Y g:i A', strtotime($tx['created_at'])) ?>
                                    </p>
                                </div>
                                <div>
                                    <strong class="govuk-tag <?= $isDeposit ? 'govuk-tag--green' : 'govuk-tag--red' ?>">
                                        <?= $isDeposit ? '+' : '-' ?><?= number_format($tx['amount'], 1) ?> HRS
                                    </strong>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const orgMembers = <?= json_encode($members) ?>;
const basePath = '<?= $basePath ?>';
const orgId = <?= $org['id'] ?>;

function toggleRecipientSelect(select) {
    const group = document.getElementById('memberSelectGroup');
    if (select.value === 'other') {
        group.classList.remove('govuk-!-display-none');
    } else {
        group.classList.add('govuk-!-display-none');
        document.getElementById('requestRecipientId').value = '0';
    }
}

// Member search for request form
const memberSearch = document.getElementById('memberSearch');
const memberResults = document.getElementById('memberResults');
if (memberSearch) {
    memberSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        if (query.length < 1) {
            memberResults.classList.add('govuk-!-display-none');
            return;
        }
        const filtered = orgMembers.filter(m =>
            (m.display_name || '').toLowerCase().includes(query) ||
            (m.email || '').toLowerCase().includes(query)
        );
        if (filtered.length > 0) {
            memberResults.innerHTML = filtered.map(m => `
                <div class="civicone-search-result-item" onclick="selectMember(${m.user_id}, '${escapeHtml(m.display_name)}')">
                    <strong>${escapeHtml(m.display_name)}</strong>
                    <span class="civicone-secondary-text civicone-text-tiny">${m.role}</span>
                </div>
            `).join('');
            memberResults.classList.remove('govuk-!-display-none');
        } else {
            memberResults.innerHTML = '<div class="govuk-!-padding-3 civicone-secondary-text">No members found</div>';
            memberResults.classList.remove('govuk-!-display-none');
        }
    });
}

function selectMember(id, name) {
    document.getElementById('requestRecipientId').value = id;
    document.getElementById('memberSearch').value = name;
    memberResults.classList.add('govuk-!-display-none');
}

// Direct transfer member search (Admin)
const directSearch = document.getElementById('directMemberSearch');
const directResults = document.getElementById('directMemberResults');
if (directSearch) {
    directSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        if (query.length < 1) {
            directResults.classList.add('govuk-!-display-none');
            return;
        }
        const filtered = orgMembers.filter(m =>
            (m.display_name || '').toLowerCase().includes(query) ||
            (m.email || '').toLowerCase().includes(query)
        );
        if (filtered.length > 0) {
            directResults.innerHTML = filtered.map(m => `
                <div class="civicone-search-result-item" onclick="selectDirectMember(${m.user_id}, '${escapeHtml(m.display_name)}')">
                    <strong>${escapeHtml(m.display_name)}</strong>
                    <span class="civicone-secondary-text civicone-text-tiny">${m.role}</span>
                </div>
            `).join('');
            directResults.classList.remove('govuk-!-display-none');
        } else {
            directResults.innerHTML = '<div class="govuk-!-padding-3 civicone-secondary-text">No members found</div>';
            directResults.classList.remove('govuk-!-display-none');
        }
    });
}

function selectDirectMember(id, name) {
    document.getElementById('directRecipientId').value = id;
    document.getElementById('directMemberSearch').value = name;
    directResults.classList.add('govuk-!-display-none');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('#memberSearch') && !e.target.closest('#memberResults')) {
        if (memberResults) memberResults.classList.add('govuk-!-display-none');
    }
    if (!e.target.closest('#directMemberSearch') && !e.target.closest('#directMemberResults')) {
        if (directResults) directResults.classList.add('govuk-!-display-none');
    }
});
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
