<?php
// CivicOne View: Create Event - WCAG 2.1 AA Compliant
// GOV.UK Form Template (Template D)

$pageTitle = 'Host an Event';
$pageSubtitle = 'Organize a meetup, workshop, or gathering';

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
    ['label' => 'Events', 'url' => '/events'],
    ['label' => 'Host an Event']
];
require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
?>

<!-- GOV.UK Page Template Boilerplate -->
<div class="civicone-width-container civicone--govuk">
    <main class="civicone-main-wrapper" role="main">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/events" class="civicone-back-link">
            Back to events
        </a>

        <!-- Page Header -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-two-thirds">
                <h1 class="civicone-heading-xl">Host an Event</h1>
                <p class="civicone-body-l">
                    Organize a meetup, workshop, or gathering for your community.
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
                <form action="<?= $basePath ?>/events/store" method="POST" novalidate>
                    <?= Nexus\Core\Csrf::input() ?>

                    <!-- Event Title -->
                    <div class="civicone-form-group <?= isset($errors['title']) ? 'civicone-form-group--error' : '' ?>">
                        <label class="civicone-label" for="title">
                            Event title
                        </label>
                        <div id="title-hint" class="civicone-hint">
                            For example, "Community Garden Cleanup" or "Yoga in the Park"
                        </div>
                        <?php if (isset($errors['title'])): ?>
                            <p id="title-error" class="civicone-error-message">
                                <span class="civicone-visually-hidden">Error:</span>
                                <?= htmlspecialchars($errors['title']) ?>
                            </p>
                        <?php endif; ?>
                        <input class="civicone-input <?= isset($errors['title']) ? 'civicone-input--error' : '' ?>"
                               id="title"
                               name="title"
                               type="text"
                               value="<?= htmlspecialchars($oldInput['title'] ?? '') ?>"
                               aria-describedby="title-hint <?= isset($errors['title']) ? 'title-error' : '' ?>">
                    </div>

                    <!-- Description -->
                    <div class="civicone-form-group <?= isset($errors['description']) ? 'civicone-form-group--error' : '' ?>">
                        <label class="civicone-label" for="description">
                            Description
                        </label>
                        <div id="description-hint" class="civicone-hint">
                            Provide details about what attendees can expect
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

                    <!-- Category -->
                    <div class="civicone-form-group <?= isset($errors['category_id']) ? 'civicone-form-group--error' : '' ?>">
                        <label class="civicone-label" for="category_id">
                            Category
                        </label>
                        <?php if (isset($errors['category_id'])): ?>
                            <p id="category_id-error" class="civicone-error-message">
                                <span class="civicone-visually-hidden">Error:</span>
                                <?= htmlspecialchars($errors['category_id']) ?>
                            </p>
                        <?php endif; ?>
                        <select class="civicone-select <?= isset($errors['category_id']) ? 'civicone-select--error' : '' ?>"
                                id="category_id"
                                name="category_id"
                                aria-describedby="<?= isset($errors['category_id']) ? 'category_id-error' : '' ?>">
                            <option value="">General Event</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($oldInput['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date & Time -->
                    <div class="civicone-form-group <?= isset($errors['start_date']) || isset($errors['start_time']) ? 'civicone-form-group--error' : '' ?>">
                        <fieldset class="civicone-fieldset">
                            <legend class="civicone-fieldset__legend civicone-fieldset__legend--m">
                                <h2 class="civicone-fieldset__heading">
                                    When is your event?
                                </h2>
                            </legend>

                            <?php if (isset($errors['start_date']) || isset($errors['start_time'])): ?>
                                <p id="date-time-error" class="civicone-error-message">
                                    <span class="civicone-visually-hidden">Error:</span>
                                    <?= htmlspecialchars($errors['start_date'] ?? $errors['start_time']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="civicone-date-input">
                                <div class="civicone-date-input__item">
                                    <div class="civicone-form-group">
                                        <label class="civicone-label civicone-date-input__label" for="start_date">
                                            Date
                                        </label>
                                        <input class="civicone-input civicone-date-input__input <?= isset($errors['start_date']) ? 'civicone-input--error' : '' ?>"
                                               id="start_date"
                                               name="start_date"
                                               type="date"
                                               value="<?= htmlspecialchars($oldInput['start_date'] ?? '') ?>"
                                               aria-describedby="<?= isset($errors['start_date']) ? 'date-time-error' : '' ?>">
                                    </div>
                                </div>

                                <div class="civicone-date-input__item">
                                    <div class="civicone-form-group">
                                        <label class="civicone-label civicone-date-input__label" for="start_time">
                                            Time
                                        </label>
                                        <input class="civicone-input civicone-date-input__input civicone-input--width-5 <?= isset($errors['start_time']) ? 'civicone-input--error' : '' ?>"
                                               id="start_time"
                                               name="start_time"
                                               type="time"
                                               value="<?= htmlspecialchars($oldInput['start_time'] ?? '') ?>"
                                               aria-describedby="<?= isset($errors['start_time']) ? 'date-time-error' : '' ?>">
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Location -->
                    <div class="civicone-form-group <?= isset($errors['location']) ? 'civicone-form-group--error' : '' ?>">
                        <label class="civicone-label" for="location">
                            Location
                        </label>
                        <div id="location-hint" class="civicone-hint">
                            Venue name or address
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
                               aria-describedby="location-hint <?= isset($errors['location']) ? 'location-error' : '' ?>">
                        <input type="hidden" name="latitude" value="<?= htmlspecialchars($oldInput['latitude'] ?? '') ?>">
                        <input type="hidden" name="longitude" value="<?= htmlspecialchars($oldInput['longitude'] ?? '') ?>">
                    </div>

                    <!-- Host as Group (Optional) -->
                    <?php if (!empty($myGroups)): ?>
                    <div class="civicone-form-group <?= isset($errors['group_id']) ? 'civicone-form-group--error' : '' ?>">
                        <label class="civicone-label" for="group_id">
                            Host as group (optional)
                        </label>
                        <div id="group_id-hint" class="civicone-hint">
                            Leave blank for a personal event
                        </div>
                        <?php if (isset($errors['group_id'])): ?>
                            <p id="group_id-error" class="civicone-error-message">
                                <span class="civicone-visually-hidden">Error:</span>
                                <?= htmlspecialchars($errors['group_id']) ?>
                            </p>
                        <?php endif; ?>
                        <select class="civicone-select <?= isset($errors['group_id']) ? 'civicone-select--error' : '' ?>"
                                id="group_id"
                                name="group_id"
                                aria-describedby="group_id-hint <?= isset($errors['group_id']) ? 'group_id-error' : '' ?>">
                            <option value="">Personal event</option>
                            <?php foreach ($myGroups as $group): ?>
                                <option value="<?= $group['id'] ?>" <?= (($oldInput['group_id'] ?? $selectedGroupId ?? '') == $group['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($group['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Submit Button -->
                    <button type="submit" class="civicone-button" data-module="civicone-button">
                        Create event
                    </button>

                </form>

            </div>
        </div>

    </main>
</div><!-- /width-container -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
