<?php
/**
 * CivicOne Page Heading Region - GOV.UK Pattern
 * https://design-system.service.gov.uk/styles/page-template/
 *
 * Usage:
 * Before including header.php, set these variables:
 * - $pageTitle (required): The H1 text
 * - $pageLead (optional): Lead paragraph below H1
 * - $pageBreadcrumbs (optional): Array of breadcrumb items
 * - $pageCaption (optional): Caption above H1 (e.g., section name)
 *
 * Example:
 * $pageTitle = 'My listings';
 * $pageLead = 'View and manage your service listings.';
 * $pageBreadcrumbs = [
 *     ['label' => 'Home', 'url' => '/'],
 *     ['label' => 'Listings', 'url' => '/listings'],
 *     ['label' => 'My listings']
 * ];
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?php if (!empty($pageBreadcrumbs)): ?>
<!-- Breadcrumbs -->
<nav class="govuk-breadcrumbs govuk-!-margin-bottom-4" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <?php foreach ($pageBreadcrumbs as $index => $crumb): ?>
            <?php if (isset($crumb['url'])): ?>
                <li class="govuk-breadcrumbs__list-item">
                    <a class="govuk-breadcrumbs__link" href="<?= $basePath . htmlspecialchars($crumb['url']) ?>">
                        <?= htmlspecialchars($crumb['label']) ?>
                    </a>
                </li>
            <?php else: ?>
                <li class="govuk-breadcrumbs__list-item" aria-current="page">
                    <?= htmlspecialchars($crumb['label']) ?>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>

<!-- Page Heading -->
<div class="govuk-!-margin-bottom-6">
    <?php if (!empty($pageCaption)): ?>
        <span class="govuk-caption-xl"><?= htmlspecialchars($pageCaption) ?></span>
    <?php endif; ?>

    <h1 class="govuk-heading-xl<?= empty($pageLead) ? ' govuk-!-margin-bottom-4' : '' ?>">
        <?= htmlspecialchars($pageTitle ?? 'Page') ?>
    </h1>

    <?php if (!empty($pageLead)): ?>
        <p class="govuk-body-l govuk-!-margin-bottom-0"><?= htmlspecialchars($pageLead) ?></p>
    <?php endif; ?>
</div>
