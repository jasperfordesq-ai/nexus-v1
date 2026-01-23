<?php
/**
 * 404 Error Page - Modern Theme
 * Page Not Found
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Page Not Found';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';
?>

<div class="error-404-container">
    <div class="error-404-card">
        <div class="error-404-icon">
            <i class="fa-solid fa-ghost" aria-hidden="true"></i>
        </div>

        <div class="error-404-code">404</div>

        <h1 class="error-404-title">Page Not Found</h1>

        <p class="error-404-message">
            We couldn't find the page you're looking for. It might have been moved, deleted, or never existed.
        </p>

        <?php if (isset($suggestedUrl) && $suggestedUrl): ?>
        <div class="error-404-suggestion">
            <div class="error-404-suggestion-label">Did you mean?</div>
            <a href="<?= htmlspecialchars($suggestedUrl) ?>">
                <?= htmlspecialchars($suggestedUrl) ?>
            </a>
        </div>
        <?php endif; ?>

        <div class="error-404-actions">
            <a href="<?= $basePath ?>/" class="error-404-btn error-404-btn--primary">
                <i class="fa-solid fa-home" aria-hidden="true"></i>
                Go Home
            </a>
            <button onclick="history.back()" class="error-404-btn error-404-btn--secondary">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Go Back
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
