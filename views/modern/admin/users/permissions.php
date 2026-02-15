<?php
/**
 * User Permission Editor
 * Gold Standard v2.0 Admin Interface
 */

use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\PermissionService;

$adminPageTitle = 'User Permissions';
$adminPageSubtitle = 'Access Control';
$adminPageIcon = 'fa-shield-halved';

// Pass user data to header
$pageData = ['user' => $user ?? null];

require dirname(__DIR__) . '/partials/admin-header.php';

$permService = new PermissionService();
$userId = $user['id'];
$currentUserId = $_SESSION['user_id'] ?? 0;

// Check if current user can edit permissions
$canEditPermissions = $permService->can($currentUserId, 'users.edit_permissions');
$canAssignRoles = $permService->can($currentUserId, 'roles.assign');

// Get user's roles
$userRoles = $permService->getUserRoles($userId);

// Get all available roles
$allRoles = $permService->getAllRoles();

// Get user's effective permissions (grouped by category)
$db = Nexus\Core\Database::getInstance();
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.name, p.display_name, p.category, p.description, p.is_dangerous,
           'role' as source, GROUP_CONCAT(r.display_name SEPARATOR ', ') as source_name
    FROM permissions p
    JOIN role_permissions rp ON p.id = rp.permission_id
    JOIN roles r ON rp.role_id = r.id
    JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = ?
        AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
    GROUP BY p.id, p.name, p.display_name, p.category, p.description, p.is_dangerous
    ORDER BY p.category, p.name
");
$stmt->execute([$userId]);
$effectivePerms = $stmt->fetchAll();

// Get direct grants
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.name, p.display_name, p.category, p.description, p.is_dangerous,
           up.expires_at, u.username as granted_by_username, up.granted_at as created_at
    FROM permissions p
    JOIN user_permissions up ON p.id = up.permission_id
    LEFT JOIN users u ON up.granted_by = u.id
    WHERE up.user_id = ?
        AND up.granted = 1
        AND (up.expires_at IS NULL OR up.expires_at > NOW())
    ORDER BY p.category, p.name
");
$stmt->execute([$userId]);
$directGrants = $stmt->fetchAll();

// Get revocations
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.name, p.display_name, p.category, p.description,
           u.username as revoked_by_username, up.granted_at as created_at
    FROM permissions p
    JOIN user_permissions up ON p.id = up.permission_id
    LEFT JOIN users u ON up.granted_by = u.id
    WHERE up.user_id = ?
        AND up.granted = 0
        AND (up.expires_at IS NULL OR up.expires_at > NOW())
");
$stmt->execute([$userId]);
$revocations = $stmt->fetchAll();

$revocationIds = array_column($revocations, 'id');

// Group effective permissions by category
$permsByCategory = [];
foreach ($effectivePerms as $perm) {
    if (!in_array($perm['id'], $revocationIds)) {
        $category = $perm['category'];
        if (!isset($permsByCategory[$category])) {
            $permsByCategory[$category] = [];
        }
        $permsByCategory[$category][] = $perm;
    }
}

// Add direct grants to categories
foreach ($directGrants as $perm) {
    if (!in_array($perm['id'], $revocationIds)) {
        $category = $perm['category'];
        if (!isset($permsByCategory[$category])) {
            $permsByCategory[$category] = [];
        }
        // Check if already in list from role
        $alreadyExists = false;
        foreach ($permsByCategory[$category] as $existing) {
            if ($existing['id'] === $perm['id']) {
                $alreadyExists = true;
                break;
            }
        }
        if (!$alreadyExists) {
            $perm['source'] = 'direct_grant';
            $perm['source_name'] = 'Direct grant by ' . ($perm['granted_by_username'] ?? 'System');
            $permsByCategory[$category][] = $perm;
        }
    }
}

// Get all permissions for assignment modal
$allPermissions = $permService->getAllPermissions();

// Count stats
$totalEffective = array_sum(array_map('count', $permsByCategory));
$totalDangerous = 0;
foreach ($permsByCategory as $perms) {
    foreach ($perms as $p) {
        if ($p['is_dangerous']) $totalDangerous++;
    }
}
?>

<style>
.permissions-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.permissions-user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.permissions-user-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--admin-border);
}

.permissions-user-details h2 {
    margin: 0 0 0.25rem 0;
    font-size: 1.5rem;
    color: var(--admin-text);
}

.permissions-user-details p {
    margin: 0;
    color: var(--admin-text-muted);
    font-size: 0.875rem;
}

.permissions-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--admin-card-bg);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    padding: 1.25rem;
}

.stat-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.stat-card-label {
    font-size: 0.875rem;
    color: var(--admin-text-muted);
    font-weight: 500;
}

.stat-card-icon {
    color: var(--admin-primary);
    font-size: 1.25rem;
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--admin-text);
    line-height: 1;
}

.permissions-section {
    background: var(--admin-card-bg);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--admin-border);
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--admin-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.role-card {
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: border-color 0.2s;
}

.role-card:hover {
    border-color: var(--admin-primary);
}

.role-info {
    flex: 1;
}

.role-name {
    font-weight: 600;
    color: var(--admin-text);
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.role-level {
    display: inline-flex;
    align-items: center;
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.role-level-100 { background: #fef3c7; color: #92400e; }
.role-level-90 { background: #dbeafe; color: #1e40af; }
.role-level-80 { background: #e0e7ff; color: #4338ca; }
.role-level-70 { background: #fce7f3; color: #9f1239; }
.role-level-default { background: #f3f4f6; color: #374151; }

.role-description {
    font-size: 0.875rem;
    color: var(--admin-text-muted);
    margin: 0;
}

.role-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    margin-top: 0.5rem;
}

.permission-grid {
    display: grid;
    gap: 0.75rem;
}

.permission-item {
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: 6px;
    padding: 0.875rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.permission-item.dangerous {
    border-left: 3px solid #ef4444;
    background: rgba(239, 68, 68, 0.05);
}

.permission-item.direct-grant {
    border-left: 3px solid #3b82f6;
    background: rgba(59, 130, 246, 0.05);
}

.permission-info {
    flex: 1;
}

.permission-name {
    font-weight: 600;
    color: var(--admin-text);
    margin-bottom: 0.125rem;
    font-size: 0.875rem;
    font-family: 'Courier New', monospace;
}

.permission-description {
    font-size: 0.8125rem;
    color: var(--admin-text-muted);
    margin: 0;
}

.permission-source {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    margin-top: 0.25rem;
    font-style: italic;
}

.permission-badges {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.permission-badge {
    padding: 0.25rem 0.625rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-dangerous {
    background: #fee2e2;
    color: #991b1b;
}

.badge-direct {
    background: #dbeafe;
    color: #1e40af;
}

.badge-role {
    background: #f3f4f6;
    color: #374151;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--admin-text-muted);
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.revocation-item {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-left: 3px solid #ef4444;
    border-radius: 6px;
    padding: 0.875rem 1rem;
    margin-bottom: 0.75rem;
}

.category-header {
    font-weight: 600;
    color: var(--admin-text);
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--admin-border);
    text-transform: uppercase;
    font-size: 0.8125rem;
    letter-spacing: 0.05em;
}
</style>

<div class="permissions-header">
    <div class="permissions-user-info">
        <?php if (!empty($user['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" loading="lazy" alt="Avatar" class="permissions-user-avatar">
        <?php else: ?>
            <div class="permissions-user-avatar mte-permissions--avatar-placeholder">
                <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
            </div>
        <?php endif; ?>
        <div class="permissions-user-details">
            <h2><?= htmlspecialchars($user['username'] ?? 'Unknown User') ?></h2>
            <p><?= htmlspecialchars($user['email'] ?? '') ?></p>
            <p class="mte-permissions--user-meta">
                User ID: <?= $userId ?> |
                Role: <strong><?= htmlspecialchars($user['role'] ?? 'user') ?></strong>
            </p>
        </div>
    </div>
    <div class="mte-permissions--header-actions">
        <a href="<?= TenantContext::getBasePath() ?>/admin-legacy/users/<?= $userId ?>" class="admin-btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to User
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="permissions-stats">
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Assigned Roles</span>
            <i class="fas fa-user-tag stat-card-icon"></i>
        </div>
        <div class="stat-card-value"><?= count($userRoles) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Effective Permissions</span>
            <i class="fas fa-key stat-card-icon"></i>
        </div>
        <div class="stat-card-value"><?= $totalEffective ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Dangerous Permissions</span>
            <i class="fas fa-exclamation-triangle stat-card-icon"></i>
        </div>
        <div class="stat-card-value mte-permissions--stat-dangerous"><?= $totalDangerous ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header">
            <span class="stat-card-label">Direct Grants</span>
            <i class="fas fa-plus-circle stat-card-icon"></i>
        </div>
        <div class="stat-card-value"><?= count($directGrants) ?></div>
    </div>
</div>

<!-- Assigned Roles Section -->
<div class="permissions-section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-user-tag"></i>
            Assigned Roles
        </h3>
        <?php if ($canAssignRoles): ?>
            <button class="admin-btn-primary" onclick="openAssignRoleModal()">
                <i class="fas fa-plus"></i> Assign Role
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($userRoles)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-user-tag"></i></div>
            <p>No roles assigned to this user</p>
        </div>
    <?php else: ?>
        <?php foreach ($userRoles as $role): ?>
            <div class="role-card">
                <div class="role-info">
                    <div class="role-name">
                        <span><?= htmlspecialchars($role['display_name']) ?></span>
                        <span class="role-level role-level-<?= $role['level'] >= 80 ? $role['level'] : 'default' ?>">
                            Level <?= $role['level'] ?>
                        </span>
                        <?php if ($role['is_system']): ?>
                            <span class="permission-badge badge-dangerous">System Role</span>
                        <?php endif; ?>
                    </div>
                    <p class="role-description"><?= htmlspecialchars($role['description']) ?></p>
                    <div class="role-meta">
                        <span><i class="fas fa-key"></i> <?= $role['permission_count'] ?? 0 ?> permissions</span>
                        <?php if (!empty($role['expires_at'])): ?>
                            <span><i class="fas fa-clock"></i> Expires: <?= date('M j, Y', strtotime($role['expires_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($canAssignRoles && !$role['is_system']): ?>
                    <button class="admin-btn-danger" onclick="revokeRole(<?= $userId ?>, <?= $role['id'] ?>, '<?= htmlspecialchars($role['display_name']) ?>')">
                        <i class="fas fa-times"></i> Revoke
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Effective Permissions Section -->
<div class="permissions-section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-key"></i>
            Effective Permissions
        </h3>
        <?php if ($canEditPermissions): ?>
            <button class="admin-btn-primary" onclick="openGrantPermissionModal()">
                <i class="fas fa-plus"></i> Grant Permission
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($permsByCategory)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-key"></i></div>
            <p>No permissions granted to this user</p>
        </div>
    <?php else: ?>
        <?php foreach ($permsByCategory as $category => $perms): ?>
            <div class="category-header"><?= htmlspecialchars($category) ?></div>
            <div class="permission-grid">
                <?php foreach ($perms as $perm): ?>
                    <div class="permission-item <?= $perm['is_dangerous'] ? 'dangerous' : '' ?> <?= $perm['source'] === 'direct_grant' ? 'direct-grant' : '' ?>">
                        <div class="permission-info">
                            <div class="permission-name"><?= htmlspecialchars($perm['name']) ?></div>
                            <p class="permission-description"><?= htmlspecialchars($perm['display_name']) ?></p>
                            <div class="permission-source">
                                <?php if ($perm['source'] === 'direct_grant'): ?>
                                    <i class="fas fa-bolt"></i> Direct grant<?= !empty($perm['granted_by_username']) ? ' by ' . htmlspecialchars($perm['granted_by_username']) : '' ?>
                                <?php else: ?>
                                    <i class="fas fa-user-tag"></i> From role: <?= htmlspecialchars($perm['source_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="permission-badges">
                            <?php if ($perm['is_dangerous']): ?>
                                <span class="permission-badge badge-dangerous">
                                    <i class="fas fa-exclamation-triangle"></i> Dangerous
                                </span>
                            <?php endif; ?>
                            <?php if ($perm['source'] === 'direct_grant'): ?>
                                <span class="permission-badge badge-direct">Direct</span>
                            <?php else: ?>
                                <span class="permission-badge badge-role">Role</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Revocations Section -->
<?php if (!empty($revocations)): ?>
<div class="permissions-section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-ban"></i>
            Revoked Permissions
        </h3>
    </div>
    <?php foreach ($revocations as $revocation): ?>
        <div class="revocation-item">
            <div class="permission-name mte-permissions--revocation-name">
                <?= htmlspecialchars($revocation['name']) ?>
            </div>
            <p class="permission-description mte-permissions--revocation-desc">
                <?= htmlspecialchars($revocation['display_name']) ?>
            </p>
            <div class="permission-source mte-permissions--revocation-source">
                <i class="fas fa-user-slash"></i> Revoked by <?= htmlspecialchars($revocation['revoked_by_username'] ?? 'System') ?> on <?= date('M j, Y', strtotime($revocation['created_at'])) ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Assign Role Modal -->
<div id="assignRoleModal" onclick="closeAssignRoleModal()" class="mte-permissions--modal-overlay">
    <div onclick="event.stopPropagation()" class="mte-permissions--modal">
        <div class="mte-permissions--modal-header">
            <h3 class="mte-permissions--modal-title">
                <i class="fas fa-user-tag"></i> Assign Role
            </h3>
            <button onclick="closeAssignRoleModal()" class="mte-permissions--modal-close">&times;</button>
        </div>

        <form id="assignRoleForm" onsubmit="event.preventDefault(); submitAssignRole();">
            <div class="mte-permissions--form-group">
                <label class="mte-permissions--label">
                    Select Role <span class="mte-permissions--required">*</span>
                </label>
                <select id="selectRole" required class="mte-permissions--select">
                    <option value="">-- Choose a role --</option>
                    <?php
                    $assignedRoleIds = array_column($userRoles, 'id');
                    foreach ($allRoles as $role):
                        if (in_array($role['id'], $assignedRoleIds)) continue;
                    ?>
                        <option value="<?= $role['id'] ?>">
                            <?= htmlspecialchars($role['display_name']) ?> (Level <?= $role['level'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="mte-permissions--hint">
                    Only showing roles not already assigned to this user
                </small>
            </div>

            <div class="mte-permissions--form-group">
                <label class="mte-permissions--label">
                    Expires At (Optional)
                </label>
                <input type="datetime-local" id="roleExpiresAt" class="mte-permissions--input">
                <small class="mte-permissions--hint">
                    Leave empty for permanent assignment
                </small>
            </div>

            <div class="mte-permissions--modal-footer">
                <button type="button" onclick="closeAssignRoleModal()" class="admin-btn admin-btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fas fa-check"></i> Assign Role
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Grant Permission Modal -->
<div id="grantPermissionModal" onclick="closeGrantPermissionModal()" class="mte-permissions--modal-overlay">
    <div onclick="event.stopPropagation()" class="mte-permissions--modal mte-permissions--modal-lg">
        <div class="mte-permissions--modal-header">
            <h3 class="mte-permissions--modal-title">
                <i class="fas fa-key"></i> Grant Permission
            </h3>
            <button onclick="closeGrantPermissionModal()" class="mte-permissions--modal-close">&times;</button>
        </div>

        <form id="grantPermissionForm" onsubmit="event.preventDefault(); submitGrantPermission();">
            <div class="mte-permissions--form-group-sm">
                <label class="mte-permissions--label">
                    Search Permissions
                </label>
                <input type="text" id="permissionSearch" placeholder="Search by name or description..." oninput="filterPermissions()" class="mte-permissions--input">
            </div>

            <div class="mte-permissions--form-group">
                <label class="mte-permissions--label">
                    Filter by Category
                </label>
                <select id="permissionCategory" onchange="filterPermissions()" class="mte-permissions--select">
                    <option value="">All Categories</option>
                    <?php
                    // $allPermissions is already grouped by category, so keys are category names
                    foreach (array_keys($allPermissions) as $cat):
                    ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= ucfirst(htmlspecialchars($cat)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mte-permissions--form-group">
                <label class="mte-permissions--label">
                    Select Permission <span class="mte-permissions--required">*</span>
                </label>
                <div id="permissionListContainer" class="mte-permissions--list-container">
                    <?php
                    // $allPermissions is already grouped by category from getAllPermissions()
                    // Format: ['users' => [perm1, perm2], 'content' => [perm3, perm4]]
                    foreach ($allPermissions as $category => $perms):
                    ?>
                        <div class="permission-group">
                            <div class="mte-permissions--group-header">
                                <?= ucfirst(htmlspecialchars($category)) ?>
                            </div>
                            <?php foreach ($perms as $perm): ?>
                                <label class="permission-option mte-permissions--option" data-category="<?= htmlspecialchars($category) ?>" data-name="<?= htmlspecialchars($perm['name']) ?>" data-display="<?= htmlspecialchars($perm['display_name']) ?>">
                                    <input type="radio" name="permission" value="<?= $perm['id'] ?>" class="mte-permissions--option-radio">
                                    <div class="mte-permissions--option-content">
                                        <div class="mte-permissions--option-title">
                                            <?= htmlspecialchars($perm['display_name']) ?>
                                            <?php if ($perm['is_dangerous']): ?>
                                                <span class="mte-permissions--option-dangerous">⚠️</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mte-permissions--option-code">
                                            <?= htmlspecialchars($perm['name']) ?>
                                        </div>
                                        <div class="mte-permissions--option-desc">
                                            <?= htmlspecialchars($perm['description']) ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="selectPermission" required>
            </div>

            <div class="mte-permissions--form-group">
                <label class="mte-permissions--label">
                    Reason <span class="mte-permissions--required">*</span>
                </label>
                <textarea id="permReason" rows="3" required placeholder="Why is this permission being granted?" class="mte-permissions--textarea"></textarea>
                <small class="mte-permissions--hint">
                    This will be recorded in the audit log
                </small>
            </div>

            <div class="mte-permissions--form-group">
                <label class="mte-permissions--label">
                    Expires At (Optional)
                </label>
                <input type="datetime-local" id="permExpiresAt" class="mte-permissions--input">
                <small class="mte-permissions--hint">
                    Leave empty for permanent grant
                </small>
            </div>

            <div class="mte-permissions--modal-footer">
                <button type="button" onclick="closeGrantPermissionModal()" class="admin-btn admin-btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fas fa-check"></i> Grant Permission
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAssignRoleModal() {
    document.getElementById('assignRoleModal').dataset.open = 'true';
}

function closeAssignRoleModal() {
    document.getElementById('assignRoleModal').dataset.open = 'false';
    document.getElementById('assignRoleForm').reset();
}

function openGrantPermissionModal() {
    document.getElementById('grantPermissionModal').dataset.open = 'true';
}

function closeGrantPermissionModal() {
    document.getElementById('grantPermissionModal').dataset.open = 'false';
    document.getElementById('grantPermissionForm').reset();
}

async function submitAssignRole() {
    const roleId = document.getElementById('selectRole').value;
    const expiresAt = document.getElementById('roleExpiresAt').value;
    const submitBtn = event.target.querySelector('button[type="submit"]');

    if (!roleId) {
        alert('Please select a role');
        return;
    }

    const data = { role_id: parseInt(roleId) };
    if (expiresAt) {
        data.expires_at = expiresAt;
    }

    // Disable button and show loading
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';

    console.log('Assigning role:', data);

    try {
        const url = `<?= TenantContext::getBasePath() ?>/admin-legacy/api/users/<?= $userId ?>/roles`;
        console.log('POST to:', url);

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('Response data:', result);

        if (response.ok && result.success) {
            alert('✅ Role assigned successfully!');
            closeAssignRoleModal();
            location.reload();
        } else {
            alert('❌ Error: ' + (result.error || 'Failed to assign role'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Fetch error:', error);
        alert('❌ Network error: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

async function submitGrantPermission() {
    // Get selected permission from radio buttons
    const selectedRadio = document.querySelector('input[name="permission"]:checked');
    const permissionId = selectedRadio ? selectedRadio.value : null;

    const expiresAt = document.getElementById('permExpiresAt').value;
    const reason = document.getElementById('permReason').value;
    const submitBtn = event.target.querySelector('button[type="submit"]');

    if (!permissionId) {
        alert('Please select a permission');
        return;
    }

    // Update hidden field for form validation
    document.getElementById('selectPermission').value = permissionId;

    if (!reason || reason.trim() === '') {
        alert('Please provide a reason for granting this permission');
        return;
    }

    const data = {
        permission_id: parseInt(permissionId),
        reason: reason.trim()
    };
    if (expiresAt) {
        data.expires_at = expiresAt;
    }

    // Disable button and show loading
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Granting...';

    console.log('Granting permission:', data);

    try {
        const url = `<?= TenantContext::getBasePath() ?>/admin-legacy/api/users/<?= $userId ?>/permissions`;
        console.log('POST to:', url);

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('Response data:', result);

        if (response.ok && result.success) {
            alert('✅ Permission granted successfully!');
            closeGrantPermissionModal();
            location.reload();
        } else {
            alert('❌ Error: ' + (result.error || 'Failed to grant permission'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Fetch error:', error);
        alert('❌ Network error: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

function filterPermissions() {
    const searchTerm = document.getElementById('permissionSearch').value.toLowerCase();
    const category = document.getElementById('permissionCategory').value;
    const options = document.querySelectorAll('.permission-option');
    const groups = document.querySelectorAll('.permission-group');

    options.forEach(option => {
        const text = option.textContent.toLowerCase();
        const optCategory = option.dataset.category;

        const matchesSearch = !searchTerm || text.includes(searchTerm);
        const matchesCategory = !category || optCategory === category;

        if (matchesSearch && matchesCategory) {
            option.classList.remove('hidden');
        } else {
            option.classList.add('hidden');
        }
    });

    // Hide empty groups
    groups.forEach(group => {
        const visibleOptions = group.querySelectorAll('.permission-option:not(.hidden)');
        if (visibleOptions.length === 0) {
            group.classList.add('hidden');
        } else {
            group.classList.remove('hidden');
        }
    });
}

async function revokeRole(userId, roleId, roleName) {
    if (!confirm(`Are you sure you want to revoke the "${roleName}" role from this user?`)) {
        return;
    }

    try {
        const response = await fetch(`<?= TenantContext::getBasePath() ?>/admin-legacy/api/users/${userId}/roles/${roleId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const result = await response.json();

        if (response.ok) {
            alert('Role revoked successfully');
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Failed to revoke role'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to revoke role');
    }
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
