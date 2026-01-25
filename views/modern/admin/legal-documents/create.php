<?php
/**
 * Admin Create Legal Document Form
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'New Document';
$adminPageSubtitle = 'Legal Documents';
$adminPageIcon = 'fa-plus';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

// Get form data from session (for validation errors)
$formData = $_SESSION['form_data'] ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']);
?>

<!-- Breadcrumb -->
<nav class="admin-breadcrumb">
    <a href="<?= $basePath ?>/admin/legal-documents"><i class="fa-solid fa-arrow-left"></i> All Documents</a>
</nav>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-plus"></i>
            Create Legal Document
        </h1>
        <p class="admin-page-subtitle">Add a new legal document to your platform</p>
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

<form action="<?= $basePath ?>/admin/legal-documents" method="POST" class="legal-form">
    <?= Csrf::input() ?>

    <div class="admin-form-grid">
        <!-- Left Column: Main Settings -->
        <div class="admin-form-main">
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-indigo">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Document Details</h3>
                        <p class="admin-card-subtitle">Basic information about this legal document</p>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label for="document_type">Document Type <span class="required">*</span></label>
                        <select name="document_type" id="document_type" class="form-select" required>
                            <option value="">Select a type...</option>
                            <?php foreach ($documentTypes as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($formData['document_type'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-help">The type determines the document's purpose and icon</p>
                    </div>

                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" name="title" id="title" class="form-input"
                               value="<?= htmlspecialchars($formData['title'] ?? '') ?>"
                               placeholder="e.g., Terms of Service" required>
                        <p class="form-help">The display name shown to users</p>
                    </div>

                    <div class="form-group">
                        <label for="slug">URL Slug <span class="required">*</span></label>
                        <div class="form-input-prefix">
                            <span class="input-prefix"><?= $basePath ?>/</span>
                            <input type="text" name="slug" id="slug" class="form-input"
                                   value="<?= htmlspecialchars($formData['slug'] ?? '') ?>"
                                   placeholder="terms" required pattern="[a-z0-9-]+">
                        </div>
                        <p class="form-help">Lowercase letters, numbers, and hyphens only</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Settings -->
        <div class="admin-form-sidebar">
            <!-- Acceptance Settings -->
            <div class="admin-glass-card">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Acceptance Settings</h3>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="requires_acceptance" value="1"
                                   <?= !empty($formData['requires_acceptance']) ? 'checked' : '' ?>>
                            <span class="checkbox-label">Require user acceptance</span>
                        </label>
                        <p class="form-help">Users must explicitly accept this document</p>
                    </div>

                    <div class="form-group">
                        <label for="acceptance_required_for">Acceptance Required For</label>
                        <select name="acceptance_required_for" id="acceptance_required_for" class="form-select">
                            <option value="registration" <?= ($formData['acceptance_required_for'] ?? '') === 'registration' ? 'selected' : '' ?>>Registration Only</option>
                            <option value="login" <?= ($formData['acceptance_required_for'] ?? '') === 'login' ? 'selected' : '' ?>>Every Login</option>
                            <option value="both" <?= ($formData['acceptance_required_for'] ?? '') === 'both' ? 'selected' : '' ?>>Registration & Updates</option>
                        </select>
                        <p class="form-help">When should users be prompted to accept</p>
                    </div>

                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="notify_on_update" value="1"
                                   <?= !empty($formData['notify_on_update']) ? 'checked' : '' ?>>
                            <span class="checkbox-label">Notify users on updates</span>
                        </label>
                        <p class="form-help">Email users when a new version is published</p>
                    </div>
                </div>
            </div>

            <!-- Status Settings -->
            <div class="admin-glass-card" style="margin-top: 1rem;">
                <div class="admin-card-header">
                    <div class="admin-card-header-icon admin-card-header-icon-blue">
                        <i class="fa-solid fa-toggle-on"></i>
                    </div>
                    <div class="admin-card-header-content">
                        <h3 class="admin-card-title">Status</h3>
                    </div>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="is_active" value="1" checked
                                   <?= !empty($formData['is_active']) ? 'checked' : '' ?>>
                            <span class="checkbox-label">Active</span>
                        </label>
                        <p class="form-help">Inactive documents are not shown to users</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="admin-btn admin-btn-primary admin-btn-lg admin-btn-full">
                    <i class="fa-solid fa-save"></i> Create Document
                </button>
                <a href="<?= $basePath ?>/admin/legal-documents" class="admin-btn admin-btn-secondary admin-btn-full">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
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
.form-select {
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
.form-select:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px;
    padding-right: 2.5rem;
}

.form-help {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.5rem;
}

/* Input with prefix */
.form-input-prefix {
    display: flex;
    align-items: center;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    overflow: hidden;
}

.form-input-prefix .input-prefix {
    padding: 0.75rem 0 0.75rem 1rem;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
    white-space: nowrap;
}

.form-input-prefix .form-input {
    border: none;
    border-radius: 0;
    padding-left: 0;
}

.form-input-prefix:focus-within {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Checkbox */
.form-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.form-checkbox input {
    width: 18px;
    height: 18px;
    accent-color: #6366f1;
}

.checkbox-label {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.9);
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

.admin-btn-lg {
    padding: 0.75rem 1.25rem;
    font-size: 0.95rem;
}

.admin-btn-full {
    width: 100%;
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

<script>
// Auto-generate slug from title
document.getElementById('title').addEventListener('input', function() {
    const slug = document.getElementById('slug');
    if (!slug.dataset.touched) {
        slug.value = this.value.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

document.getElementById('slug').addEventListener('input', function() {
    this.dataset.touched = 'true';
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
