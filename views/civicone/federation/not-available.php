<?php
// Federation Not Available - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federation Not Available";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federation Not Available');
Nexus\Core\SEO::setDescription('Federation is not currently enabled for this timebank.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-unavailable-wrapper">

        <article class="unavailable-card" role="status" aria-labelledby="unavailable-title">
            <div class="unavailable-icon" aria-hidden="true">
                <i class="fa-solid fa-network-wired"></i>
            </div>

            <h1 id="unavailable-title" class="unavailable-title">Federation Not Available</h1>

            <p class="unavailable-message">
                The federation network is not currently enabled for your timebank.
                Federation allows members to connect with partner timebanks to expand their community reach.
            </p>

            <a href="<?= $basePath ?>/members" class="back-btn">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                Browse Local Members
            </a>

            <aside class="info-note" role="note">
                <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
                <span>If you believe federation should be enabled, please contact your timebank administrator.</span>
            </aside>
        </article>

    </div>
</div>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
