<?php
/**
 * Admin Create Page - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Create Page';
$adminPageSubtitle = 'Pages';
$adminPageIcon = 'fa-file-plus';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/pages" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Create New Page
        </h1>
        <p class="admin-page-subtitle">Add a new content page to your site</p>
    </div>
</div>

<!-- Create Form Card -->
<div class="admin-glass-card" style="max-width: 900px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
            <i class="fa-solid fa-file-lines"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Page Details</h3>
            <p class="admin-card-subtitle">Configure the new page settings</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin/pages/store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

            <div class="form-group">
                <label for="title">Page Title *</label>
                <input type="text" id="title" name="title" required placeholder="e.g. Community Guidelines">
                <small>The main heading that appears on the page</small>
            </div>

            <div class="form-group">
                <label for="slug">URL Slug</label>
                <div class="slug-input-wrapper">
                    <span class="slug-prefix">/page/</span>
                    <input type="text" id="slug" name="slug" placeholder="community-guidelines">
                </div>
                <small>Leave blank to auto-generate from title</small>
            </div>

            <div class="form-group">
                <label for="content">Content *</label>
                <textarea id="content" name="content" rows="15" required placeholder="<p>Enter your content here...</p>"></textarea>
                <small>HTML is supported for formatting</small>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <div class="info-box-icon">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <div class="info-box-content">
                    <strong>Pro Tip</strong>
                    <p>After creating this page, you can use the Visual Builder for a drag-and-drop editing experience.</p>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= $basePath ?>/admin/pages" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-check"></i> Create Page
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
.form-group textarea:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.form-group small {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.form-group textarea {
    resize: vertical;
    min-height: 300px;
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    font-size: 0.9rem;
    line-height: 1.6;
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

/* Info Box */
.info-box {
    display: flex;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 0.75rem;
    margin-bottom: 2rem;
}

.info-box-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex-shrink: 0;
}

.info-box-content strong {
    display: block;
    color: #fff;
    margin-bottom: 0.25rem;
}

.info-box-content p {
    margin: 0;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.5;
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
    .slug-input-wrapper {
        flex-direction: column;
    }

    .slug-prefix {
        border-radius: 0.5rem 0.5rem 0 0;
        border-right: 1px solid rgba(255, 255, 255, 0.15);
        border-bottom: none;
    }

    .slug-input-wrapper input {
        border-radius: 0 0 0.5rem 0.5rem;
    }

    .info-box {
        flex-direction: column;
        text-align: center;
    }

    .info-box-icon {
        margin: 0 auto;
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
// Auto-generate slug from title
document.getElementById('title').addEventListener('input', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.value) {
        slugInput.placeholder = this.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
