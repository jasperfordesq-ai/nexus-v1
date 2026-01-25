<?php
/**
 * Federation Transactions Enable Required
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Enable Federated Transactions";
$hideHero = true;

Nexus\Core\SEO::setTitle('Enable Federated Transactions');
Nexus\Core\SEO::setDescription('Enable federation settings to send and receive hours from partner timebanks.');

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
                <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg civicone-panel-border-blue">
                    <i class="fa-solid fa-exchange-alt fa-3x govuk-!-margin-bottom-4 civicone-icon-blue" aria-hidden="true"></i>

                    <h1 class="govuk-heading-xl">Enable Federated Transactions</h1>

                    <p class="govuk-body-l govuk-!-margin-bottom-6">
                        To send and receive hours from members of partner timebanks,
                        you need to enable federated transactions in your settings.
                    </p>

                    <a href="<?= $basePath ?>/settings#federation" class="govuk-button" data-module="govuk-button">
                        <i class="fa-solid fa-cog govuk-!-margin-right-2" aria-hidden="true"></i>
                        Go to Federation Settings
                    </a>
                </div>

                <div class="govuk-!-margin-top-6 govuk-!-padding-4 civicone-article-green">
                    <h2 class="govuk-heading-m">
                        <i class="fa-solid fa-info-circle govuk-!-margin-right-2 civicone-icon-green" aria-hidden="true"></i>
                        What are Federated Transactions?
                    </h2>
                    <ul class="govuk-list govuk-list--bullet govuk-list--spaced">
                        <li>Exchange hours with members from partner timebanks</li>
                        <li>Hours are transferred between your balances</li>
                        <li>Transactions are recorded in both timebanks</li>
                        <li>You control who can send you hours</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Offline indicator handled by civicone-common.js -->

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/civicone/footer.php'; ?>
