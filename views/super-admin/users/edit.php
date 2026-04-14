<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - Edit User (Full Control)
 *
 * This is the MASTER SUPER ADMIN view for managing users across the hierarchy.
 * Key features:
 * - Edit user details
 * - Move user to any visible tenant
 * - Move user to Hub tenant AND grant Super Admin (combo action)
 * - Grant/Revoke Super Admin for current tenant
 */

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Edit User';
require __DIR__ . '/../partials/header.php';

$isMasterAdmin = ($access['level'] === 'master');
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/users"><?= __('super_admin.users.show.breadcrumb_users') ?></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/users/<?= $user['id'] ?>"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></a>
    <span class="super-breadcrumb-sep">/</span>
    <span><?= __('super_admin.users.edit.breadcrumb_edit') ?></span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--super-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 600;">
            <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div>
            <h1 class="super-page-title" style="margin-bottom: 0.25rem;">
                <?= __('super_admin.users.edit.title') ?>
            </h1>
            <p class="super-page-subtitle" style="margin: 0;">
                <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                &middot; <?= htmlspecialchars($user['email']) ?>
            </p>
        </div>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/users/<?= $user['id'] ?>" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            <?= __('super_admin.users.edit.back_btn') ?>
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="super-alert super-alert-success" style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-check-circle"></i>
        <?= htmlspecialchars($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="super-alert super-alert-danger" style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-exclamation-circle"></i>
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Main Form Column -->
    <div>
        <!-- Basic Info Card -->
        <div class="super-card" style="margin-bottom: 1.5rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-user"></i>
                    User Details
                </h3>
            </div>
            <div class="super-card-body">
                <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/update">
                    <?= Csrf::field() ?>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <div class="super-form-group">
                            <label class="super-form-label">
                                <?= __('super_admin.users.edit.first_name_label') ?> <span style="color: var(--super-danger);">*</span>
                            </label>
                            <input type="text" name="first_name" class="super-form-input" required
                                   value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                        </div>

                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.users.edit.last_name_label') ?></label>
                            <input type="text" name="last_name" class="super-form-input"
                                   value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="super-form-group">
                        <label class="super-form-label">
                            <?= __('super_admin.users.edit.email_label') ?> <span style="color: var(--super-danger);">*</span>
                        </label>
                        <input type="email" name="email" class="super-form-input" required
                               value="<?= htmlspecialchars($user['email']) ?>">
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.users.edit.role_label') ?></label>
                            <select name="role" class="super-form-select">
                                <option value="member" <?= $user['role'] === 'member' ? 'selected' : '' ?>><?= __('super_admin.users.index.filter_role_member') ?></option>
                                <option value="moderator" <?= $user['role'] === 'moderator' ? 'selected' : '' ?>><?= __('super_admin.users.index.filter_role_moderator') ?></option>
                                <option value="tenant_admin" <?= $user['role'] === 'tenant_admin' ? 'selected' : '' ?>><?= __('super_admin.users.index.filter_role_tenant_admin') ?></option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>><?= __('super_admin.users.index.filter_role_admin') ?></option>
                            </select>
                        </div>

                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.users.edit.location_label') ?></label>
                            <input type="text" name="location" class="super-form-input"
                                   value="<?= htmlspecialchars($user['location'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="super-form-group">
                        <label class="super-form-label"><?= __('super_admin.users.edit.phone_label') ?></label>
                        <input type="text" name="phone" class="super-form-input"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>

                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                        <button type="submit" class="super-btn super-btn-primary">
                            <i class="fa-solid fa-save"></i>
                            <?= __('super_admin.users.edit.save_btn') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Move User to Different Tenant -->
        <?php if ($canManage && count($tenants) > 1): ?>
            <div class="super-card" style="margin-bottom: 1.5rem;">
                <div class="super-card-header">
                    <h3 class="super-card-title">
                        <i class="fa-solid fa-exchange-alt"></i>
                        <?= __('super_admin.users.edit.move_tenant_card_title') ?>
                    </h3>
                </div>
                <div class="super-card-body">
                    <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                        <?= __('super_admin.users.edit.move_tenant_desc') ?>
                    </p>
                    <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/move-tenant">
                        <?= Csrf::field() ?>
                        <div style="display: flex; gap: 0.5rem;">
                            <select name="new_tenant_id" class="super-form-select" style="flex: 1;" required>
                                <option value=""><?= __('super_admin.users.edit.move_tenant_placeholder') ?></option>
                                <?php foreach ($tenants as $t): ?>
                                    <?php if ($t['id'] != $tenant['id']): ?>
                                        <option value="<?= $t['id'] ?>">
                                            <?= htmlspecialchars($t['indented_name']) ?>
                                            <?= $t['allows_subtenants'] ? ' (Hub)' : '' ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="super-btn super-btn-warning"
                                    onclick="return confirm('<?= __('super_admin.users.edit.move_tenant_confirm') ?>');">
                                <i class="fa-solid fa-exchange-alt"></i>
                                <?= __('super_admin.users.edit.move_btn') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- MASTER FEATURE: Move to Hub + Grant Super Admin (Combo Action) -->
        <?php if ($isMasterAdmin && $canManage && !empty($hubTenants)): ?>
            <div class="super-card" style="border: 2px solid var(--super-purple); margin-bottom: 1.5rem;">
                <div class="super-card-header" style="background: rgba(139, 92, 246, 0.1);">
                    <h3 class="super-card-title" style="color: var(--super-purple);">
                        <i class="fa-solid fa-crown"></i>
                        <?= __('super_admin.users.edit.assign_regional_card_title') ?>
                    </h3>
                    <span class="super-badge super-badge-purple"><?= __('super_admin.users.edit.master_only_badge') ?></span>
                </div>
                <div class="super-card-body">
                    <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                        <?= __('super_admin.users.edit.assign_desc') ?>
                    </p>

                    <div style="background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                        <strong style="display: block; margin-bottom: 0.5rem;">
                            <i class="fa-solid fa-info-circle"></i> <?= __('super_admin.users.edit.assign_what_happens') ?>
                        </strong>
                        <ol style="margin: 0; padding-left: 1.25rem; color: var(--super-text-muted); font-size: 0.875rem;">
                            <li><?= __('super_admin.users.edit.assign_step1') ?></li>
                            <li><?= __('super_admin.users.edit.assign_step2') ?></li>
                            <li><?= __('super_admin.users.edit.assign_step3') ?></li>
                            <li><?= __('super_admin.users.edit.assign_step4') ?></li>
                        </ol>
                    </div>

                    <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/move-and-promote">
                        <?= Csrf::field() ?>
                        <div class="super-form-group">
                            <label class="super-form-label"><?= __('super_admin.users.edit.hub_tenant_label') ?></label>
                            <select name="target_tenant_id" class="super-form-select" required>
                                <option value=""><?= __('super_admin.users.edit.hub_tenant_placeholder') ?></option>
                                <?php foreach ($hubTenants as $hub): ?>
                                    <option value="<?= $hub['id'] ?>">
                                        <?= htmlspecialchars($hub['indented_name']) ?>
                                        (Level <?= $hub['depth'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="super-form-help"><?= __('super_admin.users.edit.hub_tenant_help') ?></p>
                        </div>
                        <button type="submit" class="super-btn super-btn-purple" style="width: 100%; justify-content: center;"
                                onclick="return confirm('<?= __('super_admin.users.edit.move_grant_confirm') ?>');">
                            <i class="fa-solid fa-crown"></i>
                            <?= __('super_admin.users.edit.move_grant_btn') ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Current Status -->
        <div class="super-card" style="margin-bottom: 1rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-info-circle"></i>
                    <?= __('super_admin.users.edit.status_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <div style="display: grid; gap: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.users.edit.stat_user_id') ?></span>
                        <strong><?= $user['id'] ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.users.edit.stat_current_tenant') ?></span>
                        <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-table-link">
                            <?= htmlspecialchars($tenant['name']) ?>
                        </a>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.users.edit.stat_tenant_type') ?></span>
                        <?php if ($tenant['allows_subtenants']): ?>
                            <span class="super-badge super-badge-purple"><?= __('super_admin.users.edit.stat_hub') ?></span>
                        <?php else: ?>
                            <span class="super-badge super-badge-secondary"><?= __('super_admin.users.edit.stat_standard') ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.users.edit.stat_super_admin') ?></span>
                        <?php if (!empty($user['is_tenant_super_admin'])): ?>
                            <span class="super-badge super-badge-purple">
                                <i class="fa-solid fa-crown"></i> <?= __('super_admin.common.yes') ?>
                            </span>
                        <?php else: ?>
                            <span style="color: var(--super-text-muted);"><?= __('super_admin.common.no') ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.users.edit.stat_account_status') ?></span>
                        <?php if ($user['is_approved']): ?>
                            <span class="super-badge super-badge-success"><?= __('super_admin.users.edit.stat_active') ?></span>
                        <?php else: ?>
                            <span class="super-badge super-badge-warning"><?= __('super_admin.users.edit.stat_pending') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Super Admin Toggle (for current tenant) -->
        <?php if ($canManage && $tenant['allows_subtenants']): ?>
            <div class="super-card" style="margin-bottom: 1rem;">
                <div class="super-card-header">
                    <h3 class="super-card-title">
                        <i class="fa-solid fa-shield-alt"></i>
                        <?= __('super_admin.users.edit.super_admin_card_title') ?>
                    </h3>
                </div>
                <div class="super-card-body">
                    <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                        <?= __('super_admin.users.edit.super_admin_toggle_desc', ['tenant_name' => '<strong>' . htmlspecialchars($tenant['name']) . '</strong>']) ?>
                    </p>

                    <?php if (!empty($user['is_tenant_super_admin'])): ?>
                        <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/revoke-super-admin">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn super-btn-danger" style="width: 100%; justify-content: center;"
                                    onclick="return confirm('<?= __('super_admin.users.edit.revoke_confirm') ?>');">
                                <i class="fa-solid fa-user-minus"></i>
                                <?= __('super_admin.users.edit.revoke_btn') ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/grant-super-admin">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn super-btn-success" style="width: 100%; justify-content: center;"
                                    onclick="return confirm('<?= __('super_admin.users.edit.grant_confirm') ?>');">
                                <i class="fa-solid fa-user-plus"></i>
                                <?= __('super_admin.users.edit.grant_btn') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($canManage && !$tenant['allows_subtenants']): ?>
            <div class="super-card" style="margin-bottom: 1rem;">
                <div class="super-card-header">
                    <h3 class="super-card-title">
                        <i class="fa-solid fa-shield-alt"></i>
                        <?= __('super_admin.users.edit.super_admin_card_title') ?>
                    </h3>
                </div>
                <div class="super-card-body">
                    <p style="color: var(--super-text-muted); font-size: 0.875rem;">
                        <i class="fa-solid fa-info-circle"></i>
                        <?= __('super_admin.users.edit.no_hub_text', ['tenant_name' => '<strong>' . htmlspecialchars($tenant['name']) . '</strong>']) ?>
                    </p>
                    <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-top: 0.5rem;">
                        <?= __('super_admin.users.edit.no_hub_move_text') ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Links -->
        <div class="super-card">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-link"></i>
                    <?= __('super_admin.users.edit.quick_links_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <a href="/super-admin/users/<?= $user['id'] ?>" class="super-btn super-btn-secondary" style="justify-content: center;">
                        <i class="fa-solid fa-user"></i>
                        <?= __('super_admin.users.edit.view_profile_btn') ?>
                    </a>
                    <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-btn super-btn-secondary" style="justify-content: center;">
                        <i class="fa-solid fa-building"></i>
                        <?= __('super_admin.users.edit.view_tenant_btn') ?>
                    </a>
                    <a href="/super-admin/users?tenant_id=<?= $tenant['id'] ?>" class="super-btn super-btn-secondary" style="justify-content: center;">
                        <i class="fa-solid fa-users"></i>
                        <?= __('super_admin.users.edit.tenant_users_btn') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.super-btn-purple {
    background: var(--super-purple);
    color: white;
}
.super-btn-purple:hover {
    background: #7c3aed;
}
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>
