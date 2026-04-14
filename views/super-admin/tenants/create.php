<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - Create Tenant Form
 */

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Create Tenant';
$parentTenant = $parentTenant ?? null;
$availableParents = $availableParents ?? [];
require __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/tenants"><?= __('super_admin.tenants.show.breadcrumb_tenants') ?></a>
    <span class="super-breadcrumb-sep">/</span>
    <span><?= __('super_admin.tenants.create.breadcrumb_create') ?></span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-plus-circle"></i>
            <?= __('super_admin.tenants.create.title') ?>
        </h1>
        <p class="super-page-subtitle">
            <?php if ($parentTenant): ?>
                <?= __('super_admin.tenants.create.subtitle_selected', ['parent_name' => '<strong>' . htmlspecialchars($parentTenant['name']) . '</strong>']) ?>
            <?php else: ?>
                <?= __('super_admin.tenants.create.subtitle_default') ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="super-alert super-alert-danger" style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-exclamation-circle"></i>
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="super-card">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-building"></i>
            <?= __('super_admin.tenants.create.card_title') ?>
        </h3>
    </div>
    <div class="super-card-body">
        <form method="POST" action="/super-admin/tenants/store">
            <?= Csrf::field() ?>

            <!-- Parent Tenant Selection -->
            <div class="super-form-group">
                <label class="super-form-label">
                    <?= __('super_admin.tenants.create.parent_label') ?> <span style="color: var(--super-danger);">*</span>
                </label>
                <select name="parent_id" class="super-form-select" required <?= $parentTenant ? 'disabled' : '' ?>>
                    <option value=""><?= __('super_admin.tenants.create.parent_placeholder') ?></option>
                    <?php foreach ($availableParents as $parent): ?>
                        <option value="<?= $parent['id'] ?>"
                            <?= ($parentTenant && $parentTenant['id'] == $parent['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($parent['display_name']) ?>
                            <?= __('super_admin.tenants.create.depth_label', ['depth' => $parent['depth']]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($parentTenant): ?>
                    <input type="hidden" name="parent_id" value="<?= $parentTenant['id'] ?>">
                <?php endif; ?>
                <p class="super-form-help">
                    <?= __('super_admin.tenants.create.parent_help') ?>
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <!-- Name -->
                <div class="super-form-group">
                    <label class="super-form-label">
                        <?= __('super_admin.tenants.create.name_label') ?> <span style="color: var(--super-danger);">*</span>
                    </label>
                    <input type="text" name="name" class="super-form-input" required
                           placeholder="e.g., Acme Corporation"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <!-- Slug -->
                <div class="super-form-group">
                    <label class="super-form-label">
                        <?= __('super_admin.tenants.create.slug_label') ?> <span style="color: var(--super-danger);">*</span>
                    </label>
                    <input type="text" name="slug" class="super-form-input" required
                           placeholder="e.g., acme-corp"
                           pattern="[a-z0-9-]+"
                           value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
                    <p class="super-form-help"><?= __('super_admin.tenants.create.slug_help') ?></p>
                </div>
            </div>

            <!-- Tagline -->
            <div class="super-form-group">
                <label class="super-form-label"><?= __('super_admin.tenants.create.tagline_label') ?></label>
                <input type="text" name="tagline" class="super-form-input"
                       placeholder="e.g., Building the future together"
                       value="<?= htmlspecialchars($_POST['tagline'] ?? '') ?>">
            </div>

            <!-- Domain -->
            <div class="super-form-group">
                <label class="super-form-label"><?= __('super_admin.tenants.create.domain_label') ?></label>
                <input type="text" name="domain" class="super-form-input"
                       placeholder="e.g., acme.example.com"
                       value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>">
                <p class="super-form-help"><?= __('super_admin.tenants.create.domain_help') ?></p>
            </div>

            <!-- Description -->
            <div class="super-form-group">
                <label class="super-form-label"><?= __('super_admin.tenants.create.description_label') ?></label>
                <textarea name="description" class="super-form-textarea" rows="3"
                          placeholder="<?= __('super_admin.tenants.create.description_placeholder') ?>"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- Settings -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem;">
                <div class="super-form-group">
                    <label class="super-form-checkbox">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span><?= __('super_admin.tenants.create.is_active_label') ?></span>
                    </label>
                    <p class="super-form-help"><?= __('super_admin.tenants.create.is_active_help') ?></p>
                </div>

                <div class="super-form-group">
                    <label class="super-form-checkbox">
                        <input type="checkbox" name="allows_subtenants" value="1">
                        <span><?= __('super_admin.tenants.create.allows_subtenants_label') ?></span>
                    </label>
                    <p class="super-form-help"><?= __('super_admin.tenants.create.allows_subtenants_help') ?></p>
                </div>
            </div>

            <!-- Max Depth -->
            <div class="super-form-group">
                <label class="super-form-label"><?= __('super_admin.tenants.create.max_depth_label') ?></label>
                <select name="max_depth" class="super-form-select">
                    <option value="0"><?= __('super_admin.tenants.create.max_depth_unlimited') ?></option>
                    <option value="1">1 level deep</option>
                    <option value="2">2 levels deep</option>
                    <option value="3">3 levels deep</option>
                    <option value="5">5 levels deep</option>
                </select>
                <p class="super-form-help"><?= __('super_admin.tenants.create.max_depth_help') ?></p>
            </div>

            <!-- Submit -->
            <div style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                <button type="submit" class="super-btn super-btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    <?= __('super_admin.tenants.create.submit_btn') ?>
                </button>
                <a href="/super-admin/tenants" class="super-btn super-btn-secondary">
                    <i class="fa-solid fa-times"></i>
                    <?= __('super_admin.tenants.create.cancel_btn') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-generate slug from name
document.querySelector('input[name="name"]').addEventListener('input', function(e) {
    const slugInput = document.querySelector('input[name="slug"]');
    if (!slugInput.dataset.manual) {
        slugInput.value = e.target.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

document.querySelector('input[name="slug"]').addEventListener('input', function(e) {
    e.target.dataset.manual = 'true';
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
