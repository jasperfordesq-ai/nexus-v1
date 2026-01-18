<?php
/**
 * Federation API Keys Management
 * Admin interface for managing external partner API keys
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation API Keys';
$adminPageSubtitle = 'External Partner Integration';
$adminPageIcon = 'fa-key';

require __DIR__ . '/../partials/admin-header.php';

// Extract data
$apiKeys = $apiKeys ?? [];
$recentActivity = $recentActivity ?? [];
$newApiKey = $_SESSION['new_api_key'] ?? null;
unset($_SESSION['new_api_key']);
?>

<style>
/* API Keys Admin Styles */
.api-keys-dashboard {
    display: grid;
    gap: 1.5rem;
}

/* New Key Alert */
.new-key-alert {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1));
    border: 2px solid rgba(16, 185, 129, 0.4);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1rem;
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
    transition: all 0.2s;
}

.copy-btn:hover {
    background: rgba(16, 185, 129, 0.3);
}

.copy-btn.copied {
    background: #10b981;
    color: white;
}

.new-key-warning {
    font-size: 0.85rem;
    color: #f59e0b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Header Actions */
.api-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.api-header h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--admin-text, #fff);
    margin: 0;
}

.create-key-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.create-key-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
}

/* Keys Table */
.keys-table-wrapper {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border-radius: 16px;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    overflow: hidden;
}

.keys-table {
    width: 100%;
    border-collapse: collapse;
}

.keys-table th,
.keys-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
}

.keys-table th {
    background: rgba(0, 0, 0, 0.2);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--admin-text-secondary, #94a3b8);
}

.keys-table tr:last-child td {
    border-bottom: none;
}

.keys-table tr:hover {
    background: rgba(139, 92, 246, 0.05);
}

.key-name {
    font-weight: 600;
    color: var(--admin-text, #fff);
}

.key-prefix {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
    color: #8b5cf6;
    background: rgba(139, 92, 246, 0.1);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.key-status {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.key-status.active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.key-status.suspended {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.key-status.revoked {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.key-permissions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.perm-badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
    border-radius: 4px;
}

.key-actions {
    display: flex;
    gap: 0.5rem;
}

.key-action-btn {
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.key-action-btn.view {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.key-action-btn.suspend {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.key-action-btn.activate {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.key-action-btn.revoke {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.key-action-btn:hover {
    transform: translateY(-1px);
}

/* Activity Log */
.activity-section {
    margin-top: 2rem;
}

.activity-section h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--admin-text, #fff);
    margin: 0 0 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.activity-log {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border-radius: 12px;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--admin-border, rgba(255, 255, 255, 0.05));
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-method {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    min-width: 50px;
    text-align: center;
}

.activity-method.GET { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.activity-method.POST { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.activity-method.PUT { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.activity-method.DELETE { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

.activity-endpoint {
    flex: 1;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #94a3b8);
}

.activity-key {
    font-size: 0.8rem;
    color: #8b5cf6;
}

.activity-time {
    font-size: 0.8rem;
    color: var(--admin-text-secondary, #64748b);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--admin-text-secondary, #94a3b8);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    color: var(--admin-text, #fff);
    margin: 0 0 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .api-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .keys-table-wrapper {
        overflow-x: auto;
    }

    .keys-table {
        min-width: 700px;
    }
}
</style>

<div class="api-keys-dashboard">
    <?php if ($newApiKey): ?>
    <div class="new-key-alert">
        <h3><i class="fa-solid fa-circle-check"></i> Your New API Key</h3>
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

    <div class="api-header">
        <h2><i class="fa-solid fa-key"></i> API Keys</h2>
        <a href="/admin/federation/api-keys/create" class="create-key-btn">
            <i class="fa-solid fa-plus"></i> Create New Key
        </a>
    </div>

    <?php if (empty($apiKeys)): ?>
    <div class="keys-table-wrapper">
        <div class="empty-state">
            <i class="fa-solid fa-key"></i>
            <h3>No API Keys Yet</h3>
            <p>Create an API key to allow external partners to integrate with your federation network.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="keys-table-wrapper">
        <table class="keys-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Key Prefix</th>
                    <th>Status</th>
                    <th>Permissions</th>
                    <th>Requests</th>
                    <th>Last Used</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apiKeys as $key): ?>
                <tr>
                    <td>
                        <span class="key-name"><?= htmlspecialchars($key['name']) ?></span>
                        <br>
                        <small style="color: var(--admin-text-secondary);">
                            Created by <?= htmlspecialchars($key['first_name'] . ' ' . $key['last_name']) ?>
                        </small>
                    </td>
                    <td><code class="key-prefix"><?= htmlspecialchars($key['key_prefix']) ?>...</code></td>
                    <td>
                        <span class="key-status <?= $key['status'] ?>">
                            <?php if ($key['status'] === 'active'): ?>
                                <i class="fa-solid fa-circle-check"></i>
                            <?php elseif ($key['status'] === 'suspended'): ?>
                                <i class="fa-solid fa-pause"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-ban"></i>
                            <?php endif; ?>
                            <?= ucfirst($key['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="key-permissions">
                            <?php
                            $perms = json_decode($key['permissions'] ?? '[]', true);
                            foreach ($perms as $perm):
                            ?>
                            <span class="perm-badge"><?= htmlspecialchars($perm) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td><?= number_format($key['request_count_total']) ?></td>
                    <td>
                        <?php if ($key['last_used_at']): ?>
                            <?= date('M j, g:ia', strtotime($key['last_used_at'])) ?>
                        <?php else: ?>
                            <span style="color: var(--admin-text-secondary);">Never</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="key-actions">
                            <a href="/admin/federation/api-keys/<?= $key['id'] ?>" class="key-action-btn view">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <?php if ($key['status'] === 'active'): ?>
                            <form method="POST" action="/admin/federation/api-keys/<?= $key['id'] ?>/suspend" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <button type="submit" class="key-action-btn suspend" onclick="return confirm('Suspend this API key?')">
                                    <i class="fa-solid fa-pause"></i>
                                </button>
                            </form>
                            <?php elseif ($key['status'] === 'suspended'): ?>
                            <form method="POST" action="/admin/federation/api-keys/<?= $key['id'] ?>/activate" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <button type="submit" class="key-action-btn activate">
                                    <i class="fa-solid fa-play"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($key['status'] !== 'revoked'): ?>
                            <form method="POST" action="/admin/federation/api-keys/<?= $key['id'] ?>/revoke" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                                <button type="submit" class="key-action-btn revoke" onclick="return confirm('Permanently revoke this API key? This cannot be undone.')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($recentActivity)): ?>
    <div class="activity-section">
        <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent API Activity</h3>
        <div class="activity-log">
            <?php foreach ($recentActivity as $log): ?>
            <div class="activity-item">
                <span class="activity-method <?= $log['method'] ?>"><?= $log['method'] ?></span>
                <span class="activity-endpoint"><?= htmlspecialchars($log['endpoint']) ?></span>
                <span class="activity-key"><?= htmlspecialchars($log['key_name']) ?></span>
                <span class="activity-time"><?= date('M j, g:ia', strtotime($log['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function copyApiKey() {
    const keyValue = document.getElementById('newKeyValue').textContent.trim();
    navigator.clipboard.writeText(keyValue).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy';
            btn.classList.remove('copied');
        }, 2000);
    });
}
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
