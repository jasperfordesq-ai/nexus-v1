/**
 * CivicOne Dashboard JavaScript
 * Handles all dashboard interactions including:
 * - FAB menu toggle
 * - Notification settings
 * - User search for wallet transfers
 * - Listing deletion
 * - Notification deletion
 *
 * Note: NEXUS_BASE is already declared globally in body-open.php
 */

/**
 * Initialize the CivicOne Dashboard
 * @param {string} basePath - The tenant base path (optional, uses global NEXUS_BASE if not provided)
 */
function initCivicOneDashboard(basePath) {
    // NEXUS_BASE already declared globally in body-open.php, just use it
    initUserSearch();
    initClickOutsideHandlers();
}

// ============================================
// FAB Menu
// ============================================

function toggleCivicFab() {
    const menu = document.getElementById('civicFabMenu');
    const btn = document.querySelector('.civic-fab-main');

    if (!menu || !btn) return;

    const isOpen = !menu.hidden;
    menu.hidden = isOpen;
    btn.classList.toggle('active', !isOpen);
    btn.setAttribute('aria-expanded', !isOpen);
}

// ============================================
// Notification Settings
// ============================================

function openEventsModal() {
    const modal = document.getElementById('events-modal');
    if (modal && typeof modal.showModal === 'function') {
        modal.showModal();
    }
}

function toggleNotifSettings() {
    const panel = document.getElementById('notif-settings-panel');
    if (panel) {
        panel.hidden = !panel.hidden;
    }
}

function updateNotifSetting(type, id, freq) {
    fetch(NEXUS_BASE + '/api/notifications/settings', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCSRFToken()
        },
        body: JSON.stringify({
            context_type: type,
            context_id: id,
            frequency: freq
        })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (!data.success) {
            alert('Failed to save settings');
        }
    })
    .catch(function(err) {
        console.error('Error updating notification settings:', err);
    });
}

function deleteNotificationDashboard(id) {
    if (!confirm('Delete this notification?')) return;

    const formData = new URLSearchParams();
    formData.append('id', id);

    fetch(NEXUS_BASE + '/api/notifications/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': getCSRFToken()
        },
        body: formData
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            const item = document.querySelector('[data-notif-id="' + id + '"]');
            if (item) item.remove();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(function() {
        alert('Failed to delete notification.');
    });
}

// ============================================
// Listing Deletion
// ============================================

function deleteListing(id) {
    if (!confirm('Are you sure you want to delete this listing? This cannot be undone.')) return;

    const el = document.getElementById('listing-' + id);
    // Use CSS class for pending state instead of inline style (GOV.UK compliance)
    if (el) el.classList.add('civic-item--pending');

    const body = new URLSearchParams();
    body.append('id', id);
    body.append('csrf_token', getCSRFToken());

    fetch(NEXUS_BASE + '/api/listings/delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-CSRF-Token': getCSRFToken()
        },
        body: body,
        credentials: 'same-origin'
    })
    .then(function(res) {
        if (!res.ok) {
            return res.json().then(function(data) {
                throw new Error(data.error || 'Request failed');
            });
        }
        return res.json();
    })
    .then(function(data) {
        if (data.success) {
            if (el) el.remove();
        } else {
            alert('Failed: ' + (data.error || 'Unknown error'));
            if (el) el.classList.remove('civic-item--pending');
        }
    })
    .catch(function(e) {
        alert('Error: ' + e.message);
        if (el) el.classList.remove('civic-item--pending');
    });
}

// ============================================
// Wallet User Search
// ============================================

let dashSearchTimeout = null;
let dashSelectedIndex = -1;

function initUserSearch() {
    const dashSearchInput = document.getElementById('dashUserSearch');
    const dashResultsContainer = document.getElementById('dashUserResults');
    const dashSearchWrapper = document.getElementById('dashSearchWrapper');

    if (!dashSearchInput) return;

    dashSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(dashSearchTimeout);

        if (query.length < 1) {
            dashResultsContainer.hidden = true;
            dashResultsContainer.innerHTML = '';
            dashSearchInput.setAttribute('aria-expanded', 'false');
            dashSearchInput.removeAttribute('aria-activedescendant');
            return;
        }

        dashSearchTimeout = setTimeout(function() {
            searchDashUsers(query);
        }, 200);
    });

    dashSearchInput.addEventListener('keydown', function(e) {
        const results = dashResultsContainer.querySelectorAll('.civic-user-result');
        if (!results.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            dashSelectedIndex = Math.min(dashSelectedIndex + 1, results.length - 1);
            updateDashResultsSelection(results, dashSearchInput);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            dashSelectedIndex = Math.max(dashSelectedIndex - 1, 0);
            updateDashResultsSelection(results, dashSearchInput);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (dashSelectedIndex >= 0 && results[dashSelectedIndex]) {
                results[dashSelectedIndex].click();
            }
        } else if (e.key === 'Escape') {
            dashResultsContainer.hidden = true;
            dashSearchInput.setAttribute('aria-expanded', 'false');
            dashSearchInput.removeAttribute('aria-activedescendant');
        }
    });
}

function updateDashResultsSelection(results, inputEl) {
    results.forEach(function(r, i) {
        const isSelected = i === dashSelectedIndex;
        if (isSelected) {
            r.classList.add('selected');
            r.setAttribute('aria-selected', 'true');
            if (inputEl && r.id) {
                inputEl.setAttribute('aria-activedescendant', r.id);
            }
        } else {
            r.classList.remove('selected');
            r.setAttribute('aria-selected', 'false');
        }
    });
    if (results[dashSelectedIndex]) {
        results[dashSelectedIndex].scrollIntoView({ block: 'nearest' });
    }
}

function searchDashUsers(query) {
    const dashResultsContainer = document.getElementById('dashUserResults');
    const dashSearchInput = document.getElementById('dashUserSearch');

    fetch(NEXUS_BASE + '/api/wallet/user-search', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query: query })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        dashSelectedIndex = -1;

        if (data.status === 'success' && data.users && data.users.length > 0) {
            dashResultsContainer.innerHTML = data.users.map(function(user, index) {
                const initial = (user.display_name || '?')[0].toUpperCase();
                const avatarHtml = user.avatar_url
                    ? '<img src="' + escapeHtml(user.avatar_url) + '" alt="" loading="lazy">'
                    : '<span>' + initial + '</span>';

                return '<button type="button" class="civic-user-result civic-search-result-item" ' +
                    'id="dash-user-option-' + index + '" ' +
                    'role="option" ' +
                    'aria-selected="false" ' +
                    'onclick="selectDashUser(\'' +
                    escapeHtml(user.username || '') + '\', \'' +
                    escapeHtml(user.display_name) + '\', \'' +
                    escapeHtml(user.avatar_url || '') + '\', \'' +
                    user.id + '\')">' +
                    '<div class="civic-user-avatar" aria-hidden="true">' + avatarHtml + '</div>' +
                    '<div class="civic-user-info">' +
                        '<div class="civic-user-name">' + escapeHtml(user.display_name) + '</div>' +
                        '<div class="civic-user-username">' + (user.username ? '@' + escapeHtml(user.username) : '<em>No username</em>') + '</div>' +
                    '</div>' +
                '</button>';
            }).join('');
            dashResultsContainer.hidden = false;
            if (dashSearchInput) {
                dashSearchInput.setAttribute('aria-expanded', 'true');
            }
        } else {
            dashResultsContainer.innerHTML = '<div class="civic-no-results" role="status">No users found</div>';
            dashResultsContainer.hidden = false;
            if (dashSearchInput) {
                dashSearchInput.setAttribute('aria-expanded', 'true');
            }
        }
    })
    .catch(function(err) {
        console.error('Dashboard user search error:', err);
        dashResultsContainer.innerHTML = '<div class="civic-no-results" role="alert">Search error</div>';
        dashResultsContainer.hidden = false;
        if (dashSearchInput) {
            dashSearchInput.setAttribute('aria-expanded', 'true');
        }
    });
}

function selectDashUser(username, displayName, avatarUrl, userId) {
    const dashUsernameInput = document.getElementById('dashRecipientUsername');
    const dashRecipientId = document.getElementById('dashRecipientId');
    const dashSelectedUser = document.getElementById('dashSelectedUser');
    const dashSearchWrapper = document.getElementById('dashSearchWrapper');
    const dashResultsContainer = document.getElementById('dashUserResults');
    const dashSearchInput = document.getElementById('dashUserSearch');

    dashUsernameInput.value = username;
    dashRecipientId.value = userId || '';

    const initial = (displayName || '?')[0].toUpperCase();
    const avatarEl = document.getElementById('dashSelectedAvatar');
    avatarEl.innerHTML = avatarUrl
        ? '<img src="' + escapeHtml(avatarUrl) + '" alt="" loading="lazy">'
        : '<span>' + initial + '</span>';

    document.getElementById('dashSelectedName').textContent = displayName;
    document.getElementById('dashSelectedUsername').textContent = username ? '@' + username : 'No username';

    dashSelectedUser.hidden = false;
    dashSearchWrapper.hidden = true;
    dashResultsContainer.hidden = true;
    if (dashSearchInput) {
        dashSearchInput.setAttribute('aria-expanded', 'false');
        dashSearchInput.removeAttribute('aria-activedescendant');
    }
}

function clearDashSelection() {
    const dashUsernameInput = document.getElementById('dashRecipientUsername');
    const dashRecipientId = document.getElementById('dashRecipientId');
    const dashSelectedUser = document.getElementById('dashSelectedUser');
    const dashSearchWrapper = document.getElementById('dashSearchWrapper');
    const dashSearchInput = document.getElementById('dashUserSearch');

    dashUsernameInput.value = '';
    dashRecipientId.value = '';
    dashSelectedUser.hidden = true;
    dashSearchWrapper.hidden = false;
    dashSearchInput.value = '';
    dashSearchInput.setAttribute('aria-expanded', 'false');
    dashSearchInput.removeAttribute('aria-activedescendant');
    dashSearchInput.focus();
}

function validateDashTransfer(form) {
    const username = document.getElementById('dashRecipientUsername').value.trim();
    const recipientId = document.getElementById('dashRecipientId').value.trim();

    if (!username && !recipientId) {
        alert('Please select a recipient from the search results.');
        const searchInput = document.getElementById('dashUserSearch');
        if (searchInput) searchInput.focus();
        return false;
    }

    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Sending...';
    }

    return true;
}

// ============================================
// Click Outside Handlers
// ============================================

function initClickOutsideHandlers() {
    document.addEventListener('click', function(e) {
        // FAB menu
        const fab = document.getElementById('civicFab');
        const fabMenu = document.getElementById('civicFabMenu');
        if (fab && fabMenu && !fab.contains(e.target)) {
            fabMenu.hidden = true;
            const btn = document.querySelector('.civic-fab-main');
            if (btn) {
                btn.classList.remove('active');
                btn.setAttribute('aria-expanded', 'false');
            }
        }

        // User search results
        const searchWrapper = document.getElementById('dashSearchWrapper');
        const resultsContainer = document.getElementById('dashUserResults');
        const searchInput = document.getElementById('dashUserSearch');
        if (searchWrapper && resultsContainer && !searchWrapper.contains(e.target)) {
            resultsContainer.hidden = true;
            if (searchInput) {
                searchInput.setAttribute('aria-expanded', 'false');
                searchInput.removeAttribute('aria-activedescendant');
            }
        }
    });
}

// ============================================
// Utilities
// ============================================

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}
