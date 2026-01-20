<?php
// CivicOne Listing Create - WCAG 2.1 AA Compliant
// GOV.UK Form Template (Template D)
if (session_status() === PHP_SESSION_NONE) session_start();

$hTitle = 'Post a New Listing';
$hSubtitle = 'Share your skills or ask for help from the community';
$hType = 'Listings';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?php
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Offers & Requests', 'url' => '/listings'],
    ['label' => 'Post New Listing']
];
require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
?>

<!-- GOV.UK Page Template Boilerplate -->
<div class="civicone-width-container civicone--govuk">
    <main class="civicone-main-wrapper" id="main-content" role="main">

        <!-- Back Link (optional) -->
        <a href="<?= $basePath ?>/listings" class="civicone-back-link">
            Back to all listings
        </a>

        <!-- Page Header -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-two-thirds">
                <h1 class="civicone-heading-xl">Post a new listing</h1>
                <p class="civicone-body-l">
                    Share your skills, services, or items with the community, or request help with something you need.
                </p>
            </div>
        </div>

        <!-- Form Container -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-two-thirds">

                <?php
                // Variables for shared form partial
                $listing = null; // No listing data for create mode
                $formAction = $basePath . '/listings/store';
                $submitLabel = 'Post listing';
                $isEdit = false;

                // Include shared form partial
                require __DIR__ . '/_form.php';
                ?>

            </div>
        </div>

    </main>
</div><!-- /width-container -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
