<?php
/**
 * Skeleton Layout - 500 Error Page
 * Internal server error
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
http_response_code(500);
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<div style="text-align: center; padding: 4rem 1rem;">
    <div style="font-size: 6rem; font-weight: 700; color: #dc2626; margin-bottom: 1rem;">500</div>
    <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 1rem;">Server Error</h1>
    <p style="color: #888; font-size: 1.125rem; margin-bottom: 2rem; max-width: 500px; margin-left: auto; margin-right: auto;">
        Oops! Something went wrong on our end. We're working to fix it.
    </p>

    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <a href="<?= $basePath ?>/" class="sk-btn">
            <i class="fas fa-home"></i> Go Home
        </a>
        <button onclick="location.reload()" class="sk-btn sk-btn-outline">
            <i class="fas fa-redo"></i> Try Again
        </button>
    </div>

    <div class="sk-card" style="max-width: 600px; margin: 3rem auto;">
        <h3 style="font-weight: 600; margin-bottom: 1rem;">What you can do:</h3>
        <ul style="text-align: left; line-height: 2;">
            <li>Refresh the page and try again</li>
            <li>Go back to the <a href="<?= $basePath ?>/" style="color: var(--sk-link);">homepage</a></li>
            <li>Check back in a few minutes</li>
            <li>Contact support if the problem persists</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
