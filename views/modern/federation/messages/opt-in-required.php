<?php
// Federation Opt-In Required - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federation Opt-In Required";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federation Opt-In Required');
Nexus\Core\SEO::setDescription('Enable federation settings to message members from partner timebanks.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="opt-in-wrapper">

<div class="opt-in-card">
            <div class="opt-in-icon">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <h1 class="opt-in-title">Federation Opt-In Required</h1>

            <p class="opt-in-message">
                To send and receive messages from members of partner timebanks,
                you need to enable federation in your settings.
            </p>

            <a href="<?= $basePath ?>/settings#federation" class="opt-in-btn">
                <i class="fa-solid fa-cog"></i>
                Go to Federation Settings
            </a>

            <div class="info-note">
                <h4><i class="fa-solid fa-info-circle" style="color: #8b5cf6; margin-right: 6px;"></i>What is Federation?</h4>
                <ul>
                    <li>Connect with members from partner timebanks</li>
                    <li>Exchange services across communities</li>
                    <li>You control what information is shared</li>
                    <li>You can opt out at any time</li>
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

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/modern/footer.php'; ?>
