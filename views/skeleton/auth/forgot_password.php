<?php
/**
 * Skeleton Layout - Forgot Password Page
 * Password reset request
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<div style="max-width: 450px; margin: 3rem auto; padding: 0 1rem;">
    <div class="sk-card">
        <h1 style="text-align: center; margin-bottom: 0.5rem; font-size: 1.75rem;">Reset Password</h1>
        <p style="text-align: center; color: #888; margin-bottom: 2rem;">
            Enter your email and we'll send you a reset link
        </p>

        <?php if (isset($success)): ?>
            <div class="sk-alert sk-alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="sk-alert sk-alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= $basePath ?>/forgot-password" method="POST">
            <?= Csrf::input() ?>

            <div class="sk-form-group">
                <label for="email" class="sk-form-label">Email Address</label>
                <input type="email" id="email" name="email" class="sk-form-input"
                       required autocomplete="email" placeholder="you@example.com"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>">
            </div>

            <button type="submit" class="sk-btn" style="width: 100%; margin-top: 1rem;">
                Send Reset Link
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--sk-border);">
            <a href="<?= $basePath ?>/login" style="color: var(--sk-link); font-size: 0.875rem;">
                &larr; Back to login
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
