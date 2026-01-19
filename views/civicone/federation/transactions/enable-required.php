<?php
// Federation Transactions Enable Required - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Enable Federated Transactions";
$hideHero = true;

Nexus\Core\SEO::setTitle('Enable Federated Transactions');
Nexus\Core\SEO::setDescription('Enable federation settings to send and receive hours from partner timebanks.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
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
                <i class="fa-solid fa-exchange-alt"></i>
            </div>

            <h1 class="enable-title">Enable Federated Transactions</h1>

            <p class="enable-message">
                To send and receive hours from members of partner timebanks,
                you need to enable federated transactions in your settings.
            </p>

            <a href="<?= $basePath ?>/settings#federation" class="enable-btn">
                <i class="fa-solid fa-cog"></i>
                Go to Federation Settings
            </a>

            <div class="info-note">
                <h4><i class="fa-solid fa-info-circle" style="color: #8b5cf6; margin-right: 6px;"></i>What are Federated Transactions?</h4>
                <ul>
                    <li>Exchange hours with members from partner timebanks</li>
                    <li>Hours are transferred between your balances</li>
                    <li>Transactions are recorded in both timebanks</li>
                    <li>You control who can send you hours</li>
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

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
