<?php
/**
 * Groups Settings
 * Path: views/modern/admin/groups/settings.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$adminPageTitle = 'Groups Settings';
$adminPageSubtitle = 'Configure group module behavior';
$adminPageIcon = 'fa-gear';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            <i class="fa-solid fa-gear" style="color: #a855f7;"></i>
            Groups Settings
        </h1>
        <p class="admin-page-subtitle">Configure module behavior and permissions</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/groups" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-check-circle"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Settings Saved!</div>
        <div class="admin-alert-text">Your configuration has been updated successfully.</div>
    </div>
</div>
<?php endif; ?>

<form method="POST" action="<?= $basePath ?>/admin/groups/settings">
    <?= Csrf::input() ?>

    <!-- General Settings -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title"><i class="fa-solid fa-sliders"></i> General Settings</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-form-group">
                <label class="admin-toggle-label">
                    <input type="checkbox" name="allow_user_group_creation" value="1" <?= ($config['allow_user_group_creation'] ?? true) ? 'checked' : '' ?>>
                    <span>Allow users to create groups</span>
                </label>
                <p class="admin-form-help">Allow regular users to create their own groups</p>
            </div>

            <div class="admin-form-group">
                <label class="admin-toggle-label">
                    <input type="checkbox" name="require_group_approval" value="1" <?= ($config['require_group_approval'] ?? false) ? 'checked' : '' ?>>
                    <span>Require admin approval for new groups</span>
                </label>
            </div>

            <div class="admin-form-group">
                <label>Max groups per user</label>
                <input type="number" name="max_groups_per_user" class="admin-form-control" value="<?= $config['max_groups_per_user'] ?? 10 ?>" min="0">
            </div>

            <div class="admin-form-group">
                <label>Max members per group</label>
                <input type="number" name="max_members_per_group" class="admin-form-control" value="<?= $config['max_members_per_group'] ?? 1000 ?>" min="0">
            </div>
        </div>
    </div>

    <!-- Privacy & Permissions -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title"><i class="fa-solid fa-lock"></i> Privacy & Permissions</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-form-group">
                <label class="admin-toggle-label">
                    <input type="checkbox" name="allow_private_groups" value="1" <?= ($config['allow_private_groups'] ?? true) ? 'checked' : '' ?>>
                    <span>Allow private groups</span>
                </label>
            </div>

            <div class="admin-form-group">
                <label class="admin-toggle-label">
                    <input type="checkbox" name="require_location" value="1" <?= ($config['require_location'] ?? false) ? 'checked' : '' ?>>
                    <span>Require location for groups</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Features -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title"><i class="fa-solid fa-puzzle-piece"></i> Features</h3>
        </div>
        <div class="admin-card-body">
            <div class="admin-form-group">
                <label class="admin-toggle-label">
                    <input type="checkbox" name="enable_discussions" value="1" <?= ($config['enable_discussions'] ?? true) ? 'checked' : '' ?>>
                    <span>Enable group discussions</span>
                </label>
            </div>

            <div class="admin-form-group">
                <label class="admin-toggle-label">
                    <input type="checkbox" name="enable_feedback" value="1" <?= ($config['enable_feedback'] ?? true) ? 'checked' : '' ?>>
                    <span>Enable group feedback/testimonials</span>
                </label>
            </div>

            <div class="admin-form-group">
                <label class="admin-toggle-label">
                    <input type="checkbox" name="enable_achievements" value="1" <?= ($config['enable_achievements'] ?? true) ? 'checked' : '' ?>>
                    <span>Enable achievements & badges</span>
                </label>
            </div>
        </div>
    </div>

    <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-save"></i> Save Settings
        </button>
        <button type="button" class="admin-btn admin-btn-secondary" onclick="location.reload()">
            <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
    </div>
</form>

<link rel="stylesheet" href="<?= $basePath ?>/assets/css/groups-admin-gold-standard.min.css">

<style>
/* Toggle Labels */
.admin-toggle-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 0.5rem 0;
}

.admin-toggle-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #06b6d4;
}

.admin-toggle-label span {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
    font-size: 0.9rem;
}

.admin-form-help {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 4px;
    padding-left: 32px;
}

/* Enhanced Form Groups */
.admin-form-group {
    margin-bottom: 1.5rem;
}

.admin-form-group label:not(.admin-toggle-label) {
    display: block;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

/* Form Actions */
.admin-form-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
