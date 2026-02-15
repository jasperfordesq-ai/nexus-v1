<?php
/**
 * Permissions Browser - Gold Standard v2.0 Admin Interface
 * STANDALONE admin interface for browsing all system permissions
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Database;

$adminPageTitle = 'Permissions Browser';
$adminPageSubtitle = 'Enterprise Access Control';
$adminPageIcon = 'fa-key';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

$basePath = TenantContext::getBasePath();
$db = Database::getInstance();

// Get filter parameters
$filterCategory = $_GET['category'] ?? null;
$filterSearch = $_GET['search'] ?? null;
$filterDangerous = isset($_GET['dangerous']) ? (bool)$_GET['dangerous'] : null;

// Build query
$sql = "SELECT * FROM permissions WHERE 1=1";
$params = [];

if ($filterCategory) {
    $sql .= " AND category = ?";
    $params[] = $filterCategory;
}

if ($filterSearch) {
    $sql .= " AND (name LIKE ? OR display_name LIKE ? OR description LIKE ?)";
    $params[] = '%' . $filterSearch . '%';
    $params[] = '%' . $filterSearch . '%';
    $params[] = '%' . $filterSearch . '%';
}

if ($filterDangerous !== null) {
    $sql .= " AND is_dangerous = ?";
    $params[] = $filterDangerous ? 1 : 0;
}

$sql .= " ORDER BY category, name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$permissions = $stmt->fetchAll();

// Get categories
$categories = $db->query("SELECT DISTINCT category FROM permissions ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Group permissions
$groupedPerms = [];
foreach ($permissions as $perm) {
    $category = $perm['category'];
    if (!isset($groupedPerms[$category])) {
        $groupedPerms[$category] = [];
    }
    $groupedPerms[$category][] = $perm;
}

// Get stats
$totalPerms = $db->query("SELECT COUNT(*) as c FROM permissions")->fetch()['c'];
$dangerousPerms = $db->query("SELECT COUNT(*) as c FROM permissions WHERE is_dangerous = 1")->fetch()['c'];
?>

<style>
/* Permissions Browser - Gold Standard v2.0 */

/* Filter Bar */
.filter-bar {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #f1f5f9;
    margin-bottom: 0.5rem;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    background: rgba(30, 41, 59, 0.7);
    color: #f1f5f9;
    font-size: 0.875rem;
    transition: all 0.3s;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Permission Grid */
.permission-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.25rem;
    margin-top: 1.5rem;
}

@media (max-width: 768px) {
    .permission-grid {
        grid-template-columns: 1fr;
    }
}

.permission-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.3s;
}

.permission-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    border-color: rgba(99, 102, 241, 0.4);
}

.permission-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.permission-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
    font-size: 1.125rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.permission-info {
    flex: 1;
    min-width: 0;
}

.permission-name {
    font-weight: 700;
    font-size: 0.9375rem;
    color: #f1f5f9;
    margin-bottom: 0.375rem;
}

.permission-slug {
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    color: #64748b;
    background: rgba(30, 41, 59, 0.7);
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-block;
}

.permission-description {
    font-size: 0.875rem;
    color: #94a3b8;
    line-height: 1.6;
    margin-top: 0.75rem;
}

.permission-meta {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.875rem;
    flex-wrap: wrap;
}

.permission-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}

.badge-dangerous {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.badge-category {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: #94a3b8;
}

/* Category Section */
.category-section {
    margin-bottom: 3rem;
}

.category-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid rgba(99, 102, 241, 0.2);
}

.category-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.75rem;
    box-shadow: 0 8px 24px rgba(6, 182, 212, 0.3);
}

.category-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #f1f5f9;
    text-transform: capitalize;
    letter-spacing: -0.02em;
}

.category-count {
    background: rgba(99, 102, 241, 0.15);
    border: 1px solid rgba(99, 102, 241, 0.3);
    padding: 6px 16px;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 700;
    color: #818cf8;
}

/* Roles List */
.roles-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.875rem;
}

.role-tag {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #3b82f6;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-key"></i>
            Permissions Browser
        </h1>
        <p class="admin-page-subtitle">
            Browse and search all <?= number_format($totalPerms) ?> system permissions
        </p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/audit/permissions" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-clipboard-list"></i> Audit Log
        </a>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/roles" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-user-tag"></i> Manage Roles
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon-blue">
            <i class="fas fa-key"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalPerms) ?></div>
            <div class="admin-stat-label">Total Permissions</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon-purple">
            <i class="fas fa-layer-group"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= count($categories) ?></div>
            <div class="admin-stat-label">Categories</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon-red">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($dangerousPerms) ?></div>
            <div class="admin-stat-label">Dangerous Permissions</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon admin-stat-icon-green">
            <i class="fas fa-filter"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format(count($permissions)) ?></div>
            <div class="admin-stat-label">Filtered Results</div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar">
    <div class="filter-group">
        <label>Search</label>
        <input type="text" name="search" placeholder="Search permissions..." value="<?= htmlspecialchars($filterSearch ?? '') ?>">
    </div>

    <div class="filter-group">
        <label>Category</label>
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>>
                    <?= ucfirst(htmlspecialchars($cat)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label>Type</label>
        <select name="dangerous">
            <option value="">All Types</option>
            <option value="1" <?= $filterDangerous === true ? 'selected' : '' ?>>Dangerous Only</option>
            <option value="0" <?= $filterDangerous === false ? 'selected' : '' ?>>Safe Only</option>
        </select>
    </div>

    <div class="filter-group" style="display: flex; gap: 0.5rem; min-width: auto;">
        <button type="submit" class="admin-btn admin-btn-primary" style="white-space: nowrap;">
            <i class="fas fa-filter"></i> Apply
        </button>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/permissions" class="admin-btn admin-btn-secondary">
            <i class="fas fa-times"></i>
        </a>
    </div>
</form>

<!-- Permissions List -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-blue">
            <i class="fa-solid fa-key"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">
                <?php if ($filterCategory): ?>
                    <?= ucfirst(htmlspecialchars($filterCategory)) ?> Permissions
                <?php else: ?>
                    All Permissions
                <?php endif; ?>
            </h3>
            <p class="admin-card-subtitle"><?= number_format(count($permissions)) ?> permissions found</p>
        </div>
    </div>
    <div class="admin-card-body">
        <?php if (empty($permissions)): ?>
            <div class="admin-empty-state">
                <div class="admin-empty-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h3 class="admin-empty-title">No Permissions Found</h3>
                <p class="admin-empty-text">Try adjusting your filters or search terms.</p>
            </div>
        <?php else: ?>
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
                                <div class="permission-header">
                                    <div class="permission-icon">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div class="permission-info">
                                        <div class="permission-name"><?= htmlspecialchars($perm['display_name']) ?></div>
                                        <div class="permission-slug"><?= htmlspecialchars($perm['name']) ?></div>
                                    </div>
                                </div>

                                <div class="permission-description">
                                    <?= htmlspecialchars($perm['description']) ?>
                                </div>

                                <div class="permission-meta">
                                    <?php if ($perm['is_dangerous']): ?>
                                        <span class="permission-badge badge-dangerous">
                                            <i class="fas fa-exclamation-triangle"></i> Dangerous
                                        </span>
                                    <?php endif; ?>
                                    <span class="permission-badge badge-category">
                                        <i class="fas fa-<?= getCategoryIcon($perm['category']) ?>"></i> <?= htmlspecialchars($perm['category']) ?>
                                    </span>
                                </div>

                                <?php
                                // Get roles with this permission
                                $stmt = $db->prepare("
                                    SELECT r.display_name
                                    FROM roles r
                                    JOIN role_permissions rp ON r.id = rp.role_id
                                    WHERE rp.permission_id = ?
                                    ORDER BY r.level DESC
                                ");
                                $stmt->execute([$perm['id']]);
                                $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                ?>

                                <?php if (!empty($roles)): ?>
                                    <div class="roles-list">
                                        <?php foreach ($roles as $role): ?>
                                            <span class="role-tag"><?= htmlspecialchars($role) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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
