<?php
/**
 * Modern Group Creation Overlay - Inspired by /compose
 *
 * Features:
 * - Full-screen overlay (desktop modal, mobile full-screen)
 * - Group type selector (pills navigation)
 * - Permission-aware: Hub type only for admins
 * - Location picker with map
 * - Image upload
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

</head>
<body>
    <div class="create-overlay-backdrop" id="overlayBackdrop">
        <div class="create-overlay-container">
            <!-- Header -->
            <div class="create-header">
                <h1>Create New Group</h1>
                <button type="button" class="create-close-btn" onclick="closeOverlay()">
                    <i class="fa-solid fa-xmark fa-lg"></i>
                </button>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin: 16px 24px 0;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success" style="margin: 16px 24px 0;">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <!-- Type Pills Navigation -->
            <?php if (count($groupTypes) > 1): ?>
            <nav role="navigation" aria-label="Main navigation" class="type-pills-nav">
                <?php foreach ($groupTypes as $index => $type): ?>
                <button type="button"
                        class="type-pill <?= $type['id'] == $defaultTypeId ? 'active' : '' ?>"
                        data-type-id="<?= $type['id'] ?>"
                        data-is-hub="<?= $type['is_hub'] ?>"
                        onclick="selectType(<?= $type['id'] ?>, <?= $type['is_hub'] ?>)">
                    <i class="<?= htmlspecialchars($type['icon'] ?? 'fa-solid fa-layer-group') ?>"></i>
                    <span><?= htmlspecialchars($type['name']) ?></span>
                    <?php if ($type['is_hub']): ?>
                    <span class="admin-badge">
                        <i class="fa-solid fa-shield-halved"></i> Admin
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
                    <label class="form-label required">Group Name</label>
                    <input type="text" name="name" id="groupName" class="form-input" placeholder="Enter group name..." required>
                </div>

                <!-- Description -->
                <div class="form-field">
                    <label class="form-label required">Description</label>
                    <textarea name="description" id="groupDescription" class="form-textarea" placeholder="What's this group about?" required></textarea>
                    <p class="form-hint">Tell people what this group is for and what they can expect.</p>
                </div>

                <!-- Location -->
                <div class="form-field">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" id="groupLocation" class="form-input" placeholder="City, Region, or Address">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    <p class="form-hint">Optional: Add a location to help people find your group.</p>
                </div>

                <!-- Visibility -->
                <div class="form-field">
                    <label class="form-label">Privacy</label>
                    <select name="visibility" class="form-select">
                        <option value="public">Public - Anyone can see and join</option>
                        <option value="private">Private - Members must request to join</option>
                    </select>
                </div>

                <!-- Group Image -->
                <div class="form-field">
                    <label class="form-label">Group Image</label>
                    <div class="image-upload-area" id="uploadArea" onclick="document.getElementById('imageFile').click()">
                        <i class="fa-solid fa-image"></i>
                        <p class="upload-text">Click to upload an image</p>
                    </div>
                    <div class="image-preview" id="imagePreview">
                        <img id="previewImg" src="" alt="Preview" loading="lazy">
                        <button type="button" class="image-remove-btn" onclick="removeImage(event)">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <input type="file" name="image" id="imageFile" accept="image/*" style="display: none;" onchange="previewImage(this)">
                </div>
            </form>

            <!-- Footer Actions -->
            <div class="create-footer">
                <button type="button" class="btn btn-cancel" onclick="closeOverlay()">Cancel</button>
                <button type="submit" form="createGroupForm" class="btn btn-primary" id="submitBtn">
                    <i class="fa-solid fa-plus"></i>
                    Create Group
                </button>
            </div>
        </div>
    </div>

    <script>
        // Prevent multiple close calls
        let isClosing = false;

        // Close overlay - with debounce to prevent multiple calls
        function closeOverlay() {
            if (isClosing) return;
            isClosing = true;

            // Get the referrer URL
            const referrer = document.referrer;
            const currentHost = window.location.host;

            // Check if referrer exists and is from same host (internal navigation)
            if (referrer && referrer.includes(currentHost)) {
                window.location.href = referrer;
            } else {
                // No valid referrer, go to groups page
                window.location.href = '<?= $basePath ?>/groups';
            }
        }

        // Type selection
        function selectType(typeId, isHub) {
            // Update hidden input
            document.getElementById('typeIdInput').value = typeId;

            // Update active pill
            document.querySelectorAll('.type-pill').forEach(pill => {
                pill.classList.remove('active');
            });
            document.querySelector(`.type-pill[data-type-id="${typeId}"]`).classList.add('active');
        }

        // Image preview
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('uploadArea').style.display = 'none';
                    document.getElementById('imagePreview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Remove image
        function removeImage(e) {
            e.stopPropagation();
            document.getElementById('imageFile').value = '';
            document.getElementById('uploadArea').style.display = 'block';
            document.getElementById('imagePreview').style.display = 'none';
        }

        // Close on backdrop click
        const backdrop = document.getElementById('overlayBackdrop');
        if (backdrop) {
            backdrop.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeOverlay();
                }
            });
        }

        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeOverlay();
            }
        });

        // Prevent backdrop clicks from bubbling to container
        const container = document.querySelector('.create-overlay-container');
        if (container) {
            container.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    </script>
</body>
</html>
