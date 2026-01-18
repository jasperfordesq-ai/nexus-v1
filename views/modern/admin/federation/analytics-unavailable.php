<?php
/**
 * Federation Analytics Unavailable
 * Shown when federation is not enabled or tenant is not whitelisted
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Analytics';
$adminPageSubtitle = 'Unavailable';
$adminPageIcon = 'fa-chart-line';

require __DIR__ . '/../partials/admin-header.php';

$systemEnabled = $systemEnabled ?? false;
$isWhitelisted = $isWhitelisted ?? false;
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Federation Analytics
        </h1>
        <p class="admin-page-subtitle">Activity metrics and insights</p>
    </div>
</div>

<div class="admin-card" style="max-width: 600px; margin: 2rem auto;">
    <div class="admin-card-body" style="text-align: center; padding: 3rem;">
        <div style="width: 80px; height: 80px; margin: 0 auto 1.5rem; border-radius: 50%; background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(249, 115, 22, 0.1)); display: flex; align-items: center; justify-content: center;">
            <i class="fa-solid fa-chart-line" style="font-size: 2rem; color: #f97316;"></i>
        </div>

        <h2 style="margin-bottom: 1rem; color: var(--admin-text);">Federation Analytics Unavailable</h2>

        <?php if (!$systemEnabled): ?>
        <p style="color: var(--admin-text-muted); margin-bottom: 1.5rem;">
            The Federation system is currently disabled at the platform level.
            Please contact your platform administrator for more information.
        </p>
        <?php elseif (!$isWhitelisted): ?>
        <p style="color: var(--admin-text-muted); margin-bottom: 1.5rem;">
            Your timebank hasn't been approved for federation yet.
            Once approved, you'll be able to view analytics about your cross-timebank activity.
        </p>
        <?php endif; ?>

        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="<?= $basePath ?>/admin" class="admin-btn admin-btn-secondary">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Dashboard
            </a>
            <a href="<?= $basePath ?>/admin/federation" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-globe"></i>
                Federation Settings
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
