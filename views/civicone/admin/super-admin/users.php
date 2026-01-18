<?php
/**
 * Super Admin Users - Gold Standard
 * Global User Directory across all tenants
 * Path: views/modern/admin/super-admin/users.php
 */

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// $users is passed from the controller

// Count stats
$totalUsers = count($users);
$pendingUsers = count(array_filter($users, fn($u) => empty($u['is_approved'])));
$adminUsers = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$activeUsers = $totalUsers - $pendingUsers;

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Filter users if needed
if ($filter === 'pending') {
    $displayUsers = array_filter($users, fn($u) => empty($u['is_approved']));
} elseif ($filter === 'admins') {
    $displayUsers = array_filter($users, fn($u) => $u['role'] === 'admin');
} else {
    $displayUsers = $users;
}

// Flash messages
$successMsg = $_GET['msg'] ?? null;
$errorMsg = $_GET['error'] ?? null;
$deleted = isset($_GET['deleted']);
$approved = isset($_GET['approved']);

// Header config
$superAdminPageTitle = 'Global Users';
$superAdminPageSubtitle = 'User Directory';
$superAdminPageIcon = 'fa-users-gear';

require dirname(__DIR__) . '/partials/super-admin-header.php';
?>

<?php if ($deleted): ?>
<div class="super-admin-flash-message" data-type="success" style="display:none;">User deleted successfully.</div>
<?php endif; ?>

<?php if ($approved): ?>
<div class="super-admin-flash-message" data-type="success" style="display:none;">User approved successfully.</div>
<?php endif; ?>

<?php if ($errorMsg === 'cannot_delete_self'): ?>
<div class="super-admin-flash-message" data-type="error" style="display:none;">You cannot delete your own account.</div>
<?php endif; ?>

<!-- Page Header -->
<div class="super-admin-page-header">
    <div class="super-admin-page-header-content">
        <div class="super-admin-page-header-icon">
            <i class="fa-solid fa-users-gear"></i>
        </div>
        <div>
            <h1 class="super-admin-page-title">Global User Directory</h1>
            <p class="super-admin-page-subtitle">Manage users across all communities on the platform</p>
        </div>
    </div>
    <a href="/super-admin" class="super-admin-btn super-admin-btn-secondary">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Dashboard
    </a>
</div>

<!-- Stats Grid -->
<div class="super-admin-stats-grid">
    <div class="super-admin-stat-card" style="--stat-color: linear-gradient(135deg, #9333ea, #7c3aed);">
        <div class="super-admin-stat-icon" style="background: linear-gradient(135deg, #9333ea, #7c3aed); color: white;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="super-admin-stat-value"><?= number_format($totalUsers) ?></div>
        <div class="super-admin-stat-label">Total Users</div>
    </div>

    <div class="super-admin-stat-card" style="--stat-color: linear-gradient(135deg, #10b981, #059669);">
        <div class="super-admin-stat-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="super-admin-stat-value"><?= number_format($activeUsers) ?></div>
        <div class="super-admin-stat-label">Active Users</div>
    </div>

    <div class="super-admin-stat-card" style="--stat-color: linear-gradient(135deg, #f59e0b, #d97706);">
        <div class="super-admin-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
            <i class="fa-solid fa-user-clock"></i>
        </div>
        <div class="super-admin-stat-value"><?= number_format($pendingUsers) ?></div>
        <div class="super-admin-stat-label">Pending Approval</div>
    </div>

    <div class="super-admin-stat-card" style="--stat-color: linear-gradient(135deg, #ec4899, #db2777);">
        <div class="super-admin-stat-icon" style="background: linear-gradient(135deg, #ec4899, #db2777); color: white;">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="super-admin-stat-value"><?= number_format($adminUsers) ?></div>
        <div class="super-admin-stat-label">Administrators</div>
    </div>
</div>

<!-- Filter Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 1.5rem;">
    <a href="/super-admin/users" class="super-admin-btn <?= $filter === 'all' ? 'super-admin-btn-primary' : 'super-admin-btn-secondary' ?> super-admin-btn-sm">
        <i class="fa-solid fa-users"></i>
        All Users (<?= $totalUsers ?>)
    </a>
    <a href="/super-admin/users?filter=pending" class="super-admin-btn <?= $filter === 'pending' ? 'super-admin-btn-primary' : 'super-admin-btn-secondary' ?> super-admin-btn-sm">
        <i class="fa-solid fa-clock"></i>
        Pending (<?= $pendingUsers ?>)
    </a>
    <a href="/super-admin/users?filter=admins" class="super-admin-btn <?= $filter === 'admins' ? 'super-admin-btn-primary' : 'super-admin-btn-secondary' ?> super-admin-btn-sm">
        <i class="fa-solid fa-shield"></i>
        Admins (<?= $adminUsers ?>)
    </a>
</div>

<!-- Users Table -->
<div class="super-admin-glass-card">
    <div class="super-admin-card-header">
        <div class="super-admin-card-header-icon super-admin-card-header-icon-purple">
            <i class="fa-solid fa-list"></i>
        </div>
        <div class="super-admin-card-header-content">
            <h3 class="super-admin-card-title">
                <?php
                if ($filter === 'pending') echo 'Pending Users';
                elseif ($filter === 'admins') echo 'Administrators';
                else echo 'All Users';
                ?>
            </h3>
            <p class="super-admin-card-subtitle"><?= count($displayUsers) ?> users in this view</p>
        </div>
    </div>
    <div class="super-admin-card-body" style="padding: 0;">
        <?php if (!empty($displayUsers)): ?>
        <div class="super-admin-table-wrapper">
            <table class="super-admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Community</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($displayUsers as $u): ?>
                    <tr>
                        <td style="color: rgba(255,255,255,0.5);">#<?= $u['id'] ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #9333ea, #ec4899); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem;">
                                    <?= strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #fff;"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: rgba(255,255,255,0.5);"><?= htmlspecialchars($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($u['tenant_id'] == 1): ?>
                                <span style="background: rgba(147, 51, 234, 0.15); color: #c084fc; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Platform</span>
                            <?php else: ?>
                                <div>
                                    <span style="background: rgba(6, 182, 212, 0.15); color: #22d3ee; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                        <?= htmlspecialchars($u['tenant_name'] ?? 'Unknown') ?>
                                    </span>
                                    <div style="font-size: 0.7rem; color: rgba(255,255,255,0.4); margin-top: 3px; font-family: monospace;">
                                        /<?= htmlspecialchars($u['tenant_slug'] ?? '') ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span style="background: rgba(236, 72, 153, 0.15); color: #f472b6; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="fa-solid fa-shield" style="margin-right: 4px;"></i>Admin
                                </span>
                            <?php elseif (!empty($u['is_super_admin'])): ?>
                                <span style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="fa-solid fa-crown" style="margin-right: 4px;"></i>Super Admin
                                </span>
                            <?php else: ?>
                                <span style="color: rgba(255,255,255,0.6); font-size: 0.85rem; text-transform: capitalize;">
                                    <?= htmlspecialchars($u['role'] ?? 'member') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($u['is_approved'])): ?>
                                <span class="super-admin-status-badge super-admin-status-active">
                                    <span class="super-admin-status-dot"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="super-admin-status-badge super-admin-status-pending">
                                    <span class="super-admin-status-dot"></span> Pending
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">
                            <?= date('M j, Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                <?php if (empty($u['is_approved'])): ?>
                                <form action="/super-admin/users/approve" method="POST" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="super-admin-btn super-admin-btn-sm" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 4px 10px;">
                                        <i class="fa-solid fa-check"></i> Approve
                                    </button>
                                </form>
                                <?php endif; ?>
                                <a href="<?= $basePath ?>/admin/users/edit/<?= $u['id'] ?>" class="super-admin-btn super-admin-btn-secondary super-admin-btn-sm" style="padding: 4px 10px; text-decoration: none;">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form action="/super-admin/users/delete" method="POST" onsubmit="return confirm('Are you sure you want to PERMANENTLY delete this user? This cannot be undone.');" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="super-admin-btn super-admin-btn-danger super-admin-btn-sm" style="padding: 4px 10px;">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="padding: 60px 20px; text-align: center;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(147, 51, 234, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <i class="fa-solid fa-users" style="font-size: 1.5rem; color: #c084fc;"></i>
            </div>
            <h3 style="color: #fff; font-size: 1.1rem; margin: 0 0 8px;">No Users Found</h3>
            <p style="color: rgba(255,255,255,0.5); font-size: 0.9rem; margin: 0;">
                <?php
                if ($filter === 'pending') echo 'No users pending approval.';
                elseif ($filter === 'admins') echo 'No administrators found.';
                else echo 'No users in the system yet.';
                ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Responsive table */
@media (max-width: 1024px) {
    .super-admin-table {
        font-size: 0.8rem;
    }

    .super-admin-table th,
    .super-admin-table td {
        padding: 0.75rem 0.5rem;
    }
}

@media (max-width: 768px) {
    .super-admin-table-wrapper {
        overflow-x: auto;
    }

    .super-admin-table {
        min-width: 800px;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/super-admin-footer.php'; ?>
