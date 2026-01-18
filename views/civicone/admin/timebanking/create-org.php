<?php
/**
 * Admin Create Organization - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Create Organization';
$adminPageSubtitle = 'TimeBanking';
$adminPageIcon = 'fa-building-circle-arrow-right';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/timebanking/org-wallets" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Create Organization
        </h1>
        <p class="admin-page-subtitle">Create a new organization for time banking</p>
    </div>
</div>

<!-- Create Form Card -->
<div class="admin-glass-card" style="max-width: 600px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Organization Details</h3>
            <p class="admin-card-subtitle">Configure the new organization settings</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin/timebanking/create-org" method="POST">
            <?= Csrf::input() ?>

            <div class="form-group">
                <label for="owner_email">Owner Email <span class="required">*</span></label>
                <input type="email" id="owner_email" name="owner_email" placeholder="user@example.com" required>
                <small>The user who will own this organization. Must be an existing user.</small>
            </div>

            <div class="form-group">
                <label for="org_name">Organization Name <span class="required">*</span></label>
                <input type="text" id="org_name" name="org_name" placeholder="e.g. Community Garden Project" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" placeholder="Describe what this organization does..."></textarea>
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email</label>
                <input type="email" id="contact_email" name="contact_email" placeholder="contact@organization.com">
                <small>Leave blank to use the owner's email address.</small>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="approved">Approved (Active immediately)</option>
                    <option value="pending">Pending (Needs approval)</option>
                </select>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <div class="info-box-icon">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <div class="info-box-content">
                    <strong>Note</strong>
                    <p>Once created, the organization will have its own time banking wallet and can start managing members.</p>
                </div>
            </div>

            <div class="form-actions">
                <a href="<?= $basePath ?>/admin/timebanking/org-wallets" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-times"></i> Cancel
                </a>
                <button type="submit" class="admin-btn admin-btn-success">
                    <i class="fa-solid fa-plus"></i> Create Organization
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

.form-group label .required {
    color: #f87171;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group textarea,
.form-group select {
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
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.form-group select {
    cursor: pointer;
}

.form-group select option {
    background: #1e293b;
    color: #f1f5f9;
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

/* Info Box */
.info-box {
    display: flex;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 0.75rem;
    margin-bottom: 2rem;
}

.info-box-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #10b981, #059669);
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

.form-actions .admin-btn-success {
    flex: 2;
}

.admin-btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #fff;
    border: none;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #34d399, #10b981);
}

/* Responsive */
@media (max-width: 768px) {
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

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
