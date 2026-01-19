/**
 * Admin Federation JavaScript
 * Handles admin federation functionality
 * Created 2026-01-19 for civicone theme compliance
 */

let federationBasePath = '';
let federationCsrfToken = '';

/**
 * Initialize federation settings page
 */
function initFederationSettings(basePath, csrfToken) {
    federationBasePath = basePath;
    federationCsrfToken = csrfToken;
}

/**
 * Update a federation feature toggle
 */
function updateFeature(feature, enabled) {
    fetch(federationBasePath + '/admin/federation/update-feature', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({ feature: feature, enabled: enabled })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAdminToast('Setting updated', 'success');
            if (feature === 'tenant_federation_enabled') {
                setTimeout(() => location.reload(), 500);
            }
        } else {
            showAdminToast(data.error || 'Update failed', 'error');
            document.querySelector(`[data-feature="${feature}"]`).checked = !enabled;
        }
    })
    .catch(() => {
        showAdminToast('Network error', 'error');
        document.querySelector(`[data-feature="${feature}"]`).checked = !enabled;
    });
}

/**
 * Approve a partnership request
 */
function approvePartnership(id) {
    if (!confirm('Approve this partnership request?')) return;

    fetch(federationBasePath + '/admin/federation/approve-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({ partnership_id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showAdminToast(data.error || 'Failed to approve', 'error');
        }
    });
}

/**
 * Reject a partnership request
 */
function rejectPartnership(id) {
    const reason = prompt('Reason for rejection (optional):');

    fetch(federationBasePath + '/admin/federation/reject-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({ partnership_id: id, reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showAdminToast(data.error || 'Failed to reject', 'error');
        }
    });
}

/**
 * Terminate a partnership
 */
function terminatePartnership(id) {
    const reason = prompt('Reason for ending this partnership:');
    if (!reason) return;

    if (!confirm('Are you sure you want to end this partnership?')) return;

    fetch(federationBasePath + '/admin/federation/terminate-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({ partnership_id: id, reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showAdminToast(data.error || 'Failed to terminate partnership', 'error');
        }
    });
}

/**
 * Withdraw a partnership request
 */
function withdrawRequest(id) {
    if (!confirm('Withdraw this partnership request?')) return;

    fetch(federationBasePath + '/admin/federation/withdraw-request', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({ partnership_id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showAdminToast(data.error || 'Failed to withdraw request', 'error');
        }
    });
}

/**
 * Request a new partnership
 */
function requestPartnership(targetTenantId, federationLevel, notes) {
    if (!targetTenantId) {
        showAdminToast('Please select a timebank', 'error');
        return;
    }

    fetch(federationBasePath + '/admin/federation/request-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({
            target_tenant_id: targetTenantId,
            federation_level: federationLevel,
            notes: notes
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAdminToast('Partnership request sent!', 'success');
            location.reload();
        } else {
            showAdminToast(data.error || 'Failed to send request', 'error');
        }
    });
}

// Current partnership for modal operations
let currentPartnership = null;

/**
 * Show permissions modal for a partnership
 */
function showPermissionsModal(id, partnership) {
    currentPartnership = partnership;
    document.getElementById('modalPartnershipId').value = id;

    const permissions = [
        { key: 'profiles_enabled', label: 'Profile Viewing', icon: 'fa-user' },
        { key: 'messaging_enabled', label: 'Messaging', icon: 'fa-envelope' },
        { key: 'transactions_enabled', label: 'Transactions', icon: 'fa-exchange-alt' },
        { key: 'listings_enabled', label: 'Listings', icon: 'fa-list' },
        { key: 'events_enabled', label: 'Events', icon: 'fa-calendar' },
        { key: 'groups_enabled', label: 'Groups', icon: 'fa-users' },
    ];

    let html = '';
    permissions.forEach(p => {
        const checked = partnership[p.key] ? 'checked' : '';
        html += `
            <div class="admin-toggle-item">
                <div class="admin-toggle-info">
                    <i class="fa-solid ${p.icon} admin-toggle-icon"></i>
                    <span>${p.label}</span>
                </div>
                <label class="admin-switch">
                    <input type="checkbox" data-permission="${p.key}" ${checked}>
                    <span class="admin-switch-slider"></span>
                </label>
            </div>
        `;
    });

    document.getElementById('permissionsToggles').innerHTML = html;
    document.getElementById('permissionsModal').style.display = 'flex';
}

/**
 * Close permissions modal
 */
function closePermissionsModal() {
    document.getElementById('permissionsModal').style.display = 'none';
    currentPartnership = null;
}

/**
 * Save partnership permissions
 */
function savePermissions() {
    const partnershipId = document.getElementById('modalPartnershipId').value;
    const permissions = {};

    document.querySelectorAll('#permissionsToggles [data-permission]').forEach(input => {
        permissions[input.dataset.permission] = input.checked;
    });

    fetch(federationBasePath + '/admin/federation/update-partnership-permissions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({ partnership_id: partnershipId, permissions: permissions })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closePermissionsModal();
            location.reload();
        } else {
            showAdminToast(data.error || 'Failed to save permissions', 'error');
        }
    });
}

/**
 * Show counter-proposal modal
 */
function showCounterProposalModal(id, currentLevel) {
    document.getElementById('counterProposalPartnershipId').value = id;
    document.getElementById('counterProposalLevel').value = currentLevel;
    document.getElementById('counterProposalMessage').value = '';
    document.getElementById('counterProposalModal').style.display = 'flex';
}

/**
 * Close counter-proposal modal
 */
function closeCounterProposalModal() {
    document.getElementById('counterProposalModal').style.display = 'none';
}

/**
 * Submit counter-proposal
 */
function submitCounterProposal() {
    const partnershipId = document.getElementById('counterProposalPartnershipId').value;
    const level = document.getElementById('counterProposalLevel').value;
    const message = document.getElementById('counterProposalMessage').value;

    fetch(federationBasePath + '/admin/federation/counter-propose', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({
            partnership_id: partnershipId,
            federation_level: level,
            message: message
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeCounterProposalModal();
            showAdminToast('Counter-proposal sent!', 'success');
            location.reload();
        } else {
            showAdminToast(data.error || 'Failed to send counter-proposal', 'error');
        }
    });
}

/**
 * Accept a counter-proposal
 */
function acceptCounterProposal(id) {
    if (!confirm('Accept this counter-proposal and activate the partnership?')) return;

    fetch(federationBasePath + '/admin/federation/accept-counter-proposal', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({ partnership_id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAdminToast('Partnership is now active!', 'success');
            location.reload();
        } else {
            showAdminToast(data.error || 'Failed to accept counter-proposal', 'error');
        }
    });
}

/**
 * Toggle federation on/off (dashboard)
 */
function toggleFederation(enabled) {
    const confirmMsg = enabled
        ? 'Enable federation for your timebank?'
        : 'Are you sure you want to disable federation? Your timebank will be hidden from all partners.';

    if (!confirm(confirmMsg)) return;

    const btn = document.getElementById('federationToggle');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Processing...';
    }

    fetch(federationBasePath + '/admin/federation/dashboard/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': federationCsrfToken
        },
        body: JSON.stringify({ enabled: enabled })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showAdminToast(data.error || 'Failed to update', 'error');
            if (btn) {
                btn.disabled = false;
                btn.textContent = enabled ? 'Enable Federation' : 'Disable Federation';
            }
        }
    })
    .catch(() => {
        showAdminToast('Network error', 'error');
        if (btn) {
            btn.disabled = false;
            btn.textContent = enabled ? 'Enable Federation' : 'Disable Federation';
        }
    });
}

/**
 * Show admin toast notification
 */
function showAdminToast(message, type) {
    if (typeof AdminToast !== 'undefined') {
        AdminToast.show(message, type);
    } else {
        alert(message);
    }
}

/**
 * Helper functions for activity display
 */
function getActivityIcon(actionType) {
    const icons = {
        'message': 'envelope',
        'transaction': 'exchange-alt',
        'partnership': 'handshake',
        'profile': 'user',
        'listing': 'list',
        'event': 'calendar',
        'group': 'users',
        'settings': 'cog',
        'user_opted_in': 'user-plus',
        'user_opted_out': 'user-minus',
    };

    for (const [key, icon] of Object.entries(icons)) {
        if (actionType.toLowerCase().includes(key)) {
            return icon;
        }
    }
    return 'circle';
}

function formatActionType(actionType) {
    return actionType.replace(/[_-]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function timeAgo(datetime) {
    if (!datetime) return '';
    const time = new Date(datetime).getTime();
    const diff = (Date.now() - time) / 1000;

    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return new Date(time).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
