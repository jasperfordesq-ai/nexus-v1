<?php
/**
 * External Federation Partners - Show/Edit View
 * View and manage a single external federation partner
 *
 * Styles: /httpdocs/assets/css/admin-legacy/federation-external-partners.css
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

<div class="federation-partners-page">

<a href="/admin-legacy/federation/external-partners" class="back-link">
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
            <form method="POST" action="/admin-legacy/federation/external-partners/<?= $partner['id'] ?>/test" class="action-form-inline">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn test">
                    <i class="fa-solid fa-plug"></i> Test Connection
                </button>
            </form>

            <?php if ($partner['status'] === 'active'): ?>
            <form method="POST" action="/admin-legacy/federation/external-partners/<?= $partner['id'] ?>/suspend" class="action-form-inline">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn suspend" onclick="return confirm('Suspend this partner?')">
                    <i class="fa-solid fa-pause"></i> Suspend
                </button>
            </form>
            <?php elseif ($partner['status'] === 'suspended'): ?>
            <form method="POST" action="/admin-legacy/federation/external-partners/<?= $partner['id'] ?>/activate" class="action-form-inline">
                <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
                <button type="submit" class="action-btn activate">
                    <i class="fa-solid fa-play"></i> Activate
                </button>
            </form>
            <?php endif; ?>

            <form method="POST" action="/admin-legacy/federation/external-partners/<?= $partner['id'] ?>/delete" class="action-form-inline">
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
    <form method="POST" action="/admin-legacy/federation/external-partners/<?= $partner['id'] ?>/update">
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
                    <p class="form-hint">Choose how to authenticate with the external server</p>
                </div>

                <div class="form-group">
                    <label class="form-label">API Key</label>
                    <input type="password" name="api_key" class="form-input" placeholder="Enter new key to change (leave blank to keep current)">
                    <p class="form-hint">Leave blank to keep the current API key - used for authentication</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Signing Secret</label>
                    <input type="password" name="signing_secret" class="form-input" placeholder="Enter new secret to change (leave blank to keep current)">
                    <p class="form-hint">Leave blank to keep the current signing secret - used for HMAC request signatures</p>
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

                <div class="form-save-wrapper">
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
                <p class="empty-log-message">
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

</div><!-- /.federation-partners-page -->

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
