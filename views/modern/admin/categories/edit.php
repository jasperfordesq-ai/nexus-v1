<?php
/**
 * Admin Edit Category - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Edit Category';
$adminPageSubtitle = 'Categories';
$adminPageIcon = 'fa-folder-pen';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$colors = ['blue', 'green', 'red', 'yellow', 'purple', 'pink', 'indigo', 'gray'];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/categories" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Edit Category
        </h1>
        <p class="admin-page-subtitle">Update category settings for "<?= htmlspecialchars($category['name']) ?>"</p>
    </div>
</div>

<!-- Edit Form Card -->
<div class="admin-glass-card" style="max-width: 700px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
            <i class="fa-solid fa-folder-pen"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Category Details</h3>
            <p class="admin-card-subtitle">Modify the category configuration</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin/categories/update" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">
            <input type="hidden" name="id" value="<?= $category['id'] ?>">

            <div class="form-group">
                <label for="name">Category Name *</label>
                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($category['name']) ?>">
                <small>A descriptive name for this category</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="type">Module Type *</label>
                    <select id="type" name="type" required>
                        <option value="listing" <?= $category['type'] == 'listing' ? 'selected' : '' ?>>Listing (Offer/Request)</option>
                        <option value="vol_opportunity" <?= $category['type'] == 'vol_opportunity' ? 'selected' : '' ?>>Volunteering Opportunity</option>
                        <option value="event" <?= $category['type'] == 'event' ? 'selected' : '' ?>>Event</option>
                        <option value="poll" <?= $category['type'] == 'poll' ? 'selected' : '' ?>>Poll</option>
                    </select>
                    <small>Which module does this category belong to?</small>
                </div>

                <div class="form-group">
                    <label for="color">Color Badge</label>
                    <select id="color" name="color">
                        <?php foreach ($colors as $color): ?>
                        <option value="<?= $color ?>" <?= ($category['color'] ?? 'blue') == $color ? 'selected' : '' ?>><?= ucfirst($color) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Visual identifier for this category</small>
                </div>
            </div>

            <!-- Color Preview -->
            <div class="color-preview-container">
                <label>Preview</label>
                <div class="color-preview">
                    <span class="category-badge <?= htmlspecialchars($category['color'] ?? 'blue') ?>" id="colorPreview"><?= htmlspecialchars($category['name']) ?></span>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= $basePath ?>/admin/categories" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-check"></i> Update Category
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
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
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

.color-preview-container {
    margin-bottom: 2rem;
    padding: 1.25rem;
    background: rgba(139, 92, 246, 0.08);
    border: 1px solid rgba(139, 92, 246, 0.15);
    border-radius: 0.75rem;
}

.color-preview-container label {
    display: block;
    margin-bottom: 0.75rem;
    font-weight: 600;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.color-preview {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.category-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s;
}

/* Color badge variants */
.category-badge.blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
.category-badge.green { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
.category-badge.red { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
.category-badge.yellow { background: rgba(234, 179, 8, 0.2); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3); }
.category-badge.purple { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3); }
.category-badge.pink { background: rgba(236, 72, 153, 0.2); color: #ec4899; border: 1px solid rgba(236, 72, 153, 0.3); }
.category-badge.indigo { background: rgba(99, 102, 241, 0.2); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.3); }
.category-badge.gray { background: rgba(107, 114, 128, 0.2); color: #9ca3af; border: 1px solid rgba(107, 114, 128, 0.3); }

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
    const colorSelect = document.getElementById('color');
    const nameInput = document.getElementById('name');
    const preview = document.getElementById('colorPreview');

    function updatePreview() {
        const color = colorSelect.value;
        const name = nameInput.value || 'Category Name';

        preview.className = 'category-badge ' + color;
        preview.textContent = name;
    }

    colorSelect.addEventListener('change', updatePreview);
    nameInput.addEventListener('input', updatePreview);
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
