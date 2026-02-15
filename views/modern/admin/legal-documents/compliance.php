<?php
/**
 * Admin Legal Compliance Dashboard
 * Overview of document acceptance rates and compliance statistics
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Compliance Dashboard';
$adminPageSubtitle = 'Legal Documents';
$adminPageIcon = 'fa-clipboard-check';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Breadcrumb -->
<nav class="admin-breadcrumb">
    <a href="<?= $basePath ?>/admin-legacy/legal-documents"><i class="fa-solid fa-arrow-left"></i> All Documents</a>
</nav>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-clipboard-check"></i>
            Legal Compliance Dashboard
        </h1>
        <p class="admin-page-subtitle">Monitor document acceptance rates and user compliance across your platform</p>
    </div>
</div>

<!-- Overall Stats -->
<div class="compliance-overview">
    <div class="compliance-stat-card stat-primary">
        <div class="compliance-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="compliance-stat-content">
            <div class="compliance-stat-value"><?= number_format($stats['total_users'] ?? 0) ?></div>
            <div class="compliance-stat-label">Total Active Users</div>
        </div>
    </div>

    <div class="compliance-stat-card stat-success">
        <div class="compliance-stat-icon">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="compliance-stat-content">
            <div class="compliance-stat-value"><?= $stats['overall_compliance_rate'] ?? 0 ?>%</div>
            <div class="compliance-stat-label">Overall Compliance Rate</div>
        </div>
    </div>

    <div class="compliance-stat-card stat-warning">
        <div class="compliance-stat-icon">
            <i class="fa-solid fa-user-clock"></i>
        </div>
        <div class="compliance-stat-content">
            <div class="compliance-stat-value"><?= number_format($stats['users_pending_acceptance'] ?? 0) ?></div>
            <div class="compliance-stat-label">Users Pending Acceptance</div>
        </div>
    </div>

    <div class="compliance-stat-card stat-info">
        <div class="compliance-stat-icon">
            <i class="fa-solid fa-file-contract"></i>
        </div>
        <div class="compliance-stat-content">
            <div class="compliance-stat-value"><?= count($stats['documents'] ?? []) ?></div>
            <div class="compliance-stat-label">Active Documents</div>
        </div>
    </div>
</div>

<!-- Document Breakdown -->
<?php if (!empty($stats['documents'])): ?>
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-indigo">
            <i class="fa-solid fa-chart-bar"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Document Acceptance Breakdown</h3>
            <p class="admin-card-subtitle">Acceptance rates for each legal document</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="compliance-documents">
            <?php foreach ($stats['documents'] as $doc): ?>
            <div class="compliance-doc-row">
                <div class="compliance-doc-info">
                    <div class="compliance-doc-icon">
                        <?php
                        $icons = [
                            'terms' => 'fa-file-contract',
                            'privacy' => 'fa-shield-halved',
                            'cookies' => 'fa-cookie-bite',
                            'accessibility' => 'fa-universal-access',
                        ];
                        $icon = $icons[$doc['document_type']] ?? 'fa-file-lines';
                        ?>
                        <i class="fa-solid <?= $icon ?>"></i>
                    </div>
                    <div class="compliance-doc-details">
                        <div class="compliance-doc-title"><?= htmlspecialchars($doc['title']) ?></div>
                        <div class="compliance-doc-meta">
                            Version <?= htmlspecialchars($doc['version_number'] ?? 'N/A') ?>
                            <?php if ($doc['effective_date']): ?>
                            &bull; Effective <?= date('M j, Y', strtotime($doc['effective_date'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="compliance-doc-progress">
                    <div class="progress-bar-container">
                        <div class="progress-bar <?= $doc['acceptance_rate'] >= 90 ? 'progress-green' : ($doc['acceptance_rate'] >= 50 ? 'progress-yellow' : 'progress-red') ?>"
                             style="width: <?= $doc['acceptance_rate'] ?>%"></div>
                    </div>
                    <div class="progress-label"><?= $doc['acceptance_rate'] ?>%</div>
                </div>

                <div class="compliance-doc-stats">
                    <div class="stat-item stat-accepted">
                        <i class="fa-solid fa-check-circle"></i>
                        <span><?= number_format($doc['users_accepted']) ?> accepted</span>
                    </div>
                    <div class="stat-item stat-pending">
                        <i class="fa-solid fa-clock"></i>
                        <span><?= number_format($doc['users_not_accepted']) ?> pending</span>
                    </div>
                </div>

                <div class="compliance-doc-actions">
                    <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $doc['id'] ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
                        <i class="fa-solid fa-eye"></i> View
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $doc['id'] ?>/export" class="admin-btn admin-btn-secondary admin-btn-sm">
                        <i class="fa-solid fa-download"></i> Export
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="admin-glass-card">
    <div class="admin-empty-state">
        <div class="admin-empty-icon">
            <i class="fa-solid fa-chart-pie"></i>
        </div>
        <h3 class="admin-empty-title">No Compliance Data Yet</h3>
        <p class="admin-empty-text">Create legal documents and publish versions to start tracking user acceptance.</p>
        <a href="<?= $basePath ?>/admin-legacy/legal-documents/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
            <i class="fa-solid fa-plus"></i> Create First Document
        </a>
    </div>
</div>
<?php endif; ?>

<!-- GDPR Info Card -->
<div class="admin-glass-card help-card" style="margin-top: 1.5rem;">
    <div class="admin-card-body" style="padding: 1.5rem;">
        <div class="gdpr-info">
            <div class="gdpr-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="gdpr-content">
                <h4>GDPR Compliance Tracking</h4>
                <p>This system records explicit user consent for legal documents including timestamp, IP address, and user agent for audit purposes. All acceptance records can be exported as CSV for regulatory compliance.</p>
                <div class="gdpr-features">
                    <span><i class="fa-solid fa-check"></i> Timestamped acceptances</span>
                    <span><i class="fa-solid fa-check"></i> IP address tracking</span>
                    <span><i class="fa-solid fa-check"></i> Version history</span>
                    <span><i class="fa-solid fa-check"></i> CSV export</span>
                </div>
            </div>
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

/* Overview Stats */
.compliance-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.compliance-stat-card {
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-primary {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.15));
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.stat-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(16, 185, 129, 0.15));
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.stat-warning {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(234, 88, 12, 0.15));
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.stat-info {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.15), rgba(14, 165, 233, 0.15));
    border: 1px solid rgba(6, 182, 212, 0.3);
}

.compliance-stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-primary .compliance-stat-icon {
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8;
}

.stat-success .compliance-stat-icon {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
}

.stat-warning .compliance-stat-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

.stat-info .compliance-stat-icon {
    background: rgba(6, 182, 212, 0.2);
    color: #22d3ee;
}

.compliance-stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
}

.compliance-stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 0.25rem;
}

/* Document Breakdown */
.compliance-documents {
    display: flex;
    flex-direction: column;
}

.compliance-doc-row {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.25rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.compliance-doc-row:last-child {
    border-bottom: none;
}

.compliance-doc-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    min-width: 200px;
}

.compliance-doc-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: rgba(99, 102, 241, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: #818cf8;
    flex-shrink: 0;
}

.compliance-doc-title {
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.25rem;
}

.compliance-doc-meta {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.compliance-doc-progress {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    max-width: 300px;
}

.progress-bar-container {
    flex: 1;
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-green {
    background: linear-gradient(90deg, #22c55e, #4ade80);
}

.progress-yellow {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.progress-red {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

.progress-label {
    font-weight: 700;
    font-size: 1rem;
    color: #fff;
    width: 50px;
    text-align: right;
}

.compliance-doc-stats {
    display: flex;
    gap: 1.5rem;
    min-width: 200px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
}

.stat-accepted {
    color: #4ade80;
}

.stat-pending {
    color: #fbbf24;
}

.compliance-doc-actions {
    display: flex;
    gap: 0.5rem;
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
    border-color: rgba(99, 102, 241, 0.4);
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

/* GDPR Info Card */
.help-card {
    background: rgba(6, 182, 212, 0.08);
    border: 1px solid rgba(6, 182, 212, 0.2);
}

.gdpr-info {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}

.gdpr-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: rgba(6, 182, 212, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #22d3ee;
    flex-shrink: 0;
}

.gdpr-content h4 {
    margin: 0 0 0.5rem 0;
    color: #fff;
    font-size: 1rem;
}

.gdpr-content p {
    margin: 0 0 1rem 0;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    line-height: 1.6;
}

.gdpr-features {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.gdpr-features span {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.8);
}

.gdpr-features i {
    color: #22d3ee;
}

/* Responsive */
@media (max-width: 1024px) {
    .compliance-doc-row {
        flex-wrap: wrap;
    }

    .compliance-doc-progress {
        flex: 1 1 100%;
        max-width: none;
        order: 10;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(99, 102, 241, 0.1);
    }

    .compliance-doc-stats {
        min-width: auto;
    }
}

@media (max-width: 768px) {
    .compliance-overview {
        grid-template-columns: 1fr 1fr;
    }

    .compliance-doc-info {
        flex: 1 1 100%;
    }

    .compliance-doc-stats {
        flex: 1 1 100%;
        margin-top: 0.5rem;
    }

    .compliance-doc-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .gdpr-info {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .compliance-overview {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
