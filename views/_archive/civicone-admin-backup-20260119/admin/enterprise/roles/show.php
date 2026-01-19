<?php
/**
 * Role Detail View
 * Gold Standard v2.0 Admin Interface
 */

use Nexus\Core\TenantContext;

$adminPageTitle = 'Role Details';
$adminPageSubtitle = 'Permissions & Access Control';
$adminPageIcon = 'fa-user-tag';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

$basePath = TenantContext::getBasePath();
?>

<style>
.permission-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.permission-card {
    background: var(--admin-card-bg);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    padding: 1rem;
}

.permission-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.permission-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.permission-name {
    font-weight: 600;
    color: var(--admin-text);
}

.permission-slug {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    font-family: 'Courier New', monospace;
    background: var(--admin-bg-hover);
    padding: 2px 6px;
    border-radius: 3px;
    margin-top: 4px;
    display: inline-block;
}

.permission-description {
    font-size: 0.875rem;
    color: var(--admin-text-muted);
    line-height: 1.5;
}

.category-section {
    margin-bottom: 2rem;
}

.category-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--admin-border);
}

.category-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--admin-accent), var(--admin-accent-dark));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.category-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--admin-text);
    text-transform: capitalize;
}

.category-count {
    background: var(--admin-bg-hover);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--admin-text-muted);
}

.dangerous-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-user-tag"></i>
            <?= htmlspecialchars($role['display_name']) ?>
        </h1>
        <p class="admin-page-subtitle">
            Level <?= $role['level'] ?> Role
            <?php if ($role['is_system']): ?>
                <span class="dangerous-badge">
                    <i class="fas fa-shield-halved"></i> System Role
                </span>
            <?php endif; ?>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/enterprise/roles" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Roles
        </a>
        <?php if (!$role['is_system']): ?>
            <a href="<?= $basePath ?>/admin/enterprise/roles/<?= $role['id'] ?>/edit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-pen"></i> Edit Role
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Role Info Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-purple">
            <i class="fa-solid fa-info-circle"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Role Information</h3>
            <p class="admin-card-subtitle">Details and description</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div style="display: grid; grid-template-columns: 200px 1fr; gap: 1rem; align-items: start;">
            <div style="font-weight: 600; color: var(--admin-text-muted);">Name:</div>
            <div><?= htmlspecialchars($role['display_name']) ?></div>

            <div style="font-weight: 600; color: var(--admin-text-muted);">Slug:</div>
            <div><code style="background: var(--admin-bg-hover); padding: 4px 8px; border-radius: 4px;"><?= htmlspecialchars($role['name']) ?></code></div>

            <div style="font-weight: 600; color: var(--admin-text-muted);">Level:</div>
            <div><?= $role['level'] ?></div>

            <div style="font-weight: 600; color: var(--admin-text-muted);">Type:</div>
            <div><?= $role['is_system'] ? 'System Role (Protected)' : 'Custom Role' ?></div>

            <div style="font-weight: 600; color: var(--admin-text-muted);">Description:</div>
            <div><?= htmlspecialchars($role['description']) ?></div>

            <div style="font-weight: 600; color: var(--admin-text-muted);">Users with this role:</div>
            <div><strong><?= $userCount ?></strong> users</div>

            <div style="font-weight: 600; color: var(--admin-text-muted);">Total permissions:</div>
            <div><strong><?= count($permissions) ?></strong> permissions</div>
        </div>
    </div>
</div>

<!-- Permissions by Category -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-green">
            <i class="fa-solid fa-key"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Permissions (<?= count($permissions) ?>)</h3>
            <p class="admin-card-subtitle">What this role can do</p>
        </div>
    </div>
    <div class="admin-card-body">
        <?php if (empty($permissions)): ?>
            <div class="admin-empty-state">
                <i class="fas fa-key"></i>
                <h4>No Permissions</h4>
                <p>This role doesn't have any permissions assigned yet.</p>
            </div>
        <?php else: ?>
            <?php
            // Group permissions by category
            $groupedPerms = [];
            foreach ($permissions as $perm) {
                $category = $perm['category'];
                if (!isset($groupedPerms[$category])) {
                    $groupedPerms[$category] = [];
                }
                $groupedPerms[$category][] = $perm;
            }
            ksort($groupedPerms);
            ?>

            <?php foreach ($groupedPerms as $category => $perms): ?>
                <div class="category-section">
                    <div class="category-header">
                        <div class="category-icon">
                            <i class="fas fa-<?= getCategoryIcon($category) ?>"></i>
                        </div>
                        <h4 class="category-title"><?= htmlspecialchars($category) ?></h4>
                        <span class="category-count"><?= count($perms) ?> permissions</span>
                    </div>

                    <div class="permission-grid">
                        <?php foreach ($perms as $perm): ?>
                            <div class="permission-card">
                                <div class="permission-card-header">
                                    <div class="permission-icon">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div class="permission-name">
                                            <?= htmlspecialchars($perm['display_name']) ?>
                                            <?php if ($perm['is_dangerous']): ?>
                                                <span class="dangerous-badge">
                                                    <i class="fas fa-exclamation-triangle"></i> Dangerous
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="permission-slug"><?= htmlspecialchars($perm['name']) ?></div>
                                    </div>
                                </div>
                                <p class="permission-description"><?= htmlspecialchars($perm['description']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
function getCategoryIcon($category) {
    $icons = [
        'users' => 'users',
        'content' => 'newspaper',
        'gdpr' => 'user-shield',
        'monitoring' => 'heart-pulse',
        'config' => 'gears',
        'messages' => 'envelope',
        'transactions' => 'money-bill-transfer',
        'roles' => 'user-tag',
        'reports' => 'chart-bar',
        'admin' => 'crown',
    ];
    return $icons[$category] ?? 'key';
}
?>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
