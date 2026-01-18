<?php
/**
 * Super Admin Dashboard
 * Infrastructure Overview - Tenant Hierarchy Management
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Dashboard';
require __DIR__ . '/partials/header.php';
?>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-gauge-high"></i>
            Infrastructure Dashboard
        </h1>
        <p class="super-page-subtitle">
            <?php if ($access['level'] === 'master'): ?>
                Global overview of all tenants and infrastructure
            <?php else: ?>
                Overview of <?= htmlspecialchars($access['tenant_name']) ?> and sub-tenants
            <?php endif; ?>
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/tenants/create" class="super-btn super-btn-primary">
            <i class="fa-solid fa-plus"></i>
            Create Tenant
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
            <div class="super-stat-label">Total Tenants</div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon green">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['active_tenants'] ?? 0) ?></div>
            <div class="super-stat-label">Active Tenants</div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon blue">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['total_users'] ?? 0) ?></div>
            <div class="super-stat-label">Total Users</div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon amber">
            <i class="fa-solid fa-crown"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['super_admins'] ?? 0) ?></div>
            <div class="super-stat-label">Super Admins</div>
        </div>
    </div>

    <div class="super-stat-card">
        <div class="super-stat-icon purple">
            <i class="fa-solid fa-sitemap"></i>
        </div>
        <div>
            <div class="super-stat-value"><?= number_format($stats['hub_tenants'] ?? 0) ?></div>
            <div class="super-stat-label">Hub Tenants</div>
        </div>
    </div>
</div>

<!-- Tenant Hierarchy Table -->
<div class="super-card">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-sitemap"></i>
            Tenant Hierarchy
        </h3>
        <a href="/super-admin/tenants" class="super-btn super-btn-sm super-btn-secondary">
            View All <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <table class="super-table">
        <thead>
            <tr>
                <th>Tenant</th>
                <th>Domain</th>
                <th>Users</th>
                <th>Children</th>
                <th>Hub</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tenants)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: var(--super-text-muted);">
                        No tenants found in your scope
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
                                <span class="super-badge super-badge-purple" style="margin-left: 0.5rem;">You</span>
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
                                    <i class="fa-solid fa-check"></i> Hub
                                </span>
                            <?php else: ?>
                                <span style="color: var(--super-border);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tenant['is_active']): ?>
                                <span class="super-badge super-badge-success">Active</span>
                            <?php else: ?>
                                <span class="super-badge super-badge-danger">Inactive</span>
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
                Your Access Scope
            </h3>
        </div>
        <div class="super-card-body">
            <div style="margin-bottom: 1rem;">
                <strong>Level:</strong>
                <span class="super-badge <?= $access['level'] === 'master' ? 'super-badge-danger' : 'super-badge-info' ?>" style="margin-left: 0.5rem;">
                    <?= strtoupper($access['level']) ?>
                </span>
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Home Tenant:</strong>
                <span style="color: var(--super-text-muted); margin-left: 0.5rem;">
                    <?= htmlspecialchars($access['tenant_name'] ?? 'Unknown') ?>
                </span>
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>Path:</strong>
                <code style="background: var(--super-bg); padding: 0.25rem 0.5rem; border-radius: 4px; margin-left: 0.5rem;">
                    <?= htmlspecialchars($access['tenant_path'] ?? '/') ?>
                </code>
            </div>
            <div>
                <strong>Scope:</strong>
                <span style="color: var(--super-text-muted); margin-left: 0.5rem;">
                    <?php if ($access['scope'] === 'global'): ?>
                        All tenants globally
                    <?php else: ?>
                        Your tenant + all descendants
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
                Quick Actions
            </h3>
        </div>
        <div class="super-card-body">
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <a href="/super-admin/tenants/create" class="super-btn super-btn-primary" style="justify-content: center;">
                    <i class="fa-solid fa-plus"></i>
                    Create New Tenant
                </a>
                <a href="/super-admin/users?super_admins=1" class="super-btn super-btn-secondary" style="justify-content: center;">
                    <i class="fa-solid fa-crown"></i>
                    View Super Admins
                </a>
                <a href="/admin" class="super-btn super-btn-secondary" style="justify-content: center;">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Platform Admin
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
