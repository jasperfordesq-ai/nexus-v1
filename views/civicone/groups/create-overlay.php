<?php
/**
 * CivicOne Groups Create Overlay - Full-Screen Modal
 * Overlay Template: Group Creation Modal (Section 10.13)
 * Inspired by /compose pattern - Desktop modal, mobile full-screen
 * WCAG 2.1 AA Compliant
 *
 * Features:
 * - Full-screen overlay (desktop modal, mobile full-screen)
 * - Group type selector (pills navigation)
 * - Permission-aware: Hub type only for admins
 * - Location picker with map
 * - Image upload with preview
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenantId = \Nexus\Core\TenantContext::getId();
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
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
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $pageTitle ?? 'Create Group' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/purged/civicone-groups-create-overlay.min.css?v=<?= time() ?>">
    <script>
        // Pass basePath to JS
        window.TENANT_BASE_PATH = '<?= $basePath ?>';
    </script>
</head>
<body>
    <div class="create-overlay-backdrop" id="overlayBackdrop">
        <div class="create-overlay-container">
            <!-- Header -->
            <div class="create-header">
                <h1>Create New Group</h1>
                <button type="button" class="create-close-btn" onclick="closeOverlay()" aria-label="Close overlay">
                    <i class="fa-solid fa-xmark fa-lg" aria-hidden="true"></i>
                </button>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error alert-inline-margin" role="alert">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success alert-inline-margin" role="alert">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <!-- Type Pills Navigation -->
            <?php if (count($groupTypes) > 1): ?>
            <nav role="navigation" aria-label="Group type selection" class="type-pills-nav">
                <?php foreach ($groupTypes as $index => $type): ?>
                <button type="button"
                        class="type-pill <?= $type['id'] == $defaultTypeId ? 'active' : '' ?>"
                        data-type-id="<?= $type['id'] ?>"
                        data-is-hub="<?= $type['is_hub'] ?>"
                        onclick="selectType(<?= $type['id'] ?>, <?= $type['is_hub'] ?>)"
                        aria-pressed="<?= $type['id'] == $defaultTypeId ? 'true' : 'false' ?>">
                    <i class="<?= htmlspecialchars($type['icon'] ?? 'fa-solid fa-layer-group') ?>" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($type['name']) ?></span>
                    <?php if ($type['is_hub']): ?>
                    <span class="admin-badge" aria-label="Admin only">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Admin
                    </span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <!-- Form Content -->
            <form id="createGroupForm" action="<?= $basePath ?>/groups/store" method="POST" enctype="multipart/form-data" class="create-content">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="type_id" id="typeIdInput" value="<?= $defaultTypeId ?>">

                <!-- Group Name -->
                <div class="form-field">
                    <label for="groupName" class="form-label required">Group Name</label>
                    <input type="text" name="name" id="groupName" class="form-input" placeholder="Enter group name..." required aria-required="true">
                </div>

                <!-- Description -->
                <div class="form-field">
                    <label for="groupDescription" class="form-label required">Description</label>
                    <textarea name="description" id="groupDescription" class="form-textarea" placeholder="What's this group about?" required aria-required="true" aria-describedby="descriptionHint"></textarea>
                    <p class="form-hint" id="descriptionHint">Tell people what this group is for and what they can expect.</p>
                </div>

                <!-- Location -->
                <div class="form-field">
                    <label for="groupLocation" class="form-label">Location</label>
                    <input type="text" name="location" id="groupLocation" class="form-input" placeholder="City, Region, or Address" aria-describedby="locationHint">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    <p class="form-hint" id="locationHint">Optional: Add a location to help people find your group.</p>
                </div>

                <!-- Visibility -->
                <div class="form-field">
                    <label for="groupVisibility" class="form-label">Privacy</label>
                    <select name="visibility" id="groupVisibility" class="form-select">
                        <option value="public">Public - Anyone can see and join</option>
                        <option value="private">Private - Members must request to join</option>
                    </select>
                </div>

                <!-- Group Image -->
                <div class="form-field">
                    <label class="form-label">Group Image</label>
                    <div class="image-upload-area" id="uploadArea" onclick="document.getElementById('imageFile').click()" role="button" tabindex="0" aria-label="Click to upload group image" onkeypress="if(event.key==='Enter'||event.key===' ')document.getElementById('imageFile').click()">
                        <i class="fa-solid fa-image" aria-hidden="true"></i>
                        <p class="upload-text">Click to upload an image</p>
                    </div>
                    <div class="image-preview" id="imagePreview">
                        <img id="previewImg" src="" alt="Group image preview" loading="lazy">
                        <button type="button" class="image-remove-btn" onclick="removeImage(event)" aria-label="Remove image">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                    <input type="file" name="image" id="imageFile" accept="image/*" class="file-input-hidden" onchange="previewImage(this)" aria-label="Group image file upload">
                </div>
            </form>

            <!-- Footer Actions -->
            <div class="create-footer">
                <button type="button" class="btn btn-cancel" onclick="closeOverlay()">Cancel</button>
                <button type="submit" form="createGroupForm" class="btn btn-primary" id="submitBtn">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Create Group
                </button>
            </div>
        </div>
    </div>

    <script src="/assets/js/civicone-groups-create-overlay.js?v=<?= time() ?>"></script>
</body>
</html>
