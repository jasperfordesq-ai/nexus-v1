<?php
/**
 * Admin Volunteering Approvals - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Approvals';
$adminPageSubtitle = 'Volunteering';
$adminPageIcon = 'fa-clipboard-check';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-clipboard-check"></i>
            Organization Approvals
        </h1>
        <p class="admin-page-subtitle">Review and verify new organizations</p>
    </div>
    <div class="admin-page-header-actions">
        <span class="admin-badge admin-badge-primary"><?= count($pending ?? []) ?> Pending</span>
    </div>
</div>

<!-- Alert Messages -->
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'approved'): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div><strong>Success!</strong> Organization approved successfully.</div>
</div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'declined'): ?>
<div class="admin-alert admin-alert-warning">
    <i class="fa-solid fa-ban"></i>
    <div><strong>Done.</strong> Organization declined.</div>
</div>
<?php endif; ?>

<!-- Pending Approvals Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Pending Organizations</h3>
            <p class="admin-card-subtitle">Review applications from new organizations</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($pending)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-check-double"></i>
            </div>
            <h3 class="admin-empty-title">All caught up!</h3>
            <p class="admin-empty-text">No pending organizations to review.</p>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Organization</th>
                        <th class="hide-mobile">Owner</th>
                        <th class="hide-tablet">Description</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $org):
                        $owner = \Nexus\Models\User::findById($org['user_id']);
                    ?>
                    <tr>
                        <td>
                            <div class="org-cell">
                                <div class="org-avatar">
                                    <?= strtoupper(substr($org['name'], 0, 1)) ?>
                                </div>
                                <div class="org-info">
                                    <div class="org-name"><?= htmlspecialchars($org['name']) ?></div>
                                    <?php if (!empty($org['website'])): ?>
                                        <a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" class="org-website">
                                            <i class="fa-solid fa-external-link"></i> <?= htmlspecialchars(parse_url($org['website'], PHP_URL_HOST)) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="hide-mobile">
                            <div class="owner-info">
                                <div class="owner-name"><?= htmlspecialchars($owner['name'] ?? 'Unknown User') ?></div>
                                <div class="owner-email"><?= htmlspecialchars($owner['email'] ?? $org['email']) ?></div>
                            </div>
                        </td>
                        <td class="hide-tablet">
                            <div class="org-description">
                                <?= htmlspecialchars(substr($org['description'], 0, 120)) ?>...
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <form action="<?= $basePath ?>/admin/volunteering/approve" method="POST" style="display:inline;">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-success admin-btn-sm">
                                        <i class="fa-solid fa-check"></i> Approve
                                    </button>
                                </form>
                                <form action="<?= $basePath ?>/admin/volunteering/decline" method="POST" style="display:inline;">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="org_id" value="<?= $org['id'] ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Reject this organization?');">
                                        <i class="fa-solid fa-times"></i> Decline
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
/* Volunteering Approvals Specific Styles */

/* Alerts */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.admin-alert i {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-alert-success {
    border-left: 3px solid #22c55e;
}
.admin-alert-success i { color: #22c55e; }

.admin-alert-warning {
    border-left: 3px solid #f59e0b;
}
.admin-alert-warning i { color: #f59e0b; }

/* Badge */
.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.admin-badge-primary {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

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
    background: linear-gradient(135deg, #f59e0b, #d97706);
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

.org-website {
    font-size: 0.8rem;
    color: #818cf8;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.org-website:hover {
    color: #a5b4fc;
}

.org-description {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
    line-height: 1.4;
    max-width: 300px;
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

.admin-btn-success {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-btn-success:hover {
    background: rgba(34, 197, 94, 0.25);
    border-color: rgba(34, 197, 94, 0.5);
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
    background: rgba(34, 197, 94, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #4ade80;
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
