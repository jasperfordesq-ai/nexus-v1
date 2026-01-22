<?php
/**
 * CivicOne Groups Shared Form Partial
 * Used by: create.php and edit.php
 * Template D: Form/Flow (Section 10.5)
 * GOV.UK Form Pattern
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
    <div class="civicone-form-group <?= isset($errors['name']) ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="name">
            Hub name
        </label>
        <div id="name-hint" class="civicone-hint">
            <?= $isEdit ? 'The name of your hub' : 'For example, "West Cork Gardeners" or "Northside Book Club"' ?>
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
               value="<?= htmlspecialchars($oldInput['name'] ?? $group['name'] ?? '') ?>"
               aria-describedby="name-hint <?= isset($errors['name']) ? 'name-error' : '' ?>"
               required>
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
                  aria-describedby="description-hint <?= isset($errors['description']) ? 'description-error' : '' ?>"
                  required><?= htmlspecialchars($oldInput['description'] ?? $group['description'] ?? '') ?></textarea>
    </div>

    <!-- Visibility (Edit mode only) -->
    <?php if ($isEdit): ?>
    <div class="civicone-form-group">
        <fieldset class="civicone-fieldset">
            <legend class="civicone-fieldset__legend civicone-label">
                Visibility
            </legend>
            <div class="civicone-hint" id="visibility-hint">
                Choose who can join your hub
            </div>
            <div class="civicone-visibility-options" role="radiogroup" aria-describedby="visibility-hint">
                <label class="civicone-visibility-option">
                    <input type="radio" name="visibility" value="public" <?= ($oldInput['visibility'] ?? $group['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?>>
                    <div class="civicone-visibility-card">
                        <span class="civicone-visibility-icon" aria-hidden="true">🌍</span>
                        <div class="civicone-visibility-title">Public</div>
                        <div class="civicone-visibility-desc">Anyone can join instantly</div>
                    </div>
                </label>
                <label class="civicone-visibility-option">
                    <input type="radio" name="visibility" value="private" <?= ($oldInput['visibility'] ?? $group['visibility'] ?? 'public') === 'private' ? 'checked' : '' ?>>
                    <div class="civicone-visibility-card">
                        <span class="civicone-visibility-icon" aria-hidden="true">🔒</span>
                        <div class="civicone-visibility-title">Private</div>
                        <div class="civicone-visibility-desc">Requires approval to join</div>
                    </div>
                </label>
            </div>
        </fieldset>
    </div>
    <?php endif; ?>

    <!-- Featured Toggle (Site Admins Only - Edit mode only) -->
    <?php if ($isEdit && !empty($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
    <div class="civicone-form-group">
        <div class="civicone-checkboxes">
            <div class="civicone-checkboxes__item">
                <input class="civicone-checkboxes__input"
                       id="is_featured"
                       name="is_featured"
                       type="checkbox"
                       value="1"
                       <?= !empty($oldInput['is_featured']) || !empty($group['is_featured']) ? 'checked' : '' ?>>
                <label class="civicone-label civicone-checkboxes__label" for="is_featured">
                    Featured Hub
                </label>
            </div>
        </div>
        <div class="civicone-hint">
            Featured hubs appear in a special section at the top of the hubs page. Only site administrators can mark groups as featured.
        </div>
    </div>
    <?php endif; ?>

    <!-- Location (Optional) -->
    <div class="civicone-form-group <?= isset($errors['location']) ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="location">
            Location <span class="civicone-label-hint">(optional)</span>
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
               value="<?= htmlspecialchars($oldInput['location'] ?? $group['location'] ?? '') ?>"
               autocomplete="off"
               aria-describedby="location-hint <?= isset($errors['location']) ? 'location-error' : '' ?>">
        <input type="hidden" name="latitude" id="location_lat" value="<?= htmlspecialchars($oldInput['latitude'] ?? $group['latitude'] ?? '') ?>">
        <input type="hidden" name="longitude" id="location_lng" value="<?= htmlspecialchars($oldInput['longitude'] ?? $group['longitude'] ?? '') ?>">
    </div>

    <!-- Hub Avatar (Edit mode only) -->
    <?php if ($isEdit): ?>
    <div class="civicone-form-group">
        <label class="civicone-label" for="image">
            Hub avatar <span class="civicone-label-hint">(optional)</span>
        </label>
        <div id="image-hint" class="civicone-hint">
            Upload an image to represent your hub
        </div>
        <div class="civicone-file-input-wrapper">
            <input type="file" name="image" id="image" accept="image/*" aria-describedby="image-hint">
            <div class="civicone-file-input-label">
                <i class="fa-solid fa-image" aria-hidden="true"></i>
                <span>Choose avatar image</span>
            </div>
        </div>
        <?php if (!empty($group['image_url'])): ?>
            <div class="civicone-current-image-preview">
                <img src="<?= htmlspecialchars($group['image_url']) ?>" loading="lazy" alt="Current avatar">
                <div class="civicone-image-preview-info">
                    <span class="civicone-image-preview-label">Current Avatar</span>
                    <span class="civicone-image-preview-hint">Upload new to replace</span>
                </div>
                <label class="civicone-clear-image-btn" title="Remove avatar">
                    <input type="checkbox" name="clear_avatar" value="1" class="hidden">
                    <span class="clear-icon">×</span>
                </label>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Cover Image (Edit mode only) -->
    <?php if ($isEdit): ?>
    <div class="civicone-form-group">
        <label class="civicone-label" for="cover_image">
            Cover image <span class="civicone-label-hint">(optional)</span>
        </label>
        <div id="cover-image-hint" class="civicone-hint">
            Upload a banner image for your hub page
        </div>
        <div class="civicone-file-input-wrapper">
            <input type="file" name="cover_image" id="cover_image" accept="image/*" aria-describedby="cover-image-hint">
            <div class="civicone-file-input-label">
                <i class="fa-solid fa-panorama" aria-hidden="true"></i>
                <span>Choose cover image</span>
            </div>
        </div>
        <?php if (!empty($group['cover_image_url'])): ?>
            <div class="civicone-current-image-preview">
                <img src="<?= htmlspecialchars($group['cover_image_url']) ?>" loading="lazy" alt="Current cover" class="cover-img">
                <div class="civicone-image-preview-info">
                    <span class="civicone-image-preview-label">Current Cover</span>
                    <span class="civicone-image-preview-hint">Upload new to replace</span>
                </div>
                <label class="civicone-clear-image-btn" title="Remove cover">
                    <input type="checkbox" name="clear_cover" value="1" class="hidden">
                    <span class="clear-icon">×</span>
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
    <button type="submit" class="civicone-button" data-module="civicone-button">
        <?= htmlspecialchars($submitButtonText) ?>
    </button>

</form>
