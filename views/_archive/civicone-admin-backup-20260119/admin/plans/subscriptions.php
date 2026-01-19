<?php
/**
 * Admin Subscription Manager
 * Assign plans to tenants
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Subscriptions';
$adminPageSubtitle = 'Plans';
$adminPageIcon = 'fa-users';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="page-hero">
    <div class="page-hero-content">
        <h1>
            <a href="<?= $basePath ?>/admin/plans" class="admin-back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Tenant Subscriptions
        </h1>
        <p>Manage plan assignments for all tenants</p>
    </div>
</div>

<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-blue">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Tenant Plan Assignments</h3>
            <p class="admin-card-subtitle">Click a tenant to change their subscription</p>
        </div>
    </div>

    <div class="admin-card-body" style="padding: 0;">
        <table class="subscription-table">
            <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Current Plan</th>
                    <th>Tier</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Expires</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenants as $tenant): ?>
                <tr>
                    <td>
                        <div class="tenant-info">
                            <strong><?= htmlspecialchars($tenant['tenant_name']) ?></strong>
                            <small><?= htmlspecialchars($tenant['tenant_slug']) ?></small>
                        </div>
                    </td>
                    <td>
                        <?php if ($tenant['plan_name']): ?>
                            <span class="plan-badge tier-<?= $tenant['tier_level'] ?>">
                                <?= htmlspecialchars($tenant['plan_name']) ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-none">No Plan</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($tenant['plan_name']): ?>
                            Tier <?= $tenant['tier_level'] ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $status = $tenant['status'] ?? 'none';
                        $statusClass = [
                            'active' => 'success',
                            'trial' => 'warning',
                            'expired' => 'danger',
                            'cancelled' => 'danger',
                            'none' => 'muted'
                        ][$status] ?? 'muted';
                        ?>
                        <span class="status-badge status-<?= $statusClass ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>
                    <td>
                        <?= $tenant['starts_at'] ? date('M j, Y', strtotime($tenant['starts_at'])) : '-' ?>
                    </td>
                    <td>
                        <?php if ($tenant['expires_at']): ?>
                            <?= date('M j, Y', strtotime($tenant['expires_at'])) ?>
                            <?php if (strtotime($tenant['expires_at']) < time()): ?>
                                <i class="fa-solid fa-exclamation-triangle" style="color: #ef4444;"></i>
                            <?php endif; ?>
                        <?php elseif ($tenant['trial_ends_at']): ?>
                            Trial: <?= date('M j, Y', strtotime($tenant['trial_ends_at'])) ?>
                        <?php else: ?>
                            <span style="color: rgba(255,255,255,0.6);">Unlimited</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button onclick="showAssignModal(<?= $tenant['tenant_id'] ?>, '<?= htmlspecialchars($tenant['tenant_name']) ?>', <?= $tenant['plan_id'] ?? 'null' ?>)"
                                class="admin-btn admin-btn-sm admin-btn-primary">
                            <i class="fa-solid fa-edit"></i> Assign
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assign Plan Modal -->
<div id="assignModal" class="admin-modal">
    <div class="admin-modal-backdrop" onclick="closeAssignModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h2>Assign Plan</h2>
            <button onclick="closeAssignModal()" class="admin-modal-close">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="admin-modal-body">
            <form id="assignForm">
                <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
                <input type="hidden" id="assign_tenant_id" name="tenant_id" value="">

                <div class="form-group">
                    <label><strong id="assign_tenant_name"></strong></label>
                    <p style="color: rgba(255,255,255,0.6); margin: 0.5rem 0 1.5rem 0;">Select a subscription plan for this tenant</p>
                </div>

                <div class="form-group">
                    <label for="assign_plan_id">Plan *</label>
                    <select id="assign_plan_id" name="plan_id" required onchange="updatePlanPreview()">
                        <option value="">-- Select Plan --</option>
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?= $plan['id'] ?>"
                                data-tier="<?= $plan['tier_level'] ?>"
                                data-price="<?= $plan['price_monthly'] ?>">
                            <?= htmlspecialchars($plan['name']) ?> (Tier <?= $plan['tier_level'] ?>) - $<?= number_format($plan['price_monthly'], 0) ?>/mo
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="planPreview" style="display: none; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-radius: 0.5rem; margin-bottom: 1.5rem;">
                    <!-- Plan details will be injected here -->
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="assign_is_trial" name="is_trial" value="1" onchange="toggleTrialFields()">
                        This is a trial subscription
                    </label>
                </div>

                <div id="trialFields" style="display: none;">
                    <div class="form-group">
                        <label for="assign_trial_days">Trial Duration (days)</label>
                        <input type="number" id="assign_trial_days" name="trial_days" min="1" max="365" value="14">
                    </div>
                </div>

                <div id="expiresFields">
                    <div class="form-group">
                        <label for="assign_expires_at">Expiration Date (optional)</label>
                        <input type="date" id="assign_expires_at" name="expires_at">
                        <small>Leave empty for unlimited subscription</small>
                    </div>
                </div>
            </form>
        </div>
        <div class="admin-modal-footer">
            <button onclick="closeAssignModal()" class="admin-btn admin-btn-secondary">Cancel</button>
            <button onclick="submitAssign()" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-check"></i> Assign Plan
            </button>
        </div>
    </div>
</div>

<style>
.subscription-table {
    width: 100%;
    border-collapse: collapse;
}

.subscription-table thead {
    background: rgba(255, 255, 255, 0.05);
}

.subscription-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.subscription-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.subscription-table tr:hover {
    background: rgba(255, 255, 255, 0.02);
}

.tenant-info strong {
    display: block;
    color: #fff;
    margin-bottom: 0.25rem;
}

.tenant-info small {
    color: rgba(255, 255, 255, 0.5);
    font-family: monospace;
}

.plan-badge {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    font-weight: 600;
    font-size: 0.875rem;
}

.plan-badge.tier-0 { background: rgba(100, 116, 139, 0.3); color: #cbd5e1; }
.plan-badge.tier-1 { background: rgba(34, 197, 94, 0.3); color: #86efac; }
.plan-badge.tier-2 { background: rgba(168, 85, 247, 0.3); color: #c084fc; }
.plan-badge.tier-3 { background: rgba(245, 158, 11, 0.3); color: #fbbf24; }

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.status-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.status-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
.status-muted { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
.status-none { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

.admin-back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
}
</style>

<script>
let currentTenantId = null;
let currentPlanId = null;

function showAssignModal(tenantId, tenantName, planId) {
    currentTenantId = tenantId;
    currentPlanId = planId;

    document.getElementById('assign_tenant_id').value = tenantId;
    document.getElementById('assign_tenant_name').textContent = tenantName;

    if (planId) {
        document.getElementById('assign_plan_id').value = planId;
        updatePlanPreview();
    }

    document.getElementById('assignModal').classList.add('open');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.remove('open');
    document.getElementById('assignForm').reset();
    document.getElementById('planPreview').style.display = 'none';
}

function toggleTrialFields() {
    const isTrial = document.getElementById('assign_is_trial').checked;
    document.getElementById('trialFields').style.display = isTrial ? 'block' : 'none';
    document.getElementById('expiresFields').style.display = isTrial ? 'none' : 'block';
}

function updatePlanPreview() {
    const select = document.getElementById('assign_plan_id');
    const option = select.options[select.selectedIndex];

    if (!option.value) {
        document.getElementById('planPreview').style.display = 'none';
        return;
    }

    const tier = option.getAttribute('data-tier');
    const price = option.getAttribute('data-price');

    document.getElementById('planPreview').innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>${option.text.split(' (')[0]}</strong>
                <div style="margin-top: 0.5rem; color: rgba(255,255,255,0.7);">
                    Tier ${tier} • $${price}/month
                </div>
            </div>
        </div>
    `;
    document.getElementById('planPreview').style.display = 'block';
}

function submitAssign() {
    const form = document.getElementById('assignForm');
    const formData = new FormData(form);

    fetch('<?= $basePath ?>/admin/plans/assign', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✓ Plan assigned successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to assign plan'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
