<?php
/**
 * Federation Analytics - Unavailable
 * Shown when analytics are not available
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Federation Analytics';
$adminPageSubtitle = 'Not Available';
$adminPageIcon = 'fa-chart-line';

require __DIR__ . '/../partials/admin-header.php';
?>

<div class="fed-admin-card">
    <div class="fed-admin-card-body">
        <div class="fed-empty-state" style="padding: 4rem 2rem;">
            <i class="fa-solid fa-chart-line"></i>
            <h3>Analytics Not Available</h3>
            <p class="admin-text-muted">
                Federation analytics are not available at this time.
                <?php if (!empty($reason)): ?>
                <br><?= htmlspecialchars($reason) ?>
                <?php endif; ?>
            </p>
            <a href="<?= $basePath ?>/admin-legacy/federation" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Federation Settings
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
