<?php
/**
 * CivicOne View: Edit Event
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Edit Event';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Handle form errors from session
$errors = $_SESSION['form_errors'] ?? [];
$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old_input']);
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/events">Events</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/events/<?= $event['id'] ?>"><?= htmlspecialchars($event['title']) ?></a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Edit</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/events/<?= $event['id'] ?>" class="govuk-back-link govuk-!-margin-bottom-6">Back to event</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Edit Event</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Make changes to your event details.</p>

        <!-- Error Summary -->
        <?php if (!empty($errors)): ?>
        <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" tabindex="-1" data-module="govuk-error-summary">
            <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
            <div class="govuk-error-summary__body">
                <ul class="govuk-list govuk-error-summary__list">
                    <?php foreach ($errors as $field => $error): ?>
                        <li><a href="#<?= htmlspecialchars($field) ?>"><?= htmlspecialchars($error) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Include the shared form partial
        $formAction = $basePath . '/events/' . $event['id'] . '/update';
        $isEdit = true;
        $submitButtonText = 'Save changes';
        require __DIR__ . '/_form.php';
        ?>

        <p class="govuk-body govuk-!-margin-top-4">
            <a href="<?= $basePath ?>/events/<?= $event['id'] ?>" class="govuk-link">Cancel and return to event</a>
        </p>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
