<?php
/**
 * API Key Details View
 * Detailed view of a specific API key with usage stats and logs
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'API Key Details';
$adminPageSubtitle = $apiKey['name'] ?? 'Unknown Key';
$adminPageIcon = 'fa-key';

require __DIR__ . '/../partials/admin-header.php';

$apiKey = $apiKey ?? [];
$stats = $stats ?? [];
$logs = $logs ?? [];
$newApiKey = $_SESSION['new_api_key'] ?? null;
unset($_SESSION['new_api_key']);
?>

<style>
.key-detail-page {
    display: grid;
    gap: 1.5rem;
}

/* New Key Alert */
.new-key-alert {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1));
    border: 2px solid rgba(16, 185, 129, 0.4);
    border-radius: 16px;
    padding: 1.5rem;
}

.new-key-alert h3 {
    color: #10b981;
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.new-key-value {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 8px;
    padding: 1rem;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.9rem;
    color: #10b981;
    word-break: break-all;
    margin: 0.75rem 0;
    position: relative;
}

.copy-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    padding: 0.4rem 0.8rem;
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.4);
    border-radius: 6px;
    color: #10b981;
    font-size: 0.8rem;
    cursor: pointer;
}

.new-key-warning {
    font-size: 0.85rem;
    color: #f59e0b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Back Link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--admin-text-secondary, #94a3b8);
    text-decoration: none;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.back-link:hover {
    color: #8b5cf6;
}

/* Key Info Card */
.key-info-card {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border-radius: 16px;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    padding: 1.5rem;
}

.key-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
}

.key-title h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--admin-text, #fff);
    margin: 0 0 0.5rem;
}

.key-prefix-display {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 1rem;
    color: #8b5cf6;
    background: rgba(139, 92, 246, 0.1);
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    display: inline-block;
}

.key-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
}

.key-status-badge.active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.key-status-badge.suspended {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.key-status-badge.revoked {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-item {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #8b5cf6;
}

.stat-label {
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #94a3b8);
    margin-top: 0.25rem;
}

/* Permissions Section */
.permissions-section {
    margin-bottom: 1.5rem;
}

.permissions-section h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--admin-text, #fff);
    margin: 0 0 0.75rem;
}

.perm-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.perm-tag {
    padding: 0.35rem 0.75rem;
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Meta Info */
.meta-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
}

.meta-item {
    font-size: 0.9rem;
}

.meta-item strong {
    display: block;
    color: var(--admin-text-secondary, #64748b);
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.meta-item span {
    color: var(--admin-text, #fff);
}

/* Actions */
.key-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
}

.action-btn {
    padding: 0.6rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    border: none;
}

.action-btn.regenerate {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.action-btn.suspend {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.action-btn.activate {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.action-btn.revoke {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.action-btn:hover {
    transform: translateY(-1px);
}

/* Logs Section */
.logs-section {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border-radius: 16px;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    overflow: hidden;
}

.logs-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
}

.logs-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--admin-text, #fff);
}

.logs-list {
    max-height: 500px;
    overflow-y: auto;
}

.log-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1.5rem;
    border-bottom: 1px solid var(--admin-border, rgba(255, 255, 255, 0.05));
}

.log-item:last-child {
    border-bottom: none;
}

.log-method {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    min-width: 50px;
    text-align: center;
}

.log-method.GET { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.log-method.POST { background: rgba(16, 185, 129, 0.2); color: #10b981; }

.log-endpoint {
    flex: 1;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #94a3b8);
}

.log-ip {
    font-size: 0.8rem;
    color: var(--admin-text-secondary, #64748b);
}

.log-time {
    font-size: 0.8rem;
    color: var(--admin-text-secondary, #64748b);
}

.empty-logs {
    padding: 3rem;
    text-align: center;
    color: var(--admin-text-secondary, #64748b);
}
</style>

<div class="key-detail-page">
    <a href="/admin/federation/api-keys" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to API Keys
    </a>

    <?php if ($newApiKey): ?>
    <div class="new-key-alert">
        <h3><i class="fa-solid fa-circle-check"></i> Regenerated API Key</h3>
        <div class="new-key-value" id="newKeyValue">
            <?= htmlspecialchars($newApiKey) ?>
            <button class="copy-btn" onclick="copyApiKey()">
                <i class="fa-solid fa-copy"></i> Copy
            </button>
        </div>
        <p class="new-key-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            This key will only be shown once. Make sure to copy and store it securely!
        </p>
    </div>
    <?php endif; ?>

    <div class="key-info-card">
        <div class="key-header">
            <div class="key-title">
                <h2><?= htmlspecialchars($apiKey['name']) ?></h2>
                <code class="key-prefix-display"><?= htmlspecialchars($apiKey['key_prefix']) ?>...</code>
            </div>
            <span class="key-status-badge <?= $apiKey['status'] ?>">
                <?php if ($apiKey['status'] === 'active'): ?>
                    <i class="fa-solid fa-circle-check"></i> Active
                <?php elseif ($apiKey['status'] === 'suspended'): ?>
                    <i class="fa-solid fa-pause"></i> Suspended
                <?php else: ?>
                    <i class="fa-solid fa-ban"></i> Revoked
                <?php endif; ?>
            </span>
        </div>

        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?= number_format($stats['total_requests'] ?? 0) ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($stats['active_days'] ?? 0) ?></div>
                <div class="stat-label">Active Days</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($apiKey['rate_limit'] ?? 1000) ?></div>
                <div class="stat-label">Rate Limit/hr</div>
            </div>
        </div>

        <div class="permissions-section">
            <h3>Permissions</h3>
            <div class="perm-list">
                <?php
                $perms = json_decode($apiKey['permissions'] ?? '[]', true);
                foreach ($perms as $perm):
                ?>
                <span class="perm-tag"><?= htmlspecialchars($perm) ?></span>
                <?php endforeach; ?>
                <?php if (empty($perms)): ?>
                <span style="color: var(--admin-text-secondary);">No permissions assigned</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="meta-info">
            <div class="meta-item">
                <strong>Created</strong>
                <span><?= date('F j, Y \a\t g:ia', strtotime($apiKey['created_at'])) ?></span>
            </div>
            <div class="meta-item">
                <strong>Created By</strong>
                <span><?= htmlspecialchars($apiKey['first_name'] . ' ' . $apiKey['last_name']) ?></span>
            </div>
            <div class="meta-item">
                <strong>Last Used</strong>
                <span><?= $stats['last_request'] ? date('F j, Y \a\t g:ia', strtotime($stats['last_request'])) : 'Never' ?></span>
            </div>
            <div class="meta-item">
                <strong>Expires</strong>
                <span><?= $apiKey['expires_at'] ? date('F j, Y', strtotime($apiKey['expires_at'])) : 'Never' ?></span>
            </div>
        </div>

        <?php if ($apiKey['status'] !== 'revoked'): ?>
        <div class="key-actions">
            <form method="POST" action="/admin/federation/api-keys/<?= $apiKey['id'] ?>/regenerate" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn regenerate" onclick="return confirm('Regenerate this API key? The old key will stop working immediately.')">
                    <i class="fa-solid fa-rotate"></i> Regenerate Key
                </button>
            </form>

            <?php if ($apiKey['status'] === 'active'): ?>
            <form method="POST" action="/admin/federation/api-keys/<?= $apiKey['id'] ?>/suspend" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn suspend">
                    <i class="fa-solid fa-pause"></i> Suspend
                </button>
            </form>
            <?php else: ?>
            <form method="POST" action="/admin/federation/api-keys/<?= $apiKey['id'] ?>/activate" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn activate">
                    <i class="fa-solid fa-play"></i> Reactivate
                </button>
            </form>
            <?php endif; ?>

            <form method="POST" action="/admin/federation/api-keys/<?= $apiKey['id'] ?>/revoke" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn revoke" onclick="return confirm('Permanently revoke this API key? This cannot be undone.')">
                    <i class="fa-solid fa-trash"></i> Revoke
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="logs-section">
        <div class="logs-header">
            <h3><i class="fa-solid fa-list"></i> Request Log (Last 100)</h3>
        </div>
        <div class="logs-list">
            <?php if (empty($logs)): ?>
            <div class="empty-logs">
                <i class="fa-solid fa-inbox"></i>
                <p>No API requests recorded yet</p>
            </div>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <div class="log-item">
                <span class="log-method <?= $log['method'] ?>"><?= $log['method'] ?></span>
                <span class="log-endpoint"><?= htmlspecialchars($log['endpoint']) ?></span>
                <span class="log-ip"><?= htmlspecialchars($log['ip_address']) ?></span>
                <span class="log-time"><?= date('M j, g:ia', strtotime($log['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyApiKey() {
    const keyValue = document.getElementById('newKeyValue').textContent.trim();
    navigator.clipboard.writeText(keyValue).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        setTimeout(() => {
            btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy';
        }, 2000);
    });
}
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
