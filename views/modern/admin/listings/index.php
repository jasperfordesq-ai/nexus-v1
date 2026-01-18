<?php
/**
 * Admin Listings Manager - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Listings';
$adminPageSubtitle = 'Content Directory';
$adminPageIcon = 'fa-list';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$listings = $listings ?? [];
$tenants = $tenants ?? [];
$currentTenantId = $currentTenantId ?? null;
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-list"></i>
            Global Content Directory
        </h1>
        <p class="admin-page-subtitle">Manage marketplace, events, polls, and more</p>
    </div>
    <div class="admin-page-header-actions">
        <form method="GET" action="" class="admin-filter-form">
            <select name="tenant_id" onchange="this.form.submit()" class="admin-select">
                <option value="">All Tenants</option>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?= $tenant['id'] ?>" <?= ($currentTenantId == $tenant['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tenant['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <span class="admin-badge admin-badge-primary">
            <i class="fa-solid fa-shield-halved"></i>
            Admin Access
        </span>
    </div>
</div>

<!-- Listings Table Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-indigo">
            <i class="fa-solid fa-table-list"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">All Content</h3>
            <p class="admin-card-subtitle"><?= count($listings) ?> items found</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($listings)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <h3 class="admin-empty-title">No listings found</h3>
            <p class="admin-empty-text">Content will appear here once created</p>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th class="hide-mobile">Tenant</th>
                        <th>Listing</th>
                        <th class="hide-tablet">Author</th>
                        <th class="hide-mobile">Type</th>
                        <th class="hide-tablet">Created</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listings as $row): ?>
                    <?php
                    $typeColor = '#6b7280';
                    $typeLabel = $row['content_type'];
                    $typeIcon = 'fa-file';
                    $editUrl = '#';

                    switch ($row['content_type']) {
                        case 'listing':
                            $typeColor = ($row['type'] ?? '') === 'offer' ? '#e41e3f' : '#f97316';
                            $typeLabel = ($row['type'] ?? 'listing');
                            $typeIcon = ($row['type'] ?? '') === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand';
                            $editUrl = "{$basePath}/listings/edit/{$row['id']}";
                            break;
                        case 'event':
                            $typeColor = '#3b82f6';
                            $typeIcon = 'fa-calendar-days';
                            $editUrl = "{$basePath}/events/edit/{$row['id']}";
                            break;
                        case 'poll':
                            $typeColor = '#8b5cf6';
                            $typeIcon = 'fa-square-poll-vertical';
                            $editUrl = "{$basePath}/polls/edit/{$row['id']}";
                            break;
                        case 'goal':
                            $typeColor = '#10b981';
                            $typeIcon = 'fa-bullseye';
                            $editUrl = "{$basePath}/goals/edit/{$row['id']}";
                            break;
                        case 'resource':
                            $typeColor = '#06b6d4';
                            $typeIcon = 'fa-book';
                            $editUrl = "{$basePath}/resources/edit/{$row['id']}";
                            break;
                        case 'volunteer':
                            $typeColor = '#ec4899';
                            $typeIcon = 'fa-hands-helping';
                            $editUrl = "{$basePath}/volunteering/edit/{$row['id']}";
                            break;
                    }
                    ?>
                    <tr>
                        <td>
                            <span class="admin-listing-id">#<?= $row['id'] ?></span>
                        </td>
                        <td class="hide-mobile">
                            <span class="admin-tenant-badge">
                                <?= htmlspecialchars($row['tenant_name'] ?? 'Unknown') ?>
                            </span>
                        </td>
                        <td>
                            <div class="admin-listing-cell">
                                <div class="admin-listing-title"><?= htmlspecialchars($row['title']) ?></div>
                                <div class="admin-listing-desc"><?= htmlspecialchars(substr($row['description'], 0, 60)) ?>...</div>
                            </div>
                        </td>
                        <td class="hide-tablet">
                            <span class="admin-author-name"><?= htmlspecialchars($row['author_name'] ?? 'Unknown') ?></span>
                        </td>
                        <td class="hide-mobile">
                            <span class="admin-type-badge" style="--type-color: <?= $typeColor ?>;">
                                <i class="fa-solid <?= $typeIcon ?>"></i>
                                <?= htmlspecialchars(ucfirst($typeLabel)) ?>
                            </span>
                        </td>
                        <td class="hide-tablet admin-date-cell">
                            <?= date('M j, Y', strtotime($row['created_at'])) ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <a href="<?= $editUrl ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form method="POST" action="<?= $basePath ?>/admin/listings/delete/<?= $row['id'] ?>?type=<?= $row['content_type'] ?>" onsubmit="return confirm('Delete this item?');" style="display:inline;">
                                    <?= Csrf::input() ?>
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

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="admin-pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?><?= $currentTenantId ? "&tenant_id={$currentTenantId}" : '' ?>"
           class="admin-page-btn <?= $i == $currentPage ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<style>
/* Filter Form */
.admin-filter-form {
    display: inline-flex;
}

.admin-select {
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 0.85rem;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 1rem;
}

.admin-select:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
}

.admin-select option {
    background: #1e293b;
    color: #fff;
}

/* Badge */
.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
}

.admin-badge-primary {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

/* Card Header Icon */
.admin-card-header-icon-indigo {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
}

/* Listing ID */
.admin-listing-id {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Tenant Badge */
.admin-tenant-badge {
    display: inline-flex;
    padding: 4px 10px;
    background: rgba(100, 116, 139, 0.2);
    color: #cbd5e1;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Listing Cell */
.admin-listing-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.admin-listing-title {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

.admin-listing-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Author Name */
.admin-author-name {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
}

/* Type Badge */
.admin-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: color-mix(in srgb, var(--type-color) 15%, transparent);
    color: var(--type-color);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Date Cell */
.admin-date-cell {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Action Buttons */
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
    background: rgba(99, 102, 241, 0.1);
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

/* Pagination */
.admin-pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.admin-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 0.75rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.admin-page-btn:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
    color: #fff;
}

.admin-page-btn.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
    color: white;
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

    .admin-page-header-actions {
        flex-direction: column;
        gap: 0.75rem;
        width: 100%;
    }

    .admin-select {
        width: 100%;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
