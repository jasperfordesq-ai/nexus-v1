<?php
/**
 * CivicOne View: Edit Hub
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Edit Hub';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Local Hubs', 'href' => $basePath . '/groups'],
        ['text' => htmlspecialchars($group['name']), 'href' => $basePath . '/groups/' . $group['id']],
        ['text' => 'Edit']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

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
