<?php
/**
 * Native App Management - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Native App';
$adminPageSubtitle = 'System';
$adminPageIcon = 'fa-mobile-screen';

// Include standalone admin header
require __DIR__ . '/partials/admin-header.php';

// Safe defaults
$stats = $stats ?? ['total_devices' => 0, 'unique_users' => 0, 'recent_registrations' => []];
$pwaStats = $pwaStats ?? ['total_subscriptions' => 0, 'unique_users' => 0];
$fcmConfigured = $fcmConfigured ?? false;
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-mobile-screen"></i>
            Native App Management
        </h1>
        <p class="admin-page-subtitle">Manage push notifications and view device statistics</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/settings#notifications" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-gear"></i> Settings
        </a>
    </div>
</div>

<?php if (!$fcmConfigured): ?>
<!-- Firebase Configuration Warning -->
<div class="admin-glass-card config-warning-card" style="max-width: 1200px;">
    <div class="config-warning-content">
        <div class="config-warning-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="config-warning-text">
            <strong>Firebase not configured</strong>
            <p>To enable native push notifications, download your Firebase service account JSON file and place it in one of these locations:</p>
            <div class="config-code-block">
                <code>firebase-service-account.json</code> (project root)<br>
                <code>config/firebase-service-account.json</code>
            </div>
            <p>Or specify the path in <code>.env</code>:</p>
            <code>FCM_SERVICE_ACCOUNT_PATH=path/to/file.json</code>
            <p class="config-hint">See <code>capacitor/FIREBASE_SETUP.md</code> for complete setup instructions.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="native-stats-grid" style="max-width: 1200px;">
    <div class="admin-glass-card stat-card-native">
        <div class="stat-card-icon stat-icon-android">
            <i class="fa-brands fa-android"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= number_format($stats['total_devices']) ?></div>
            <div class="stat-card-label">Native App Devices</div>
        </div>
        <div class="stat-card-decoration">
            <i class="fa-solid fa-mobile-screen"></i>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native">
        <div class="stat-card-icon stat-icon-users">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= number_format($stats['unique_users']) ?></div>
            <div class="stat-card-label">Unique Users (Native)</div>
        </div>
        <div class="stat-card-decoration">
            <i class="fa-solid fa-user-check"></i>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native">
        <div class="stat-card-icon stat-icon-pwa">
            <i class="fa-solid fa-globe"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= number_format($pwaStats['total_subscriptions']) ?></div>
            <div class="stat-card-label">PWA Subscriptions</div>
        </div>
        <div class="stat-card-decoration">
            <i class="fa-solid fa-browser"></i>
        </div>
    </div>

    <div class="admin-glass-card stat-card-native stat-card-highlight">
        <div class="stat-card-icon stat-icon-total">
            <i class="fa-solid fa-bell"></i>
        </div>
        <div class="stat-card-content">
            <div class="stat-card-value"><?= number_format($stats['unique_users'] + $pwaStats['unique_users']) ?></div>
            <div class="stat-card-label">Total Push-Enabled Users</div>
        </div>
        <div class="stat-card-decoration">
            <i class="fa-solid fa-chart-line"></i>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div class="native-content-grid" style="max-width: 1200px;">
    <!-- Send Test Notification Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Send Test Notification</h3>
                <p class="admin-card-subtitle">Send a push notification to native app users</p>
            </div>
        </div>
        <div class="admin-card-body">
            <form id="testPushForm">
                <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text"
                           id="pushTitle"
                           name="title"
                           class="form-control"
                           value="Test Notification"
                           required
                           placeholder="Notification title">
                </div>

                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea id="pushBody"
                              name="body"
                              class="form-control"
                              rows="3"
                              required
                              placeholder="Enter your notification message...">This is a test notification from the admin panel.</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Target Audience</label>
                    <div class="audience-selector">
                        <label class="audience-option">
                            <input type="radio" name="target" value="all" checked>
                            <div class="audience-option-content">
                                <i class="fa-solid fa-users"></i>
                                <span>All Native Users</span>
                            </div>
                        </label>
                        <label class="audience-option">
                            <input type="radio" name="target" value="android">
                            <div class="audience-option-content">
                                <i class="fa-brands fa-android"></i>
                                <span>Android Only</span>
                            </div>
                        </label>
                        <label class="audience-option">
                            <input type="radio" name="target" value="ios">
                            <div class="audience-option-content">
                                <i class="fa-brands fa-apple"></i>
                                <span>iOS Only</span>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit"
                        id="sendTestBtn"
                        class="admin-btn admin-btn-primary admin-btn-lg admin-btn-block"
                        <?= !$fcmConfigured ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-paper-plane"></i>
                    Send Test Notification
                </button>

                <div id="testResult"></div>
            </form>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Quick Actions</h3>
                <p class="admin-card-subtitle">Common tasks and system status</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="quick-actions-list">
                <a href="<?= $basePath ?>/admin/settings#notifications" class="quick-action-item">
                    <div class="quick-action-icon quick-action-settings">
                        <i class="fa-solid fa-gear"></i>
                    </div>
                    <div class="quick-action-text">
                        <strong>Notification Settings</strong>
                        <span>Configure push notification preferences</span>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>

                <a href="<?= $basePath ?>/admin/newsletters" class="quick-action-item">
                    <div class="quick-action-icon quick-action-newsletter">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div class="quick-action-text">
                        <strong>Newsletter Center</strong>
                        <span>Send mass communications</span>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>

                <div class="quick-action-item quick-action-status">
                    <div class="quick-action-icon quick-action-firebase">
                        <i class="fa-solid fa-fire"></i>
                    </div>
                    <div class="quick-action-text">
                        <strong>Firebase Status</strong>
                        <span class="<?= $fcmConfigured ? 'status-connected' : 'status-disconnected' ?>">
                            <?= $fcmConfigured ? 'Connected' : 'Not Configured' ?>
                        </span>
                    </div>
                    <div class="status-indicator <?= $fcmConfigured ? 'status-active' : 'status-inactive' ?>"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Devices Card -->
<div class="admin-glass-card" style="max-width: 1200px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <i class="fa-solid fa-mobile-screen"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Recent Device Registrations</h3>
            <p class="admin-card-subtitle">Latest native app device activity</p>
        </div>
        <span class="device-count-badge"><?= count($stats['recent_registrations'] ?? []) ?> devices</span>
    </div>

    <?php if (empty($stats['recent_registrations'])): ?>
    <div class="admin-card-body">
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <h3>No devices registered yet</h3>
            <p>Devices will appear here when users install and open the native app.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="devices-table-wrapper">
        <table class="devices-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Platform</th>
                    <th>Registered</th>
                    <th>Last Active</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['recent_registrations'] as $device): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar">
                                <?= strtoupper(substr($device['first_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="user-info">
                                <?php if ($device['first_name']): ?>
                                    <strong><?= htmlspecialchars($device['first_name'] . ' ' . $device['last_name']) ?></strong>
                                    <span><?= htmlspecialchars($device['email']) ?></span>
                                <?php else: ?>
                                    <strong>User #<?= $device['user_id'] ?></strong>
                                    <span>No profile</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="platform-badge platform-<?= $device['platform'] ?>">
                            <i class="fa-brands fa-<?= $device['platform'] === 'ios' ? 'apple' : 'android' ?>"></i>
                            <?= ucfirst($device['platform']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="date-cell">
                            <?= date('M j, Y', strtotime($device['created_at'])) ?>
                        </span>
                    </td>
                    <td>
                        <span class="date-cell">
                            <?= date('M j, Y', strtotime($device['updated_at'])) ?>
                            <small><?= date('g:i A', strtotime($device['updated_at'])) ?></small>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-badge-active">
                            <i class="fa-solid fa-circle"></i> Active
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
/* Page Header Extension */
.admin-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-page-header-content {
    flex: 1;
}

.admin-page-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.25rem;
}

.admin-page-title i {
    color: #a5b4fc;
}

.admin-page-subtitle {
    margin: 0;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.6);
}

.admin-page-header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Config Warning Card */
.config-warning-card {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%) !important;
    border-color: rgba(251, 191, 36, 0.3) !important;
}

.config-warning-content {
    display: flex;
    gap: 1.25rem;
    padding: 0.5rem;
}

.config-warning-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.config-warning-icon i {
    font-size: 1.5rem;
    color: white;
}

.config-warning-text {
    flex: 1;
    color: #fcd34d;
}

.config-warning-text strong {
    display: block;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.config-warning-text p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
    opacity: 0.9;
}

.config-warning-text code {
    background: rgba(0, 0, 0, 0.3);
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-family: 'Monaco', 'Menlo', monospace;
}

.config-code-block {
    background: rgba(0, 0, 0, 0.2);
    padding: 12px 16px;
    border-radius: 8px;
    margin: 0.75rem 0;
    line-height: 1.8;
}

.config-code-block code {
    background: none;
    padding: 0;
}

.config-hint {
    opacity: 0.7;
    font-size: 0.85rem !important;
}

/* Stats Grid */
.native-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card-native {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card-native:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.stat-card-highlight {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%) !important;
    border-color: rgba(99, 102, 241, 0.3) !important;
}

.stat-card-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-icon-android {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(22, 163, 74, 0.2));
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.stat-icon-users {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.2));
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #93c5fd;
}

.stat-icon-pwa {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.2));
    border: 1px solid rgba(139, 92, 246, 0.3);
    color: #c4b5fd;
}

.stat-icon-total {
    background: linear-gradient(135deg, rgba(251, 146, 60, 0.2), rgba(249, 115, 22, 0.2));
    border: 1px solid rgba(251, 146, 60, 0.3);
    color: #fdba74;
}

.stat-card-content {
    flex: 1;
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-card-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.stat-card-decoration {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.15);
}

/* Content Grid */
.native-content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Form Styles */
.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.75rem;
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

/* Audience Selector */
.audience-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}

.audience-option {
    cursor: pointer;
}

.audience-option input {
    display: none;
}

.audience-option-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    transition: all 0.2s;
}

.audience-option-content i {
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.5);
}

.audience-option-content span {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
}

.audience-option input:checked + .audience-option-content {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.5);
}

.audience-option input:checked + .audience-option-content i {
    color: #a5b4fc;
}

.audience-option input:checked + .audience-option-content span {
    color: #fff;
}

/* Button Extensions */
.admin-btn-lg {
    padding: 0.9rem 1.5rem;
    font-size: 1rem;
}

.admin-btn-block {
    width: 100%;
    justify-content: center;
}

/* Test Result */
#testResult {
    margin-top: 1rem;
}

#testResult .test-result {
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#testResult .test-result.success {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

#testResult .test-result.error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}

/* Quick Actions */
.quick-actions-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quick-action-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.75rem;
    text-decoration: none;
    transition: all 0.2s;
}

.quick-action-item:not(.quick-action-status):hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.2);
}

.quick-action-status {
    cursor: default;
}

.quick-action-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.quick-action-settings {
    background: linear-gradient(135deg, rgba(107, 114, 128, 0.2), rgba(75, 85, 99, 0.2));
    border: 1px solid rgba(107, 114, 128, 0.3);
    color: #9ca3af;
}

.quick-action-newsletter {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

.quick-action-firebase {
    background: linear-gradient(135deg, rgba(251, 146, 60, 0.2), rgba(249, 115, 22, 0.2));
    border: 1px solid rgba(251, 146, 60, 0.3);
    color: #fdba74;
}

.quick-action-text {
    flex: 1;
}

.quick-action-text strong {
    display: block;
    color: #fff;
    font-size: 0.95rem;
    margin-bottom: 0.15rem;
}

.quick-action-text span {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.quick-action-text .status-connected {
    color: #86efac;
}

.quick-action-text .status-disconnected {
    color: #fca5a5;
}

.quick-action-arrow {
    color: rgba(255, 255, 255, 0.3);
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.status-indicator.status-active {
    background: #22c55e;
    box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
}

.status-indicator.status-inactive {
    background: #ef4444;
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
}

/* Device Count Badge */
.device-count-badge {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.05);
    padding: 0.35rem 0.85rem;
    border-radius: 20px;
    margin-left: auto;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
}

.empty-state-icon i {
    font-size: 2rem;
    color: rgba(99, 102, 241, 0.5);
}

.empty-state h3 {
    margin: 0 0 0.5rem;
    color: #fff;
    font-size: 1.1rem;
}

.empty-state p {
    margin: 0;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
    max-width: 400px;
    margin: 0 auto;
}

/* Devices Table */
.devices-table-wrapper {
    overflow-x: auto;
}

.devices-table {
    width: 100%;
    border-collapse: collapse;
}

.devices-table th,
.devices-table td {
    padding: 1rem 1.5rem;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.devices-table th {
    font-weight: 600;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: rgba(0, 0, 0, 0.2);
}

.devices-table tbody tr {
    transition: background 0.2s;
}

.devices-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.devices-table tbody tr:last-child td {
    border-bottom: none;
}

/* User Cell */
.user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1rem;
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.user-info strong {
    color: #fff;
    font-size: 0.95rem;
}

.user-info span {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
}

/* Platform Badge */
.platform-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
}

.platform-android {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #86efac;
}

.platform-ios {
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #a5b4fc;
}

/* Date Cell */
.date-cell {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.date-cell small {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.8rem;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-badge-active {
    background: rgba(34, 197, 94, 0.15);
    color: #86efac;
}

.status-badge-active i {
    font-size: 6px;
}

/* Responsive */
@media (max-width: 1200px) {
    .native-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 1024px) {
    .native-content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .admin-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .native-stats-grid {
        grid-template-columns: 1fr;
    }

    .audience-selector {
        grid-template-columns: 1fr;
    }

    .config-warning-content {
        flex-direction: column;
    }

    .devices-table th,
    .devices-table td {
        padding: 0.75rem 1rem;
    }
}
</style>

<script>
document.getElementById('testPushForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('sendTestBtn');
    const resultDiv = document.getElementById('testResult');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
    resultDiv.innerHTML = '';

    try {
        const formData = new FormData(this);

        const response = await fetch('<?= $basePath ?>/admin/native-app/test-push', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            resultDiv.innerHTML = '<div class="test-result success"><i class="fa-solid fa-check-circle"></i> ' + result.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="test-result error"><i class="fa-solid fa-times-circle"></i> ' + result.message + '</div>';
        }
    } catch (error) {
        resultDiv.innerHTML = '<div class="test-result error"><i class="fa-solid fa-times-circle"></i> Network error: ' + error.message + '</div>';
    }

    btn.disabled = false;
    btn.innerHTML = originalText;
});
</script>

<?php require __DIR__ . '/partials/admin-footer.php'; ?>
