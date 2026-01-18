<?php
/**
 * Super Admin - View Tenant Details
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Tenant Details';
require __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/tenants">Tenants</a>
    <span class="super-breadcrumb-sep">/</span>
    <?php foreach ($breadcrumb as $i => $crumb): ?>
        <?php if ($i > 0): ?><span class="super-breadcrumb-sep">/</span><?php endif; ?>
        <?php if ($crumb['id'] === $tenant['id']): ?>
            <span><?= htmlspecialchars($crumb['name']) ?></span>
        <?php else: ?>
            <a href="/super-admin/tenants/<?= $crumb['id'] ?>"><?= htmlspecialchars($crumb['name']) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-building"></i>
            <?= htmlspecialchars($tenant['name']) ?>
        </h1>
        <p class="super-page-subtitle">
            <?php if (!empty($tenant['tagline'])): ?>
                <?= htmlspecialchars($tenant['tagline']) ?>
            <?php else: ?>
                Tenant ID: <?= $tenant['id'] ?> | Path: <?= htmlspecialchars($tenant['path']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="super-page-actions">
        <?php if ($tenant['allows_subtenants']): ?>
            <a href="/super-admin/tenants/create?parent_id=<?= $tenant['id'] ?>" class="super-btn super-btn-primary">
                <i class="fa-solid fa-plus"></i>
                Add Sub-Tenant
            </a>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <a href="/super-admin/tenants/<?= $tenant['id'] ?>/edit" class="super-btn super-btn-secondary">
                <i class="fa-solid fa-pen"></i>
                Edit
            </a>
        <?php endif; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Main Content -->
    <div>
        <!-- Info Card -->
        <div class="super-card" style="margin-bottom: 1.5rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-info-circle"></i>
                    Tenant Information
                </h3>
                <div>
                    <?php if ($tenant['is_active']): ?>
                        <span class="super-badge super-badge-success">Active</span>
                    <?php else: ?>
                        <span class="super-badge super-badge-danger">Inactive</span>
                    <?php endif; ?>
                    <?php if ($tenant['allows_subtenants']): ?>
                        <span class="super-badge super-badge-purple">Hub Tenant</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="super-card-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Slug</strong>
                        <code style="background: var(--super-bg); padding: 0.25rem 0.5rem; border-radius: 4px;">
                            <?= htmlspecialchars($tenant['slug'] ?? '-') ?>
                        </code>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Domain</strong>
                        <?php if (!empty($tenant['domain'])): ?>
                            <a href="https://<?= htmlspecialchars($tenant['domain']) ?>" target="_blank" style="color: var(--super-primary);">
                                <?= htmlspecialchars($tenant['domain']) ?>
                                <i class="fa-solid fa-external-link" style="font-size: 0.75rem;"></i>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--super-text-muted);">Not configured</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Hierarchy Level</strong>
                        <span>Level <?= $tenant['depth'] ?></span>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Parent</strong>
                        <?php if (!empty($tenant['parent_name'])): ?>
                            <a href="/super-admin/tenants/<?= $tenant['parent_id'] ?>" class="super-table-link">
                                <?= htmlspecialchars($tenant['parent_name']) ?>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--super-text-muted);">Root tenant</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Total Users</strong>
                        <span><?= number_format($tenant['user_count'] ?? 0) ?></span>
                    </div>
                    <div>
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Sub-tenants</strong>
                        <span><?= number_format($tenant['direct_children'] ?? 0) ?> direct</span>
                    </div>
                </div>

                <?php if (!empty($tenant['description'])): ?>
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                        <strong style="display: block; color: var(--super-text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem;">Description</strong>
                        <p style="color: var(--super-text-muted);"><?= nl2br(htmlspecialchars($tenant['description'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sub-tenants -->
        <?php if (!empty($children)): ?>
            <div class="super-card" style="margin-bottom: 1.5rem;">
                <div class="super-card-header">
                    <h3 class="super-card-title">
                        <i class="fa-solid fa-sitemap"></i>
                        Sub-Tenants (<?= count($children) ?>)
                    </h3>
                </div>
                <table class="super-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Users</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($children as $child): ?>
                            <tr>
                                <td>
                                    <a href="/super-admin/tenants/<?= $child['id'] ?>" class="super-table-link">
                                        <?= htmlspecialchars($child['name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $childUsers = \Nexus\Core\Database::query(
                                        "SELECT COUNT(*) FROM users WHERE tenant_id = ?",
                                        [$child['id']]
                                    )->fetchColumn();
                                    echo number_format($childUsers);
                                    ?>
                                </td>
                                <td>
                                    <?php if ($child['is_active']): ?>
                                        <span class="super-badge super-badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="super-badge super-badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/super-admin/tenants/<?= $child['id'] ?>" class="super-btn super-btn-sm super-btn-secondary">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Tenant Admins -->
        <div class="super-card">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-user-shield"></i>
                    Tenant Administrators
                </h3>
                <a href="/super-admin/users?tenant_id=<?= $tenant['id'] ?>" class="super-btn super-btn-sm super-btn-secondary">
                    View All Users
                </a>
            </div>
            <table class="super-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Super Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($admins)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem; color: var(--super-text-muted);">
                                No administrators assigned
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td>
                                    <a href="/super-admin/users/<?= $admin['id'] ?>" class="super-table-link">
                                        <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
                                    </a>
                                </td>
                                <td style="color: var(--super-text-muted);"><?= htmlspecialchars($admin['email']) ?></td>
                                <td>
                                    <span class="super-badge super-badge-info"><?= htmlspecialchars($admin['role']) ?></span>
                                </td>
                                <td>
                                    <?php if ($admin['is_tenant_super_admin']): ?>
                                        <span class="super-badge super-badge-purple">
                                            <i class="fa-solid fa-crown"></i> Yes
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--super-text-muted);">No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Quick Actions -->
        <div class="super-card" style="margin-bottom: 1rem;">
            <div class="super-card-header">
                <h3 class="super-card-title">
                    <i class="fa-solid fa-bolt"></i>
                    Actions
                </h3>
            </div>
            <div class="super-card-body">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php if ($tenant['allows_subtenants']): ?>
                        <a href="/super-admin/tenants/create?parent_id=<?= $tenant['id'] ?>" class="super-btn super-btn-primary" style="justify-content: center;">
                            <i class="fa-solid fa-plus"></i>
                            Create Sub-Tenant
                        </a>
                    <?php endif; ?>

                    <a href="/super-admin/users/create?tenant_id=<?= $tenant['id'] ?>" class="super-btn super-btn-secondary" style="justify-content: center;">
                        <i class="fa-solid fa-user-plus"></i>
                        Add User
                    </a>

                    <?php if ($canManage): ?>
                        <a href="/super-admin/tenants/<?= $tenant['id'] ?>/edit" class="super-btn super-btn-secondary" style="justify-content: center;">
                            <i class="fa-solid fa-cog"></i>
                            Settings
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hub Toggle -->
        <?php if ($canManage): ?>
            <div class="super-card" style="margin-bottom: 1rem;">
                <div class="super-card-header">
                    <h3 class="super-card-title">
                        <i class="fa-solid fa-sitemap"></i>
                        Hub Settings
                    </h3>
                </div>
                <div class="super-card-body">
                    <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                        Hub tenants can create their own sub-tenants and have regional super admins.
                    </p>
                    <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/toggle-hub">
                        <?= Csrf::field() ?>
                        <?php if ($tenant['allows_subtenants']): ?>
                            <input type="hidden" name="enable" value="0">
                            <button type="submit" class="super-btn super-btn-danger" style="width: 100%; justify-content: center;"
                                    onclick="return confirm('This will prevent new sub-tenants from being created. Existing sub-tenants will remain.');">
                                <i class="fa-solid fa-toggle-off"></i>
                                Disable Hub
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="enable" value="1">
                            <button type="submit" class="super-btn super-btn-success" style="width: 100%; justify-content: center;">
                                <i class="fa-solid fa-toggle-on"></i>
                                Enable Hub
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tenant Status Toggle -->
        <?php if ($canManage && (int)$tenant['id'] !== 1): ?>
            <?php if ((int)$tenant['is_active'] === 1): ?>
                <!-- Danger Zone - Deactivate -->
                <div class="super-card" style="border-color: var(--super-danger);">
                    <div class="super-card-header" style="background: rgba(239, 68, 68, 0.1);">
                        <h3 class="super-card-title" style="color: var(--super-danger);">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            Danger Zone
                        </h3>
                    </div>
                    <div class="super-card-body">
                        <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                            Deactivating will prevent all users from accessing this tenant.
                        </p>
                        <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/delete">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn super-btn-danger" style="width: 100%; justify-content: center;"
                                    onclick="return confirm('Are you sure you want to deactivate this tenant? Users will lose access.');">
                                <i class="fa-solid fa-power-off"></i>
                                Deactivate Tenant
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Reactivate -->
                <div class="super-card" style="border-color: var(--super-success);">
                    <div class="super-card-header" style="background: rgba(34, 197, 94, 0.1);">
                        <h3 class="super-card-title" style="color: var(--super-success);">
                            <i class="fa-solid fa-heart-pulse"></i>
                            Reactivate Tenant
                        </h3>
                    </div>
                    <div class="super-card-body">
                        <p style="color: var(--super-text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
                            This tenant is currently inactive. Reactivating will restore user access.
                        </p>
                        <form method="POST" action="/super-admin/tenants/<?= $tenant['id'] ?>/reactivate">
                            <?= Csrf::field() ?>
                            <button type="submit" class="super-btn super-btn-success" style="width: 100%; justify-content: center;">
                                <i class="fa-solid fa-power-off"></i>
                                Reactivate Tenant
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
