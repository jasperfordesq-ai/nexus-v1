<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin Dashboard
 * Infrastructure Overview - Tenant Hierarchy Management
 */

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Dashboard';
require __DIR__ . '/partials/header.php';
?>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-gauge-high"></i>
            <?= __('super_admin.dashboard.title') ?>
        </h1>
        <p class="super-page-subtitle">
            <?php if ($access['level'] === 'master'): ?>
                <?= __('super_admin.dashboard.subtitle_global') ?>
            <?php else: ?>
                <?= __('super_admin.dashboard.subtitle_scoped', ['tenant_name' => htmlspecialchars($access['tenant_name'])]) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/tenants/create" class="super-btn super-btn-primary">
            <i class="fa-solid fa-plus"></i>
            <?= __('super_admin.dashboard.create_tenant_btn') ?>
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="super-stats-grid">
    <div class="super-stat-card">
        <div class="super-stat-icon purple">
            <i class="fa-solid fa-building"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['total_tenants'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.dashboard.stat_total_tenants') ?></div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon green">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['active_tenants'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.dashboard.stat_active_tenants') ?></div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon blue">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['total_users'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.dashboard.stat_total_users') ?></div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon amber">
            <i class="fa-solid fa-crown"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['super_admins'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.dashboard.stat_super_admins') ?></div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon purple">
            <i class="fa-solid fa-sitemap"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['hub_tenants'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.dashboard.stat_hub_tenants') ?></div>
        </div>
    </div>
</div>

<!-- Tenant Hierarchy Table -->
<div class="super-card">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-sitemap"></i>
            <?= __('super_admin.dashboard.table_title') ?>
        </h3>
        <a href="/super-admin/tenants" class="super-btn super-btn-sm super-btn-secondary">
            <?= __('super_admin.dashboard.view_all') ?> <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <table class="super-table">
        <thead>
            <tr>
                <th><?= __('super_admin.dashboard.col_tenant') ?></th>
                <th><?= __('super_admin.dashboard.col_domain') ?></th>
                <th><?= __('super_admin.dashboard.col_users') ?></th>
                <th><?= __('super_admin.dashboard.col_children') ?></th>
                <th><?= __('super_admin.dashboard.col_hub') ?></th>
                <th><?= __('super_admin.dashboard.col_status') ?></th>
                <th><?= __('super_admin.dashboard.col_actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tenants)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: var(--super-text-muted);">
                        <?= __('super_admin.dashboard.no_tenants') ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td>
                            <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-table-link">
                                <?= htmlspecialchars($tenant['indented_name'] ?? $tenant['name']) ?>
                            </a>
                            <?php if ($tenant['relationship'] === 'self'): ?>
                                <span class="super-badge super-badge-purple" style="margin-left: 0.5rem;"><?= __('super_admin.dashboard.you_badge') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($tenant['domain'])): ?>
                                <span style="color: var(--super-text-muted); font-size: 0.85rem;">
                                    <?= htmlspecialchars($tenant['domain']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--super-border);">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($tenant['user_count'] ?? 0) ?></td>
                        <td><?= number_format($tenant['direct_children'] ?? 0) ?></td>
                        <td>
                            <?php if ($tenant['allows_subtenants']): ?>
                                <span class="super-badge super-badge-success">
                                    <i class="fa-solid fa-check"></i> <?= __('super_admin.common.hub') ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--super-border);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tenant['is_active']): ?>
                                <span class="super-badge super-badge-success"><?= __('super_admin.common.active') ?></span>
                            <?php else: ?>
                                <span class="super-badge super-badge-danger"><?= __('super_admin.common.inactive') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-btn super-btn-sm super-btn-secondary">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <?php if ($tenant['can_manage'] && $tenant['relationship'] !== 'self'): ?>
                                <a href="/super-admin/tenants/<?= $tenant['id'] ?>/edit" class="super-btn super-btn-sm super-btn-secondary">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Quick Access Panel -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-top: 1.5rem;">
    <!-- Scope Info -->
    <div class="super-card">
        <div class="super-card-header">
            <h3 class="super-card-title">
                <i class="fa-solid fa-eye"></i>
                <?= __('super_admin.dashboard.scope_title') ?>
            </h3>
        </div>
        <div class="super-card-body">
            <div style="margin-bottom: 1rem;">
                <strong><?= __('super_admin.dashboard.scope_level') ?></strong>
                <span class="super-badge <?= $access['level'] === 'master' ? 'super-badge-danger' : 'super-badge-info' ?>" style="margin-left: 0.5rem;">
                    <?= strtoupper($access['level']) ?>
                </span>
            </div>
            <div style="margin-bottom: 1rem;">
                <strong><?= __('super_admin.dashboard.scope_home_tenant') ?></strong>
                <span style="color: var(--super-text-muted); margin-left: 0.5rem;">
                    <?= htmlspecialchars($access['tenant_name'] ?? __('super_admin.common.unknown')) ?>
                </span>
            </div>
            <div style="margin-bottom: 1rem;">
                <strong><?= __('super_admin.dashboard.scope_path') ?></strong>
                <code style="background: var(--super-bg); padding: 0.25rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;">
                    <?= htmlspecialchars($access['tenant_path'] ?? '/') ?>
                </code>
            </div>
            <div>
                <strong><?= __('super_admin.dashboard.scope_scope') ?></strong>
                <span style="color: var(--super-text-muted); margin-left: 0.5rem;">
                    <?php if ($access['scope'] === 'global'): ?>
                        <?= __('super_admin.dashboard.scope_global') ?>
                    <?php else: ?>
                        <?= __('super_admin.dashboard.scope_hierarchy') ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="super-card">
        <div class="super-card-header">
            <h3 class="super-card-title">
                <i class="fa-solid fa-bolt"></i>
                <?= __('super_admin.dashboard.quick_actions_title') ?>
            </h3>
        </div>
        <div class="super-card-body">
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <a href="/super-admin/tenants/create" class="super-btn super-btn-primary" style="justify-content: center;">
                    <i class="fa-solid fa-plus"></i>
                    <?= __('super_admin.dashboard.create_new_tenant') ?>
                </a>
                <a href="/super-admin/users?super_admins=1" class="super-btn super-btn-secondary" style="justify-content: center;">
                    <i class="fa-solid fa-crown"></i>
                    <?= __('super_admin.dashboard.view_super_admins') ?>
                </a>
                <a href="/admin-legacy" class="super-btn super-btn-secondary" style="justify-content: center;">
                    <i class="fa-solid fa-arrow-left"></i>
                    <?= __('super_admin.dashboard.back_to_platform_admin') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
