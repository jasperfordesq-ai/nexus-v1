<?php
/**
 * Super Admin - Bulk Operations
 *
 * Perform bulk actions on users and tenants.
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Bulk Operations';
require __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <span>Bulk Operations</span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-layer-group"></i>
            Bulk Operations
        </h1>
        <p class="super-page-subtitle">
            Perform mass updates on users and tenants
        </p>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="super-alert super-alert-success" style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-check-circle"></i>
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="super-alert super-alert-danger" style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-exclamation-circle"></i>
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
    <!-- Bulk Move Users -->
    <div class="super-card">
        <div class="super-card-header">
            <h3 class="super-card-title">
                <i class="fa-solid fa-users"></i>
                Bulk Move Users
            </h3>
        </div>
        <div class="super-card-body">
            <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                Move multiple users to a different tenant at once. Optionally grant Super Admin privileges.
            </p>

            <form method="POST" action="/super-admin/bulk/move-users" id="bulkMoveUsersForm">
                <?= Csrf::field() ?>

                <!-- Source Tenant Filter -->
                <div class="super-form-group">
                    <label class="super-form-label">Filter by Source Tenant</label>
                    <select id="sourceTenantFilter" class="super-form-select">
                        <option value="">All Tenants</option>
                        <?php foreach ($tenants as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['indented_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- User Selection -->
                <div class="super-form-group">
                    <label class="super-form-label">Select Users</label>
                    <div id="userSelectionContainer" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--super-border); border-radius: 6px; padding: 0.5rem;">
                        <div style="text-align: center; padding: 2rem; color: var(--super-text-muted);">
                            <i class="fa-solid fa-mouse-pointer"></i>
                            Select a source tenant to load users
                        </div>
                    </div>
                    <p class="super-form-help">
                        <span id="selectedUserCount">0</span> user(s) selected
                    </p>
                </div>

                <!-- Target Tenant -->
                <div class="super-form-group">
                    <label class="super-form-label">Target Tenant</label>
                    <select name="target_tenant_id" class="super-form-select" required>
                        <option value="">-- Select Target --</option>
                        <?php foreach ($tenants as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['indented_name']) ?>
                                <?= $t['allows_subtenants'] ? ' (Hub)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Grant Super Admin -->
                <div class="super-form-group" style="padding: 0.75rem; background: rgba(139, 92, 246, 0.1); border-radius: 6px;">
                    <label class="super-form-checkbox">
                        <input type="checkbox" name="grant_super_admin" value="1">
                        <span>Grant Super Admin to all moved users</span>
                    </label>
                    <p class="super-form-help" style="margin-left: 1.75rem; margin-bottom: 0;">
                        Only works if target is a Hub tenant
                    </p>
                </div>

                <button type="submit" class="super-btn super-btn-primary" style="width: 100%; justify-content: center;">
                    <i class="fa-solid fa-exchange-alt"></i>
                    Move Selected Users
                </button>
            </form>
        </div>
    </div>

    <!-- Bulk Tenant Operations -->
    <div class="super-card">
        <div class="super-card-header">
            <h3 class="super-card-title">
                <i class="fa-solid fa-building"></i>
                Bulk Tenant Operations
            </h3>
        </div>
        <div class="super-card-body">
            <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                Enable/disable multiple tenants or toggle Hub capability in bulk.
            </p>

            <form method="POST" action="/super-admin/bulk/update-tenants" id="bulkTenantsForm">
                <?= Csrf::field() ?>

                <!-- Tenant Selection -->
                <div class="super-form-group">
                    <label class="super-form-label">Select Tenants</label>
                    <div style="max-height: 250px; overflow-y: auto; border: 1px solid var(--super-border); border-radius: 6px; padding: 0.5rem;">
                        <?php foreach ($tenants as $t): ?>
                            <?php if ($t['id'] !== 1): // Cannot modify Master ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.5rem; cursor: pointer; border-radius: 4px;" class="tenant-checkbox-row">
                                    <input type="checkbox" name="tenant_ids[]" value="<?= $t['id'] ?>" class="tenant-checkbox">
                                    <span style="flex: 1;"><?= htmlspecialchars($t['indented_name']) ?></span>
                                    <?php if ($t['allows_subtenants']): ?>
                                        <span class="super-badge super-badge-purple" style="font-size: 0.65rem;">Hub</span>
                                    <?php endif; ?>
                                    <?php if (!$t['is_active']): ?>
                                        <span class="super-badge super-badge-danger" style="font-size: 0.65rem;">Inactive</span>
                                    <?php endif; ?>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <p class="super-form-help">
                        <span id="selectedTenantCount">0</span> tenant(s) selected
                        <a href="#" onclick="selectAllTenants(); return false;" style="margin-left: 0.5rem;">Select All</a>
                        <a href="#" onclick="deselectAllTenants(); return false;" style="margin-left: 0.5rem;">Deselect All</a>
                    </p>
                </div>

                <!-- Action Selection -->
                <div class="super-form-group">
                    <label class="super-form-label">Action</label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem;">
                        <label class="super-radio-card">
                            <input type="radio" name="action" value="activate" required>
                            <span class="super-radio-card-content">
                                <i class="fa-solid fa-check-circle text-success"></i>
                                <strong>Activate</strong>
                            </span>
                        </label>
                        <label class="super-radio-card">
                            <input type="radio" name="action" value="deactivate">
                            <span class="super-radio-card-content">
                                <i class="fa-solid fa-times-circle text-danger"></i>
                                <strong>Deactivate</strong>
                            </span>
                        </label>
                        <label class="super-radio-card">
                            <input type="radio" name="action" value="enable_hub">
                            <span class="super-radio-card-content">
                                <i class="fa-solid fa-toggle-on text-purple"></i>
                                <strong>Enable Hub</strong>
                            </span>
                        </label>
                        <label class="super-radio-card">
                            <input type="radio" name="action" value="disable_hub">
                            <span class="super-radio-card-content">
                                <i class="fa-solid fa-toggle-off text-secondary"></i>
                                <strong>Disable Hub</strong>
                            </span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="super-btn super-btn-warning" style="width: 100%; justify-content: center;"
                        onclick="return confirm('Apply this action to all selected tenants?');">
                    <i class="fa-solid fa-bolt"></i>
                    Apply to Selected
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.tenant-checkbox-row:hover {
    background: var(--super-bg);
}

.super-radio-card {
    display: block;
    cursor: pointer;
}

.super-radio-card input {
    display: none;
}

.super-radio-card-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.75rem;
    border: 2px solid var(--super-border);
    border-radius: 8px;
    transition: all 0.2s;
}

.super-radio-card input:checked + .super-radio-card-content {
    border-color: var(--super-primary);
    background: rgba(59, 130, 246, 0.1);
}

.super-radio-card-content i {
    font-size: 1.25rem;
}

.text-success { color: var(--super-success); }
.text-danger { color: var(--super-danger); }
.text-purple { color: var(--super-purple); }
.text-secondary { color: var(--super-text-muted); }

.user-checkbox-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.5rem;
    cursor: pointer;
    border-radius: 4px;
}

.user-checkbox-row:hover {
    background: var(--super-bg);
}
</style>

<script>
// Load users when source tenant changes
document.getElementById('sourceTenantFilter').addEventListener('change', function() {
    const tenantId = this.value;
    const container = document.getElementById('userSelectionContainer');

    if (!tenantId) {
        container.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--super-text-muted);"><i class="fa-solid fa-mouse-pointer"></i> Select a source tenant to load users</div>';
        return;
    }

    container.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--super-text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading users...</div>';

    fetch('/super-admin/api/users/search?tenant_id=' + tenantId)
        .then(r => r.json())
        .then(data => {
            if (data.users && data.users.length > 0) {
                let html = '';
                data.users.forEach(user => {
                    html += `
                        <label class="user-checkbox-row">
                            <input type="checkbox" name="user_ids[]" value="${user.id}" class="user-checkbox">
                            <span style="flex: 1;">
                                <strong>${user.first_name || ''} ${user.last_name || ''}</strong>
                                <span style="color: var(--super-text-muted); font-size: 0.75rem;">${user.email}</span>
                            </span>
                            ${user.is_tenant_super_admin ? '<span class="super-badge super-badge-purple" style="font-size: 0.65rem;">SA</span>' : ''}
                        </label>
                    `;
                });
                container.innerHTML = html;
                updateUserCount();
            } else {
                container.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--super-text-muted);">No users found in this tenant</div>';
            }
        })
        .catch(err => {
            container.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--super-danger);">Error loading users</div>';
        });
});

// Update selected counts
function updateUserCount() {
    const count = document.querySelectorAll('.user-checkbox:checked').length;
    document.getElementById('selectedUserCount').textContent = count;
}

function updateTenantCount() {
    const count = document.querySelectorAll('.tenant-checkbox:checked').length;
    document.getElementById('selectedTenantCount').textContent = count;
}

document.getElementById('userSelectionContainer').addEventListener('change', updateUserCount);
document.querySelectorAll('.tenant-checkbox').forEach(cb => cb.addEventListener('change', updateTenantCount));

// Select/Deselect all tenants
function selectAllTenants() {
    document.querySelectorAll('.tenant-checkbox').forEach(cb => cb.checked = true);
    updateTenantCount();
}

function deselectAllTenants() {
    document.querySelectorAll('.tenant-checkbox').forEach(cb => cb.checked = false);
    updateTenantCount();
}

// Form validation
document.getElementById('bulkMoveUsersForm').addEventListener('submit', function(e) {
    const selectedUsers = document.querySelectorAll('.user-checkbox:checked').length;
    if (selectedUsers === 0) {
        e.preventDefault();
        alert('Please select at least one user to move.');
        return false;
    }
    return confirm('Move ' + selectedUsers + ' user(s) to the selected tenant?');
});

document.getElementById('bulkTenantsForm').addEventListener('submit', function(e) {
    const selectedTenants = document.querySelectorAll('.tenant-checkbox:checked').length;
    if (selectedTenants === 0) {
        e.preventDefault();
        alert('Please select at least one tenant.');
        return false;
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
