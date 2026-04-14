<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin Federation Audit Log
 * Complete audit trail of all federation activity
 */

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Federation Audit Log';
require __DIR__ . '/../partials/header.php';
?>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-clipboard-list"></i>
            <?= __('super_admin.federation.audit_log.title') ?>
        </h1>
        <p class="super-page-subtitle">
            <?= __('super_admin.federation.audit_log.subtitle') ?>
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/federation" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            <?= __('super_admin.federation.audit_log.back_btn') ?>
        </a>
    </div>
</div>

<!-- Stats Summary -->
<?php if (!empty($stats)): ?>
<div class="super-stats-grid">
    <div class="super-stat-card">
        <div class="super-stat-icon blue">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.federation.audit_log.stat_total_30d') ?></div>
        </div>
    </div>
    <div class="super-stat-card">
        <div class="super-stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['critical'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.federation.audit_log.stat_critical') ?></div>
        </div>
    </div>
    <div class="super-stat-card">
        <div class="super-stat-icon amber">
            <i class="fa-solid fa-exclamation"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['warning'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.federation.audit_log.stat_warnings') ?></div>
        </div>
    </div>
    <div class="super-stat-card">
        <div class="super-stat-icon green">
            <i class="fa-solid fa-info-circle"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['info'] ?? 0) ?></div>
            <div class="super-stat-label"><?= __('super_admin.federation.audit_log.stat_info') ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="super-card" style="margin-top: 1.5rem;">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-filter"></i>
            <?= __('super_admin.federation.audit_log.filters_card_title') ?>
        </h3>
    </div>
    <div class="super-card-body">
        <form method="GET" action="/super-admin/federation/audit" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="min-width: 150px;">
                <label class="super-label"><?= __('super_admin.federation.audit_log.level_label') ?></label>
                <select name="level" class="super-input">
                    <option value=""><?= __('super_admin.federation.audit_log.level_all') ?></option>
                    <option value="critical" <?= ($filters['level'] ?? '') === 'critical' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.level_critical') ?></option>
                    <option value="warning" <?= ($filters['level'] ?? '') === 'warning' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.level_warning') ?></option>
                    <option value="info" <?= ($filters['level'] ?? '') === 'info' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.level_info') ?></option>
                    <option value="debug" <?= ($filters['level'] ?? '') === 'debug' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.level_debug') ?></option>
                </select>
            </div>
            <div style="min-width: 150px;">
                <label class="super-label"><?= __('super_admin.federation.audit_log.category_label') ?></label>
                <select name="category" class="super-input">
                    <option value=""><?= __('super_admin.federation.audit_log.category_all') ?></option>
                    <option value="system" <?= ($filters['category'] ?? '') === 'system' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.category_system') ?></option>
                    <option value="tenant" <?= ($filters['category'] ?? '') === 'tenant' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.category_tenant') ?></option>
                    <option value="partnership" <?= ($filters['category'] ?? '') === 'partnership' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.category_partnership') ?></option>
                    <option value="profile" <?= ($filters['category'] ?? '') === 'profile' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.category_profile') ?></option>
                    <option value="messaging" <?= ($filters['category'] ?? '') === 'messaging' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.category_messaging') ?></option>
                    <option value="transaction" <?= ($filters['category'] ?? '') === 'transaction' ? 'selected' : '' ?>><?= __('super_admin.federation.audit_log.category_transaction') ?></option>
                </select>
            </div>
            <div style="min-width: 150px;">
                <label class="super-label"><?= __('super_admin.federation.audit_log.date_from_label') ?></label>
                <input type="date" name="date_from" class="super-input" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
            </div>
            <div style="min-width: 150px;">
                <label class="super-label"><?= __('super_admin.federation.audit_log.date_to_label') ?></label>
                <input type="date" name="date_to" class="super-input" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label class="super-label"><?= __('super_admin.federation.audit_log.search_label') ?></label>
                <input type="text" name="search" class="super-input" placeholder="<?= __('super_admin.federation.audit_log.search_placeholder') ?>" value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            </div>
            <button type="submit" class="super-btn super-btn-primary">
                <i class="fa-solid fa-search"></i>
                <?= __('super_admin.common.filter_btn') ?>
            </button>
            <a href="/super-admin/federation/audit" class="super-btn super-btn-secondary">
                <i class="fa-solid fa-times"></i>
                <?= __('super_admin.common.clear_btn') ?>
            </a>
        </form>
    </div>
</div>

<!-- Audit Log Table -->
<div class="super-card" style="margin-top: 1.5rem;">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-list"></i>
            <?= __('super_admin.federation.audit_log.table_card_title', ['count' => count($logs)]) ?>
        </h3>
    </div>
    <table class="super-table">
        <thead>
            <tr>
                <th style="width: 50px;"><?= __('super_admin.federation.audit_log.col_level') ?></th>
                <th><?= __('super_admin.federation.audit_log.col_action') ?></th>
                <th><?= __('super_admin.federation.audit_log.col_category') ?></th>
                <th><?= __('super_admin.federation.audit_log.col_actor') ?></th>
                <th><?= __('super_admin.federation.audit_log.col_tenants') ?></th>
                <th><?= __('super_admin.federation.audit_log.col_ip') ?></th>
                <th><?= __('super_admin.federation.audit_log.col_time') ?></th>
                <th style="width: 50px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 3rem; color: var(--super-text-muted);">
                    <i class="fa-solid fa-clipboard-list" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    <?= __('super_admin.federation.audit_log.no_events') ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td>
                    <?php
                    $levelIcons = [
                        'critical' => ['fa-triangle-exclamation', '#dc2626'],
                        'warning' => ['fa-exclamation', '#f59e0b'],
                        'info' => ['fa-info-circle', '#3b82f6'],
                        'debug' => ['fa-bug', '#6b7280']
                    ];
                    $icon = $levelIcons[$log['level']] ?? ['fa-circle', '#6b7280'];
                    ?>
                    <i class="fa-solid <?= $icon[0] ?>" style="color: <?= $icon[1] ?>;" title="<?= ucfirst($log['level']) ?>"></i>
                </td>
                <td>
                    <span style="font-weight: 500;"><?= htmlspecialchars($log['action_type']) ?></span>
                </td>
                <td>
                    <span class="super-badge super-badge-secondary"><?= htmlspecialchars($log['category']) ?></span>
                </td>
                <td style="font-size: 0.9rem;">
                    <?php if (!empty($log['actor_name'])): ?>
                    <?= htmlspecialchars($log['actor_name']) ?>
                    <?php if (!empty($log['actor_email'])): ?>
                    <div style="color: var(--super-text-muted); font-size: 0.8rem;">
                        <?= htmlspecialchars($log['actor_email']) ?>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="color: var(--super-text-muted);"><?= __('super_admin.federation.audit_log.system_actor') ?></span>
                    <?php endif; ?>
                </td>
                <td style="font-size: 0.85rem;">
                    <?php if (!empty($log['source_tenant_id'])): ?>
                    <div><?= __('super_admin.federation.audit_log.from_label') ?> <?= $log['source_tenant_id'] ?></div>
                    <?php endif; ?>
                    <?php if (!empty($log['target_tenant_id'])): ?>
                    <div><?= __('super_admin.federation.audit_log.to_label') ?> <?= $log['target_tenant_id'] ?></div>
                    <?php endif; ?>
                    <?php if (empty($log['source_tenant_id']) && empty($log['target_tenant_id'])): ?>
                    <span style="color: var(--super-text-muted);">-</span>
                    <?php endif; ?>
                </td>
                <td style="color: var(--super-text-muted); font-size: 0.85rem;">
                    <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                </td>
                <td style="color: var(--super-text-muted); font-size: 0.85rem; white-space: nowrap;">
                    <?= date('M j, g:i A', strtotime($log['created_at'])) ?>
                </td>
                <td>
                    <?php if (!empty($log['data'])): ?>
                    <button onclick="showDetails(<?= htmlspecialchars(json_encode($log), ENT_QUOTES) ?>)" class="super-btn super-btn-sm super-btn-secondary" title="View Details">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Details Modal -->
<div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--super-card-bg); border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow: auto;">
        <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--super-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><?= __('super_admin.federation.audit_log.modal_title') ?></h3>
            <button onclick="closeModal()" class="super-btn super-btn-sm super-btn-secondary">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div id="detailsContent" style="padding: 1.5rem;">
        </div>
    </div>
</div>

<script>
function showDetails(log) {
    const content = document.getElementById('detailsContent');
    const labels = {
        action: '<?= __('super_admin.federation.audit_log.modal_action') ?>',
        category: '<?= __('super_admin.federation.audit_log.modal_category') ?>',
        level: '<?= __('super_admin.federation.audit_log.modal_level') ?>',
        time: '<?= __('super_admin.federation.audit_log.modal_time') ?>',
        actor: '<?= __('super_admin.federation.audit_log.modal_actor') ?>',
        ip: '<?= __('super_admin.federation.audit_log.modal_ip') ?>',
        userAgent: '<?= __('super_admin.federation.audit_log.modal_user_agent') ?>',
        data: '<?= __('super_admin.federation.audit_log.modal_data') ?>'
    };
    let html = `
        <div style="margin-bottom: 1rem;">
            <strong>${labels.action}</strong> ${escapeHtml(log.action_type)}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>${labels.category}</strong> ${escapeHtml(log.category)}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>${labels.level}</strong> ${escapeHtml(log.level)}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>${labels.time}</strong> ${new Date(log.created_at).toLocaleString()}
        </div>
    `;

    if (log.actor_name) {
        html += `<div style="margin-bottom: 1rem;"><strong>${labels.actor}</strong> ${escapeHtml(log.actor_name)} (${escapeHtml(log.actor_email || '')})</div>`;
    }

    if (log.ip_address) {
        html += `<div style="margin-bottom: 1rem;"><strong>${labels.ip}</strong> ${escapeHtml(log.ip_address)}</div>`;
    }

    if (log.user_agent) {
        html += `<div style="margin-bottom: 1rem;"><strong>${labels.userAgent}</strong> <span style="font-size: 0.85rem; word-break: break-all;">${escapeHtml(log.user_agent)}</span></div>`;
    }

    if (log.data) {
        let dataObj = log.data;
        if (typeof dataObj === 'string') {
            try { dataObj = JSON.parse(dataObj); } catch(e) {}
        }
        html += `
            <div style="margin-bottom: 0.5rem;"><strong>${labels.data}</strong></div>
            <pre style="background: var(--super-bg); padding: 1rem; border-radius: 6px; overflow: auto; font-size: 0.85rem;">${JSON.stringify(dataObj, null, 2)}</pre>
        `;
    }

    content.innerHTML = html;
    document.getElementById('detailsModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on background click
document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
