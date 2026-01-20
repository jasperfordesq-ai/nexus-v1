<?php
/**
 * Federation Opt-In Required
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Federation Opt-In Required";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federation Opt-In Required');
Nexus\Core\SEO::setDescription('Enable federation settings to message members from partner timebanks.');

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
            <i class="fa-solid fa-user-shield"></i>
        </div>

        <h1 class="civic-fed-opt-in-title">Federation Opt-In Required</h1>

        <p class="civic-fed-opt-in-message">
            To send and receive messages from members of partner timebanks,
            you need to enable federation in your settings.
        </p>

        <a href="<?= $basePath ?>/settings#federation" class="civic-fed-btn civic-fed-btn--primary">
            <i class="fa-solid fa-cog" aria-hidden="true"></i>
            Go to Federation Settings
        </a>

        <aside class="civic-fed-info-card" role="complementary" aria-labelledby="federation-info-heading">
            <h2 id="federation-info-heading" class="civic-fed-info-heading">
                <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
                What is Federation?
            </h2>
            <ul class="civic-fed-info-list">
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Connect with members from partner timebanks
                </li>
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Exchange services across communities
                </li>
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    You control what information is shared
                </li>
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    You can opt out at any time
                </li>
            </ul>
        </aside>
    </div>
</div>

<script src="/assets/js/federation-common.js?v=<?= time() ?>"></script>
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
