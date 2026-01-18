<?php require __DIR__ . '/../../layouts/civicone/header.php'; ?>

<style>
/* Wallet User Search Autocomplete */
.civic-user-search-wrapper {
    position: relative;
}

.civic-user-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #E5E7EB;
    border-top: none;
    border-radius: 0 0 8px 8px;
    max-height: 250px;
    overflow-y: auto;
    z-index: 100;
    display: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.civic-user-results.show {
    display: block;
}

.civic-user-result {
    display: flex;
    align-items: center;
    padding: 12px;
    cursor: pointer;
    transition: background 0.15s;
    gap: 12px;
    border-bottom: 1px solid #F3F4F6;
}

.civic-user-result:last-child {
    border-bottom: none;
}

.civic-user-result:hover,
.civic-user-result.selected {
    background: #F9FAFB;
}

.civic-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--civic-brand, #1D70B8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
    overflow: hidden;
}

.civic-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.civic-user-info {
    flex: 1;
    min-width: 0;
}

.civic-user-name {
    font-weight: 600;
    color: #1F2937;
}

.civic-user-username {
    font-size: 0.85em;
    color: #6B7280;
}

.civic-user-no-results {
    padding: 16px;
    text-align: center;
    color: #9CA3AF;
}

/* Selected user chip */
.civic-selected-user {
    display: none;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #F0F9FF;
    border: 2px solid var(--civic-brand, #1D70B8);
    border-radius: 8px;
    margin-bottom: 16px;
}

.civic-selected-user.show {
    display: flex;
}

.civic-selected-clear {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: transparent;
    border: none;
    color: #6B7280;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    margin-left: auto;
}

.civic-selected-clear:hover {
    background: #FEE2E2;
    color: #DC2626;
}
</style>

<div class="civic-container">
    <h1>Your Wallet</h1>

    <div class="civic-grid">
        <!-- Balance Card -->
        <div class="civic-card" style="border-left: 10px solid var(--civic-brand);">
            <h3>Current Balance</h3>
            <p style="font-size: 4em; font-weight: bold; margin: 20px 0; color: var(--civic-brand);">
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

    <h2 style="margin-top: 40px; border-top: 2px solid #E5E7EB; padding-top: 20px;">Recent Activity</h2>
    <?php if (empty($transactions)): ?>
        <p>No transactions yet.</p>
    <?php else: ?>
        <!-- Desktop Table View -->
        <div class="wallet-table-wrapper" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="wallet-table-desktop" style="width: 100%; border-collapse: collapse; margin-top: 20px; min-width: 500px;" aria-label="Transaction history">
                <caption class="visually-hidden">Your recent time credit transactions</caption>
                <thead>
                    <tr style="background: #DEE0E2; text-align: left;">
                        <th scope="col" style="padding: 12px; border: 1px solid #E5E7EB;">Date</th>
                        <th scope="col" style="padding: 12px; border: 1px solid #E5E7EB;">Description</th>
                        <th scope="col" style="padding: 12px; border: 1px solid #E5E7EB;">Participants</th>
                        <th scope="col" style="padding: 12px; border: 1px solid #E5E7EB;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <tr style="border-bottom: 1px solid #E5E7EB;">
                            <td style="padding: 12px;"><?= htmlspecialchars($t['created_at']) ?></td>
                            <td style="padding: 12px;"><?= htmlspecialchars($t['description']) ?></td>
                            <td style="padding: 12px;">
                                <?php if ($t['sender_id'] == $_SESSION['user_id']): ?>
                                    To: <?= htmlspecialchars($t['receiver_name']) ?>
                                <?php else: ?>
                                    From: <?= htmlspecialchars($t['sender_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; font-weight: bold; color: <?= $t['sender_id'] == $_SESSION['user_id'] ? '#D32F2F' : '#00796B' ?>;">
                                <?= $t['sender_id'] == $_SESSION['user_id'] ? '-' : '+' ?><?= $t['amount'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="wallet-cards-mobile" style="display: none;">
            <?php foreach ($transactions as $t): ?>
                <div class="civic-card" style="margin-bottom: 12px; padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <span style="font-size: 0.85em; color: var(--civic-text-muted);"><?= htmlspecialchars(date('M j, Y', strtotime($t['created_at']))) ?></span>
                        <span style="font-weight: bold; font-size: 1.25em; color: <?= $t['sender_id'] == $_SESSION['user_id'] ? '#D32F2F' : '#00796B' ?>;">
                            <?= $t['sender_id'] == $_SESSION['user_id'] ? '-' : '+' ?><?= $t['amount'] ?>
                        </span>
                    </div>
                    <p style="font-weight: 600; margin: 0 0 4px 0;"><?= htmlspecialchars($t['description']) ?></p>
                    <p style="font-size: 0.9em; color: var(--civic-text-secondary); margin: 0;">
                        <?php if ($t['sender_id'] == $_SESSION['user_id']): ?>
                            To: <?= htmlspecialchars($t['receiver_name']) ?>
                        <?php else: ?>
                            From: <?= htmlspecialchars($t['sender_name']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
            @media (max-width: 600px) {
                .wallet-table-wrapper { display: none !important; }
                .wallet-cards-mobile { display: block !important; }
            }
        </style>
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
