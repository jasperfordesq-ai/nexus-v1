<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - View User Details
 */

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'User Details';
require __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/users"><?= __('super_admin.users.show.breadcrumb_users') ?></a>
    <span class="super-breadcrumb-sep">/</span>
    <span><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--super-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 600;">
            <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div>
            <h1 class="super-page-title" style="margin-bottom: 0.25rem;">
                <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
            </h1>
            <p class="super-page-subtitle" style="margin: 0;">
                <?= htmlspecialchars($user['email']) ?>
            </p>
        </div>
    </div>
    <div class="super-page-actions">
        <?php if ($canManage): ?>
            <a href="/super-admin/users/<?= $user['id'] ?>/edit" class="super-btn super-btn-primary">
                <i class="fa-solid fa-pen"></i>
                <?= __('super_admin.users.show.edit_btn') ?>
            </a>
        <?php endif; ?>
        <a href="/super-admin/users" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            <?= __('super_admin.users.show.back_btn') ?>
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
    <!-- Main Content -->
    <div>
        <!-- User Info Card -->
        <div class="super-card" style="margin-bottom: 1.5rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-user"></i>
                    <?= __('super_admin.users.show.info_card_title') ?>
                </h3>
                <div>
                    <?php if ($user['is_approved']): ?>
                        <span class="super-badge super-badge-success"><?= __('super_admin.users.show.status_active') ?></span>
                    <?php else: ?>
                        <span class="super-badge super-badge-warning"><?= __('super_admin.users.show.status_pending') ?></span>
                    <?php endif; ?>
                    <?php if ($user['is_tenant_super_admin'] ?? false): ?>
                        <span class="super-badge super-badge-purple">
                            <i class="fa-solid fa-crown"></i> <?= __('super_admin.users.show.super_admin_badge') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="super-card-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.users.show.field_first_name') ?></strong>
                        <span><?= htmlspecialchars($user['first_name'] ?? '-') ?></span>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.users.show.field_last_name') ?></strong>
                        <span><?= htmlspecialchars($user['last_name'] ?? '-') ?></span>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.users.show.field_email') ?></strong>
                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>" style="color: var(--super-primary);">
                            <?= htmlspecialchars($user['email']) ?>
                        </a>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.users.show.field_role') ?></strong>
                        <span class="super-badge super-badge-info"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.users.show.field_location') ?></strong>
                        <span><?= htmlspecialchars($user['location'] ?? '-') ?></span>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.users.show.field_phone') ?></strong>
                        <span><?= htmlspecialchars($user['phone'] ?? '-') ?></span>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.users.show.field_created') ?></strong>
                        <span><?= date('M j, Y g:ia', strtotime($user['created_at'])) ?></span>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;"><?= __('super_admin.users.show.field_last_login') ?></strong>
                        <span><?= $user['last_login_at'] ? date('M j, Y g:ia', strtotime($user['last_login_at'])) : __('super_admin.users.show.never_logged_in') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tenant Association -->
        <div class="super-card">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-building"></i>
                    <?= __('super_admin.users.show.tenant_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
                    <div style="width: 48px; height: 48px; border-radius: 8px; background: var(--super-primary); color: white; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <div style="flex: 1;">
                        <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-table-link" style="font-size: 1.1rem;">
                            <?= htmlspecialchars($tenant['name']) ?>
                        </a>
                        <div style="font-size: 0.875rem; color: var(--super-text-muted);">
                            <?= htmlspecialchars($tenant['slug']) ?>
                            <?php if ($tenant['domain']): ?>
                                &middot; <?= htmlspecialchars($tenant['domain']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <?php if ($tenant['is_active']): ?>
                            <span class="super-badge super-badge-success"><?= __('super_admin.common.active') ?></span>
                        <?php else: ?>
                            <span class="super-badge super-badge-danger"><?= __('super_admin.common.inactive') ?></span>
                        <?php endif; ?>
                        <?php if ($tenant['allows_subtenants']): ?>
                            <span class="super-badge super-badge-purple"><?= __('super_admin.common.hub') ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($canManage): ?>
                    <!-- Move User to Different Tenant -->
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                        <h4 style="font-size: 0.875rem; margin-bottom: 0.75rem;"><?= __('super_admin.users.show.move_tenant_heading') ?></h4>
                        <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/move-tenant" style="display: flex; gap: 0.5rem;">
                            <?= Csrf::field() ?>
                            <select name="new_tenant_id" class="super-form-select" style="flex: 1;" required>
                                <option value=""><?= __('super_admin.users.show.move_tenant_select_placeholder') ?></option>
                                <?php
                                $tenants = \App\Services\TenantVisibilityService::getTenantList();
                                foreach ($tenants as $t):
                                    if ($t['id'] != $tenant['id']):
                                ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['indented_name']) ?></option>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </select>
                            <button type="submit" class="super-btn super-btn-warning"
                                    onclick="return confirm('<?= __('super_admin.users.show.move_tenant_confirm') ?>');">
                                <i class="fa-solid fa-exchange-alt"></i>
                                <?= __('super_admin.common.move_btn') ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Quick Actions -->
        <div class="super-card" style="margin-bottom: 1rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-bolt"></i>
                    <?= __('super_admin.users.show.actions_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-btn super-btn-secondary" style="justify-content: center;">
                        <i class="fa-solid fa-building"></i>
                        <?= __('super_admin.users.show.view_tenant_btn') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- GOD ONLY: Global Super Admin (is_super_admin) -->
        <?php if (!empty($_SESSION['is_god'])): ?>
            <div class="super-card" style="margin-bottom: 1rem; border: 2px solid #fbbf24;">
                <div class="super-card-header" style="background: linear-gradient(135deg, rgba(147, 51, 234, 0.2), rgba(236, 72, 153, 0.1));">
                    <h3 class="super-card-title" style="color: #fbbf24;">
                        <i class="fa-solid fa-bolt"></i>
                        <?= __('super_admin.users.show.god_card_title') ?>
                    </h3>
                </div>
                <div class="super-card-body">
                    <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                        <strong style="color: #fbbf24;"><?= __('super_admin.users.show.god_warning') ?></strong> <?= __('super_admin.users.show.god_warning_text') ?>
                    </p>

                    <?php if ($user['is_super_admin'] ?? false): ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; background: rgba(34, 197, 94, 0.1); border-radius: 8px; margin-bottom: 1rem;">
                            <i class="fa-solid fa-check-circle" style="color: #22c55e;"></i>
                            <span style="color: #22c55e; font-weight: 600;"><?= __('super_admin.users.show.god_has_access') ?></span>
                        </div>
                        <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/revoke-global-super-admin">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn super-btn-danger" style="width: 100%; justify-content: center;"
                                    onclick="return confirm('<?= __('super_admin.users.show.god_revoke_confirm') ?>');">
                                <i class="fa-solid fa-bolt-slash"></i>
                                <?= __('super_admin.users.show.god_revoke_btn') ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/grant-global-super-admin">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn" style="width: 100%; justify-content: center; background: linear-gradient(135deg, #a855f7, #ec4899); border: none;"
                                    onclick="return confirm('<?= __('super_admin.users.show.god_grant_confirm') ?>');">
                                <i class="fa-solid fa-bolt"></i>
                                <?= __('super_admin.users.show.god_grant_btn') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tenant Super Admin Toggle (is_tenant_super_admin) -->
        <?php if ($canManage && ($tenant['allows_subtenants'] ?? false)): ?>
            <div class="super-card" style="margin-bottom: 1rem;">
                <div class="super-card-header">
                    <h3 class="super-card-title">
                        <i class="fa-solid fa-crown"></i>
                        <?= __('super_admin.users.show.tenant_super_admin_card_title') ?>
                    </h3>
                </div>
                <div class="super-card-body">
                    <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                        <?= __('super_admin.users.show.tenant_super_admin_desc') ?>
                    </p>

                    <?php if ($user['is_tenant_super_admin'] ?? false): ?>
                        <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/revoke-super-admin">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn super-btn-danger" style="width: 100%; justify-content: center;"
                                    onclick="return confirm('<?= __('super_admin.users.show.revoke_super_admin_confirm') ?>');">
                                <i class="fa-solid fa-user-minus"></i>
                                <?= __('super_admin.users.show.revoke_super_admin_btn') ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="/super-admin/users/<?= $user['id'] ?>/grant-super-admin">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn super-btn-success" style="width: 100%; justify-content: center;"
                                    onclick="return confirm('<?= __('super_admin.users.show.grant_super_admin_confirm') ?>');">
                                <i class="fa-solid fa-user-plus"></i>
                                <?= __('super_admin.users.show.grant_super_admin_btn') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($canManage && !($tenant['allows_subtenants'] ?? false)): ?>
            <div class="super-card" style="margin-bottom: 1rem;">
                <div class="super-card-header">
                    <h3 class="super-card-title">
                        <i class="fa-solid fa-crown"></i>
                        <?= __('super_admin.users.show.tenant_super_admin_card_title') ?>
                    </h3>
                </div>
                <div class="super-card-body">
                    <p style="color: var(--super-text-muted); font-size: 0.875rem;">
                        <i class="fa-solid fa-info-circle"></i>
                        <?= __('super_admin.users.show.no_hub_capability') ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- User Stats -->
        <div class="super-card">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-chart-bar"></i>
                    <?= __('super_admin.users.show.stats_card_title') ?>
                </h3>
            </div>
            <div class="super-card-body">
                <div style="display: grid; gap: 0.75rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.users.show.stat_user_id') ?></span>
                        <strong><?= $user['id'] ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.users.show.stat_tenant_id') ?></span>
                        <strong><?= $user['tenant_id'] ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.users.show.stat_status') ?></span>
                        <strong><?= $user['status'] ?? 'active' ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--super-text-muted);"><?= __('super_admin.users.show.stat_approved') ?></span>
                        <strong><?= $user['is_approved'] ? __('super_admin.common.yes') : __('super_admin.common.no') ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
