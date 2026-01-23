<?php
/**
 * 500 Error Page - Modern Theme
 * Service Unavailable / Server Error
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Something Went Wrong';
$hideHero = true;
$referenceNumber = $referenceNumber ?? null;

require __DIR__ . '/../../layouts/modern/header.php';
?>

<div class="error-404-container">
    <div class="error-404-card">
        <div class="error-404-icon" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.2));">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true" style="color: #dc2626;"></i>
        </div>

        <div class="error-404-code" style="background: linear-gradient(135deg, #dc2626, #ef4444); -webkit-background-clip: text; background-clip: text;">500</div>

        <h1 class="error-404-title">Something Went Wrong</h1>

        <p class="error-404-message">
            We're having trouble processing your request. Please try again in a few moments.
        </p>

        <?php if ($referenceNumber): ?>
        <div class="error-404-suggestion">
            <div class="error-404-suggestion-label">Reference Number</div>
            <strong><?= htmlspecialchars($referenceNumber) ?></strong>
            <p style="margin-top: 0.5rem; font-size: 0.875rem;">Please quote this when contacting support.</p>
        </div>
        <?php endif; ?>

        <div class="error-404-actions">
            <a href="<?= $basePath ?>/" class="error-404-btn error-404-btn--primary">
                <i class="fa-solid fa-home" aria-hidden="true"></i>
                Go Home
            </a>
            <button onclick="location.reload()" class="error-404-btn error-404-btn--secondary">
                <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                Try Again
            </button>
        </div>

        <p class="error-404-message" style="margin-top: 1.5rem; font-size: 0.875rem;">
            If the problem persists, <a href="<?= $basePath ?>/help/contact" style="color: #6366f1;">contact support</a>.
        </p>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
