<?php
/**
 * Federation Groups Enable Required
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = "Enable Federated Groups";
\Nexus\Core\SEO::setTitle('Enable Federated Groups');
\Nexus\Core\SEO::setDescription('Enable federation settings to browse and join groups from partner timebanks.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <div class="govuk-!-padding-6 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #1d70b8;">
            <p class="govuk-body govuk-!-margin-bottom-4">
                <i class="fa-solid fa-people-group fa-3x" style="color: #1d70b8;" aria-hidden="true"></i>
            </p>

            <h1 class="govuk-heading-xl">Enable Federated Groups</h1>

            <p class="govuk-body-l govuk-!-margin-bottom-6">
                To browse and join groups from partner timebanks,
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
                What are Federated Groups?
            </h2>
            <ul class="govuk-list govuk-list--bullet govuk-list--spaced">
                <li>Join interest groups from partner timebanks</li>
                <li>Connect with members across the network</li>
                <li>Participate in group discussions and activities</li>
                <li>You control your group memberships</li>
            </ul>
        </div>
    </div>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
