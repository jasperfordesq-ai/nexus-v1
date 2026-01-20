<?php
/**
 * Federation Not Available
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Federation Not Available";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federation Not Available');
Nexus\Core\SEO::setDescription('Federation is not currently enabled for this timebank.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <div class="civic-fed-opt-in-card" role="main">
        <div class="civic-fed-opt-in-icon" aria-hidden="true">
            <i class="fa-solid fa-network-wired"></i>
        </div>

        <h1 class="civic-fed-opt-in-title">Federation Not Available</h1>

        <p class="civic-fed-opt-in-message">
            The federation network is not currently enabled for your timebank.
            Federation allows members to connect with partner timebanks to expand their community reach.
        </p>

        <a href="<?= $basePath ?>/members" class="civic-fed-btn civic-fed-btn--primary">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            Browse Local Members
        </a>

        <aside class="civic-fed-notice" role="note">
            <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
            <div>
                If you believe federation should be enabled, please contact your timebank administrator.
            </div>
        </aside>
    </div>
</div>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
    if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
