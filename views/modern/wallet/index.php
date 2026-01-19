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
