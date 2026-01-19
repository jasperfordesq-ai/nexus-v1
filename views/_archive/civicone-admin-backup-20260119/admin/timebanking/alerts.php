<?php
/**
 * Admin Abuse Alerts - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 * Full FDS Gold Standard compliance
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Abuse Alerts';
$adminPageSubtitle = 'TimeBanking';
$adminPageIcon = 'fa-shield-exclamation';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

// Safe defaults
$alerts = $alerts ?? [];
$alertCounts = $alertCounts ?? ['new' => 0, 'reviewing' => 0, 'resolved' => 0, 'dismissed' => 0];
$currentStatus = $currentStatus ?? null;
$page = $page ?? 1;
?>

<!-- Page Header with Actions -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-shield-exclamation"></i>
            Abuse Alerts
        </h1>
        <p class="admin-page-subtitle">Real-time fraud detection and security monitoring</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/timebanking" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to TimeBanking</span>
        </a>
        <form action="<?= $basePath ?>/admin/timebanking/run-detection" method="POST" style="display: inline-block; margin: 0;">
            <?= Csrf::input() ?>
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-radar"></i>
                <span>Run Detection</span>
            </button>
        </form>
    </div>
</div>

<!-- Alert Statistics Grid -->
<div class="admin-stats-grid alerts-stats-grid">
    <!-- All Alerts -->
    <a href="<?= $basePath ?>/admin/timebanking/alerts" class="admin-stat-card <?= !$currentStatus ? 'admin-stat-active' : '' ?>">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format(array_sum($alertCounts)) ?></div>
            <div class="admin-stat-label">Total Alerts</div>
        </div>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-chart-line"></i>
        </div>
    </a>

    <!-- New Alerts -->
    <a href="<?= $basePath ?>/admin/timebanking/alerts?status=new" class="admin-stat-card admin-stat-red <?= $currentStatus === 'new' ? 'admin-stat-active' : '' ?>">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($alertCounts['new'] ?? 0) ?></div>
            <div class="admin-stat-label">New</div>
        </div>
        <?php if (($alertCounts['new'] ?? 0) > 0): ?>
        <div class="admin-stat-trend admin-stat-trend-up">
            <i class="fa-solid fa-exclamation"></i>
            <span>Urgent</span>
        </div>
        <?php endif; ?>
    </a>

    <!-- Reviewing Alerts -->
    <a href="<?= $basePath ?>/admin/timebanking/alerts?status=reviewing" class="admin-stat-card admin-stat-orange <?= $currentStatus === 'reviewing' ? 'admin-stat-active' : '' ?>">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-magnifying-glass-chart"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($alertCounts['reviewing'] ?? 0) ?></div>
            <div class="admin-stat-label">Under Review</div>
        </div>
        <?php if (($alertCounts['reviewing'] ?? 0) > 0): ?>
        <div class="admin-stat-trend">
            <i class="fa-solid fa-clock"></i>
            <span>In Progress</span>
        </div>
        <?php endif; ?>
    </a>

    <!-- Resolved Alerts -->
    <a href="<?= $basePath ?>/admin/timebanking/alerts?status=resolved" class="admin-stat-card admin-stat-green <?= $currentStatus === 'resolved' ? 'admin-stat-active' : '' ?>">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($alertCounts['resolved'] ?? 0) ?></div>
            <div class="admin-stat-label">Resolved</div>
        </div>
        <div class="admin-stat-trend admin-stat-trend-down">
            <i class="fa-solid fa-check"></i>
            <span>Complete</span>
        </div>
    </a>

    <!-- Dismissed Alerts -->
    <a href="<?= $basePath ?>/admin/timebanking/alerts?status=dismissed" class="admin-stat-card admin-stat-slate <?= $currentStatus === 'dismissed' ? 'admin-stat-active' : '' ?>">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-ban"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($alertCounts['dismissed'] ?? 0) ?></div>
            <div class="admin-stat-label">Dismissed</div>
        </div>
    </a>
</div>

<!-- Main Alerts Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-red">
            <i class="fa-solid fa-shield-exclamation"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">
                <?php if ($currentStatus): ?>
                    <?= ucfirst($currentStatus) ?> Alerts
                <?php else: ?>
                    All Security Alerts
                <?php endif; ?>
            </h3>
            <p class="admin-card-subtitle">
                <?php if ($currentStatus): ?>
                    Showing alerts with status: <strong><?= htmlspecialchars($currentStatus) ?></strong>
                <?php else: ?>
                    Comprehensive fraud detection and suspicious activity monitoring
                <?php endif; ?>
            </p>
        </div>
        <?php if ($currentStatus): ?>
        <a href="<?= $basePath ?>/admin/timebanking/alerts" class="admin-btn admin-btn-secondary admin-btn-sm">
            <i class="fa-solid fa-filter-circle-xmark"></i>
            <span>Clear Filter</span>
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($alerts)): ?>
    <!-- Empty State -->
    <div class="admin-card-body">
        <div class="admin-empty-state">
            <div class="admin-empty-icon admin-empty-icon-success">
                <i class="fa-solid fa-shield-check"></i>
            </div>
            <h3 class="admin-empty-title">All Clear!</h3>
            <p class="admin-empty-text">
                <?php if ($currentStatus): ?>
                    No alerts with status "<?= htmlspecialchars($currentStatus) ?>" found.<br>
                    Your security monitoring system is actively protecting the community.
                <?php else: ?>
                    No abuse alerts detected. Your TimeBanking community is secure and healthy!<br>
                    The fraud detection system is running continuously in the background.
                <?php endif; ?>
            </p>
            <?php if ($currentStatus): ?>
            <a href="<?= $basePath ?>/admin/timebanking/alerts" class="admin-btn admin-btn-primary" style="margin-top: 1.5rem;">
                <i class="fa-solid fa-list"></i> View All Alerts
            </a>
            <?php else: ?>
            <form action="<?= $basePath ?>/admin/timebanking/run-detection" method="POST" style="margin-top: 1.5rem;">
                <?= Csrf::input() ?>
                <button type="submit" class="admin-btn admin-btn-secondary">
                    <i class="fa-solid fa-radar"></i> Run Manual Detection
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Alerts Table -->
    <div class="admin-card-body" style="padding: 0;">
        <div class="admin-table-wrapper">
            <table class="admin-table admin-table-hover">
                <thead>
                    <tr>
                        <th style="width: 30%;">Alert Type</th>
                        <th class="hide-mobile" style="width: 20%;">User</th>
                        <th style="width: 12%;">Severity</th>
                        <th class="hide-tablet" style="width: 13%;">Status</th>
                        <th class="hide-tablet" style="width: 15%;">Date</th>
                        <th style="text-align: right; width: 10%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert): ?>
                    <tr>
                        <!-- Alert Type with Icon -->
                        <td>
                            <div class="alert-type-cell">
                                <?php
                                $typeIcon = match($alert['alert_type'] ?? '') {
                                    'large_transfer' => 'fa-money-bill-transfer',
                                    'high_velocity' => 'fa-gauge-high',
                                    'circular_transfer' => 'fa-arrows-rotate',
                                    'unusual_pattern' => 'fa-chart-line-up',
                                    default => 'fa-clock'
                                };
                                $typeColor = match($alert['alert_type'] ?? '') {
                                    'large_transfer' => 'purple',
                                    'high_velocity' => 'orange',
                                    'circular_transfer' => 'pink',
                                    'unusual_pattern' => 'cyan',
                                    default => 'blue'
                                };
                                ?>
                                <div class="alert-type-icon alert-type-icon-<?= $typeColor ?>">
                                    <i class="fa-solid <?= $typeIcon ?>"></i>
                                </div>
                                <div class="alert-type-info">
                                    <div class="alert-type-name">
                                        <?= ucwords(str_replace('_', ' ', $alert['alert_type'] ?? 'Unknown')) ?>
                                    </div>
                                    <div class="alert-type-desc">
                                        <?php
                                        $details = json_decode($alert['details'] ?? '{}', true);
                                        if ($alert['alert_type'] === 'large_transfer') {
                                            echo 'Amount: <strong>' . number_format($details['amount'] ?? 0, 1) . ' HRS</strong>';
                                        } elseif ($alert['alert_type'] === 'high_velocity') {
                                            echo '<strong>' . ($details['transaction_count'] ?? 0) . '</strong> txns in 1 hour';
                                        } elseif ($alert['alert_type'] === 'circular_transfer') {
                                            echo 'Return: <strong>' . number_format($details['return_amount'] ?? 0, 1) . ' HRS</strong>';
                                        } else {
                                            echo 'Balance: <strong>' . number_format($details['balance'] ?? 0, 1) . ' HRS</strong>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- User Info -->
                        <td class="hide-mobile">
                            <div class="admin-user-cell">
                                <div class="admin-user-avatar-placeholder">
                                    <?= strtoupper(substr($alert['user_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div class="admin-user-info">
                                    <div class="admin-user-name"><?= htmlspecialchars($alert['user_name'] ?? 'Unknown User') ?></div>
                                    <div class="admin-user-meta">ID: <?= $alert['user_id'] ?? 'N/A' ?></div>
                                </div>
                            </div>
                        </td>

                        <!-- Severity Badge -->
                        <td>
                            <?php
                            $severityClass = match($alert['severity'] ?? 'low') {
                                'critical' => 'admin-badge-critical',
                                'high' => 'admin-badge-danger',
                                'medium' => 'admin-badge-warning',
                                default => 'admin-badge-info'
                            };
                            $severityIcon = match($alert['severity'] ?? 'low') {
                                'critical' => 'fa-circle-exclamation',
                                'high' => 'fa-triangle-exclamation',
                                'medium' => 'fa-circle-info',
                                default => 'fa-circle'
                            };
                            ?>
                            <span class="admin-badge <?= $severityClass ?>">
                                <i class="fa-solid <?= $severityIcon ?>"></i>
                                <?= ucfirst($alert['severity'] ?? 'Low') ?>
                            </span>
                        </td>

                        <!-- Status Badge -->
                        <td class="hide-tablet">
                            <?php
                            $statusClass = match($alert['status'] ?? 'new') {
                                'new' => 'admin-status-danger',
                                'reviewing' => 'admin-status-pending',
                                'resolved' => 'admin-status-active',
                                'dismissed' => 'admin-status-inactive',
                                default => 'admin-status-pending'
                            };
                            ?>
                            <span class="admin-status-badge <?= $statusClass ?>">
                                <span class="admin-status-dot"></span>
                                <?= ucfirst($alert['status'] ?? 'New') ?>
                            </span>
                        </td>

                        <!-- Date/Time -->
                        <td class="hide-tablet">
                            <div class="alert-datetime">
                                <span class="alert-date">
                                    <i class="fa-solid fa-calendar-days"></i>
                                    <?= date('M d, Y', strtotime($alert['created_at'] ?? 'now')) ?>
                                </span>
                                <span class="alert-time">
                                    <i class="fa-solid fa-clock"></i>
                                    <?= date('g:i A', strtotime($alert['created_at'] ?? 'now')) ?>
                                </span>
                            </div>
                        </td>

                        <!-- Actions -->
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <a href="<?= $basePath ?>/admin/timebanking/alert/<?= $alert['id'] ?>"
                                   class="admin-btn admin-btn-primary admin-btn-sm"
                                   title="View alert details">
                                    <i class="fa-solid fa-eye"></i>
                                    <span class="hide-mobile">View</span>
                                </a>
                                <?php if (($alert['status'] ?? '') === 'new'): ?>
                                <form action="<?= $basePath ?>/admin/timebanking/alert/<?= $alert['id'] ?>/status" method="POST" style="display: inline;">
                                    <?= Csrf::input() ?>
                                    <input type="hidden" name="status" value="dismissed">
                                    <button type="submit"
                                            class="admin-btn admin-btn-ghost admin-btn-sm"
                                            onclick="return confirm('Dismiss this alert as false positive?');"
                                            title="Dismiss alert">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($page > 1 || count($alerts) >= 25): ?>
        <div class="admin-pagination">
            <?php if ($page > 1): ?>
            <a href="?<?= $currentStatus ? "status=$currentStatus&" : '' ?>page=<?= $page - 1 ?>"
               class="admin-btn admin-btn-secondary admin-btn-sm">
                <i class="fa-solid fa-chevron-left"></i>
                <span>Previous</span>
            </a>
            <?php else: ?>
            <span class="admin-btn admin-btn-secondary admin-btn-sm" style="opacity: 0.3; cursor: not-allowed;">
                <i class="fa-solid fa-chevron-left"></i>
                <span>Previous</span>
            </span>
            <?php endif; ?>

            <span class="admin-pagination-info">
                <i class="fa-solid fa-file-lines"></i>
                Page <?= $page ?>
            </span>

            <?php if (count($alerts) >= 25): ?>
            <a href="?<?= $currentStatus ? "status=$currentStatus&" : '' ?>page=<?= $page + 1 ?>"
               class="admin-btn admin-btn-secondary admin-btn-sm">
                <span>Next</span>
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php else: ?>
            <span class="admin-btn admin-btn-secondary admin-btn-sm" style="opacity: 0.3; cursor: not-allowed;">
                <span>Next</span>
                <i class="fa-solid fa-chevron-right"></i>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
/* ============================================================================
   ALERTS PAGE - FULL GOLD STANDARD v2.0
   Holographic Glassmorphism with Complete Component Polish
   ============================================================================ */

/* ========== GLASS CARD CONTAINER ========== */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    overflow: hidden;
    box-shadow:
        0 4px 30px rgba(0, 0, 0, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

/* ========== CARD HEADER ========== */
.admin-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), transparent);
}

.admin-card-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.admin-card-header-icon-red {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.25), rgba(220, 38, 38, 0.15));
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: #fca5a5;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
}

.admin-card-header-content {
    flex: 1;
    min-width: 0;
}

.admin-card-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 4px 0;
}

.admin-card-subtitle {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.55);
    margin: 0;
}

.admin-card-subtitle strong {
    color: #a5b4fc;
}

/* ========== CARD BODY ========== */
.admin-card-body {
    padding: 1.5rem;
}

/* ========== TABLE WRAPPER ========== */
.admin-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* ========== TABLE STYLING ========== */
.admin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.admin-table thead {
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.12), rgba(99, 102, 241, 0.05));
}

.admin-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(255, 255, 255, 0.7);
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    white-space: nowrap;
}

.admin-table td {
    padding: 1rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid rgba(99, 102, 241, 0.08);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* ========== EMPTY STATE ========== */
.admin-empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(99, 102, 241, 0.08));
    border: 2px solid rgba(99, 102, 241, 0.25);
    color: #818cf8;
}

.admin-empty-icon-success {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(22, 163, 74, 0.08)) !important;
    border-color: rgba(34, 197, 94, 0.3) !important;
    color: #86efac !important;
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 0.75rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.55);
    font-size: 0.95rem;
    line-height: 1.6;
    margin: 0;
}

/* ========== BUTTONS ========== */
.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: 10px;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
}

.admin-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
}

.admin-btn-secondary {
    background: rgba(99, 102, 241, 0.12);
    color: #a5b4fc;
    border-color: rgba(99, 102, 241, 0.3);
}

.admin-btn-secondary:hover {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.5);
    transform: translateY(-1px);
}

.admin-btn-sm {
    padding: 0.5rem 0.875rem;
    font-size: 0.8125rem;
    border-radius: 8px;
}

.admin-btn-ghost {
    background: transparent;
    color: rgba(255, 255, 255, 0.4);
    border: 1px solid transparent;
    padding: 0.5rem 0.625rem;
}

.admin-btn-ghost:hover {
    background: rgba(239, 68, 68, 0.12);
    color: #f87171;
    border-color: rgba(239, 68, 68, 0.4);
    box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);
}

/* ========== STATS GRID ========== */
.alerts-stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Clickable Stat Cards - Full Styling */
.alerts-stats-grid .admin-stat-card {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.alerts-stats-grid .admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.alerts-stats-grid .admin-stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.alerts-stats-grid .admin-stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.375rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.alerts-stats-grid .admin-stat-content {
    flex: 1;
    min-width: 0;
}

.alerts-stats-grid .admin-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #f1f5f9;
    line-height: 1.2;
}

.alerts-stats-grid .admin-stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 4px;
}

.alerts-stats-grid .admin-stat-trend {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    gap: 4px;
}

.alerts-stats-grid .admin-stat-trend-up { color: #fca5a5; }
.alerts-stats-grid .admin-stat-trend-down { color: #86efac; }

/* Stat Card Color Variants */
.alerts-stats-grid .admin-stat-red::before { background: linear-gradient(135deg, #ef4444, #dc2626); }
.alerts-stats-grid .admin-stat-red .admin-stat-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }
.alerts-stats-grid .admin-stat-red .admin-stat-value { color: #f87171; }

.alerts-stats-grid .admin-stat-orange::before { background: linear-gradient(135deg, #f59e0b, #d97706); }
.alerts-stats-grid .admin-stat-orange .admin-stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.alerts-stats-grid .admin-stat-orange .admin-stat-value { color: #fbbf24; }

.alerts-stats-grid .admin-stat-green::before { background: linear-gradient(135deg, #22c55e, #16a34a); }
.alerts-stats-grid .admin-stat-green .admin-stat-icon { background: linear-gradient(135deg, #22c55e, #16a34a); }
.alerts-stats-grid .admin-stat-green .admin-stat-value { color: #4ade80; }

.alerts-stats-grid .admin-stat-slate::before { background: linear-gradient(135deg, #64748b, #475569); }
.alerts-stats-grid .admin-stat-slate .admin-stat-icon { background: linear-gradient(135deg, #64748b, #475569); }
.alerts-stats-grid .admin-stat-slate .admin-stat-value { color: #94a3b8; }

/* Active Filter State */
.alerts-stats-grid .admin-stat-active {
    border-color: rgba(99, 102, 241, 0.5) !important;
    box-shadow: 0 0 25px rgba(99, 102, 241, 0.25) !important;
}

@media (max-width: 1400px) {
    .alerts-stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 900px) {
    .alerts-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .alerts-stats-grid {
        grid-template-columns: 1fr;
    }
}


/* Alert Type Cell */
.alert-type-cell {
    display: flex;
    align-items: center;
    gap: 0.875rem;
}

.alert-type-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.125rem;
    flex-shrink: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.alert-type-icon-purple {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(124, 58, 237, 0.15));
    border: 1px solid rgba(139, 92, 246, 0.3);
    color: #c4b5fd;
}

.alert-type-icon-orange {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.25), rgba(245, 158, 11, 0.15));
    border: 1px solid rgba(251, 191, 36, 0.3);
    color: #fde68a;
}

.alert-type-icon-pink {
    background: linear-gradient(135deg, rgba(236, 72, 153, 0.25), rgba(219, 39, 119, 0.15));
    border: 1px solid rgba(236, 72, 153, 0.3);
    color: #fbcfe8;
}

.alert-type-icon-cyan {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.25), rgba(8, 145, 178, 0.15));
    border: 1px solid rgba(6, 182, 212, 0.3);
    color: #a5f3fc;
}

.alert-type-icon-blue {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.25), rgba(37, 99, 235, 0.15));
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #bfdbfe;
}

tr:hover .alert-type-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.25);
}

.alert-type-info {
    min-width: 0;
    flex: 1;
}

.alert-type-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.9375rem;
    line-height: 1.4;
    margin-bottom: 3px;
}

.alert-type-desc {
    font-size: 0.8125rem;
    color: rgba(255, 255, 255, 0.5);
    line-height: 1.4;
}

.alert-type-desc strong {
    color: rgba(255, 255, 255, 0.75);
    font-weight: 600;
}

/* User Cell Enhancements */
.admin-user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-user-avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: 2px solid rgba(99, 102, 241, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

tr:hover .admin-user-avatar-placeholder {
    transform: scale(1.1);
    border-color: rgba(99, 102, 241, 0.6);
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
}

.admin-user-info {
    min-width: 0;
}

.admin-user-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
    line-height: 1.4;
}

.admin-user-meta {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 2px;
}

/* Badge System */
.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.admin-badge i {
    font-size: 0.7rem;
}

.admin-badge-critical {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.35), rgba(220, 38, 38, 0.25));
    color: #fecaca;
    border: 1px solid rgba(239, 68, 68, 0.5);
    box-shadow: 0 0 15px rgba(239, 68, 68, 0.3);
}

.admin-badge-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.15));
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.35);
}

.admin-badge-warning {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.15));
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.35);
}

.admin-badge-info {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.15));
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.35);
}

/* Status Badge Enhancements */
.admin-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 0.875rem;
    font-weight: 600;
    padding: 2px 0;
}

.admin-status-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    animation: pulse-dot 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.admin-status-danger { color: #fca5a5; }
.admin-status-danger .admin-status-dot {
    background: #ef4444;
    box-shadow: 0 0 10px rgba(239, 68, 68, 0.6);
}

.admin-status-pending { color: #fcd34d; }
.admin-status-pending .admin-status-dot {
    background: #f59e0b;
    box-shadow: 0 0 10px rgba(245, 158, 11, 0.6);
}

.admin-status-active { color: #86efac; }
.admin-status-active .admin-status-dot {
    background: #22c55e;
    box-shadow: 0 0 10px rgba(34, 197, 94, 0.6);
}

.admin-status-inactive { color: #cbd5e1; }
.admin-status-inactive .admin-status-dot {
    background: #64748b;
    box-shadow: 0 0 10px rgba(100, 116, 139, 0.4);
}

/* Date/Time Display */
.alert-datetime {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.alert-date,
.alert-time {
    font-size: 0.8125rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.alert-date {
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
}

.alert-date i {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.75rem;
}

.alert-time {
    color: rgba(255, 255, 255, 0.45);
    font-weight: 400;
}

.alert-time i {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.7rem;
}

/* Action Buttons */
.admin-action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    align-items: center;
}


/* Pagination Enhancements */
.admin-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.25rem;
    padding: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
    background: linear-gradient(to bottom, transparent, rgba(0, 0, 0, 0.1));
}

.admin-pagination-info {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
    padding: 0.625rem 1.25rem;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(99, 102, 241, 0.08));
    border: 1px solid rgba(99, 102, 241, 0.25);
    border-radius: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-pagination-info i {
    color: rgba(99, 102, 241, 0.6);
}

/* Table Hover Effects */
.admin-table-hover tbody tr {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-table-hover tbody tr:hover {
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.08), rgba(99, 102, 241, 0.05));
    transform: translateX(4px);
    box-shadow: inset 3px 0 0 rgba(99, 102, 241, 0.5);
}

/* Responsive Utilities */
@media (max-width: 1100px) {
    .hide-tablet {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none !important;
    }

    .admin-page-header {
        flex-direction: column;
        gap: 1rem;
    }

    .admin-page-header-actions {
        width: 100%;
        justify-content: stretch;
    }

    .admin-page-header-actions .admin-btn,
    .admin-page-header-actions form {
        flex: 1;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.875rem 1rem;
        font-size: 0.875rem;
    }

    .admin-action-buttons {
        flex-direction: column;
        gap: 0.375rem;
    }

    .admin-action-buttons .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .alert-type-cell {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.625rem;
    }

    .alert-type-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
}

@media (max-width: 480px) {
    .admin-btn span:not(.admin-status-dot) {
        display: none;
    }

    .admin-pagination {
        gap: 0.75rem;
        padding: 1rem;
    }

    .admin-pagination .admin-btn span {
        display: none;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
