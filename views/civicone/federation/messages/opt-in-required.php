<?php
// Federation Opt-In Required - CivicOne WCAG 2.1 AA
$pageTitle = $pageTitle ?? "Federation Opt-In Required";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federation Opt-In Required');
Nexus\Core\SEO::setDescription('Enable federation settings to message members from partner timebanks.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="opt-in-wrapper">

        <div class="opt-in-card" role="main">
            <div class="opt-in-icon" aria-hidden="true">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <h1 class="opt-in-title">Federation Opt-In Required</h1>

            <p class="opt-in-message">
                To send and receive messages from members of partner timebanks,
                you need to enable federation in your settings.
            </p>

            <a href="<?= $basePath ?>/settings#federation" class="opt-in-btn">
                <i class="fa-solid fa-cog" aria-hidden="true"></i>
                Go to Federation Settings
            </a>

            <aside class="info-note" role="complementary" aria-labelledby="federation-info-heading">
                <h2 id="federation-info-heading" class="info-note-heading">
                    <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
                    What is Federation?
                </h2>
                <ul class="info-note-list">
                    <li>Connect with members from partner timebanks</li>
                    <li>Exchange services across communities</li>
                    <li>You control what information is shared</li>
                    <li>You can opt out at any time</li>
                </ul>
            </aside>
        </div>

    </div>
</div>

<script src="/assets/js/federation-common.js?v=<?= time() ?>"></script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
