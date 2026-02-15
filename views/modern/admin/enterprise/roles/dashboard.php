<?php
/**
 * Role & Permission Management Dashboard - Gold Standard v2.0
 * STANDALONE admin interface for enterprise RBAC system
 */

use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\PermissionService;

$basePath = TenantContext::getBasePath();
$permService = new PermissionService();

// Admin header configuration
$adminPageTitle = 'Roles & Permissions';
$adminPageSubtitle = 'Enterprise';
$adminPageIcon = 'fa-shield-halved';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'roles';
$currentPage = 'dashboard';

// Get all roles and permissions
$roles = $permService->getAllRoles();
$permissions = $permService->getAllPermissions();

// Calculate stats
$totalRoles = count($roles);
$totalPermissions = array_sum(array_map('count', $permissions));
$totalUsers = array_sum(array_column($roles, 'user_count'));
$dangerousPerms = 0;
foreach ($permissions as $category => $perms) {
    $dangerousPerms += count(array_filter($perms, fn($p) => $p['is_dangerous']));
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-user-shield"></i>
            Roles & Permissions
        </h1>
        <p class="admin-page-subtitle">Manage access control and permission assignments</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Enterprise Hub
        </a>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/permissions" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-key"></i> Permissions
        </a>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<style>
/* Role Management - Gold Standard v2.0 */

/* Stats Grid */
.role-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

@media (max-width: 1200px) {
    .role-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .role-stats-grid { grid-template-columns: 1fr; }
}

.role-stat-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
}

.role-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border-color: rgba(99, 102, 241, 0.4);
}

.role-stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.role-stat-icon-purple {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
}

.role-stat-icon-blue {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
}

.role-stat-icon-green {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
}

.role-stat-icon-red {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    box-shadow: 0 8px 24px rgba(239, 68, 68, 0.3);
}

.role-stat-content {
    flex: 1;
}

.role-stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
    line-height: 1;
    margin-bottom: 6px;
}

.role-stat-label {
    font-size: 0.85rem;
    color: #94a3b8;
    font-weight: 500;
}

/* Roles Grid */
.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

@media (max-width: 768px) {
    .roles-grid { grid-template-columns: 1fr; }
}

.role-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 28px;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.role-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    border-color: rgba(99, 102, 241, 0.4);
}

.role-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 20px;
}

.role-card-title-area {
    flex: 1;
}

.role-card-level {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #818cf8;
    margin-bottom: 10px;
}

.role-card-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 6px 0;
}

.role-card-description {
    font-size: 0.85rem;
    color: #94a3b8;
    line-height: 1.5;
    margin-bottom: 20px;
}

.role-card-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

.role-card-stat {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
    padding: 12px 16px;
    text-align: center;
}

.role-card-stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #6366f1;
    line-height: 1;
    margin-bottom: 4px;
}

.role-card-stat-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}

.role-card-actions {
    display: flex;
    gap: 8px;
}

.role-card-actions .admin-btn {
    flex: 1;
    padding: 10px 16px;
    font-size: 0.85rem;
}

.role-system-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    padding: 4px 10px;
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
    color: #10b981;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Permission Categories */
.permission-categories {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 28px;
}

.permission-categories-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.permission-categories-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.permission-categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.permission-category-card {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 14px;
    padding: 20px;
    transition: all 0.3s;
}

.permission-category-card:hover {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(99, 102, 241, 0.3);
    transform: translateY(-2px);
}

.permission-category-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.permission-category-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #f1f5f9;
    text-transform: capitalize;
}

.permission-category-count {
    padding: 3px 8px;
    background: rgba(99, 102, 241, 0.2);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #818cf8;
}

.permission-category-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.permission-category-list li {
    font-size: 0.8rem;
    color: #94a3b8;
    padding: 6px 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.05);
    display: flex;
    align-items: center;
    gap: 8px;
}

.permission-category-list li:last-child {
    border-bottom: none;
}

.permission-category-list li i {
    font-size: 0.7rem;
    color: #6366f1;
}

.permission-dangerous {
    color: #f87171 !important;
}

.permission-dangerous i {
    color: #ef4444 !important;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: #94a3b8;
    margin-bottom: 10px;
}

.empty-state p {
    font-size: 0.9rem;
    margin-bottom: 20px;
}
</style>

<!-- Stats Grid -->
<div class="role-stats-grid">
    <div class="role-stat-card">
        <div class="role-stat-icon role-stat-icon-purple">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <div class="role-stat-content">
            <div class="role-stat-value"><?= $totalRoles ?></div>
            <div class="role-stat-label">Total Roles</div>
        </div>
    </div>

    <div class="role-stat-card">
        <div class="role-stat-icon role-stat-icon-blue">
            <i class="fa-solid fa-key"></i>
        </div>
        <div class="role-stat-content">
            <div class="role-stat-value"><?= $totalPermissions ?></div>
            <div class="role-stat-label">Total Permissions</div>
        </div>
    </div>

    <div class="role-stat-card">
        <div class="role-stat-icon role-stat-icon-green">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="role-stat-content">
            <div class="role-stat-value"><?= $totalUsers ?></div>
            <div class="role-stat-label">Users with Roles</div>
        </div>
    </div>

    <div class="role-stat-card">
        <div class="role-stat-icon role-stat-icon-red">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="role-stat-content">
            <div class="role-stat-value"><?= $dangerousPerms ?></div>
            <div class="role-stat-label">Dangerous Permissions</div>
        </div>
    </div>
</div>

<!-- Roles Section -->
<h2 style="font-size: 1.5rem; font-weight: 700; color: #f1f5f9; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
    <i class="fa-solid fa-user-shield" style="color: #6366f1;"></i>
    Enterprise Roles
</h2>

<?php if (empty($roles)): ?>
<div class="empty-state">
    <i class="fa-solid fa-shield-halved"></i>
    <h3>No Roles Found</h3>
    <p>Create your first role to start managing permissions</p>
    <button onclick="createNewRole()" class="admin-btn admin-btn-primary">
        <i class="fa-solid fa-plus"></i> Create First Role
    </button>
</div>
<?php else: ?>
<div class="roles-grid">
    <?php foreach ($roles as $role): ?>
    <div class="role-card">
        <?php if ($role['is_system']): ?>
        <span class="role-system-badge">System</span>
        <?php endif; ?>

        <div class="role-card-header">
            <div class="role-card-title-area">
                <div class="role-card-level">Level <?= $role['level'] ?></div>
                <h3 class="role-card-name"><?= htmlspecialchars($role['display_name']) ?></h3>
            </div>
        </div>

        <p class="role-card-description"><?= htmlspecialchars($role['description']) ?></p>

        <div class="role-card-stats">
            <div class="role-card-stat">
                <div class="role-card-stat-value"><?= $role['permission_count'] ?></div>
                <div class="role-card-stat-label">Permissions</div>
            </div>
            <div class="role-card-stat">
                <div class="role-card-stat-value"><?= $role['user_count'] ?></div>
                <div class="role-card-stat-label">Users</div>
            </div>
        </div>

        <div class="role-card-actions">
            <button onclick="viewRole(<?= $role['id'] ?>)" class="admin-btn admin-btn-secondary">
                <i class="fa-solid fa-eye"></i> View
            </button>
            <button onclick="editRole(<?= $role['id'] ?>)" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-edit"></i> Edit
            </button>
            <?php if (!$role['is_system']): ?>
            <button onclick="deleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['display_name']) ?>')" class="admin-btn admin-btn-danger">
                <i class="fa-solid fa-trash"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Permission Categories -->
<div style="margin-top: 48px;">
    <h2 style="font-size: 1.5rem; font-weight: 700; color: #f1f5f9; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <i class="fa-solid fa-key" style="color: #6366f1;"></i>
        Permission Catalog
    </h2>

    <div class="permission-categories">
        <div class="permission-categories-grid">
            <?php foreach ($permissions as $category => $perms): ?>
            <div class="permission-category-card">
                <div class="permission-category-header">
                    <div class="permission-category-name"><?= ucfirst($category) ?></div>
                    <div class="permission-category-count"><?= count($perms) ?></div>
                </div>
                <ul class="permission-category-list">
                    <?php foreach (array_slice($perms, 0, 5) as $perm): ?>
                    <li class="<?= $perm['is_dangerous'] ? 'permission-dangerous' : '' ?>">
                        <i class="fa-solid <?= $perm['is_dangerous'] ? 'fa-triangle-exclamation' : 'fa-check' ?>"></i>
                        <span><?= htmlspecialchars($perm['display_name']) ?></span>
                    </li>
                    <?php endforeach; ?>
                    <?php if (count($perms) > 5): ?>
                    <li style="color: #6366f1; font-weight: 600; cursor: pointer;" onclick="viewCategory('<?= $category ?>')">
                        <i class="fa-solid fa-ellipsis"></i>
                        <span>+<?= count($perms) - 5 ?> more...</span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function createNewRole() {
    window.location.href = '<?= $basePath ?>/admin-legacy/enterprise/roles/create';
}

function viewRole(roleId) {
    window.location.href = '<?= $basePath ?>/admin-legacy/enterprise/roles/' + roleId;
}

function editRole(roleId) {
    window.location.href = '<?= $basePath ?>/admin-legacy/enterprise/roles/' + roleId + '/edit';
}

async function deleteRole(roleId, roleName) {
    const confirmed = await AdminModal.confirm({
        title: 'Delete Role',
        message: `Are you sure you want to delete the role "${roleName}"? This action cannot be undone. Users with this role will lose their permissions.`,
        type: 'danger',
        confirmText: 'Delete Role',
        cancelText: 'Cancel'
    });

    if (!confirmed) return;

    try {
        const response = await fetch(`<?= $basePath ?>/admin-legacy/api/roles/${roleId}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            AdminRealTime.showToast('Role deleted successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            AdminRealTime.showToast(data.error || 'Failed to delete role', 'error');
        }
    } catch (error) {
        console.error('Delete role error:', error);
        AdminRealTime.showToast('An error occurred', 'error');
    }
}

function viewCategory(category) {
    window.location.href = '<?= $basePath ?>/admin-legacy/enterprise/permissions?category=' + category;
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
