<?php
/**
 * Modern Consent Detail View - Gold Standard v2.0
 * View consent type details and user consent records
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$adminPageTitle = 'Consent Details';
$adminPageSubtitle = 'Enterprise';
$adminPageIcon = 'fa-user-shield';
require dirname(__DIR__, 2) . '/partials/admin-header.php';
$currentSection = 'gdpr';
$currentPage = 'consents';

$consentType = $consentType ?? [];
$consents = $consents ?? [];
?>

<style>
.consent-detail-container { max-width: 1200px; margin: 0 auto; padding: 24px; }

.nexus-card { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 16px; overflow: hidden; margin-bottom: 1.5rem; }
.nexus-card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(99, 102, 241, 0.2); display: flex; justify-content: space-between; align-items: center; }
.nexus-card-header h3 { font-size: 1rem; font-weight: 600; color: #f1f5f9; margin: 0; display: flex; align-items: center; gap: 0.75rem; }
.nexus-card-header h3 i { color: #64748b; }
.nexus-card-body { padding: 1.5rem; }

.detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
@media (max-width: 768px) { .detail-grid { grid-template-columns: 1fr; } }

.detail-item label { display: block; font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.375rem; }
.detail-item .value { font-size: 0.95rem; color: #f1f5f9; }
.detail-item .value.mono { font-family: 'Monaco', 'Menlo', monospace; }

.consent-text-box { background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 10px; padding: 1rem; margin-top: 1rem; }
.consent-text-box label { display: block; font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem; }
.consent-text-box p { color: #f1f5f9; font-size: 0.9rem; line-height: 1.6; margin: 0; }

.badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
.badge-success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.badge-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
.badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.badge-info { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }
.badge-secondary { background: rgba(107, 114, 128, 0.2); color: #94a3b8; }

.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
@media (max-width: 768px) { .stats-row { grid-template-columns: 1fr; } }

.stat-card { padding: 1.25rem; border-radius: 12px; text-align: center; }
.stat-card.success { background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); }
.stat-card.danger { background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%); border: 1px solid rgba(239, 68, 68, 0.3); }
.stat-card.info { background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0.05) 100%); border: 1px solid rgba(99, 102, 241, 0.3); }
.stat-card .stat-value { font-size: 2rem; font-weight: 700; line-height: 1; }
.stat-card.success .stat-value { color: #10b981; }
.stat-card.danger .stat-value { color: #ef4444; }
.stat-card.info .stat-value { color: #6366f1; }
.stat-card .stat-label { font-size: 0.8rem; color: #94a3b8; margin-top: 0.5rem; }

.consents-table { width: 100%; border-collapse: collapse; }
.consents-table th { background: rgba(99, 102, 241, 0.1); padding: 0.875rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
.consents-table td { padding: 1rem; border-bottom: 1px solid rgba(99, 102, 241, 0.2); font-size: 0.9rem; color: #f1f5f9; vertical-align: middle; }
.consents-table tr:hover td { background: rgba(99, 102, 241, 0.1); }

.user-info { line-height: 1.4; }
.user-info strong { color: #f1f5f9; }
.user-info small { color: #94a3b8; }

.empty-state { padding: 3rem 2rem; text-align: center; }
.empty-state i { font-size: 3rem; color: #64748b; margin-bottom: 1rem; }
.empty-state h5 { color: #94a3b8; font-weight: 600; margin-bottom: 0.5rem; }
.empty-state p { color: #64748b; margin: 0; }

.nexus-btn { display: inline-flex; align-items: center; padding: 0.625rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; text-decoration: none; transition: all 0.2s; border: none; cursor: pointer; }
.nexus-btn-outline { background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(99, 102, 241, 0.2); color: #f1f5f9; }
.nexus-btn-outline:hover { background: rgba(99, 102, 241, 0.1); border-color: #6366f1; color: #6366f1; }
.nexus-btn i { margin-right: 0.5rem; }
</style>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-user-shield"></i>
            Consent Details
        </h1>
        <p class="admin-page-subtitle">View consent history and permissions</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/consents" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> All Consents
        </a>
    </div>
</div>

<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<div class="consent-detail-container">
    <!-- Consent Type Details -->
    <div class="nexus-card">
        <div class="nexus-card-header">
            <h3><i class="fa-solid fa-file-contract"></i> Consent Type Details</h3>
            <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/consents" class="nexus-btn nexus-btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>
        <div class="nexus-card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Slug</label>
                    <div class="value mono"><?= htmlspecialchars($consentType['slug'] ?? '') ?></div>
                </div>
                <div class="detail-item">
                    <label>Category</label>
                    <div class="value"><?= ucfirst($consentType['category'] ?? 'general') ?></div>
                </div>
                <div class="detail-item">
                    <label>Legal Basis</label>
                    <div class="value"><?= ucfirst(str_replace('_', ' ', $consentType['legal_basis'] ?? 'consent')) ?></div>
                </div>
                <div class="detail-item">
                    <label>Current Version</label>
                    <div class="value">v<?= htmlspecialchars($consentType['current_version'] ?? '1.0') ?></div>
                </div>
                <div class="detail-item">
                    <label>Required</label>
                    <div class="value">
                        <?php if ($consentType['is_required'] ?? false): ?>
                            <span class="badge badge-warning"><i class="fa-solid fa-asterisk"></i> Required</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Optional</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <div class="value">
                        <?php if ($consentType['is_active'] ?? true): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($consentType['description'])): ?>
                <div class="consent-text-box">
                    <label>Description</label>
                    <p><?= htmlspecialchars($consentType['description']) ?></p>
                </div>
            <?php endif; ?>

            <div class="consent-text-box">
                <label>Current Consent Text</label>
                <p><?= htmlspecialchars($consentType['current_text'] ?? 'No consent text defined') ?></p>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <?php
    $givenCount = 0;
    $withdrawnCount = 0;
    foreach ($consents as $c) {
        if ($c['consent_given']) $givenCount++;
        else $withdrawnCount++;
    }
    $totalCount = count($consents);
    ?>
    <div class="stats-row">
        <div class="stat-card info">
            <div class="stat-value"><?= $totalCount ?></div>
            <div class="stat-label">Total Records</div>
        </div>
        <div class="stat-card success">
            <div class="stat-value"><?= $givenCount ?></div>
            <div class="stat-label">Active Consents</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-value"><?= $withdrawnCount ?></div>
            <div class="stat-label">Withdrawn</div>
        </div>
    </div>

    <!-- User Consents -->
    <div class="nexus-card">
        <div class="nexus-card-header">
            <h3><i class="fa-solid fa-users"></i> User Consent Records</h3>
            <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/consents/export?type=<?= urlencode($consentType['slug'] ?? '') ?>" class="nexus-btn nexus-btn-outline">
                <i class="fa-solid fa-download"></i> Export
            </a>
        </div>
        <table class="consents-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Status</th>
                    <th>Version</th>
                    <th>Given At</th>
                    <th>Withdrawn At</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($consents)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fa-solid fa-inbox"></i>
                                <h5>No consent records</h5>
                                <p>No users have interacted with this consent type yet</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($consents as $consent): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <strong><?= htmlspecialchars($consent['email'] ?? '') ?></strong>
                                    <br><small><?= htmlspecialchars(trim(($consent['first_name'] ?? '') . ' ' . ($consent['last_name'] ?? ''))) ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if ($consent['consent_given']): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check"></i> Given</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><i class="fa-solid fa-times"></i> Withdrawn</span>
                                <?php endif; ?>
                            </td>
                            <td>v<?= htmlspecialchars($consent['consent_version'] ?? '1.0') ?></td>
                            <td>
                                <?php if (!empty($consent['given_at'])): ?>
                                    <span title="<?= $consent['given_at'] ?>">
                                        <?= date('M j, Y H:i', strtotime($consent['given_at'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #64748b;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($consent['withdrawn_at'])): ?>
                                    <span title="<?= $consent['withdrawn_at'] ?>">
                                        <?= date('M j, Y H:i', strtotime($consent['withdrawn_at'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #64748b;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code style="font-size: 0.8rem; color: #94a3b8;">
                                    <?= htmlspecialchars($consent['ip_address'] ?? '-') ?>
                                </code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
