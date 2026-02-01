<?php
/**
 * TOTP Verification View - Modern Theme
 * Full-screen modal for 2FA verification during login
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication</title>
    <link rel="stylesheet" href="/assets/css/totp.min.css">
</head>
<body class="totp-setup-page">

<div class="totp-modal-overlay">
    <div class="totp-modal verify-modal">
        <div class="totp-modal-header">
            <div class="totp-modal-icon">
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h1 class="totp-modal-title">Verify Your Identity</h1>
            <p class="totp-modal-subtitle">Enter your authentication code to continue</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="verify-error-banner">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?= htmlspecialchars(urldecode($error)) ?></span>
            </div>
        <?php endif; ?>

        <div class="totp-modal-body">
            <form action="<?= $basePath ?>/auth/2fa" method="POST" id="totp-form" class="verify-form">
                <?= \Nexus\Core\Csrf::input() ?>

                <div class="verify-code-section">
                    <label class="verify-label">Enter 6-digit code from your authenticator app</label>
                    <input type="text"
                           name="code"
                           id="totp-code"
                           class="verify-code-input"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           autocomplete="one-time-code"
                           inputmode="numeric"
                           placeholder="000000"
                           autofocus
                           required>
                    <button type="submit" class="verify-submit-btn">
                        Verify Code
                    </button>
                </div>
            </form>

            <div class="verify-divider">
                <span>or use a backup code</span>
            </div>

            <form action="<?= $basePath ?>/auth/2fa" method="POST" id="backup-form" class="verify-backup-section">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="use_backup_code" value="1">

                <p class="verify-backup-hint">Lost access to your authenticator app?</p>
                <div class="verify-backup-row">
                    <input type="text"
                           name="code"
                           class="verify-backup-input"
                           placeholder="XXXX-XXXX"
                           maxlength="9"
                           pattern="[A-Z0-9\-]{8,9}">
                    <button type="submit" class="verify-backup-btn">
                        Use Backup Code
                    </button>
                </div>
            </form>
        </div>

        <div class="totp-modal-footer">
            <a href="<?= $basePath ?>/login" class="verify-cancel-link">
                ← Cancel and return to login
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('totp-code');

    codeInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '');

        // Auto-submit when 6 digits entered
        if (this.value.length === 6) {
            document.getElementById('totp-form').submit();
        }
    });
});
</script>

</body>
</html>
