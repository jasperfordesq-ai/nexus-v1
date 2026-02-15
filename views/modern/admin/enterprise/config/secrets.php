<?php
/**
 * Modern Secrets Management - Gold Standard v2.0
 * Dark Mode Optimized Vault & Secrets Interface
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Secrets Management';
$adminPageSubtitle = 'Enterprise';
$adminPageIcon = 'fa-vault';

// Navigation context for enterprise nav
$currentSection = 'config';
$currentPage = 'secrets';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Extract vault status
$vaultConnected = $vaultStatus['vault_available'] ?? $vaultStatus['connected'] ?? false;
$vaultEnabled = $vaultStatus['vault_enabled'] ?? false;
$vaultAddress = $vaultStatus['address'] ?? getenv('VAULT_ADDR') ?: '';

// Default secrets for demo if none provided
if (empty($secrets)) {
    $secrets = [
        ['key' => 'DB_PASSWORD', 'category' => 'database', 'required' => true, 'updated_at' => date('Y-m-d H:i:s', strtotime('-5 days')), 'updated_by' => 'admin'],
        ['key' => 'REDIS_PASSWORD', 'category' => 'cache', 'required' => false, 'updated_at' => date('Y-m-d H:i:s', strtotime('-10 days')), 'updated_by' => 'admin'],
        ['key' => 'APP_KEY', 'category' => 'application', 'required' => true, 'updated_at' => date('Y-m-d H:i:s', strtotime('-30 days')), 'updated_by' => 'system'],
        ['key' => 'JWT_SECRET', 'category' => 'authentication', 'required' => true, 'updated_at' => date('Y-m-d H:i:s', strtotime('-15 days')), 'updated_by' => 'admin'],
        ['key' => 'MAIL_PASSWORD', 'category' => 'email', 'required' => false, 'updated_at' => date('Y-m-d H:i:s', strtotime('-7 days')), 'updated_by' => 'admin'],
        ['key' => 'AWS_SECRET_KEY', 'category' => 'cloud', 'required' => false, 'updated_at' => date('Y-m-d H:i:s', strtotime('-20 days')), 'updated_by' => 'admin'],
        ['key' => 'STRIPE_SECRET_KEY', 'category' => 'payment', 'required' => false, 'updated_at' => date('Y-m-d H:i:s', strtotime('-3 days')), 'updated_by' => 'admin'],
    ];
}

function getSecretsCategoryBadgeClass($category) {
    return [
        'database' => 'primary',
        'authentication' => 'danger',
        'application' => 'info',
        'email' => 'warning',
        'cloud' => 'success',
        'payment' => 'purple',
        'cache' => 'cyan',
    ][$category] ?? 'default';
}
?>

<style>
.secrets-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
}

[data-theme="light"] .secrets-bg {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.secrets-container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 24px;
    position: relative;
    z-index: 1;
}

/* Breadcrumb */
.breadcrumb-nav {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: #94a3b8;
    margin-bottom: 24px;
}

[data-theme="light"] .breadcrumb-nav {
    color: #64748b;
}

.breadcrumb-nav a {
    color: #6366f1;
    text-decoration: none;
}

/* Vault Status Banner */
.vault-status {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
}

[data-theme="light"] .vault-status {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.vault-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
}

.vault-icon.connected { background: linear-gradient(135deg, #10b981, #34d399); }
.vault-icon.disconnected { background: linear-gradient(135deg, #f59e0b, #fbbf24); }

.vault-info {
    flex: 1;
}

.vault-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #f1f5f9;
    margin-bottom: 4px;
}

[data-theme="light"] .vault-title {
    color: #1e293b;
}

.vault-subtitle {
    font-size: 0.875rem;
    color: #94a3b8;
}

[data-theme="light"] .vault-subtitle {
    color: #64748b;
}

.vault-subtitle code {
    background: rgba(99, 102, 241, 0.1);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
}

/* Secrets Grid */
.secrets-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 24px;
}

@media (max-width: 1024px) {
    .secrets-grid {
        grid-template-columns: 1fr;
    }
}

/* Cards */
.secrets-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    overflow: hidden;
}

[data-theme="light"] .secrets-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.secrets-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

[data-theme="light"] .secrets-card-header {
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.secrets-card-header h3 {
    font-size: 1rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="light"] .secrets-card-header h3 {
    color: #1e293b;
}

.secrets-card-header h3 i {
    color: #6366f1;
}

.secrets-card-body {
    padding: 0;
}

/* Table */
.secrets-table {
    width: 100%;
    border-collapse: collapse;
}

.secrets-table th {
    padding: 14px 20px;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: rgba(99, 102, 241, 0.05);
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
}

[data-theme="light"] .secrets-table th {
    color: #64748b;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.secrets-table td {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.05);
    color: #f1f5f9;
    font-size: 0.9rem;
}

[data-theme="light"] .secrets-table td {
    color: #1e293b;
}

.secrets-table tr:hover td {
    background: rgba(99, 102, 241, 0.05);
}

.secret-key {
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: 0.85rem;
    color: #6366f1;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-required {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    margin-left: 8px;
}

.badge-primary { background: rgba(99, 102, 241, 0.15); color: #6366f1; }
.badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.badge-info { background: rgba(6, 182, 212, 0.15); color: #06b6d4; }
.badge-purple { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
.badge-cyan { background: rgba(34, 211, 238, 0.15); color: #22d3ee; }
.badge-default { background: rgba(99, 102, 241, 0.1); color: #94a3b8; }

[data-theme="light"] .badge-default {
    color: #64748b;
}

/* Action Buttons */
.action-btns {
    display: flex;
    gap: 6px;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.8rem;
}

[data-theme="light"] .action-btn {
    color: #1e293b;
}

.action-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    transform: translateY(-1px);
}

.action-btn.danger:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Sidebar */
.sidebar-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 20px;
}

[data-theme="light"] .sidebar-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.sidebar-header {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    font-size: 0.875rem;
    font-weight: 700;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="light"] .sidebar-header {
    color: #1e293b;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.sidebar-header i {
    color: #6366f1;
}

.category-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.category-item {
    padding: 12px 20px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #f1f5f9;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
}

[data-theme="light"] .category-item {
    color: #1e293b;
}

.category-item:hover {
    background: rgba(99, 102, 241, 0.05);
}

.category-item:last-child {
    border-bottom: none;
}

.category-count {
    background: rgba(99, 102, 241, 0.15);
    color: #6366f1;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Security Tips */
.tips-list {
    padding: 16px 20px;
}

.tip-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.05);
    font-size: 0.85rem;
    color: #94a3b8;
}

[data-theme="light"] .tip-item {
    color: #64748b;
}

.tip-item:last-child {
    border-bottom: none;
}

.tip-item i {
    color: #10b981;
    margin-top: 2px;
}

/* Cyber Buttons */
.cyber-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.cyber-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

.cyber-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
}

.cyber-btn-outline {
    background: transparent;
    color: #f1f5f9;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

[data-theme="light"] .cyber-btn-outline {
    color: #1e293b;
    border: 1px solid rgba(99, 102, 241, 0.15);
}

/* Search */
.search-box {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    width: 220px;
}

[data-theme="light"] .search-box {
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.search-box input {
    flex: 1;
    background: none;
    border: none;
    outline: none;
    color: #f1f5f9;
    font-size: 0.85rem;
}

[data-theme="light"] .search-box input {
    color: #1e293b;
}

.search-box input::placeholder {
    color: #94a3b8;
}

[data-theme="light"] .search-box input::placeholder {
    color: #64748b;
}

.search-box i {
    color: #94a3b8;
    font-size: 0.85rem;
}

[data-theme="light"] .search-box i {
    color: #64748b;
}

.info-box {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.info-box-icon {
    color: #f59e0b;
    font-size: 1.25rem;
    margin-top: 2px;
}

.info-box-title {
    color: #f1f5f9;
    display: block;
    margin-bottom: 4px;
}

[data-theme="light"] .info-box-title {
    color: #1e293b;
}

.info-box-text {
    color: #94a3b8;
    font-size: 0.9rem;
}

[data-theme="light"] .info-box-text {
    color: #64748b;
}

/* Responsive */
@media (max-width: 768px) {
    .secrets-container {
        padding: 16px;
    }

    .vault-status {
        flex-direction: column;
        text-align: center;
    }

    .secrets-table {
        font-size: 0.8rem;
    }

    .secrets-table th:nth-child(3),
    .secrets-table td:nth-child(3),
    .secrets-table th:nth-child(4),
    .secrets-table td:nth-child(4) {
        display: none;
    }
}
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-vault"></i>
            Secrets Management
        </h1>
        <p class="admin-page-subtitle">HashiCorp Vault integration & environment secrets</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/config" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Configuration
        </a>
        <button class="admin-btn admin-btn-primary" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i> Add Secret
        </button>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<div class="secrets-bg"></div>

<div class="secrets-container">
    <!-- Vault Status -->
    <div class="vault-status">
        <div class="vault-icon <?= $vaultConnected ? 'connected' : 'disconnected' ?>">
            <i class="fa-solid fa-vault"></i>
        </div>
        <div class="vault-info">
            <div class="vault-title">
                HashiCorp Vault: <?= $vaultConnected ? 'Connected' : ($vaultEnabled ? 'Disconnected' : 'Disabled') ?>
            </div>
            <div class="vault-subtitle">
                <?php if ($vaultConnected): ?>
                    Server at <code><?= htmlspecialchars($vaultAddress) ?></code>
                <?php elseif ($vaultEnabled): ?>
                    Vault is enabled but not connected. Check credentials.
                <?php else: ?>
                    Secrets are managed via environment variables. Set <code>USE_VAULT=true</code> to enable Vault integration.
                <?php endif; ?>
            </div>
        </div>
        <?php if ($vaultConnected): ?>
        <button class="cyber-btn cyber-btn-outline" onclick="testVaultConnection()">
            <i class="fa-solid fa-sync"></i>
            Test Connection
        </button>
        <?php else: ?>
        <a href="https://developer.hashicorp.com/vault/docs" target="_blank" class="cyber-btn cyber-btn-outline">
            <i class="fa-solid fa-external-link"></i>
            Setup Guide
        </a>
        <?php endif; ?>
    </div>

    <?php if (!$vaultConnected): ?>
    <!-- Info box when Vault is not connected -->
    <div class="info-box">
        <i class="fa-solid fa-info-circle info-box-icon"></i>
        <div>
            <strong class="info-box-title">Environment Variables Mode</strong>
            <span class="info-box-text">
                Without Vault, secrets are read from environment variables (e.g., <code>.env</code> file).
                The table below shows common secrets your application may use. To manage these, edit your server's environment configuration directly.
            </span>
        </div>
    </div>
    <?php endif; ?>

    <div class="secrets-grid">
        <!-- Main Content -->
        <div>
            <div class="secrets-card">
                <div class="secrets-card-header">
                    <h3><i class="fa-solid fa-key"></i> Secrets</h3>
                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" placeholder="Filter secrets..." id="secretFilter" onkeyup="filterSecrets()">
                        </div>
                        <button class="cyber-btn cyber-btn-primary" onclick="openAddModal()">
                            <i class="fa-solid fa-plus"></i>
                            Add Secret
                        </button>
                    </div>
                </div>
                <div class="secrets-card-body">
                    <table class="secrets-table" id="secretsTable">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Category</th>
                                <th>Last Updated</th>
                                <th>Updated By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($secrets as $secret): ?>
                            <tr data-key="<?= htmlspecialchars(strtolower($secret['key'])) ?>">
                                <td>
                                    <span class="secret-key"><?= htmlspecialchars($secret['key']) ?></span>
                                    <?php if ($secret['required'] ?? false): ?>
                                        <span class="badge badge-required">Required</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getSecretsCategoryBadgeClass($secret['category'] ?? 'general') ?>">
                                        <?= ucfirst($secret['category'] ?? 'general') ?>
                                    </span>
                                </td>
                                <td><?= $secret['updated_at'] ? date('M j, Y', strtotime($secret['updated_at'])) : 'Never' ?></td>
                                <td><?= htmlspecialchars($secret['updated_by'] ?? '-') ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn" onclick="viewSecret('<?= $secret['key'] ?>')" title="View">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button class="action-btn" onclick="rotateSecret('<?= $secret['key'] ?>')" title="Rotate">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                        <?php if (!($secret['required'] ?? false)): ?>
                                        <button class="action-btn danger" onclick="deleteSecret('<?= $secret['key'] ?>')" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Categories -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <i class="fa-solid fa-folder"></i>
                    Categories
                </div>
                <ul class="category-list">
                    <li class="category-item" onclick="filterByCategory('')">
                        <span>All Secrets</span>
                        <span class="category-count"><?= count($secrets) ?></span>
                    </li>
                    <?php
                    $categories = ['database', 'authentication', 'application', 'email', 'cloud', 'payment', 'cache'];
                    foreach ($categories as $cat):
                        $count = count(array_filter($secrets, fn($s) => ($s['category'] ?? '') === $cat));
                        if ($count > 0):
                    ?>
                    <li class="category-item" onclick="filterByCategory('<?= $cat ?>')">
                        <span><?= ucfirst($cat) ?></span>
                        <span class="category-count"><?= $count ?></span>
                    </li>
                    <?php endif; endforeach; ?>
                </ul>
            </div>

            <!-- Security Tips -->
            <div class="sidebar-card">
                <div class="sidebar-header">
                    <i class="fa-solid fa-lightbulb"></i>
                    Security Tips
                </div>
                <div class="tips-list">
                    <div class="tip-item">
                        <i class="fa-solid fa-check"></i>
                        <span>Rotate secrets every 90 days</span>
                    </div>
                    <div class="tip-item">
                        <i class="fa-solid fa-check"></i>
                        <span>Use strong, random values</span>
                    </div>
                    <div class="tip-item">
                        <i class="fa-solid fa-check"></i>
                        <span>Never share via email/chat</span>
                    </div>
                    <div class="tip-item">
                        <i class="fa-solid fa-check"></i>
                        <span>Enable Vault in production</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function filterSecrets() {
    const filter = document.getElementById('secretFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#secretsTable tbody tr');
    rows.forEach(row => {
        const key = row.dataset.key || '';
        row.style.display = key.includes(filter) ? '' : 'none';
    });
}

function filterByCategory(category) {
    const rows = document.querySelectorAll('#secretsTable tbody tr');
    rows.forEach(row => {
        if (!category) {
            row.style.display = '';
        } else {
            const badge = row.querySelector('.badge:not(.badge-required)');
            const rowCategory = badge?.textContent.trim().toLowerCase() || '';
            row.style.display = rowCategory === category ? '' : 'none';
        }
    });
}

function viewSecret(key) {
    fetch('<?= $basePath ?>/admin-legacy/enterprise/config/secrets/' + key + '/value', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            alert('Secret value for ' + key + ':\n\n' + (data.value || '(empty)'));
        })
        .catch(() => alert('Failed to retrieve secret'));
}

function rotateSecret(key) {
    if (confirm('Generate a new value for ' + key + '?\n\nThis will invalidate the current value immediately.')) {
        fetch('<?= $basePath ?>/admin-legacy/enterprise/config/secrets/' + key + '/rotate', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Secret rotated!\n\nNew value:\n' + data.value);
                    location.reload();
                } else {
                    alert('Failed: ' + (data.error || 'Unknown error'));
                }
            });
    }
}

function deleteSecret(key) {
    if (confirm('Delete secret ' + key + '?\n\nThis cannot be undone.')) {
        fetch('<?= $basePath ?>/admin-legacy/enterprise/config/secrets/' + key, { method: 'DELETE' })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Failed to delete');
            });
    }
}

function testVaultConnection() {
    fetch('<?= $basePath ?>/admin-legacy/enterprise/config/vault/test')
        .then(r => r.json())
        .then(data => {
            if (data.connected) {
                alert('Vault connection successful!\n\nServer: ' + data.address);
            } else {
                alert('Connection failed: ' + (data.error || 'Unknown'));
            }
        });
}

function openAddModal() {
    alert('Add Secret modal coming soon.');
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
