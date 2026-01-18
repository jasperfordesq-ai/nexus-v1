<?php
/**
 * Super Admin Federation Control Center
 * Overview of all federation activity and controls
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Federation Control Center';
require __DIR__ . '/../partials/header.php';
?>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-network-wired"></i>
            Federation Control Center
        </h1>
        <p class="super-page-subtitle">
            Platform-wide federation management and oversight
        </p>
    </div>
    <?php if ($access['level'] === 'master'): ?>
    <div class="super-page-actions">
        <a href="/super-admin/federation/system-controls" class="super-btn super-btn-primary">
            <i class="fa-solid fa-sliders"></i>
            System Controls
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Emergency Alert Banner (if lockdown active) -->
<?php if (!empty($systemStatus['emergency_lockdown_active'])): ?>
<div style="background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem;"></i>
        <div>
            <strong style="font-size: 1.1rem;">EMERGENCY LOCKDOWN ACTIVE</strong>
            <p style="margin: 0.25rem 0 0 0; opacity: 0.9;">
                <?= htmlspecialchars($systemStatus['emergency_lockdown_reason'] ?? 'No reason provided') ?>
            </p>
        </div>
    </div>
    <?php if ($access['level'] === 'master'): ?>
    <button onclick="liftLockdown()" class="super-btn" style="background: white; color: #dc2626;">
        <i class="fa-solid fa-unlock"></i>
        Lift Lockdown
    </button>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- System Status Cards -->
<div class="super-stats-grid">
    <div class="super-stat-card">
        <div class="super-stat-icon <?= $systemStatus['federation_enabled'] ? 'green' : 'red' ?>">
            <i class="fa-solid fa-power-off"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= $systemStatus['federation_enabled'] ? 'ON' : 'OFF' ?></div>
            <div class="super-stat-label">Federation System</div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon purple">
            <i class="fa-solid fa-building-shield"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= count($whitelistedTenants ?? []) ?></div>
            <div class="super-stat-label">Whitelisted Tenants</div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon blue">
            <i class="fa-solid fa-handshake"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= $partnershipStats['active'] ?? 0 ?></div>
            <div class="super-stat-label">Active Partnerships</div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon amber">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= $partnershipStats['pending'] ?? 0 ?></div>
            <div class="super-stat-label">Pending Requests</div>
        </div>
    </div>
</div>

<!-- Feature Status Grid -->
<div class="super-card" style="margin-top: 1.5rem;">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-toggle-on"></i>
            Global Feature Status
        </h3>
        <?php if ($access['level'] === 'master'): ?>
        <a href="/super-admin/federation/system-controls" class="super-btn super-btn-sm super-btn-secondary">
            Configure <i class="fa-solid fa-arrow-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <div class="super-card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <?php
            $features = [
                'cross_tenant_profiles_enabled' => ['Profiles', 'fa-user'],
                'cross_tenant_messaging_enabled' => ['Messaging', 'fa-envelope'],
                'cross_tenant_transactions_enabled' => ['Transactions', 'fa-exchange-alt'],
                'cross_tenant_listings_enabled' => ['Listings', 'fa-list'],
                'cross_tenant_events_enabled' => ['Events', 'fa-calendar'],
                'cross_tenant_groups_enabled' => ['Groups', 'fa-users'],
            ];
            foreach ($features as $key => $info):
                $enabled = !empty($systemStatus[$key]);
            ?>
            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--super-bg); border-radius: 6px;">
                <i class="fa-solid <?= $info[1] ?>" style="color: var(--super-text-muted); width: 20px;"></i>
                <span style="flex: 1;"><?= $info[0] ?></span>
                <span class="super-badge <?= $enabled ? 'super-badge-success' : 'super-badge-secondary' ?>">
                    <?= $enabled ? 'ON' : 'OFF' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
    <!-- Whitelisted Tenants -->
    <div class="super-card">
        <div class="super-card-header">
            <h3 class="super-card-title">
                <i class="fa-solid fa-building-shield"></i>
                Whitelisted Tenants
            </h3>
            <a href="/super-admin/federation/whitelist" class="super-btn super-btn-sm super-btn-secondary">
                Manage <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <table class="super-table">
            <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Approved</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($whitelistedTenants)): ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 2rem; color: var(--super-text-muted);">
                        No tenants whitelisted yet
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach (array_slice($whitelistedTenants, 0, 5) as $tenant): ?>
                <tr>
                    <td>
                        <a href="/super-admin/federation/tenant/<?= $tenant['tenant_id'] ?>" class="super-table-link">
                            <?= htmlspecialchars($tenant['tenant_name'] ?? 'Unknown') ?>
                        </a>
                    </td>
                    <td style="color: var(--super-text-muted); font-size: 0.85rem;">
                        <?= date('M j, Y', strtotime($tenant['approved_at'])) ?>
                    </td>
                    <td>
                        <span class="super-badge super-badge-success">Active</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Partnerships -->
    <div class="super-card">
        <div class="super-card-header">
            <h3 class="super-card-title">
                <i class="fa-solid fa-handshake"></i>
                Recent Partnerships
            </h3>
            <a href="/super-admin/federation/partnerships" class="super-btn super-btn-sm super-btn-secondary">
                View All <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <table class="super-table">
            <thead>
                <tr>
                    <th>Partnership</th>
                    <th>Level</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($partnershipStats['recent'])): ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 2rem; color: var(--super-text-muted);">
                        No partnerships yet
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach (array_slice($partnershipStats['recent'] ?? [], 0, 5) as $partnership): ?>
                <tr>
                    <td style="font-size: 0.9rem;">
                        <?= htmlspecialchars($partnership['tenant_name'] ?? 'Unknown') ?>
                        <i class="fa-solid fa-arrows-left-right" style="color: var(--super-text-muted); margin: 0 0.5rem;"></i>
                        <?= htmlspecialchars($partnership['partner_name'] ?? 'Unknown') ?>
                    </td>
                    <td>
                        <span class="super-badge super-badge-info">L<?= $partnership['federation_level'] ?></span>
                    </td>
                    <td>
                        <?php
                        $statusColors = [
                            'active' => 'success',
                            'pending' => 'warning',
                            'suspended' => 'danger',
                            'terminated' => 'secondary'
                        ];
                        $color = $statusColors[$partnership['status']] ?? 'secondary';
                        ?>
                        <span class="super-badge super-badge-<?= $color ?>">
                            <?= ucfirst($partnership['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Critical Events & Audit Log -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
    <!-- Critical Events -->
    <?php if (!empty($criticalEvents)): ?>
    <div class="super-card">
        <div class="super-card-header">
            <h3 class="super-card-title" style="color: #dc2626;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Critical Events
            </h3>
        </div>
        <div class="super-card-body">
            <?php foreach ($criticalEvents as $event): ?>
            <div style="padding: 0.75rem; background: #fef2f2; border-left: 3px solid #dc2626; border-radius: 4px; margin-bottom: 0.5rem;">
                <div style="font-weight: 500; color: #991b1b;">
                    <?= htmlspecialchars($event['action_type']) ?>
                </div>
                <div style="font-size: 0.85rem; color: #7f1d1d; margin-top: 0.25rem;">
                    <?= date('M j, Y g:i A', strtotime($event['created_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Audit Log -->
    <div class="super-card">
        <div class="super-card-header">
            <h3 class="super-card-title">
                <i class="fa-solid fa-clipboard-list"></i>
                Recent Activity
            </h3>
            <a href="/super-admin/federation/audit" class="super-btn super-btn-sm super-btn-secondary">
                Full Log <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <div class="super-card-body" style="max-height: 300px; overflow-y: auto;">
            <?php if (empty($recentAudit)): ?>
            <p style="text-align: center; color: var(--super-text-muted); padding: 2rem;">
                No federation activity yet
            </p>
            <?php else: ?>
            <?php foreach (array_slice($recentAudit, 0, 10) as $log): ?>
            <div style="padding: 0.5rem 0; border-bottom: 1px solid var(--super-border); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 0.9rem;"><?= htmlspecialchars($log['action_type']) ?></span>
                    <?php if (!empty($log['actor_name'])): ?>
                    <span style="color: var(--super-text-muted); font-size: 0.85rem;">
                        by <?= htmlspecialchars($log['actor_name']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <span style="color: var(--super-text-muted); font-size: 0.8rem;">
                    <?= date('M j, g:i A', strtotime($log['created_at'])) ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Federation Activity Analytics -->
<?php
$auditStats = $auditStats ?? \Nexus\Services\FederationAuditService::getStats(30);
?>
<div class="super-card" style="margin-top: 1.5rem;">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-chart-line"></i>
            Federation Activity (30 days)
        </h3>
    </div>
    <div class="super-card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="text-align: center; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
                <div style="font-size: 1.75rem; font-weight: 700; color: var(--super-primary);">
                    <?= number_format($auditStats['total_actions'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--super-text-muted);">Total Actions</div>
            </div>
            <div style="text-align: center; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
                <div style="font-size: 1.75rem; font-weight: 700; color: #22c55e;">
                    <?= number_format($auditStats['by_category']['messaging'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--super-text-muted);">Messages</div>
            </div>
            <div style="text-align: center; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
                <div style="font-size: 1.75rem; font-weight: 700; color: #f59e0b;">
                    <?= number_format($auditStats['by_category']['transaction'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--super-text-muted);">Transactions</div>
            </div>
            <div style="text-align: center; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
                <div style="font-size: 1.75rem; font-weight: 700; color: #a855f7;">
                    <?= number_format($auditStats['by_category']['profile'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--super-text-muted);">Profile Views</div>
            </div>
            <div style="text-align: center; padding: 1rem; background: var(--super-bg); border-radius: 8px;">
                <div style="font-size: 1.75rem; font-weight: 700; color: #dc2626;">
                    <?= number_format($auditStats['critical_count'] ?? 0) ?>
                </div>
                <div style="font-size: 0.85rem; color: var(--super-text-muted);">Critical Events</div>
            </div>
        </div>

        <?php if (!empty($auditStats['most_active_pairs'])): ?>
        <h4 style="margin: 0 0 0.75rem 0; font-size: 0.9rem; color: var(--super-text-muted);">Most Active Partnerships</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            <?php foreach (array_slice($auditStats['most_active_pairs'], 0, 5) as $pair): ?>
            <span style="padding: 0.5rem 0.75rem; background: var(--super-bg); border-radius: 6px; font-size: 0.85rem;">
                <?= htmlspecialchars($pair['pair_label'] ?? 'Unknown') ?>
                <strong style="color: var(--super-primary); margin-left: 0.25rem;"><?= $pair['count'] ?></strong>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Navigation -->
<div class="super-card" style="margin-top: 1.5rem;">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-compass"></i>
            Quick Navigation
        </h3>
    </div>
    <div class="super-card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <?php if ($access['level'] === 'master'): ?>
            <a href="/super-admin/federation/system-controls" class="super-btn super-btn-secondary" style="justify-content: center;">
                <i class="fa-solid fa-sliders"></i>
                System Controls
            </a>
            <?php endif; ?>
            <a href="/super-admin/federation/whitelist" class="super-btn super-btn-secondary" style="justify-content: center;">
                <i class="fa-solid fa-building-shield"></i>
                Tenant Whitelist
            </a>
            <a href="/super-admin/federation/partnerships" class="super-btn super-btn-secondary" style="justify-content: center;">
                <i class="fa-solid fa-handshake"></i>
                Partnerships
            </a>
            <a href="/super-admin/federation/audit" class="super-btn super-btn-secondary" style="justify-content: center;">
                <i class="fa-solid fa-clipboard-list"></i>
                Audit Log
            </a>
        </div>
    </div>
</div>

<?php if ($access['level'] === 'master'): ?>
<script>
function liftLockdown() {
    if (!confirm('Are you sure you want to lift the emergency lockdown?')) return;

    fetch('/super-admin/federation/lift-lockdown', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= Csrf::token() ?>'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to lift lockdown');
        }
    });
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
