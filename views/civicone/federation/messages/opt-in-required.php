<?php
/**
 * Federation Opt-In Required
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Federation Opt-In Required";
$hideHero = true;

Nexus\Core\SEO::setTitle('Federation Opt-In Required');
Nexus\Core\SEO::setDescription('Enable federation settings to message members from partner timebanks.');

require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-width-container">
    <!-- Offline Banner -->
    <div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-display-none" id="offlineBanner" role="alert" aria-live="polite" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">
                <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
                No internet connection
            </p>
        </div>
    </div>

    <main class="govuk-main-wrapper" id="main-content" role="main">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                    <i class="fa-solid fa-user-shield fa-3x govuk-!-margin-bottom-4" style="color: #1d70b8;" aria-hidden="true"></i>

                    <h1 class="govuk-heading-xl">Federation Opt-In Required</h1>

                    <p class="govuk-body-l govuk-!-margin-bottom-6">
                        To send and receive messages from members of partner timebanks,
                        you need to enable federation in your settings.
                    </p>

                    <a href="<?= $basePath ?>/settings#federation" class="govuk-button" data-module="govuk-button">
                        <i class="fa-solid fa-cog govuk-!-margin-right-2" aria-hidden="true"></i>
                        Go to Federation Settings
                    </a>
                </div>

                <div class="govuk-!-margin-top-6 govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #00703c;">
                    <h2 class="govuk-heading-m">
                        <i class="fa-solid fa-info-circle govuk-!-margin-right-2" style="color: #00703c;" aria-hidden="true"></i>
                        What is Federation?
                    </h2>
                    <ul class="govuk-list govuk-list--bullet govuk-list--spaced">
                        <li>Connect with members from partner timebanks</li>
                        <li>Exchange services across communities</li>
                        <li>You control what information is shared</li>
                        <li>You can opt out at any time</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="/assets/js/federation-common.js?v=<?= time() ?>"></script>
<script>
(function() {
    'use strict';
    var banner = document.getElementById('offlineBanner');
    function updateOffline(offline) {
        if (banner) banner.classList.toggle('govuk-!-display-none', !offline);
    }
    window.addEventListener('online', function() { updateOffline(false); });
    window.addEventListener('offline', function() { updateOffline(true); });
    if (!navigator.onLine) updateOffline(true);
})();
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
