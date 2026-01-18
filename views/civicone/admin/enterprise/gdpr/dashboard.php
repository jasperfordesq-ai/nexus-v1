<?php
/**
 * GDPR Dashboard - Gold Standard v2.0
 * STANDALONE Admin Interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

// Admin header configuration
$adminPageTitle = 'GDPR Compliance';
$adminPageSubtitle = 'Data Protection & Privacy';
$adminPageIcon = 'fa-shield-halved';

// Extract stats with defaults
$complianceScore = $stats['compliance_score'] ?? 85;
$pendingCount = $stats['pending_requests'] ?? $stats['pending_count'] ?? 0;
$overdueCount = $stats['overdue_requests'] ?? $stats['overdue_count'] ?? 0;
$completedCount = $stats['completed_requests'] ?? 0;
$avgProcessingHours = round($stats['avg_processing_hours'] ?? $stats['avg_processing_time'] ?? 0, 1);
$consentCoverage = number_format($stats['consent_coverage'] ?? 0, 1);
$usersWithConsent = number_format($stats['users_with_consent'] ?? 0);
$totalUsers = number_format($stats['total_users'] ?? 0);
$activeBreaches = $stats['active_breaches'] ?? 0;
$totalRequests = $stats['total_requests'] ?? 0;

// Include standard admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'gdpr';
$currentPage = 'dashboard';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-shield-halved"></i>
            GDPR Compliance
        </h1>
        <p class="admin-page-subtitle">Data protection & privacy management center</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/enterprise" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Enterprise Hub
        </a>
        <a href="<?= $basePath ?>/admin/enterprise/gdpr/requests/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> New Request
        </a>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<style>
/* Compliance Score Section */
.score-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.compliance-score-card {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    box-shadow: 0 15px 40px rgba(99, 102, 241, 0.35);
    position: relative;
    overflow: hidden;
}

.compliance-score-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
}

.circular-chart {
    width: 160px;
    height: 160px;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
}

.circle-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.2);
    stroke-width: 3.5;
}

.circle {
    fill: none;
    stroke: white;
    stroke-width: 3;
    stroke-linecap: round;
    animation: progress 1s ease-out forwards;
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}

@keyframes progress {
    0% { stroke-dasharray: 0 100; }
}

.score-text {
    fill: white;
    font-size: 0.5em;
    text-anchor: middle;
    font-weight: 800;
}

.compliance-score-card h4 {
    font-size: 1.35rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    position: relative;
    z-index: 1;
}

.compliance-score-card p {
    font-size: 0.85rem;
    opacity: 0.85;
    text-align: center;
    margin: 0;
    max-width: 240px;
    position: relative;
    z-index: 1;
}

/* Stats Mini Grid */
.stats-mini-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.mini-stat-card {
    background: rgba(10, 22, 40, 0.8);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 16px;
    padding: 1.25rem;
    transition: all 0.3s;
}

.mini-stat-card:hover {
    transform: translateY(-3px);
    border-color: rgba(6, 182, 212, 0.3);
    box-shadow: 0 12px 30px rgba(0,0,0,0.25);
}

.mini-stat-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.mini-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}

.mini-stat-icon.warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3); }
.mini-stat-icon.success { background: linear-gradient(135deg, #10b981, #34d399); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
.mini-stat-icon.info { background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3); }
.mini-stat-icon.danger { background: linear-gradient(135deg, #ef4444, #f87171); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
.mini-stat-icon.indigo { background: linear-gradient(135deg, #6366f1, #8b5cf6); box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); }

.mini-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}

.mini-stat-label {
    color: rgba(255,255,255,0.5);
    font-size: 0.8rem;
    margin-top: 4px;
    font-weight: 500;
}

.mini-stat-footer {
    padding-top: 0.85rem;
    border-top: 1px solid rgba(6, 182, 212, 0.1);
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
}

.mini-stat-footer .text-danger { color: #f87171; }
.mini-stat-footer .text-success { color: #34d399; }
.mini-stat-footer a { color: #22d3ee; text-decoration: none; }
.mini-stat-footer a:hover { text-decoration: underline; }

/* Quick Actions Card */
.gdpr-card {
    background: rgba(10, 22, 40, 0.8);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.gdpr-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(6, 182, 212, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.gdpr-card-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.gdpr-card-header h3 i {
    color: #22d3ee;
}

.gdpr-card-body {
    padding: 1.5rem;
}

/* Quick Actions Grid */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}

.quick-action-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: rgba(6, 182, 212, 0.05);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 14px;
    text-decoration: none;
    color: #fff;
    transition: all 0.2s;
}

.quick-action-card:hover {
    background: rgba(6, 182, 212, 0.1);
    border-color: rgba(6, 182, 212, 0.3);
    transform: translateY(-2px);
}

.quick-action-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.quick-action-content {
    flex: 1;
    min-width: 0;
}

.quick-action-title {
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 0.95rem;
}

.quick-action-desc {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
}

.action-badge {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 0.3rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    flex-shrink: 0;
}

.action-badge.danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

/* Recent Activity */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(6, 182, 212, 0.05);
    border: 1px solid rgba(6, 182, 212, 0.1);
    border-radius: 12px;
    transition: all 0.2s;
}

.activity-item:hover {
    background: rgba(6, 182, 212, 0.08);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.activity-icon.request { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; }
.activity-icon.breach { background: rgba(239, 68, 68, 0.2); color: #f87171; }
.activity-icon.consent { background: rgba(16, 185, 129, 0.2); color: #34d399; }

.activity-content {
    flex: 1;
    min-width: 0;
}

.activity-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: #fff;
    margin-bottom: 2px;
}

.activity-meta {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.5);
}

.activity-time {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.4);
    flex-shrink: 0;
}

/* Request Type Badges */
.request-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.request-type-badge.access { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }
.request-type-badge.deletion { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.request-type-badge.portability { background: rgba(99, 102, 241, 0.15); color: #a5b4fc; }
.request-type-badge.rectification { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }

/* Responsive */
@media (max-width: 1024px) {
    .score-layout {
        grid-template-columns: 1fr;
    }

    .compliance-score-card {
        min-height: 250px;
    }
}

@media (max-width: 768px) {
    .stats-mini-grid {
        grid-template-columns: 1fr;
    }

    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Critical Alert -->
<?php if ($activeBreaches > 0 || $overdueCount > 0): ?>
<div class="enterprise-alert enterprise-alert-danger">
    <div class="enterprise-alert-icon">
        <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <div class="enterprise-alert-content">
        <div class="enterprise-alert-title">Action Required</div>
        <div class="enterprise-alert-message">
            <?php if ($activeBreaches > 0): ?>
                <?= $activeBreaches ?> active data breach<?= $activeBreaches > 1 ? 'es' : '' ?> require attention.
            <?php endif; ?>
            <?php if ($overdueCount > 0): ?>
                <?= $overdueCount ?> GDPR request<?= $overdueCount > 1 ? 's are' : ' is' ?> overdue.
            <?php endif; ?>
        </div>
    </div>
    <a href="<?= $basePath ?>/admin/enterprise/gdpr/<?= $activeBreaches > 0 ? 'breaches' : 'requests' ?>" class="enterprise-btn enterprise-btn-primary" style="flex-shrink: 0;">
        Review Now
    </a>
</div>
<?php endif; ?>

<!-- Compliance Score & Stats -->
<div class="score-layout">
    <div class="compliance-score-card">
        <svg viewBox="0 0 36 36" class="circular-chart">
            <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            <path class="circle" stroke-dasharray="<?= $complianceScore ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            <text x="18" y="20.5" class="score-text"><?= $complianceScore ?>%</text>
        </svg>
        <h4>Compliance Score</h4>
        <p>Based on request processing, consent coverage, and breach response metrics</p>
    </div>

    <div class="stats-mini-grid">
        <div class="mini-stat-card">
            <div class="mini-stat-header">
                <div class="mini-stat-icon warning">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div class="mini-stat-value"><?= $pendingCount ?></div>
                    <div class="mini-stat-label">Pending Requests</div>
                </div>
            </div>
            <div class="mini-stat-footer">
                <?php if ($overdueCount > 0): ?>
                    <span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= $overdueCount ?> overdue</span>
                <?php else: ?>
                    <span class="text-success"><i class="fa-solid fa-circle-check"></i> All within SLA</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="mini-stat-card">
            <div class="mini-stat-header">
                <div class="mini-stat-icon success">
                    <i class="fa-solid fa-check-double"></i>
                </div>
                <div>
                    <div class="mini-stat-value"><?= $completedCount ?></div>
                    <div class="mini-stat-label">Completed This Month</div>
                </div>
            </div>
            <div class="mini-stat-footer">
                Avg. processing: <?= $avgProcessingHours ?> hours
            </div>
        </div>

        <div class="mini-stat-card">
            <div class="mini-stat-header">
                <div class="mini-stat-icon info">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div>
                    <div class="mini-stat-value"><?= $consentCoverage ?>%</div>
                    <div class="mini-stat-label">Consent Coverage</div>
                </div>
            </div>
            <div class="mini-stat-footer">
                <?= $usersWithConsent ?> of <?= $totalUsers ?> users
            </div>
        </div>

        <div class="mini-stat-card">
            <div class="mini-stat-header">
                <div class="mini-stat-icon <?= $activeBreaches > 0 ? 'danger' : 'success' ?>">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <div class="mini-stat-value"><?= $activeBreaches ?></div>
                    <div class="mini-stat-label">Active Breaches</div>
                </div>
            </div>
            <div class="mini-stat-footer">
                <?php if ($activeBreaches > 0): ?>
                    <a href="<?= $basePath ?>/admin/enterprise/gdpr/breaches">Immediate attention required</a>
                <?php else: ?>
                    <span class="text-success">No active incidents</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="gdpr-card">
    <div class="gdpr-card-header">
        <h3><i class="fa-solid fa-bolt"></i> Quick Actions</h3>
        <a href="<?= $basePath ?>/admin/enterprise/gdpr/requests" class="enterprise-btn enterprise-btn-secondary enterprise-btn-sm">
            View All Requests
        </a>
    </div>
    <div class="gdpr-card-body">
        <div class="quick-actions-grid">
            <a href="<?= $basePath ?>/admin/enterprise/gdpr/requests" class="quick-action-card">
                <div class="quick-action-icon">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <div class="quick-action-content">
                    <div class="quick-action-title">Process Requests</div>
                    <div class="quick-action-desc">Handle pending GDPR requests</div>
                </div>
                <?php if ($pendingCount > 0): ?>
                    <span class="action-badge"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>

            <a href="<?= $basePath ?>/admin/enterprise/gdpr/consents" class="quick-action-card">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div class="quick-action-content">
                    <div class="quick-action-title">Manage Consents</div>
                    <div class="quick-action-desc">View and configure consent types</div>
                </div>
            </a>

            <a href="<?= $basePath ?>/admin/enterprise/gdpr/breaches" class="quick-action-card">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="fa-solid fa-shield-exclamation"></i>
                </div>
                <div class="quick-action-content">
                    <div class="quick-action-title">Breach Reports</div>
                    <div class="quick-action-desc">Track and manage data breaches</div>
                </div>
                <?php if ($activeBreaches > 0): ?>
                    <span class="action-badge danger"><?= $activeBreaches ?></span>
                <?php endif; ?>
            </a>

            <a href="<?= $basePath ?>/admin/enterprise/gdpr/audit" class="quick-action-card">
                <div class="quick-action-icon" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div class="quick-action-content">
                    <div class="quick-action-title">Audit Log</div>
                    <div class="quick-action-desc">View all GDPR-related activities</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Two Column Layout: Recent Activity & Request Types -->
<div style="display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem;">
    <div class="gdpr-card">
        <div class="gdpr-card-header">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</h3>
            <a href="<?= $basePath ?>/admin/enterprise/gdpr/audit" class="enterprise-btn enterprise-btn-secondary enterprise-btn-sm">
                View All
            </a>
        </div>
        <div class="gdpr-card-body">
            <div class="activity-list">
                <?php if (!empty($recentActivity)): ?>
                    <?php foreach (array_slice($recentActivity, 0, 5) as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?= $activity['type'] ?? 'request' ?>">
                            <i class="fa-solid <?= $activity['icon'] ?? 'fa-clipboard-list' ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?= htmlspecialchars($activity['title'] ?? 'Activity') ?></div>
                            <div class="activity-meta"><?= htmlspecialchars($activity['description'] ?? '') ?></div>
                        </div>
                        <div class="activity-time"><?= $activity['time'] ?? '' ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="activity-item">
                        <div class="activity-icon request">
                            <i class="fa-solid fa-inbox"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">New access request submitted</div>
                            <div class="activity-meta">Request #1234 from user@example.com</div>
                        </div>
                        <div class="activity-time">2 hours ago</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon consent">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Consent preference updated</div>
                            <div class="activity-meta">Marketing consent withdrawn</div>
                        </div>
                        <div class="activity-time">4 hours ago</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon request">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">Deletion request completed</div>
                            <div class="activity-meta">Request #1232 fulfilled</div>
                        </div>
                        <div class="activity-time">Yesterday</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="gdpr-card">
        <div class="gdpr-card-header">
            <h3><i class="fa-solid fa-chart-pie"></i> Request Types</h3>
        </div>
        <div class="gdpr-card-body">
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: rgba(6, 182, 212, 0.05); border-radius: 10px;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="request-type-badge access">Access</span>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Data Access Requests</span>
                    </div>
                    <span style="font-weight: 700; color: #fff;"><?= $stats['access_requests'] ?? 12 ?></span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: rgba(6, 182, 212, 0.05); border-radius: 10px;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="request-type-badge deletion">Deletion</span>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Right to Erasure</span>
                    </div>
                    <span style="font-weight: 700; color: #fff;"><?= $stats['deletion_requests'] ?? 8 ?></span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: rgba(6, 182, 212, 0.05); border-radius: 10px;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="request-type-badge portability">Portability</span>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Data Portability</span>
                    </div>
                    <span style="font-weight: 700; color: #fff;"><?= $stats['portability_requests'] ?? 3 ?></span>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: rgba(6, 182, 212, 0.05); border-radius: 10px;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="request-type-badge rectification">Rectification</span>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Data Correction</span>
                    </div>
                    <span style="font-weight: 700; color: #fff;"><?= $stats['rectification_requests'] ?? 5 ?></span>
                </div>
            </div>

            <div style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid rgba(6, 182, 212, 0.1); text-align: center;">
                <div style="font-size: 2rem; font-weight: 800; color: #fff;"><?= $totalRequests ?></div>
                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px;">Total Requests</div>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 1200px) {
    div[style*="grid-template-columns: 1fr 380px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
