<?php
// Force Modern Layout
// FIXED: Use consistent session variable order (active_layout first)
$layout = layout(); // Fixed: centralized detection
$modernView = __DIR__ . '/../modern/admin-legacy/native-app.php';

if (file_exists($modernView)) {
    require $modernView;
    return;
}

// Fallback - inline view
require __DIR__ . '/../layouts/modern/header.php';
?>

<style>
    .native-app-admin {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    .native-app-admin h1 {
        margin-bottom: 8px;
    }
    .native-app-admin .subtitle {
        color: var(--htb-text-muted, #6b7280);
        margin-bottom: 24px;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: var(--htb-card-bg, white);
        border-radius: 12px;
        padding: 20px;
        border: 1px solid var(--htb-border, #e5e7eb);
    }
    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 12px;
    }
    .stat-card-icon.green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .stat-card-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .stat-card-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
    .stat-card-icon.orange { background: rgba(249, 115, 22, 0.1); color: #f97316; }
    .stat-card-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--htb-text-main, #1f2937);
        line-height: 1;
        margin-bottom: 4px;
    }
    .stat-card-label {
        font-size: 0.9rem;
        color: var(--htb-text-muted, #6b7280);
    }
    .admin-section {
        background: var(--htb-card-bg, white);
        border-radius: 12px;
        border: 1px solid var(--htb-border, #e5e7eb);
        margin-bottom: 24px;
        overflow: hidden;
    }
    .admin-section-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--htb-border, #e5e7eb);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .admin-section-header h2 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
    }
    .admin-section-body {
        padding: 20px;
    }
    .config-warning {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: #fef3c7;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    .config-warning i {
        color: #d97706;
        margin-top: 2px;
    }
    .config-warning-text {
        color: #92400e;
        font-size: 0.9rem;
        line-height: 1.5;
    }
    .config-warning-text code {
        background: rgba(0,0,0,0.1);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.85em;
    }
    .test-form {
        display: grid;
        gap: 16px;
    }
    .test-form label {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--htb-text-main, #374151);
        margin-bottom: 4px;
        display: block;
    }
    .test-form input, .test-form textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--htb-border, #d1d5db);
        border-radius: 8px;
        font-size: 0.95rem;
        background: var(--htb-bg, white);
        color: var(--htb-text-main, #1f2937);
    }
    .test-form textarea {
        min-height: 80px;
        resize: vertical;
    }
    .test-form button {
        padding: 12px 24px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .test-form button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .test-result {
        margin-top: 12px;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.9rem;
    }
    .test-result.success {
        background: #d1fae5;
        color: #065f46;
    }
    .test-result.error {
        background: #fee2e2;
        color: #991b1b;
    }
    .devices-table {
        width: 100%;
        border-collapse: collapse;
    }
    .devices-table th, .devices-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--htb-border, #e5e7eb);
    }
    .devices-table th {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--htb-text-muted, #6b7280);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .devices-table td {
        font-size: 0.9rem;
    }
    .platform-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .platform-badge.android {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }
    .platform-badge.ios {
        background: rgba(99, 102, 241, 0.1);
        color: #6366f1;
    }
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--htb-text-muted, #6b7280);
    }
    .empty-state i {
        font-size: 3rem;
        opacity: 0.3;
        margin-bottom: 12px;
    }
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--htb-text-muted, #6b7280);
        text-decoration: none;
        font-size: 0.9rem;
        margin-bottom: 16px;
    }
    .back-link:hover {
        color: var(--htb-text-main, #374151);
    }
</style>

<div class="native-app-admin">
    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Admin
    </a>

    <h1>Native App Management</h1>
    <p class="subtitle">Manage Android app push notifications and view device statistics</p>

    <?php if (!$fcmConfigured): ?>
    <div class="config-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div class="config-warning-text">
            <strong>Firebase not configured.</strong> To enable native push notifications, download your Firebase service account JSON file and place it in one of these locations:
            <br><code>firebase-service-account.json</code> (project root)
            <br><code>config/firebase-service-account.json</code>
            <br><br>Or specify the path in <code>.env</code>: <code>FCM_SERVICE_ACCOUNT_PATH=path/to/file.json</code>
            <br><br>See <code>capacitor/FIREBASE_SETUP.md</code> for complete setup instructions.
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon green">
                <i class="fa-brands fa-android"></i>
            </div>
            <div class="stat-card-value"><?= number_format($stats['total_devices']) ?></div>
            <div class="stat-card-label">Native App Devices</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon blue">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="stat-card-value"><?= number_format($stats['unique_users']) ?></div>
            <div class="stat-card-label">Unique Users (Native)</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon purple">
                <i class="fa-solid fa-globe"></i>
            </div>
            <div class="stat-card-value"><?= number_format($pwaStats['total_subscriptions']) ?></div>
            <div class="stat-card-label">PWA Subscriptions</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon orange">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div class="stat-card-value"><?= number_format($stats['unique_users'] + $pwaStats['unique_users']) ?></div>
            <div class="stat-card-label">Total Push-Enabled Users</div>
        </div>
    </div>

    <!-- Test Notification -->
    <div class="admin-section">
        <div class="admin-section-header">
            <i class="fa-solid fa-paper-plane" style="color: #10b981;"></i>
            <h2>Send Test Notification</h2>
        </div>
        <div class="admin-section-body">
            <form class="test-form" id="testPushForm">
                <input type="hidden" name="csrf_token" value="<?= \Nexus\Core\Csrf::token() ?>">
                <div>
                    <label for="pushTitle">Title</label>
                    <input type="text" id="pushTitle" name="title" value="Test Notification" required>
                </div>
                <div>
                    <label for="pushBody">Message</label>
                    <textarea id="pushBody" name="body" required>This is a test notification from the admin panel.</textarea>
                </div>
                <div>
                    <button type="submit" id="sendTestBtn" <?= !$fcmConfigured ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-paper-plane"></i>
                        Send to All Native App Users
                    </button>
                </div>
                <div id="testResult"></div>
            </form>
        </div>
    </div>

    <!-- Recent Devices -->
    <div class="admin-section">
        <div class="admin-section-header">
            <i class="fa-solid fa-mobile-screen" style="color: #6366f1;"></i>
            <h2>Recent Device Registrations</h2>
        </div>
        <div class="admin-section-body" style="padding: 0;">
            <?php if (empty($stats['recent_registrations'])): ?>
            <div class="empty-state">
                <i class="fa-solid fa-mobile-screen-button"></i>
                <p>No devices registered yet.</p>
                <p style="font-size: 0.85rem;">Devices will appear here when users install and open the native app.</p>
            </div>
            <?php else: ?>
            <table class="devices-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Platform</th>
                        <th>Registered</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recent_registrations'] as $device): ?>
                    <tr>
                        <td>
                            <?php if ($device['first_name']): ?>
                                <?= htmlspecialchars($device['first_name'] . ' ' . $device['last_name']) ?>
                                <br><small style="color: var(--htb-text-muted);"><?= htmlspecialchars($device['email']) ?></small>
                            <?php else: ?>
                                <span style="color: var(--htb-text-muted);">User #<?= $device['user_id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="platform-badge <?= $device['platform'] ?>">
                                <i class="fa-brands fa-<?= $device['platform'] === 'ios' ? 'apple' : 'android' ?>"></i>
                                <?= ucfirst($device['platform']) ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($device['created_at'])) ?></td>
                        <td><?= date('M j, Y g:ia', strtotime($device['updated_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Security: HTML escape function to prevent XSS
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

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

        const response = await fetch('<?= \Nexus\Core\TenantContext::getBasePath() ?>/admin-legacy/native-app/test-push', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            resultDiv.innerHTML = '<div class="test-result success"><i class="fa-solid fa-check"></i> ' + escapeHtml(result.message) + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="test-result error"><i class="fa-solid fa-xmark"></i> ' + escapeHtml(result.message) + '</div>';
        }
    } catch (error) {
        resultDiv.innerHTML = '<div class="test-result error"><i class="fa-solid fa-xmark"></i> Network error: ' + escapeHtml(error.message) + '</div>';
    }

    btn.disabled = false;
    btn.innerHTML = originalText;
});
</script>

<?php require __DIR__ . '/../layouts/modern/footer.php'; ?>
