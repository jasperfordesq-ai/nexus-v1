<?php
/**
 * Admin Legal Document Detail View
 * Show document with version history and stats
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = $document['title'] ?? 'Legal Document';
$adminPageSubtitle = 'Version Management';
$adminPageIcon = 'fa-scale-balanced';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Breadcrumb -->
<nav class="admin-breadcrumb">
    <a href="<?= $basePath ?>/admin/legal-documents"><i class="fa-solid fa-arrow-left"></i> All Documents</a>
</nav>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <?php
            $icons = [
                'terms' => 'fa-file-contract',
                'privacy' => 'fa-shield-halved',
                'cookies' => 'fa-cookie-bite',
                'accessibility' => 'fa-universal-access'
            ];
            $icon = $icons[$document['document_type']] ?? 'fa-file-lines';
            ?>
            <i class="fa-solid <?= $icon ?>"></i>
            <?= htmlspecialchars($document['title']) ?>
        </h1>
        <p class="admin-page-subtitle">
            Type: <?= htmlspecialchars($document['document_type']) ?> |
            Current Version: <?= htmlspecialchars($document['version_number'] ?? 'None') ?> |
            <?= $document['is_active'] ? 'Active' : 'Inactive' ?>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> New Version
        </a>
        <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/edit" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-cog"></i> Settings
        </a>
        <a href="<?= $basePath ?>/<?= $document['slug'] ?>" class="admin-btn admin-btn-secondary" target="_blank">
            <i class="fa-solid fa-external-link"></i> View Public
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <?= htmlspecialchars($_SESSION['flash_success']) ?>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <?= htmlspecialchars($_SESSION['flash_error']) ?>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<!-- Stats Row -->
<?php if (!empty($stats)): ?>
<div class="admin-stats-grid stats-4">
    <?php
    $totalAcceptances = 0;
    $currentVersionAcceptances = 0;
    foreach ($stats as $s) {
        $totalAcceptances += $s['total_acceptances'];
        if ($s['is_current']) {
            $currentVersionAcceptances = $s['total_acceptances'];
        }
    }
    ?>
    <div class="admin-stat-card">
        <div class="admin-stat-icon stat-icon-indigo">
            <i class="fa-solid fa-code-branch"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= count($versions) ?></div>
            <div class="admin-stat-label">Total Versions</div>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon stat-icon-green">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($currentVersionAcceptances) ?></div>
            <div class="admin-stat-label">Current Version Acceptances</div>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon stat-icon-blue">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totalAcceptances) ?></div>
            <div class="admin-stat-label">All-Time Acceptances</div>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon stat-icon-cyan">
            <i class="fa-solid fa-calendar"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $document['effective_date'] ? date('M j, Y', strtotime($document['effective_date'])) : 'N/A' ?></div>
            <div class="admin-stat-label">Current Effective Date</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Versions Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-purple">
            <i class="fa-solid fa-code-branch"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Version History</h3>
            <p class="admin-card-subtitle">All versions of this document, newest first</p>
        </div>
        <div class="admin-card-header-actions">
            <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/compare" class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-code-compare"></i> Compare Versions
            </a>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($versions)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-code-branch"></i>
            </div>
            <h3 class="admin-empty-title">No Versions Yet</h3>
            <p class="admin-empty-text">Create the first version of this document.</p>
            <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i> Create Version 1.0
            </a>
        </div>
        <?php else: ?>
        <div class="versions-list">
            <?php foreach ($versions as $v): ?>
            <div class="version-row <?= $v['is_current'] ? 'version-current' : '' ?> <?= $v['is_draft'] ? 'version-draft' : '' ?>">
                <div class="version-number-badge <?= $v['is_current'] ? 'current' : ($v['is_draft'] ? 'draft' : 'archived') ?>">
                    v<?= htmlspecialchars($v['version_number']) ?>
                </div>
                <div class="version-info">
                    <div class="version-title-row">
                        <span class="version-label">
                            <?= $v['version_label'] ? htmlspecialchars($v['version_label']) : 'Version ' . htmlspecialchars($v['version_number']) ?>
                        </span>
                        <?php if ($v['is_current']): ?>
                            <span class="version-status status-current">
                                <i class="fa-solid fa-check-circle"></i> Current
                            </span>
                        <?php elseif ($v['is_draft']): ?>
                            <span class="version-status status-draft">
                                <i class="fa-solid fa-pen"></i> Draft
                            </span>
                        <?php else: ?>
                            <span class="version-status status-archived">
                                <i class="fa-solid fa-archive"></i> Archived
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="version-meta">
                        <span><i class="fa-solid fa-calendar"></i> Effective: <?= date('M j, Y', strtotime($v['effective_date'])) ?></span>
                        <span><i class="fa-solid fa-user"></i> By: <?= htmlspecialchars($v['created_by_name'] ?? 'Unknown') ?></span>
                        <span><i class="fa-solid fa-clock"></i> Created: <?= date('M j, Y H:i', strtotime($v['created_at'])) ?></span>
                        <?php if ($v['published_at']): ?>
                        <span><i class="fa-solid fa-rocket"></i> Published: <?= date('M j, Y H:i', strtotime($v['published_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($v['summary_of_changes']): ?>
                    <div class="version-changes">
                        <strong>Changes:</strong> <?= htmlspecialchars($v['summary_of_changes']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="version-stats">
                    <?php
                    $versionStats = array_filter($stats, fn($s) => $s['version_id'] == $v['id']);
                    $acceptCount = !empty($versionStats) ? reset($versionStats)['total_acceptances'] : 0;
                    ?>
                    <div class="version-stat">
                        <span class="stat-value"><?= number_format($acceptCount) ?></span>
                        <span class="stat-label">acceptances</span>
                    </div>
                </div>
                <div class="version-actions">
                    <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $v['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="View Version">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    <?php if ($v['is_draft']): ?>
                        <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $v['id'] ?>/edit" class="admin-btn admin-btn-secondary admin-btn-sm" title="Edit Draft">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <form action="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $v['id'] ?>/publish" method="POST" style="display:inline;">
                            <?= Csrf::input() ?>
                            <button type="submit" class="admin-btn admin-btn-success admin-btn-sm" title="Publish Version" onclick="return confirm('Publish this version? It will become the current active version.');">
                                <i class="fa-solid fa-rocket"></i>
                            </button>
                        </form>
                        <form action="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $v['id'] ?>/delete" method="POST" style="display:inline;">
                            <?= Csrf::input() ?>
                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" title="Delete Draft" onclick="return confirm('Delete this draft? This cannot be undone.');">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    <?php elseif (!$v['is_current']): ?>
                        <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $v['id'] ?>/acceptances" class="admin-btn admin-btn-secondary admin-btn-sm" title="View Acceptances">
                            <i class="fa-solid fa-users"></i>
                        </a>
                    <?php else: ?>
                        <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $v['id'] ?>/acceptances" class="admin-btn admin-btn-primary admin-btn-sm" title="View Acceptances">
                            <i class="fa-solid fa-users"></i> Acceptances
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Export Card -->
<div class="admin-glass-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-file-export"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Compliance Export</h3>
            <p class="admin-card-subtitle">Export acceptance records for audits and regulatory review</p>
        </div>
    </div>
    <div class="admin-card-body">
        <form action="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/export" method="GET" class="export-form">
            <div class="export-fields">
                <div class="form-group">
                    <label>Start Date (optional)</label>
                    <input type="date" name="start_date" class="form-input">
                </div>
                <div class="form-group">
                    <label>End Date (optional)</label>
                    <input type="date" name="end_date" class="form-input">
                </div>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-download"></i> Export CSV
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Breadcrumb */
.admin-breadcrumb {
    margin-bottom: 1rem;
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

/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.admin-stats-grid.stats-4 {
    grid-template-columns: repeat(4, 1fr);
}

.admin-stat-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-icon-indigo {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
}

.stat-icon-green {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
}

.stat-icon-blue {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}

.stat-icon-cyan {
    background: rgba(6, 182, 212, 0.15);
    color: #22d3ee;
}

.admin-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
}

.admin-stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

/* Versions List */
.versions-list {
    display: flex;
    flex-direction: column;
}

.version-row {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem 1.5rem;
    background: rgba(30, 41, 59, 0.3);
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s;
}

.version-row:last-child {
    border-bottom: none;
}

.version-row:hover {
    background: rgba(99, 102, 241, 0.08);
}

.version-row.version-current {
    background: rgba(34, 197, 94, 0.08);
    border-left: 3px solid #4ade80;
}

.version-row.version-draft {
    background: rgba(245, 158, 11, 0.05);
    border-left: 3px solid #fbbf24;
}

.version-number-badge {
    min-width: 60px;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.9rem;
    text-align: center;
}

.version-number-badge.current {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.4);
}

.version-number-badge.draft {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.4);
}

.version-number-badge.archived {
    background: rgba(100, 116, 139, 0.2);
    color: #94a3b8;
    border: 1px solid rgba(100, 116, 139, 0.4);
}

.version-info {
    flex: 1;
    min-width: 0;
}

.version-title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.version-label {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

.version-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-current {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-draft {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-archived {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(100, 116, 139, 0.3);
}

.version-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    flex-wrap: wrap;
}

.version-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.version-changes {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    padding: 0.5rem;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 6px;
}

.version-stats {
    min-width: 100px;
    text-align: center;
}

.version-stat .stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    display: block;
}

.version-stat .stat-label {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
}

.version-actions {
    display: flex;
    gap: 0.5rem;
}

/* Export Form */
.export-form {
    max-width: 600px;
}

.export-fields {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
    flex-wrap: wrap;
}

.form-group {
    flex: 1;
    min-width: 150px;
}

.form-group label {
    display: block;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 0.5rem;
}

.form-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 0.9rem;
}

.form-input:focus {
    outline: none;
    border-color: #6366f1;
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

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-success {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Alerts */
.admin-alert {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
}

.admin-alert-success {
    background: rgba(34, 197, 94, 0.15);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #4ade80;
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
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
    .admin-stats-grid.stats-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .admin-stats-grid.stats-4 {
        grid-template-columns: 1fr;
    }

    .version-row {
        flex-wrap: wrap;
        padding: 1rem;
    }

    .version-info {
        flex: 1 1 100%;
        order: 2;
        margin-top: 0.75rem;
    }

    .version-number-badge {
        order: 1;
    }

    .version-stats {
        order: 3;
        width: 100%;
        text-align: left;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(99, 102, 241, 0.1);
    }

    .version-actions {
        order: 4;
        width: 100%;
        margin-top: 0.75rem;
    }

    .export-fields {
        flex-direction: column;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
