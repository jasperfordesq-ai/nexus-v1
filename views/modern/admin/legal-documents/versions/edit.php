<?php
/**
 * Admin Edit Draft Version Form
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Edit Version';
$adminPageSubtitle = $document['title'] . ' v' . $version['version_number'];
$adminPageIcon = 'fa-pen';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Get form data from session (for validation errors)
$formData = $_SESSION['form_data'] ?? $version;
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);
?>

<!-- Breadcrumb -->
<nav class="admin-breadcrumb">
    <a href="<?= $basePath ?>/admin-legacy/legal-documents"><i class="fa-solid fa-arrow-left"></i> All Documents</a>
    <span>/</span>
    <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>"><?= htmlspecialchars($document['title']) ?></a>
    <span>/</span>
    <span>Edit Version <?= htmlspecialchars($version['version_number']) ?></span>
</nav>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-pen"></i>
            Edit Draft Version <?= htmlspecialchars($version['version_number']) ?>
        </h1>
        <p class="admin-page-subtitle">Update this draft version of <?= htmlspecialchars($document['title']) ?></p>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <div>
        <strong>Please fix the following errors:</strong>
        <ul style="margin: 0.5rem 0 0 1rem; padding: 0;">
            <?php foreach ($formErrors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<form action="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>/versions/<?= $version['id'] ?>" method="POST" class="version-form">
    <?= Csrf::input() ?>
    <input type="hidden" name="_method" value="PUT">

    <div class="admin-form-grid">
        <!-- Left Column: Main Content -->
        <div class="admin-form-main">
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-indigo">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Document Content</h3>
                        <p class="admin-card-subtitle">Full HTML content of the legal document</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label for="content">Content (HTML) <span class="required">*</span></label>
                        <textarea name="content" id="content" class="form-textarea form-textarea-large" required><?= htmlspecialchars($formData['content'] ?? '') ?></textarea>
                        <p class="form-help">You can use HTML formatting. The content will be displayed on the public legal page.</p>
                    </div>
                </div>
            </div>

            <!-- Summary of Changes -->
            <div class="admin-glass-card" style="margin-top: 1.5rem;">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-yellow">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Summary of Changes</h3>
                        <p class="admin-card-subtitle">Brief description of what changed in this version</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label for="summary_of_changes">Changes Summary</label>
                        <textarea name="summary_of_changes" id="summary_of_changes" class="form-textarea" rows="3"><?= htmlspecialchars($formData['summary_of_changes'] ?? '') ?></textarea>
                        <p class="form-help">This is shown to users when notifying them of updates.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Settings -->
        <div class="admin-form-sidebar">
            <!-- Version Info -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-purple">
                        <i class="fa-solid fa-code-branch"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Version Details</h3>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label for="version_number">Version Number <span class="required">*</span></label>
                        <input type="text" name="version_number" id="version_number" class="form-input"
                               value="<?= htmlspecialchars($formData['version_number'] ?? '') ?>"
                               required pattern="\d+(\.\d+)*">
                        <p class="form-help">Format: 1.0, 2.1, etc.</p>
                    </div>

                    <div class="form-group">
                        <label for="version_label">Version Label (optional)</label>
                        <input type="text" name="version_label" id="version_label" class="form-input"
                               value="<?= htmlspecialchars($formData['version_label'] ?? '') ?>"
                               placeholder="e.g., January 2026 Update">
                    </div>

                    <div class="form-group">
                        <label for="effective_date">Effective Date <span class="required">*</span></label>
                        <input type="date" name="effective_date" id="effective_date" class="form-input"
                               value="<?= htmlspecialchars($formData['effective_date'] ?? '') ?>" required>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg admin-btn-full">
                    <i class="fa-solid fa-save"></i> Save Changes
                </button>
                <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-full">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
            </div>

            <!-- Delete Draft -->
            <div class="delete-section" style="margin-top: 2rem;">
                <form action="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>/versions/<?= $version['id'] ?>/delete" method="POST"
                      onsubmit="return confirm('Delete this draft version? This cannot be undone.')">
                    <?= Csrf::input() ?>
                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-full">
                        <i class="fa-solid fa-trash"></i> Delete Draft
                    </button>
                </form>
            </div>
        </div>
    </div>
</form>

<style>
/* Breadcrumb */
.admin-breadcrumb {
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.admin-breadcrumb a {
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: color 0.2s;
}

.admin-breadcrumb a:hover {
    color: #818cf8;
}

.admin-breadcrumb span {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
}

/* Form Grid */
.admin-form-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 1.5rem;
}

.admin-form-main {
    min-width: 0;
}

.admin-form-sidebar {
    min-width: 0;
}

/* Form Elements */
.form-group {
    margin-bottom: 1.25rem;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 0.5rem;
}

.form-group label .required {
    color: #f87171;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 0.9rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-textarea {
    min-height: 120px;
    resize: vertical;
    font-family: inherit;
}

.form-textarea-large {
    min-height: 400px;
    font-family: 'Fira Code', monospace;
    font-size: 0.85rem;
    line-height: 1.6;
}

.form-help {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.5rem;
}

/* Actions */
.form-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

.admin-btn-lg {
    padding: 0.75rem 1.25rem;
    font-size: 0.95rem;
}

.admin-btn-full {
    width: 100%;
}

/* Delete Section */
.delete-section {
    padding-top: 1.5rem;
    border-top: 1px solid rgba(239, 68, 68, 0.2);
}

/* Alerts */
.admin-alert {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    font-size: 0.9rem;
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
}

/* Responsive */
@media (max-width: 1024px) {
    .admin-form-grid {
        grid-template-columns: 1fr;
    }

    .admin-form-sidebar {
        order: -1;
    }
}
</style>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
