<?php
/**
 * 403 Error Page - Modern Theme
 * Access Denied / Forbidden
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Access Denied';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';
?>

<div class="error-404-container">
    <div class="error-404-card">
        <div class="error-404-icon" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.2));">
            <i class="fa-solid fa-lock" aria-hidden="true" style="color: #d97706;"></i>
        </div>

        <div class="error-404-code" style="background: linear-gradient(135deg, #d97706, #f59e0b); -webkit-background-clip: text; background-clip: text;">403</div>

        <h1 class="error-404-title">Access Denied</h1>

        <p class="error-404-message">
            You don't have permission to view this page.
        </p>

        <div class="error-404-suggestion" style="text-align: left;">
            <div class="error-404-suggestion-label">This might be because:</div>
            <ul style="margin: 0.5rem 0 0; padding-left: 1.25rem; color: #4b5563;">
                <li>You need to <a href="<?= $basePath ?>/login" style="color: #6366f1;">sign in</a> to access this page</li>
                <li>You don't have the right permissions for this area</li>
                <li>The page is restricted to certain users</li>
            </ul>
        </div>

        <div class="error-404-actions">
            <a href="<?= $basePath ?>/login" class="error-404-btn error-404-btn--primary">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                Sign In
            </a>
            <a href="<?= $basePath ?>/" class="error-404-btn error-404-btn--secondary">
                <i class="fa-solid fa-home" aria-hidden="true"></i>
                Go Home
            </a>
        </div>

        <p class="error-404-message" style="margin-top: 1.5rem; font-size: 0.875rem;">
            Think you should have access? <a href="<?= $basePath ?>/help/contact" style="color: #6366f1;">Contact us</a> for help.
        </p>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
