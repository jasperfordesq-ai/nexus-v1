<?php
/**
 * Onboarding Overlay - Locked Full-Screen Experience
 *
 * Features:
 * - Desktop: Full-screen modal (cannot close)
 * - Mobile: Full-screen overlay (100vw x 100vh)
 * - Based on /create-group and /compose designs
 * - Dark mode by default
 * - NO close button - must complete to proceed
 * - Safe-area-inset support for notched devices
 * - Locks user out until completion
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenantId = \Nexus\Core\TenantContext::getId();
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';

$pageTitle = 'Complete Your Profile';
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
        --ob-bg: #0f172a;
        --ob-surface: rgba(30, 41, 59, 0.95);
        --ob-border: rgba(255, 255, 255, 0.1);
        --ob-text: #f1f5f9;
        --ob-text-secondary: #cbd5e1;
        --ob-text-muted: #94a3b8;
        --ob-primary: #6366f1;
        --ob-primary-light: rgba(99, 102, 241, 0.15);
        --ob-success: #10b981;
        --ob-pink: #ec4899;
    }

    html, body {
        width: 100%;
        height: 100%;
        overflow: hidden;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--ob-bg);
        color: var(--ob-text);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* ============================================
       LOCKED OVERLAY STRUCTURE
       ============================================ */
    .onboarding-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: var(--ob-bg);
        z-index: 99999;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .onboarding-overlay {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        background: var(--ob-bg);
    }

    /* ============================================
       HEADER
       ============================================ */
    .onboarding-header {
        position: sticky;
        top: 0;
        z-index: 100;
        background: var(--ob-surface);
        border-bottom: 1px solid var(--ob-border);
        padding: 16px 24px;
        padding-top: calc(16px + var(--safe-top));
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
    }

    .onboarding-header-content {
        max-width: 720px;
        margin: 0 auto;
        text-align: center;
    }

    .onboarding-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 36px;
        margin-bottom: 16px;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    }

    .onboarding-title {
        font-size: 28px;
        font-weight: 800;
        color: var(--ob-text);
        margin-bottom: 8px;
    }

    .onboarding-subtitle {
        font-size: 15px;
        color: var(--ob-text-secondary);
        line-height: 1.5;
    }

    /* ============================================
       CONTENT AREA
       ============================================ */
    .onboarding-content {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 32px 24px;
        padding-bottom: calc(32px + var(--safe-bottom));
    }

    .onboarding-form {
        max-width: 600px;
        margin: 0 auto;
    }

    /* ============================================
       FORM ELEMENTS
       ============================================ */
    .ob-field {
        margin-bottom: 28px;
    }

    .ob-label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: var(--ob-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 10px;
    }

    .ob-input,
    .ob-textarea {
        width: 100%;
        padding: 16px;
        border: 2px solid var(--ob-border);
        border-radius: 12px;
        background: rgba(30, 41, 59, 0.6);
        color: var(--ob-text);
        font-size: 16px;
        font-family: inherit;
        transition: all 0.2s;
        -webkit-appearance: none;
    }

    .ob-textarea {
        min-height: 120px;
        resize: vertical;
    }

    .ob-input:focus,
    .ob-textarea:focus {
        outline: none;
        border-color: var(--ob-primary);
        box-shadow: 0 0 0 4px var(--ob-primary-light);
    }

    .ob-hint {
        font-size: 13px;
        color: var(--ob-text-muted);
        margin-top: 8px;
        line-height: 1.4;
    }

    /* Avatar Upload */
    .ob-avatar-upload {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 20px;
        border: 2px dashed var(--ob-border);
        border-radius: 12px;
        background: rgba(30, 41, 59, 0.4);
        transition: all 0.2s;
    }

    .ob-avatar-upload:hover {
        border-color: var(--ob-primary);
        background: rgba(99, 102, 241, 0.05);
    }

    .ob-avatar-preview {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        overflow: hidden;
        background: rgba(99, 102, 241, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--ob-primary);
        font-size: 32px;
        flex-shrink: 0;
        border: 3px solid var(--ob-border);
    }

    .ob-avatar-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .ob-avatar-info {
        flex: 1;
    }

    .ob-avatar-title {
        font-weight: 600;
        color: var(--ob-text);
        margin-bottom: 4px;
    }

    .ob-avatar-desc {
        font-size: 13px;
        color: var(--ob-text-muted);
        margin-bottom: 12px;
    }

    .ob-upload-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: var(--ob-primary-light);
        color: var(--ob-primary);
        border: 2px solid var(--ob-primary);
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .ob-upload-btn:hover {
        background: var(--ob-primary);
        color: white;
    }

    .ob-upload-btn input[type="file"] {
        display: none;
    }

    /* Submit button */
    .ob-submit {
        width: 100%;
        padding: 18px;
        border-radius: 14px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border: none;
        color: white;
        font-size: 17px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        transition: all 0.2s;
        -webkit-tap-highlight-color: transparent;
        margin-top: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .ob-submit:active {
        transform: scale(0.98);
    }

    .ob-submit:disabled {
        background: #4b5563;
        box-shadow: none;
        cursor: not-allowed;
    }

    /* Required indicator */
    .ob-required {
        color: var(--ob-pink);
        margin-left: 4px;
    }

    /* ============================================
       DESKTOP: CENTERED MODAL
       ============================================ */
    @media (min-width: 768px) {
        .onboarding-backdrop {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .onboarding-overlay {
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            background: var(--ob-surface);
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }

        .onboarding-header {
            border-radius: 24px 24px 0 0;
            padding-top: 16px;
        }

        .onboarding-title {
            font-size: 32px;
        }

        .onboarding-content {
            padding: 40px;
        }
    }

    /* ============================================
       MOBILE OPTIMIZATIONS
       ============================================ */
    @media (max-width: 768px) {
        .onboarding-header {
            padding: 12px 16px;
            padding-top: calc(12px + var(--safe-top));
        }

        .onboarding-icon {
            width: 64px;
            height: 64px;
            font-size: 28px;
            margin-bottom: 12px;
        }

        .onboarding-title {
            font-size: 22px;
        }

        .onboarding-subtitle {
            font-size: 14px;
        }

        .onboarding-content {
            padding: 20px 16px;
        }

        .ob-avatar-upload {
            flex-direction: column;
            text-align: center;
        }
    }

    /* ============================================
       ANIMATIONS
       ============================================ */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .onboarding-overlay {
        animation: fadeIn 0.4s ease-out;
    }

    /* ============================================
       UTILITIES
       ============================================ */
    .text-center {
        text-align: center;
    }

    .mt-3 {
        margin-top: 24px;
    }
    </style>
</head>
<body>

<!-- LOCKED Backdrop (no escape) -->
<div class="onboarding-backdrop">
    <!-- Main Overlay -->
    <div class="onboarding-overlay">

        <!-- Header -->
        <div class="onboarding-header">
            <div class="onboarding-header-content">
                <div class="onboarding-icon">
                    <i class="fa-solid fa-user-astronaut"></i>
                </div>
                <h1 class="onboarding-title">Welcome to the Community!</h1>
                <p class="onboarding-subtitle">Let's complete your profile so you can start connecting with your neighbors.</p>
            </div>
        </div>

        <!-- Content Area -->
        <div class="onboarding-content">
            <form action="<?= $basePath ?>/onboarding/store" method="POST" enctype="multipart/form-data" class="onboarding-form" id="onboardingForm">
                <?= Nexus\Core\Csrf::input() ?>

                <!-- Profile Picture -->
                <div class="ob-field">
                    <label class="ob-label">Profile Picture <span class="ob-required">*</span></label>
                    <div class="ob-avatar-upload">
                        <div class="ob-avatar-preview" id="avatarPreview">
                            <?php if (!empty($user['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" loading="lazy" alt="Avatar" id="avatarImg">
                            <?php else: ?>
                                <i class="fa-solid fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ob-avatar-info">
                            <div class="ob-avatar-title">Upload your photo</div>
                            <div class="ob-avatar-desc">Choose a clear photo that represents you. JPG, PNG or GIF (max 8MB)</div>
                            <label class="ob-upload-btn">
                                <i class="fa-solid fa-camera"></i>
                                Choose Photo
                                <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="previewAvatar(this)">
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Bio -->
                <div class="ob-field">
                    <label class="ob-label" for="bio">Bio <span class="ob-required">*</span></label>
                    <textarea id="bio"
                              name="bio"
                              class="ob-textarea"
                              placeholder="Tell us a little about yourself... What are your interests? What brings you to the community?"
                              required><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    <div class="ob-hint">This helps your neighbors get to know you and find common interests.</div>
                </div>

                <!-- Submit -->
                <button type="submit" class="ob-submit" id="submitBtn">
                    Complete Setup
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

                <div class="text-center mt-3" style="color: var(--ob-text-muted); font-size: 13px;">
                    <i class="fa-solid fa-lock" style="margin-right: 6px;"></i>
                    You must complete this step to access the platform
                </div>
            </form>
        </div>

    </div>
</div>

<script>
// Avatar preview
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar" id="avatarImg" loading="lazy">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Track if form is being submitted
let isSubmitting = false;

// Form validation
document.getElementById('onboardingForm').addEventListener('submit', function(e) {
    const bio = document.getElementById('bio').value.trim();

    if (!bio) {
        e.preventDefault();
        alert('Please add a bio to help your neighbors get to know you.');
        document.getElementById('bio').focus();
        return false;
    }

    if (bio.length < 10) {
        e.preventDefault();
        alert('Please write a bit more about yourself (at least 10 characters).');
        document.getElementById('bio').focus();
        return false;
    }

    // Mark as submitting to disable beforeunload warning
    isSubmitting = true;

    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Setting up your profile...';
});

// Prevent accidental navigation (but not when form is being submitted)
window.addEventListener('beforeunload', function (e) {
    if (isSubmitting) {
        // Allow navigation when form is being submitted
        return undefined;
    }
    e.preventDefault();
    e.returnValue = 'Are you sure you want to leave? You must complete your profile to access the platform.';
    return e.returnValue;
});

// Disable ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        e.preventDefault();
        alert('Please complete your profile to continue. You cannot skip this step.');
    }
});

// Disable back button navigation
history.pushState(null, '', location.href);
window.addEventListener('popstate', function() {
    history.pushState(null, '', location.href);
    alert('Please complete your profile to continue. You cannot go back.');
});
</script>

</body>
</html>
