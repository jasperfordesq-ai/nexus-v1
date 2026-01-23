/**
 * CivicOne Wallet Index User Search & Form Handling
 * Template G: Account Area
 * Provides autocomplete user search and form validation
 */

(function() {
    'use strict';

    let searchTimeout = null;
    let selectedIndex = -1;

    const searchInput = document.getElementById('civiconeUserSearch');
    const resultsDiv = document.getElementById('civiconeUserResults');
    const selectedDiv = document.getElementById('civiconeSelectedUser');
    const searchWrapper = document.getElementById('civiconeSearchWrapper');
    const usernameInput = document.getElementById('civiconeRecipientUsername');
    const recipientIdInput = document.getElementById('civiconeRecipientId');

    if (!searchInput) return;

    // ============================================
    // User Search on Input
    // ============================================
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(searchTimeout);
        selectedIndex = -1;

        if (query.length < 1) {
            resultsDiv.classList.remove('show');
            resultsDiv.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(() => searchUsers(query), 200);
    });

    // ============================================
    // Keyboard Navigation
    // ============================================
    searchInput.addEventListener('keydown', function(e) {
        const results = resultsDiv.querySelectorAll('.civicone-user-result');
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

    // ============================================
    // Close Results on Outside Click
    // ============================================
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.civicone-user-search-wrapper')) {
            resultsDiv.classList.remove('show');
        }
    });

    // ============================================
    // Update Results Selection
    // ============================================
    function updateResultsSelection(results) {
        results.forEach((r, i) => {
            r.classList.toggle('selected', i === selectedIndex);
        });
        if (results[selectedIndex]) {
            results[selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    // ============================================
    // Search Users API Call
    // ============================================
    function searchUsers(query) {
        const basePath = document.querySelector('meta[name="base-path"]')?.content || '';

        fetch(basePath + '/api/wallet/user-search', {
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
                        <div class="civicone-user-result"
                             onclick="window.civiconeWallet.selectUser('${escapeHtml(user.username || '')}', '${escapeHtml(user.display_name)}', '${escapeHtml(user.avatar_url || '')}', '${user.id}')"
                             tabindex="0"
                             role="option"
                             aria-selected="false">
                            <div class="civicone-user-avatar">${avatarHtml}</div>
                            <div class="civicone-user-info">
                                <div class="civicone-user-name">${escapeHtml(user.display_name)}</div>
                                <div class="civicone-user-username">${user.username ? '@' + escapeHtml(user.username) : '<em>No username set</em>'}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                resultsDiv.classList.add('show');
                resultsDiv.setAttribute('role', 'listbox');
            } else {
                resultsDiv.innerHTML = '<div class="civicone-user-no-results">No users found</div>';
                resultsDiv.classList.add('show');
            }
        })
        .catch(err => {
            console.error('User search error:', err);
            resultsDiv.innerHTML = '<div class="civicone-user-no-results">Search error</div>';
            resultsDiv.classList.add('show');
        });
    }

    // ============================================
    // Select User
    // ============================================
    function selectUser(username, displayName, avatarUrl, userId) {
        // Store values in hidden inputs
        usernameInput.value = username;
        recipientIdInput.value = userId || '';

        // Update selected user display
        const initial = (displayName || '?')[0].toUpperCase();
        const avatarEl = document.getElementById('civiconeSelectedAvatar');
        avatarEl.innerHTML = avatarUrl
            ? `<img src="${escapeHtml(avatarUrl)}" alt="">`
            : initial;

        document.getElementById('civiconeSelectedName').textContent = displayName;
        document.getElementById('civiconeSelectedUsername').textContent = username ? '@' + username : 'No username';

        // Show selected, hide search
        selectedDiv.classList.add('show');
        searchWrapper.classList.add('hidden');
        resultsDiv.classList.remove('show');
    }

    // ============================================
    // Clear Selection
    // ============================================
    function clearSelection() {
        usernameInput.value = '';
        recipientIdInput.value = '';
        selectedDiv.classList.remove('show');
        searchWrapper.classList.remove('hidden');
        searchInput.value = '';
        searchInput.focus();
    }

    // ============================================
    // Validate Form
    // ============================================
    function validateForm(form) {
        const username = usernameInput.value.trim();
        const recipientId = recipientIdInput.value.trim();

        if (!username && !recipientId) {
            alert('Please select a recipient from the search results.');
            searchInput.focus();
            return false;
        }

        // Disable button to prevent double submit
        const btn = document.getElementById('civiconeSubmitBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Sending...';
        }

        return true;
    }

    // ============================================
    // Escape HTML
    // ============================================
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================
    // Public API
    // ============================================
    window.civiconeWallet = {
        selectUser: selectUser,
        clearSelection: clearSelection,
        validateForm: validateForm
    };

    // ============================================
    // Pre-fill Recipient (from URL params)
    // ============================================
    const prefillData = document.getElementById('civicone-prefill-data');
    if (prefillData) {
        try {
            const data = JSON.parse(prefillData.textContent);
            if (data.username && data.display_name && data.id) {
                selectUser(data.username, data.display_name, data.avatar_url || '', data.id);
            }
        } catch (e) {
            console.error('Failed to parse prefill data:', e);
        }
    }

})();
