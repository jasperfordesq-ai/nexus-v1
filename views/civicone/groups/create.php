<?php
// CivicOne View: Create Hub - WCAG 2.1 AA Compliant
// GOV.UK Form Template (Template D)

$pageTitle = 'Start a Hub';
$pageSubtitle = 'Build a space for your local community or shared interest';

// Handle form errors from session
$errors = $_SESSION['form_errors'] ?? [];
$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old_input']);

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?php
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Local Hubs', 'url' => '/groups'],
    ['label' => 'Start a Hub']
];
require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
?>

<!-- GOV.UK Page Template Boilerplate -->
<div class="civicone-width-container civicone--govuk">
    <main class="civicone-main-wrapper" role="main">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/groups" class="civicone-back-link">
            Back to local hubs
        </a>

        <!-- Page Header -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-two-thirds">
                <h1 class="civicone-heading-xl">Start a Hub</h1>
                <p class="civicone-body-l">
                    Create a space for your neighbourhood or interest group to connect and collaborate.
                </p>
            </div>
        </div>

        <!-- Form Container -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-two-thirds">

                <!-- GOV.UK Error Summary -->
                <?php if (!empty($errors)): ?>
                <div class="civicone-error-summary" aria-labelledby="error-summary-title" role="alert" tabindex="-1">
                    <h2 class="civicone-error-summary__title" id="error-summary-title">
                        There is a problem
                    </h2>
                    <div class="civicone-error-summary__body">
                        <ul class="civicone-error-summary__list">
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
                    <div class="civicone-form-group <?= isset($errors['name']) ? 'civicone-form-group--error' : '' ?>">
                        <label class="civicone-label" for="name">
                            Hub name
                        </label>
                        <div id="name-hint" class="civicone-hint">
                            For example, "West Cork Gardeners" or "Northside Book Club"
                        </div>
                        <?php if (isset($errors['name'])): ?>
                            <p id="name-error" class="civicone-error-message">
                                <span class="civicone-visually-hidden">Error:</span>
                                <?= htmlspecialchars($errors['name']) ?>
                            </p>
                        <?php endif; ?>
                        <input class="civicone-input <?= isset($errors['name']) ? 'civicone-input--error' : '' ?>"
                               id="name"
                               name="name"
                               type="text"
                               value="<?= htmlspecialchars($oldInput['name'] ?? '') ?>"
                               aria-describedby="name-hint <?= isset($errors['name']) ? 'name-error' : '' ?>">
                    </div>

                    <!-- Description -->
                    <div class="civicone-form-group <?= isset($errors['description']) ? 'civicone-form-group--error' : '' ?>">
                        <label class="civicone-label" for="description">
                            Description
                        </label>
                        <div id="description-hint" class="civicone-hint">
                            Explain what your hub is about and who should join
                        </div>
                        <?php if (isset($errors['description'])): ?>
                            <p id="description-error" class="civicone-error-message">
                                <span class="civicone-visually-hidden">Error:</span>
                                <?= htmlspecialchars($errors['description']) ?>
                            </p>
                        <?php endif; ?>
                        <textarea class="civicone-textarea <?= isset($errors['description']) ? 'civicone-textarea--error' : '' ?>"
                                  id="description"
                                  name="description"
                                  rows="5"
                                  aria-describedby="description-hint <?= isset($errors['description']) ? 'description-error' : '' ?>"><?= htmlspecialchars($oldInput['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Location (Optional) -->
                    <div class="civicone-form-group <?= isset($errors['location']) ? 'civicone-form-group--error' : '' ?>">
                        <label class="civicone-label" for="location">
                            Location (optional)
                        </label>
                        <div id="location-hint" class="civicone-hint">
                            Add a location to help members find local hubs near them
                        </div>
                        <?php if (isset($errors['location'])): ?>
                            <p id="location-error" class="civicone-error-message">
                                <span class="civicone-visually-hidden">Error:</span>
                                <?= htmlspecialchars($errors['location']) ?>
                            </p>
                        <?php endif; ?>
                        <input class="civicone-input mapbox-location-input-v2 <?= isset($errors['location']) ? 'civicone-input--error' : '' ?>"
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
                    <button type="submit" class="civicone-button" data-module="civicone-button">
                        Create hub
                    </button>

                </form>

            </div>
        </div>

    </main>
</div><!-- /width-container -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
