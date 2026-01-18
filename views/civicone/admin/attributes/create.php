<?php
/**
 * Admin Create Attribute - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Create Attribute';
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
            <a href="<?= $basePath ?>/admin/attributes" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Create New Attribute
        </h1>
        <p class="admin-page-subtitle">Define a new service tag or requirement</p>
    </div>
</div>

<!-- Create Form Card -->
<div class="admin-glass-card" style="max-width: 700px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #14b8a6, #0d9488);">
            <i class="fa-solid fa-tag"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Attribute Details</h3>
            <p class="admin-card-subtitle">Configure the new attribute settings</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin/attributes/store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

            <div class="form-group">
                <label for="name">Attribute Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g. Garda Vetting">
                <small>Displayed label for the checkbox or badge</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="category_id">Scope (Category)</label>
                    <select id="category_id" name="category_id">
                        <option value="">Global (All listings)</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>">Only for: <?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Limit this attribute to a specific category</small>
                </div>

                <div class="form-group">
                    <label for="input_type">Input Type</label>
                    <select id="input_type" name="input_type">
                        <option value="checkbox">Checkbox (Yes/No)</option>
                    </select>
                    <small>How users will select this attribute</small>
                </div>
            </div>

            <!-- Preview Box -->
            <div class="attribute-preview-container">
                <label>Preview</label>
                <div class="attribute-preview">
                    <div class="preview-checkbox">
                        <input type="checkbox" id="previewCheck" checked disabled>
                        <span id="previewLabel">Attribute Name</span>
                    </div>
                    <div class="preview-badge">
                        <span class="attr-badge" id="previewBadge">
                            <i class="fa-solid fa-check"></i> Attribute Name
                        </span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= $basePath ?>/admin/attributes" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-check"></i> Create Attribute
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
    updatePreview();
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
