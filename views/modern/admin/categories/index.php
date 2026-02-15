<?php
/**
 * Admin Category Manager - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Categories';
$adminPageSubtitle = 'Configuration';
$adminPageIcon = 'fa-folder-tree';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-folder-tree"></i>
            Category Manager
        </h1>
        <p class="admin-page-subtitle">Organize listings and opportunities with taxonomies</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/categories/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> New Category
        </a>
    </div>
</div>

<!-- Categories Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
            <i class="fa-solid fa-tags"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Defined Categories</h3>
            <p class="admin-card-subtitle"><?= count($categories ?? []) ?> categories configured</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($categories)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <h3 class="admin-empty-title">No categories defined yet</h3>
            <p class="admin-empty-text">Create your first category to organize content.</p>
            <a href="<?= $basePath ?>/admin-legacy/categories/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i> Create Category
            </a>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="hide-mobile">Module</th>
                        <th class="hide-tablet">Color</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <div class="category-cell">
                                <div class="category-color" style="background-color: var(--nexus-<?= $cat['color'] ?>-500, <?= $cat['color'] ?>);"></div>
                                <div class="category-info">
                                    <div class="category-name"><?= htmlspecialchars($cat['name']) ?></div>
                                    <div class="category-slug">/<?= htmlspecialchars($cat['slug']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="hide-mobile">
                            <?php
                            $typeClass = match ($cat['type']) {
                                'vol_opportunity' => 'module-badge-green',
                                'timebanking' => 'module-badge-blue',
                                default => 'module-badge-gray'
                            };
                            ?>
                            <span class="module-badge <?= $typeClass ?>">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $cat['type']))) ?>
                            </span>
                        </td>
                        <td class="hide-tablet">
                            <div class="color-preview">
                                <span class="color-dot" style="background-color: var(--nexus-<?= $cat['color'] ?>-500, <?= $cat['color'] ?>);"></span>
                                <span class="color-name"><?= htmlspecialchars(ucfirst($cat['color'])) ?></span>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <a href="<?= $basePath ?>/admin-legacy/categories/edit/<?= $cat['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form action="<?= $basePath ?>/admin-legacy/categories/delete" method="POST" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
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
/* Category Manager Specific Styles */

/* Category Cell */
.category-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.category-color {
    width: 12px;
    height: 40px;
    border-radius: 4px;
    flex-shrink: 0;
}

.category-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.category-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

.category-slug {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    font-family: monospace;
}

/* Module Badges */
.module-badge {
    display: inline-flex;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.module-badge-green {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.module-badge-blue {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.module-badge-gray {
    background: rgba(107, 114, 128, 0.15);
    color: #9ca3af;
    border: 1px solid rgba(107, 114, 128, 0.3);
}

/* Color Preview */
.color-preview {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.color-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.color-name {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
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

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
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

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

.admin-action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

/* Table */
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
    background: rgba(139, 92, 246, 0.1);
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
