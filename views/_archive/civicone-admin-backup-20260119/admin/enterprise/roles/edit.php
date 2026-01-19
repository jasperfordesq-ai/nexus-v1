<?php
/**
 * Edit Role - Gold Standard v2.0 Admin Interface
 * STANDALONE admin interface for editing existing roles
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Database;
use Nexus\Services\Enterprise\PermissionService;

$basePath = TenantContext::getBasePath();
$db = Database::getInstance();
$permService = new PermissionService();

// Admin header configuration
$adminPageTitle = 'Edit Role';
$adminPageSubtitle = 'Enterprise Access Control';
$adminPageIcon = 'fa-user-pen';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Get role data (passed from controller)
if (!isset($role)) {
    echo '<div class="admin-glass-card"><p>Role not found</p></div>';
    require dirname(__DIR__, 2) . '/partials/admin-footer.php';
    exit;
}

// Get current role permissions
$stmt = $db->prepare("
    SELECT permission_id
    FROM role_permissions
    WHERE role_id = ?
");
$stmt->execute([$role['id']]);
$currentPermissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all permissions grouped by category
$stmt = $db->query("
    SELECT * FROM permissions
    ORDER BY category, name
");
$allPermissions = $stmt->fetchAll();

// Group by category
$permissionsByCategory = [];
foreach ($allPermissions as $perm) {
    $category = $perm['category'];
    if (!isset($permissionsByCategory[$category])) {
        $permissionsByCategory[$category] = [];
    }
    $permissionsByCategory[$category][] = $perm;
}
ksort($permissionsByCategory);

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

<style>
/* Form Styles - Gold Standard v2.0 */
.form-section {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 28px;
    margin-bottom: 24px;
}

.form-section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid rgba(99, 102, 241, 0.2);
}

.form-section-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.form-section-title {
    flex: 1;
}

.form-section-title h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 4px 0;
}

.form-section-title p {
    font-size: 0.85rem;
    color: #94a3b8;
    margin: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-label .required {
    color: #ef4444;
}

.form-input,
.form-textarea,
.form-select {
    padding: 12px 16px;
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 10px;
    font-size: 0.95rem;
    color: #f1f5f9;
    transition: all 0.3s;
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.form-textarea {
    min-height: 100px;
    resize: vertical;
    font-family: inherit;
}

.form-hint {
    font-size: 0.75rem;
    color: #94a3b8;
}

.system-role-notice {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.system-role-notice i {
    font-size: 1.5rem;
    color: #10b981;
}

.system-role-notice-content {
    flex: 1;
}

.system-role-notice-title {
    font-weight: 700;
    color: #10b981;
    margin-bottom: 4px;
}

.system-role-notice-text {
    font-size: 0.875rem;
    color: #94a3b8;
}

/* Permission Selection */
.permission-categories {
    display: grid;
    gap: 16px;
}

.permission-category {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
}

.permission-category:hover {
    background: rgba(30, 41, 59, 0.7);
    border-color: rgba(99, 102, 241, 0.3);
}

.category-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.category-title-area {
    display: flex;
    align-items: center;
    gap: 12px;
}

.category-icon-small {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.category-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: #f1f5f9;
    text-transform: capitalize;
}

.select-all-category {
    padding: 6px 12px;
    background: rgba(99, 102, 241, 0.2);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #818cf8;
    cursor: pointer;
    transition: all 0.2s;
}

.select-all-category:hover {
    background: rgba(99, 102, 241, 0.3);
}

.permissions-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

.permission-checkbox-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.permission-checkbox-item:hover {
    background: rgba(15, 23, 42, 0.7);
    border-color: rgba(99, 102, 241, 0.3);
}

.permission-checkbox-item.checked {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.4);
}

.permission-checkbox {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    cursor: pointer;
}

.permission-info {
    flex: 1;
    min-width: 0;
}

.permission-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #f1f5f9;
    margin-bottom: 4px;
}

.permission-slug {
    font-family: 'Courier New', monospace;
    font-size: 0.7rem;
    color: #64748b;
    margin-bottom: 4px;
}

.permission-desc {
    font-size: 0.75rem;
    color: #94a3b8;
    line-height: 1.4;
}

.dangerous-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 24px;
    border-top: 2px solid rgba(99, 102, 241, 0.2);
}

.btn-update {
    padding: 14px 32px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-update:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
}

.btn-update:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-cancel {
    padding: 14px 32px;
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(99, 102, 241, 0.3);
    color: #f1f5f9;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-cancel:hover {
    background: rgba(30, 41, 59, 0.9);
    border-color: rgba(99, 102, 241, 0.5);
}

.selected-count {
    background: rgba(99, 102, 241, 0.2);
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #818cf8;
}
</style>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-user-pen"></i>
            Edit Role: <?= htmlspecialchars($role['display_name']) ?>
        </h1>
        <p class="admin-page-subtitle">Modify role details and permissions</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/enterprise/roles/<?= $role['id'] ?>" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-eye"></i> View Role
        </a>
        <a href="<?= $basePath ?>/admin/enterprise/roles" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Roles
        </a>
    </div>
</div>

<?php if ($role['is_system']): ?>
<div class="system-role-notice">
    <i class="fa-solid fa-shield-halved"></i>
    <div class="system-role-notice-content">
        <div class="system-role-notice-title">System Role - Protected</div>
        <div class="system-role-notice-text">
            This is a system role. The name and slug cannot be modified, but you can update the description and permissions.
        </div>
    </div>
</div>
<?php endif; ?>

<form id="editRoleForm">
    <!-- Basic Information -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="form-section-icon">
                <i class="fa-solid fa-info-circle"></i>
            </div>
            <div class="form-section-title">
                <h3>Basic Information</h3>
                <p>Update the role details</p>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">
                    Display Name <span class="required">*</span>
                </label>
                <input
                    type="text"
                    name="display_name"
                    class="form-input"
                    value="<?= htmlspecialchars($role['display_name']) ?>"
                    required
                >
                <span class="form-hint">Human-readable name shown to users</span>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Slug <span class="required">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    class="form-input"
                    value="<?= htmlspecialchars($role['name']) ?>"
                    <?= $role['is_system'] ? 'disabled' : '' ?>
                    pattern="[a-z0-9_]+"
                    title="Lowercase letters, numbers, and underscores only"
                >
                <span class="form-hint">
                    <?= $role['is_system'] ? 'Cannot be changed (system role)' : 'Unique identifier (lowercase, underscores)' ?>
                </span>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Role Level <span class="required">*</span>
                </label>
                <input
                    type="number"
                    name="level"
                    class="form-input"
                    value="<?= $role['level'] ?>"
                    min="0"
                    max="1000"
                    required
                >
                <span class="form-hint">Higher levels have more authority (0-1000)</span>
            </div>

            <div class="form-group full-width">
                <label class="form-label">
                    Description <span class="required">*</span>
                </label>
                <textarea
                    name="description"
                    class="form-textarea"
                    required
                ><?= htmlspecialchars($role['description']) ?></textarea>
                <span class="form-hint">Explain what this role can do and why it exists</span>
            </div>
        </div>
    </div>

    <!-- Permissions Selection -->
    <div class="form-section">
        <div class="form-section-header">
            <div class="form-section-icon">
                <i class="fa-solid fa-key"></i>
            </div>
            <div class="form-section-title">
                <h3>Permissions</h3>
                <p>Modify which permissions this role should have</p>
            </div>
            <div class="selected-count">
                <i class="fa-solid fa-check-circle"></i>
                <span id="selectedCount"><?= count($currentPermissionIds) ?></span> selected
            </div>
        </div>

        <div class="permission-categories">
            <?php foreach ($permissionsByCategory as $category => $permissions): ?>
                <div class="permission-category">
                    <div class="category-header-row">
                        <div class="category-title-area">
                            <div class="category-icon-small">
                                <i class="fa-solid fa-<?= getCategoryIcon($category) ?>"></i>
                            </div>
                            <span class="category-name"><?= htmlspecialchars($category) ?></span>
                        </div>
                        <button
                            type="button"
                            class="select-all-category"
                            onclick="toggleCategory('<?= htmlspecialchars($category) ?>')"
                        >
                            <i class="fa-solid fa-check-double"></i> Toggle All
                        </button>
                    </div>

                    <div class="permissions-list">
                        <?php foreach ($permissions as $perm): ?>
                            <?php $isChecked = in_array($perm['id'], $currentPermissionIds); ?>
                            <label class="permission-checkbox-item <?= $isChecked ? 'checked' : '' ?>" data-category="<?= htmlspecialchars($category) ?>">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="<?= $perm['id'] ?>"
                                    class="permission-checkbox"
                                    data-category="<?= htmlspecialchars($category) ?>"
                                    <?= $isChecked ? 'checked' : '' ?>
                                    onchange="updateSelectedCount()"
                                >
                                <div class="permission-info">
                                    <div class="permission-label">
                                        <?= htmlspecialchars($perm['display_name']) ?>
                                        <?php if ($perm['is_dangerous']): ?>
                                            <span class="dangerous-badge">
                                                <i class="fa-solid fa-exclamation-triangle"></i> Dangerous
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="permission-slug"><?= htmlspecialchars($perm['name']) ?></div>
                                    <div class="permission-desc"><?= htmlspecialchars($perm['description']) ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="form-section">
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="window.location.href='<?= $basePath ?>/admin/enterprise/roles/<?= $role['id'] ?>'">
                <i class="fa-solid fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn-update">
                <i class="fa-solid fa-save"></i> Update Role
            </button>
        </div>
    </div>
</form>

<script>
// Update checkbox item styling
document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const item = this.closest('.permission-checkbox-item');
        if (this.checked) {
            item.classList.add('checked');
        } else {
            item.classList.remove('checked');
        }
    });
});

// Update selected count
function updateSelectedCount() {
    const count = document.querySelectorAll('.permission-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

// Toggle all permissions in a category
function toggleCategory(category) {
    const checkboxes = document.querySelectorAll(`.permission-checkbox[data-category="${category}"]`);
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);

    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
        checkbox.dispatchEvent(new Event('change'));
    });

    updateSelectedCount();
}

// Form submission
document.getElementById('editRoleForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = {
        display_name: formData.get('display_name'),
        description: formData.get('description'),
        level: parseInt(formData.get('level')),
        permission_ids: Array.from(formData.getAll('permissions[]')).map(id => parseInt(id))
    };

    // Only include name if not a system role
    <?php if (!$role['is_system']): ?>
    data.name = formData.get('name');
    <?php endif; ?>

    // Disable submit button
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';

    try {
        const response = await fetch('<?= $basePath ?>/admin/enterprise/roles/<?= $role['id'] ?>', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            if (window.AdminRealTime) {
                AdminRealTime.showToast('Role updated successfully!', 'success');
            } else {
                alert('Role updated successfully!');
            }

            // Redirect to role detail page
            setTimeout(() => {
                window.location.href = '<?= $basePath ?>/admin/enterprise/roles/<?= $role['id'] ?>';
            }, 1000);
        } else {
            throw new Error(result.error || 'Failed to update role');
        }
    } catch (error) {
        console.error('Error updating role:', error);

        if (window.AdminRealTime) {
            AdminRealTime.showToast(error.message || 'Failed to update role', 'error');
        } else {
            alert('Error: ' + (error.message || 'Failed to update role'));
        }

        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-save"></i> Update Role';
    }
});
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
