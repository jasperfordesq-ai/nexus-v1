<?php
/**
 * CivicOne Events Shared Form Partial
 * Partial: Event Form (Section 10.12)
 * Used by: create.php and edit.php
 * GOV.UK Form Pattern with SDG selection
 * WCAG 2.1 AA Compliant
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
    <?= Nexus\Core\Csrf::input() ?>

    <?php if ($isEdit && !empty($event['id'])): ?>
        <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['id']) ?>">
    <?php endif; ?>

    <!-- Event Title -->
    <div class="civicone-form-group <?= isset($errors['title']) ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="title">
            Event title
        </label>
        <div id="title-hint" class="civicone-hint">
            <?= $isEdit ? 'The name of your event' : 'For example, "Community Garden Planting Day" or "Book Club Meeting"' ?>
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
               value="<?= htmlspecialchars($oldInput['title'] ?? $event['title'] ?? '') ?>"
               aria-describedby="title-hint <?= isset($errors['title']) ? 'title-error' : '' ?>"
               required>
    </div>

    <!-- Description -->
    <div class="civicone-form-group <?= isset($errors['description']) ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="description">
            Description
        </label>
        <div id="description-hint" class="civicone-hint">
            <?= $isEdit ? 'What will happen at this event?' : 'Explain what will happen at your event and who should attend' ?>
        </div>
        <?php if ($isEdit): ?>
        <div class="civicone-ai-generate-wrapper">
            <button type="button" class="civicone-button-secondary civicone-ai-generate-btn" id="aiGenerateBtn">
                <i class="fa-solid fa-sparkles" aria-hidden="true"></i>
                <span>Generate with AI</span>
            </button>
        </div>
        <?php endif; ?>
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
                  aria-describedby="description-hint <?= isset($errors['description']) ? 'description-error' : '' ?>"
                  required><?= htmlspecialchars($oldInput['description'] ?? $event['description'] ?? '') ?></textarea>
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
                <option value="<?= $cat['id'] ?>" <?= ($oldInput['category_id'] ?? $event['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
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

            <div class="civicone-dates-grid">
                <div class="civicone-form-group">
                    <label class="civicone-label" for="start_date">
                        Start date
                    </label>
                    <input class="civicone-input <?= isset($errors['start_date']) ? 'civicone-input--error' : '' ?>"
                           id="start_date"
                           name="start_date"
                           type="date"
                           value="<?= htmlspecialchars($oldInput['start_date'] ?? (!empty($event['start_time']) ? date('Y-m-d', strtotime($event['start_time'])) : '')) ?>"
                           aria-describedby="<?= isset($errors['start_date']) ? 'date-time-error' : '' ?>"
                           required>
                </div>

                <div class="civicone-form-group">
                    <label class="civicone-label" for="start_time">
                        Start time
                    </label>
                    <input class="civicone-input <?= isset($errors['start_time']) ? 'civicone-input--error' : '' ?>"
                           id="start_time"
                           name="start_time"
                           type="time"
                           value="<?= htmlspecialchars($oldInput['start_time'] ?? (!empty($event['start_time']) ? date('H:i', strtotime($event['start_time'])) : '')) ?>"
                           aria-describedby="<?= isset($errors['start_time']) ? 'date-time-error' : '' ?>"
                           required>
                </div>
            </div>

            <?php if ($isEdit): ?>
            <div class="civicone-dates-grid mt-15">
                <div class="civicone-form-group">
                    <label class="civicone-label" for="end_date">
                        End date <span class="civicone-label-hint">(optional)</span>
                    </label>
                    <input class="civicone-input <?= isset($errors['end_date']) ? 'civicone-input--error' : '' ?>"
                           id="end_date"
                           name="end_date"
                           type="date"
                           value="<?= htmlspecialchars($oldInput['end_date'] ?? (!empty($event['end_time']) ? date('Y-m-d', strtotime($event['end_time'])) : '')) ?>"
                           aria-describedby="<?= isset($errors['end_date']) ? 'end-date-error' : '' ?>">
                </div>

                <div class="civicone-form-group">
                    <label class="civicone-label" for="end_time">
                        End time <span class="civicone-label-hint">(optional)</span>
                    </label>
                    <input class="civicone-input <?= isset($errors['end_time']) ? 'civicone-input--error' : '' ?>"
                           id="end_time"
                           name="end_time"
                           type="time"
                           value="<?= htmlspecialchars($oldInput['end_time'] ?? (!empty($event['end_time']) ? date('H:i', strtotime($event['end_time'])) : '')) ?>"
                           aria-describedby="<?= isset($errors['end_time']) ? 'end-time-error' : '' ?>">
                </div>
            </div>
            <?php endif; ?>
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
               value="<?= htmlspecialchars($oldInput['location'] ?? $event['location'] ?? '') ?>"
               autocomplete="off"
               aria-describedby="location-hint <?= isset($errors['location']) ? 'location-error' : '' ?>"
               required>
        <input type="hidden" name="latitude" id="location_lat" value="<?= htmlspecialchars($oldInput['latitude'] ?? $event['latitude'] ?? '') ?>">
        <input type="hidden" name="longitude" id="location_lng" value="<?= htmlspecialchars($oldInput['longitude'] ?? $event['longitude'] ?? '') ?>">
    </div>

    <!-- Host as Group (Optional) -->
    <?php if (!empty($myGroups)): ?>
    <div class="civicone-form-group <?= isset($errors['group_id']) ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="group_id">
            Host as hub <span class="civicone-label-hint">(optional)</span>
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
                <option value="<?= $group['id'] ?>" <?= (($oldInput['group_id'] ?? $selectedGroupId ?? $event['group_id'] ?? '') == $group['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($group['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <!-- SDG Selection Accordion (Edit mode only) -->
    <?php if ($isEdit): ?>
    <details class="civicone-sdg-accordion" id="sdg-accordion">
        <summary class="civicone-sdg-summary">
            <span class="civicone-sdg-summary-label">
                <i class="fa-solid fa-globe" aria-hidden="true"></i>
                <span>UN Sustainable Development Goals</span>
                <span class="civicone-sdg-summary-hint">(optional)</span>
            </span>
            <span class="civicone-sdg-summary-icon" aria-hidden="true">▼</span>
        </summary>
        <div class="civicone-sdg-content">
            <p class="civicone-sdg-intro">
                Select the UN Sustainable Development Goals that your event supports. This helps members discover events aligned with their values.
            </p>
            <div class="civicone-sdg-grid" role="group" aria-label="Select Sustainable Development Goals">
                <?php
                $sdgGoals = [
                    1 => ['icon' => '🚫', 'label' => 'No Poverty'],
                    2 => ['icon' => '🌾', 'label' => 'Zero Hunger'],
                    3 => ['icon' => '❤️', 'label' => 'Good Health'],
                    4 => ['icon' => '📚', 'label' => 'Quality Education'],
                    5 => ['icon' => '⚖️', 'label' => 'Gender Equality'],
                    6 => ['icon' => '💧', 'label' => 'Clean Water'],
                    7 => ['icon' => '⚡', 'label' => 'Clean Energy'],
                    8 => ['icon' => '💼', 'label' => 'Decent Work'],
                    9 => ['icon' => '🏗️', 'label' => 'Industry Innovation'],
                    10 => ['icon' => '🤝', 'label' => 'Reduced Inequalities'],
                    11 => ['icon' => '🏙️', 'label' => 'Sustainable Cities'],
                    12 => ['icon' => '♻️', 'label' => 'Responsible Consumption'],
                    13 => ['icon' => '🌡️', 'label' => 'Climate Action'],
                    14 => ['icon' => '🌊', 'label' => 'Life Below Water'],
                    15 => ['icon' => '🌳', 'label' => 'Life on Land'],
                    16 => ['icon' => '⚖️', 'label' => 'Peace & Justice'],
                    17 => ['icon' => '🤝', 'label' => 'Partnerships']
                ];

                $selectedSdgs = $oldInput['sdg_goals'] ?? (!empty($event['sdg_goals']) ? json_decode($event['sdg_goals'], true) : []);
                $selectedSdgs = is_array($selectedSdgs) ? $selectedSdgs : [];

                foreach ($sdgGoals as $goalNum => $goal):
                    $isSelected = in_array($goalNum, $selectedSdgs);
                ?>
                    <label class="civicone-sdg-card <?= $isSelected ? 'selected' : '' ?>" data-sdg="<?= $goalNum ?>">
                        <input type="checkbox"
                               name="sdg_goals[]"
                               value="<?= $goalNum ?>"
                               <?= $isSelected ? 'checked' : '' ?>
                               aria-label="SDG <?= $goalNum ?>: <?= htmlspecialchars($goal['label']) ?>">
                        <span class="civicone-sdg-icon" aria-hidden="true"><?= $goal['icon'] ?></span>
                        <span class="civicone-sdg-label"><?= htmlspecialchars($goal['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
    <?php endif; ?>

    <!-- SEO Settings Accordion (Edit mode only) -->
    <?php if ($isEdit && !empty($event['id'])): ?>
        <?php
        $seo = $seo ?? \Nexus\Models\SeoMetadata::get('event', $event['id']);
        $entityTitle = $event['title'] ?? '';
        $entityUrl = $basePath . '/events/' . $event['id'];
        require __DIR__ . '/../../partials/seo-accordion.php';
        ?>
    <?php endif; ?>

    <!-- Submit Button -->
    <button type="submit" class="civicone-button" data-module="civicone-button">
        <?= htmlspecialchars($submitButtonText) ?>
    </button>

</form>

<?php if ($isEdit): ?>
<script src="/assets/js/civicone-events-form.js?v=<?= time() ?>"></script>
<?php endif; ?>
