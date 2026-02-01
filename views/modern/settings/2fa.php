<?php
/**
 * 2FA Settings View - Modern Theme
 * Manage two-factor authentication settings
 * Note: 2FA is mandatory and cannot be disabled
 */

$layout = \Nexus\Services\LayoutHelper::get();

if ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/header.php';
} else {
    $hero_title = "Security Settings";
    $hero_subtitle = "Manage your two-factor authentication.";
    $hero_gradient = 'htb-hero-gradient-brand';
    require dirname(__DIR__) . '/../layouts/modern/header.php';
}

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="settings-wrapper">
    <div class="settings-container">

        <div class="settings-sidebar">
            <a href="<?= $basePath ?>/settings" class="settings-back-link">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Settings
            </a>
        </div>

        <div class="settings-content">
            <div class="htb-card">
                <div class="settings-card-body">

                    <h2 class="settings-title">Two-Factor Authentication</h2>

                    <?php if (!empty($flash_success)): ?>
                        <div class="settings-alert settings-alert-success">
                            <?= htmlspecialchars($flash_success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($flash_error)): ?>
                        <div class="settings-alert settings-alert-error">
                            <?= htmlspecialchars($flash_error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_enabled): ?>
                        <div class="tfa-status tfa-status-enabled">
                            <div class="tfa-status-icon">
                                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <div class="tfa-status-text">
                                <strong>2FA is enabled</strong>
                                <p>Your account is protected with two-factor authentication.</p>
                            </div>
                        </div>

                        <div class="tfa-backup-status">
                            <h3>Backup Codes</h3>
                            <p>You have <strong><?= $backup_codes_remaining ?></strong> backup codes remaining.</p>

                            <?php if ($backup_codes_remaining <= 3): ?>
                                <div class="tfa-warning">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    You're running low on backup codes. Consider generating new ones.
                                </div>
                            <?php endif; ?>

                            <a href="<?= $basePath ?>/auth/2fa/backup-codes" class="tfa-action-btn tfa-action-secondary">
                                View / Regenerate Backup Codes
                            </a>
                        </div>

                        <div class="tfa-trusted-devices">
                            <h3>Trusted Devices</h3>
                            <p>These devices can skip 2FA verification for 30 days.</p>

                            <?php if (!empty($trusted_devices)): ?>
                                <div class="tfa-devices-list">
                                    <?php foreach ($trusted_devices as $device): ?>
                                        <div class="tfa-device-item">
                                            <div class="tfa-device-info">
                                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                </svg>
                                                <div>
                                                    <strong><?= htmlspecialchars($device['device_name']) ?></strong>
                                                    <span class="tfa-device-meta">
                                                        Added <?= date('M j, Y', strtotime($device['trusted_at'])) ?>
                                                        <?php if ($device['last_used_at']): ?>
                                                            · Last used <?= date('M j, Y', strtotime($device['last_used_at'])) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <form action="<?= $basePath ?>/settings/2fa/devices/revoke" method="POST" class="tfa-device-actions">
                                                <?= \Nexus\Core\Csrf::input() ?>
                                                <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                                <button type="submit" class="tfa-device-revoke-btn" onclick="return confirm('Remove this device from trusted devices?')">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <?php if (count($trusted_devices) > 1): ?>
                                    <form action="<?= $basePath ?>/settings/2fa/devices/revoke-all" method="POST" class="tfa-revoke-all">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <button type="submit" class="tfa-action-btn tfa-action-danger-outline" onclick="return confirm('Remove all trusted devices? You will need to verify 2FA on next login.')">
                                            Remove All Trusted Devices
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="tfa-no-devices">No trusted devices. When you log in and check "Remember this device", it will appear here.</p>
                            <?php endif; ?>
                        </div>

                        <div class="tfa-mandatory-notice">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Two-factor authentication is <strong>mandatory</strong> for all accounts and cannot be disabled.</span>
                        </div>

                    <?php else: ?>
                        <div class="tfa-status tfa-status-disabled">
                            <div class="tfa-status-icon">
                                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <div class="tfa-status-text">
                                <strong>2FA setup required</strong>
                                <p>You must set up two-factor authentication to continue using the platform.</p>
                            </div>
                        </div>

                        <a href="<?= $basePath ?>/auth/2fa/setup" class="tfa-action-btn tfa-action-primary">
                            Set Up Two-Factor Authentication
                        </a>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-wrapper {
    max-width: 900px;
    margin: 0 auto;
    padding: 30px 20px;
}

.settings-container {
    display: flex;
    gap: 30px;
}

.settings-sidebar {
    width: 200px;
    flex-shrink: 0;
}

.settings-back-link {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-gray-600, #64748b);
    text-decoration: none;
    font-weight: 500;
    padding: 10px 0;
}

.settings-back-link:hover {
    color: var(--color-primary-500, #6366f1);
}

.settings-content {
    flex: 1;
}

.settings-card-body {
    padding: 30px;
}

.settings-title {
    margin: 0 0 25px 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-gray-900, #0f172a);
}

.settings-alert {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 500;
}

.settings-alert-success {
    background: var(--color-success-50, #f0fdf4);
    color: var(--color-success-700, #15803d);
    border: 1px solid var(--color-success-200, #bbf7d0);
}

.settings-alert-error {
    background: var(--color-danger-50, #fef2f2);
    color: var(--color-danger-700, #b91c1c);
    border: 1px solid var(--color-danger-200, #fecaca);
}

.tfa-status {
    display: flex;
    gap: 16px;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.tfa-status-enabled {
    background: var(--color-success-50, #f0fdf4);
    border: 1px solid var(--color-success-200, #bbf7d0);
}

.tfa-status-disabled {
    background: var(--color-warning-50, #fffbeb);
    border: 1px solid var(--color-warning-200, #fde68a);
}

.tfa-status-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.tfa-status-enabled .tfa-status-icon {
    background: var(--color-success-500, #22c55e);
    color: white;
}

.tfa-status-disabled .tfa-status-icon {
    background: var(--color-warning-500, #f59e0b);
    color: white;
}

.tfa-status-text strong {
    display: block;
    font-size: 1.1rem;
    margin-bottom: 4px;
}

.tfa-status-text p {
    margin: 0;
    color: var(--color-gray-600, #64748b);
}

.tfa-backup-status {
    background: var(--color-gray-50, #f8fafc);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid var(--color-gray-200, #e2e8f0);
}

.tfa-backup-status h3 {
    margin: 0 0 10px 0;
    font-size: 1rem;
    color: var(--color-gray-800, #1e293b);
}

.tfa-backup-status p {
    margin: 0 0 15px 0;
    color: var(--color-gray-600, #64748b);
}

.tfa-backup-status strong {
    color: var(--color-primary-500, #6366f1);
    font-size: 1.2rem;
}

.tfa-warning {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--color-warning-100, #fef3c7);
    color: var(--color-warning-800, #92400e);
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

.tfa-action-btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.tfa-action-primary {
    background: linear-gradient(135deg, var(--color-primary-500, #6366f1), var(--color-primary-600, #4f46e5));
    color: white;
}

.tfa-action-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.tfa-action-secondary {
    background: var(--color-gray-700, #334155);
    color: white;
}

.tfa-action-secondary:hover {
    background: var(--color-gray-800, #1e293b);
}

.tfa-mandatory-notice {
    display: flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    padding: 16px 20px;
    border-radius: 12px;
    color: var(--color-primary-700, #4338ca);
    font-size: 0.9rem;
    margin-top: 20px;
}

.tfa-mandatory-notice svg {
    flex-shrink: 0;
    color: var(--color-primary-500, #6366f1);
}

.tfa-trusted-devices {
    background: var(--color-gray-50, #f8fafc);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    border: 1px solid var(--color-gray-200, #e2e8f0);
}

.tfa-trusted-devices h3 {
    margin: 0 0 10px 0;
    font-size: 1rem;
    color: var(--color-gray-800, #1e293b);
}

.tfa-trusted-devices > p {
    margin: 0 0 15px 0;
    color: var(--color-gray-600, #64748b);
    font-size: 0.9rem;
}

.tfa-devices-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
}

.tfa-device-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: white;
    padding: 14px;
    border-radius: 8px;
    border: 1px solid var(--color-gray-200, #e2e8f0);
}

.tfa-device-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.tfa-device-info svg {
    color: var(--color-gray-500, #64748b);
    flex-shrink: 0;
}

.tfa-device-info strong {
    display: block;
    color: var(--color-gray-800, #1e293b);
    font-size: 0.95rem;
}

.tfa-device-meta {
    display: block;
    color: var(--color-gray-500, #64748b);
    font-size: 0.8rem;
    margin-top: 2px;
}

.tfa-device-revoke-btn {
    padding: 6px 14px;
    background: transparent;
    color: var(--color-danger-600, #dc2626);
    border: 1px solid var(--color-danger-300, #fca5a5);
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.tfa-device-revoke-btn:hover {
    background: var(--color-danger-50, #fef2f2);
    border-color: var(--color-danger-500, #ef4444);
}

.tfa-revoke-all {
    margin-top: 10px;
}

.tfa-action-danger-outline {
    background: transparent;
    color: var(--color-danger-600, #dc2626);
    border: 1px solid var(--color-danger-300, #fca5a5);
    padding: 10px 20px;
    font-size: 0.9rem;
}

.tfa-action-danger-outline:hover {
    background: var(--color-danger-50, #fef2f2);
    border-color: var(--color-danger-500, #ef4444);
}

.tfa-no-devices {
    color: var(--color-gray-500, #64748b);
    font-style: italic;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .settings-container {
        flex-direction: column;
    }

    .settings-sidebar {
        width: 100%;
    }
}
</style>

<?php
if ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/modern/footer.php';
}
?>
