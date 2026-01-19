<?php
/**
 * View Federation API Key Details
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = $apiKey['name'] ?? 'API Key Details';
$adminPageSubtitle = 'Key Information';
$adminPageIcon = 'fa-key';

require __DIR__ . '/../partials/admin-header.php';

$apiKey = $apiKey ?? [];
$usageStats = $usageStats ?? [];
?>

<a href="<?= $basePath ?>/admin/federation/api-keys" class="admin-back-link">
    <i class="fa-solid fa-arrow-left"></i> Back to API Keys
</a>

<div class="fed-grid-2" style="margin-top: 1rem;">
    <!-- Key Details -->
    <div class="fed-admin-card">
        <div class="fed-admin-card-header">
            <h3 class="fed-admin-card-title">
                <i class="fa-solid fa-key"></i>
                Key Details
            </h3>
        </div>
        <div class="fed-admin-card-body">
            <div class="admin-form-group">
                <label class="admin-label">Name</label>
                <div class="admin-text"><?= htmlspecialchars($apiKey['name'] ?? 'Unnamed') ?></div>
            </div>

            <?php if (!empty($apiKey['description'])): ?>
            <div class="admin-form-group">
                <label class="admin-label">Description</label>
                <div class="admin-text"><?= htmlspecialchars($apiKey['description']) ?></div>
            </div>
            <?php endif; ?>

            <div class="admin-form-group">
                <label class="admin-label">Key Prefix</label>
                <code><?= htmlspecialchars($apiKey['key_prefix'] ?? '') ?>...****</code>
                <small class="admin-text-muted">Full key is only shown once at creation</small>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Created</label>
                <div class="admin-text"><?= date('M j, Y \a\t g:i A', strtotime($apiKey['created_at'])) ?></div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Last Used</label>
                <div class="admin-text">
                    <?= !empty($apiKey['last_used_at']) ? date('M j, Y \a\t g:i A', strtotime($apiKey['last_used_at'])) : 'Never' ?>
                </div>
            </div>

            <?php if (!empty($apiKey['expires_at'])): ?>
            <div class="admin-form-group">
                <label class="admin-label">Expires</label>
                <div class="admin-text">
                    <?= date('M j, Y', strtotime($apiKey['expires_at'])) ?>
                    <?php if (strtotime($apiKey['expires_at']) < time()): ?>
                    <span class="admin-badge admin-badge-danger">Expired</span>
                    <?php elseif (strtotime($apiKey['expires_at']) < strtotime('+7 days')): ?>
                    <span class="admin-badge admin-badge-warning">Expiring Soon</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-top: 1.5rem;">
                <button onclick="revokeApiKey(<?= $apiKey['id'] ?>)" class="admin-btn admin-btn-danger">
                    <i class="fa-solid fa-ban"></i>
                    Revoke Key
                </button>
            </div>
        </div>
    </div>

    <!-- Permissions & Usage -->
    <div>
        <div class="fed-admin-card">
            <div class="fed-admin-card-header">
                <h3 class="fed-admin-card-title">
                    <i class="fa-solid fa-shield-halved"></i>
                    Permissions
                </h3>
            </div>
            <div class="fed-admin-card-body">
                <?php if (empty($apiKey['permissions'])): ?>
                <p class="admin-text-muted">No permissions assigned</p>
                <?php else: ?>
                <div class="admin-toggle-list">
                    <?php foreach ($apiKey['permissions'] as $perm): ?>
                    <div class="admin-toggle-item">
                        <span class="admin-badge admin-badge-info"><?= htmlspecialchars($perm) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="fed-admin-card" style="margin-top: 1rem;">
            <div class="fed-admin-card-header">
                <h3 class="fed-admin-card-title">
                    <i class="fa-solid fa-chart-bar"></i>
                    Usage Stats (Last 30 Days)
                </h3>
            </div>
            <div class="fed-admin-card-body">
                <div class="analytics-metric-grid">
                    <div class="analytics-metric">
                        <div class="analytics-metric-value"><?= number_format($usageStats['total_requests'] ?? 0) ?></div>
                        <div class="analytics-metric-label">Total Requests</div>
                    </div>
                    <div class="analytics-metric">
                        <div class="analytics-metric-value"><?= number_format($usageStats['successful'] ?? 0) ?></div>
                        <div class="analytics-metric-label">Successful</div>
                    </div>
                    <div class="analytics-metric">
                        <div class="analytics-metric-value"><?= number_format($usageStats['failed'] ?? 0) ?></div>
                        <div class="analytics-metric-label">Failed</div>
                    </div>
                    <div class="analytics-metric">
                        <div class="analytics-metric-value"><?= number_format($usageStats['rate_limited'] ?? 0) ?></div>
                        <div class="analytics-metric-label">Rate Limited</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function revokeApiKey(id) {
    if (!confirm('Are you sure you want to revoke this API key? This action cannot be undone.')) return;

    fetch('<?= $basePath ?>/admin/federation/api-keys/' + id + '/revoke', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= Csrf::token() ?>'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?= $basePath ?>/admin/federation/api-keys';
        } else {
            alert(data.error || 'Failed to revoke key');
        }
    });
}
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
