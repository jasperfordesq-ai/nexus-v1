<?php
/**
 * Admin Attributes Manager - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Attributes';
$adminPageSubtitle = 'Configuration';
$adminPageIcon = 'fa-tags';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-tags"></i>
            Attribute Manager
        </h1>
        <p class="admin-page-subtitle">Manage tags and service requirements for listings</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/attributes/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i>
            New Attribute
        </a>
    </div>
</div>

<!-- Attributes Table Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-teal">
            <i class="fa-solid fa-tags"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Service Attributes</h3>
            <p class="admin-card-subtitle"><?= count($attributes ?? []) ?> attributes defined</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($attributes)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-tags"></i>
            </div>
            <h3 class="admin-empty-title">No attributes yet</h3>
            <p class="admin-empty-text">Create your first attribute to enhance listings</p>
            <a href="<?= $basePath ?>/admin/attributes/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i>
                Create First Attribute
            </a>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Attribute Name</th>
                        <th class="hide-mobile">Scope / Category</th>
                        <th class="hide-tablet">Input Type</th>
                        <th class="hide-mobile" style="text-align: center;">Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attributes as $attr): ?>
                    <tr>
                        <td>
                            <div class="admin-attr-name"><?= htmlspecialchars($attr['name']) ?></div>
                        </td>
                        <td class="hide-mobile">
                            <?php if (!empty($attr['category_name'])): ?>
                                <span class="admin-attr-badge admin-attr-badge-category">
                                    <?= htmlspecialchars($attr['category_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="admin-attr-badge admin-attr-badge-global">
                                    Global
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-tablet">
                            <span class="admin-attr-type">
                                <?= strtoupper($attr['input_type']) ?>
                            </span>
                        </td>
                        <td class="hide-mobile" style="text-align: center;">
                            <?php if ($attr['is_active']): ?>
                                <span class="admin-status-badge admin-status-active">
                                    <span class="admin-status-dot"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="admin-status-badge admin-status-inactive">
                                    <span class="admin-status-dot"></span> Inactive
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <a href="<?= $basePath ?>/admin/attributes/edit/<?= $attr['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form action="<?= $basePath ?>/admin/attributes/delete" method="POST" onsubmit="return confirm('Delete this attribute?');" style="display:inline;">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= $attr['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Attribute name styling */
.admin-attr-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

/* Attribute badges */
.admin-attr-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
}

.admin-attr-badge-category {
    background: rgba(6, 182, 212, 0.15);
    color: #22d3ee;
    border: 1px solid rgba(6, 182, 212, 0.3);
}

.admin-attr-badge-global {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(100, 116, 139, 0.3);
}

/* Input type badge */
.admin-attr-type {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.75rem;
    color: #94a3b8;
    background: rgba(51, 65, 85, 0.6);
    padding: 4px 10px;
    border-radius: 6px;
}

/* Status badges */
.admin-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    font-weight: 500;
}

.admin-status-badge .admin-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.admin-status-active {
    color: #34d399;
}

.admin-status-active .admin-status-dot {
    background: #34d399;
    box-shadow: 0 0 8px rgba(52, 211, 153, 0.5);
}

.admin-status-inactive {
    color: #f87171;
}

.admin-status-inactive .admin-status-dot {
    background: #f87171;
    box-shadow: 0 0 8px rgba(248, 113, 113, 0.5);
}

/* Action buttons */
.admin-action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
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
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
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

/* Card Header Icon */
.admin-card-header-icon-teal {
    background: rgba(20, 184, 166, 0.15);
    color: #2dd4bf;
}

/* Table Styles */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: rgba(20, 184, 166, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.3);
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Responsive */
@media (max-width: 1024px) {
    .hide-tablet {
        display: none;
    }
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }

    .admin-action-buttons {
        flex-direction: column;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
