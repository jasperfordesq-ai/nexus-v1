<?php
/**
 * Skeleton Layout - Login Page
 * User authentication
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<div style="max-width: 450px; margin: 3rem auto; padding: 0 1rem;">
    <div class="sk-card">
        <h1 style="text-align: center; margin-bottom: 0.5rem; font-size: 1.75rem;">Welcome Back</h1>
        <p style="text-align: center; color: #888; margin-bottom: 2rem;">Sign in to your account</p>

        <?php if (isset($_GET['registered'])): ?>
            <div class="sk-alert sk-alert-success">
                Registration successful! Please sign in.
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="sk-alert sk-alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= $basePath ?>/login" method="POST">
            <?= Csrf::input() ?>

            <div class="sk-form-group">
                <label for="email" class="sk-form-label">Email Address</label>
                <input type="email" id="email" name="email" class="sk-form-input"
                       required autocomplete="email" placeholder="you@example.com">
            </div>

            <div class="sk-form-group">
                <label for="password" class="sk-form-label">Password</label>
                <input type="password" id="password" name="password" class="sk-form-input"
                       required autocomplete="current-password" placeholder="Enter your password">
            </div>

            <div class="sk-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="remember" value="1">
                    <span style="font-size: 0.875rem;">Remember me</span>
                </label>
                <a href="<?= $basePath ?>/forgot-password" style="font-size: 0.875rem; color: var(--sk-link);">
                    Forgot password?
                </a>
            </div>

            <button type="submit" class="sk-btn" style="width: 100%; margin-top: 1rem;">
                Sign In
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--sk-border);">
            <p style="color: #888; font-size: 0.875rem;">
                Don't have an account?
                <a href="<?= $basePath ?>/register" style="color: var(--sk-link); font-weight: 600;">Sign up</a>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
