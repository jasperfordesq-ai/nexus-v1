<?php
/**
 * CivicOne Groups Shared Form Partial
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 * Used by: create.php and edit.php
 *
 * Required variables:
 * - $formAction: URL to submit the form to
 * - $isEdit: boolean - true for edit mode, false for create mode
 * - $submitButtonText: text for the submit button
 *
 * Optional variables:
 * - $group: array - group data (required for edit mode)
 * - $errors: array - validation errors
 * - $oldInput: array - old input values
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$errors = $errors ?? [];
$oldInput = $oldInput ?? [];
$group = $group ?? [];
$isEdit = $isEdit ?? false;
?>

<form action="<?= htmlspecialchars($formAction) ?>" method="POST" <?= $isEdit ? 'enctype="multipart/form-data"' : '' ?> novalidate>
    <?= Nexus\Core\Csrf::input() ?>

    <?php if ($isEdit && !empty($group['id'])): ?>
        <input type="hidden" name="group_id" value="<?= htmlspecialchars($group['id']) ?>">
    <?php endif; ?>

    <!-- Hub Name -->
    <div class="govuk-form-group <?= isset($errors['name']) ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="name">
            Hub name
        </label>
        <div id="name-hint" class="govuk-hint">
            <?= $isEdit ? 'The name of your hub' : 'For example, "West Cork Gardeners" or "Northside Book Club"' ?>
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
               value="<?= htmlspecialchars($oldInput['name'] ?? $group['name'] ?? '') ?>"
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
                  required><?= htmlspecialchars($oldInput['description'] ?? $group['description'] ?? '') ?></textarea>
    </div>

    <!-- Visibility (Edit mode only) -->
    <?php if ($isEdit): ?>
    <div class="govuk-form-group">
        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">Visibility</h2>
            </legend>
            <div id="visibility-hint" class="govuk-hint">
                Choose who can join your hub
            </div>
            <div class="govuk-radios" data-module="govuk-radios" aria-describedby="visibility-hint">
                <div class="govuk-radios__item">
                    <input class="govuk-radios__input" type="radio" name="visibility" id="visibility-public" value="public" <?= ($oldInput['visibility'] ?? $group['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?>>
                    <label class="govuk-label govuk-radios__label" for="visibility-public">
                        <span aria-hidden="true">🌍</span> Public
                        <span class="govuk-hint govuk-radios__hint">Anyone can join instantly</span>
                    </label>
                </div>
                <div class="govuk-radios__item">
                    <input class="govuk-radios__input" type="radio" name="visibility" id="visibility-private" value="private" <?= ($oldInput['visibility'] ?? $group['visibility'] ?? 'public') === 'private' ? 'checked' : '' ?>>
                    <label class="govuk-label govuk-radios__label" for="visibility-private">
                        <span aria-hidden="true">🔒</span> Private
                        <span class="govuk-hint govuk-radios__hint">Requires approval to join</span>
                    </label>
                </div>
            </div>
        </fieldset>
    </div>
    <?php endif; ?>

    <!-- Featured Toggle (Site Admins Only - Edit mode only) -->
    <?php if ($isEdit && !empty($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
    <div class="govuk-form-group">
        <div class="govuk-checkboxes" data-module="govuk-checkboxes">
            <div class="govuk-checkboxes__item">
                <input class="govuk-checkboxes__input"
                       id="is_featured"
                       name="is_featured"
                       type="checkbox"
                       value="1"
                       <?= !empty($oldInput['is_featured']) || !empty($group['is_featured']) ? 'checked' : '' ?>>
                <label class="govuk-label govuk-checkboxes__label" for="is_featured">
                    <span aria-hidden="true">⭐</span> Featured Hub
                </label>
            </div>
        </div>
        <div class="govuk-hint">
            Featured hubs appear in a special section at the top of the hubs page. Only site administrators can mark groups as featured.
        </div>
    </div>
    <?php endif; ?>

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
               value="<?= htmlspecialchars($oldInput['location'] ?? $group['location'] ?? '') ?>"
               autocomplete="off"
               aria-describedby="location-hint <?= isset($errors['location']) ? 'location-error' : '' ?>">
        <input type="hidden" name="latitude" id="location_lat" value="<?= htmlspecialchars($oldInput['latitude'] ?? $group['latitude'] ?? '') ?>">
        <input type="hidden" name="longitude" id="location_lng" value="<?= htmlspecialchars($oldInput['longitude'] ?? $group['longitude'] ?? '') ?>">
    </div>

    <!-- Hub Avatar (Edit mode only) -->
    <?php if ($isEdit): ?>
    <div class="govuk-form-group">
        <label class="govuk-label" for="image">
            Hub avatar <span class="govuk-hint govuk-!-display-inline">(optional)</span>
        </label>
        <div id="image-hint" class="govuk-hint">
            Upload an image to represent your hub
        </div>
        <input class="govuk-file-upload" type="file" name="image" id="image" accept="image/*" aria-describedby="image-hint">
        <?php if (!empty($group['image_url'])): ?>
            <div class="govuk-!-margin-top-3 govuk-!-padding-3" style="border: 1px solid #b1b4b6; display: flex; align-items: center; gap: 1rem;">
                <img src="<?= htmlspecialchars($group['image_url']) ?>" loading="lazy" alt="Current avatar" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                <div>
                    <p class="govuk-body-s govuk-!-margin-bottom-0"><strong>Current Avatar</strong></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Upload new to replace</p>
                </div>
                <label class="govuk-body-s" style="margin-left: auto;">
                    <input type="checkbox" name="clear_avatar" value="1" class="govuk-checkboxes__input" style="width: auto;">
                    Remove
                </label>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Cover Image (Edit mode only) -->
    <?php if ($isEdit): ?>
    <div class="govuk-form-group">
        <label class="govuk-label" for="cover_image">
            Cover image <span class="govuk-hint govuk-!-display-inline">(optional)</span>
        </label>
        <div id="cover-image-hint" class="govuk-hint">
            Upload a banner image for your hub page
        </div>
        <input class="govuk-file-upload" type="file" name="cover_image" id="cover_image" accept="image/*" aria-describedby="cover-image-hint">
        <?php if (!empty($group['cover_image_url'])): ?>
            <div class="govuk-!-margin-top-3 govuk-!-padding-3" style="border: 1px solid #b1b4b6; display: flex; align-items: center; gap: 1rem;">
                <img src="<?= htmlspecialchars($group['cover_image_url']) ?>" loading="lazy" alt="Current cover" style="width: 120px; height: 60px; object-fit: cover; border-radius: 4px;">
                <div>
                    <p class="govuk-body-s govuk-!-margin-bottom-0"><strong>Current Cover</strong></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Upload new to replace</p>
                </div>
                <label class="govuk-body-s" style="margin-left: auto;">
                    <input type="checkbox" name="clear_cover" value="1" class="govuk-checkboxes__input" style="width: auto;">
                    Remove
                </label>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- SEO Settings Accordion (Edit mode only) -->
    <?php if ($isEdit && !empty($group['id'])): ?>
        <?php
        $seo = $seo ?? \Nexus\Models\SeoMetadata::get('group', $group['id']);
        $entityTitle = $group['name'] ?? '';
        $entityUrl = $basePath . '/groups/' . $group['id'];
        require __DIR__ . '/../../partials/seo-accordion.php';
        ?>
    <?php endif; ?>

    <!-- Submit Button -->
    <button type="submit" class="govuk-button" data-module="govuk-button">
        <?= htmlspecialchars($submitButtonText) ?>
    </button>

</form>
