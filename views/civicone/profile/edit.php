<?php
// CivicOne View: Edit Profile - GOV.UK Template D (Form Pattern)
// ================================================================
// Pattern: Template D - Form with validation, Error summary
// WCAG 2.1 AA Compliant
// Refactored: 2026-01-20

$hTitle = 'Edit Profile';
$hSubtitle = 'Update your personal information';
$hType = 'Profile';

// Get TinyMCE API key from .env
$tinymceApiKey = 'no-api-key';
$envPath = dirname(__DIR__, 3) . '/.env';
if (file_exists($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, 'TINYMCE_API_KEY=') === 0) {
            $tinymceApiKey = trim(substr($line, 16), '"\'');
            break;
        }
    }
}

// Handle form errors from session
$errors = $_SESSION['form_errors'] ?? [];
$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old_input']);

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
$displayName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper">

        <!-- Breadcrumbs (GOV.UK Template D requirement) -->
        <nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
            <ol class="govuk-breadcrumbs__list">
                <li class="govuk-breadcrumbs__list-item">
                    <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
                </li>
                <li class="govuk-breadcrumbs__list-item">
                    <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/members">Members</a>
                </li>
                <li class="govuk-breadcrumbs__list-item">
                    <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/profile/<?= $user['id'] ?>"><?= $displayName ?></a>
                </li>
                <li class="govuk-breadcrumbs__list-item" aria-current="page">
                    Edit Profile
                </li>
            </ol>
        </nav>
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <!-- Error Summary -->
                <?php if (!empty($errors)): ?>
                <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" tabindex="-1" data-module="govuk-error-summary">
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

                <h1 class="govuk-heading-xl">Edit your profile</h1>

                <form action="<?= $basePath ?>/profile/update" method="POST" enctype="multipart/form-data" novalidate>
                    <?= Nexus\Core\Csrf::input() ?>

                    <!-- Profile Picture -->
                    <div class="govuk-form-group <?= isset($errors['avatar']) ? 'govuk-form-group--error' : '' ?>">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">Profile picture</h2>
                            </legend>

                            <div class="govuk-hint" id="avatar-hint">
                                Upload a photo that's at least 400x400 pixels. JPG or PNG formats only.
                            </div>

                            <?php if (isset($errors['avatar'])): ?>
                                <p id="avatar-error" class="govuk-error-message">
                                    <span class="govuk-visually-hidden">Error:</span> <?= htmlspecialchars($errors['avatar']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="govuk-!-margin-bottom-4">
                                <img src="<?= htmlspecialchars($user['avatar_url'] ?? '/assets/images/default-avatar.svg') ?>"
                                     alt="Current profile photo"
                                     id="avatar-preview"
                                     style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #b1b4b6;">
                            </div>

                            <input type="file"
                                   name="avatar"
                                   id="avatar"
                                   class="govuk-file-upload <?= isset($errors['avatar']) ? 'govuk-file-upload--error' : '' ?>"
                                   accept="image/jpeg,image/png"
                                   aria-describedby="<?= isset($errors['avatar']) ? 'avatar-error avatar-hint' : 'avatar-hint' ?>">
                        </fieldset>
                    </div>

                    <!-- Profile Type -->
                    <div class="govuk-form-group <?= isset($errors['profile_type']) ? 'govuk-form-group--error' : '' ?>">
                        <label class="govuk-label govuk-label--m" for="profile_type">
                            Profile type
                        </label>
                        <?php if (isset($errors['profile_type'])): ?>
                            <p id="profile_type-error" class="govuk-error-message">
                                <span class="govuk-visually-hidden">Error:</span> <?= htmlspecialchars($errors['profile_type']) ?>
                            </p>
                        <?php endif; ?>
                        <select name="profile_type"
                                id="profile_type"
                                class="govuk-select <?= isset($errors['profile_type']) ? 'govuk-select--error' : '' ?>"
                                aria-describedby="<?= isset($errors['profile_type']) ? 'profile_type-error' : '' ?>">
                            <option value="individual" <?= ($oldInput['profile_type'] ?? $user['profile_type'] ?? 'individual') === 'individual' ? 'selected' : '' ?>>Individual</option>
                            <option value="organisation" <?= ($oldInput['profile_type'] ?? $user['profile_type'] ?? 'individual') === 'organisation' ? 'selected' : '' ?>>Organisation</option>
                        </select>
                    </div>

                    <!-- Organisation Name (conditional) -->
                    <div id="org_field_container"
                         class="govuk-form-group <?= isset($errors['organization_name']) ? 'govuk-form-group--error' : '' ?>"
                         <?= ($oldInput['profile_type'] ?? $user['profile_type'] ?? 'individual') === 'organisation' ? '' : 'hidden' ?>>
                        <label class="govuk-label govuk-label--m" for="organization_name">
                            Organisation name
                        </label>
                        <?php if (isset($errors['organization_name'])): ?>
                            <p id="organization_name-error" class="govuk-error-message">
                                <span class="govuk-visually-hidden">Error:</span> <?= htmlspecialchars($errors['organization_name']) ?>
                            </p>
                        <?php endif; ?>
                        <input type="text"
                               name="organization_name"
                               id="organization_name"
                               class="govuk-input <?= isset($errors['organization_name']) ? 'govuk-input--error' : '' ?>"
                               value="<?= htmlspecialchars($oldInput['organization_name'] ?? $user['organization_name'] ?? '') ?>"
                               aria-describedby="<?= isset($errors['organization_name']) ? 'organization_name-error' : '' ?>">
                    </div>

                    <!-- First Name -->
                    <div class="govuk-form-group <?= isset($errors['first_name']) ? 'govuk-form-group--error' : '' ?>">
                        <label class="govuk-label govuk-label--m" for="first_name">
                            First name
                        </label>
                        <?php if (isset($errors['first_name'])): ?>
                            <p id="first_name-error" class="govuk-error-message">
                                <span class="govuk-visually-hidden">Error:</span> <?= htmlspecialchars($errors['first_name']) ?>
                            </p>
                        <?php endif; ?>
                        <input type="text"
                               name="first_name"
                               id="first_name"
                               class="govuk-input <?= isset($errors['first_name']) ? 'govuk-input--error' : '' ?>"
                               value="<?= htmlspecialchars($oldInput['first_name'] ?? $user['first_name'] ?? '') ?>"
                               aria-describedby="<?= isset($errors['first_name']) ? 'first_name-error' : '' ?>"
                               required>
                    </div>

                    <!-- Last Name -->
                    <div class="govuk-form-group <?= isset($errors['last_name']) ? 'govuk-form-group--error' : '' ?>">
                        <label class="govuk-label govuk-label--m" for="last_name">
                            Last name
                        </label>
                        <?php if (isset($errors['last_name'])): ?>
                            <p id="last_name-error" class="govuk-error-message">
                                <span class="govuk-visually-hidden">Error:</span> <?= htmlspecialchars($errors['last_name']) ?>
                            </p>
                        <?php endif; ?>
                        <input type="text"
                               name="last_name"
                               id="last_name"
                               class="govuk-input <?= isset($errors['last_name']) ? 'govuk-input--error' : '' ?>"
                               value="<?= htmlspecialchars($oldInput['last_name'] ?? $user['last_name'] ?? '') ?>"
                               aria-describedby="<?= isset($errors['last_name']) ? 'last_name-error' : '' ?>"
                               required>
                    </div>

                    <!-- Location -->
                    <div class="govuk-form-group <?= isset($errors['location']) ? 'govuk-form-group--error' : '' ?>">
                        <label class="govuk-label govuk-label--m" for="location">
                            Location
                        </label>
                        <div id="location-hint" class="govuk-hint">
                            Your city or area
                        </div>
                        <?php if (isset($errors['location'])): ?>
                            <p id="location-error" class="govuk-error-message">
                                <span class="govuk-visually-hidden">Error:</span> <?= htmlspecialchars($errors['location']) ?>
                            </p>
                        <?php endif; ?>
                        <input type="text"
                               name="location"
                               id="location"
                               class="govuk-input <?= isset($errors['location']) ? 'govuk-input--error' : '' ?> mapbox-location-input-v2"
                               value="<?= htmlspecialchars($oldInput['location'] ?? $user['location'] ?? '') ?>"
                               aria-describedby="<?= isset($errors['location']) ? 'location-error location-hint' : 'location-hint' ?>">
                    </div>

                    <!-- Bio -->
                    <div class="govuk-form-group <?= isset($errors['bio']) ? 'govuk-form-group--error' : '' ?>">
                        <label class="govuk-label govuk-label--m" for="bio">
                            About you
                        </label>
                        <div id="bio-hint" class="govuk-hint">
                            Tell others about yourself. Share your skills, interests, or what brings you to the community.
                        </div>
                        <?php if (isset($errors['bio'])): ?>
                            <p id="bio-error" class="govuk-error-message">
                                <span class="govuk-visually-hidden">Error:</span> <?= htmlspecialchars($errors['bio']) ?>
                            </p>
                        <?php endif; ?>
                        <textarea name="bio"
                                  id="bio-editor"
                                  rows="5"
                                  class="govuk-textarea <?= isset($errors['bio']) ? 'govuk-textarea--error' : '' ?>"
                                  aria-describedby="<?= isset($errors['bio']) ? 'bio-error bio-hint' : 'bio-hint' ?>"><?= htmlspecialchars($oldInput['bio'] ?? $user['bio'] ?? '') ?></textarea>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="govuk-button-group">
                        <button type="submit" class="govuk-button" data-module="govuk-button">
                            Save changes
                        </button>
                        <a href="<?= $basePath ?>/profile/<?= $user['id'] ?>" class="govuk-link">
                            Cancel
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </main>
</div>

<!-- External CSS and JavaScript for profile edit (CLAUDE.md compliant) -->
<link rel="stylesheet" href="/assets/css/civicone-profile-edit.css">
<script src="https://cdn.tiny.cloud/1/<?= htmlspecialchars($tinymceApiKey) ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/assets/js/civicone-profile-edit.js"></script>
<script>
    // Initialize TinyMCE after external script loads
    if (typeof window.CivicProfileEdit !== 'undefined') {
        window.CivicProfileEdit.initializeTinyMCE('<?= htmlspecialchars($tinymceApiKey) ?>', <?= isset($errors['bio']) ? 'true' : 'false' ?>);

        <?php if (!empty($errors)): ?>
        // Focus error summary on page load if errors exist
        window.addEventListener('DOMContentLoaded', function() {
            window.CivicProfileEdit.focusErrorSummary();
        });
        <?php endif; ?>
    }
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
