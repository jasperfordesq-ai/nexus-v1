<?php
/**
 * Federation API Keys Management
 * Manage API keys for federation integrations
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'API Keys';
$adminPageSubtitle = 'Federation Integration Keys';
$adminPageIcon = 'fa-key';

require __DIR__ . '/../partials/admin-header.php';

$apiKeys = $apiKeys ?? [];
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-key"></i>
            Federation API Keys
        </h1>
        <p class="admin-page-subtitle">Manage API keys for external integrations</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/federation/api-keys/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i>
            Create API Key
        </a>
    </div>
</div>

<div class="fed-admin-card">
    <div class="fed-admin-card-header">
        <h3 class="fed-admin-card-title">
            <i class="fa-solid fa-list"></i>
            Active API Keys
        </h3>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Key (Partial)</th>
                <th>Permissions</th>
                <th>Last Used</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($apiKeys)): ?>
            <tr>
                <td colspan="6" class="admin-empty-state">
                    <i class="fa-solid fa-key"></i>
                    <p>No API keys created yet</p>
                    <a href="<?= $basePath ?>/admin-legacy/federation/api-keys/create" class="admin-btn admin-btn-primary">
                        Create Your First Key
                    </a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($apiKeys as $key): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($key['name'] ?? 'Unnamed Key') ?></strong>
                    <?php if (!empty($key['description'])): ?>
                    <div class="admin-text-muted admin-text-small">
                        <?= htmlspecialchars($key['description']) ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <code><?= htmlspecialchars(substr($key['key_prefix'] ?? '', 0, 8)) ?>...****</code>
                </td>
                <td>
                    <?php if (!empty($key['permissions'])): ?>
                        <?php foreach ($key['permissions'] as $perm): ?>
                        <span class="admin-badge admin-badge-secondary"><?= htmlspecialchars($perm) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <span class="admin-text-muted">None</span>
                    <?php endif; ?>
                </td>
                <td class="admin-text-muted">
                    <?= !empty($key['last_used_at']) ? date('M j, Y', strtotime($key['last_used_at'])) : 'Never' ?>
                </td>
                <td class="admin-text-muted">
                    <?= date('M j, Y', strtotime($key['created_at'])) ?>
                </td>
                <td>
                    <a href="<?= $basePath ?>/admin-legacy/federation/api-keys/<?= $key['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="View">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    <button onclick="revokeApiKey(<?= $key['id'] ?>)" class="admin-btn admin-btn-danger admin-btn-sm" title="Revoke">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function revokeApiKey(id) {
    if (!confirm('Are you sure you want to revoke this API key? This action cannot be undone.')) return;

    fetch('<?= $basePath ?>/admin-legacy/federation/api-keys/' + id + '/revoke', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= Csrf::token() ?>'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to revoke key');
        }
    });
}
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
