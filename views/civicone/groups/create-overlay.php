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
    <link rel="stylesheet" href="/assets/css/govuk-frontend.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "GDS Transport", arial, sans-serif;
            background: rgba(11, 12, 12, 0.7);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .overlay-container {
            background: white;
            max-width: 640px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 0;
        }
        .overlay-header {
            background: #1d70b8;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .overlay-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        .close-btn {
            background: transparent;
            border: 2px solid white;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-btn:hover { background: rgba(255,255,255,0.1); }
        .type-pills {
            display: flex;
            gap: 8px;
            padding: 16px 20px;
            background: #f3f2f1;
            border-bottom: 1px solid #b1b4b6;
            flex-wrap: wrap;
        }
        .type-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: white;
            border: 2px solid #b1b4b6;
            color: #0b0c0c;
            font-size: 16px;
            font-weight: 400;
            cursor: pointer;
            transition: all 0.15s;
        }
        .type-pill:hover { border-color: #0b0c0c; }
        .type-pill.active {
            background: #1d70b8;
            border-color: #1d70b8;
            color: white;
        }
        .type-pill .admin-badge {
            background: #912b88;
            color: white;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: 700;
        }
        .overlay-content { padding: 20px; }
        .overlay-footer {
            padding: 20px;
            background: #f3f2f1;
            border-top: 1px solid #b1b4b6;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            position: sticky;
            bottom: 0;
        }
        .image-upload-area {
            border: 3px dashed #b1b4b6;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s;
            background: #f3f2f1;
        }
        .image-upload-area:hover { border-color: #1d70b8; background: white; }
        .image-upload-area i { font-size: 48px; color: #505a5f; margin-bottom: 12px; }
        .image-preview { display: none; position: relative; margin-top: 12px; }
        .image-preview.show { display: block; }
        .image-preview img { width: 100%; max-height: 200px; object-fit: cover; border: 1px solid #b1b4b6; }
        .image-preview .remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #d4351c;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .file-input-hidden { display: none; }
        @media (max-width: 640px) {
            body { padding: 0; }
            .overlay-container { max-height: 100vh; height: 100vh; border-radius: 0; }
        }
    </style>
    <script>
        window.TENANT_BASE_PATH = '<?= $basePath ?>';
    </script>
</head>
<body>
    <div class="overlay-container" role="dialog" aria-modal="true" aria-labelledby="overlay-title">
        <!-- Header -->
        <div class="overlay-header">
            <h1 id="overlay-title">
                <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                Create New Group
            </h1>
            <button type="button" class="close-btn" onclick="closeOverlay()" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
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
        <nav class="type-pills" role="navigation" aria-label="Group type selection">
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
                <span class="admin-badge">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Admin
                </span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <!-- Form Content -->
        <form id="createGroupForm" action="<?= $basePath ?>/groups/store" method="POST" enctype="multipart/form-data" class="overlay-content">
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
                    <span class="govuk-hint" style="display: inline; font-size: 16px;">(optional)</span>
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
                    <span class="govuk-hint" style="display: inline; font-size: 16px;">(optional)</span>
                </label>
                <div class="image-upload-area" id="uploadArea" onclick="document.getElementById('imageFile').click()" role="button" tabindex="0" aria-label="Click to upload group image" onkeypress="if(event.key==='Enter'||event.key===' ')document.getElementById('imageFile').click()">
                    <i class="fa-solid fa-image" aria-hidden="true"></i>
                    <p class="govuk-body">Click to upload an image</p>
                </div>
                <div class="image-preview" id="imagePreview">
                    <img id="previewImg" src="" alt="Group image preview" loading="lazy">
                    <button type="button" class="remove-btn" onclick="removeImage(event)" aria-label="Remove image">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <input type="file" name="image" id="imageFile" accept="image/*" class="file-input-hidden" onchange="previewImage(this)" aria-label="Group image file upload">
            </div>
        </form>

        <!-- Footer Actions -->
        <div class="overlay-footer">
            <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" onclick="closeOverlay()">
                Cancel
            </button>
            <button type="submit" form="createGroupForm" class="govuk-button" data-module="govuk-button" id="submitBtn">
                <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                Create Group
            </button>
        </div>
    </div>

    <script>
        function closeOverlay() {
            window.history.back();
        }

        function selectType(typeId, isHub) {
            document.getElementById('typeIdInput').value = typeId;
            document.querySelectorAll('.type-pill').forEach(pill => {
                const isActive = pill.dataset.typeId == typeId;
                pill.classList.toggle('active', isActive);
                pill.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').classList.add('show');
                    document.getElementById('uploadArea').style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage(event) {
            event.stopPropagation();
            document.getElementById('imageFile').value = '';
            document.getElementById('imagePreview').classList.remove('show');
            document.getElementById('uploadArea').style.display = 'block';
        }

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeOverlay();
        });

        // Click outside to close on desktop
        document.body.addEventListener('click', function(e) {
            if (e.target === document.body) closeOverlay();
        });
    </script>
</body>
</html>
