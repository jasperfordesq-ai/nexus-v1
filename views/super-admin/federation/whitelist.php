<?php
/**
 * Super Admin Federation Whitelist Management
 * Manage which tenants can participate in federation
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Federation Whitelist';
require __DIR__ . '/../partials/header.php';
?>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-building-shield"></i>
            Federation Whitelist
        </h1>
        <p class="super-page-subtitle">
            Manage which tenants are approved for federation participation
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/federation" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Overview
        </a>
    </div>
</div>

<!-- Add to Whitelist -->
<?php if (!empty($availableTenants)): ?>
<div class="super-card" style="margin-bottom: 1.5rem;">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-plus"></i>
            Add Tenant to Whitelist
        </h3>
    </div>
    <div class="super-card-body">
        <form id="addWhitelistForm" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="super-label">Tenant</label>
                <select id="addTenantId" class="super-input" required>
                    <option value="">Select a tenant...</option>
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
            <div style="flex: 2; min-width: 300px;">
                <label class="super-label">Notes (optional)</label>
                <input type="text" id="addNotes" class="super-input" placeholder="Reason for approval...">
            </div>
            <button type="submit" class="super-btn super-btn-primary">
                <i class="fa-solid fa-plus"></i>
                Add to Whitelist
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Whitelisted Tenants Table -->
<div class="super-card">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-list"></i>
            Whitelisted Tenants (<?= count($whitelistedTenants) ?>)
        </h3>
    </div>
    <table class="super-table">
        <thead>
            <tr>
                <th>Tenant</th>
                <th>Domain</th>
                <th>Approved By</th>
                <th>Approved Date</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($whitelistedTenants)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 3rem; color: var(--super-text-muted);">
                    <i class="fa-solid fa-building-shield" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No tenants whitelisted yet
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($whitelistedTenants as $tenant): ?>
            <tr id="whitelist-row-<?= $tenant['tenant_id'] ?>">
                <td>
                    <a href="/super-admin/federation/tenant/<?= $tenant['tenant_id'] ?>" class="super-table-link">
                        <?= htmlspecialchars($tenant['tenant_name'] ?? 'Unknown') ?>
                    </a>
                </td>
                <td style="color: var(--super-text-muted);">
                    <?= htmlspecialchars($tenant['tenant_domain'] ?? '-') ?>
                </td>
                <td>
                    <?= htmlspecialchars($tenant['approved_by_name'] ?? 'Unknown') ?>
                </td>
                <td style="color: var(--super-text-muted);">
                    <?= date('M j, Y', strtotime($tenant['approved_at'])) ?>
                </td>
                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?= htmlspecialchars($tenant['notes'] ?? '-') ?>
                </td>
                <td>
                    <a href="/super-admin/federation/tenant/<?= $tenant['tenant_id'] ?>" class="super-btn super-btn-sm super-btn-secondary">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    <button onclick="removeFromWhitelist(<?= $tenant['tenant_id'] ?>, '<?= htmlspecialchars($tenant['tenant_name'] ?? '', ENT_QUOTES) ?>')"
                        class="super-btn super-btn-sm super-btn-danger">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Status Message -->
<div id="statusMessage" style="display: none; position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 1.5rem; border-radius: 8px; color: white; z-index: 1000;"></div>

<script>
const csrfToken = '<?= Csrf::token() ?>';

document.getElementById('addWhitelistForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const tenantId = document.getElementById('addTenantId').value;
    const notes = document.getElementById('addNotes').value;

    if (!tenantId) {
        alert('Please select a tenant');
        return;
    }

    fetch('/super-admin/federation/add-to-whitelist', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ tenant_id: tenantId, notes: notes })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showStatus(data.error || 'Failed to add tenant', false);
        }
    });
});

function removeFromWhitelist(tenantId, tenantName) {
    if (!confirm(`Remove "${tenantName}" from the federation whitelist?\n\nThis will disable all federation features for this tenant.`)) {
        return;
    }

    fetch('/super-admin/federation/remove-from-whitelist', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ tenant_id: tenantId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('whitelist-row-' + tenantId)?.remove();
            showStatus('Tenant removed from whitelist', true);
        } else {
            showStatus(data.error || 'Failed to remove tenant', false);
        }
    });
}

function showStatus(message, success) {
    const el = document.getElementById('statusMessage');
    el.textContent = message;
    el.style.background = success ? '#16a34a' : '#dc2626';
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 3000);
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
