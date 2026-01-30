<?php
/**
 * CivicOne View: Create Hub
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Start a Hub';

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
        ['text' => 'Local Hubs', 'href' => $basePath . '/groups'],
        ['text' => 'Start a Hub']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/groups" class="govuk-back-link govuk-!-margin-bottom-6">Back to local hubs</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Start a Hub</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">
            Create a space for your neighbourhood or interest group to connect and collaborate.
        </p>

        <!-- GOV.UK Error Summary -->
        <?php if (!empty($errors)): ?>
        <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
            <h2 class="govuk-error-summary__title" id="error-summary-title">
                There is a problem
            </h2>
            <div class="govuk-error-summary__body">
                <ul class="govuk-list govuk-error-summary__list">
                    <?php foreach ($errors as $field => $error): ?>
                        <li>
                            <a href="#<?= htmlspecialchars($field) ?>"><?= htmlspecialchars($error) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="<?= $basePath ?>/groups/store" method="POST" novalidate>
            <?= Nexus\Core\Csrf::input() ?>

            <!-- Hub Name -->
            <div class="govuk-form-group <?= isset($errors['name']) ? 'govuk-form-group--error' : '' ?>">
                <label class="govuk-label" for="name">
                    Hub name
                </label>
                <div id="name-hint" class="govuk-hint">
                    For example, "West Cork Gardeners" or "Northside Book Club"
                </div>
                <?php if (isset($errors['name'])): ?>
                    <p id="name-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">Error:</span>
                        <?= htmlspecialchars($errors['name']) ?>
                    </p>
                <?php endif; ?>
                <input class="govuk-input <?= isset($errors['name']) ? 'govuk-input--error' : '' ?>"
                       id="name"
                       name="name"
                       type="text"
                       value="<?= htmlspecialchars($oldInput['name'] ?? '') ?>"
                       aria-describedby="name-hint <?= isset($errors['name']) ? 'name-error' : '' ?>"
                       required>
            </div>

            <!-- Description -->
            <div class="govuk-form-group <?= isset($errors['description']) ? 'govuk-form-group--error' : '' ?>">
                <label class="govuk-label" for="description">
                    Description
                </label>
                <div id="description-hint" class="govuk-hint">
                    Explain what your hub is about and who should join
                </div>
                <?php if (isset($errors['description'])): ?>
                    <p id="description-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">Error:</span>
                        <?= htmlspecialchars($errors['description']) ?>
                    </p>
                <?php endif; ?>
                <textarea class="govuk-textarea <?= isset($errors['description']) ? 'govuk-textarea--error' : '' ?>"
                          id="description"
                          name="description"
                          rows="5"
                          aria-describedby="description-hint <?= isset($errors['description']) ? 'description-error' : '' ?>"
                          required><?= htmlspecialchars($oldInput['description'] ?? '') ?></textarea>
            </div>

            <!-- Location (Optional) -->
            <div class="govuk-form-group <?= isset($errors['location']) ? 'govuk-form-group--error' : '' ?>">
                <label class="govuk-label" for="location">
                    Location <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <div id="location-hint" class="govuk-hint">
                    Add a location to help members find local hubs near them
                </div>
                <?php if (isset($errors['location'])): ?>
                    <p id="location-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">Error:</span>
                        <?= htmlspecialchars($errors['location']) ?>
                    </p>
                <?php endif; ?>
                <input class="govuk-input mapbox-location-input-v2 <?= isset($errors['location']) ? 'govuk-input--error' : '' ?>"
                       id="location"
                       name="location"
                       type="text"
                       value="<?= htmlspecialchars($oldInput['location'] ?? '') ?>"
                       autocomplete="off"
                       aria-describedby="location-hint <?= isset($errors['location']) ? 'location-error' : '' ?>">
                <input type="hidden" name="latitude" id="location_lat" value="<?= htmlspecialchars($oldInput['latitude'] ?? '') ?>">
                <input type="hidden" name="longitude" id="location_lng" value="<?= htmlspecialchars($oldInput['longitude'] ?? '') ?>">
            </div>

            <!-- Submit Button -->
            <button type="submit" class="govuk-button" data-module="govuk-button">
                Create hub
            </button>

        </form>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
