<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - Create User Form
 */

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Create User';
require __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/users"><?= __('super_admin.users.show.breadcrumb_users') ?></a>
    <span class="super-breadcrumb-sep">/</span>
    <span><?= __('super_admin.users.create.breadcrumb_create') ?></span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-user-plus"></i>
            <?= __('super_admin.users.create.title') ?>
        </h1>
        <p class="super-page-subtitle">
            <?php if ($selectedTenant): ?>
                <?= __('super_admin.users.create.subtitle_selected', ['tenant_name' => '<strong>' . htmlspecialchars($selectedTenant['name']) . '</strong>']) ?>
            <?php else: ?>
                <?= __('super_admin.users.create.subtitle_default') ?>
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
            <i class="fa-solid fa-user"></i>
            <?= __('super_admin.users.create.card_title') ?>
        </h3>
    </div>
    <div class="super-card-body">
        <form method="POST" action="/super-admin/users/store">
            <?= Csrf::field() ?>

            <!-- Tenant Selection -->
            <div class="super-form-group">
                <label class="super-form-label">
                    <?= __('super_admin.users.create.tenant_label') ?> <span style="color: var(--super-danger);">*</span>
                </label>
                <select name="tenant_id" class="super-form-select" required <?= $selectedTenant ? 'disabled' : '' ?>>
                    <option value=""><?= __('super_admin.users.create.tenant_placeholder') ?></option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>"
                            <?= ($selectedTenant && $selectedTenant['id'] == $tenant['id']) ? 'selected' : '' ?>
                            <?= ($tenantId == $tenant['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tenant['indented_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($selectedTenant): ?>
                    <input type="hidden" name="tenant_id" value="<?= $selectedTenant['id'] ?>">
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <!-- First Name -->
                <div class="super-form-group">
                    <label class="super-form-label">
                        <?= __('super_admin.users.create.first_name_label') ?> <span style="color: var(--super-danger);">*</span>
                    </label>
                    <input type="text" name="first_name" class="super-form-input" required
                           placeholder="John"
                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>

                <!-- Last Name -->
                <div class="super-form-group">
                    <label class="super-form-label"><?= __('super_admin.users.create.last_name_label') ?></label>
                    <input type="text" name="last_name" class="super-form-input"
                           placeholder="Doe"
                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
            </div>

            <!-- Email -->
            <div class="super-form-group">
                <label class="super-form-label">
                    <?= __('super_admin.users.create.email_label') ?> <span style="color: var(--super-danger);">*</span>
                </label>
                <input type="email" name="email" class="super-form-input" required
                       placeholder="john.doe@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <!-- Password -->
            <div class="super-form-group">
                <label class="super-form-label">
                    <?= __('super_admin.users.create.password_label') ?> <span style="color: var(--super-danger);">*</span>
                </label>
                <input type="password" name="password" class="super-form-input" required
                       placeholder="<?= __('super_admin.users.create.password_placeholder') ?>"
                       minlength="8">
                <p class="super-form-help"><?= __('super_admin.users.create.password_help') ?></p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <!-- Role -->
                <div class="super-form-group">
                    <label class="super-form-label"><?= __('super_admin.users.create.role_label') ?></label>
                    <select name="role" class="super-form-select">
                        <option value="member"><?= __('super_admin.users.index.filter_role_member') ?></option>
                        <option value="moderator"><?= __('super_admin.users.index.filter_role_moderator') ?></option>
                        <option value="tenant_admin"><?= __('super_admin.users.index.filter_role_tenant_admin') ?></option>
                        <option value="admin"><?= __('super_admin.users.index.filter_role_admin') ?></option>
                    </select>
                </div>

                <!-- Location -->
                <div class="super-form-group">
                    <label class="super-form-label"><?= __('super_admin.users.create.location_label') ?></label>
                    <input type="text" name="location" class="super-form-input"
                           placeholder="<?= __('super_admin.users.create.location_placeholder') ?>"
                           value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                </div>
            </div>

            <!-- Phone -->
            <div class="super-form-group">
                <label class="super-form-label"><?= __('super_admin.users.create.phone_label') ?></label>
                <input type="text" name="phone" class="super-form-input"
                       placeholder="+1 234 567 8900"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <!-- Super Admin Option -->
            <div class="super-form-group" style="margin-top: 1rem; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
                <label class="super-form-checkbox">
                    <input type="checkbox" name="is_tenant_super_admin" value="1">
                    <span><?= __('super_admin.users.create.super_admin_label') ?></span>
                </label>
                <p class="super-form-help" style="margin-left: 1.75rem;">
                    <?= __('super_admin.users.create.super_admin_help') ?>
                </p>
            </div>

            <!-- Submit -->
            <div style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                <button type="submit" class="super-btn super-btn-primary">
                    <i class="fa-solid fa-user-plus"></i>
                    <?= __('super_admin.users.create.submit_btn') ?>
                </button>
                <a href="/super-admin/users" class="super-btn super-btn-secondary">
                    <i class="fa-solid fa-times"></i>
                    <?= __('super_admin.users.create.cancel_btn') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
