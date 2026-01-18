<?php
/**
 * Tenant Admin Federation Settings Dashboard
 * Allows tenant admins to manage their own federation settings
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Settings';
$adminPageSubtitle = 'Manage cross-tenant collaboration';
$adminPageIcon = 'fa-network-wired';

require __DIR__ . '/../partials/admin-header.php';
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-network-wired"></i>
            Federation Settings
        </h1>
        <p class="admin-page-subtitle">Manage how your timebank interacts with other timebanks</p>
    </div>
    <div class="admin-page-header-actions">
        <?php if ($systemEnabled && $isWhitelisted): ?>
        <a href="<?= $basePath ?>/admin/federation/directory" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-compass"></i>
            Directory
        </a>
        <a href="<?= $basePath ?>/admin/federation/analytics" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-chart-line"></i>
            Analytics
        </a>
        <a href="<?= $basePath ?>/admin/federation/partnerships" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-handshake"></i>
            Partnerships
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Status Banner -->
<?php if (!$systemEnabled): ?>
<div class="admin-alert admin-alert-info" style="margin-bottom: 1.5rem;">
    <i class="fa-solid fa-info-circle"></i>
    <div>
        <strong>Federation is not yet available</strong>
        <p style="margin: 0.25rem 0 0 0;">The platform administrator has not enabled federation features yet. Check back later.</p>
    </div>
</div>
<?php elseif (!$isWhitelisted): ?>
<div class="admin-alert admin-alert-warning" style="margin-bottom: 1.5rem;">
    <i class="fa-solid fa-clock"></i>
    <div>
        <strong>Pending Approval</strong>
        <p style="margin: 0.25rem 0 0 0;">Your timebank is not yet approved for federation. Contact the platform administrator to request access.</p>
    </div>
</div>
<?php endif; ?>

<!-- Status Summary Cards -->
<div class="admin-stats-grid" style="margin-bottom: 1.5rem;">
    <div class="admin-stat-card <?= ($statusSummary['canFederate'] ?? false) ? 'admin-stat-green' : 'admin-stat-gray' ?>">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-power-off"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= ($statusSummary['canFederate'] ?? false) ? 'Active' : 'Inactive' ?></div>
            <div class="admin-stat-label">Federation Status</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= count(array_filter($partnerships ?? [], fn($p) => $p['status'] === 'active')) ?></div>
            <div class="admin-stat-label">Active Partnerships</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-amber">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= count($pendingRequests ?? []) ?></div>
            <div class="admin-stat-label">Pending Requests</div>
        </div>
    </div>
</div>

<?php if ($systemEnabled && $isWhitelisted): ?>
<!-- Feature Toggles -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-toggle-on"></i>
            Federation Features
        </h3>
    </div>
    <div class="admin-card-body">
        <p style="color: var(--admin-text-muted); margin-bottom: 1.5rem;">
            Control which federation features are enabled for your timebank. You can enable features
            here, but actual functionality depends on your partnership agreements.
        </p>

        <div class="admin-toggle-list">
            <?php
            $featureList = [
                'tenant_federation_enabled' => ['Enable Federation', 'Turn on federation for your timebank', 'fa-power-off'],
                'tenant_appear_in_directory' => ['Appear in Directory', 'Let other timebanks discover you', 'fa-eye'],
                'tenant_profiles_enabled' => ['Share Profiles', 'Allow partners to view member profiles', 'fa-user'],
                'tenant_messaging_enabled' => ['Cross-Tenant Messaging', 'Allow messages between timebanks', 'fa-envelope'],
                'tenant_transactions_enabled' => ['Cross-Tenant Transactions', 'Allow time credit exchanges', 'fa-exchange-alt'],
                'tenant_listings_enabled' => ['Share Listings', 'Make listings visible to partners', 'fa-list'],
                'tenant_events_enabled' => ['Share Events', 'Make events visible to partners', 'fa-calendar'],
                'tenant_groups_enabled' => ['Federated Groups', 'Allow cross-tenant group membership', 'fa-users'],
            ];
            foreach ($featureList as $key => $info):
                $enabled = !empty($features[$key]['enabled']);
            ?>
            <div class="admin-toggle-item">
                <div class="admin-toggle-info">
                    <i class="fa-solid <?= $info[2] ?> admin-toggle-icon"></i>
                    <div>
                        <div class="admin-toggle-title"><?= $info[0] ?></div>
                        <div class="admin-toggle-desc"><?= $info[1] ?></div>
                    </div>
                </div>
                <label class="admin-switch">
                    <input type="checkbox" data-feature="<?= $key ?>" <?= $enabled ? 'checked' : '' ?>
                        onchange="updateFeature('<?= $key ?>', this.checked)">
                    <span class="admin-switch-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Pending Partnership Requests -->
<?php if (!empty($pendingRequests)): ?>
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-inbox"></i>
            Partnership Requests
        </h3>
    </div>
    <div class="admin-card-body">
        <?php foreach ($pendingRequests as $request): ?>
        <div class="admin-request-item">
            <div class="admin-request-info">
                <strong><?= htmlspecialchars($request['tenant_name'] ?? 'Unknown Timebank') ?></strong>
                <span class="admin-badge admin-badge-info">Level <?= $request['federation_level'] ?></span>
                <p style="margin: 0.25rem 0 0 0; color: var(--admin-text-muted); font-size: 0.9rem;">
                    Requested <?= date('M j, Y', strtotime($request['requested_at'])) ?>
                </p>
            </div>
            <div class="admin-request-actions">
                <button onclick="approvePartnership(<?= $request['id'] ?>)" class="admin-btn admin-btn-success admin-btn-sm">
                    <i class="fa-solid fa-check"></i> Approve
                </button>
                <button onclick="rejectPartnership(<?= $request['id'] ?>)" class="admin-btn admin-btn-danger admin-btn-sm">
                    <i class="fa-solid fa-times"></i> Reject
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Active Partnerships Summary -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fa-solid fa-handshake"></i>
            Active Partnerships
        </h3>
        <a href="<?= $basePath ?>/admin/federation/partnerships" class="admin-btn admin-btn-secondary admin-btn-sm">
            View All <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Partner Timebank</th>
                <th>Level</th>
                <th>Features</th>
                <th>Since</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $activePartnerships = array_filter($partnerships ?? [], fn($p) => $p['status'] === 'active');
            if (empty($activePartnerships)):
            ?>
            <tr>
                <td colspan="4" style="text-align: center; padding: 2rem; color: var(--admin-text-muted);">
                    <i class="fa-solid fa-handshake" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                    No active partnerships yet
                    <br><br>
                    <a href="<?= $basePath ?>/admin/federation/partnerships" class="admin-btn admin-btn-primary">
                        Find Partners
                    </a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach (array_slice($activePartnerships, 0, 5) as $p): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($p['partner_name'] ?? $p['tenant_name'] ?? 'Unknown') ?></strong>
                </td>
                <td>
                    <?php
                    $levelNames = ['', 'Discovery', 'Social', 'Economic', 'Integrated'];
                    ?>
                    <span class="admin-badge admin-badge-info">
                        L<?= $p['federation_level'] ?> - <?= $levelNames[$p['federation_level']] ?? '' ?>
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 0.25rem;">
                        <?php if ($p['profiles_enabled']): ?><span class="admin-badge" title="Profiles"><i class="fa-solid fa-user"></i></span><?php endif; ?>
                        <?php if ($p['messaging_enabled']): ?><span class="admin-badge" title="Messaging"><i class="fa-solid fa-envelope"></i></span><?php endif; ?>
                        <?php if ($p['transactions_enabled']): ?><span class="admin-badge" title="Transactions"><i class="fa-solid fa-exchange-alt"></i></span><?php endif; ?>
                        <?php if ($p['listings_enabled']): ?><span class="admin-badge" title="Listings"><i class="fa-solid fa-list"></i></span><?php endif; ?>
                    </div>
                </td>
                <td style="color: var(--admin-text-muted);">
                    <?= date('M j, Y', strtotime($p['approved_at'] ?? $p['created_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<style>
.admin-toggle-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.admin-toggle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: var(--admin-bg-secondary);
    border-radius: 8px;
}
.admin-toggle-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.admin-toggle-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-bg);
    border-radius: 8px;
    color: var(--admin-primary);
}
.admin-toggle-title {
    font-weight: 600;
}
.admin-toggle-desc {
    font-size: 0.9rem;
    color: var(--admin-text-muted);
}
.admin-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
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
    border-radius: 26px;
}
.admin-switch-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
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
    transform: translateX(24px);
}
.admin-request-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: var(--admin-bg-secondary);
    border-radius: 8px;
    margin-bottom: 0.75rem;
}
.admin-request-item:last-child {
    margin-bottom: 0;
}
.admin-request-actions {
    display: flex;
    gap: 0.5rem;
}
</style>

<script>
const csrfToken = '<?= Csrf::token() ?>';
const basePath = '<?= $basePath ?>';

function updateFeature(feature, enabled) {
    fetch(basePath + '/admin/federation/update-feature', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ feature: feature, enabled: enabled })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Setting updated', 'success');
            // Reload page when main federation toggle changes (other features depend on it)
            if (feature === 'tenant_federation_enabled') {
                setTimeout(() => location.reload(), 500);
            }
        } else {
            showToast(data.error || 'Update failed', 'error');
            // Revert the toggle
            document.querySelector(`[data-feature="${feature}"]`).checked = !enabled;
        }
    })
    .catch(() => {
        showToast('Network error', 'error');
        document.querySelector(`[data-feature="${feature}"]`).checked = !enabled;
    });
}

function approvePartnership(id) {
    if (!confirm('Approve this partnership request?')) return;

    fetch(basePath + '/admin/federation/approve-partnership', {
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
            showToast(data.error || 'Failed to approve', 'error');
        }
    });
}

function rejectPartnership(id) {
    const reason = prompt('Reason for rejection (optional):');

    fetch(basePath + '/admin/federation/reject-partnership', {
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
            showToast(data.error || 'Failed to reject', 'error');
        }
    });
}

function showToast(message, type) {
    // Use existing admin toast if available, otherwise alert
    if (typeof AdminToast !== 'undefined') {
        AdminToast.show(message, type);
    } else {
        alert(message);
    }
}
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
