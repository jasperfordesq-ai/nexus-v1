<?php
// Federation Groups Enable Required - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Enable Federated Groups";
$hideHero = true;

Nexus\Core\SEO::setTitle('Enable Federated Groups');
Nexus\Core\SEO::setDescription('Enable federation settings to browse and join groups from partner timebanks.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="enable-wrapper">

        <div class="enable-card">
            <div class="enable-icon">
                <i class="fa-solid fa-people-group"></i>
            </div>

            <h1 class="enable-title">Enable Federated Groups</h1>

            <p class="enable-message">
                To browse and join groups from partner timebanks,
                you need to enable federation in your settings.
            </p>

            <a href="<?= $basePath ?>/settings#federation" class="enable-btn">
                <i class="fa-solid fa-cog"></i>
                Go to Federation Settings
            </a>

            <div class="info-note">
                <h4><i class="fa-solid fa-info-circle" style="color: #8b5cf6; margin-right: 6px;"></i>What are Federated Groups?</h4>
                <ul>
                    <li>Join interest groups from partner timebanks</li>
                    <li>Connect with members across the network</li>
                    <li>Participate in group discussions and activities</li>
                    <li>You control your group memberships</li>
                </ul>
            </div>
        </div>

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

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
