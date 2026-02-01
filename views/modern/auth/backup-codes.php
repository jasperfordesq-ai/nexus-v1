<?php
/**
 * Backup Codes View - Modern Theme
 * Full-screen modal for displaying backup codes after 2FA setup
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Backup Codes</title>
    <link rel="stylesheet" href="/assets/css/totp.min.css">
</head>
<body class="totp-setup-page">

<div class="totp-modal-overlay">
    <div class="totp-modal backup-modal">
        <div class="totp-modal-header backup-header-success">
            <div class="totp-modal-icon">
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <h1 class="totp-modal-title">Two-Factor Authentication Enabled!</h1>
            <p class="totp-modal-subtitle">Save your backup codes before continuing</p>
        </div>

        <div class="totp-modal-body">
            <?php if (!empty($backup_codes)): ?>
                <div class="backup-warning-box">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <strong>Save these codes now!</strong>
                        <p>These codes will only be shown once. If you lose your phone and don't have these codes, you'll be locked out.</p>
                    </div>
                </div>

                <div class="backup-codes-container">
                    <div class="backup-codes-grid-modal">
                        <?php foreach ($backup_codes as $code): ?>
                            <div class="backup-code-item"><?= htmlspecialchars($code) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="backup-actions-row">
                    <button type="button" class="backup-btn backup-btn-secondary" onclick="copyBackupCodes()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        Copy
                    </button>
                    <button type="button" class="backup-btn backup-btn-secondary" onclick="downloadBackupCodes()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Download
                    </button>
                    <button type="button" class="backup-btn backup-btn-secondary" onclick="window.print()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Print
                    </button>
                </div>

            <?php else: ?>
                <div class="backup-info-section">
                    <p class="backup-codes-count">You have <strong><?= $codes_remaining ?></strong> backup codes remaining.</p>
                    <p class="backup-codes-hint">Each code can only be used once. If you're running low, generate new ones.</p>
                </div>

                <form action="<?= $basePath ?>/settings/2fa/regenerate-backup" method="POST" class="backup-regenerate-form">
                    <?= \Nexus\Core\Csrf::input() ?>

                    <div class="backup-warning-box backup-warning-small">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span>Generating new codes will invalidate all existing unused codes.</span>
                    </div>

                    <div class="backup-password-group">
                        <label for="password">Enter your password to confirm:</label>
                        <input type="password" name="password" id="password" required placeholder="Your current password">
                    </div>

                    <button type="submit" class="backup-btn backup-btn-dark">
                        Generate New Backup Codes
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="totp-modal-footer">
            <?php if (!empty($backup_codes)): ?>
                <div class="backup-confirm-section">
                    <label class="backup-checkbox-label">
                        <input type="checkbox" id="backup-confirm-checkbox">
                        <span>I have saved these codes in a safe place</span>
                    </label>
                    <a href="<?= $basePath ?>/dashboard" id="backup-continue-btn" class="backup-btn backup-btn-primary backup-btn-disabled">
                        Continue to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <a href="<?= $basePath ?>/settings/2fa" class="backup-back-link">
                    ← Back to 2FA Settings
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($backup_codes)): ?>
<script>
const backupCodes = <?= json_encode($backup_codes) ?>;

function copyBackupCodes() {
    const text = "Project NEXUS Backup Codes\n" +
                 "Generated: <?= date('Y-m-d H:i') ?>\n\n" +
                 backupCodes.join("\n") +
                 "\n\nEach code can only be used once.";

    navigator.clipboard.writeText(text).then(() => {
        alert('Backup codes copied to clipboard!');
    }).catch(() => {
        alert('Failed to copy. Please manually select and copy the codes.');
    });
}

function downloadBackupCodes() {
    const text = "Project NEXUS Backup Codes\n" +
                 "Generated: <?= date('Y-m-d H:i') ?>\n\n" +
                 backupCodes.join("\n") +
                 "\n\nEach code can only be used once.\n" +
                 "Keep these codes in a safe place!";

    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'nexus-backup-codes.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

document.getElementById('backup-confirm-checkbox').addEventListener('change', function() {
    const btn = document.getElementById('backup-continue-btn');
    if (this.checked) {
        btn.classList.remove('backup-btn-disabled');
    } else {
        btn.classList.add('backup-btn-disabled');
    }
});
</script>
<?php endif; ?>

</body>
</html>
