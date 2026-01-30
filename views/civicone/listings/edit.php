<?php
/**
 * CivicOne View: Edit Listing
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Edit Listing';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Offers & Requests', 'href' => $basePath . '/listings'],
        ['text' => htmlspecialchars($listing['title']), 'href' => $basePath . '/listings/' . $listing['id']],
        ['text' => 'Edit']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="govuk-back-link govuk-!-margin-bottom-6">Back to listing</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Edit listing</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">
            Update the details of your listing. Your changes will be visible to the community immediately.
        </p>

        <?php
        // Variables for shared form partial
        $formAction = $basePath . '/listings/update';
        $submitLabel = 'Save changes';
        $isEdit = true;

        // Include shared form partial
        require __DIR__ . '/_form.php';
        ?>

        <!-- Delete Section -->
        <?php if ($listing['status'] !== 'deleted'): ?>
        <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">

        <div class="govuk-!-padding-6 civicone-danger-zone">
            <h2 class="govuk-heading-m civicone-danger-heading">
                <i class="fa-solid fa-triangle-exclamation govuk-!-margin-right-1" aria-hidden="true"></i>
                Delete this listing
            </h2>
            <p class="govuk-body">
                Once you delete this listing, there is no going back. This action cannot be undone.
            </p>

            <form action="<?= $basePath ?>/listings/delete" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this listing? This action cannot be undone.');">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="id" value="<?= $listing['id'] ?>">

                <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">
                    <i class="fa-solid fa-trash govuk-!-margin-right-1" aria-hidden="true"></i> Delete listing
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
