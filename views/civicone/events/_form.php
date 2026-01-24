<?php
/**
 * CivicOne Events Shared Form Partial
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 *
 * Required variables:
 * - $formAction: URL to submit the form to
 * - $isEdit: boolean - true for edit mode, false for create mode
 * - $submitButtonText: text for the submit button
 *
 * Optional variables:
 * - $event: array - event data (required for edit mode)
 * - $errors: array - validation errors
 * - $oldInput: array - old input values
 * - $categories: array - available event categories
 * - $myGroups: array - user's groups for hosting
 * - $selectedGroupId: int - pre-selected group ID
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$errors = $errors ?? [];
$oldInput = $oldInput ?? [];
$event = $event ?? [];
$isEdit = $isEdit ?? false;
$categories = $categories ?? [];
$myGroups = $myGroups ?? [];
?>

<form action="<?= htmlspecialchars($formAction) ?>" method="POST" novalidate>
    <?= \Nexus\Core\Csrf::input() ?>

    <?php if ($isEdit && !empty($event['id'])): ?>
        <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['id']) ?>">
    <?php endif; ?>

    <!-- Event Title -->
    <div class="govuk-form-group <?= isset($errors['title']) ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="title">Event title</label>
        <div id="title-hint" class="govuk-hint">
            <?= $isEdit ? 'The name of your event' : 'For example, "Community Garden Planting Day"' ?>
        </div>
        <?php if (isset($errors['title'])): ?>
            <p id="title-error" class="govuk-error-message">
                <span class="govuk-visually-hidden">Error:</span>
                <?= htmlspecialchars($errors['title']) ?>
            </p>
        <?php endif; ?>
        <input class="govuk-input <?= isset($errors['title']) ? 'govuk-input--error' : '' ?>"
               id="title"
               name="title"
               type="text"
               value="<?= htmlspecialchars($oldInput['title'] ?? $event['title'] ?? '') ?>"
               aria-describedby="title-hint <?= isset($errors['title']) ? 'title-error' : '' ?>"
               required>
    </div>

    <!-- Description -->
    <div class="govuk-form-group <?= isset($errors['description']) ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="description">Description</label>
        <div id="description-hint" class="govuk-hint">
            <?= $isEdit ? 'What will happen at this event?' : 'Explain what will happen and who should attend' ?>
        </div>
        <?php if ($isEdit): ?>
        <p class="govuk-body-s govuk-!-margin-bottom-2">
            <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" id="aiGenerateBtn" data-module="govuk-button">
                <i class="fa-solid fa-sparkles govuk-!-margin-right-1" aria-hidden="true"></i>
                Generate with AI
            </button>
        </p>
        <?php endif; ?>
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
                  required><?= htmlspecialchars($oldInput['description'] ?? $event['description'] ?? '') ?></textarea>
    </div>

    <!-- Category -->
    <div class="govuk-form-group <?= isset($errors['category_id']) ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="category_id">Category</label>
        <?php if (isset($errors['category_id'])): ?>
            <p id="category_id-error" class="govuk-error-message">
                <span class="govuk-visually-hidden">Error:</span>
                <?= htmlspecialchars($errors['category_id']) ?>
            </p>
        <?php endif; ?>
        <select class="govuk-select <?= isset($errors['category_id']) ? 'govuk-select--error' : '' ?>"
                id="category_id"
                name="category_id">
            <option value="">General Event</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($oldInput['category_id'] ?? $event['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Date & Time -->
    <div class="govuk-form-group <?= isset($errors['start_date']) || isset($errors['start_time']) ? 'govuk-form-group--error' : '' ?>">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">When is your event?</h2>
            </legend>

            <?php if (isset($errors['start_date']) || isset($errors['start_time'])): ?>
                <p id="date-time-error" class="govuk-error-message">
                    <span class="govuk-visually-hidden">Error:</span>
                    <?= htmlspecialchars($errors['start_date'] ?? $errors['start_time']) ?>
                </p>
            <?php endif; ?>

            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="start_date">Start date</label>
                        <input class="govuk-input <?= isset($errors['start_date']) ? 'govuk-input--error' : '' ?>"
                               id="start_date"
                               name="start_date"
                               type="date"
                               value="<?= htmlspecialchars($oldInput['start_date'] ?? (!empty($event['start_time']) ? date('Y-m-d', strtotime($event['start_time'])) : '')) ?>"
                               required>
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="start_time">Start time</label>
                        <input class="govuk-input govuk-input--width-5 <?= isset($errors['start_time']) ? 'govuk-input--error' : '' ?>"
                               id="start_time"
                               name="start_time"
                               type="time"
                               value="<?= htmlspecialchars($oldInput['start_time'] ?? (!empty($event['start_time']) ? date('H:i', strtotime($event['start_time'])) : '')) ?>"
                               required>
                    </div>
                </div>
            </div>

            <?php if ($isEdit): ?>
            <div class="govuk-grid-row govuk-!-margin-top-4">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="end_date">
                            End date <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                        </label>
                        <input class="govuk-input"
                               id="end_date"
                               name="end_date"
                               type="date"
                               value="<?= htmlspecialchars($oldInput['end_date'] ?? (!empty($event['end_time']) ? date('Y-m-d', strtotime($event['end_time'])) : '')) ?>">
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="end_time">
                            End time <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                        </label>
                        <input class="govuk-input govuk-input--width-5"
                               id="end_time"
                               name="end_time"
                               type="time"
                               value="<?= htmlspecialchars($oldInput['end_time'] ?? (!empty($event['end_time']) ? date('H:i', strtotime($event['end_time'])) : '')) ?>">
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </fieldset>
    </div>

    <!-- Location -->
    <div class="govuk-form-group <?= isset($errors['location']) ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="location">Location</label>
        <div id="location-hint" class="govuk-hint">Venue name or address</div>
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
               value="<?= htmlspecialchars($oldInput['location'] ?? $event['location'] ?? '') ?>"
               autocomplete="off"
               aria-describedby="location-hint <?= isset($errors['location']) ? 'location-error' : '' ?>"
               required>
        <input type="hidden" name="latitude" id="location_lat" value="<?= htmlspecialchars($oldInput['latitude'] ?? $event['latitude'] ?? '') ?>">
        <input type="hidden" name="longitude" id="location_lng" value="<?= htmlspecialchars($oldInput['longitude'] ?? $event['longitude'] ?? '') ?>">
    </div>

    <!-- Host as Group (Optional) -->
    <?php if (!empty($myGroups)): ?>
    <div class="govuk-form-group <?= isset($errors['group_id']) ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="group_id">
            Host as hub <span class="govuk-hint govuk-!-display-inline">(optional)</span>
        </label>
        <div id="group_id-hint" class="govuk-hint">Leave blank for a personal event</div>
        <?php if (isset($errors['group_id'])): ?>
            <p id="group_id-error" class="govuk-error-message">
                <span class="govuk-visually-hidden">Error:</span>
                <?= htmlspecialchars($errors['group_id']) ?>
            </p>
        <?php endif; ?>
        <select class="govuk-select <?= isset($errors['group_id']) ? 'govuk-select--error' : '' ?>"
                id="group_id"
                name="group_id"
                aria-describedby="group_id-hint">
            <option value="">Personal event</option>
            <?php foreach ($myGroups as $group): ?>
                <option value="<?= $group['id'] ?>" <?= (($oldInput['group_id'] ?? $selectedGroupId ?? $event['group_id'] ?? '') == $group['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($group['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <!-- SDG Selection (Edit mode only) -->
    <?php if ($isEdit): ?>
    <details class="govuk-details govuk-!-margin-bottom-6" data-module="govuk-details">
        <summary class="govuk-details__summary">
            <span class="govuk-details__summary-text">
                <i class="fa-solid fa-globe govuk-!-margin-right-1" aria-hidden="true"></i>
                UN Sustainable Development Goals (optional)
            </span>
        </summary>
        <div class="govuk-details__text">
            <p class="govuk-body-s">Select the goals your event supports.</p>
            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                <?php
                $sdgGoals = [
                    1 => 'No Poverty', 2 => 'Zero Hunger', 3 => 'Good Health',
                    4 => 'Quality Education', 5 => 'Gender Equality', 6 => 'Clean Water',
                    7 => 'Clean Energy', 8 => 'Decent Work', 9 => 'Industry Innovation',
                    10 => 'Reduced Inequalities', 11 => 'Sustainable Cities', 12 => 'Responsible Consumption',
                    13 => 'Climate Action', 14 => 'Life Below Water', 15 => 'Life on Land',
                    16 => 'Peace & Justice', 17 => 'Partnerships'
                ];
                $selectedSdgs = $oldInput['sdg_goals'] ?? (!empty($event['sdg_goals']) ? json_decode($event['sdg_goals'], true) : []);
                $selectedSdgs = is_array($selectedSdgs) ? $selectedSdgs : [];

                foreach ($sdgGoals as $goalNum => $label):
                    $isSelected = in_array($goalNum, $selectedSdgs);
                ?>
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="sdg-<?= $goalNum ?>" name="sdg_goals[]" type="checkbox" value="<?= $goalNum ?>" <?= $isSelected ? 'checked' : '' ?>>
                        <label class="govuk-label govuk-checkboxes__label" for="sdg-<?= $goalNum ?>">
                            SDG <?= $goalNum ?>: <?= htmlspecialchars($label) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
    <?php endif; ?>

    <!-- SEO Settings (Edit mode only) -->
    <?php if ($isEdit && !empty($event['id'])): ?>
        <?php
        $seo = $seo ?? \Nexus\Models\SeoMetadata::get('event', $event['id']);
        $entityTitle = $event['title'] ?? '';
        $entityUrl = $basePath . '/events/' . $event['id'];
        require __DIR__ . '/../../partials/seo-accordion.php';
        ?>
    <?php endif; ?>

    <!-- Submit Button -->
    <button type="submit" class="govuk-button" data-module="govuk-button">
        <?= htmlspecialchars($submitButtonText) ?>
    </button>

</form>

<?php if ($isEdit): ?>
<script src="/assets/js/civicone-events-form.js?v=<?= time() ?>"></script>
<?php endif; ?>
