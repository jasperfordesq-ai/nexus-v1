<?php
/**
 * Federation Transactions Enable Required
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Enable Federated Transactions";
$hideHero = true;

Nexus\Core\SEO::setTitle('Enable Federated Transactions');
Nexus\Core\SEO::setDescription('Enable federation settings to send and receive hours from partner timebanks.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
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
            <i class="fa-solid fa-exchange-alt"></i>
        </div>

        <h1 class="civic-fed-opt-in-title">Enable Federated Transactions</h1>

        <p class="civic-fed-opt-in-message">
            To send and receive hours from members of partner timebanks,
            you need to enable federated transactions in your settings.
        </p>

        <a href="<?= $basePath ?>/settings#federation" class="civic-fed-btn civic-fed-btn--primary">
            <i class="fa-solid fa-cog" aria-hidden="true"></i>
            Go to Federation Settings
        </a>

        <aside class="civic-fed-info-card" role="complementary" aria-labelledby="transactions-info-heading">
            <h2 id="transactions-info-heading" class="civic-fed-info-heading">
                <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
                What are Federated Transactions?
            </h2>
            <ul class="civic-fed-info-list">
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Exchange hours with members from partner timebanks
                </li>
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Hours are transferred between your balances
                </li>
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Transactions are recorded in both timebanks
                </li>
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    You control who can send you hours
                </li>
            </ul>
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

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
