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

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

// Include shared UI components
include dirname(__DIR__) . '/components/org-ui-components.php';
?>

<!-- Organization wallet CSS -->
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/purged/civicone-organizations-wallet.min.css">

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
        <div class="org-glass-card org-stat-card balance org-position-relative" style="background: <?= $statusStyle['bg'] ?>;" role="region" aria-label="Current Balance">
            <?php if ($balanceStatus['status'] !== 'healthy'): ?>
            <div class="org-status-label" role="status">
                <?= $balanceStatus['label'] ?>
            </div>
            <?php endif; ?>
            <!-- Live update indicator -->
            <div class="org-live-indicator" id="liveIndicator">
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
            <div class="org-balance-message"><?= $balanceStatus['message'] ?></div>
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
        <div class="org-flex-column">

            <!-- Deposit Form -->
            <div class="org-glass-card">
                <h3 class="org-section-title">
                    <i class="fa-solid fa-arrow-down-to-bracket org-icon-emerald"></i>
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
                            <label class="org-form-label" for="depositAmount">Amount (Hours) <span class="org-required">*</span></label>
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
                                <i class="fa-solid fa-arrow-down org-icon-mr"></i>
                                Deposit Credits
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Request Transfer Form -->
            <div class="org-glass-card">
                <h3 class="org-section-title">
                    <i class="fa-solid fa-hand-holding-dollar org-icon-indigo"></i>
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

                        <div class="org-form-group org-hidden-form" id="memberSelectGroup">
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
                            <label class="org-form-label" for="requestAmount">Amount (Hours) <span class="org-required">*</span></label>
                            <input type="number" name="amount" id="requestAmount" min="0.5" max="<?= $summary['balance'] ?>" step="0.5" required
                                   placeholder="Enter amount" class="org-form-input" aria-describedby="requestAmountHint requestAmountError">
                            <i class="fa-solid fa-check org-validation-icon valid-icon" aria-hidden="true"></i>
                            <i class="fa-solid fa-times org-validation-icon invalid-icon" aria-hidden="true"></i>
                            <div class="org-validation-message" id="requestAmountError" role="alert"></div>
                            <div class="org-form-hint" id="requestAmountHint">Organization balance: <?= number_format($summary['balance'], 1) ?> HRS</div>
                        </div>
                        <div class="org-form-group" id="requestDescGroup">
                            <label class="org-form-label" for="requestDesc">Reason for Request <span class="org-required">*</span></label>
                            <textarea name="description" id="requestDesc" required placeholder="Explain why you need these credits..."
                                      class="org-form-textarea" aria-describedby="requestDescError" minlength="10"></textarea>
                            <i class="fa-solid fa-check org-validation-icon valid-icon" aria-hidden="true"></i>
                            <i class="fa-solid fa-times org-validation-icon invalid-icon" aria-hidden="true"></i>
                            <div class="org-validation-message" id="requestDescError" role="alert"></div>
                        </div>
                        <button type="submit" class="org-submit-btn secondary" id="requestBtn">
                            <span class="org-btn-text">
                                <i class="fa-solid fa-paper-plane org-icon-mr"></i>
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
                    <i class="fa-solid fa-bolt org-icon-amber"></i>
                    Direct Transfer (Admin)
                </h3>
                <div class="org-form-content">
                    <div class="org-info-box warning">
                        <i class="fa-solid fa-shield-halved"></i>
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
                        <button type="submit" class="org-submit-btn amber"
                                <?= $summary['balance'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-bolt org-icon-mr"></i>
                            Transfer Now
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Requests & History -->
        <div class="org-flex-column">

            <?php if ($isAdmin && !empty($pendingRequests)): ?>
            <!-- Pending Requests (Admin) -->
            <div class="org-glass-card" id="pendingRequestsContainer">
                <h3 class="org-section-title">
                    <i class="fa-solid fa-inbox org-icon-amber"></i>
                    Pending Requests
                    <span class="org-title-badge">
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
                                <i class="fa-solid fa-arrow-right org-icon-mx-sm org-icon-gray" aria-hidden="true"></i>
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
                <form id="approveForm" action="" method="POST" class="org-hidden-form">
                    <?= \Nexus\Core\Csrf::input() ?>
                </form>
                <form id="rejectForm" action="" method="POST" class="org-hidden-form">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="reason" id="rejectReasonInput">
                </form>
            </div>
            <?php endif; ?>

            <!-- Transaction History -->
            <div class="org-glass-card">
                <h3 class="org-section-title">
                    <i class="fa-solid fa-clock-rotate-left org-icon-emerald"></i>
                    Transaction History
                    <?php if ($isAdmin): ?>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet/export"
                       class="org-export-btn" title="Export transactions to CSV">
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
                        <i class="fa-solid fa-chart-line org-icon-emerald org-icon-mr"></i>
                        Monthly Activity
                    </h4>
                    <div class="org-chart-wrapper" id="monthlyChartWrapper">
                        <!-- SVG Chart - more reliable than Canvas -->
                        <svg id="monthlyChart" width="100%" height="200" viewBox="0 0 400 200" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Monthly activity chart"></svg>
                        <div class="org-chart-legend" id="chartLegend">
                            <span><i class="fa-solid fa-circle org-legend-dot emerald"></i>Received</span>
                            <span><i class="fa-solid fa-circle org-legend-dot red"></i>Paid Out</span>
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
        group.classList.remove('org-hidden-form');
    } else {
        group.classList.add('org-hidden-form');
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
            memberResults.innerHTML = '<div class="org-no-results">No members found</div>';
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
    document.getElementById('memberSearchWrapper').classList.add('org-hidden-form');
    memberResults.classList.remove('show');
}

function clearMemberSelection() {
    document.getElementById('requestRecipientId').value = '';
    document.getElementById('selectedMember').classList.remove('show');
    document.getElementById('memberSearchWrapper').classList.remove('org-hidden-form');
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
            directResults.innerHTML = '<div class="org-no-results">No members found</div>';
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
    document.getElementById('directSearchWrapper').classList.add('org-hidden-form');
    directResults.classList.remove('show');
}

function clearDirectSelection() {
    document.getElementById('directRecipientId').value = '';
    document.getElementById('directSelectedMember').classList.remove('show');
    document.getElementById('directSearchWrapper').classList.remove('org-hidden-form');
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

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
