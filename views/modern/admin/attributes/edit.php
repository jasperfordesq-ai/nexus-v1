<?php
/**
 * Admin Edit Attribute - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Edit Attribute';
$adminPageSubtitle = 'Attributes';
$adminPageIcon = 'fa-tag';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$categories = $categories ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/attributes" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Edit Attribute
        </h1>
        <p class="admin-page-subtitle">Update settings for "<?= htmlspecialchars($attribute['name']) ?>"</p>
    </div>
</div>

<!-- Edit Form Card -->
<div class="admin-glass-card" style="max-width: 700px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #14b8a6, #0d9488);">
            <i class="fa-solid fa-tag"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Attribute Details</h3>
            <p class="admin-card-subtitle">Modify the attribute configuration</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin-legacy/attributes/update" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <input type="hidden" name="id" value="<?= $attribute['id'] ?>">

            <div class="form-group">
                <label for="name">Attribute Name *</label>
                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($attribute['name']) ?>">
                <small>Displayed label for the checkbox or badge</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category_id">Scope (Category)</label>
                    <select id="category_id" name="category_id">
                        <option value="">Global (All listings)</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $attribute['category_id'] ? 'selected' : '' ?>>
                            Only for: <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Limit this attribute to a specific category</small>
                </div>

                <div class="form-group">
                    <label for="input_type">Input Type</label>
                    <select id="input_type" name="input_type">
                        <option value="checkbox" <?= ($attribute['input_type'] ?? 'checkbox') == 'checkbox' ? 'selected' : '' ?>>Checkbox (Yes/No)</option>
                    </select>
                    <small>How users will select this attribute</small>
                </div>
            </div>

            <!-- Active Status Toggle -->
            <div class="status-toggle-container">
                <label class="status-toggle">
                    <input type="checkbox" name="is_active" value="1" <?= $attribute['is_active'] ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                    <span class="toggle-label">Active Status</span>
                </label>
                <small>Inactive attributes are hidden from forms</small>
            </div>

            <!-- Preview Box -->
            <div class="attribute-preview-container">
                <label>Preview</label>
                <div class="attribute-preview">
                    <div class="preview-checkbox">
                        <input type="checkbox" id="previewCheck" checked disabled>
                        <span id="previewLabel"><?= htmlspecialchars($attribute['name']) ?></span>
                    </div>
                    <div class="preview-badge">
                        <span class="attr-badge" id="previewBadge">
                            <i class="fa-solid fa-check"></i> <?= htmlspecialchars($attribute['name']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= $basePath ?>/admin-legacy/attributes" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-check"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #fff;
}

.form-group input[type="text"],
.form-group select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 1rem;
    transition: all 0.2s;
}

.form-group input[type="text"]:focus,
.form-group select:focus {
    outline: none;
    border-color: #14b8a6;
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.2);
}

.form-group input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.form-group select option {
    background: #1e293b;
    color: #fff;
}

.form-group small {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

/* Status Toggle */
.status-toggle-container {
    margin-bottom: 1.5rem;
    padding: 1.25rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
}

.status-toggle {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.status-toggle input[type="checkbox"] {
    display: none;
}

.toggle-slider {
    position: relative;
    width: 48px;
    height: 26px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 9999px;
    transition: all 0.3s;
}

.toggle-slider::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    background: #fff;
    border-radius: 50%;
    transition: all 0.3s;
}

.status-toggle input:checked + .toggle-slider {
    background: #14b8a6;
}

.status-toggle input:checked + .toggle-slider::after {
    transform: translateX(22px);
}

.toggle-label {
    font-weight: 600;
    color: #fff;
}

.status-toggle-container small {
    display: block;
    margin-top: 0.5rem;
    margin-left: calc(48px + 0.75rem);
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.attribute-preview-container {
    margin-bottom: 2rem;
    padding: 1.25rem;
    background: rgba(20, 184, 166, 0.08);
    border: 1px solid rgba(20, 184, 166, 0.15);
    border-radius: 0.75rem;
}

.attribute-preview-container > label {
    display: block;
    margin-bottom: 1rem;
    font-weight: 600;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.attribute-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    align-items: center;
}

.preview-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 0.5rem;
}

.preview-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #14b8a6;
}

.preview-checkbox span {
    color: #fff;
    font-weight: 500;
}

.preview-badge {
    display: flex;
    align-items: center;
}

.attr-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    background: rgba(20, 184, 166, 0.15);
    color: #14b8a6;
    border: 1px solid rgba(20, 184, 166, 0.25);
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
}

.attr-badge i {
    font-size: 0.7rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.form-actions .admin-btn {
    flex: 1;
    justify-content: center;
}

.form-actions .admin-btn-primary {
    flex: 2;
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }

    .attribute-preview {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .admin-btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('name');
    const previewLabel = document.getElementById('previewLabel');
    const previewBadge = document.getElementById('previewBadge');

    function updatePreview() {
        const name = nameInput.value || 'Attribute Name';
        previewLabel.textContent = name;
        previewBadge.innerHTML = '<i class="fa-solid fa-check"></i> ' + name;
    }

    nameInput.addEventListener('input', updatePreview);
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
