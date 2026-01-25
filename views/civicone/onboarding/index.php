<?php
/**
 * Onboarding Overlay - Locked Full-Screen Experience
 * GOV.UK Design System - Standalone Page
 * WCAG 2.1 AA Compliant
 *
 * Features:
 * - Desktop: Full-screen modal (cannot close)
 * - Mobile: Full-screen overlay (100vw x 100vh)
 * - Based on GOV.UK Start Page pattern
 * - NO close button - must complete to proceed
 * - Safe-area-inset support for notched devices
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$tenantId = TenantContext::getId();
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';

$pageTitle = 'Complete your profile';
?>
<!DOCTYPE html>
<html lang="en" class="govuk-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b0c0c">
    <title><?= htmlspecialchars($pageTitle) ?> - Project NEXUS</title>

    <!-- GOV.UK Frontend CSS -->
    <link rel="stylesheet" href="/assets/govuk-frontend-5.14.0/govuk-frontend.min.css">

    <!-- Onboarding CSS (extracted per CLAUDE.md) -->
    <link rel="stylesheet" href="/assets/css/civicone-onboarding-index.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/civicone-onboarding-index.css') ?>">
</head>
<body class="govuk-template__body civicone-onboarding-body">
    <script>document.body.className += ' js-enabled';</script>

    <!-- Skip Link -->
    <a href="#main-content" class="govuk-skip-link" data-module="govuk-skip-link">Skip to main content</a>

    <!-- Locked Backdrop -->
    <div class="civicone-onboarding-backdrop">

        <!-- Main Panel -->
        <div class="civicone-onboarding-panel" role="dialog" aria-modal="true" aria-labelledby="onboarding-title">

            <!-- Header -->
            <div class="civicone-onboarding-header">
                <span class="govuk-caption-l">Welcome to the community</span>
                <h1 class="govuk-heading-xl" id="onboarding-title">Complete your profile</h1>
                <p class="govuk-body-l">
                    Let's set up your profile so you can start connecting with your neighbours.
                </p>
            </div>

            <!-- Content -->
            <main class="civicone-onboarding-content" id="main-content" role="main">

                <form action="<?= $basePath ?>/onboarding/store" method="POST" enctype="multipart/form-data" class="civicone-onboarding-form" id="onboardingForm" novalidate>
                    <?= Csrf::input() ?>

                    <!-- Profile Picture -->
                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">
                                    Profile picture
                                </h2>
                            </legend>
                            <div id="avatar-hint" class="govuk-hint">
                                Choose a clear photo that represents you. JPG, PNG or GIF (max 8MB).
                            </div>

                            <div class="civicone-avatar-upload">
                                <div class="civicone-avatar-preview" id="avatarPreview">
                                    <?php if (!empty($user['avatar_url'])): ?>
                                        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" loading="lazy" alt="Current avatar" id="avatarImg">
                                    <?php else: ?>
                                        <span class="civicone-avatar-placeholder" aria-hidden="true">
                                            <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="civicone-avatar-actions">
                                    <label class="govuk-button govuk-button--secondary" for="avatarInput">
                                        Choose photo
                                    </label>
                                    <input type="file"
                                           name="avatar"
                                           id="avatarInput"
                                           class="govuk-file-upload civicone-file-hidden"
                                           accept="image/*"
                                           aria-describedby="avatar-hint">
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Bio -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="bio">
                            About you
                        </label>
                        <div id="bio-hint" class="govuk-hint">
                            Tell us a little about yourself. What are your interests? What brings you to the community?
                            This helps your neighbours get to know you and find common interests.
                        </div>
                        <textarea class="govuk-textarea"
                                  id="bio"
                                  name="bio"
                                  rows="5"
                                  aria-describedby="bio-hint"
                                  required><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>

                    <!-- Submit -->
                    <div class="govuk-button-group">
                        <button type="submit" class="govuk-button" data-module="govuk-button" id="submitBtn">
                            Complete setup
                        </button>
                    </div>

                    <div class="govuk-inset-text">
                        You must complete this step to access the platform. Your information helps build trust
                        in our community.
                    </div>
                </form>

            </main>

        </div>

    </div>

    <!-- Onboarding JavaScript -->
    <script src="/assets/js/civicone-onboarding-index.min.js" defer></script>
    <script>
        // Preview avatar image
        document.getElementById('avatarInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('avatarPreview');
                    preview.innerHTML = '<img src="' + event.target.result + '" alt="Avatar preview" id="avatarImg">';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>

</body>
</html>
