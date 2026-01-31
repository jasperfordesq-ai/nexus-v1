<?php
/**
 * Groups Create Overlay - GOV.UK Design System
 * WCAG 2.1 AA Compliant Modal
 *
 * Features:
 * - Full-screen overlay (desktop modal, mobile full-screen)
 * - Group type selector (pills navigation)
 * - Permission-aware: Hub type only for admins
 * - Location picker
 * - Image upload with preview
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenantId = \Nexus\Core\TenantContext::getId();
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';
$csrfToken = \Nexus\Core\Csrf::generate();

// Get flash messages
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

// Group types passed from controller
$groupTypes = $groupTypes ?? [];
$defaultTypeId = $defaultTypeId ?? null;
$isAdmin = $isAdmin ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $pageTitle ?? 'Create Group' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/govuk-frontend-5.14.0/govuk-frontend.min.css">
    <link rel="stylesheet" href="/assets/css/design-tokens.min.css">
    <link rel="stylesheet" href="/assets/css/groups-edit-overlay.min.css">
    <style>
        /* Minimal reset - everything else in external CSS */
        * { box-sizing: border-box; margin: 0; padding: 0; }
    </style>
    <script>
        window.TENANT_BASE_PATH = '<?= $basePath ?>';
    </script>
</head>
<body class="govuk-overlay-body">
    <div class="govuk-overlay-container" role="dialog" aria-modal="true" aria-labelledby="overlay-title">
        <!-- Header -->
        <div class="govuk-overlay-header">
            <button type="button" class="govuk-overlay-close-btn" onclick="closeOverlay()" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <h1 id="overlay-title">
                <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                Create New Group
            </h1>
        </div>

        <?php if ($error): ?>
        <div class="govuk-error-summary" role="alert" aria-labelledby="error-summary-title" tabindex="-1" data-module="govuk-error-summary">
            <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
            <div class="govuk-error-summary__body">
                <p class="govuk-body"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="success-banner-title" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="success-banner-title">Success</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading"><?= htmlspecialchars($success) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Type Pills Navigation -->
        <?php if (count($groupTypes) > 1): ?>
        <nav class="govuk-overlay-type-pills" role="navigation" aria-label="Group type selection">
            <?php foreach ($groupTypes as $index => $type): ?>
            <button type="button"
                    class="govuk-overlay-type-pill <?= $type['id'] == $defaultTypeId ? 'active' : '' ?>"
                    data-type-id="<?= $type['id'] ?>"
                    data-is-hub="<?= $type['is_hub'] ?>"
                    onclick="selectType(<?= $type['id'] ?>, <?= $type['is_hub'] ?>)"
                    aria-pressed="<?= $type['id'] == $defaultTypeId ? 'true' : 'false' ?>">
                <i class="<?= htmlspecialchars($type['icon'] ?? 'fa-solid fa-layer-group') ?>" aria-hidden="true"></i>
                <span><?= htmlspecialchars($type['name']) ?></span>
                <?php if ($type['is_hub']): ?>
                <span class="govuk-overlay-admin-badge">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Admin
                </span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <!-- Form Content -->
        <form id="createGroupForm" action="<?= $basePath ?>/groups/store" method="POST" enctype="multipart/form-data" class="govuk-overlay-content">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="type_id" id="typeIdInput" value="<?= $defaultTypeId ?>">

            <!-- Group Name -->
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="groupName">
                    Group Name
                </label>
                <input type="text" name="name" id="groupName" class="govuk-input" placeholder="Enter group name..." required aria-required="true">
            </div>

            <!-- Description -->
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="groupDescription">
                    Description
                </label>
                <div class="govuk-hint" id="descriptionHint">Tell people what this group is for and what they can expect.</div>
                <textarea name="description" id="groupDescription" class="govuk-textarea" rows="4" placeholder="What's this group about?" required aria-required="true" aria-describedby="descriptionHint"></textarea>
            </div>

            <!-- Location -->
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="groupLocation">
                    Location
                    <span class="govuk-hint civicone-hint-inline">(optional)</span>
                </label>
                <div class="govuk-hint" id="locationHint">Add a location to help people find your group.</div>
                <input type="text" name="location" id="groupLocation" class="govuk-input" placeholder="City, Region, or Address" aria-describedby="locationHint">
                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">
            </div>

            <!-- Visibility -->
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="groupVisibility">
                    Privacy
                </label>
                <select name="visibility" id="groupVisibility" class="govuk-select">
                    <option value="public">Public - Anyone can see and join</option>
                    <option value="private">Private - Members must request to join</option>
                </select>
            </div>

            <!-- Group Image -->
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m">
                    Group Image
                    <span class="govuk-hint civicone-hint-inline">(optional)</span>
                </label>
                <div class="govuk-overlay-image-upload-area" id="uploadArea" onclick="document.getElementById('imageFile').click()" role="button" tabindex="0" aria-label="Click to upload group image" onkeypress="if(event.key==='Enter'||event.key===' ')document.getElementById('imageFile').click()">
                    <i class="fa-solid fa-image" aria-hidden="true"></i>
                    <p class="govuk-body">Click to upload an image</p>
                </div>
                <div class="govuk-overlay-image-preview" id="imagePreview">
                    <img id="previewImg" src="" alt="Group image preview" loading="lazy">
                    <button type="button" class="remove-btn" onclick="removeImage(event)" aria-label="Remove image">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <input type="file" name="image" id="imageFile" accept="image/*" class="govuk-file-input-hidden" onchange="previewImage(this)" aria-label="Group image file upload">
            </div>
        </form>

        <!-- Footer Actions -->
        <div class="govuk-overlay-footer">
            <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="closeOverlay()">
                Cancel
            </button>
            <button type="submit" form="createGroupForm" class="govuk-button" data-module="govuk-button" id="submitBtn">
                <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                Create Group
            </button>
        </div>
    </div>

    <!-- Overlay functions in external JS -->
    <script src="/assets/js/civicone-groups-create-overlay.js"></script>
</body>
</html>
