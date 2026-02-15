<?php
/**
 * Admin Legal Documents Manager
 * List all legal documents with compliance statistics
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Legal Documents';
$adminPageSubtitle = 'Compliance';
$adminPageIcon = 'fa-scale-balanced';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-scale-balanced"></i>
            Legal Documents
        </h1>
        <p class="admin-page-subtitle">Manage Terms of Service, Privacy Policy, and other legal documents with version control</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/legal-documents/compliance" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-chart-pie"></i> Compliance Dashboard
        </a>
        <a href="<?= $basePath ?>/admin-legacy/legal-documents/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> New Document
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

<!-- Compliance Summary -->
<?php if (!empty($complianceStats['documents'])): ?>
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-icon stat-icon-blue">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($complianceStats['total_users']) ?></div>
            <div class="admin-stat-label">Active Users</div>
        </div>
    </div>
    <?php foreach ($complianceStats['documents'] as $doc): ?>
    <div class="admin-stat-card">
        <div class="admin-stat-icon <?= $doc['acceptance_rate'] >= 90 ? 'stat-icon-green' : ($doc['acceptance_rate'] >= 50 ? 'stat-icon-yellow' : 'stat-icon-red') ?>">
            <i class="fa-solid <?= $doc['document_type'] === 'terms' ? 'fa-file-contract' : ($doc['document_type'] === 'privacy' ? 'fa-shield-halved' : 'fa-file-lines') ?>"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $doc['acceptance_rate'] ?>%</div>
            <div class="admin-stat-label"><?= htmlspecialchars($doc['title']) ?> v<?= htmlspecialchars($doc['version_number'] ?? '?') ?></div>
            <div class="admin-stat-sub"><?= number_format($doc['users_accepted']) ?> accepted, <?= number_format($doc['users_not_accepted']) ?> pending</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Documents Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-indigo">
            <i class="fa-solid fa-file-lines"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">All Legal Documents</h3>
            <p class="admin-card-subtitle">Click on a document to manage versions and view acceptance records</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($documents)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-file-circle-plus"></i>
            </div>
            <h3 class="admin-empty-title">No Legal Documents Yet</h3>
            <p class="admin-empty-text">Create your first legal document to start tracking versions and user acceptances.</p>
            <a href="<?= $basePath ?>/admin-legacy/legal-documents/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i> Create First Document
            </a>
        </div>
        <?php else: ?>
        <div class="legal-docs-list">
            <?php foreach ($documents as $doc): ?>
            <div class="legal-doc-row">
                <div class="legal-doc-icon">
                    <?php
                    $icons = [
                        'terms' => 'fa-file-contract',
                        'privacy' => 'fa-shield-halved',
                        'cookies' => 'fa-cookie-bite',
                        'accessibility' => 'fa-universal-access',
                        'community_guidelines' => 'fa-handshake',
                        'acceptable_use' => 'fa-check-circle'
                    ];
                    $icon = $icons[$doc['document_type']] ?? 'fa-file-lines';
                    ?>
                    <i class="fa-solid <?= $icon ?>"></i>
                </div>
                <div class="legal-doc-info">
                    <div class="legal-doc-title-row">
                        <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $doc['id'] ?>" class="legal-doc-title">
                            <?= htmlspecialchars($doc['title']) ?>
                        </a>
                        <?php if ($doc['is_active']): ?>
                            <span class="legal-doc-status status-active">
                                <i class="fa-solid fa-check-circle"></i> Active
                            </span>
                        <?php else: ?>
                            <span class="legal-doc-status status-inactive">
                                <i class="fa-solid fa-pause-circle"></i> Inactive
                            </span>
                        <?php endif; ?>
                        <?php if ($doc['requires_acceptance']): ?>
                            <span class="legal-doc-badge badge-required">
                                <i class="fa-solid fa-asterisk"></i> Required
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="legal-doc-meta">
                        <span><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($doc['document_type']) ?></span>
                        <span><i class="fa-solid fa-code-branch"></i> v<?= htmlspecialchars($doc['version_number'] ?? 'No version') ?></span>
                        <span><i class="fa-solid fa-layer-group"></i> <?= $doc['version_count'] ?? 0 ?> version(s)</span>
                        <?php if ($doc['effective_date']): ?>
                        <span><i class="fa-solid fa-calendar"></i> Effective: <?= date('M j, Y', strtotime($doc['effective_date'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="legal-doc-actions">
                    <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $doc['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm" title="Manage Versions">
                        <i class="fa-solid fa-code-branch"></i> <span class="btn-text">Versions</span>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $doc['id'] ?>/versions/create" class="admin-btn admin-btn-success admin-btn-sm" title="Create New Version">
                        <i class="fa-solid fa-plus"></i>
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $doc['id'] ?>/edit" class="admin-btn admin-btn-secondary admin-btn-sm" title="Edit Settings">
                        <i class="fa-solid fa-cog"></i>
                    </a>
                    <a href="<?= $basePath ?>/<?= $doc['slug'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm" title="View Public Page" target="_blank">
                        <i class="fa-solid fa-external-link"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Help Card -->
<div class="admin-glass-card help-card" style="margin-top: 1.5rem;">
    <div class="admin-card-body" style="padding: 1.25rem;">
        <div class="help-items">
            <div class="help-item">
                <i class="fa-solid fa-code-branch"></i>
                <span>Each document has versioned history</span>
            </div>
            <div class="help-item">
                <i class="fa-solid fa-user-check"></i>
                <span>Track user acceptances per version</span>
            </div>
            <div class="help-item">
                <i class="fa-solid fa-file-export"></i>
                <span>Export acceptance records for audits</span>
            </div>
            <div class="help-item">
                <i class="fa-solid fa-shield-halved"></i>
                <span>GDPR compliant tracking</span>
            </div>
        </div>
    </div>
</div>

<style>
/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
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

.stat-icon-blue {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}

.stat-icon-green {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
}

.stat-icon-yellow {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
}

.stat-icon-red {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.admin-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
}

.admin-stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
}

.admin-stat-sub {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 2px;
}

/* Legal Docs List */
.legal-docs-list {
    display: flex;
    flex-direction: column;
}

.legal-doc-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    background: rgba(30, 41, 59, 0.3);
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: all 0.2s;
}

.legal-doc-row:last-child {
    border-bottom: none;
}

.legal-doc-row:hover {
    background: rgba(99, 102, 241, 0.08);
}

.legal-doc-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(99, 102, 241, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: #818cf8;
    flex-shrink: 0;
}

.legal-doc-info {
    flex: 1;
    min-width: 0;
}

.legal-doc-title-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
}

.legal-doc-title {
    font-weight: 600;
    font-size: 1rem;
    color: #fff;
    text-decoration: none;
}

.legal-doc-title:hover {
    color: #818cf8;
}

.legal-doc-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-inactive {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(100, 116, 139, 0.3);
}

.legal-doc-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-required {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.legal-doc-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    flex-wrap: wrap;
}

.legal-doc-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.legal-doc-meta i {
    opacity: 0.7;
}

.legal-doc-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: flex-end;
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

.admin-btn-success {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.admin-btn-success:hover {
    background: rgba(34, 197, 94, 0.25);
    border-color: rgba(34, 197, 94, 0.5);
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

/* Help Card */
.help-card {
    background: rgba(6, 182, 212, 0.1);
    border: 1px solid rgba(6, 182, 212, 0.2);
}

.help-items {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.help-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
}

.help-item i {
    color: #22d3ee;
}

/* Responsive */
@media (max-width: 768px) {
    .legal-doc-row {
        flex-wrap: wrap;
        padding: 1rem;
    }

    .legal-doc-info {
        flex: 1 1 calc(100% - 60px);
    }

    .legal-doc-actions {
        width: 100%;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(99, 102, 241, 0.1);
    }

    .legal-doc-actions .btn-text {
        display: none;
    }

    .help-items {
        flex-direction: column;
        gap: 0.75rem;
    }

    .admin-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
