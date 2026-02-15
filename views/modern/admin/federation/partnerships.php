<?php
/**
 * Tenant Admin Federation Partnerships
 * Manage partnerships with other timebanks
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Partnerships';
$adminPageSubtitle = 'Manage connections with other timebanks';
$adminPageIcon = 'fa-handshake';

require __DIR__ . '/../partials/admin-header.php';
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-handshake"></i>
            Federation Partnerships
        </h1>
        <p class="admin-page-subtitle">Connect with other timebanks to expand your community</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/federation/directory" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-compass"></i>
            Find Partners
        </a>
        <a href="<?= $basePath ?>/admin-legacy/federation" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-sliders"></i>
            Settings
        </a>
    </div>
</div>

<!-- Request New Partnership -->
<?php if (!empty($availableTenants)): ?>
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-plus"></i>
            Request New Partnership
        </h3>
    </div>
    <div class="admin-card-body">
        <form id="requestPartnershipForm" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="flex: 2; min-width: 250px;">
                <label class="admin-label">Select Timebank</label>
                <select id="targetTenantId" class="admin-input" required>
                    <option value="">Choose a timebank to partner with...</option>
                    <?php foreach ($availableTenants as $tenant): ?>
                    <option value="<?= $tenant['id'] ?>">
                        <?= htmlspecialchars($tenant['name']) ?>
                        <?php if (!empty($tenant['domain'])): ?>
                        (<?= htmlspecialchars($tenant['domain']) ?>)
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 150px;">
                <label class="admin-label">Federation Level</label>
                <select id="federationLevel" class="admin-input">
                    <option value="1">Level 1 - Discovery</option>
                    <option value="2">Level 2 - Social</option>
                    <option value="3">Level 3 - Economic</option>
                    <option value="4">Level 4 - Integrated</option>
                </select>
            </div>
            <div style="flex: 2; min-width: 250px;">
                <label class="admin-label">Message (optional)</label>
                <input type="text" id="partnershipNotes" class="admin-input" placeholder="Why do you want to partner?">
            </div>
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-paper-plane"></i>
                Send Request
            </button>
        </form>

        <div style="margin-top: 1rem; padding: 1rem; background: var(--admin-bg-secondary); border-radius: 8px;">
            <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem;">Federation Levels Explained:</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.85rem; color: var(--admin-text-muted);">
                <div><strong>L1 Discovery:</strong> See each other in directory</div>
                <div><strong>L2 Social:</strong> + View profiles, messaging</div>
                <div><strong>L3 Economic:</strong> + Time credit exchanges</div>
                <div><strong>L4 Integrated:</strong> + Full feature access</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Pending Requests (Incoming) -->
<?php if (!empty($pendingRequests)): ?>
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-inbox"></i>
            Incoming Requests (<?= count($pendingRequests) ?>)
        </h3>
    </div>
    <div class="admin-card-body">
        <?php foreach ($pendingRequests as $request): ?>
        <div class="partnership-request-card">
            <div class="partnership-request-info">
                <div class="partnership-request-header">
                    <strong><?= htmlspecialchars($request['requester_name'] ?? 'Unknown Timebank') ?></strong>
                    <span class="admin-badge admin-badge-warning">Pending</span>
                </div>
                <div class="partnership-request-meta">
                    <span><i class="fa-solid fa-layer-group"></i> Level <?= $request['federation_level'] ?></span>
                    <span><i class="fa-solid fa-clock"></i> <?= date('M j, Y', strtotime($request['requested_at'])) ?></span>
                </div>
                <?php if (!empty($request['notes'])): ?>
                <p style="margin: 0.5rem 0 0 0; font-style: italic; color: var(--admin-text-muted);">
                    "<?= htmlspecialchars($request['notes']) ?>"
                </p>
                <?php endif; ?>
            </div>
            <div class="partnership-request-actions">
                <button onclick="approvePartnership(<?= $request['id'] ?>)" class="admin-btn admin-btn-success">
                    <i class="fa-solid fa-check"></i> Approve
                </button>
                <button onclick="showCounterProposalModal(<?= $request['id'] ?>, <?= $request['federation_level'] ?>)" class="admin-btn admin-btn-warning">
                    <i class="fa-solid fa-handshake-angle"></i> Counter
                </button>
                <button onclick="rejectPartnership(<?= $request['id'] ?>)" class="admin-btn admin-btn-danger">
                    <i class="fa-solid fa-times"></i> Reject
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Counter-Proposals Awaiting Your Response -->
<?php $counterProposals = $counterProposals ?? []; ?>
<?php if (!empty($counterProposals)): ?>
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-handshake-angle"></i>
            Counter-Proposals (<?= count($counterProposals) ?>)
        </h3>
    </div>
    <div class="admin-card-body">
        <p style="color: var(--admin-text-muted); margin-bottom: 1rem; font-size: 0.9rem;">
            These timebanks have responded to your partnership requests with modified terms.
        </p>
        <?php foreach ($counterProposals as $cp): ?>
        <div class="partnership-request-card" style="border-left-color: var(--admin-info);">
            <div class="partnership-request-info">
                <div class="partnership-request-header">
                    <strong><?= htmlspecialchars($cp['partner_name'] ?? 'Unknown Timebank') ?></strong>
                    <span class="admin-badge admin-badge-info">Counter-Proposal</span>
                </div>
                <div class="partnership-request-meta">
                    <span><i class="fa-solid fa-layer-group"></i> Proposed Level <?= $cp['federation_level'] ?></span>
                    <span><i class="fa-solid fa-clock"></i> <?= date('M j, Y', strtotime($cp['counter_proposed_at'])) ?></span>
                </div>
                <?php if (!empty($cp['counter_proposal_message'])): ?>
                <p style="margin: 0.5rem 0 0 0; font-style: italic; color: var(--admin-text-muted);">
                    "<?= htmlspecialchars($cp['counter_proposal_message']) ?>"
                </p>
                <?php endif; ?>
            </div>
            <div class="partnership-request-actions">
                <button onclick="acceptCounterProposal(<?= $cp['id'] ?>)" class="admin-btn admin-btn-success">
                    <i class="fa-solid fa-check"></i> Accept
                </button>
                <button onclick="withdrawRequest(<?= $cp['id'] ?>)" class="admin-btn admin-btn-danger">
                    <i class="fa-solid fa-times"></i> Decline
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Outgoing Pending Requests -->
<?php $outgoingRequests = $outgoingRequests ?? []; ?>
<?php if (!empty($outgoingRequests)): ?>
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-paper-plane"></i>
            Sent Requests (<?= count($outgoingRequests) ?>)
        </h3>
    </div>
    <div class="admin-card-body">
        <?php foreach ($outgoingRequests as $request): ?>
        <div class="partnership-request-card" style="border-left-color: var(--admin-primary);">
            <div class="partnership-request-info">
                <div class="partnership-request-header">
                    <strong><?= htmlspecialchars($request['partner_name'] ?? 'Unknown Timebank') ?></strong>
                    <span class="admin-badge admin-badge-primary">Awaiting Response</span>
                </div>
                <div class="partnership-request-meta">
                    <span><i class="fa-solid fa-layer-group"></i> Level <?= $request['federation_level'] ?></span>
                    <span><i class="fa-solid fa-clock"></i> Sent <?= date('M j, Y', strtotime($request['requested_at'])) ?></span>
                </div>
            </div>
            <div class="partnership-request-actions">
                <button onclick="withdrawRequest(<?= $request['id'] ?>)" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-undo"></i> Withdraw
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- All Partnerships -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-list"></i>
            All Partnerships
        </h3>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Partner Timebank</th>
                <th>Level</th>
                <th>Features Enabled</th>
                <th>Status</th>
                <th>Since</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($partnerships)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">
                    <i class="fa-solid fa-handshake" style="font-size: 2.5rem; margin-bottom: 1rem; display: block;"></i>
                    No partnerships yet
                    <br>
                    <span style="font-size: 0.9rem;">Use the form above to request your first partnership!</span>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($partnerships as $p): ?>
            <tr id="partnership-row-<?= $p['id'] ?>">
                <td>
                    <strong><?= htmlspecialchars($p['partner_name'] ?? $p['tenant_name'] ?? 'Unknown') ?></strong>
                    <?php if (!empty($p['domain'])): ?>
                    <div style="font-size: 0.85rem; color: var(--admin-text-muted);">
                        <?= htmlspecialchars($p['domain']) ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $levelNames = ['', 'Discovery', 'Social', 'Economic', 'Integrated'];
                    $levelColors = ['', 'info', 'primary', 'warning', 'success'];
                    ?>
                    <span class="admin-badge admin-badge-<?= $levelColors[$p['federation_level']] ?? 'info' ?>">
                        L<?= $p['federation_level'] ?> <?= $levelNames[$p['federation_level']] ?? '' ?>
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                        <?php if ($p['profiles_enabled']): ?>
                        <span class="admin-badge admin-badge-secondary" title="Profiles"><i class="fa-solid fa-user"></i></span>
                        <?php endif; ?>
                        <?php if ($p['messaging_enabled']): ?>
                        <span class="admin-badge admin-badge-secondary" title="Messaging"><i class="fa-solid fa-envelope"></i></span>
                        <?php endif; ?>
                        <?php if ($p['transactions_enabled']): ?>
                        <span class="admin-badge admin-badge-secondary" title="Transactions"><i class="fa-solid fa-exchange-alt"></i></span>
                        <?php endif; ?>
                        <?php if ($p['listings_enabled']): ?>
                        <span class="admin-badge admin-badge-secondary" title="Listings"><i class="fa-solid fa-list"></i></span>
                        <?php endif; ?>
                        <?php if ($p['events_enabled']): ?>
                        <span class="admin-badge admin-badge-secondary" title="Events"><i class="fa-solid fa-calendar"></i></span>
                        <?php endif; ?>
                        <?php if ($p['groups_enabled']): ?>
                        <span class="admin-badge admin-badge-secondary" title="Groups"><i class="fa-solid fa-users"></i></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php
                    $statusColors = [
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        'terminated' => 'secondary'
                    ];
                    ?>
                    <span class="admin-badge admin-badge-<?= $statusColors[$p['status']] ?? 'secondary' ?>">
                        <?= ucfirst($p['status']) ?>
                    </span>
                </td>
                <td style="color: var(--admin-text-muted); font-size: 0.9rem;">
                    <?= date('M j, Y', strtotime($p['approved_at'] ?? $p['created_at'])) ?>
                </td>
                <td>
                    <?php
                    $currentTenantId = \Nexus\Core\TenantContext::getId();
                    $isIncoming = ($p['partner_tenant_id'] ?? 0) == $currentTenantId;
                    $isOutgoing = ($p['tenant_id'] ?? 0) == $currentTenantId;
                    ?>
                    <?php if ($p['status'] === 'active'): ?>
                    <button onclick="showPermissionsModal(<?= $p['id'] ?>, <?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"
                        class="admin-btn admin-btn-secondary admin-btn-sm" title="Edit Permissions">
                        <i class="fa-solid fa-sliders"></i>
                    </button>
                    <button onclick="terminatePartnership(<?= $p['id'] ?>)"
                        class="admin-btn admin-btn-danger admin-btn-sm" title="End Partnership">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                    <?php elseif ($p['status'] === 'pending' && $isIncoming): ?>
                    <!-- Incoming pending request - show approve/reject -->
                    <button onclick="approvePartnership(<?= $p['id'] ?>)"
                        class="admin-btn admin-btn-success admin-btn-sm" title="Approve">
                        <i class="fa-solid fa-check"></i>
                    </button>
                    <button onclick="rejectPartnership(<?= $p['id'] ?>)"
                        class="admin-btn admin-btn-danger admin-btn-sm" title="Reject">
                        <i class="fa-solid fa-times"></i>
                    </button>
                    <?php elseif ($p['status'] === 'pending' && $isOutgoing): ?>
                    <!-- Outgoing pending request - show withdraw -->
                    <button onclick="withdrawRequest(<?= $p['id'] ?>)"
                        class="admin-btn admin-btn-secondary admin-btn-sm" title="Withdraw Request">
                        <i class="fa-solid fa-undo"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Permissions Modal -->
<div id="permissionsModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-overlay" onclick="closePermissionsModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Partnership Permissions</h3>
            <button onclick="closePermissionsModal()" class="admin-btn admin-btn-sm admin-btn-secondary">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="admin-modal-body">
            <p style="color: var(--admin-text-muted); margin-bottom: 1rem;">
                Configure what features are enabled for this partnership.
            </p>
            <input type="hidden" id="modalPartnershipId">
            <div class="admin-toggle-list" id="permissionsToggles">
                <!-- Populated by JS -->
            </div>
        </div>
        <div class="admin-modal-footer">
            <button onclick="closePermissionsModal()" class="admin-btn admin-btn-secondary">Cancel</button>
            <button onclick="savePermissions()" class="admin-btn admin-btn-primary">Save Changes</button>
        </div>
    </div>
</div>

<style>
.partnership-request-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem;
    background: var(--admin-bg-secondary);
    border-radius: 8px;
    margin-bottom: 0.75rem;
    border-left: 4px solid var(--admin-warning);
}
.partnership-request-card:last-child {
    margin-bottom: 0;
}
.partnership-request-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}
.partnership-request-meta {
    display: flex;
    gap: 1.5rem;
    font-size: 0.9rem;
    color: var(--admin-text-muted);
}
.partnership-request-meta i {
    margin-right: 0.25rem;
}
.partnership-request-actions {
    display: flex;
    gap: 0.5rem;
}
.admin-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.admin-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
}
.admin-modal-content {
    position: relative;
    background: var(--admin-bg);
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow: auto;
}
.admin-modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--admin-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.admin-modal-header h3 {
    margin: 0;
}
.admin-modal-body {
    padding: 1.5rem;
}
.admin-modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--admin-border);
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}
.admin-toggle-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.admin-toggle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    background: var(--admin-bg-secondary);
    border-radius: 6px;
}
.admin-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}
.admin-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.admin-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 24px;
}
.admin-switch-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}
.admin-switch input:checked + .admin-switch-slider {
    background-color: var(--admin-success);
}
.admin-switch input:checked + .admin-switch-slider:before {
    transform: translateX(20px);
}
</style>

<script>
const csrfToken = '<?= Csrf::token() ?>';
const basePath = '<?= $basePath ?>';

document.getElementById('requestPartnershipForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const targetTenantId = document.getElementById('targetTenantId').value;
    const federationLevel = document.getElementById('federationLevel').value;
    const notes = document.getElementById('partnershipNotes').value;

    if (!targetTenantId) {
        alert('Please select a timebank');
        return;
    }

    fetch(basePath + '/admin-legacy/federation/request-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
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
            alert('Partnership request sent!');
            location.reload();
        } else {
            alert(data.error || 'Failed to send request');
        }
    });
});

function approvePartnership(id) {
    if (!confirm('Approve this partnership request?')) return;

    fetch(basePath + '/admin-legacy/federation/approve-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ partnership_id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to approve');
        }
    });
}

function rejectPartnership(id) {
    const reason = prompt('Reason for rejection (optional):');

    fetch(basePath + '/admin-legacy/federation/reject-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ partnership_id: id, reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to reject');
        }
    });
}

function terminatePartnership(id) {
    const reason = prompt('Reason for ending this partnership:');
    if (!reason) return;

    if (!confirm('Are you sure you want to end this partnership?')) return;

    fetch(basePath + '/admin-legacy/federation/terminate-partnership', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ partnership_id: id, reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to terminate partnership');
        }
    });
}

let currentPartnership = null;

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
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fa-solid ${p.icon}" style="width: 20px; color: var(--admin-primary);"></i>
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

function closePermissionsModal() {
    document.getElementById('permissionsModal').style.display = 'none';
    currentPartnership = null;
}

function savePermissions() {
    const partnershipId = document.getElementById('modalPartnershipId').value;
    const permissions = {};

    document.querySelectorAll('#permissionsToggles [data-permission]').forEach(input => {
        permissions[input.dataset.permission] = input.checked;
    });

    fetch(basePath + '/admin-legacy/federation/update-partnership-permissions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ partnership_id: partnershipId, permissions: permissions })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closePermissionsModal();
            location.reload();
        } else {
            alert(data.error || 'Failed to save permissions');
        }
    });
}

// Counter-Proposal Functions
function showCounterProposalModal(id, currentLevel) {
    document.getElementById('counterProposalPartnershipId').value = id;
    document.getElementById('counterProposalLevel').value = currentLevel;
    document.getElementById('counterProposalMessage').value = '';
    document.getElementById('counterProposalModal').style.display = 'flex';
}

function closeCounterProposalModal() {
    document.getElementById('counterProposalModal').style.display = 'none';
}

function submitCounterProposal() {
    const partnershipId = document.getElementById('counterProposalPartnershipId').value;
    const level = document.getElementById('counterProposalLevel').value;
    const message = document.getElementById('counterProposalMessage').value;

    fetch(basePath + '/admin-legacy/federation/counter-propose', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
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
            alert('Counter-proposal sent!');
            location.reload();
        } else {
            alert(data.error || 'Failed to send counter-proposal');
        }
    });
}

function acceptCounterProposal(id) {
    if (!confirm('Accept this counter-proposal and activate the partnership?')) return;

    fetch(basePath + '/admin-legacy/federation/accept-counter-proposal', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ partnership_id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Partnership is now active!');
            location.reload();
        } else {
            alert(data.error || 'Failed to accept counter-proposal');
        }
    });
}

function withdrawRequest(id) {
    if (!confirm('Withdraw this partnership request?')) return;

    fetch(basePath + '/admin-legacy/federation/withdraw-request', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ partnership_id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to withdraw request');
        }
    });
}
</script>

<!-- Counter-Proposal Modal -->
<div id="counterProposalModal" class="admin-modal" style="display: none;">
    <div class="admin-modal-overlay" onclick="closeCounterProposalModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Counter-Proposal</h3>
            <button onclick="closeCounterProposalModal()" class="admin-btn admin-btn-sm admin-btn-secondary">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="admin-modal-body">
            <p style="color: var(--admin-text-muted); margin-bottom: 1rem;">
                Suggest different terms for this partnership. The requester will need to accept your counter-proposal.
            </p>
            <input type="hidden" id="counterProposalPartnershipId">

            <div style="margin-bottom: 1rem;">
                <label class="admin-label">Proposed Federation Level</label>
                <select id="counterProposalLevel" class="admin-input">
                    <option value="1">Level 1 - Discovery (See in directory only)</option>
                    <option value="2">Level 2 - Social (Profiles + Messaging)</option>
                    <option value="3">Level 3 - Economic (+ Transactions)</option>
                    <option value="4">Level 4 - Integrated (Full access)</option>
                </select>
            </div>

            <div>
                <label class="admin-label">Message (optional)</label>
                <textarea id="counterProposalMessage" class="admin-input" rows="3"
                    placeholder="Explain why you're proposing different terms..."></textarea>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button onclick="closeCounterProposalModal()" class="admin-btn admin-btn-secondary">Cancel</button>
            <button onclick="submitCounterProposal()" class="admin-btn admin-btn-warning">
                <i class="fa-solid fa-paper-plane"></i>
                Send Counter-Proposal
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
