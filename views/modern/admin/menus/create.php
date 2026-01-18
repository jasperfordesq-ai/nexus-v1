<?php
/**
 * Admin Menu Creation Form
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Create Menu';
$adminPageSubtitle = 'Navigation';
$adminPageIcon = 'fa-plus';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/menus" class="admin-back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Create New Menu
        </h1>
        <p class="admin-page-subtitle">Configure your new navigation menu</p>
    </div>
</div>

<!-- Create Form -->
<div class="admin-glass-card" style="max-width: 800px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-blue">
            <i class="fa-solid fa-plus"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Menu Configuration</h3>
            <p class="admin-card-subtitle">Set up the basic menu properties</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form method="POST" action="<?= $basePath ?>/admin/menus/create">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

            <div class="form-group">
                <label for="name">Menu Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g., Main Navigation">
                <small>A descriptive name for this menu (visible only to you)</small>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" placeholder="Optional description for this menu"></textarea>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="location">Menu Location *</label>
                    <select id="location" name="location" required>
                        <?php foreach ($available_locations as $value => $label): ?>
                        <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Where this menu should appear</small>
                </div>

                <div class="form-group">
                    <label for="layout">Layout (Optional)</label>
                    <select id="layout" name="layout">
                        <option value="">All Layouts</option>
                        <?php foreach ($available_layouts as $layout): ?>
                        <option value="<?= $layout ?>"><?= htmlspecialchars($layout) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Restrict to specific layout or leave blank for all</small>
                </div>
            </div>

            <?php if (isset($plan_status['plan_name'])): ?>
            <div class="plan-info-box" style="background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; padding: 1rem; border-radius: 0.25rem; margin-top: 1.5rem;">
                <strong>Plan: <?= htmlspecialchars($plan_status['plan_name']) ?></strong>
                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: rgba(255, 255, 255, 0.7);">
                    You can create menus for these layouts: <?= implode(', ', $available_layouts) ?>
                </p>
            </div>
            <?php endif; ?>

            <div class="form-actions" style="display: flex; gap: 1rem; margin-top: 2rem;">
                <a href="<?= $basePath ?>/admin/menus" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-check"></i> Create Menu
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.admin-back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
}

.admin-back-link:hover {
    opacity: 0.8;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.form-group input[type="text"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.375rem;
    background: rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 1rem;
}

.form-group input[type="text"]:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #3b82f6;
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
