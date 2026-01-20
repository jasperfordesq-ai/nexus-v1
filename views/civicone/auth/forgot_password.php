<?php
// Consolidated Forgot Password View
// Adapts to Modern, Social, and CivicOne layouts.

$layout = \Nexus\Services\LayoutHelper::get(); // Get current layout

// 1. Header Selection
if ($layout === 'civicone') {
    require __DIR__ . '/../../layouts/civicone/header.php';
    echo '<div class="civicone-wrapper" style="padding-top: 40px;">';
} else {
    // Modern Defaults
    $hero_title = "Reset Password";
    $hero_subtitle = "Recover access to your account.";
    $hero_gradient = 'htb-hero-gradient-brand';
    $hero_type = 'Authentication';
    require dirname(__DIR__) . '/../layouts/civicone/header.php';
}
?>

<div class="auth-wrapper">
    <div class="htb-card auth-card">
        <div class="auth-card-body">

            <div style="text-align: center; margin-bottom: 25px; color: var(--htb-text-main);">
                Enter your email address below and we'll send you a link to reset your password.
            </div>

            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/email" method="POST" class="auth-form">
                <?= Nexus\Core\Csrf::input() ?>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" placeholder="e.g. alice@example.com" required class="form-input">
                </div>

                <button type="submit" class="htb-btn htb-btn-primary auth-submit-btn" style="opacity: 1; pointer-events: auto;">Send Reset Link</button>
            </form>

            <div class="auth-login-link">
                Remember your password? <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login">Login here</a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Scoped Refactor Styles */
    .auth-wrapper {
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        padding: 0 15px;
        box-sizing: border-box;
    }

    .auth-card {
        margin-top: 0;
        position: relative;
        z-index: 10;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .auth-card-body {
        padding: 50px;
    }

    /* Desktop spacing for no-hero layout */
    @media (min-width: 601px) {
        .auth-wrapper {
            padding-top: 140px;
        }
    }

    .form-group {
        margin-bottom: 20px;
        width: 100%;
    }

    .form-label {
        display: block;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--htb-text-main, #1f2937);
        font-size: 1rem;
    }

    .form-input {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        padding: 12px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: #4f46e5 !important;
    }

    .auth-submit-btn {
        width: 100%;
        font-size: 1.1rem;
        padding: 14px;
        background: var(--htb-gradient-brand, linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%));
        border: none;
        color: white;
        border-radius: 8px;
        margin-top: 10px;
    }

    .auth-login-link {
        margin-top: 25px;
        text-align: center;
        font-size: 0.95rem;
        color: var(--htb-text-muted, #6b7280);
    }

    .auth-login-link a {
        color: #4f46e5;
        font-weight: 600;
        text-decoration: none;
    }

    /* Mobile Responsiveness */
    @media (max-width: 600px) {
        .auth-wrapper {
            padding-top: 120px;
            padding-left: 10px;
            padding-right: 10px;
        }

        .auth-card-body {
            padding: 25px !important;
        }
    }

    /* Reset negative margin for Social/Civic layouts */
    

    /* ========================================
       DARK MODE FOR FORGOT PASSWORD
       ======================================== */

    [data-theme="dark"] .auth-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
    }

    [data-theme="dark"] .auth-card-body div[style*="color: var(--htb-text-main)"] {
        color: #e2e8f0 !important;
    }

    [data-theme="dark"] .form-label {
        color: #e2e8f0;
    }

    [data-theme="dark"] .form-input {
        background: rgba(15, 23, 42, 0.6);
        border-color: rgba(255, 255, 255, 0.15);
        color: #f1f5f9;
    }

    [data-theme="dark"] .form-input::placeholder {
        color: #64748b;
    }

    [data-theme="dark"] .form-input:focus {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }

    [data-theme="dark"] .auth-login-link {
        color: #94a3b8;
    }

    [data-theme="dark"] .auth-login-link a {
        color: #818cf8;
    }
</style>

<?php
// Close Wrappers & Include Footer
if ($layout === 'civicone') {
    echo '</div>'; // Close civicone-wrapper
    require __DIR__ . '/../../layouts/civicone/footer.php';
} else {
    require dirname(__DIR__) . '/../layouts/civicone/footer.php';
}
?>