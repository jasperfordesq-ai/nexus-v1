<?php
/**
 * Onboarding Overlay - Locked Full-Screen Experience
 *
 * Features:
 * - Desktop: Full-screen modal (cannot close)
 * - Mobile: Full-screen overlay with safe-area support
 * - Dark mode by default, light mode compatible
 * - NO close button - must complete to proceed
 * - Animated gradient background
 * - Polished form controls with validation
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenantId = \Nexus\Core\TenantContext::getId();
$siteName = \Nexus\Core\TenantContext::getSetting('site_name') ?? 'the Community';
$userId = $_SESSION['user_id'] ?? null;
$userName = $user['first_name'] ?? $_SESSION['user_name'] ?? 'there';

$pageTitle = 'Complete Your Profile';
$mode = $_COOKIE['nexus_mode'] ?? 'dark';

// CSS version for cache busting
$cssVersion = file_exists(__DIR__ . '/../../../config/deployment-version.php')
    ? (require __DIR__ . '/../../../config/deployment-version.php')['version'] ?? time()
    : time();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($mode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#1e293b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Design Tokens & Core Styles -->
    <link rel="preload" as="style" href="/assets/css/design-tokens.min.css?v=<?= $cssVersion ?>">
    <link rel="stylesheet" href="/assets/css/design-tokens.min.css?v=<?= $cssVersion ?>">

    <!-- Onboarding Specific Styles -->
    <link rel="stylesheet" href="/assets/css/modern-onboarding.css?v=<?= $cssVersion ?>">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- LOCKED Backdrop (no escape) -->
<div class="onboarding-backdrop">
    <!-- Main Overlay -->
    <div class="onboarding-overlay" role="dialog" aria-modal="true" aria-labelledby="onboarding-title">

        <!-- Header -->
        <div class="onboarding-header">
            <div class="onboarding-header-content">
                <div class="onboarding-icon">
                    <i class="fa-solid fa-user-astronaut" aria-hidden="true"></i>
                </div>
                <h1 class="onboarding-title" id="onboarding-title">Welcome, <?= htmlspecialchars($userName) ?>!</h1>
                <p class="onboarding-subtitle">Let's complete your profile so you can start connecting with others in <?= htmlspecialchars($siteName) ?>.</p>
            </div>
        </div>

        <!-- Content Area -->
        <div class="onboarding-content">
            <form action="<?= htmlspecialchars($basePath) ?>/onboarding/store" method="POST" enctype="multipart/form-data" class="onboarding-form" id="onboardingForm" novalidate>
                <?= Nexus\Core\Csrf::input() ?>

                <!-- Alert Container (for JS validation messages) -->
                <div id="alertContainer" aria-live="polite"></div>

                <!-- Profile Picture -->
                <div class="ob-field" id="avatarField">
                    <label class="ob-label">
                        <i class="fa-solid fa-camera" aria-hidden="true"></i>
                        Profile Picture
                    </label>
                    <div class="ob-avatar-upload" role="button" tabindex="0" id="avatarDropzone" aria-describedby="avatarHint">
                        <div class="ob-avatar-preview" id="avatarPreview">
                            <?php if (!empty($user['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Your current avatar" id="avatarImg">
                            <?php else: ?>
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ob-avatar-info">
                            <div class="ob-avatar-title">Upload your photo</div>
                            <div class="ob-avatar-desc">A clear photo helps others recognize you. JPG, PNG or GIF (max 8MB)</div>
                            <label class="ob-upload-btn">
                                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                                Choose Photo
                                <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" aria-label="Upload profile picture">
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Bio -->
                <div class="ob-field" id="bioField">
                    <label class="ob-label" for="bio">
                        <i class="fa-solid fa-pen-nib" aria-hidden="true"></i>
                        About You <span class="ob-required" aria-hidden="true">*</span>
                    </label>
                    <textarea id="bio"
                              name="bio"
                              class="ob-textarea"
                              placeholder="Tell us about yourself... What are your interests? What skills can you share? What brings you to the community?"
                              required
                              minlength="10"
                              maxlength="1000"
                              aria-describedby="bioHint bioCharCount"
                              aria-required="true"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    <div class="ob-char-count" id="bioCharCount" aria-live="polite">
                        <span id="charCurrent">0</span>/1000
                    </div>
                    <p class="ob-hint" id="bioHint">Share a bit about yourself - your interests, skills, or what you're looking for. This helps others connect with you.</p>
                </div>

                <!-- Submit -->
                <button type="submit" class="ob-submit" id="submitBtn">
                    <span id="submitText">Complete Setup</span>
                    <i class="fa-solid fa-arrow-right" id="submitIcon" aria-hidden="true"></i>
                </button>

                <div class="ob-locked-msg">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <span>Complete this step to access the platform</span>
                </div>
            </form>
        </div>

    </div>
</div>

<script src="/assets/js/modern-onboarding.js?v=<?= $cssVersion ?>"></script>
</body>
</html>
