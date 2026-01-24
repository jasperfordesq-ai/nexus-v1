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
        <div class="govuk-!-padding-6" style="background: #f3f2f1; border-left: 5px solid #00703c;">
            <h2 class="govuk-heading-m">Current Balance</h2>
            <p class="govuk-heading-xl govuk-!-margin-bottom-2" style="color: #00703c;">
                <?= number_format($user['balance'] ?? 0) ?>
            </p>
            <p class="govuk-body"><strong>Time Credits</strong> available to spend.</p>
        </div>
    </div>

    <!-- Transfer Card -->
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-6" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-m">Make a Transfer</h2>

            <form action="<?= $basePath ?>/wallet/transfer" method="POST" id="civicWalletForm" onsubmit="return validateCivicWalletForm(this);">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="username" id="civicRecipientUsername" value="">
                <input type="hidden" name="recipient_id" id="civicRecipientId" value="">

                <!-- Selected User Display -->
                <div class="govuk-!-padding-3 govuk-!-margin-bottom-4" id="civicSelectedUser" style="background: #f3f2f1; display: none;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div id="civicSelectedAvatar" style="width: 40px; height: 40px; border-radius: 50%; background: #1d70b8; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">?</div>
                        <div style="flex: 1;">
                            <p class="govuk-body govuk-!-margin-bottom-0"><strong id="civicSelectedName">-</strong></p>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" id="civicSelectedUsername" style="color: #505a5f;">-</p>
                        </div>
                        <button type="button" onclick="clearCivicSelection()" class="govuk-button govuk-button--secondary" data-module="govuk-button" title="Clear selection">
                            <i class="fa-solid fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <!-- Search Input -->
                <div class="govuk-form-group" id="civicSearchWrapper">
                    <label class="govuk-label" for="civicUserSearch">Recipient</label>
                    <input type="text" id="civicUserSearch" class="govuk-input" placeholder="Search by name or username..." autocomplete="off">
                    <div id="civicUserResults" style="border: 1px solid #b1b4b6; display: none; max-height: 200px; overflow-y: auto;"></div>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="amount">Amount (Credits)</label>
                    <input type="number" name="amount" id="amount" class="govuk-input govuk-input--width-4" placeholder="1" min="1" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="description">What was this for?</label>
                    <input type="text" name="description" id="description" class="govuk-input" placeholder="Gardening help..." required>
                </div>

                <button type="submit" class="govuk-button" data-module="govuk-button" id="civicSubmitBtn">
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

<script>
(function() {
    var searchTimeout = null;
    var selectedIndex = -1;

    var searchInput = document.getElementById('civicUserSearch');
    var resultsDiv = document.getElementById('civicUserResults');
    var selectedDiv = document.getElementById('civicSelectedUser');
    var searchWrapper = document.getElementById('civicSearchWrapper');
    var usernameInput = document.getElementById('civicRecipientUsername');
    var recipientIdInput = document.getElementById('civicRecipientId');

    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        selectedIndex = -1;

        if (query.length < 1) {
            resultsDiv.style.display = 'none';
            resultsDiv.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(function() { searchCivicUsers(query); }, 200);
    });

    searchInput.addEventListener('keydown', function(e) {
        var results = resultsDiv.querySelectorAll('.govuk-wallet-result');
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
            resultsDiv.style.display = 'none';
        }
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#civicSearchWrapper')) {
            resultsDiv.style.display = 'none';
        }
    });

    function updateResultsSelection(results) {
        results.forEach(function(r, i) {
            r.style.background = i === selectedIndex ? '#f3f2f1' : 'white';
        });
    }

    window.searchCivicUsers = function(query) {
        fetch('<?= $basePath ?>/api/wallet/user-search', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            selectedIndex = -1;

            if (data.status === 'success' && data.users && data.users.length > 0) {
                resultsDiv.innerHTML = data.users.map(function(user) {
                    var initial = (user.display_name || '?')[0].toUpperCase();
                    return '<div class="govuk-wallet-result govuk-!-padding-3" style="cursor: pointer; border-bottom: 1px solid #f3f2f1;" onclick="selectCivicUser(\'' + escapeHtml(user.username || '') + '\', \'' + escapeHtml(user.display_name) + '\', \'' + escapeHtml(user.avatar_url || '') + '\', \'' + user.id + '\')">' +
                        '<strong>' + escapeHtml(user.display_name) + '</strong>' +
                        '<span style="color: #505a5f;"> ' + (user.username ? '@' + escapeHtml(user.username) : '') + '</span>' +
                    '</div>';
                }).join('');
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.innerHTML = '<div class="govuk-!-padding-3" style="color: #505a5f;">No users found</div>';
                resultsDiv.style.display = 'block';
            }
        })
        .catch(function(err) {
            console.warn('User search error:', err);
        });
    };

    window.selectCivicUser = function(username, displayName, avatarUrl, userId) {
        usernameInput.value = username;
        recipientIdInput.value = userId || '';

        document.getElementById('civicSelectedName').textContent = displayName;
        document.getElementById('civicSelectedUsername').textContent = username ? '@' + username : 'No username';

        selectedDiv.style.display = 'block';
        searchWrapper.style.display = 'none';
        resultsDiv.style.display = 'none';
    };

    window.clearCivicSelection = function() {
        usernameInput.value = '';
        recipientIdInput.value = '';
        selectedDiv.style.display = 'none';
        searchWrapper.style.display = 'block';
        searchInput.value = '';
        searchInput.focus();
    };

    window.validateCivicWalletForm = function(form) {
        var username = usernameInput.value.trim();
        var recipientId = recipientIdInput.value.trim();

        if (!username && !recipientId) {
            alert('Please select a recipient from the search results.');
            searchInput.focus();
            return false;
        }

        var btn = document.getElementById('civicSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Sending...';

        return true;
    };

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

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
