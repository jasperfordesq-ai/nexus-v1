<?php
// CivicOne Listing Edit - WCAG 2.1 AA Compliant
// GOV.UK Form Template (Template D)
if (session_status() === PHP_SESSION_NONE) session_start();

$hTitle = 'Edit Listing';
$hSubtitle = 'Update your offer or request details';
$hType = 'Listings';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?php
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Offers & Requests', 'url' => '/listings'],
    ['label' => htmlspecialchars($listing['title']), 'url' => '/listings/' . $listing['id']],
    ['label' => 'Edit']
];
require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
?>

<!-- GOV.UK Page Template Boilerplate -->
<div class="civicone-width-container civicone--govuk">
    <main class="civicone-main-wrapper" role="main">

        <!-- Back Link (optional) -->
        <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="civicone-back-link">
            Back to listing
        </a>

        <!-- Page Header -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-two-thirds">
                <h1 class="civicone-heading-xl">Edit listing</h1>
                <p class="civicone-body-l">
                    Update the details of your listing. Your changes will be visible to the community immediately.
                </p>
            </div>
        </div>

        <!-- Form Container -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-two-thirds">

                <?php
                // Variables for shared form partial
                // $listing is already available from controller
                $formAction = $basePath . '/listings/update';
                $submitLabel = 'Save changes';
                $isEdit = true;

                // Include shared form partial
                require __DIR__ . '/_form.php';
                ?>

                <!-- Delete Action - Separate Form -->
                <?php if ($listing['status'] !== 'deleted'): ?>
                <div class="civicone-form-section civicone-form-section--danger">
                    <h2 class="civicone-heading-m">Delete this listing</h2>
                    <p class="civicone-body">
                        Once you delete this listing, there is no going back. This action cannot be undone.
                    </p>

                    <form action="<?= $basePath ?>/listings/delete" method="POST"
                          onsubmit="return confirm('Are you sure you want to delete this listing? This action cannot be undone.');"
                          class="civicone-form-delete">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="id" value="<?= $listing['id'] ?>">

                        <button type="submit" class="civicone-button civicone-button--warning">
                            Delete listing
                        </button>
                    </form>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div><!-- /width-container -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
