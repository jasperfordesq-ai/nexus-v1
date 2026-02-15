<?php
/**
 * Admin Blog/Article Form - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$isEdit = !empty($post);

// Admin header configuration
$adminPageTitle = $isEdit ? 'Edit Article' : 'New Article';
$adminPageSubtitle = 'Blog';
$adminPageIcon = $isEdit ? 'fa-pen-to-square' : 'fa-file-pen';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/blog" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <?= $isEdit ? 'Edit Article' : 'Create New Article' ?>
        </h1>
        <p class="admin-page-subtitle"><?= $isEdit ? 'Update article content and settings' : 'Share news with your community' ?></p>
    </div>
    <?php if ($isEdit): ?>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/blog/builder/<?= $post['id'] ?>" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Visual Builder
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Article Form Card -->
<div class="admin-glass-card" style="max-width: 900px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ec4899, #db2777);">
            <i class="fa-solid fa-newspaper"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Article Details</h3>
            <p class="admin-card-subtitle"><?= $isEdit ? 'Modify the article content' : 'Fill in the article information' ?></p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $isEdit ? $basePath . '/admin-legacy/blog/update/' . $post['id'] : $basePath . '/admin-legacy/blog/store' ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

            <div class="form-group">
                <label for="title">Article Title *</label>
                <input type="text" id="title" name="title" required value="<?= htmlspecialchars($post['title'] ?? '') ?>" placeholder="Enter a compelling headline...">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="slug">URL Slug</label>
                    <div class="slug-input-wrapper">
                        <span class="slug-prefix">/news/</span>
                        <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($post['slug'] ?? '') ?>" placeholder="my-article-title">
                    </div>
                    <small>Leave blank to auto-generate from title</small>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>
                            Draft
                        </option>
                        <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>
                            Published
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="featured_image">Featured Image URL</label>
                <div class="image-input-wrapper">
                    <input type="url" id="featured_image" name="featured_image" value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>" placeholder="https://example.com/image.jpg">
                    <button type="button" class="preview-btn" onclick="previewImage()">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <div id="imagePreview" class="image-preview" style="display: none;">
                    <img src="" alt="Preview" loading="lazy">
                </div>
            </div>

            <div class="form-group">
                <label for="excerpt">Excerpt</label>
                <textarea id="excerpt" name="excerpt" rows="3" placeholder="Brief summary that appears in article listings..."><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
                <small>Short description for search results and social sharing</small>
            </div>

            <div class="form-group">
                <label for="content">Content *</label>
                <textarea id="content" name="content" rows="15" required placeholder="Write your article content here..."><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
                <small>You can use HTML for formatting</small>
            </div>

            <div class="form-actions">
                <a href="<?= $basePath ?>/admin-legacy/blog" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-<?= $isEdit ? 'save' : 'paper-plane' ?>"></i>
                    <?= $isEdit ? 'Update Article' : 'Publish Article' ?>
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
.form-group input[type="url"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 1rem;
    transition: all 0.2s;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #ec4899;
    box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.2);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
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

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group textarea#content {
    min-height: 300px;
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    font-size: 0.9rem;
    line-height: 1.6;
}

.form-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

/* Slug Input */
.slug-input-wrapper {
    display: flex;
    align-items: stretch;
}

.slug-prefix {
    display: flex;
    align-items: center;
    padding: 0 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-right: none;
    border-radius: 0.5rem 0 0 0.5rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

.slug-input-wrapper input {
    border-radius: 0 0.5rem 0.5rem 0;
}

/* Image Input */
.image-input-wrapper {
    display: flex;
    gap: 0.5rem;
}

.image-input-wrapper input {
    flex: 1;
}

.preview-btn {
    padding: 0 1rem;
    background: rgba(236, 72, 153, 0.2);
    border: 1px solid rgba(236, 72, 153, 0.3);
    border-radius: 0.5rem;
    color: #ec4899;
    cursor: pointer;
    transition: all 0.2s;
}

.preview-btn:hover {
    background: rgba(236, 72, 153, 0.3);
}

.image-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 0.5rem;
    text-align: center;
}

.image-preview img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 0.5rem;
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

    .form-actions {
        flex-direction: column;
    }

    .form-actions .admin-btn {
        width: 100%;
    }
}
</style>

<script>
function previewImage() {
    const input = document.getElementById('featured_image');
    const preview = document.getElementById('imagePreview');
    const img = preview.querySelector('img');

    if (input.value) {
        img.src = input.value;
        img.onload = function() {
            preview.style.display = 'block';
        };
        img.onerror = function() {
            preview.style.display = 'none';
            alert('Could not load image. Please check the URL.');
        };
    } else {
        preview.style.display = 'none';
    }
}

// Auto-generate slug from title
document.getElementById('title').addEventListener('input', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.value) {
        // Only auto-fill if slug is empty
        slugInput.placeholder = this.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
