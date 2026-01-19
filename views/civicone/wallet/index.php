<?php
// CivicOne View: Wallet - WCAG 2.1 AA Compliant
// CSS extracted to civicone-wallet.css
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="civic-container">
    <h1>Your Wallet</h1>

    <div class="civic-grid">
        <!-- Balance Card -->
        <div class="civic-card civic-balance-card">
            <h3>Current Balance</h3>
            <p class="civic-balance-amount">
                <?= number_format($user['balance'] ?? 0) ?>
            </p>
            <p><strong>Time Credits</strong> available to spend.</p>
        </div>

        <!-- Transfer Card -->
        <div class="civic-card">
            <h3>Make a Transfer</h3>
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet/transfer" method="POST" id="civicWalletForm" onsubmit="return validateCivicWalletForm(this);">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="username" id="civicRecipientUsername" value="">
                <input type="hidden" name="recipient_id" id="civicRecipientId" value="">

                <label class="civic-label">Recipient</label>

                <!-- Selected User Chip -->
                <div class="civic-selected-user" id="civicSelectedUser">
                    <div class="civic-user-avatar" id="civicSelectedAvatar">?</div>
                    <div class="civic-user-info">
                        <div class="civic-user-name" id="civicSelectedName">-</div>
                        <div class="civic-user-username" id="civicSelectedUsername">-</div>
                    </div>
                    <button type="button" class="civic-selected-clear" onclick="clearCivicSelection()" title="Clear selection">
                        &times;
                    </button>
                </div>

                <!-- Search Input -->
                <div class="civic-user-search-wrapper" id="civicSearchWrapper">
                    <input type="text" id="civicUserSearch" class="civic-input" placeholder="Search by name or username..." autocomplete="off">
                    <div class="civic-user-results" id="civicUserResults"></div>
                </div>

                <label for="amount" class="civic-label">Amount (Credits)</label>
                <input type="number" name="amount" id="amount" class="civic-input" placeholder="1" min="1" required>

                <label for="description" class="civic-label">What was this for?</label>
                <input type="text" name="description" id="description" class="civic-input" placeholder="Gardening help..." required>

                <button type="submit" class="civic-btn" id="civicSubmitBtn">Send Credits</button>
            </form>
        </div>
    </div>

    <h2 class="civic-wallet-history-header">Recent Activity</h2>
    <?php if (empty($transactions)): ?>
        <p>No transactions yet.</p>
    <?php else: ?>
        <!-- Desktop Table View -->
        <div class="wallet-table-wrapper">
            <table class="wallet-table-desktop" aria-label="Transaction history">
                <caption class="visually-hidden">Your recent time credit transactions</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Description</th>
                        <th scope="col">Participants</th>
                        <th scope="col">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t):
                        $isOutgoing = $t['sender_id'] == $_SESSION['user_id'];
                        $amountClass = $isOutgoing ? 'amount-negative' : 'amount-positive';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($t['created_at']) ?></td>
                            <td><?= htmlspecialchars($t['description']) ?></td>
                            <td>
                                <?php if ($isOutgoing): ?>
                                    To: <?= htmlspecialchars($t['receiver_name']) ?>
                                <?php else: ?>
                                    From: <?= htmlspecialchars($t['sender_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="<?= $amountClass ?>">
                                <?= $isOutgoing ? '-' : '+' ?><?= $t['amount'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="wallet-cards-mobile">
            <?php foreach ($transactions as $t):
                $isOutgoing = $t['sender_id'] == $_SESSION['user_id'];
                $amountClass = $isOutgoing ? 'wallet-card-amount negative' : 'wallet-card-amount positive';
            ?>
                <div class="civic-card wallet-card-item">
                    <div class="wallet-card-header">
                        <time class="wallet-card-date" datetime="<?= $t['created_at'] ?>"><?= htmlspecialchars(date('M j, Y', strtotime($t['created_at']))) ?></time>
                        <span class="<?= $amountClass ?>">
                            <?= $isOutgoing ? '-' : '+' ?><?= $t['amount'] ?>
                        </span>
                    </div>
                    <p class="wallet-card-description"><?= htmlspecialchars($t['description']) ?></p>
                    <p class="wallet-card-participant">
                        <?php if ($isOutgoing): ?>
                            To: <?= htmlspecialchars($t['receiver_name']) ?>
                        <?php else: ?>
                            From: <?= htmlspecialchars($t['sender_name']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    let searchTimeout = null;
    let selectedIndex = -1;

    const searchInput = document.getElementById('civicUserSearch');
    const resultsDiv = document.getElementById('civicUserResults');
    const selectedDiv = document.getElementById('civicSelectedUser');
    const searchWrapper = document.getElementById('civicSearchWrapper');
    const usernameInput = document.getElementById('civicRecipientUsername');
    const recipientIdInput = document.getElementById('civicRecipientId');

    if (!searchInput) return;

    // Search on input
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(searchTimeout);
        selectedIndex = -1;

        if (query.length < 1) {
            resultsDiv.classList.remove('show');
            resultsDiv.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(() => searchCivicUsers(query), 200);
    });

    // Keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
        const results = resultsDiv.querySelectorAll('.civic-user-result');
        if (!results.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, results.length - 1);
            updateResultsSelection(results);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
            updateResultsSelection(results);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && results[selectedIndex]) {
                results[selectedIndex].click();
            }
        } else if (e.key === 'Escape') {
            resultsDiv.classList.remove('show');
        }
    });

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.civic-user-search-wrapper')) {
            resultsDiv.classList.remove('show');
        }
    });

    function updateResultsSelection(results) {
        results.forEach((r, i) => {
            r.classList.toggle('selected', i === selectedIndex);
        });
        if (results[selectedIndex]) {
            results[selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    window.searchCivicUsers = function(query) {
        fetch('<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/wallet/user-search', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query })
        })
        .then(res => res.json())
        .then(data => {
            selectedIndex = -1;

            if (data.status === 'success' && data.users && data.users.length > 0) {
                resultsDiv.innerHTML = data.users.map(user => {
                    const initial = (user.display_name || '?')[0].toUpperCase();
                    const avatarHtml = user.avatar_url
                        ? `<img src="${escapeHtml(user.avatar_url)}" alt="">`
                        : initial;

                    return `
                        <div class="civic-user-result" onclick="selectCivicUser('${escapeHtml(user.username || '')}', '${escapeHtml(user.display_name)}', '${escapeHtml(user.avatar_url || '')}', '${user.id}')">
                            <div class="civic-user-avatar">${avatarHtml}</div>
                            <div class="civic-user-info">
                                <div class="civic-user-name">${escapeHtml(user.display_name)}</div>
                                <div class="civic-user-username">${user.username ? '@' + escapeHtml(user.username) : '<em>No username set</em>'}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                resultsDiv.classList.add('show');
            } else {
                resultsDiv.innerHTML = '<div class="civic-user-no-results">No users found</div>';
                resultsDiv.classList.add('show');
            }
        })
        .catch(err => {
            console.error('User search error:', err);
            resultsDiv.innerHTML = '<div class="civic-user-no-results">Search error</div>';
            resultsDiv.classList.add('show');
        });
    };

    window.selectCivicUser = function(username, displayName, avatarUrl, userId) {
        // Store values in hidden inputs
        usernameInput.value = username;
        recipientIdInput.value = userId || '';

        // Update selected user display
        const initial = (displayName || '?')[0].toUpperCase();
        document.getElementById('civicSelectedAvatar').innerHTML = avatarUrl
            ? `<img src="${escapeHtml(avatarUrl)}" alt="">`
            : initial;
        document.getElementById('civicSelectedName').textContent = displayName;
        document.getElementById('civicSelectedUsername').textContent = username ? '@' + username : 'No username';

        // Show selected, hide search
        selectedDiv.classList.add('show');
        searchWrapper.style.display = 'none';
        resultsDiv.classList.remove('show');
    };

    window.clearCivicSelection = function() {
        usernameInput.value = '';
        recipientIdInput.value = '';
        selectedDiv.classList.remove('show');
        searchWrapper.style.display = 'block';
        searchInput.value = '';
        searchInput.focus();
    };

    window.validateCivicWalletForm = function(form) {
        const username = usernameInput.value.trim();
        const recipientId = recipientIdInput.value.trim();

        if (!username && !recipientId) {
            alert('Please select a recipient from the search results.');
            searchInput.focus();
            return false;
        }

        // Disable button to prevent double submit
        const btn = document.getElementById('civicSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Sending...';

        return true;
    };

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Pre-fill recipient from profile page (when navigating via ?to=userId)
    <?php if (!empty($prefillRecipient)): ?>
    selectCivicUser(
        <?= json_encode($prefillRecipient['username'] ?? '') ?>,
        <?= json_encode($prefillRecipient['display_name'] ?? '') ?>,
        <?= json_encode($prefillRecipient['avatar_url'] ?? '') ?>,
        <?= json_encode($prefillRecipient['id'] ?? '') ?>
    );
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
