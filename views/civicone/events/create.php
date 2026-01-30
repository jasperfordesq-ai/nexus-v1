<?php
/**
 * CivicOne View: Create Event
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Host an Event';

// Handle form errors from session
$errors = $_SESSION['form_errors'] ?? [];
$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old_input']);

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Events', 'href' => $basePath . '/events'],
        ['text' => 'Host an Event']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/events" class="govuk-back-link govuk-!-margin-bottom-6">Back to events</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Host an Event</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Organize a meetup, workshop, or gathering for your community.</p>

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
        $formAction = $basePath . '/events/store';
        $isEdit = false;
        $submitButtonText = 'Create event';
        require __DIR__ . '/_form.php';
        ?>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
