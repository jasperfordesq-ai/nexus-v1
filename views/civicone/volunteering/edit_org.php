<?php
/**
 * Template D: Form Page - Edit Volunteer Organization
 *
 * Purpose: Edit organization profile with glassmorphism theme
 * Features: Offline detection, image upload, form validation
 * WCAG 2.1 AA: 44px minimum touch targets, keyboard navigation, focus states
 */

// views/modern/volunteering/edit_org.php
$hero_title = "Edit Organisation";
$hero_subtitle = "Update your organisation profile.";
$hero_gradient = 'htb-hero-gradient-teal';
$hideHero = true;

require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="/assets/css/purged/civicone-volunteering-edit-org.min.css">


<!-- Animated Background -->
<div class="edit-org-glass-bg"></div>

<!-- Offline Banner -->
<div class="edit-org-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="edit-org-container">

    <!-- Back Link -->
    <div class="edit-org-back-link-container">
        <a href="<?= $basePath ?>/volunteering/dashboard" class="edit-org-back-link">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Dashboard
        </a>
    </div>

    <!-- Main Card -->
    <div class="edit-org-card">
        <!-- Card Header -->
        <div class="edit-org-card-header">
            <div class="edit-org-card-icon">
                <i class="fa-solid fa-building" aria-hidden="true"></i>
            </div>
            <h1 class="edit-org-card-title">Edit <?= htmlspecialchars($org['name']) ?></h1>
        </div>

        <form action="<?= $basePath ?>/volunteering/org/update" method="POST" id="editOrgForm">
            <?= \Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="org_id" value="<?= $org['id'] ?>">

            <!-- Organization Name -->
            <div class="edit-org-form-group">
                <label class="edit-org-label" for="org-name">
                    <i class="fa-solid fa-signature edit-org-label-icon"></i>
                    Organization Name
                </label>
                <input
                    type="text"
                    name="name"
                    id="org-name"
                    value="<?= htmlspecialchars($org['name']) ?>"
                    required
                    class="edit-org-input"
                    placeholder="Enter organization name"
                >
            </div>

            <!-- Contact Email -->
            <div class="edit-org-form-group">
                <label class="edit-org-label" for="org-email">
                    <i class="fa-solid fa-envelope edit-org-label-icon"></i>
                    Contact Email
                </label>
                <input
                    type="email"
                    name="email"
                    id="org-email"
                    value="<?= htmlspecialchars($org['contact_email']) ?>"
                    required
                    class="edit-org-input"
                    placeholder="org@example.com"
                >
            </div>

            <!-- Website -->
            <div class="edit-org-form-group">
                <label class="edit-org-label" for="org-website">
                    <i class="fa-solid fa-globe edit-org-label-icon"></i>
                    Website
                </label>
                <input
                    type="url"
                    name="website"
                    id="org-website"
                    value="<?= htmlspecialchars($org['website']) ?>"
                    class="edit-org-input"
                    placeholder="https://..."
                >
            </div>

            <!-- Description -->
            <div class="edit-org-form-group">
                <label class="edit-org-label" for="org-description">
                    <i class="fa-solid fa-align-left edit-org-label-icon"></i>
                    Description
                </label>
                <textarea
                    name="description"
                    id="org-description"
                    rows="5"
                    required
                    class="edit-org-textarea"
                    placeholder="Describe your organization's mission and activities..."
                ><?= htmlspecialchars($org['description']) ?></textarea>
            </div>

            <?php if (Nexus\Core\TenantContext::hasFeature('wallet')): ?>
                <!-- Auto-Pay Feature Box -->
                <div class="edit-org-feature-box">
                    <label class="edit-org-feature-label">
                        <input
                            type="checkbox"
                            name="auto_pay"
                            value="1"
                            <?= $org['auto_pay_enabled'] ? 'checked' : '' ?>
                            class="edit-org-feature-checkbox"
                        >
                        <div>
                            <p class="edit-org-feature-title">
                                <i class="fa-solid fa-wand-magic-sparkles edit-org-feature-icon"></i>
                                Enable Auto-Pay Time Credits
                            </p>
                            <p class="edit-org-feature-description">
                                When enabled, approving hours will automatically transfer Time Credits from your personal wallet to the volunteer's wallet (1 Hour = 1 Credit).
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Quick Actions -->
                <div class="edit-org-quick-actions">
                    <p class="edit-org-quick-actions-title">
                        <i class="fa-solid fa-bolt edit-org-quick-actions-icon"></i>
                        Quick Actions
                    </p>
                    <div class="edit-org-quick-actions-grid">
                        <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet" class="edit-org-quick-btn edit-org-quick-btn--primary">
                            <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                            Org Wallet
                        </a>
                        <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/members" class="edit-org-quick-btn edit-org-quick-btn--secondary">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            Members
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Actions -->
            <div class="edit-org-form-actions">
                <button type="submit" class="edit-org-btn edit-org-btn--primary" id="submitBtn">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Save Changes
                </button>
                <a href="<?= $basePath ?>/volunteering/dashboard" class="edit-org-btn edit-org-btn--secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</div>


<script src="/assets/js/civicone-volunteering-edit-org.js"></script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
