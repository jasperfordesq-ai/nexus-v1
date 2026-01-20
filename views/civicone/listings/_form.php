<?php
/**
 * CivicOne Listings Form Partial - WCAG 2.1 AA Compliant
 * Shared form fields for create.php and edit.php
 *
 * GOV.UK Form Pattern: https://design-system.service.gov.uk/patterns/
 *
 * Expected variables:
 * - $basePath (string): Base path for URLs
 * - $listing (array|null): Listing data for edit mode, null for create mode
 * - $categories (array): Available categories
 * - $errors (array|null): Validation errors from server
 * - $formAction (string): Form submission URL
 * - $submitLabel (string): Submit button label
 * - $isEdit (bool): Whether in edit mode
 */

$isEdit = isset($listing) && !empty($listing['id']);
$listing = $listing ?? [];
$errors = $errors ?? [];
?>

<!-- GOV.UK Form Pattern -->
<form action="<?= htmlspecialchars($formAction) ?>"
      method="POST"
      enctype="multipart/form-data"
      novalidate
      class="civicone--govuk">
    <?= Nexus\Core\Csrf::input() ?>

    <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= htmlspecialchars($listing['id']) ?>">
    <?php endif; ?>

    <!-- Type Selection - GOV.UK Radios Component -->
    <?php
    $typeValue = $listing['type'] ?? ($_GET['type'] ?? 'offer');
    $typeError = $errors['type'] ?? null;
    ?>
    <div class="civicone-form-group <?= $typeError ? 'civicone-form-group--error' : '' ?>">
        <fieldset class="civicone-fieldset" aria-describedby="type-hint <?= $typeError ? 'type-error' : '' ?>">
            <legend class="civicone-fieldset__legend civicone-fieldset__legend--m">
                <h1 class="civicone-fieldset__heading">
                    What do you want to do?
                </h1>
            </legend>

            <div id="type-hint" class="civicone-hint">
                Choose whether you're offering a skill or service, or requesting help from the community.
            </div>

            <?php if ($typeError): ?>
            <p id="type-error" class="civicone-error-message">
                <span class="civicone-visually-hidden">Error:</span>
                <?= htmlspecialchars($typeError) ?>
            </p>
            <?php endif; ?>

            <div class="civicone-radios civicone-radios--large">
                <div class="civicone-radios__item">
                    <input class="civicone-radios__input"
                           id="type-offer"
                           name="type"
                           type="radio"
                           value="offer"
                           <?= $typeValue === 'offer' ? 'checked' : '' ?>
                           aria-describedby="type-offer-hint">
                    <label class="civicone-label civicone-radios__label" for="type-offer">
                        <span class="civicone-radios__label-text">Offer help or a service</span>
                    </label>
                    <div id="type-offer-hint" class="civicone-hint civicone-radios__hint">
                        I have skills, items, or services to share with the community
                    </div>
                </div>

                <div class="civicone-radios__item">
                    <input class="civicone-radios__input"
                           id="type-request"
                           name="type"
                           type="radio"
                           value="request"
                           <?= $typeValue === 'request' ? 'checked' : '' ?>
                           aria-describedby="type-request-hint">
                    <label class="civicone-label civicone-radios__label" for="type-request">
                        <span class="civicone-radios__label-text">Request help or a service</span>
                    </label>
                    <div id="type-request-hint" class="civicone-hint civicone-radios__hint">
                        I need assistance or support from the community
                    </div>
                </div>
            </div>
        </fieldset>
    </div>

    <!-- Category - GOV.UK Select Component -->
    <?php
    $categoryValue = $listing['category_id'] ?? '';
    $categoryError = $errors['category_id'] ?? null;
    ?>
    <div class="civicone-form-group <?= $categoryError ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="category_id">
            Category
        </label>
        <div id="category-hint" class="civicone-hint">
            Select the category that best describes your listing
        </div>

        <?php if ($categoryError): ?>
        <p id="category-error" class="civicone-error-message">
            <span class="civicone-visually-hidden">Error:</span>
            <?= htmlspecialchars($categoryError) ?>
        </p>
        <?php endif; ?>

        <select class="civicone-select <?= $categoryError ? 'civicone-select--error' : '' ?>"
                id="category_id"
                name="category_id"
                aria-describedby="category-hint <?= $categoryError ? 'category-error' : '' ?>">
            <option value="">Select a category</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['id']) ?>"
                        <?= $categoryValue == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Title - GOV.UK Text Input Component -->
    <?php
    $titleValue = $listing['title'] ?? '';
    $titleError = $errors['title'] ?? null;
    ?>
    <div class="civicone-form-group <?= $titleError ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="title">
            Title
        </label>
        <div id="title-hint" class="civicone-hint">
            A clear, descriptive title that summarizes your offer or request
        </div>

        <?php if ($titleError): ?>
        <p id="title-error" class="civicone-error-message">
            <span class="civicone-visually-hidden">Error:</span>
            <?= htmlspecialchars($titleError) ?>
        </p>
        <?php endif; ?>

        <input class="civicone-input <?= $titleError ? 'civicone-input--error' : '' ?>"
               id="title"
               name="title"
               type="text"
               value="<?= htmlspecialchars($titleValue) ?>"
               aria-describedby="title-hint <?= $titleError ? 'title-error' : '' ?>">
    </div>

    <!-- Description - GOV.UK Textarea Component -->
    <?php
    $descriptionValue = $listing['description'] ?? '';
    $descriptionError = $errors['description'] ?? null;
    ?>
    <div class="civicone-form-group <?= $descriptionError ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="description">
            Description
        </label>
        <div id="description-hint" class="civicone-hint">
            Provide full details about what you're offering or what help you need. Include any relevant experience, qualifications, or specific requirements.
        </div>

        <?php if ($descriptionError): ?>
        <p id="description-error" class="civicone-error-message">
            <span class="civicone-visually-hidden">Error:</span>
            <?= htmlspecialchars($descriptionError) ?>
        </p>
        <?php endif; ?>

        <textarea class="civicone-textarea <?= $descriptionError ? 'civicone-textarea--error' : '' ?>"
                  id="description"
                  name="description"
                  rows="8"
                  aria-describedby="description-hint <?= $descriptionError ? 'description-error' : '' ?>"><?= htmlspecialchars($descriptionValue) ?></textarea>
    </div>

    <!-- Location - GOV.UK Text Input Component (with Mapbox integration) -->
    <?php
    $locationValue = $listing['location'] ?? '';
    $latitudeValue = $listing['latitude'] ?? '';
    $longitudeValue = $listing['longitude'] ?? '';
    $locationError = $errors['location'] ?? null;
    ?>
    <div class="civicone-form-group <?= $locationError ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="location">
            Location (optional)
        </label>
        <div id="location-hint" class="civicone-hint">
            City or area where this offer or request is relevant. This helps members find local opportunities.
        </div>

        <?php if ($locationError): ?>
        <p id="location-error" class="civicone-error-message">
            <span class="civicone-visually-hidden">Error:</span>
            <?= htmlspecialchars($locationError) ?>
        </p>
        <?php endif; ?>

        <input class="civicone-input civicone-input--width-20 mapbox-location-input-v2 <?= $locationError ? 'civicone-input--error' : '' ?>"
               id="location"
               name="location"
               type="text"
               value="<?= htmlspecialchars($locationValue) ?>"
               aria-describedby="location-hint <?= $locationError ? 'location-error' : '' ?>">

        <input type="hidden" name="latitude" value="<?= htmlspecialchars($latitudeValue) ?>">
        <input type="hidden" name="longitude" value="<?= htmlspecialchars($longitudeValue) ?>">
    </div>

    <!-- Image Upload - GOV.UK File Upload Component -->
    <?php
    $imageError = $errors['image'] ?? null;
    $hasImage = !empty($listing['image_url']);
    ?>
    <div class="civicone-form-group <?= $imageError ? 'civicone-form-group--error' : '' ?>">
        <label class="civicone-label" for="image">
            Upload an image (optional)
        </label>
        <div id="image-hint" class="civicone-hint">
            An image can help illustrate your listing. Maximum file size 5MB. Accepted formats: JPG, PNG, GIF.
        </div>

        <?php if ($imageError): ?>
        <p id="image-error" class="civicone-error-message">
            <span class="civicone-visually-hidden">Error:</span>
            <?= htmlspecialchars($imageError) ?>
        </p>
        <?php endif; ?>

        <?php if ($hasImage): ?>
        <div class="civicone-form-group__current-image">
            <img src="<?= htmlspecialchars($listing['image_url']) ?>"
                 alt="Current listing image"
                 class="civicone-form-group__current-image-preview">
            <p class="civicone-body-s">Current image (upload a new file to replace)</p>
        </div>
        <?php endif; ?>

        <input class="civicone-file-upload <?= $imageError ? 'civicone-file-upload--error' : '' ?>"
               id="image"
               name="image"
               type="file"
               accept="image/jpeg,image/png,image/gif"
               aria-describedby="image-hint <?= $imageError ? 'image-error' : '' ?>">
    </div>

    <!-- Submit Button - GOV.UK Button Component -->
    <div class="civicone-button-group">
        <button type="submit" class="civicone-button" data-module="civicone-button">
            <?= htmlspecialchars($submitLabel) ?>
        </button>

        <a href="<?= $isEdit ? $basePath . '/listings/' . $listing['id'] : $basePath . '/listings' ?>"
           class="civicone-link">
            Cancel
        </a>
    </div>
</form>
