<?php
/**
 * Super Admin - User List
 */

$pageTitle = $pageTitle ?? 'Manage Users';
require __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <span>Users</span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-users"></i>
            User Management
        </h1>
        <p class="super-page-subtitle">
            Manage users across <?= $access['scope'] === 'global' ? 'all tenants' : 'your tenant hierarchy' ?>
        </p>
    </div>
    <div class="super-page-actions">
        <a href="/super-admin/users/create" class="super-btn super-btn-primary">
            <i class="fa-solid fa-user-plus"></i>
            Add User
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

<!-- Filters -->
<div class="super-card" style="margin-bottom: 1.5rem;">
    <div class="super-card-body">
        <form method="GET" action="/super-admin/users" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div class="super-form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                <label class="super-form-label">Search</label>
                <input type="text" name="search" class="super-form-input"
                       placeholder="Name or email..."
                       value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            </div>

            <div class="super-form-group" style="min-width: 180px; margin-bottom: 0;">
                <label class="super-form-label">Tenant</label>
                <select name="tenant_id" class="super-form-select">
                    <option value="">All Tenants</option>
                    <?php foreach ($tenants as $tenant): ?>
                        <option value="<?= $tenant['id'] ?>"
                            <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tenant['indented_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="super-form-group" style="min-width: 140px; margin-bottom: 0;">
                <label class="super-form-label">Role</label>
                <select name="role" class="super-form-select">
                    <option value="">All Roles</option>
                    <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="tenant_admin" <?= ($filters['role'] ?? '') === 'tenant_admin' ? 'selected' : '' ?>>Tenant Admin</option>
                    <option value="moderator" <?= ($filters['role'] ?? '') === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                    <option value="member" <?= ($filters['role'] ?? '') === 'member' ? 'selected' : '' ?>>Member</option>
                </select>
            </div>

            <div class="super-form-group" style="margin-bottom: 0;">
                <label class="super-form-checkbox">
                    <input type="checkbox" name="super_admins" value="1"
                        <?= isset($_GET['super_admins']) ? 'checked' : '' ?>>
                    <span>Super Admins Only</span>
                </label>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="super-btn super-btn-primary">
                    <i class="fa-solid fa-search"></i>
                    Filter
                </button>
                <a href="/super-admin/users" class="super-btn super-btn-secondary">
                    <i class="fa-solid fa-times"></i>
                    Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="super-card">
    <table class="super-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Tenant</th>
                <th>Role</th>
                <th>Status</th>
                <th>Super Admin</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--super-text-muted);">
                        <i class="fa-solid fa-users" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                        No users found matching your criteria
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--super-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                    <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div>
                                    <a href="/super-admin/users/<?= $user['id'] ?>" class="super-table-link">
                                        <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                                    </a>
                                    <div style="font-size: 0.75rem; color: var(--super-text-muted);">
                                        <?= htmlspecialchars($user['email']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="/super-admin/tenants?search=<?= urlencode($user['tenant_name']) ?>" style="color: var(--super-text-muted); font-size: 0.875rem;">
                                <?= htmlspecialchars($user['tenant_name']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="super-badge super-badge-info">
                                <?= htmlspecialchars(ucfirst($user['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['is_approved']): ?>
                                <span class="super-badge super-badge-success">Active</span>
                            <?php else: ?>
                                <span class="super-badge super-badge-warning">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['is_tenant_super_admin']): ?>
                                <span class="super-badge super-badge-purple">
                                    <i class="fa-solid fa-crown"></i> Yes
                                </span>
                            <?php elseif ($user['is_super_admin']): ?>
                                <span class="super-badge super-badge-warning">
                                    <i class="fa-solid fa-star"></i> Legacy
                                </span>
                            <?php else: ?>
                                <span style="color: var(--super-text-muted);">No</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--super-text-muted); font-size: 0.875rem;">
                            <?php if ($user['last_login_at']): ?>
                                <?= date('M j, Y', strtotime($user['last_login_at'])) ?>
                            <?php else: ?>
                                Never
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.25rem;">
                                <a href="/super-admin/users/<?= $user['id'] ?>" class="super-btn super-btn-sm super-btn-secondary" title="View">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (count($users) >= 100): ?>
    <p style="text-align: center; color: var(--super-text-muted); margin-top: 1rem;">
        <i class="fa-solid fa-info-circle"></i>
        Showing first 100 results. Use filters to narrow down your search.
    </p>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
