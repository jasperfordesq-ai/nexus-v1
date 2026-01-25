<?php
/**
 * Admin View Version Details
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Version ' . $version['version_number'];
$adminPageSubtitle = $document['title'];
$adminPageIcon = 'fa-code-branch';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';
?>

<!-- Breadcrumb -->
<nav class="admin-breadcrumb">
    <a href="<?= $basePath ?>/admin/legal-documents"><i class="fa-solid fa-arrow-left"></i> All Documents</a>
    <span>/</span>
    <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>"><?= htmlspecialchars($document['title']) ?></a>
    <span>/</span>
    <span>Version <?= htmlspecialchars($version['version_number']) ?></span>
</nav>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-code-branch"></i>
            Version <?= htmlspecialchars($version['version_number']) ?>
            <?php if ($version['is_draft']): ?>
                <span class="status-badge status-draft">Draft</span>
            <?php else: ?>
                <span class="status-badge status-published">Published</span>
            <?php endif; ?>
        </h1>
        <p class="admin-page-subtitle">
            <?= htmlspecialchars($document['title']) ?>
            <?php if ($version['version_label']): ?>
             &bull; <?= htmlspecialchars($version['version_label']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <?php if ($version['is_draft']): ?>
        <form action="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $version['id'] ?>/publish" method="POST" style="display: inline;">
            <?= Csrf::input() ?>
            <button type="submit" class="admin-btn admin-btn-success" onclick="return confirm('Publish this version? It will become the current active version.')">
                <i class="fa-solid fa-rocket"></i> Publish
            </button>
        </form>
        <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $version['id'] ?>/edit" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-pen"></i> Edit
        </a>
        <?php else: ?>
        <a href="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $version['id'] ?>/acceptances" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-user-check"></i> View Acceptances (<?= number_format($acceptanceCount) ?>)
        </a>
        <?php if ($version['is_current']): ?>
        <form action="<?= $basePath ?>/admin/legal-documents/<?= $document['id'] ?>/versions/<?= $version['id'] ?>/notify" method="POST" style="display: inline;">
            <?= Csrf::input() ?>
            <button type="submit" class="admin-btn admin-btn-warning" onclick="return confirm('Send email notifications to all users who haven\\'t accepted this version yet?')">
                <i class="fa-solid fa-envelope"></i> Notify Users
            </button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Version Info -->
<div class="version-info-grid">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-purple">
                <i class="fa-solid fa-info-circle"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Version Details</h3>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Version Number</label>
                    <span><?= htmlspecialchars($version['version_number']) ?></span>
                </div>
                <div class="info-item">
                    <label>Version Label</label>
                    <span><?= htmlspecialchars($version['version_label'] ?: 'None') ?></span>
                </div>
                <div class="info-item">
                    <label>Effective Date</label>
                    <span><?= date('F j, Y', strtotime($version['effective_date'])) ?></span>
                </div>
                <div class="info-item">
                    <label>Status</label>
                    <span><?= $version['is_draft'] ? 'Draft' : 'Published' ?></span>
                </div>
                <div class="info-item">
                    <label>Created</label>
                    <span><?= date('M j, Y g:i A', strtotime($version['created_at'])) ?></span>
                </div>
                <?php if (!$version['is_draft']): ?>
                <div class="info-item">
                    <label>Published</label>
                    <span><?= date('M j, Y g:i A', strtotime($version['published_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($version['summary_of_changes']): ?>
            <div class="changes-summary">
                <label>Summary of Changes</label>
                <p><?= nl2br(htmlspecialchars($version['summary_of_changes'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Content Preview -->
<div class="admin-glass-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-indigo">
            <i class="fa-solid fa-file-lines"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Document Content</h3>
            <p class="admin-card-subtitle">Full HTML content of this version</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="content-preview">
            <?= $version['content'] ?>
        </div>
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

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 0.5rem;
}

.status-draft {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-published {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.25rem;
}

.info-item label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 0.5rem;
}

.info-item span {
    font-size: 0.95rem;
    color: #fff;
}

.changes-summary {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

.changes-summary label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 0.5rem;
}

.changes-summary p {
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.6;
    margin: 0;
}

/* Content Preview */
.content-preview {
    background: rgba(30, 41, 59, 0.4);
    border-radius: 12px;
    padding: 2rem;
    max-height: 600px;
    overflow-y: auto;
    font-size: 0.95rem;
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.85);
}

.content-preview h1,
.content-preview h2,
.content-preview h3,
.content-preview h4 {
    color: #fff;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.content-preview h2 {
    font-size: 1.35rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
}

.content-preview p {
    margin-bottom: 1rem;
}

.content-preview ul,
.content-preview ol {
    padding-left: 1.5rem;
    margin-bottom: 1rem;
}

.content-preview li {
    margin-bottom: 0.5rem;
}

.content-preview a {
    color: #818cf8;
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
}

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    border: 1px solid rgba(34, 197, 94, 0.5);
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #16a34a, #15803d);
    transform: translateY(-1px);
}

.admin-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border: 1px solid rgba(245, 158, 11, 0.5);
}

.admin-btn-warning:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
    transform: translateY(-1px);
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #16a34a, #15803d);
    transform: translateY(-1px);
}
</style>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
