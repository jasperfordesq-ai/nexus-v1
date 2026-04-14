<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - Audit Log
 *
 * Track all hierarchy changes made through the Super Admin Panel.
 */

use App\Services\SuperAdminAuditService;

$pageTitle = $pageTitle ?? 'Audit Log';
require __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <span><?= __('super_admin.audit.breadcrumb') ?></span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-history"></i>
            <?= __('super_admin.audit.title') ?>
        </h1>
        <p class="super-page-subtitle">
            <?= $access['scope'] === 'global' ? __('super_admin.audit.subtitle_global') : __('super_admin.audit.subtitle_scoped') ?>
        </p>
    </div>
</div>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="super-stat-card">
        <div class="super-stat-icon" style="background: var(--super-primary);">
            <i class="fa-solid fa-list"></i>
        </div>
        <div class="super-stat-content">
            <div class="super-stat-value"><?= number_format($stats['total_actions'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.audit.stat_actions_30d') ?></div>
        </div>
    </div>

    <?php
    $tenantActions = 0;
    $userActions = 0;
    foreach (($stats['by_type'] ?? []) as $type) {
        if (str_starts_with($type['action_type'], 'tenant_')) {
            $tenantActions += $type['count'];
        } elseif (str_starts_with($type['action_type'], 'user_')) {
            $userActions += $type['count'];
        }
    }
    ?>

    <div class="super-stat-card">
        <div class="super-stat-icon" style="background: var(--super-success);">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="super-stat-content">
            <div class="super-stat-value"><?= number_format($tenantActions) ?></div>
            <div class="super-stat-label"><?= __('super_admin.audit.stat_tenant_changes') ?></div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon" style="background: var(--super-info);">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="super-stat-content">
            <div class="super-stat-value"><?= number_format($userActions) ?></div>
            <div class="super-stat-label"><?= __('super_admin.audit.stat_user_changes') ?></div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon" style="background: var(--super-purple);">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="super-stat-content">
            <div class="super-stat-value"><?= count($stats['top_actors'] ?? []) ?></div>
            <div class="super-stat-label"><?= __('super_admin.audit.stat_active_admins') ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="super-card" style="margin-bottom: 1.5rem;">
    <div class="super-card-body">
        <form method="GET" action="/super-admin/audit" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div class="super-form-group" style="min-width: 150px; margin-bottom: 0;">
                <label class="super-form-label"><?= __('super_admin.audit.filter_action_type') ?></label>
                <select name="action_type" class="super-form-select">
                    <option value=""><?= __('super_admin.audit.filter_all_actions') ?></option>
                    <optgroup label="<?= __('super_admin.audit.filter_group_tenant') ?>">
                        <option value="tenant_created" <?= ($filters['action_type'] ?? '') === 'tenant_created' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_tenant_created') ?></option>
                        <option value="tenant_updated" <?= ($filters['action_type'] ?? '') === 'tenant_updated' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_tenant_updated') ?></option>
                        <option value="tenant_moved" <?= ($filters['action_type'] ?? '') === 'tenant_moved' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_tenant_moved') ?></option>
                        <option value="tenant_hub_enabled" <?= ($filters['action_type'] ?? '') === 'tenant_hub_enabled' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_tenant_hub_enabled') ?></option>
                        <option value="tenant_hub_disabled" <?= ($filters['action_type'] ?? '') === 'tenant_hub_disabled' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_tenant_hub_disabled') ?></option>
                    </optgroup>
                    <optgroup label="<?= __('super_admin.audit.filter_group_user') ?>">
                        <option value="user_created" <?= ($filters['action_type'] ?? '') === 'user_created' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_user_created') ?></option>
                        <option value="user_moved" <?= ($filters['action_type'] ?? '') === 'user_moved' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_user_moved') ?></option>
                        <option value="user_super_admin_granted" <?= ($filters['action_type'] ?? '') === 'user_super_admin_granted' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_super_admin_granted') ?></option>
                        <option value="user_super_admin_revoked" <?= ($filters['action_type'] ?? '') === 'user_super_admin_revoked' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_super_admin_revoked') ?></option>
                    </optgroup>
                    <optgroup label="<?= __('super_admin.audit.filter_group_bulk') ?>">
                        <option value="bulk_users_moved" <?= ($filters['action_type'] ?? '') === 'bulk_users_moved' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_bulk_users_moved') ?></option>
                        <option value="bulk_tenants_updated" <?= ($filters['action_type'] ?? '') === 'bulk_tenants_updated' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_bulk_tenants_updated') ?></option>
                    </optgroup>
                </select>
            </div>

            <div class="super-form-group" style="min-width: 120px; margin-bottom: 0;">
                <label class="super-form-label"><?= __('super_admin.audit.filter_target_type') ?></label>
                <select name="target_type" class="super-form-select">
                    <option value=""><?= __('super_admin.audit.filter_all_types') ?></option>
                    <option value="tenant" <?= ($filters['target_type'] ?? '') === 'tenant' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_type_tenant') ?></option>
                    <option value="user" <?= ($filters['target_type'] ?? '') === 'user' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_type_user') ?></option>
                    <option value="bulk" <?= ($filters['target_type'] ?? '') === 'bulk' ? 'selected' : '' ?>><?= __('super_admin.audit.filter_type_bulk') ?></option>
                </select>
            </div>

            <div class="super-form-group" style="min-width: 140px; margin-bottom: 0;">
                <label class="super-form-label"><?= __('super_admin.audit.filter_date_from') ?></label>
                <input type="date" name="date_from" class="super-form-input"
                       value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
            </div>

            <div class="super-form-group" style="min-width: 140px; margin-bottom: 0;">
                <label class="super-form-label"><?= __('super_admin.audit.filter_date_to') ?></label>
                <input type="date" name="date_to" class="super-form-input"
                       value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
            </div>

            <div class="super-form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                <label class="super-form-label"><?= __('super_admin.audit.filter_search_label') ?></label>
                <input type="text" name="search" class="super-form-input"
                       placeholder="<?= __('super_admin.audit.filter_search_placeholder') ?>"
                       value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="super-btn super-btn-primary">
                    <i class="fa-solid fa-search"></i>
                    <?= __('super_admin.common.filter_btn') ?>
                </button>
                <a href="/super-admin/audit" class="super-btn super-btn-secondary">
                    <i class="fa-solid fa-times"></i>
                    <?= __('super_admin.common.clear_btn') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Audit Log Table -->
<div class="super-card">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-list-alt"></i>
            <?= __('super_admin.audit.table_title') ?>
        </h3>
    </div>
    <table class="super-table">
        <thead>
            <tr>
                <th style="width: 160px;"><?= __('super_admin.audit.col_time') ?></th>
                <th style="width: 180px;"><?= __('super_admin.audit.col_actor') ?></th>
                <th style="width: 160px;"><?= __('super_admin.audit.col_action') ?></th>
                <th><?= __('super_admin.audit.col_target') ?></th>
                <th><?= __('super_admin.audit.col_description') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 3rem; color: var(--super-text-muted);">
                        <i class="fa-solid fa-history" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                        <?= __('super_admin.audit.no_entries') ?>
                        <br><small><?= __('super_admin.audit.no_entries_hint') ?></small>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="color: var(--super-text-muted); font-size: 0.875rem;">
                            <?= date('M j, g:ia', strtotime($log['created_at'])) ?>
                            <div style="font-size: 0.75rem; opacity: 0.7;">
                                <?= date('Y', strtotime($log['created_at'])) ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 500;"><?= htmlspecialchars($log['actor_name']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--super-text-muted);">
                                <?= htmlspecialchars($log['actor_email']) ?>
                            </div>
                        </td>
                        <td>
                            <span class="super-badge" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                <i class="fa-solid <?= SuperAdminAuditService::getActionIcon($log['action_type']) ?>"></i>
                                <?= SuperAdminAuditService::getActionLabel($log['action_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['target_name']): ?>
                                <strong><?= htmlspecialchars($log['target_name']) ?></strong>
                                <?php if ($log['target_id']): ?>
                                    <span style="color: var(--super-text-muted); font-size: 0.75rem;">
                                        (ID: <?= $log['target_id'] ?>)
                                    </span>
                                <?php endif; ?>
                            <?php elseif ($log['target_id']): ?>
                                <?= ucfirst($log['target_type']) ?> #<?= $log['target_id'] ?>
                            <?php else: ?>
                                <span style="color: var(--super-text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--super-text-muted); font-size: 0.875rem;">
                            <?= htmlspecialchars($log['description'] ?? '—') ?>
                            <?php if ($log['old_values'] || $log['new_values']): ?>
                                <button type="button" class="super-btn super-btn-sm super-btn-secondary"
                                        onclick="toggleDetails(this)"
                                        style="margin-left: 0.5rem; padding: 0.125rem 0.375rem;">
                                    <i class="fa-solid fa-code"></i>
                                </button>
                                <div class="audit-details" style="display: none; margin-top: 0.5rem; background: var(--super-bg); padding: 0.5rem; border-radius: 4px; font-size: 0.75rem; font-family: monospace;">
                                    <?php if ($log['old_values']): ?>
                                        <div><strong>Before:</strong> <?= htmlspecialchars(json_encode($log['old_values'], JSON_PRETTY_PRINT)) ?></div>
                                    <?php endif; ?>
                                    <?php if ($log['new_values']): ?>
                                        <div><strong>After:</strong> <?= htmlspecialchars(json_encode($log['new_values'], JSON_PRETTY_PRINT)) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function toggleDetails(btn) {
    const details = btn.nextElementSibling;
    details.style.display = details.style.display === 'none' ? 'block' : 'none';
}
</script>

<style>
.text-success { color: var(--super-success); }
.text-info { color: var(--super-info); }
.text-warning { color: var(--super-warning); }
.text-danger { color: var(--super-danger); }
.text-purple { color: var(--super-purple); }
.text-secondary { color: var(--super-text-muted); }
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>
