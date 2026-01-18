<?php
/**
 * Skeleton Layout - Registration Page
 * New user signup
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<div style="max-width: 500px; margin: 3rem auto; padding: 0 1rem;">
    <div class="sk-card">
        <h1 style="text-align: center; margin-bottom: 0.5rem; font-size: 1.75rem;">Create Account</h1>
        <p style="text-align: center; color: #888; margin-bottom: 2rem;">Join our community today</p>

        <?php if (!empty($errors)): ?>
            <div class="sk-alert sk-alert-error">
                <strong>Please fix the following errors:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= $basePath ?>/register" method="POST">
            <?= Csrf::input() ?>

            <div class="sk-form-group">
                <label for="name" class="sk-form-label">Full Name *</label>
                <input type="text" id="name" name="name" class="sk-form-input"
                       required autocomplete="name" placeholder="John Doe"
                       value="<?= htmlspecialchars($old['name'] ?? '') ?>">
            </div>

            <div class="sk-form-group">
                <label for="email" class="sk-form-label">Email Address *</label>
                <input type="email" id="email" name="email" class="sk-form-input"
                       required autocomplete="email" placeholder="you@example.com"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>">
            </div>

            <div class="sk-form-group">
                <label for="password" class="sk-form-label">Password *</label>
                <input type="password" id="password" name="password" class="sk-form-input"
                       required autocomplete="new-password" placeholder="At least 8 characters"
                       minlength="8">
                <small style="color: #888; display: block; margin-top: 0.25rem;">
                    Minimum 8 characters
                </small>
            </div>

            <div class="sk-form-group">
                <label for="password_confirm" class="sk-form-label">Confirm Password *</label>
                <input type="password" id="password_confirm" name="password_confirm" class="sk-form-input"
                       required autocomplete="new-password" placeholder="Confirm your password">
            </div>

            <div class="sk-form-group">
                <label for="location" class="sk-form-label">Location</label>
                <input type="text" id="location" name="location" class="sk-form-input"
                       placeholder="City, State/Country"
                       value="<?= htmlspecialchars($old['location'] ?? '') ?>">
            </div>

            <div class="sk-form-group">
                <label style="display: flex; align-items: start; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="terms" value="1" required style="margin-top: 0.25rem;">
                    <span style="font-size: 0.875rem;">
                        I agree to the <a href="<?= $basePath ?>/terms" style="color: var(--sk-link);">Terms of Service</a>
                        and <a href="<?= $basePath ?>/privacy" style="color: var(--sk-link);">Privacy Policy</a>
                    </span>
                </label>
            </div>

            <button type="submit" class="sk-btn" style="width: 100%; margin-top: 1rem;">
                Create Account
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--sk-border);">
            <p style="color: #888; font-size: 0.875rem;">
                Already have an account?
                <a href="<?= $basePath ?>/login" style="color: var(--sk-link); font-weight: 600;">Sign in</a>
            </p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
