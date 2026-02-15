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
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-plus"></i>
            Request New Partnership
        </h3>
    </div>
    <div class="fed-admin-card-body">
        <form id="requestPartnershipForm" class="admin-form-row">
            <div class="admin-form-group">
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
            <div class="admin-form-group">
                <label class="admin-label">Federation Level</label>
                <select id="federationLevel" class="admin-input">
                    <option value="1">Level 1 - Discovery</option>
                    <option value="2">Level 2 - Social</option>
                    <option value="3">Level 3 - Economic</option>
                    <option value="4">Level 4 - Integrated</option>
                </select>
            </div>
            <div class="admin-form-group">
                <label class="admin-label">Message (optional)</label>
                <input type="text" id="partnershipNotes" class="admin-input" placeholder="Why do you want to partner?">
            </div>
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-paper-plane"></i>
                Send Request
            </button>
        </form>

        <div class="admin-info-box">
            <h4>Federation Levels Explained:</h4>
            <div class="admin-levels-grid">
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
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-inbox"></i>
            Incoming Requests (<?= count($pendingRequests) ?>)
        </h3>
    </div>
    <div class="fed-admin-card-body">
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
                <p class="admin-text-muted admin-text-italic">
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
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-handshake-angle"></i>
            Counter-Proposals (<?= count($counterProposals) ?>)
        </h3>
    </div>
    <div class="fed-admin-card-body">
        <p class="admin-text-muted">
            These timebanks have responded to your partnership requests with modified terms.
        </p>
        <?php foreach ($counterProposals as $cp): ?>
        <div class="partnership-request-card">
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
                <p class="admin-text-muted admin-text-italic">
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
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-paper-plane"></i>
            Sent Requests (<?= count($outgoingRequests) ?>)
        </h3>
    </div>
    <div class="fed-admin-card-body">
        <?php foreach ($outgoingRequests as $request): ?>
        <div class="partnership-request-card">
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
<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
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
                <td colspan="6" class="admin-empty-state">
                    <i class="fa-solid fa-handshake"></i>
                    <p>No partnerships yet</p>
                    <span class="admin-text-muted">Use the form above to request your first partnership!</span>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($partnerships as $p): ?>
            <tr id="partnership-row-<?= $p['id'] ?>">
                <td>
                    <strong><?= htmlspecialchars($p['partner_name'] ?? $p['tenant_name'] ?? 'Unknown') ?></strong>
                    <?php if (!empty($p['domain'])): ?>
                    <div class="admin-text-muted admin-text-small">
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
                    <?php if ($p['profiles_enabled']): ?><span class="admin-badge admin-badge-secondary" title="Profiles"><i class="fa-solid fa-user"></i></span><?php endif; ?>
                    <?php if ($p['messaging_enabled']): ?><span class="admin-badge admin-badge-secondary" title="Messaging"><i class="fa-solid fa-envelope"></i></span><?php endif; ?>
                    <?php if ($p['transactions_enabled']): ?><span class="admin-badge admin-badge-secondary" title="Transactions"><i class="fa-solid fa-exchange-alt"></i></span><?php endif; ?>
                    <?php if ($p['listings_enabled']): ?><span class="admin-badge admin-badge-secondary" title="Listings"><i class="fa-solid fa-list"></i></span><?php endif; ?>
                    <?php if ($p['events_enabled']): ?><span class="admin-badge admin-badge-secondary" title="Events"><i class="fa-solid fa-calendar"></i></span><?php endif; ?>
                    <?php if ($p['groups_enabled']): ?><span class="admin-badge admin-badge-secondary" title="Groups"><i class="fa-solid fa-users"></i></span><?php endif; ?>
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
                <td class="admin-text-muted">
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
                    <button onclick="approvePartnership(<?= $p['id'] ?>)"
                        class="admin-btn admin-btn-success admin-btn-sm" title="Approve">
                        <i class="fa-solid fa-check"></i>
                    </button>
                    <button onclick="rejectPartnership(<?= $p['id'] ?>)"
                        class="admin-btn admin-btn-danger admin-btn-sm" title="Reject">
                        <i class="fa-solid fa-times"></i>
                    </button>
                    <?php elseif ($p['status'] === 'pending' && $isOutgoing): ?>
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
            <p class="admin-text-muted">
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
            <p class="admin-text-muted">
                Suggest different terms for this partnership. The requester will need to accept your counter-proposal.
            </p>
            <input type="hidden" id="counterProposalPartnershipId">

            <div class="admin-form-group">
                <label class="admin-label">Proposed Federation Level</label>
                <select id="counterProposalLevel" class="admin-input">
                    <option value="1">Level 1 - Discovery (See in directory only)</option>
                    <option value="2">Level 2 - Social (Profiles + Messaging)</option>
                    <option value="3">Level 3 - Economic (+ Transactions)</option>
                    <option value="4">Level 4 - Integrated (Full access)</option>
                </select>
            </div>

            <div class="admin-form-group">
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

<script src="/assets/js/admin-federation.js?v=<?= time() ?>"></script>
<script>
    initFederationSettings('<?= $basePath ?>', '<?= Csrf::token() ?>');

    document.getElementById('requestPartnershipForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const targetTenantId = document.getElementById('targetTenantId').value;
        const federationLevel = document.getElementById('federationLevel').value;
        const notes = document.getElementById('partnershipNotes').value;
        requestPartnership(targetTenantId, federationLevel, notes);
    });
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
