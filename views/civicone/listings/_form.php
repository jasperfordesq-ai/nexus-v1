<?php
/**
 * CivicOne Listings Form Partial
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
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

<!-- Error Summary -->
<?php if (!empty($errors)): ?>
<div class="govuk-error-summary govuk-!-margin-bottom-6" aria-labelledby="error-summary-title" role="alert" tabindex="-1" data-module="govuk-error-summary">
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

<form action="<?= htmlspecialchars($formAction) ?>" method="POST" enctype="multipart/form-data" novalidate>
    <?= \Nexus\Core\Csrf::input() ?>

    <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= htmlspecialchars($listing['id']) ?>">
    <?php endif; ?>

    <!-- Type Selection -->
    <?php
    $typeValue = $listing['type'] ?? ($_GET['type'] ?? 'offer');
    $typeError = $errors['type'] ?? null;
    ?>
    <div class="govuk-form-group <?= $typeError ? 'govuk-form-group--error' : '' ?>">
        <fieldset class="govuk-fieldset" aria-describedby="type-hint <?= $typeError ? 'type-error' : '' ?>">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--l">
                <h2 class="govuk-fieldset__heading">What do you want to do?</h2>
            </legend>

            <div id="type-hint" class="govuk-hint">
                Choose whether you're offering a skill or service, or requesting help from the community.
            </div>

            <?php if ($typeError): ?>
            <p id="type-error" class="govuk-error-message">
                <span class="govuk-visually-hidden">Error:</span>
                <?= htmlspecialchars($typeError) ?>
            </p>
            <?php endif; ?>

            <div class="govuk-radios" data-module="govuk-radios">
                <div class="govuk-radios__item">
                    <input class="govuk-radios__input" id="type-offer" name="type" type="radio" value="offer"
                           <?= $typeValue === 'offer' ? 'checked' : '' ?> aria-describedby="type-offer-hint">
                    <label class="govuk-label govuk-radios__label" for="type-offer">
                        <i class="fa-solid fa-hand-holding-heart govuk-!-margin-right-1" aria-hidden="true"></i>
                        Offer help or a service
                    </label>
                    <div id="type-offer-hint" class="govuk-hint govuk-radios__hint">
                        I have skills, items, or services to share with the community
                    </div>
                </div>

                <div class="govuk-radios__item">
                    <input class="govuk-radios__input" id="type-request" name="type" type="radio" value="request"
                           <?= $typeValue === 'request' ? 'checked' : '' ?> aria-describedby="type-request-hint">
                    <label class="govuk-label govuk-radios__label" for="type-request">
                        <i class="fa-solid fa-hand govuk-!-margin-right-1" aria-hidden="true"></i>
                        Request help or a service
                    </label>
                    <div id="type-request-hint" class="govuk-hint govuk-radios__hint">
                        I need assistance or support from the community
                    </div>
                </div>
            </div>
        </fieldset>
    </div>

    <!-- Category -->
    <?php
    $categoryValue = $listing['category_id'] ?? '';
    $categoryError = $errors['category_id'] ?? null;
    ?>
    <div class="govuk-form-group <?= $categoryError ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="category_id">Category</label>
        <div id="category-hint" class="govuk-hint">Select the category that best describes your listing</div>

        <?php if ($categoryError): ?>
        <p id="category-error" class="govuk-error-message">
            <span class="govuk-visually-hidden">Error:</span>
            <?= htmlspecialchars($categoryError) ?>
        </p>
        <?php endif; ?>

        <select class="govuk-select <?= $categoryError ? 'govuk-select--error' : '' ?>"
                id="category_id" name="category_id" aria-describedby="category-hint <?= $categoryError ? 'category-error' : '' ?>">
            <option value="">Select a category</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['id']) ?>" <?= $categoryValue == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Title -->
    <?php
    $titleValue = $listing['title'] ?? '';
    $titleError = $errors['title'] ?? null;
    ?>
    <div class="govuk-form-group <?= $titleError ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="title">Title</label>
        <div id="title-hint" class="govuk-hint">A clear, descriptive title that summarizes your offer or request</div>

        <?php if ($titleError): ?>
        <p id="title-error" class="govuk-error-message">
            <span class="govuk-visually-hidden">Error:</span>
            <?= htmlspecialchars($titleError) ?>
        </p>
        <?php endif; ?>

        <input class="govuk-input <?= $titleError ? 'govuk-input--error' : '' ?>"
               id="title" name="title" type="text" value="<?= htmlspecialchars($titleValue) ?>"
               aria-describedby="title-hint <?= $titleError ? 'title-error' : '' ?>">
    </div>

    <!-- Description -->
    <?php
    $descriptionValue = $listing['description'] ?? '';
    $descriptionError = $errors['description'] ?? null;
    ?>
    <div class="govuk-form-group <?= $descriptionError ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="description">Description</label>
        <div id="description-hint" class="govuk-hint">
            Provide full details about what you're offering or what help you need. Include any relevant experience, qualifications, or specific requirements.
        </div>

        <?php if ($descriptionError): ?>
        <p id="description-error" class="govuk-error-message">
            <span class="govuk-visually-hidden">Error:</span>
            <?= htmlspecialchars($descriptionError) ?>
        </p>
        <?php endif; ?>

        <textarea class="govuk-textarea <?= $descriptionError ? 'govuk-textarea--error' : '' ?>"
                  id="description" name="description" rows="8"
                  aria-describedby="description-hint <?= $descriptionError ? 'description-error' : '' ?>"><?= htmlspecialchars($descriptionValue) ?></textarea>
    </div>

    <!-- Location -->
    <?php
    $locationValue = $listing['location'] ?? '';
    $latitudeValue = $listing['latitude'] ?? '';
    $longitudeValue = $listing['longitude'] ?? '';
    $locationError = $errors['location'] ?? null;
    ?>
    <div class="govuk-form-group <?= $locationError ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="location">Location (optional)</label>
        <div id="location-hint" class="govuk-hint">City or area where this offer or request is relevant</div>

        <?php if ($locationError): ?>
        <p id="location-error" class="govuk-error-message">
            <span class="govuk-visually-hidden">Error:</span>
            <?= htmlspecialchars($locationError) ?>
        </p>
        <?php endif; ?>

        <input class="govuk-input govuk-input--width-20 mapbox-location-input-v2 <?= $locationError ? 'govuk-input--error' : '' ?>"
               id="location" name="location" type="text" value="<?= htmlspecialchars($locationValue) ?>"
               aria-describedby="location-hint <?= $locationError ? 'location-error' : '' ?>">

        <input type="hidden" name="latitude" value="<?= htmlspecialchars($latitudeValue) ?>">
        <input type="hidden" name="longitude" value="<?= htmlspecialchars($longitudeValue) ?>">
    </div>

    <!-- Image Upload -->
    <?php
    $imageError = $errors['image'] ?? null;
    $hasImage = !empty($listing['image_url']);
    ?>
    <div class="govuk-form-group <?= $imageError ? 'govuk-form-group--error' : '' ?>">
        <label class="govuk-label" for="image">Upload an image (optional)</label>
        <div id="image-hint" class="govuk-hint">An image can help illustrate your listing. Maximum 5MB. JPG, PNG, GIF accepted.</div>

        <?php if ($imageError): ?>
        <p id="image-error" class="govuk-error-message">
            <span class="govuk-visually-hidden">Error:</span>
            <?= htmlspecialchars($imageError) ?>
        </p>
        <?php endif; ?>

        <?php if ($hasImage): ?>
        <div class="govuk-!-margin-bottom-3">
            <img src="<?= htmlspecialchars($listing['image_url']) ?>" alt="Current listing image" style="max-width: 200px; height: auto;">
            <p class="govuk-body-s govuk-!-margin-top-1">Current image (upload a new file to replace)</p>
        </div>
        <?php endif; ?>

        <input class="govuk-file-upload <?= $imageError ? 'govuk-file-upload--error' : '' ?>"
               id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif"
               aria-describedby="image-hint <?= $imageError ? 'image-error' : '' ?>">
    </div>

    <!-- Submit -->
    <div class="govuk-button-group">
        <button type="submit" class="govuk-button" data-module="govuk-button">
            <?= htmlspecialchars($submitLabel) ?>
        </button>

        <a href="<?= $isEdit ? $basePath . '/listings/' . $listing['id'] : $basePath . '/listings' ?>" class="govuk-link">
            Cancel
        </a>
    </div>
</form>
