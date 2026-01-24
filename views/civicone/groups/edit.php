<?php
/**
 * CivicOne View: Edit Hub
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Edit Hub';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/groups">Local Hubs</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/groups/<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Edit</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=settings" class="govuk-back-link govuk-!-margin-bottom-6">
    Back to <?= htmlspecialchars($group['name']) ?>
</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Edit Hub</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">
            Update the settings for <strong><?= htmlspecialchars($group['name']) ?></strong>
        </p>

        <?php
        $formAction = $basePath . '/groups/update';
        $isEdit = true;
        $submitButtonText = 'Save changes';
        require __DIR__ . '/_form.php';
        ?>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
