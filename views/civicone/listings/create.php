<?php
/**
 * CivicOne View: Create Listing
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Post a New Listing';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/listings">Offers & Requests</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Post New Listing</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/listings" class="govuk-back-link govuk-!-margin-bottom-6">Back to all listings</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Post a new listing</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">
            Share your skills, services, or items with the community, or request help with something you need.
        </p>

        <?php
        // Variables for shared form partial
        $listing = null;
        $formAction = $basePath . '/listings/store';
        $submitLabel = 'Post listing';
        $isEdit = false;

        // Include shared form partial
        require __DIR__ . '/_form.php';
        ?>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
