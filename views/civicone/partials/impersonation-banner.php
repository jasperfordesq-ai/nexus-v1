<?php
/**
 * Admin Impersonation Banner
 * Shows when an admin is logged in as another user
 */

if (!empty($_SESSION['is_impersonating'])):
    $basePath = \Nexus\Core\TenantContext::getBasePath();
    $impersonatedUserName = $_SESSION['user_name'] ?? 'Unknown User';
    $adminName = $_SESSION['impersonating_as_admin_name'] ?? 'Admin';
?>
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/purged/civicone-impersonation-banner.min.css">
<div id="impersonation-banner" class="impersonation-banner">
    <div class="impersonation-banner-content">
        <div class="impersonation-banner-icon">
            <i class="fa-solid fa-user-secret"></i>
        </div>
        <div class="impersonation-banner-text">
            <strong>You are currently logged in as <?= htmlspecialchars($impersonatedUserName) ?></strong>
            <span class="impersonation-banner-subtext">Viewing the platform as this user would see it</span>
        </div>
    </div>
    <div class="impersonation-banner-actions">
        <a href="<?= $basePath ?>/admin-legacy/stop-impersonating" class="impersonation-banner-btn impersonation-exit-btn">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Exit & Return to <?= htmlspecialchars($adminName) ?></span>
        </a>
    </div>
</div>

<?php endif; ?>
