<?php
/**
 * Super Admin - Tenant List
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Manage Tenants';
require __DIR__ . '/../partials/header.php';
?>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-building"></i>
            Manage Tenants
        </h1>
        <p class="super-page-subtitle">
            <?= $stats['total_tenants'] ?? 0 ?> tenant(s) in your scope
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/tenants/create" class="super-btn super-btn-primary">
            <i class="fa-solid fa-plus"></i>
            Create Tenant
        </a>
    </div>
</div>

<!-- Filters -->
<div class="super-card" style="margin-bottom: 1rem;">
    <div class="super-card-body" style="padding: 0.75rem 1rem;">
        <form method="GET" action="/super-admin/tenants" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" name="search" class="super-input" placeholder="Search tenants..."
                       value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            </div>
            <label class="super-checkbox-group">
                <input type="checkbox" name="hub" class="super-checkbox" <?= isset($filters['allows_subtenants']) ? 'checked' : '' ?>>
                <span>Hub tenants only</span>
            </label>
            <select name="is_active" class="super-select" style="width: auto;">
                <option value="">All Status</option>
                <option value="1" <?= ($filters['is_active'] ?? '') === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" class="super-btn super-btn-secondary">
                <i class="fa-solid fa-search"></i>
                Filter
            </button>
            <?php if (!empty($filters['search']) || isset($filters['allows_subtenants']) || isset($filters['is_active'])): ?>
                <a href="/super-admin/tenants" class="super-btn super-btn-secondary">
                    <i class="fa-solid fa-times"></i>
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Tenants Table -->
<div class="super-card">
    <table class="super-table">
        <thead>
            <tr>
                <th>Tenant</th>
                <th>Slug</th>
                <th>Domain</th>
                <th>Parent</th>
                <th>Users</th>
                <th>Hub</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tenants)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 3rem; color: var(--super-text-muted);">
                        <i class="fa-solid fa-building" style="font-size: 2rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                        No tenants found
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td>
                            <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-table-link">
                                <strong><?= htmlspecialchars($tenant['name']) ?></strong>
                            </a>
                            <?php if ((int)$tenant['depth'] > 0): ?>
                                <span style="color: var(--super-text-muted); font-size: 0.75rem; display: block;">
                                    Level <?= $tenant['depth'] ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($tenant['relationship'] === 'self'): ?>
                                <span class="super-badge super-badge-purple">You</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code style="font-size: 0.8rem; background: var(--super-bg); padding: 0.2rem 0.4rem; border-radius: 3px;">
                                <?= htmlspecialchars($tenant['slug'] ?? '-') ?>
                            </code>
                        </td>
                        <td style="color: var(--super-text-muted); font-size: 0.85rem;">
                            <?= htmlspecialchars($tenant['domain'] ?? '-') ?>
                        </td>
                        <td style="color: var(--super-text-muted); font-size: 0.85rem;">
                            <?= htmlspecialchars($tenant['parent_name'] ?? 'Root') ?>
                        </td>
                        <td><?= number_format($tenant['user_count'] ?? 0) ?></td>
                        <td>
                            <?php if ($tenant['allows_subtenants']): ?>
                                <span class="super-badge super-badge-success">
                                    <i class="fa-solid fa-check"></i> Hub
                                </span>
                            <?php else: ?>
                                <span class="super-badge super-badge-warning">Leaf</span>
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
                            <div style="display: flex; gap: 0.25rem;">
                                <a href="/super-admin/tenants/<?= $tenant['id'] ?>" class="super-btn super-btn-sm super-btn-secondary" title="View">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <?php if ($tenant['can_manage'] && $tenant['relationship'] !== 'self'): ?>
                                    <a href="/super-admin/tenants/<?= $tenant['id'] ?>/edit" class="super-btn super-btn-sm super-btn-secondary" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($tenant['allows_subtenants']): ?>
                                    <a href="/super-admin/tenants/create?parent_id=<?= $tenant['id'] ?>" class="super-btn super-btn-sm super-btn-primary" title="Add Sub-tenant">
                                        <i class="fa-solid fa-plus"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
