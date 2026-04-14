<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - Tenant Federation Features
 * View and manage federation features for a specific tenant
 */

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Tenant Federation Settings';
require __DIR__ . '/../partials/header.php';
?>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-building"></i>
            <?= htmlspecialchars($tenant['name'] ?? 'Unknown Tenant') ?>
        </h1>
        <p class="super-page-subtitle">
            <?= __('super_admin.federation.tenant_features.subtitle') ?>
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-building"></i>
            <?= __('super_admin.federation.tenant_features.view_tenant_btn') ?>
        </a>
        <a href="/super-admin/federation/whitelist" class="super-btn super-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            <?= __('super_admin.federation.tenant_features.back_btn') ?>
        </a>
    </div>
</div>

<!-- Tenant Info Card -->
<div class="super-card" style="margin-bottom: 1.5rem;">
    <div class="super-card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
            <div>
                <div style="color: var(--super-text-muted); font-size: 0.85rem;"><?= __('super_admin.federation.tenant_features.field_domain') ?></div>
                <div style="font-weight: 500;"><?= htmlspecialchars($tenant['domain'] ?? '-') ?></div>
            </div>
            <div>
                <div style="color: var(--super-text-muted); font-size: 0.85rem;"><?= __('super_admin.federation.tenant_features.field_whitelist_status') ?></div>
                <div>
                    <?php if ($isWhitelisted): ?>
                    <span class="super-badge super-badge-success"><?= __('super_admin.federation.tenant_features.whitelisted_badge') ?></span>
                    <?php else: ?>
                    <span class="super-badge super-badge-danger"><?= __('super_admin.federation.tenant_features.not_whitelisted_badge') ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div style="color: var(--super-text-muted); font-size: 0.85rem;"><?= __('super_admin.federation.tenant_features.field_active_partnerships') ?></div>
                <div style="font-weight: 500;"><?= count(array_filter($partnerships ?? [], fn($p) => $p['status'] === 'active')) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (!$isWhitelisted): ?>
<div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem; color: #92400e;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span><?= __('super_admin.federation.tenant_features.not_whitelisted_warning') ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Feature Toggles -->
<div class="super-card" style="margin-bottom: 1.5rem;">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-toggle-on"></i>
            <?= __('super_admin.federation.tenant_features.features_card_title') ?>
        </h3>
    </div>
    <div class="super-card-body">
        <p style="color: var(--super-text-muted); margin-bottom: 1.5rem;">
            <?= __('super_admin.federation.tenant_features.features_card_desc') ?>
        </p>

        <div style="display: grid; gap: 1rem;">
            <?php
            $featureList = [
                'tenant_federation_enabled' => [__('super_admin.federation.tenant_features.feat_federation_enabled_title'), __('super_admin.federation.tenant_features.feat_federation_enabled_desc'), 'fa-power-off'],
                'tenant_appear_in_directory' => [__('super_admin.federation.tenant_features.feat_appear_in_directory_title'), __('super_admin.federation.tenant_features.feat_appear_in_directory_desc'), 'fa-eye'],
                'tenant_profiles_enabled' => [__('super_admin.federation.tenant_features.feat_profiles_title'), __('super_admin.federation.tenant_features.feat_profiles_desc'), 'fa-user'],
                'tenant_messaging_enabled' => [__('super_admin.federation.tenant_features.feat_messaging_title'), __('super_admin.federation.tenant_features.feat_messaging_desc'), 'fa-envelope'],
                'tenant_transactions_enabled' => [__('super_admin.federation.tenant_features.feat_transactions_title'), __('super_admin.federation.tenant_features.feat_transactions_desc'), 'fa-exchange-alt'],
                'tenant_listings_enabled' => [__('super_admin.federation.tenant_features.feat_listings_title'), __('super_admin.federation.tenant_features.feat_listings_desc'), 'fa-list'],
                'tenant_events_enabled' => [__('super_admin.federation.tenant_features.feat_events_title'), __('super_admin.federation.tenant_features.feat_events_desc'), 'fa-calendar'],
                'tenant_groups_enabled' => [__('super_admin.federation.tenant_features.feat_groups_title'), __('super_admin.federation.tenant_features.feat_groups_desc'), 'fa-users'],
            ];
            foreach ($featureList as $key => $info):
                $enabled = !empty($features[$key]);
            ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 40px; height: 40px; background: var(--super-card-bg); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid <?= $info[2] ?>" style="color: var(--super-primary);"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?= $info[0] ?></div>
                        <p style="margin: 0.25rem 0 0 0; color: var(--super-text-muted); font-size: 0.9rem;">
                            <?= $info[1] ?>
                        </p>
                    </div>
                </div>
                <label class="super-toggle">
                    <input type="checkbox" id="<?= $key ?>" <?= $enabled ? 'checked' : '' ?> <?= !$isWhitelisted ? 'disabled' : '' ?>
                        onchange="updateFeature('<?= $key ?>', this.checked)">
                    <span class="super-toggle-slider"></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Partnerships -->
<div class="super-card">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-handshake"></i>
            <?= __('super_admin.federation.tenant_features.partnerships_card_title', ['count' => count($partnerships ?? [])]) ?>
        </h3>
    </div>
    <table class="super-table">
        <thead>
            <tr>
                <th><?= __('super_admin.federation.tenant_features.col_partner_tenant') ?></th>
                <th><?= __('super_admin.federation.tenant_features.col_level') ?></th>
                <th><?= __('super_admin.federation.tenant_features.col_status') ?></th>
                <th><?= __('super_admin.federation.tenant_features.col_created') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($partnerships)): ?>
            <tr>
                <td colspan="4" style="text-align: center; padding: 2rem; color: var(--super-text-muted);">
                    <?= __('super_admin.federation.tenant_features.no_partnerships') ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($partnerships as $p): ?>
            <tr>
                <td>
                    <?php
                    $partnerName = $p['tenant_id'] == $tenant['id']
                        ? ($p['partner_name'] ?? 'Unknown')
                        : ($p['tenant_name'] ?? 'Unknown');
                    $partnerId = $p['tenant_id'] == $tenant['id']
                        ? $p['partner_tenant_id']
                        : $p['tenant_id'];
                    ?>
                    <a href="/super-admin/federation/tenant/<?= $partnerId ?>" class="super-table-link">
                        <?= htmlspecialchars($partnerName) ?>
                    </a>
                </td>
                <td>
                    <span class="super-badge super-badge-info">L<?= $p['federation_level'] ?></span>
                </td>
                <td>
                    <?php
                    $statusColors = [
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        'terminated' => 'secondary'
                    ];
                    ?>
                    <span class="super-badge super-badge-<?= $statusColors[$p['status']] ?? 'secondary' ?>">
                        <?= ucfirst($p['status']) ?>
                    </span>
                </td>
                <td style="color: var(--super-text-muted); font-size: 0.85rem;">
                    <?= date('M j, Y', strtotime($p['created_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Status Message -->
<div id="statusMessage" style="display: none; position: fixed; bottom: 2rem; right: 2rem; padding: 1rem 1.5rem; border-radius: 8px; color: white; z-index: 1000;"></div>

<style>
.super-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}
.super-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}
.super-toggle input:disabled + .super-toggle-slider {
    opacity: 0.5;
    cursor: not-allowed;
}
.super-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.3s;
    border-radius: 26px;
}
.super-toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}
.super-toggle input:checked + .super-toggle-slider {
    background-color: #16a34a;
}
.super-toggle input:checked + .super-toggle-slider:before {
    transform: translateX(24px);
}
</style>

<script>
const csrfToken = '<?= Csrf::token() ?>';
const tenantId = <?= $tenant['id'] ?>;

function updateFeature(feature, enabled) {
    fetch('/super-admin/federation/update-tenant-feature', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            tenant_id: tenantId,
            feature: feature,
            enabled: enabled
        })
    })
    .then(r => r.json())
    .then(data => {
        showStatus(data.success ? 'Feature updated' : (data.error || 'Update failed'), data.success);
    })
    .catch(err => {
        showStatus('Network error', false);
    });
}

function showStatus(message, success) {
    const el = document.getElementById('statusMessage');
    el.textContent = message;
    el.style.background = success ? '#16a34a' : '#dc2626';
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 3000);
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
