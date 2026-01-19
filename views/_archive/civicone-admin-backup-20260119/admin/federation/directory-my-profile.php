<?php
/**
 * Federation Directory - My Profile
 * Edit how your timebank appears in the directory
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'My Directory Profile';
$adminPageSubtitle = 'Edit your federation presence';
$adminPageIcon = 'fa-building';

require __DIR__ . '/../partials/admin-header.php';

$profile = $profile ?? [];
$settings = $settings ?? [];
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-building"></i>
            My Directory Profile
        </h1>
        <p class="admin-page-subtitle">Control how your timebank appears to others</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/federation/directory" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-compass"></i>
            View Directory
        </a>
    </div>
</div>

<form action="<?= $basePath ?>/admin/federation/directory-my-profile" method="POST" enctype="multipart/form-data">
    <?= Csrf::input() ?>

    <div class="fed-grid-2">
        <!-- Profile Info -->
        <div class="fed-admin-card">
            <div class="fed-admin-card-header">
                <h3 class="fed-admin-card-title">
                    <i class="fa-solid fa-info-circle"></i>
                    Profile Information
                </h3>
            </div>
            <div class="fed-admin-card-body">
                <div class="admin-form-group">
                    <label class="admin-label">Timebank Name</label>
                    <input type="text" name="name" class="admin-input" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" readonly>
                    <small class="admin-text-muted">Name is managed in tenant settings</small>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Description</label>
                    <textarea name="description" class="admin-input" rows="4" placeholder="Tell other timebanks about your community..."><?= htmlspecialchars($profile['description'] ?? '') ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Location</label>
                    <input type="text" name="location" class="admin-input" value="<?= htmlspecialchars($profile['location'] ?? '') ?>" placeholder="City, Region">
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Website</label>
                    <input type="url" name="website" class="admin-input" value="<?= htmlspecialchars($profile['website'] ?? '') ?>" placeholder="https://...">
                </div>
            </div>
        </div>

        <!-- Visibility Settings -->
        <div class="fed-admin-card">
            <div class="fed-admin-card-header">
                <h3 class="fed-admin-card-title">
                    <i class="fa-solid fa-eye"></i>
                    Visibility Settings
                </h3>
            </div>
            <div class="fed-admin-card-body">
                <div class="admin-toggle-list">
                    <div class="admin-toggle-item">
                        <div class="admin-toggle-info">
                            <i class="fa-solid fa-eye admin-toggle-icon"></i>
                            <div>
                                <div class="admin-toggle-title">Appear in Directory</div>
                                <div class="admin-toggle-desc">Let other timebanks discover you</div>
                            </div>
                        </div>
                        <label class="admin-switch">
                            <input type="checkbox" name="appear_in_directory" <?= !empty($settings['appear_in_directory']) ? 'checked' : '' ?>>
                            <span class="admin-switch-slider"></span>
                        </label>
                    </div>

                    <div class="admin-toggle-item">
                        <div class="admin-toggle-info">
                            <i class="fa-solid fa-users admin-toggle-icon"></i>
                            <div>
                                <div class="admin-toggle-title">Show Member Count</div>
                                <div class="admin-toggle-desc">Display your member count publicly</div>
                            </div>
                        </div>
                        <label class="admin-switch">
                            <input type="checkbox" name="show_member_count" <?= !empty($settings['show_member_count']) ? 'checked' : '' ?>>
                            <span class="admin-switch-slider"></span>
                        </label>
                    </div>

                    <div class="admin-toggle-item">
                        <div class="admin-toggle-info">
                            <i class="fa-solid fa-list admin-toggle-icon"></i>
                            <div>
                                <div class="admin-toggle-title">Show Listing Count</div>
                                <div class="admin-toggle-desc">Display your listing count publicly</div>
                            </div>
                        </div>
                        <label class="admin-switch">
                            <input type="checkbox" name="show_listing_count" <?= !empty($settings['show_listing_count']) ? 'checked' : '' ?>>
                            <span class="admin-switch-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top: 1.5rem;">
        <button type="submit" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-save"></i>
            Save Changes
        </button>
    </div>
</form>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>
