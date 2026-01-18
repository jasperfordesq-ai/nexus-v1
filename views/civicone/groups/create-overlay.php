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

    <style>
        /* Reset and Base */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow: hidden;
        }

        /* Theme Variables */
        [data-theme="light"] {
            --bg-primary: #f5f5f5;
            --bg-secondary: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: rgba(0, 0, 0, 0.1);
            --overlay-bg: rgba(0, 0, 0, 0.5);
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.1);
            --overlay-bg: rgba(0, 0, 0, 0.7);
        }

        /* Overlay Backdrop */
        .create-overlay-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--overlay-bg);
            backdrop-filter: blur(8px);
            z-index: 9998;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Main Container */
        .create-overlay-container {
            position: relative;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            background: var(--bg-secondary);
            border-radius: 20px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 9999;
        }

        /* Header */
        .create-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .create-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .create-close-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .create-close-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: var(--text-primary);
            transform: scale(1.05);
        }

        .create-close-btn:active {
            transform: scale(0.95);
        }

        /* Type Pills Navigation */
        .type-pills-nav {
            display: flex;
            gap: 8px;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
            flex-shrink: 0;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        .type-pills-nav::-webkit-scrollbar {
            height: 6px;
        }

        .type-pills-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .type-pills-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .type-pills-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .type-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 12px;
            background: var(--bg-primary);
            border: 2px solid transparent;
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }

        .type-pill:hover {
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .type-pill.active {
            background: linear-gradient(135deg, #ec4899, #f472b6);
            color: white;
            border-color: transparent;
        }

        .type-pill i {
            font-size: 1rem;
        }

        /* Content Area */
        .create-content {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        /* Form Fields */
        .form-field {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-label.required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #ec4899;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-hint {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 6px;
        }

        /* Image Upload */
        .image-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .image-upload-area:hover {
            border-color: #ec4899;
            background: rgba(236, 72, 153, 0.05);
        }

        .image-upload-area i {
            font-size: 2rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .image-upload-area .upload-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .image-preview {
            display: none;
            position: relative;
            border-radius: 12px;
            overflow: hidden;
        }

        .image-preview img {
            width: 100%;
            height: auto;
            display: block;
        }

        .image-remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Footer Actions */
        .create-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cancel {
            background: var(--bg-primary);
            color: var(--text-secondary);
        }

        .btn-cancel:hover {
            background: var(--border-color);
        }

        .btn-primary {
            background: linear-gradient(135deg, #ec4899, #f472b6);
            color: white;
            box-shadow: 0 4px 14px rgba(236, 72, 153, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(236, 72, 153, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .create-overlay-backdrop {
                padding: 0;
            }

            .create-overlay-container {
                max-width: 100%;
                max-height: 100vh;
                border-radius: 0;
            }

            .create-content {
                padding: 20px;
            }

            .type-pills-nav {
                padding: 12px 20px;
            }
        }

        /* Admin Badge */
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
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
