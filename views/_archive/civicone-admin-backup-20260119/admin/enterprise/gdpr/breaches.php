<?php
/**
 * Admin GDPR Data Breach Management - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Data Breaches';
$adminPageSubtitle = 'Enterprise GDPR';
$adminPageIcon = 'fa-shield-halved';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'gdpr';
$currentPage = 'breaches';

// Extract breach data
$breachStats = $stats ?? [];
$activeBreaches = $breachStats['active_breaches'] ?? 0;
$investigating = $breachStats['investigating'] ?? 0;
$notifiedDPA = $breachStats['notified_dpa'] ?? 0;
$resolved = $breachStats['resolved'] ?? 0;
$notificationRequired = $breachStats['notification_required'] ?? 0;
$breaches = $breaches ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/enterprise" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Data Breach Management
        </h1>
        <p class="admin-page-subtitle">Track, investigate & report security incidents</p>
    </div>
    <div class="admin-page-actions">
        <a href="<?= $basePath ?>/admin/enterprise/gdpr/breaches/report" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i> Report Breach
        </a>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<!-- Critical Alert Banner -->
<?php if ($activeBreaches > 0): ?>
<div class="critical-alert-banner">
    <div class="alert-icon-box">
        <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <div class="alert-content">
        <div class="alert-title">
            <i class="fa-solid fa-fire"></i> Active Security Incidents Detected
        </div>
        <div class="alert-message">
            There <?= $activeBreaches == 1 ? 'is' : 'are' ?> <strong><?= $activeBreaches ?></strong>
            active breach<?= $activeBreaches == 1 ? '' : 'es' ?> requiring immediate attention.
            <?php if ($notificationRequired > 0): ?>
                <strong><?= $notificationRequired ?></strong> require<?= $notificationRequired == 1 ? 's' : '' ?> DPA notification within 72 hours.
            <?php endif; ?>
        </div>
    </div>
    <a href="#active-breaches" class="admin-btn admin-btn-danger">
        <i class="fa-solid fa-shield-halved"></i> Review Incidents
    </a>
</div>
<?php endif; ?>

<!-- Breach Stats Grid -->
<div class="breach-stats-grid">
    <div class="breach-stat-card critical">
        <div class="breach-stat-icon">
            <i class="fa-solid fa-fire"></i>
        </div>
        <div class="breach-stat-content">
            <div class="breach-stat-value"><?= $activeBreaches ?></div>
            <div class="breach-stat-label">Active Incidents</div>
        </div>
    </div>

    <div class="breach-stat-card warning">
        <div class="breach-stat-icon">
            <i class="fa-solid fa-magnifying-glass"></i>
        </div>
        <div class="breach-stat-content">
            <div class="breach-stat-value"><?= $investigating ?></div>
            <div class="breach-stat-label">Under Investigation</div>
        </div>
    </div>

    <div class="breach-stat-card info">
        <div class="breach-stat-icon">
            <i class="fa-solid fa-bell"></i>
        </div>
        <div class="breach-stat-content">
            <div class="breach-stat-value"><?= $notifiedDPA ?></div>
            <div class="breach-stat-label">DPA Notified</div>
        </div>
    </div>

    <div class="breach-stat-card success">
        <div class="breach-stat-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="breach-stat-content">
            <div class="breach-stat-value"><?= $resolved ?></div>
            <div class="breach-stat-label">Resolved</div>
        </div>
    </div>
</div>

<!-- Breach Table -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fa-solid fa-shield-exclamation"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Security Incidents</h3>
            <p class="admin-card-subtitle">All reported data breaches and security incidents</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;" id="active-breaches">
        <?php if (!empty($breaches)): ?>
        <div class="admin-table-responsive">
            <table class="admin-table breach-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Severity</th>
                        <th>Affected Users</th>
                        <th>Detected</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($breaches as $breach): ?>
                    <tr class="<?= $breach['status'] === 'active' ? 'critical-row' : '' ?>">
                        <td>
                            <a href="<?= $basePath ?>/admin/enterprise/gdpr/breaches/<?= $breach['id'] ?>" class="breach-id">
                                #<?= $breach['id'] ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($breach['breach_type']) ?></td>
                        <td>
                            <span class="severity-badge <?= strtolower($breach['severity']) ?>">
                                <i class="fa-solid fa-circle"></i>
                                <?= ucfirst($breach['severity']) ?>
                            </span>
                        </td>
                        <td><?= number_format($breach['affected_users'] ?? 0) ?></td>
                        <td><?= date('M j, Y H:i', strtotime($breach['detected_at'])) ?></td>
                        <td>
                            <span class="status-badge <?= strtolower($breach['status']) ?>">
                                <?= ucfirst($breach['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= $basePath ?>/admin/enterprise/gdpr/breaches/<?= $breach['id'] ?>" class="action-btn" title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <?php if ($breach['status'] === 'active'): ?>
                                <button type="button" class="action-btn warning" onclick="escalateBreach(<?= $breach['id'] ?>)" title="Escalate">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon success">
                <i class="fa-solid fa-shield-check"></i>
            </div>
            <h3>No Security Breaches Reported</h3>
            <p>Your system is secure. All data protection measures are functioning normally.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Page Header */
.admin-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.admin-page-header-content {
    flex: 1;
}

.admin-page-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-page-subtitle {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
}

.admin-page-actions {
    display: flex;
    gap: 0.75rem;
    flex-shrink: 0;
}

.back-link {
    color: inherit;
    text-decoration: none;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 1;
}

/* Admin Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
}

.admin-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
}

.admin-btn-danger {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: white;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.4);
}

.admin-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
}

/* Critical Alert Banner */
.critical-alert-banner {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.15), rgba(239, 68, 68, 0.1));
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-left: 4px solid #ef4444;
    border-radius: 1rem;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    animation: alertPulse 2s ease-in-out infinite;
}

@keyframes alertPulse {
    0%, 100% { box-shadow: 0 4px 20px rgba(220, 38, 38, 0.2); }
    50% { box-shadow: 0 8px 30px rgba(220, 38, 38, 0.35); }
}

.alert-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #dc2626, #ef4444);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
    animation: iconPulse 1.5s ease-in-out infinite;
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.alert-content {
    flex: 1;
}

.alert-title {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert-title i {
    color: #f59e0b;
}

.alert-message {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.5;
}

.alert-message strong {
    color: #fca5a5;
    font-weight: 700;
}

/* Breach Stats Grid */
.breach-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.breach-stat-card {
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.breach-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--stat-color), transparent);
}

.breach-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 0 25px var(--stat-glow);
    border-color: var(--stat-color);
}

.breach-stat-card.critical {
    --stat-color: #ef4444;
    --stat-glow: rgba(239, 68, 68, 0.3);
}

.breach-stat-card.warning {
    --stat-color: #f59e0b;
    --stat-glow: rgba(245, 158, 11, 0.3);
}

.breach-stat-card.info {
    --stat-color: #06b6d4;
    --stat-glow: rgba(6, 182, 212, 0.3);
}

.breach-stat-card.success {
    --stat-color: #10b981;
    --stat-glow: rgba(16, 185, 129, 0.3);
}

.breach-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--stat-color), var(--stat-color-light, var(--stat-color)));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.breach-stat-card.critical .breach-stat-icon { --stat-color-light: #f87171; }
.breach-stat-card.warning .breach-stat-icon { --stat-color-light: #fbbf24; }
.breach-stat-card.info .breach-stat-icon { --stat-color-light: #22d3ee; }
.breach-stat-card.success .breach-stat-icon { --stat-color-light: #34d399; }

.breach-stat-content {
    flex: 1;
}

.breach-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.breach-stat-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Card Header */
.admin-card-header-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
}

.admin-card-header-content {
    flex: 1;
}

.admin-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.admin-card-subtitle {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.25rem 0 0 0;
}

/* Table */
.admin-table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table thead {
    background: rgba(99, 102, 241, 0.05);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.admin-table tbody tr {
    border-bottom: 1px solid rgba(99, 102, 241, 0.08);
    transition: all 0.2s;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.06);
}

.admin-table tbody tr.critical-row {
    background: rgba(239, 68, 68, 0.08);
    border-left: 3px solid #ef4444;
}

.admin-table tbody tr.critical-row:hover {
    background: rgba(239, 68, 68, 0.12);
}

.admin-table td {
    padding: 1rem 1.25rem;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.875rem;
}

.breach-id {
    font-weight: 700;
    color: #818cf8;
    text-decoration: none;
    transition: all 0.2s;
}

.breach-id:hover {
    color: #a5b4fc;
    text-decoration: underline;
}

/* Badges */
.severity-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.severity-badge.critical,
.status-badge.active {
    background: rgba(220, 38, 38, 0.15);
    color: #fca5a5;
    border: 1px solid rgba(220, 38, 38, 0.3);
}

.severity-badge.high {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.severity-badge.medium,
.status-badge.investigating {
    background: rgba(245, 158, 11, 0.15);
    color: #fcd34d;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.severity-badge.low,
.status-badge.notified {
    background: rgba(6, 182, 212, 0.15);
    color: #67e8f9;
    border: 1px solid rgba(6, 182, 212, 0.3);
}

.status-badge.resolved {
    background: rgba(16, 185, 129, 0.15);
    color: #6ee7b7;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.severity-badge i {
    font-size: 0.4rem;
}

/* Table Actions */
.table-actions {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: rgba(255, 255, 255, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.action-btn:hover {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.4);
    color: #fff;
    transform: translateY(-2px);
}

.action-btn.warning {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.2);
}

.action-btn.warning:hover {
    background: rgba(245, 158, 11, 0.2);
    border-color: rgba(245, 158, 11, 0.4);
    color: #fbbf24;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.empty-state-icon.success {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-page-header {
        flex-direction: column;
        gap: 1rem;
    }

    .admin-page-actions {
        width: 100%;
    }

    .admin-page-actions .admin-btn {
        flex: 1;
        justify-content: center;
    }

    .critical-alert-banner {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }

    .breach-stats-grid {
        grid-template-columns: 1fr 1fr;
    }

    .admin-table {
        min-width: 700px;
    }
}

@media (max-width: 480px) {
    .breach-stats-grid {
        grid-template-columns: 1fr;
    }

    .admin-page-title {
        font-size: 1.35rem;
    }
}
</style>

<script>
const basePath = '<?= $basePath ?>';

function escalateBreach(id) {
    if (confirm('Escalate this breach to the incident response team?')) {
        fetch(`${basePath}/admin/enterprise/gdpr/breaches/${id}/escalate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= Csrf::generate() ?>'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Breach escalated successfully');
                window.location.reload();
            } else {
                alert('Failed to escalate breach: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Network error. Please try again.');
            console.error('Escalate error:', err);
        });
    }
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
