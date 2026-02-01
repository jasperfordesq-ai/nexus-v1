<?php
/**
 * TOTP Setup View - Modern Theme
 * Full-screen modal overlay for 2FA setup
 */

$layout = \Nexus\Services\LayoutHelper::get();
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Minimal header - just basic HTML structure, no navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Two-Factor Authentication</title>
    <link rel="stylesheet" href="/assets/css/totp.min.css">
</head>
<body class="totp-setup-page">

<div class="totp-modal-overlay">
    <div class="totp-modal">
        <div class="totp-modal-header">
            <div class="totp-modal-icon">
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h1 class="totp-modal-title">Set Up Two-Factor Authentication</h1>
            <p class="totp-modal-subtitle">Protect your account with an extra layer of security</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="totp-error">
                <?= htmlspecialchars(urldecode($error)) ?>
            </div>
        <?php endif; ?>

        <div class="totp-modal-body">
            <div class="totp-steps-grid">
                <!-- Step 1: Download App -->
                <div class="totp-step-card">
                    <div class="totp-step-badge">1</div>
                    <h3>Get an Authenticator App</h3>
                    <p>Download one of these free apps:</p>
                    <div class="totp-app-list">
                        <span class="totp-app-chip">Google Authenticator</span>
                        <span class="totp-app-chip">Authy</span>
                        <span class="totp-app-chip">Microsoft Authenticator</span>
                    </div>
                </div>

                <!-- Step 2: QR Code -->
                <div class="totp-step-card totp-qr-card">
                    <div class="totp-step-badge">2</div>
                    <h3>Scan This QR Code</h3>
                    <?php if (!empty($qr_code)): ?>
                        <div class="totp-qr-wrapper">
                            <?= $qr_code ?>
                        </div>
                        <details class="totp-manual-details">
                            <summary>Can't scan? Enter manually</summary>
                            <code class="totp-secret-display"><?= htmlspecialchars($secret) ?></code>
                        </details>
                    <?php else: ?>
                        <div class="totp-error">
                            QR code failed to generate.
                            <a href="<?= $basePath ?>/auth/2fa/setup?refresh=1">Try again</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Step 3: Verify -->
                <div class="totp-step-card totp-verify-card">
                    <div class="totp-step-badge">3</div>
                    <h3>Enter Verification Code</h3>
                    <p>Enter the 6-digit code from your app:</p>
                    <form action="<?= $basePath ?>/auth/2fa/setup" method="POST" id="totp-setup-form">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="text"
                               name="code"
                               id="totp-code"
                               class="totp-code-field"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               autocomplete="one-time-code"
                               inputmode="numeric"
                               placeholder="000000"
                               autofocus
                               required>
                        <button type="submit" class="totp-verify-btn">
                            Verify &amp; Enable 2FA
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="totp-modal-footer">
            <div class="totp-info-banner">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Two-factor authentication is <strong>mandatory</strong> for all accounts to ensure platform security.</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('totp-code');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }
});
</script>

</body>
</html>
