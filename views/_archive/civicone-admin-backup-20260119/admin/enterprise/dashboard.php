<?php
/**
 * Enterprise Dashboard - Gold Standard v2.0
 * STANDALONE Admin Interface with Holographic Glassmorphism
 * Cyan/Teal Theme for Enterprise Module
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$isSuperAdmin = !empty($_SESSION['is_super_admin']);

// Admin header configuration
$adminPageTitle = 'Enterprise Dashboard';
$adminPageSubtitle = 'Command Center';
$adminPageIcon = 'fa-building-shield';

// Extract stats with defaults
$gdprStats = $stats['gdpr'] ?? [];
$systemStats = $stats['system'] ?? [];
$configStats = $stats['config'] ?? [];

$pendingRequests = $gdprStats['pending_count'] ?? 0;
$activeBreaches = $gdprStats['active_breaches'] ?? 0;
$overdueCount = $gdprStats['overdue_count'] ?? 0;
$avgProcessingTime = round($gdprStats['avg_processing_time'] ?? 0, 1);
$totalRequests = $gdprStats['total_requests'] ?? 0;
$completedRequests = $gdprStats['completed_requests'] ?? 0;

$vaultEnabled = $configStats['vault_enabled'] ?? false;
$vaultAvailable = $configStats['vault_available'] ?? false;
$environment = $configStats['environment'] ?? 'production';

$phpVersion = $systemStats['php_version'] ?? PHP_VERSION;
$memoryUsage = $systemStats['memory_usage'] ?? 'N/A';
$cpuLoad = $systemStats['cpu_load'] ?? 'N/A';
$diskUsage = $systemStats['disk_usage'] ?? 'N/A';
$uptime = $systemStats['uptime'] ?? 'N/A';

// Include standard admin header
require dirname(__DIR__) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'dashboard';
$currentPage = '';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-building-shield"></i>
            Enterprise Command Center
        </h1>
        <p class="admin-page-subtitle">GDPR Compliance, System Monitoring & Advanced Configuration</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-shield-halved"></i> GDPR Console
        </a>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-chart-line"></i> Monitoring
        </a>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require __DIR__ . '/partials/nav.php'; ?>

<style>
/* Alert Banner */
.critical-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-left: 4px solid #ef4444;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    animation: alertPulse 2s ease-in-out infinite;
}

@keyframes alertPulse {
    0%, 100% { border-left-color: #ef4444; }
    50% { border-left-color: #f87171; }
}

.critical-alert-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(239, 68, 68, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #f87171;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.critical-alert-content {
    flex: 1;
}

.critical-alert-title {
    font-weight: 700;
    color: #fff;
    margin-bottom: 2px;
}

.critical-alert-message {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.6);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: rgba(10, 22, 40, 0.8);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 16px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
    text-decoration: none;
    display: block;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--stat-color, linear-gradient(135deg, #06b6d4, #0891b2));
}

.stat-card::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 200%;
    background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.03) 50%, transparent 70%);
    transform: translateX(100%);
    transition: transform 0.6s;
}

.stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(6, 182, 212, 0.3);
    box-shadow: 0 12px 35px rgba(6, 182, 212, 0.15);
}

.stat-card:hover::after {
    transform: translateX(-100%);
}

.stat-card.cyan { --stat-color: linear-gradient(135deg, #06b6d4, #0891b2); }
.stat-card.indigo { --stat-color: linear-gradient(135deg, #6366f1, #4f46e5); }
.stat-card.emerald { --stat-color: linear-gradient(135deg, #10b981, #059669); }
.stat-card.amber { --stat-color: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-card.red { --stat-color: linear-gradient(135deg, #ef4444, #dc2626); }

.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    margin-bottom: 1rem;
    background: var(--stat-color);
    color: white;
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
}

.stat-value {
    font-size: 2.25rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 0.35rem;
}

.stat-label {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.stat-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #22d3ee;
    transition: gap 0.2s;
}

.stat-card:hover .stat-link {
    gap: 0.75rem;
}

/* Feature Modules */
.modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.module-card {
    background: rgba(10, 22, 40, 0.8);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s;
}

.module-card:hover {
    transform: translateY(-4px);
    border-color: rgba(6, 182, 212, 0.3);
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
}

.module-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(6, 182, 212, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.module-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.module-icon.gdpr {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
}

.module-icon.monitoring {
    background: linear-gradient(135deg, #10b981, #059669);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.module-icon.config {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
}

.module-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.module-subtitle {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.5);
    margin: 4px 0 0;
}

.module-body {
    padding: 1.5rem;
}

.module-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.module-stat {
    padding: 1rem;
    background: rgba(6, 182, 212, 0.05);
    border: 1px solid rgba(6, 182, 212, 0.1);
    border-radius: 12px;
    transition: all 0.2s;
}

.module-stat:hover {
    background: rgba(6, 182, 212, 0.1);
    transform: translateY(-2px);
}

.module-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}

.module-stat-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.module-actions {
    display: flex;
    gap: 0.75rem;
}

.module-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.module-btn-primary {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: white;
    box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
}

.module-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4);
}

.module-btn-outline {
    background: rgba(6, 182, 212, 0.1);
    border: 1px solid rgba(6, 182, 212, 0.25);
    color: #22d3ee;
}

.module-btn-outline:hover {
    background: rgba(6, 182, 212, 0.2);
}

/* System Status Panel */
.system-panel {
    background: rgba(10, 22, 40, 0.8);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 20px;
    overflow: hidden;
}

.system-panel-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(6, 182, 212, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.system-panel-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 700;
    color: #fff;
}

.system-panel-title i {
    color: #22d3ee;
}

.system-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.system-status-badge.online {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}

.system-status-badge .pulse {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    animation: statusPulse 2s ease-in-out infinite;
}

@keyframes statusPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.system-panel-body {
    padding: 1.5rem;
}

.system-metrics {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.system-metric {
    padding: 1rem;
    background: rgba(6, 182, 212, 0.05);
    border: 1px solid rgba(6, 182, 212, 0.1);
    border-radius: 12px;
    transition: all 0.2s;
}

.system-metric:hover {
    background: rgba(6, 182, 212, 0.1);
}

.system-metric-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.system-metric-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
}

/* Quick Actions Panel */
.quick-actions {
    margin-top: 1.5rem;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem;
    background: rgba(10, 22, 40, 0.6);
    border: 1px solid rgba(6, 182, 212, 0.15);
    border-radius: 14px;
    text-decoration: none;
    transition: all 0.2s;
}

.quick-action-btn:hover {
    background: rgba(6, 182, 212, 0.1);
    border-color: rgba(6, 182, 212, 0.3);
    transform: translateY(-2px);
}

.quick-action-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
}

.quick-action-icon.cyan { background: linear-gradient(135deg, #06b6d4, #0891b2); }
.quick-action-icon.indigo { background: linear-gradient(135deg, #6366f1, #4f46e5); }
.quick-action-icon.emerald { background: linear-gradient(135deg, #10b981, #059669); }
.quick-action-icon.amber { background: linear-gradient(135deg, #f59e0b, #d97706); }

.quick-action-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(255,255,255,0.8);
    text-align: center;
}

/* Super Admin Only Section */
.super-admin-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px dashed rgba(245, 158, 11, 0.3);
}

.super-admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.6rem;
    background: rgba(245, 158, 11, 0.15);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 700;
    color: #fbbf24;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 1rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .modules-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .module-stats {
        grid-template-columns: 1fr;
    }
    .system-metrics {
        grid-template-columns: 1fr;
    }
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<!-- Critical Alerts -->
<?php if ($activeBreaches > 0 || $overdueCount > 0): ?>
<div class="critical-alert">
    <div class="critical-alert-icon">
        <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <div class="critical-alert-content">
        <div class="critical-alert-title">Critical Issues Require Attention</div>
        <div class="critical-alert-message">
            <?php if ($activeBreaches > 0): ?>
                <?= $activeBreaches ?> active data breach<?= $activeBreaches > 1 ? 'es' : '' ?> reported.
            <?php endif; ?>
            <?php if ($overdueCount > 0): ?>
                <?= $overdueCount ?> GDPR request<?= $overdueCount > 1 ? 's' : '' ?> overdue.
            <?php endif; ?>
        </div>
    </div>
    <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/breaches" class="enterprise-btn enterprise-btn-primary" style="flex-shrink: 0;">
        <i class="fa-solid fa-eye"></i>
        Review Now
    </a>
</div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="stats-grid">
    <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests" class="stat-card indigo">
        <div class="stat-icon">
            <i class="fa-solid fa-clipboard-list"></i>
        </div>
        <div class="stat-value"><?= $pendingRequests ?></div>
        <div class="stat-label">Pending GDPR Requests</div>
        <span class="stat-link">
            View Queue <i class="fa-solid fa-arrow-right"></i>
        </span>
    </a>

    <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/breaches" class="stat-card <?= $activeBreaches > 0 ? 'red' : 'emerald' ?>">
        <div class="stat-icon">
            <i class="fa-solid fa-shield-<?= $activeBreaches > 0 ? 'exclamation' : 'check' ?>"></i>
        </div>
        <div class="stat-value"><?= $activeBreaches ?></div>
        <div class="stat-label">Active Breaches</div>
        <span class="stat-link">
            <?= $activeBreaches > 0 ? 'Investigate' : 'View History' ?> <i class="fa-solid fa-arrow-right"></i>
        </span>
    </a>

    <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring" class="stat-card emerald">
        <div class="stat-icon">
            <i class="fa-solid fa-heartbeat"></i>
        </div>
        <div class="stat-value">
            <span class="system-status-badge online">
                <span class="pulse"></span>
                Online
            </span>
        </div>
        <div class="stat-label">System Status</div>
        <span class="stat-link">
            View Metrics <i class="fa-solid fa-arrow-right"></i>
        </span>
    </a>

    <?php if ($isSuperAdmin): ?>
    <a href="<?= $basePath ?>/admin-legacy/enterprise/config/secrets" class="stat-card <?= $vaultAvailable ? 'cyan' : 'amber' ?>">
        <div class="stat-icon">
            <i class="fa-solid fa-vault"></i>
        </div>
        <div class="stat-value"><?= $vaultAvailable ? 'Active' : 'Disabled' ?></div>
        <div class="stat-label">Secrets Vault</div>
        <span class="stat-link">
            Configure <i class="fa-solid fa-arrow-right"></i>
        </span>
    </a>
    <?php else: ?>
    <div class="stat-card cyan">
        <div class="stat-icon">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="stat-value"><?= $avgProcessingTime ?>d</div>
        <div class="stat-label">Avg Processing Time</div>
        <span class="stat-link" style="color: rgba(255,255,255,0.4);">
            GDPR Requests
        </span>
    </div>
    <?php endif; ?>
</div>

<!-- Feature Modules -->
<div class="modules-grid">
    <!-- GDPR Compliance Module -->
    <div class="module-card">
        <div class="module-header">
            <div class="module-icon gdpr">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>
            <div>
                <h3 class="module-title">GDPR Compliance</h3>
                <p class="module-subtitle">Data protection & privacy management</p>
            </div>
        </div>
        <div class="module-body">
            <div class="module-stats">
                <div class="module-stat">
                    <div class="module-stat-value"><?= $pendingRequests ?></div>
                    <div class="module-stat-label">Pending</div>
                </div>
                <div class="module-stat">
                    <div class="module-stat-value"><?= $overdueCount ?></div>
                    <div class="module-stat-label">Overdue</div>
                </div>
                <div class="module-stat">
                    <div class="module-stat-value"><?= $activeBreaches ?></div>
                    <div class="module-stat-label">Breaches</div>
                </div>
                <div class="module-stat">
                    <div class="module-stat-value"><?= $avgProcessingTime ?>d</div>
                    <div class="module-stat-label">Avg Time</div>
                </div>
            </div>
            <div class="module-actions">
                <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr" class="module-btn module-btn-primary">
                    <i class="fa-solid fa-gauge-high"></i>
                    Dashboard
                </a>
                <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests" class="module-btn module-btn-outline">
                    <i class="fa-solid fa-inbox"></i>
                    Requests
                </a>
            </div>
        </div>
    </div>

    <!-- System Monitoring Module -->
    <div class="module-card">
        <div class="module-header">
            <div class="module-icon monitoring">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div>
                <h3 class="module-title">System Monitoring</h3>
                <p class="module-subtitle">Real-time performance & health metrics</p>
            </div>
        </div>
        <div class="module-body">
            <div class="module-stats">
                <div class="module-stat">
                    <div class="module-stat-value"><?= $phpVersion ?></div>
                    <div class="module-stat-label">PHP Version</div>
                </div>
                <div class="module-stat">
                    <div class="module-stat-value"><?= $memoryUsage ?></div>
                    <div class="module-stat-label">Memory</div>
                </div>
                <div class="module-stat">
                    <div class="module-stat-value"><?= strtoupper($environment) ?></div>
                    <div class="module-stat-label">Environment</div>
                </div>
                <div class="module-stat">
                    <div class="module-stat-value">
                        <span class="system-status-badge online">
                            <span class="pulse"></span>
                            OK
                        </span>
                    </div>
                    <div class="module-stat-label">Status</div>
                </div>
            </div>
            <div class="module-actions">
                <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring" class="module-btn module-btn-primary">
                    <i class="fa-solid fa-display"></i>
                    Dashboard
                </a>
                <?php if ($isSuperAdmin): ?>
                <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring/logs" class="module-btn module-btn-outline">
                    <i class="fa-solid fa-file-lines"></i>
                    Logs
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <div class="quick-actions-grid">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests/create" class="quick-action-btn">
            <div class="quick-action-icon indigo">
                <i class="fa-solid fa-plus"></i>
            </div>
            <span class="quick-action-label">New GDPR Request</span>
        </a>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/breaches/report" class="quick-action-btn">
            <div class="quick-action-icon red">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <span class="quick-action-label">Report Breach</span>
        </a>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/consents" class="quick-action-btn">
            <div class="quick-action-icon emerald">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
            <span class="quick-action-label">Manage Consents</span>
        </a>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/audit" class="quick-action-btn">
            <div class="quick-action-icon cyan">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <span class="quick-action-label">Audit Log</span>
        </a>
    </div>
</div>

<?php if ($isSuperAdmin): ?>
<!-- Super Admin Only Section -->
<div class="super-admin-section">
    <div class="super-admin-badge">
        <i class="fa-solid fa-crown"></i>
        Super Admin Only
    </div>

    <div class="modules-grid">
        <!-- Configuration Module -->
        <div class="module-card">
            <div class="module-header">
                <div class="module-icon config">
                    <i class="fa-solid fa-sliders"></i>
                </div>
                <div>
                    <h3 class="module-title">System Configuration</h3>
                    <p class="module-subtitle">Advanced settings & secrets management</p>
                </div>
            </div>
            <div class="module-body">
                <div class="module-stats">
                    <div class="module-stat">
                        <div class="module-stat-value"><?= $vaultEnabled ? 'Yes' : 'No' ?></div>
                        <div class="module-stat-label">Vault Enabled</div>
                    </div>
                    <div class="module-stat">
                        <div class="module-stat-value">
                            <span class="system-status-badge <?= $vaultAvailable ? 'online' : '' ?>" style="<?= !$vaultAvailable ? 'background: rgba(245, 158, 11, 0.15); color: #fbbf24;' : '' ?>">
                                <?= $vaultAvailable ? 'Connected' : 'Disconnected' ?>
                            </span>
                        </div>
                        <div class="module-stat-label">Vault Status</div>
                    </div>
                    <div class="module-stat">
                        <div class="module-stat-value"><?= strtoupper($environment) ?></div>
                        <div class="module-stat-label">Environment</div>
                    </div>
                    <div class="module-stat">
                        <div class="module-stat-value">Ready</div>
                        <div class="module-stat-label">Config Status</div>
                    </div>
                </div>
                <div class="module-actions">
                    <a href="<?= $basePath ?>/admin-legacy/enterprise/config" class="module-btn module-btn-primary">
                        <i class="fa-solid fa-gear"></i>
                        Settings
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/enterprise/config/secrets" class="module-btn module-btn-outline">
                        <i class="fa-solid fa-key"></i>
                        Secrets Vault
                    </a>
                </div>
            </div>
        </div>

        <!-- System Panel -->
        <div class="system-panel">
            <div class="system-panel-header">
                <div class="system-panel-title">
                    <i class="fa-solid fa-server"></i>
                    System Resources
                </div>
                <span class="system-status-badge online">
                    <span class="pulse"></span>
                    Operational
                </span>
            </div>
            <div class="system-panel-body">
                <div class="system-metrics">
                    <div class="system-metric">
                        <div class="system-metric-label">PHP Version</div>
                        <div class="system-metric-value"><?= $phpVersion ?></div>
                    </div>
                    <div class="system-metric">
                        <div class="system-metric-label">Memory Usage</div>
                        <div class="system-metric-value"><?= $memoryUsage ?></div>
                    </div>
                    <div class="system-metric">
                        <div class="system-metric-label">CPU Load</div>
                        <div class="system-metric-value"><?= $cpuLoad ?></div>
                    </div>
                    <div class="system-metric">
                        <div class="system-metric-label">Disk Usage</div>
                        <div class="system-metric-value"><?= $diskUsage ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
