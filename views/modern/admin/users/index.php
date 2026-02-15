<?php
/**
 * Admin User Management - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Users';
$adminPageSubtitle = 'User Management';
$adminPageIcon = 'fa-users';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-users"></i>
            User Management
        </h1>
        <p class="admin-page-subtitle">View and manage all platform members</p>
    </div>
    <div class="admin-page-header-actions">
        <span class="admin-badge admin-badge-primary"><?= count($users ?? []) ?> Members</span>
        <a href="<?= $basePath ?>/admin-legacy/users/create" class="admin-btn admin-btn-success">
            <i class="fa-solid fa-user-plus"></i> Create User
        </a>
    </div>
</div>

<!-- Success Messages -->
<?php if (isset($_GET['created'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div>
        <strong>Success!</strong> New user has been created successfully.
    </div>
</div>
<?php endif; ?>

<!-- Users Table Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">User Directory</h3>
            <p class="admin-card-subtitle">All registered members</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (!empty($users)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User Profile</th>
                        <th class="hide-mobile">Role</th>
                        <th class="hide-tablet" style="text-align: center;">Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="admin-user-cell">
                                <?php if (!empty($user['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($user['avatar_url']) ?>" loading="lazy" class="admin-user-avatar" alt="">
                                <?php else: ?>
                                    <div class="admin-user-avatar-placeholder">
                                        <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="admin-user-info">
                                    <div class="admin-user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                                    <div class="admin-user-email"><?= htmlspecialchars($user['email']) ?></div>
                                    <div class="admin-user-joined">Joined <?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="hide-mobile">
                            <?php if ($user['role'] === 'admin' && !empty($user['is_super_admin'])): ?>
                                <span class="admin-badge admin-badge-super-admin"><i class="fa-solid fa-crown"></i> Super Admin</span>
                            <?php elseif ($user['role'] === 'admin'): ?>
                                <span class="admin-badge admin-badge-danger">Administrator</span>
                            <?php elseif ($user['role'] === 'newsletter_admin'): ?>
                                <span class="admin-badge admin-badge-warning">Newsletter Admin</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-primary">Member</span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-tablet" style="text-align: center;">
                            <?php if ($user['is_approved']): ?>
                                <span class="admin-status-badge admin-status-active">
                                    <span class="admin-status-dot"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="admin-status-badge admin-status-pending">
                                    <span class="admin-status-dot"></span> Pending
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <form action="<?= $basePath ?>/admin-legacy/impersonate" method="POST" onsubmit="return confirm('You are about to login as <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>. Continue?');" style="display:inline;">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-warning admin-btn-sm" title="Login as this user">
                                        <i class="fa-solid fa-user-secret"></i> Login As
                                    </button>
                                </form>
                                <?php endif; ?>
                                <a href="<?= $basePath ?>/admin-legacy/users/edit/<?= $user['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form action="<?= $basePath ?>/admin-legacy/users/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');" style="display:inline;">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                        <i class="fa-solid fa-trash"></i> Delete
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
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <h3 class="admin-empty-title">No members found</h3>
            <p class="admin-empty-text">Your community is waiting to grow.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* User Management Specific Styles */
.admin-user-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-user-avatar-placeholder {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}

.admin-user-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.admin-user-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

.admin-user-email {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

.admin-user-joined {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

.admin-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    font-weight: 500;
}

.admin-status-badge .admin-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.admin-status-active {
    color: #22c55e;
}

.admin-status-active .admin-status-dot {
    background: #22c55e;
    box-shadow: 0 0 8px rgba(34, 197, 94, 0.5);
}

.admin-status-pending {
    color: #f59e0b;
}

.admin-status-pending .admin-status-dot {
    background: #f59e0b;
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.5);
}

.admin-action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.admin-badge-primary {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.admin-badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-badge-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-badge-super-admin {
    background: linear-gradient(135deg, rgba(147, 51, 234, 0.3), rgba(236, 72, 153, 0.2));
    color: #fbbf24;
    border: 1px solid rgba(147, 51, 234, 0.4);
}

.admin-badge-super-admin i {
    font-size: 0.7rem;
    filter: drop-shadow(0 0 2px rgba(251, 191, 36, 0.5));
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    border: 1px solid rgba(34, 197, 94, 0.5);
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #16a34a, #15803d);
    transform: translateY(-1px);
}

.admin-btn-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.admin-btn-warning:hover {
    background: rgba(245, 158, 11, 0.25);
    border-color: rgba(245, 158, 11, 0.5);
}

/* Alerts */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.admin-alert i {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-alert-success {
    border-left: 3px solid #22c55e;
}
.admin-alert-success i { color: #22c55e; }

/* Table Styles */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.3);
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Responsive */
@media (max-width: 1024px) {
    .hide-tablet {
        display: none;
    }
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }

    .admin-action-buttons {
        flex-direction: column;
    }

    .admin-user-cell {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
