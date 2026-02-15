<?php
/**
 * Admin View Acceptance Records for a Version
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Acceptances';
$adminPageSubtitle = $document['title'] . ' v' . $version['version_number'];
$adminPageIcon = 'fa-user-check';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Breadcrumb -->
<nav class="admin-breadcrumb">
    <a href="<?= $basePath ?>/admin-legacy/legal-documents"><i class="fa-solid fa-arrow-left"></i> All Documents</a>
    <span>/</span>
    <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>"><?= htmlspecialchars($document['title']) ?></a>
    <span>/</span>
    <span>Version <?= htmlspecialchars($version['version_number']) ?> Acceptances</span>
</nav>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-user-check"></i>
            Acceptance Records
        </h1>
        <p class="admin-page-subtitle">
            <?= htmlspecialchars($document['title']) ?> - Version <?= htmlspecialchars($version['version_number']) ?>
            &bull; <?= number_format($pagination['count']) ?> total acceptances
        </p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>/export" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-download"></i> Export All (CSV)
        </a>
    </div>
</div>

<!-- Acceptances Table -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-green">
            <i class="fa-solid fa-list"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">User Acceptances</h3>
            <p class="admin-card-subtitle">Showing page <?= $pagination['current'] ?> of <?= $pagination['total'] ?></p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($acceptances)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-user-clock"></i>
            </div>
            <h3 class="admin-empty-title">No Acceptances Yet</h3>
            <p class="admin-empty-text">No users have accepted this version of the document yet.</p>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Accepted At</th>
                        <th>Method</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($acceptances as $record): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">
                                    <?= strtoupper(substr($record['user_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div class="user-info">
                                    <div class="user-name"><?= htmlspecialchars($record['user_name'] ?? 'Unknown') ?></div>
                                    <div class="user-email"><?= htmlspecialchars($record['user_email'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="date-cell">
                                <div class="date-primary"><?= date('M j, Y', strtotime($record['accepted_at'])) ?></div>
                                <div class="date-secondary"><?= date('g:i A', strtotime($record['accepted_at'])) ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="method-badge method-<?= $record['acceptance_method'] ?? 'unknown' ?>">
                                <?= ucfirst($record['acceptance_method'] ?? 'Unknown') ?>
                            </span>
                        </td>
                        <td>
                            <span class="ip-address"><?= htmlspecialchars($record['ip_address'] ?? 'N/A') ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['total'] > 1): ?>
        <div class="admin-pagination">
            <?php if ($pagination['current'] > 1): ?>
            <a href="?page=<?= $pagination['current'] - 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>

            <span class="pagination-info">Page <?= $pagination['current'] ?> of <?= $pagination['total'] ?></span>

            <?php if ($pagination['current'] < $pagination['total']): ?>
            <a href="?page=<?= $pagination['current'] + 1 ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                Next <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

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

/* Table */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th,
.admin-table td {
    padding: 1rem 1.25rem;
    text-align: left;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.admin-table th {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.5);
    background: rgba(30, 41, 59, 0.3);
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

/* User Cell */
.user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85rem;
    color: white;
}

.user-name {
    font-weight: 500;
    color: #fff;
}

.user-email {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Date Cell */
.date-primary {
    color: #fff;
}

.date-secondary {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Method Badge */
.method-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.method-registration {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.method-login {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.method-settings {
    background: rgba(6, 182, 212, 0.15);
    color: #22d3ee;
    border: 1px solid rgba(6, 182, 212, 0.3);
}

.method-unknown {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(100, 116, 139, 0.3);
}

/* IP Address */
.ip-address {
    font-family: 'Fira Code', monospace;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
}

/* Pagination */
.admin-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1rem;
    border-top: 1px solid rgba(99, 102, 241, 0.1);
}

.pagination-info {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
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

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
}

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
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
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
