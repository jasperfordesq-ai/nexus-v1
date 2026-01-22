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

    <!-- Onboarding CSS (extracted per CLAUDE.md) -->
    <link rel="stylesheet" href="/assets/css/civicone-onboarding-index.css?v=<?= time() ?>">

    <style>
    /* Critical inline styles for standalone page - rest extracted to civicone-onboarding-index.css */
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

                <div class="text-center mt-3 ob-hint-text">
                    <i class="fa-solid fa-lock mr-6"></i>
                    You must complete this step to access the platform
                </div>
            </form>
        </div>

    </div>
</div>

<!-- Onboarding Flow JavaScript -->
<script src="<?= NexusCoreTenantContext::getBasePath() ?>/assets/js/civicone-onboarding-index.min.js" defer></script>

</body>
</html>
