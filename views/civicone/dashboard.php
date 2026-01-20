<?php
/**
 * CivicOne Dashboard - Account Area Hub (Overview)
 * WCAG 2.1 AA Compliant
 * Template: Account Area Template (Template G)
 * Pattern Sources:
 * - MOJ Sub navigation: https://design-patterns.service.justice.gov.uk/components/sub-navigation/
 * - GOV.UK Page Template: https://design-system.service.gov.uk/styles/page-template/
 *
 * CRITICAL: This is now the "Overview" hub page for the Account Area.
 * Other sections (Notifications, Hubs, Listings, Wallet, Events) are separate pages
 * with their own routes, linked via secondary navigation.
 */

$hTitle = "My Dashboard";
$hSubtitle = "Welcome back, " . htmlspecialchars($user['name']);
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(__DIR__) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="civic-dashboard civicone-account-area">

    <!-- Account Area Secondary Navigation (MOJ Sub navigation pattern) -->
    <?php require dirname(__DIR__) . '/layouts/civicone/partials/account-navigation.php'; ?>

    <!-- Overview Content (replaces old "overview" tab) -->
    <?php require __DIR__ . '/dashboard/partials/_overview.php'; ?>

</div>

<!-- Dashboard FAB -->
<div class="civic-fab" id="civicFab">
    <button type="button" class="civic-fab-main" onclick="toggleCivicFab()" aria-label="Quick Actions" aria-expanded="false">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
    </button>
    <div class="civic-fab-menu" id="civicFabMenu" hidden>
        <a href="<?= $basePath ?>/wallet" class="civic-fab-item">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
            <span>Send Credits</span>
        </a>
        <a href="<?= $basePath ?>/listings/create" class="civic-fab-item">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span>New Listing</span>
        </a>
        <a href="<?= $basePath ?>/events/create" class="civic-fab-item">
            <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
            <span>Create Event</span>
        </a>
    </div>
</div>

<script src="/assets/js/civicone-dashboard.js"></script>
<script>
// Initialize dashboard with basePath
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initCivicOneDashboard === 'function') {
        initCivicOneDashboard('<?= $basePath ?>');
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/civicone/footer.php'; ?>
