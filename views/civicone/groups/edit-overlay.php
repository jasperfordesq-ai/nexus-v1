<?php
/**
 * Group Edit & Invite Overlay - Professional Meta-Style Interface
 *
 * Features:
 * - Desktop: Glassmorphism modal with holographic effects
 * - Mobile: Full-screen fixed overlay (100vw x 100vh)
 * - Two tabs: Edit Settings and Invite Members
 * - Horizontal scrollable pill navigation (YouTube/Instagram style)
 * - Dark mode by default
 * - Safe-area-inset support for notched devices
 * - Proper close functionality (no multi-click bugs)
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenantId = \Nexus\Core\TenantContext::getId();
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';

// Get flash messages
$error = $_SESSION['group_error'] ?? null;
$success = $_SESSION['group_success'] ?? null;
unset($_SESSION['group_error'], $_SESSION['group_success']);

$pageTitle = $pageTitle ?? 'Edit Group';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#1e293b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Global functions -->
    <script>
    // Haptic feedback helper
    function haptic() {
        if (navigator.vibrate) navigator.vibrate(10);
    }

    // Tab switching
    function switchTab(type) {
        var pills = document.querySelectorAll('.edit-pill');
        for (var i = 0; i < pills.length; i++) {
            pills[i].classList.remove('active');
            pills[i].setAttribute('aria-selected', 'false');
        }
        var activePill = document.querySelector('.edit-pill[data-type="' + type + '"]');
        if (activePill) {
            activePill.classList.add('active');
            activePill.setAttribute('aria-selected', 'true');
        }

        var panels = document.querySelectorAll('.edit-panel');
        for (var j = 0; j < panels.length; j++) {
            panels[j].classList.remove('active');
        }
        var activePanel = document.getElementById('panel-' + type);
        if (activePanel) {
            activePanel.classList.add('active');
        }
        haptic();
    }

    // Close overlay - debounced with proper navigation
    let isClosing = false;
    function closeEditOverlay() {
        if (isClosing) return;
        isClosing = true;
        haptic();

        const referrer = document.referrer;
        const currentHost = window.location.host;

        // If came from same site, go back to referrer
        if (referrer && referrer.includes(currentHost)) {
            window.location.href = referrer;
        } else {
            // Otherwise go to the group page
            window.location.href = '<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=settings';
        }
    }

    // Image preview
    function previewImage(input, targetId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(targetId).src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>

    <style>
    /* ============================================
       RESET & BASE
       ============================================ */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --safe-top: env(safe-area-inset-top, 0px);
        --safe-bottom: env(safe-area-inset-bottom, 0px);
        --safe-left: env(safe-area-inset-left, 0px);
        --safe-right: env(safe-area-inset-right, 0px);

        /* Dark theme colors */
        --ed-bg: #0f172a;
        --ed-surface: rgba(30, 41, 59, 0.95);
        --ed-border: rgba(255, 255, 255, 0.1);
        --ed-text: #f1f5f9;
        --ed-text-secondary: #cbd5e1;
        --ed-text-muted: #94a3b8;
        --ed-primary: #6366f1;
        --ed-primary-light: rgba(99, 102, 241, 0.15);
        --ed-success: #10b981;
        --ed-success-light: rgba(16, 185, 129, 0.15);
        --ed-danger: #ef4444;
        --ed-danger-light: rgba(239, 68, 68, 0.15);
        --ed-pink: #ec4899;
        --ed-pink-light: rgba(236, 72, 153, 0.15);
    }

    html, body {
        width: 100%;
        height: 100%;
        overflow: hidden;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--ed-bg);
        color: var(--ed-text);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* ============================================
       OVERLAY STRUCTURE (Mobile-first)
       ============================================ */
    .edit-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: var(--ed-bg);
        z-index: 9999;
        display: flex;
        flex-direction: column;
    }

    .edit-overlay {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        background: var(--ed-bg);
    }

    /* ============================================
       HEADER
       ============================================ */
    .edit-header {
        position: sticky;
        top: 0;
        z-index: 100;
        background: var(--ed-surface);
        border-bottom: 1px solid var(--ed-border);
        padding: 12px 16px;
        padding-top: calc(12px + var(--safe-top));
        display: flex;
        align-items: center;
        justify-content: space-between;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
    }

    .edit-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .edit-close-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: var(--ed-danger-light);
        color: var(--ed-danger);
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        -webkit-tap-highlight-color: transparent;
    }

    .edit-close-btn:active {
        transform: scale(0.9);
    }

    .edit-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--ed-text);
    }

    /* ============================================
       TAB NAVIGATION (Pills)
       ============================================ */
    .edit-tabs {
        padding: 12px 16px;
        background: var(--ed-surface);
        border-bottom: 1px solid var(--ed-border);
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    .edit-tabs::-webkit-scrollbar {
        display: none;
    }

    .edit-tabs-inner {
        display: flex;
        gap: 8px;
        min-width: min-content;
    }

    .edit-pill {
        flex-shrink: 0;
        padding: 10px 20px;
        border-radius: 24px;
        border: 2px solid var(--ed-border);
        background: transparent;
        color: var(--ed-text-secondary);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }

    .edit-pill i {
        font-size: 16px;
        transition: transform 0.2s;
    }

    .edit-pill:hover {
        border-color: var(--ed-primary);
        color: var(--ed-primary);
        background: var(--ed-primary-light);
    }

    .edit-pill:active {
        transform: scale(0.96);
    }

    .edit-pill.active {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border-color: transparent;
        color: white;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
    }

    .edit-pill.active i {
        transform: scale(1.1);
    }

    /* ============================================
       CONTENT AREA
       ============================================ */
    .edit-content {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding-bottom: calc(20px + var(--safe-bottom));
    }

    /* Form panels */
    .edit-panel {
        display: none;
        padding: 20px 16px;
        animation: panelFadeIn 0.3s ease;
    }

    .edit-panel.active {
        display: block;
    }

    @keyframes panelFadeIn {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ============================================
       FORM ELEMENTS
       ============================================ */
    .ed-field {
        margin-bottom: 24px;
    }

    .ed-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--ed-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 8px;
    }

    .ed-input,
    .ed-textarea,
    .ed-select {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid var(--ed-border);
        border-radius: 12px;
        background: rgba(30, 41, 59, 0.6);
        color: var(--ed-text);
        font-size: 16px;
        font-family: inherit;
        transition: all 0.2s;
        -webkit-appearance: none;
    }

    .ed-textarea {
        min-height: 120px;
        resize: vertical;
    }

    .ed-input:focus,
    .ed-textarea:focus,
    .ed-select:focus {
        outline: none;
        border-color: var(--ed-primary);
        box-shadow: 0 0 0 4px var(--ed-primary-light);
    }

    .ed-hint {
        font-size: 12px;
        color: var(--ed-text-muted);
        margin-top: 6px;
    }

    /* Checkbox */
    .ed-checkbox-field {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 24px;
        cursor: pointer;
    }

    .ed-checkbox-field input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin-top: 2px;
        accent-color: var(--ed-primary);
        cursor: pointer;
    }

    .ed-checkbox-label {
        flex: 1;
    }

    .ed-checkbox-title {
        font-weight: 600;
        color: var(--ed-text);
        margin-bottom: 4px;
    }

    .ed-checkbox-desc {
        font-size: 12px;
        color: var(--ed-text-muted);
    }

    /* Image upload */
    .ed-image-upload {
        margin-bottom: 24px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .ed-image-preview {
        width: 100%;
        max-width: 300px;
        height: 200px;
        border-radius: 12px;
        object-fit: cover;
        margin-bottom: 12px;
        border: 2px solid var(--ed-border);
        display: block;
    }

    .ed-upload-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: var(--ed-primary-light);
        color: var(--ed-primary);
        border: 2px solid var(--ed-primary);
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        align-self: flex-start;
    }

    .ed-upload-btn:hover {
        background: var(--ed-primary);
        color: white;
    }

    .ed-upload-btn input[type="file"] {
        display: none;
    }

    .ed-image-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .ed-clear-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 14px;
        background: transparent;
        color: #9ca3af;
        border: 2px solid #374151;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .ed-clear-btn:hover {
        background: rgba(220, 38, 38, 0.15);
        color: #f87171;
        border-color: #f87171;
    }

    .ed-clear-btn:has(input:checked) {
        background: #dc2626;
        color: white;
        border-color: #dc2626;
    }

    .ed-image-upload:has(.ed-clear-btn input:checked) .ed-image-preview {
        opacity: 0.4;
        border-color: #f87171;
    }

    /* Submit button */
    .ed-submit {
        width: 100%;
        padding: 16px;
        border-radius: 14px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border: none;
        color: white;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        transition: all 0.2s;
        -webkit-tap-highlight-color: transparent;
        margin-top: 12px;
    }

    .ed-submit:active {
        transform: scale(0.98);
    }

    .ed-submit:disabled {
        background: #4b5563;
        box-shadow: none;
        cursor: not-allowed;
    }

    /* Invite tab styles */
    .invite-search {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid var(--ed-border);
        border-radius: 12px;
        font-size: 16px;
        font-family: inherit;
        background: rgba(30, 41, 59, 0.6);
        color: var(--ed-text);
        transition: all 0.2s;
        margin-bottom: 16px;
    }

    .invite-search:focus {
        outline: none;
        border-color: var(--ed-primary);
        box-shadow: 0 0 0 4px var(--ed-primary-light);
    }

    .user-list {
        max-height: 400px;
        overflow-y: auto;
        margin-bottom: 20px;
        border: 1px solid var(--ed-border);
        border-radius: 12px;
    }

    .user-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid var(--ed-border);
        cursor: pointer;
        transition: background 0.15s ease;
        min-height: 44px;
    }

    .user-item:last-child {
        border-bottom: none;
    }

    .user-item:hover {
        background: var(--ed-primary-light);
    }

    .user-item.selected {
        background: var(--ed-pink-light);
    }

    .user-item input[type="checkbox"] {
        margin-right: 12px;
        width: 20px;
        height: 20px;
        accent-color: var(--ed-pink);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
        background: #374151;
    }

    .user-info {
        flex: 1;
    }

    .user-name {
        font-weight: 600;
        color: var(--ed-text);
    }

    .user-email {
        font-size: 0.85rem;
        color: var(--ed-text-muted);
    }

    .selected-count {
        text-align: center;
        margin-bottom: 16px;
        font-weight: 600;
        color: var(--ed-pink);
    }

    .no-users {
        text-align: center;
        padding: 40px 20px;
        color: var(--ed-text-muted);
    }

    .add-directly-box {
        margin: 20px 0;
        padding: 16px;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
        border: 2px solid var(--ed-success);
        border-radius: 12px;
    }

    .add-directly-box label {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        cursor: pointer;
    }

    .add-directly-box input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin-top: 2px;
        accent-color: var(--ed-success);
    }

    .add-directly-title {
        font-weight: 600;
        color: var(--ed-success);
        margin-bottom: 4px;
    }

    .add-directly-desc {
        font-size: 0.85rem;
        color: var(--ed-text-secondary);
    }

    /* ============================================
       DESKTOP: GLASSMORPHISM MODAL
       ============================================ */
    @media (min-width: 768px) {
        body {
            overflow: hidden;
        }

        .edit-backdrop {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .edit-backdrop:not(:has(.edit-overlay:hover)) {
            cursor: pointer;
        }

        .edit-overlay {
            position: relative;
            width: 100%;
            max-width: 720px;
            max-height: 90vh;
            background: var(--ed-surface);
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }

        .edit-header {
            border-radius: 24px 24px 0 0;
            padding-top: 12px;
        }

        .edit-close-btn:hover {
            background: var(--ed-danger);
            color: white;
            transform: scale(1.05);
        }

        .edit-content {
            padding: 24px;
        }

        .edit-panel {
            padding: 0;
        }

        .ed-input,
        .ed-textarea,
        .ed-select {
            font-size: 15px;
        }
    }

    /* ============================================
       UTILITIES
       ============================================ */
    .text-center {
        text-align: center;
    }

    .mt-2 {
        margin-top: 16px;
    }

    .mb-2 {
        margin-bottom: 16px;
    }
    </style>
</head>
<body>

<!-- Backdrop with click-to-close on desktop -->
<div class="edit-backdrop" onclick="if(event.target === this) closeEditOverlay()">
    <!-- Main Overlay -->
    <div class="edit-overlay">

        <!-- Header -->
        <div class="edit-header">
            <div class="edit-header-left">
                <button type="button" class="edit-close-btn" onclick="closeEditOverlay()" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <h1 class="edit-title"><?= htmlspecialchars($group['name']) ?></h1>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="edit-tabs" role="tablist">
            <div class="edit-tabs-inner">
                <button type="button" class="edit-pill <?= $defaultTab === 'edit' ? 'active' : '' ?>"
                        data-type="edit"
                        role="tab"
                        aria-selected="<?= $defaultTab === 'edit' ? 'true' : 'false' ?>"
                        onclick="switchTab('edit')">
                    <i class="fa-solid fa-pen-to-square"></i>
                    Edit Settings
                </button>
                <button type="button" class="edit-pill <?= $defaultTab === 'invite' ? 'active' : '' ?>"
                        data-type="invite"
                        role="tab"
                        aria-selected="<?= $defaultTab === 'invite' ? 'true' : 'false' ?>"
                        onclick="switchTab('invite')">
                    <i class="fa-solid fa-user-plus"></i>
                    Invite Members
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="edit-content" id="contentArea">

            <!-- ============================================
                 PANEL 1: EDIT SETTINGS
                 ============================================ -->
            <div id="panel-edit" class="edit-panel <?= $defaultTab === 'edit' ? 'active' : '' ?>" role="tabpanel">
                <form action="<?= $basePath ?>/groups/update" method="POST" enctype="multipart/form-data">
                    <?= Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">

                    <!-- Group Name -->
                    <div class="ed-field">
                        <label class="ed-label" for="name">Group Name</label>
                        <input type="text" id="name" name="name" class="ed-input"
                               value="<?= htmlspecialchars($group['name']) ?>" required>
                    </div>

                    <!-- Description -->
                    <div class="ed-field">
                        <label class="ed-label" for="description">Description</label>
                        <textarea id="description" name="description" class="ed-textarea" required><?= htmlspecialchars($group['description']) ?></textarea>
                    </div>

                    <!-- Location -->
                    <div class="ed-field">
                        <label class="ed-label" for="location">Location</label>
                        <input type="text" id="location" name="location" class="ed-input"
                               value="<?= htmlspecialchars($group['location'] ?? '') ?>"
                               placeholder="City, State or Region">
                    </div>

                    <!-- Group Type -->
                    <div class="ed-field">
                        <label class="ed-label" for="type_id">Group Type</label>
                        <select id="type_id" name="type_id" class="ed-select" required>
                            <?php foreach ($groupTypes as $type): ?>
                                <option value="<?= $type['id'] ?>"
                                        <?= $group['type_id'] == $type['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Visibility -->
                    <div class="ed-field">
                        <label class="ed-label" for="visibility">Visibility</label>
                        <select id="visibility" name="visibility" class="ed-select" required>
                            <option value="public" <?= $group['visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
                            <option value="private" <?= $group['visibility'] === 'private' ? 'selected' : '' ?>>Private</option>
                        </select>
                        <div class="ed-hint">Public groups are visible to everyone. Private groups require approval to join.</div>
                    </div>

                    <!-- Featured Hub (Admin Only) -->
                    <?php if ($isAdmin): ?>
                    <div class="ed-checkbox-field">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1"
                               <?= !empty($group['is_featured']) ? 'checked' : '' ?>>
                        <div class="ed-checkbox-label">
                            <div class="ed-checkbox-title">⭐ Featured Hub</div>
                            <div class="ed-checkbox-desc">Featured hubs appear in a special section at the top of the hubs page. Only site administrators can mark groups as featured.</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Group Image -->
                    <div class="ed-image-upload">
                        <label class="ed-label">Group Image</label>
                        <?php
                            $groupImageSrc = !empty($group['image_url'])
                                ? htmlspecialchars($group['image_url'])
                                : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%231e293b'/%3E%3Cg transform='translate(200,150)'%3E%3Ccircle r='50' fill='%23475569'/%3E%3Cpath d='M-20,-10 L-20,10 L0,0 Z M20,-10 L20,10 L0,0 Z' fill='%23cbd5e1'/%3E%3Ccircle cx='-15' cy='0' r='8' fill='%23cbd5e1'/%3E%3Ccircle cx='15' cy='0' r='8' fill='%23cbd5e1'/%3E%3C/g%3E%3Ctext x='200' y='270' text-anchor='middle' font-family='Arial' font-size='16' fill='%2394a3b8'%3EGroup Image%3C/text%3E%3C/svg%3E";
                        ?>
                        <img src="<?= $groupImageSrc ?>" loading="lazy"
                             alt="Group image"
                             class="ed-image-preview"
                             id="imagePreview"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 400 300%27%3E%3Crect width=%27400%27 height=%27300%27 fill=%27%231e293b%27/%3E%3Cg transform=%27translate(200,150)%27%3E%3Ccircle r=%2750%27 fill=%27%23475569%27/%3E%3Cpath d=%27M-20,-10 L-20,10 L0,0 Z M20,-10 L20,10 L0,0 Z%27 fill=%27%23cbd5e1%27/%3E%3Ccircle cx=%27-15%27 cy=%270%27 r=%278%27 fill=%27%23cbd5e1%27/%3E%3Ccircle cx=%2715%27 cy=%270%27 r=%278%27 fill=%27%23cbd5e1%27/%3E%3C/g%3E%3Ctext x=%27200%27 y=%27270%27 text-anchor=%27middle%27 font-family=%27Arial%27 font-size=%2716%27 fill=%27%2394a3b8%27%3EGroup Image%3C/text%3E%3C/svg%3E'">
                        <div class="ed-image-actions">
                            <label class="ed-upload-btn">
                                <i class="fa-solid fa-image"></i>
                                Change Image
                                <input type="file" name="image" accept="image/*" onchange="previewImage(this, 'imagePreview')">
                            </label>
                            <?php if (!empty($group['image_url'])): ?>
                            <label class="ed-clear-btn" title="Remove image">
                                <input type="checkbox" name="clear_avatar" value="1" style="display: none;">
                                <i class="fa-solid fa-trash"></i>
                                Clear
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Cover Image -->
                    <div class="ed-image-upload">
                        <label class="ed-label">Cover Image</label>
                        <?php
                            $coverImageSrc = !empty($group['cover_image_url'])
                                ? htmlspecialchars($group['cover_image_url'])
                                : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 300'%3E%3Cdefs%3E%3ClinearGradient id='grad' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%231e293b;stop-opacity:1' /%3E%3Cstop offset='100%25' style='stop-color:%23334155;stop-opacity:1' /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='800' height='300' fill='url(%23grad)'/%3E%3Cg transform='translate(400,120)'%3E%3Ccircle r='40' fill='%23475569' opacity='0.5'/%3E%3Cpath d='M-30,-20 L-10,-20 L0,-35 L10,-20 L30,-20 L30,20 L-30,20 Z' fill='%2364748b'/%3E%3Ccircle cx='-10' cy='-5' r='6' fill='%23fbbf24'/%3E%3C/g%3E%3Ctext x='400' y='270' text-anchor='middle' font-family='Arial' font-size='18' fill='%2394a3b8'%3ECover Image%3C/text%3E%3C/svg%3E";
                        ?>
                        <img src="<?= $coverImageSrc ?>" loading="lazy"
                             alt="Cover image"
                             class="ed-image-preview"
                             id="coverPreview"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 800 300%27%3E%3Cdefs%3E%3ClinearGradient id=%27grad%27 x1=%270%25%27 y1=%270%25%27 x2=%27100%25%27 y2=%27100%25%27%3E%3Cstop offset=%270%25%27 style=%27stop-color:%231e293b;stop-opacity:1%27 /%3E%3Cstop offset=%27100%25%27 style=%27stop-color:%23334155;stop-opacity:1%27 /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width=%27800%27 height=%27300%27 fill=%27url(%23grad)%27/%3E%3Cg transform=%27translate(400,120)%27%3E%3Ccircle r=%2740%27 fill=%27%23475569%27 opacity=%270.5%27/%3E%3Cpath d=%27M-30,-20 L-10,-20 L0,-35 L10,-20 L30,-20 L30,20 L-30,20 Z%27 fill=%27%2364748b%27/%3E%3Ccircle cx=%27-10%27 cy=%27-5%27 r=%276%27 fill=%27%23fbbf24%27/%3E%3C/g%3E%3Ctext x=%27400%27 y=%27270%27 text-anchor=%27middle%27 font-family=%27Arial%27 font-size=%2718%27 fill=%27%2394a3b8%27%3ECover Image%3C/text%3E%3C/svg%3E'">
                        <div class="ed-image-actions">
                            <label class="ed-upload-btn">
                                <i class="fa-solid fa-image"></i>
                                Change Cover
                                <input type="file" name="cover_image" accept="image/*" onchange="previewImage(this, 'coverPreview')">
                            </label>
                            <?php if (!empty($group['cover_image_url'])): ?>
                            <label class="ed-clear-btn" title="Remove cover">
                                <input type="checkbox" name="clear_cover" value="1" style="display: none;">
                                <i class="fa-solid fa-trash"></i>
                                Clear
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="ed-submit">
                        <i class="fa-solid fa-check"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- ============================================
                 PANEL 2: INVITE MEMBERS
                 ============================================ -->
            <div id="panel-invite" class="edit-panel <?= $defaultTab === 'invite' ? 'active' : '' ?>" role="tabpanel">
                <p class="text-center mb-2" style="color: var(--ed-text-secondary);">
                    Select members to invite to <strong><?= htmlspecialchars($group['name']) ?></strong>
                </p>

                <?php if (empty($availableUsers)): ?>
                    <div class="no-users">
                        <p>All community members are already in this group!</p>
                    </div>
                <?php else: ?>
                    <form action="<?= $basePath ?>/groups/<?= $group['id'] ?>/invite" method="POST" id="inviteForm">
                        <?= Nexus\Core\Csrf::input() ?>

                        <input type="text" class="invite-search" id="userSearch" placeholder="Search members by name...">

                        <div class="user-list" id="userList">
                            <?php foreach ($availableUsers as $user): ?>
                                <label class="user-item" data-name="<?= strtolower(htmlspecialchars($user['name'])) ?>">
                                    <input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>">
                                    <?php
                                        $avatarSrc = $user['avatar_url'] ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 128 128'%3E%3Ccircle cx='64' cy='64' r='64' fill='%23374151'/%3E%3Ccircle cx='64' cy='48' r='20' fill='%2394a3b8'/%3E%3Cellipse cx='64' cy='96' rx='32' ry='24' fill='%2394a3b8'/%3E%3C/svg%3E";
                                    ?>
                                    <img src="<?= htmlspecialchars($avatarSrc) ?>" loading="lazy" alt="" class="user-avatar">
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                        <?php if (!empty($user['email'])): ?>
                                            <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="selected-count" id="selectedCount">0 members selected</div>

                        <!-- Add Directly Option -->
                        <div class="add-directly-box">
                            <label>
                                <input type="checkbox" name="add_directly" value="1" id="addDirectlyCheckbox">
                                <div>
                                    <div class="add-directly-title">Add directly to group</div>
                                    <div class="add-directly-desc">
                                        Skip the invitation step and add selected members immediately. They'll receive a notification that they've been added.
                                    </div>
                                </div>
                            </label>
                        </div>

                        <button type="submit" class="ed-submit" id="submitBtn" disabled>
                            Send Invitations
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Invite tab functionality
    const searchInput = document.getElementById('userSearch');
    const userList = document.getElementById('userList');
    const selectedCount = document.getElementById('selectedCount');
    const submitBtn = document.getElementById('submitBtn');
    const checkboxes = document.querySelectorAll('input[name="user_ids[]"]');
    const addDirectlyCheckbox = document.getElementById('addDirectlyCheckbox');

    // Search filter
    if (searchInput && userList) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const items = userList.querySelectorAll('.user-item');

            items.forEach(item => {
                const name = item.dataset.name;
                item.style.display = name.includes(query) ? 'flex' : 'none';
            });
        });
    }

    // Selection count
    function updateCount() {
        const checked = document.querySelectorAll('input[name="user_ids[]"]:checked').length;
        if (selectedCount) {
            selectedCount.textContent = checked + ' member' + (checked !== 1 ? 's' : '') + ' selected';
        }
        if (submitBtn) {
            submitBtn.disabled = checked === 0;
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            this.closest('.user-item').classList.toggle('selected', this.checked);
            updateCount();
        });
    });

    // Toggle button text based on "Add directly" checkbox
    if (addDirectlyCheckbox && submitBtn) {
        addDirectlyCheckbox.addEventListener('change', function() {
            if (this.checked) {
                submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Add Members Now';
                submitBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            } else {
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Invitations';
                submitBtn.style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
            }
        });
    }

    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditOverlay();
        }
    });

    // Prevent form submission if offline
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to save changes.');
                return;
            }
        });
    });
});
</script>

</body>
</html>
