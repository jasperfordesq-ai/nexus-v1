<?php
/**
 * Admin Volunteering Organizations - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Organizations';
$adminPageSubtitle = 'Volunteering';
$adminPageIcon = 'fa-building';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-building"></i>
            Organization Management
        </h1>
        <p class="admin-page-subtitle">View and manage all registered organizations</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/volunteering/approvals" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-clipboard-check"></i> Review Pending
        </a>
    </div>
</div>

<!-- Organizations Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <i class="fa-solid fa-sitemap"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">All Organizations</h3>
            <p class="admin-card-subtitle"><?= count($orgs ?? []) ?> registered organizations</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($orgs)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-building"></i>
            </div>
            <h3 class="admin-empty-title">No organizations found</h3>
            <p class="admin-empty-text">Organizations will appear here once they register.</p>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="hide-mobile" style="width: 60px;">ID</th>
                        <th>Organization</th>
                        <th class="hide-tablet">Owner</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orgs as $org):
                        $owner = \Nexus\Models\User::findById($org['user_id']);
                    ?>
                    <tr>
                        <td class="hide-mobile">
                            <span class="org-id">#<?= $org['id'] ?></span>
                        </td>
                        <td>
                            <div class="org-cell">
                                <div class="org-avatar">
                                    <?= strtoupper(substr($org['name'], 0, 1)) ?>
                                </div>
                                <div class="org-info">
                                    <div class="org-name"><?= htmlspecialchars($org['name']) ?></div>
                                    <div class="org-email"><?= htmlspecialchars($org['contact_email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="hide-tablet">
                            <?php if ($owner): ?>
                            <div class="owner-info">
                                <div class="owner-name"><?= htmlspecialchars($owner['name']) ?></div>
                                <div class="owner-email"><?= htmlspecialchars($owner['email'] ?? '') ?></div>
                            </div>
                            <?php else: ?>
                            <span class="owner-deleted">User Deleted</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = match ($org['status'] ?? 'pending') {
                                'approved' => 'status-approved',
                                'declined' => 'status-declined',
                                'pending' => 'status-pending',
                                default => 'status-unknown'
                            };
                            ?>
                            <span class="org-status <?= $statusClass ?>">
                                <?= htmlspecialchars(ucfirst($org['status'] ?? 'pending')) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <a href="<?= $basePath ?>/volunteering/org/edit/<?= $org['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <form action="<?= $basePath ?>/admin/volunteering/delete" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this organization? This cannot be undone.');">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                        <i class="fa-solid fa-trash"></i> Delete
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
/* Volunteering Organizations Specific Styles */

/* Organization Cell */
.org-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.org-avatar {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.org-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.org-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

.org-email {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.org-id {
    font-family: monospace;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* Owner Info */
.owner-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.owner-name {
    font-weight: 500;
    color: #fff;
    font-size: 0.9rem;
}

.owner-email {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.owner-deleted {
    color: #f87171;
    font-weight: 500;
    font-size: 0.85rem;
}

/* Status Badges */
.org-status {
    display: inline-flex;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.status-approved {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-declined {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-pending {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-unknown {
    background: rgba(107, 114, 128, 0.15);
    color: #9ca3af;
    border: 1px solid rgba(107, 114, 128, 0.3);
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

    .org-cell {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
