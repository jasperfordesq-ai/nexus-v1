<?php
/**
 * Federation Not Available
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = "Federation Not Available";
\Nexus\Core\SEO::setTitle('Federation Not Available');
\Nexus\Core\SEO::setDescription('Federation is not currently enabled for this timebank.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <div class="govuk-!-padding-6 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #1d70b8;">
            <p class="govuk-body govuk-!-margin-bottom-4">
                <i class="fa-solid fa-network-wired fa-3x" style="color: #1d70b8;" aria-hidden="true"></i>
            </p>

            <h1 class="govuk-heading-xl">Federation Not Available</h1>

            <p class="govuk-body-l govuk-!-margin-bottom-6">
                The federation network is not currently enabled for your timebank.
                Federation allows members to connect with partner timebanks to expand their community reach.
            </p>

            <a href="<?= $basePath ?>/members" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                Browse Local Members
            </a>

            <div class="govuk-inset-text govuk-!-margin-top-6" style="text-align: left;">
                <p class="govuk-body govuk-!-margin-bottom-0">
                    <i class="fa-solid fa-info-circle govuk-!-margin-right-2" aria-hidden="true"></i>
                    If you believe federation should be enabled, please contact your timebank administrator.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
