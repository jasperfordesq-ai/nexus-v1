<?php
/**
 * External Federation Partners - Show/Edit View
 * View and manage a single external federation partner
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Partner Details';
$adminPageSubtitle = $partner['name'] ?? 'External Partner';
$adminPageIcon = 'fa-globe';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require __DIR__ . '/../partials/admin-header.php';

$partner = $partner ?? [];
$logs = $logs ?? [];

$statusColors = [
    'pending' => ['bg' => 'rgba(245, 158, 11, 0.15)', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
    'active' => ['bg' => 'rgba(16, 185, 129, 0.15)', 'color' => '#10b981', 'icon' => 'fa-circle-check'],
    'suspended' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'color' => '#ef4444', 'icon' => 'fa-pause'],
    'failed' => ['bg' => 'rgba(239, 68, 68, 0.15)', 'color' => '#ef4444', 'icon' => 'fa-triangle-exclamation'],
];
$status = $statusColors[$partner['status']] ?? $statusColors['pending'];
?>

<style>
.partner-detail-page {
    display: grid;
    gap: 1.5rem;
}

/* Status Header */
.status-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 12px;
    padding: 1.25rem;
}

.status-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.status-details {
    color: var(--admin-text-secondary, #94a3b8);
    font-size: 0.9rem;
}

.status-actions {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.action-btn.test {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.action-btn.suspend {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.action-btn.activate {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.action-btn.delete {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.action-btn:hover {
    transform: translateY(-1px);
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

/* Form Section */
.form-section {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 12px;
    padding: 1.5rem;
}

.form-section h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--admin-text, #fff);
    margin: 0 0 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--admin-text, #fff);
    margin-bottom: 0.5rem;
}

.form-hint {
    font-size: 0.8rem;
    color: var(--admin-text-secondary, #94a3b8);
    margin-top: 0.35rem;
}

.form-input,
.form-textarea,
.form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 8px;
    color: var(--admin-text, #fff);
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
}

.form-textarea {
    min-height: 80px;
    resize: vertical;
}

/* Permissions */
.permissions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.permission-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 6px;
}

.permission-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #8b5cf6;
}

.permission-item label {
    font-size: 0.85rem;
    color: var(--admin-text, #fff);
}

/* Logs Section */
.logs-section {
    grid-column: 1 / -1;
}

.logs-table-wrapper {
    overflow-x: auto;
    max-height: 400px;
    overflow-y: auto;
}

.logs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.logs-table th,
.logs-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
}

.logs-table th {
    background: rgba(0, 0, 0, 0.2);
    font-weight: 600;
    color: var(--admin-text-secondary, #94a3b8);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
}

.logs-table tr:hover {
    background: rgba(139, 92, 246, 0.05);
}

.log-method {
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.log-method.GET { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.log-method.POST { background: rgba(16, 185, 129, 0.2); color: #10b981; }

.log-endpoint {
    font-family: monospace;
    font-size: 0.8rem;
    color: var(--admin-text-secondary, #94a3b8);
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.log-status {
    font-weight: 600;
}

.log-status.success { color: #10b981; }
.log-status.error { color: #ef4444; }

.log-time {
    color: var(--admin-text-secondary, #64748b);
    font-size: 0.8rem;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
}

.btn-secondary {
    background: rgba(100, 116, 139, 0.2);
    color: var(--admin-text-secondary, #94a3b8);
}

/* Flash Messages */
.flash-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.flash-message.success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.flash-message.error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Error Display */
.error-display {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.error-display h4 {
    color: #ef4444;
    font-size: 0.9rem;
    margin: 0 0 0.5rem;
}

.error-display p {
    color: var(--admin-text-secondary, #94a3b8);
    font-size: 0.85rem;
    margin: 0;
    font-family: monospace;
}

/* Back Link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--admin-text-secondary, #94a3b8);
    text-decoration: none;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.back-link:hover {
    color: #8b5cf6;
}
</style>

<a href="/admin/federation/external-partners" class="back-link">
    <i class="fa-solid fa-arrow-left"></i> Back to External Partners
</a>

<?php if ($flashSuccess): ?>
<div class="flash-message success">
    <i class="fa-solid fa-circle-check"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="flash-message error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<div class="partner-detail-page">
    <!-- Status Header -->
    <div class="status-header">
        <div class="status-info">
            <span class="status-badge" style="background: <?= $status['bg'] ?>; color: <?= $status['color'] ?>;">
                <i class="fa-solid <?= $status['icon'] ?>"></i>
                <?= ucfirst($partner['status']) ?>
            </span>
            <div class="status-details">
                <?php if ($partner['verified_at']): ?>
                    Last verified: <?= date('M j, Y g:ia', strtotime($partner['verified_at'])) ?>
                <?php else: ?>
                    Never verified - test connection to verify
                <?php endif; ?>
            </div>
        </div>
        <div class="status-actions">
            <form method="POST" action="/admin/federation/external-partners/<?= $partner['id'] ?>/test" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn test">
                    <i class="fa-solid fa-plug"></i> Test Connection
                </button>
            </form>

            <?php if ($partner['status'] === 'active'): ?>
            <form method="POST" action="/admin/federation/external-partners/<?= $partner['id'] ?>/suspend" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn suspend" onclick="return confirm('Suspend this partner?')">
                    <i class="fa-solid fa-pause"></i> Suspend
                </button>
            </form>
            <?php elseif ($partner['status'] === 'suspended'): ?>
            <form method="POST" action="/admin/federation/external-partners/<?= $partner['id'] ?>/activate" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn activate">
                    <i class="fa-solid fa-play"></i> Activate
                </button>
            </form>
            <?php endif; ?>

            <form method="POST" action="/admin/federation/external-partners/<?= $partner['id'] ?>/delete" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn delete" onclick="return confirm('Delete this partner? This cannot be undone.')">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>

    <?php if ($partner['last_error']): ?>
    <div class="error-display">
        <h4><i class="fa-solid fa-triangle-exclamation"></i> Last Error (<?= $partner['error_count'] ?> consecutive failures)</h4>
        <p><?= htmlspecialchars($partner['last_error']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <form method="POST" action="/admin/federation/external-partners/<?= $partner['id'] ?>/update">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">

        <div class="content-grid">
            <!-- Basic Info -->
            <div class="form-section">
                <h3><i class="fa-solid fa-info-circle"></i> Basic Information</h3>

                <div class="form-group">
                    <label class="form-label">Partner Name</label>
                    <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($partner['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea"><?= htmlspecialchars($partner['description'] ?? '') ?></textarea>
                </div>

                <?php if ($partner['partner_name']): ?>
                <div class="form-group">
                    <label class="form-label">Reported Partner Name</label>
                    <input type="text" class="form-input" value="<?= htmlspecialchars($partner['partner_name']) ?>" disabled>
                    <p class="form-hint">Name reported by the partner's API</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Connection Details -->
            <div class="form-section">
                <h3><i class="fa-solid fa-link"></i> Connection Details</h3>

                <div class="form-group">
                    <label class="form-label">Base URL</label>
                    <input type="url" name="base_url" class="form-input" value="<?= htmlspecialchars($partner['base_url']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">API Path</label>
                    <input type="text" name="api_path" class="form-input" value="<?= htmlspecialchars($partner['api_path'] ?? '/api/v1/federation') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Authentication Method</label>
                    <select name="auth_method" class="form-select">
                        <option value="api_key" <?= ($partner['auth_method'] ?? 'api_key') === 'api_key' ? 'selected' : '' ?>>API Key (Bearer Token)</option>
                        <option value="hmac" <?= ($partner['auth_method'] ?? '') === 'hmac' ? 'selected' : '' ?>>HMAC-SHA256 Signing</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">API Key</label>
                    <input type="password" name="api_key" class="form-input" placeholder="Enter new key to change (leave blank to keep current)">
                    <p class="form-hint">Leave blank to keep the current API key</p>
                </div>
            </div>

            <!-- Permissions -->
            <div class="form-section">
                <h3><i class="fa-solid fa-shield-halved"></i> Permissions</h3>

                <div class="permissions-grid">
                    <div class="permission-item">
                        <input type="checkbox" name="allow_member_search" id="permMembers" <?= $partner['allow_member_search'] ? 'checked' : '' ?>>
                        <label for="permMembers">Member Search</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" name="allow_listing_search" id="permListings" <?= $partner['allow_listing_search'] ? 'checked' : '' ?>>
                        <label for="permListings">Listing Search</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" name="allow_messaging" id="permMessaging" <?= $partner['allow_messaging'] ? 'checked' : '' ?>>
                        <label for="permMessaging">Messaging</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" name="allow_transactions" id="permTransactions" <?= $partner['allow_transactions'] ? 'checked' : '' ?>>
                        <label for="permTransactions">Transactions</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" name="allow_events" id="permEvents" <?= $partner['allow_events'] ? 'checked' : '' ?>>
                        <label for="permEvents">Events</label>
                    </div>
                    <div class="permission-item">
                        <input type="checkbox" name="allow_groups" id="permGroups" <?= $partner['allow_groups'] ? 'checked' : '' ?>>
                        <label for="permGroups">Groups</label>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i> Save Changes
                    </button>
                </div>
            </div>

            <!-- Partner Metadata -->
            <div class="form-section">
                <h3><i class="fa-solid fa-server"></i> Partner Information</h3>

                <div class="form-group">
                    <label class="form-label">API Version</label>
                    <input type="text" class="form-input" value="<?= htmlspecialchars($partner['partner_version'] ?? 'Unknown') ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">Member Count</label>
                    <input type="text" class="form-input" value="<?= $partner['partner_member_count'] ? number_format($partner['partner_member_count']) : 'Unknown' ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">Last Sync</label>
                    <input type="text" class="form-input" value="<?= $partner['last_sync_at'] ? date('M j, Y g:ia', strtotime($partner['last_sync_at'])) : 'Never' ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">Created</label>
                    <input type="text" class="form-input" value="<?= date('M j, Y g:ia', strtotime($partner['created_at'])) ?>" disabled>
                </div>
            </div>

            <!-- API Logs -->
            <div class="form-section logs-section">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent API Calls</h3>

                <?php if (empty($logs)): ?>
                <p style="color: var(--admin-text-secondary); text-align: center; padding: 2rem;">
                    No API calls yet. Test the connection to see activity.
                </p>
                <?php else: ?>
                <div class="logs-table-wrapper">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Endpoint</th>
                                <th>Status</th>
                                <th>Time (ms)</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><span class="log-method <?= $log['method'] ?>"><?= $log['method'] ?></span></td>
                                <td class="log-endpoint" title="<?= htmlspecialchars($log['endpoint']) ?>"><?= htmlspecialchars($log['endpoint']) ?></td>
                                <td>
                                    <?php if ($log['success']): ?>
                                    <span class="log-status success"><?= $log['response_code'] ?> OK</span>
                                    <?php else: ?>
                                    <span class="log-status error"><?= $log['response_code'] ?: 'Error' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $log['response_time_ms'] ?? '-' ?></td>
                                <td class="log-time"><?= date('M j, g:ia', strtotime($log['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
