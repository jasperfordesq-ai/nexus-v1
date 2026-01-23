<?php
/**
 * Group Type Create/Edit Form - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\GroupType;

// Admin check
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ' . TenantContext::getBasePath() . '/');
    exit;
}

$tenantId = TenantContext::getId();
$basePath = TenantContext::getBasePath();

// Determine if editing or creating
// Check for ID from controller data or GET parameter
$editId = $editId ?? $_GET['id'] ?? null;
$isEdit = !empty($editId);
$typeId = $isEdit ? (int)$editId : null;
$type = $isEdit ? GroupType::findById($typeId) : null;

if ($isEdit && !$type) {
    header('Location: ' . $basePath . '/admin/group-types');
    exit;
}

// Handle form submission
$message = '';
$messageType = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!\Nexus\Core\Csrf::verify()) {
        $errors[] = "Invalid request. Please refresh and try again.";
    }

    // Validation
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-layer-group');
    $color = trim($_POST['color'] ?? '#6366f1');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isHub = isset($_POST['is_hub']) ? 1 : 0;

    if (empty($name)) {
        $errors[] = "Name is required";
    }

    if (empty($slug)) {
        $slug = GroupType::generateSlug($name);
    } elseif (!GroupType::isSlugUnique($slug, $typeId)) {
        $errors[] = "Slug is already in use";
    }

    if (empty($errors)) {
        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'icon' => $icon,
            'color' => $color,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'is_hub' => $isHub
        ];

        try {
            if ($isEdit) {
                GroupType::update($typeId, $data);
                $message = "Group type updated successfully";
            } else {
                $typeId = GroupType::create($data);
                $message = "Group type created successfully";
            }
            $messageType = 'success';

            // Refresh type data
            $type = GroupType::findById($typeId);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Populate form with existing data if editing
$formData = [
    'name' => $_POST['name'] ?? ($type['name'] ?? ''),
    'slug' => $_POST['slug'] ?? ($type['slug'] ?? ''),
    'description' => $_POST['description'] ?? ($type['description'] ?? ''),
    'icon' => $_POST['icon'] ?? ($type['icon'] ?? 'fa-layer-group'),
    'color' => $_POST['color'] ?? ($type['color'] ?? '#6366f1'),
    'sort_order' => $_POST['sort_order'] ?? ($type['sort_order'] ?? 0),
    'is_active' => isset($_POST['is_active']) ? true : ($type['is_active'] ?? true),
    'is_hub' => isset($_POST['is_hub']) ? true : ($type['is_hub'] ?? false),
];

// Popular icon options
$iconOptions = [
    'fa-layer-group' => 'Default',
    'fa-users' => 'Community',
    'fa-hands-helping' => 'Volunteering',
    'fa-futbol' => 'Sports',
    'fa-palette' => 'Arts',
    'fa-graduation-cap' => 'Education',
    'fa-leaf' => 'Environment',
    'fa-star' => 'Hobbies',
    'fa-briefcase' => 'Business',
    'fa-heart' => 'Social',
    'fa-book' => 'Learning',
    'fa-music' => 'Music',
    'fa-camera' => 'Photography',
    'fa-utensils' => 'Food & Drink',
    'fa-dumbbell' => 'Fitness',
    'fa-gamepad' => 'Gaming',
    'fa-code' => 'Technology',
    'fa-theater-masks' => 'Theater',
];

// Color presets
$colorPresets = [
    '#6366f1' => 'Indigo',
    '#3b82f6' => 'Blue',
    '#06b6d4' => 'Cyan',
    '#22c55e' => 'Green',
    '#10b981' => 'Emerald',
    '#f59e0b' => 'Amber',
    '#f97316' => 'Orange',
    '#ef4444' => 'Red',
    '#ec4899' => 'Pink',
    '#8b5cf6' => 'Purple',
];

// Admin header configuration
$adminPageTitle = $isEdit ? 'Edit Group Type' : 'Create Group Type';
$adminPageSubtitle = 'Community';
$adminPageIcon = 'fa-layer-group';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-layer-group"></i>
            <?= $isEdit ? 'Edit' : 'Create' ?> Group Type
        </h1>
        <p class="admin-page-subtitle">
            <?= $isEdit ? 'Update group type details' : 'Add a new group category' ?>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/group-types" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="admin-alert admin-alert-error">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-times-circle"></i>
    </div>
    <div class="admin-alert-content">
        <strong>Please fix the following errors:</strong>
        <ul style="margin: 0.5rem 0 0 1.5rem;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if ($message): ?>
<div class="admin-alert admin-alert-<?= $messageType ?>">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-check-circle"></i>
    </div>
    <div class="admin-alert-content">
        <?= htmlspecialchars($message) ?>
    </div>
</div>
<?php endif; ?>

<div class="admin-form-layout">
    <!-- Main Form -->
    <div class="admin-form-main">
        <form method="POST" id="typeForm">
            <?= \Nexus\Core\Csrf::field() ?>
            <!-- Basic Information -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-blue">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Basic Information</h3>
                        <p class="admin-card-subtitle">Core details for this group type</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-form-group">
                        <label class="admin-label" for="name">
                            Type Name <span class="admin-required">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               class="admin-input"
                               value="<?= htmlspecialchars($formData['name']) ?>"
                               required
                               placeholder="e.g., Community, Sports, Arts & Culture">
                        <span class="admin-help-text">The display name for this group type</span>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-label" for="slug">
                            URL Slug <span class="admin-required">*</span>
                        </label>
                        <input type="text"
                               id="slug"
                               name="slug"
                               class="admin-input"
                               value="<?= htmlspecialchars($formData['slug']) ?>"
                               pattern="[a-z0-9-]+"
                               placeholder="e.g., community, sports-recreation">
                        <span class="admin-help-text">URL-friendly identifier (lowercase letters, numbers, and hyphens only)</span>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-label" for="description">
                            Description
                        </label>
                        <textarea id="description"
                                  name="description"
                                  class="admin-textarea"
                                  rows="4"
                                  placeholder="Brief description of this group type..."><?= htmlspecialchars($formData['description']) ?></textarea>
                        <span class="admin-help-text">Help users understand what types of groups belong in this category</span>
                    </div>
                </div>
            </div>

            <!-- Appearance -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-purple">
                        <i class="fa-solid fa-palette"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Appearance</h3>
                        <p class="admin-card-subtitle">Visual styling for this type</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-form-group">
                        <label class="admin-label" for="icon">
                            Icon
                        </label>
                        <select id="icon" name="icon" class="admin-select">
                            <?php foreach ($iconOptions as $iconClass => $iconLabel): ?>
                                <option value="<?= $iconClass ?>"
                                        <?= $formData['icon'] === $iconClass ? 'selected' : '' ?>>
                                    <?= $iconLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="icon-preview">
                            <div class="icon-preview-badge" style="background: <?= htmlspecialchars($formData['color']) ?>;">
                                <i class="fa-solid <?= htmlspecialchars($formData['icon']) ?>"></i>
                            </div>
                            <span class="icon-preview-text">Preview</span>
                        </div>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-label" for="color">
                            Color
                        </label>
                        <div class="color-picker-wrapper">
                            <input type="color"
                                   id="color"
                                   name="color"
                                   class="admin-color-input"
                                   value="<?= htmlspecialchars($formData['color']) ?>">
                            <input type="text"
                                   id="color-hex"
                                   class="admin-input"
                                   value="<?= htmlspecialchars($formData['color']) ?>"
                                   pattern="#[0-9a-fA-F]{6}"
                                   placeholder="#6366f1">
                        </div>
                        <div class="color-presets">
                            <?php foreach ($colorPresets as $preset => $label): ?>
                                <button type="button"
                                        class="color-preset"
                                        style="background: <?= $preset ?>;"
                                        data-color="<?= $preset ?>"
                                        title="<?= $label ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-green">
                        <i class="fa-solid fa-cog"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Settings</h3>
                        <p class="admin-card-subtitle">Configuration options</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="admin-form-group">
                        <label class="admin-label" for="sort_order">
                            Sort Order
                        </label>
                        <input type="number"
                               id="sort_order"
                               name="sort_order"
                               class="admin-input"
                               value="<?= htmlspecialchars($formData['sort_order']) ?>"
                               min="0"
                               step="10">
                        <span class="admin-help-text">Lower numbers appear first (use multiples of 10 for flexibility)</span>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-checkbox">
                            <input type="checkbox"
                                   name="is_active"
                                   <?= $formData['is_active'] ? 'checked' : '' ?>>
                            <span class="admin-checkbox-label">Active</span>
                        </label>
                        <span class="admin-help-text">Only active types are shown to users</span>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-checkbox">
                            <input type="checkbox"
                                   name="is_hub"
                                   <?= $formData['is_hub'] ? 'checked' : '' ?>>
                            <span class="admin-checkbox-label">Hub Type (Admin Only)</span>
                        </label>
                        <span class="admin-help-text">Hub types are for admin-curated geographic communities. Only administrators can create groups of hub types.</span>
                    </div>
                </div>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-save"></i>
                    <?= $isEdit ? 'Update' : 'Create' ?> Type
                </button>
                <a href="<?= $basePath ?>/admin/group-types" class="admin-btn admin-btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Sidebar -->
    <div class="admin-form-sidebar">
        <?php if ($isEdit && $type): ?>
        <!-- Statistics -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-cyan">
                    <i class="fa-solid fa-chart-bar"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Statistics</h3>
                </div>
            </div>
            <div class="admin-card-body">
                <?php $stats = GroupType::getStats($typeId); ?>
                <div class="sidebar-stat">
                    <div class="sidebar-stat-label">Total Groups</div>
                    <div class="sidebar-stat-value"><?= $stats['total_groups'] ?></div>
                </div>
                <div class="sidebar-stat">
                    <div class="sidebar-stat-label">Total Members</div>
                    <div class="sidebar-stat-value"><?= $stats['total_members'] ?></div>
                </div>
                <div class="sidebar-stat">
                    <div class="sidebar-stat-label">Public</div>
                    <div class="sidebar-stat-value"><?= $stats['public_groups'] ?></div>
                </div>
                <div class="sidebar-stat">
                    <div class="sidebar-stat-label">Private</div>
                    <div class="sidebar-stat-value"><?= $stats['private_groups'] ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Help -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-orange">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Tips</h3>
                </div>
            </div>
            <div class="admin-card-body">
                <ul class="help-list">
                    <li>Choose clear, descriptive names that users will recognize</li>
                    <li>Use consistent icons that represent the category well</li>
                    <li>Colors help users quickly identify types at a glance</li>
                    <li>Sort order determines display order in lists and filters</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
/* Form Layout */
.admin-form-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 1.5rem;
    align-items: start;
}

.admin-form-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.admin-form-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    position: sticky;
    top: 1rem;
}

@media (max-width: 1024px) {
    .admin-form-layout {
        grid-template-columns: 1fr;
    }
    .admin-form-sidebar {
        position: static;
    }
}

/* Form Elements */
.admin-form-group {
    margin-bottom: 1.5rem;
}

.admin-form-group:last-child {
    margin-bottom: 0;
}

.admin-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.9rem;
}

.admin-required {
    color: #ef4444;
}

.admin-input,
.admin-textarea,
.admin-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.8);
    color: #fff;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.admin-input:focus,
.admin-textarea:focus,
.admin-select:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.admin-help-text {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Icon Preview */
.icon-preview {
    margin-top: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.icon-preview-badge {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    transition: all 0.3s;
}

.icon-preview-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

/* Color Picker */
.color-picker-wrapper {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.admin-color-input {
    width: 60px;
    height: 45px;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 8px;
    cursor: pointer;
    background: rgba(15, 23, 42, 0.8);
}

.color-picker-wrapper .admin-input {
    flex: 1;
}

.color-presets {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
    flex-wrap: wrap;
}

.color-preset {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
    transition: all 0.2s;
}

.color-preset:hover {
    transform: scale(1.1);
    border-color: rgba(255, 255, 255, 0.3);
}

/* Checkbox */
.admin-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.admin-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.admin-checkbox-label {
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}

/* Form Actions */
.admin-form-actions {
    display: flex;
    gap: 1rem;
    padding-top: 1rem;
}

/* Sidebar Stats */
.sidebar-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.sidebar-stat:last-child {
    border-bottom: none;
}

.sidebar-stat-label {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
}

.sidebar-stat-value {
    color: #fff;
    font-weight: 700;
    font-size: 1.1rem;
}

/* Help List */
.help-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.help-list li {
    padding: 0.5rem 0;
    padding-left: 1.5rem;
    position: relative;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
    line-height: 1.5;
}

.help-list li::before {
    content: '→';
    position: absolute;
    left: 0;
    color: #06b6d4;
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.admin-alert-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
    margin-top: 0.1rem;
}
</style>

<script>
// Auto-generate slug from name
document.getElementById('name').addEventListener('input', function() {
    const slug = this.value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    document.getElementById('slug').value = slug;
});

// Icon preview update
document.getElementById('icon').addEventListener('change', function() {
    const iconClass = this.value;
    const preview = document.querySelector('.icon-preview-badge i');
    preview.className = 'fa-solid ' + iconClass;
});

// Color picker sync
const colorInput = document.getElementById('color');
const colorHex = document.getElementById('color-hex');
const iconPreview = document.querySelector('.icon-preview-badge');

colorInput.addEventListener('input', function() {
    colorHex.value = this.value;
    iconPreview.style.background = this.value;
});

colorHex.addEventListener('input', function() {
    if (/^#[0-9A-F]{6}$/i.test(this.value)) {
        colorInput.value = this.value;
        iconPreview.style.background = this.value;
    }
});

// Color presets
document.querySelectorAll('.color-preset').forEach(button => {
    button.addEventListener('click', function() {
        const color = this.dataset.color;
        colorInput.value = color;
        colorHex.value = color;
        iconPreview.style.background = color;
    });
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
