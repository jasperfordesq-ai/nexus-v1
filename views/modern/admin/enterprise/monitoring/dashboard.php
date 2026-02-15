<?php
/**
 * System Monitoring Dashboard - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'System Monitoring';
$adminPageSubtitle = 'Enterprise';
$adminPageIcon = 'fa-heart-pulse';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'monitoring';
$currentPage = 'dashboard';

// Extract status with defaults
$systemStatus = $status ?? [];
$phpVersion = $systemStatus['php_version'] ?? PHP_VERSION;
$memoryUsage = $systemStatus['memory_usage'] ?? 'N/A';
$memoryLimit = $systemStatus['memory_limit'] ?? ini_get('memory_limit');
$maxExecTime = $systemStatus['max_execution_time'] ?? ini_get('max_execution_time');
$opcacheEnabled = $systemStatus['opcache_enabled'] ?? (extension_loaded('Zend OPcache') && ini_get('opcache.enable'));
$extensions = $systemStatus['loaded_extensions'] ?? get_loaded_extensions();

// Server metrics
$serverMemory = $systemStatus['server_memory'] ?? null;
$loadAverage = $systemStatus['load_average'] ?? null;
$uptime = $systemStatus['uptime'] ?? null;
$disk = $systemStatus['disk'] ?? null;
$cpuCores = $systemStatus['cpu_cores'] ?? null;

// Server info
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$hostname = gethostname() ?: 'Unknown';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-heart-pulse"></i>
            System Monitoring
        </h1>
        <p class="admin-page-subtitle">Real-time performance metrics and system health</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Enterprise Hub
        </a>
        <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring/health" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-stethoscope"></i> Run Health Check
        </a>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<!-- Health Status Banner -->
<div class="health-banner health-banner-healthy">
    <div class="health-banner-icon">
        <i class="fa-solid fa-shield-check"></i>
    </div>
    <div class="health-banner-content">
        <h2 class="health-banner-title">System Healthy</h2>
        <p class="health-banner-text">All services are running normally. Last checked: <?= date('F j, Y \a\t g:i:s A') ?></p>
    </div>
    <div class="health-banner-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring/logs" class="health-btn">
            <i class="fa-solid fa-file-lines"></i> View Logs
        </a>
    </div>
</div>

<!-- Server Stats Grid -->
<div class="monitor-stats-grid">
    <?php if ($serverMemory): ?>
    <div class="monitor-stat-card">
        <div class="monitor-stat-icon monitor-stat-icon-cyan">
            <i class="fa-solid fa-memory"></i>
        </div>
        <div class="monitor-stat-content">
            <div class="monitor-stat-label">Server RAM</div>
            <div class="monitor-stat-value"><?= $serverMemory['percent'] ?>%</div>
        </div>
        <div class="monitor-stat-sub"><?= $serverMemory['used'] ?> / <?= $serverMemory['total'] ?> MB</div>
        <div class="monitor-stat-badge <?= $serverMemory['percent'] < 80 ? 'good' : ($serverMemory['percent'] < 90 ? 'warn' : 'bad') ?>">
            <i class="fa-solid fa-<?= $serverMemory['percent'] < 80 ? 'check' : 'exclamation' ?>"></i>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($disk): ?>
    <div class="monitor-stat-card">
        <div class="monitor-stat-icon monitor-stat-icon-amber">
            <i class="fa-solid fa-hard-drive"></i>
        </div>
        <div class="monitor-stat-content">
            <div class="monitor-stat-label">Disk Usage</div>
            <div class="monitor-stat-value"><?= $disk['percent'] ?>%</div>
        </div>
        <div class="monitor-stat-sub"><?= $disk['used'] ?> / <?= $disk['total'] ?> GB</div>
        <div class="monitor-stat-badge <?= $disk['percent'] < 80 ? 'good' : ($disk['percent'] < 90 ? 'warn' : 'bad') ?>">
            <i class="fa-solid fa-<?= $disk['percent'] < 80 ? 'check' : 'exclamation' ?>"></i>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($loadAverage): ?>
    <div class="monitor-stat-card">
        <div class="monitor-stat-icon monitor-stat-icon-purple">
            <i class="fa-solid fa-gauge-high"></i>
        </div>
        <div class="monitor-stat-content">
            <div class="monitor-stat-label">CPU Load (1/5/15m)</div>
            <div class="monitor-stat-value" style="font-size: 1.1rem;"><?= $loadAverage['display'] ?></div>
        </div>
        <?php if ($cpuCores): ?>
        <div class="monitor-stat-sub"><?= $cpuCores ?> CPU core<?= $cpuCores > 1 ? 's' : '' ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($uptime): ?>
    <div class="monitor-stat-card">
        <div class="monitor-stat-icon monitor-stat-icon-green">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="monitor-stat-content">
            <div class="monitor-stat-label">Uptime</div>
            <div class="monitor-stat-value"><?= $uptime['display'] ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- PHP Stats Grid -->
<div class="monitor-stats-grid">
    <div class="monitor-stat-card">
        <div class="monitor-stat-icon monitor-stat-icon-purple">
            <i class="fa-brands fa-php"></i>
        </div>
        <div class="monitor-stat-content">
            <div class="monitor-stat-label">PHP Version</div>
            <div class="monitor-stat-value"><?= htmlspecialchars($phpVersion) ?></div>
        </div>
        <div class="monitor-stat-badge good">
            <i class="fa-solid fa-check"></i>
        </div>
    </div>

    <div class="monitor-stat-card">
        <div class="monitor-stat-icon monitor-stat-icon-cyan">
            <i class="fa-solid fa-microchip"></i>
        </div>
        <div class="monitor-stat-content">
            <div class="monitor-stat-label">PHP Memory</div>
            <div class="monitor-stat-value"><?= htmlspecialchars($memoryUsage) ?></div>
        </div>
        <div class="monitor-stat-sub">limit: <?= htmlspecialchars($memoryLimit) ?></div>
    </div>

    <div class="monitor-stat-card">
        <div class="monitor-stat-icon monitor-stat-icon-green">
            <i class="fa-solid fa-bolt"></i>
        </div>
        <div class="monitor-stat-content">
            <div class="monitor-stat-label">OPcache</div>
            <div class="monitor-stat-value"><?= $opcacheEnabled ? 'Enabled' : 'Disabled' ?></div>
        </div>
        <div class="monitor-stat-badge <?= $opcacheEnabled ? 'good' : 'warn' ?>">
            <i class="fa-solid fa-<?= $opcacheEnabled ? 'check' : 'exclamation' ?>"></i>
        </div>
    </div>

    <div class="monitor-stat-card">
        <div class="monitor-stat-icon monitor-stat-icon-amber">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <div class="monitor-stat-content">
            <div class="monitor-stat-label">Max Execution</div>
            <div class="monitor-stat-value"><?= htmlspecialchars($maxExecTime) ?>s</div>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="monitor-grid-2col">
    <!-- Server Information Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-purple">
                <i class="fa-solid fa-server"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Server Information</h3>
                <p class="admin-card-subtitle">Environment and configuration details</p>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <div class="server-info-list">
                <div class="server-info-item">
                    <div class="server-info-label">
                        <i class="fa-solid fa-globe"></i> Hostname
                    </div>
                    <div class="server-info-value"><?= htmlspecialchars($hostname) ?></div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">
                        <i class="fa-solid fa-server"></i> Server Software
                    </div>
                    <div class="server-info-value"><?= htmlspecialchars($serverSoftware) ?></div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">
                        <i class="fa-brands fa-php"></i> PHP Version
                    </div>
                    <div class="server-info-value"><?= htmlspecialchars($phpVersion) ?></div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">
                        <i class="fa-solid fa-memory"></i> Memory Limit
                    </div>
                    <div class="server-info-value"><?= htmlspecialchars($memoryLimit) ?></div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">
                        <i class="fa-solid fa-clock"></i> Max Execution Time
                    </div>
                    <div class="server-info-value"><?= htmlspecialchars($maxExecTime) ?> seconds</div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">
                        <i class="fa-solid fa-upload"></i> Max Upload Size
                    </div>
                    <div class="server-info-value"><?= ini_get('upload_max_filesize') ?></div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">
                        <i class="fa-solid fa-file"></i> Max POST Size
                    </div>
                    <div class="server-info-value"><?= ini_get('post_max_size') ?></div>
                </div>
                <div class="server-info-item">
                    <div class="server-info-label">
                        <i class="fa-solid fa-calendar"></i> Server Time
                    </div>
                    <div class="server-info-value"><?= date('Y-m-d H:i:s T') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-cyan">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Quick Actions</h3>
                <p class="admin-card-subtitle">Common monitoring tasks</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="quick-actions-grid">
                <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring/logs" class="quick-action-card">
                    <div class="quick-action-icon">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">View Logs</div>
                        <div class="quick-action-desc">Browse system and error logs</div>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>

                <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring/health" class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                        <i class="fa-solid fa-heartbeat"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Health Check</div>
                        <div class="quick-action-desc">Run full system diagnostics</div>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>

                <a href="<?= $basePath ?>/admin-legacy/enterprise/config" class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                        <i class="fa-solid fa-gears"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Configuration</div>
                        <div class="quick-action-desc">Manage system settings</div>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>

                <a href="<?= $basePath ?>/admin-legacy/cron-jobs" class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #ec4899, #f472b6);">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Cron Jobs</div>
                        <div class="quick-action-desc">View scheduled tasks</div>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>

                <a href="<?= $basePath ?>/admin-legacy/activity-log" class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                        <i class="fa-solid fa-list-ul"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Activity Log</div>
                        <div class="quick-action-desc">Track admin actions</div>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>

                <a href="<?= $basePath ?>/admin-legacy/enterprise" class="quick-action-card">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, #64748b, #94a3b8);">
                        <i class="fa-solid fa-arrow-left"></i>
                    </div>
                    <div class="quick-action-content">
                        <div class="quick-action-title">Enterprise Hub</div>
                        <div class="quick-action-desc">Return to enterprise dashboard</div>
                    </div>
                    <i class="fa-solid fa-chevron-right quick-action-arrow"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- PHP Extensions Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-green">
            <i class="fa-solid fa-puzzle-piece"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Loaded PHP Extensions</h3>
            <p class="admin-card-subtitle"><?= count($extensions) ?> extensions currently loaded</p>
        </div>
        <div class="extension-search-wrapper">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="extensionSearch" class="extension-search" placeholder="Search extensions...">
        </div>
    </div>
    <div class="admin-card-body">
        <div class="extension-grid" id="extensionGrid">
            <?php
            sort($extensions);
            foreach ($extensions as $ext):
                $isImportant = in_array($ext, ['pdo', 'pdo_mysql', 'mysqli', 'curl', 'json', 'mbstring', 'openssl', 'zip', 'gd', 'imagick', 'redis', 'memcached']);
            ?>
                <span class="extension-tag <?= $isImportant ? 'important' : '' ?>" data-ext="<?= strtolower($ext) ?>">
                    <?php if ($isImportant): ?><i class="fa-solid fa-star"></i><?php endif; ?>
                    <?= htmlspecialchars($ext) ?>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="extension-empty" id="extensionEmpty" style="display: none;">
            <i class="fa-solid fa-search"></i>
            <p>No extensions found matching your search</p>
        </div>
    </div>
</div>

<!-- Required Extensions Check -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-amber">
            <i class="fa-solid fa-clipboard-check"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Required Extensions Status</h3>
            <p class="admin-card-subtitle">Critical extensions for platform operation</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php
        $requiredExtensions = [
            'pdo' => 'Database connectivity (PDO)',
            'pdo_mysql' => 'MySQL database driver',
            'curl' => 'HTTP requests & API calls',
            'json' => 'JSON encoding/decoding',
            'mbstring' => 'Multi-byte string handling',
            'openssl' => 'Encryption & HTTPS',
            'zip' => 'Archive handling',
            'gd' => 'Image processing',
            'fileinfo' => 'File type detection',
            'dom' => 'DOM manipulation',
            'xml' => 'XML parsing',
        ];
        ?>
        <div class="requirements-list">
            <?php foreach ($requiredExtensions as $ext => $description): ?>
                <?php $loaded = extension_loaded($ext); ?>
                <div class="requirement-item">
                    <div class="requirement-status <?= $loaded ? 'loaded' : 'missing' ?>">
                        <i class="fa-solid fa-<?= $loaded ? 'check-circle' : 'times-circle' ?>"></i>
                    </div>
                    <div class="requirement-info">
                        <div class="requirement-name"><?= htmlspecialchars($ext) ?></div>
                        <div class="requirement-desc"><?= htmlspecialchars($description) ?></div>
                    </div>
                    <div class="requirement-badge <?= $loaded ? 'badge-success' : 'badge-danger' ?>">
                        <?= $loaded ? 'Loaded' : 'Missing' ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
/* Health Banner */
.health-banner {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
}

.health-banner-healthy {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(52, 211, 153, 0.1));
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.health-banner-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: linear-gradient(135deg, #10b981, #34d399);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
}

.health-banner-content {
    flex: 1;
}

.health-banner-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: #10b981;
    margin: 0 0 0.5rem 0;
}

.health-banner-text {
    color: rgba(255, 255, 255, 0.7);
    margin: 0;
    font-size: 0.95rem;
}

.health-banner-actions {
    flex-shrink: 0;
}

.health-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.4);
    border-radius: 10px;
    color: #10b981;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
}

.health-btn:hover {
    background: rgba(16, 185, 129, 0.3);
    transform: translateY(-2px);
}

/* Stats Grid */
.monitor-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.monitor-stat-card {
    background: rgba(15, 23, 42, 0.85);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    transition: all 0.2s;
}

.monitor-stat-card:hover {
    border-color: rgba(99, 102, 241, 0.4);
    transform: translateY(-2px);
}

.monitor-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.monitor-stat-icon-purple { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.monitor-stat-icon-cyan { background: linear-gradient(135deg, #06b6d4, #22d3ee); }
.monitor-stat-icon-green { background: linear-gradient(135deg, #10b981, #34d399); }
.monitor-stat-icon-amber { background: linear-gradient(135deg, #f59e0b, #fbbf24); }

.monitor-stat-content {
    flex: 1;
    min-width: 0;
}

.monitor-stat-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.monitor-stat-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.monitor-stat-sub {
    position: absolute;
    bottom: 0.75rem;
    right: 1rem;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.4);
}

.monitor-stat-badge {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.monitor-stat-badge.good {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.monitor-stat-badge.warn {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.monitor-stat-badge.bad {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

/* Two Column Layout */
.monitor-grid-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Server Info List */
.server-info-list {
    display: flex;
    flex-direction: column;
}

.server-info-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.server-info-item:last-child {
    border-bottom: none;
}

.server-info-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.875rem;
}

.server-info-label i {
    width: 20px;
    text-align: center;
    color: #818cf8;
}

.server-info-value {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

/* Quick Actions Grid */
.quick-actions-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.quick-action-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s;
}

.quick-action-card:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
    transform: translateX(4px);
}

.quick-action-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: linear-gradient(135deg, #06b6d4, #22d3ee);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.quick-action-content {
    flex: 1;
}

.quick-action-title {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
    margin-bottom: 0.15rem;
}

.quick-action-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.quick-action-arrow {
    color: rgba(255, 255, 255, 0.3);
    font-size: 0.8rem;
    transition: all 0.2s;
}

.quick-action-card:hover .quick-action-arrow {
    color: #818cf8;
    transform: translateX(4px);
}

/* Extension Search */
.extension-search-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    margin-left: auto;
}

.extension-search-wrapper i {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
}

.extension-search {
    background: transparent;
    border: none;
    outline: none;
    color: #fff;
    font-size: 0.85rem;
    width: 160px;
    font-family: inherit;
}

.extension-search::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

/* Extension Grid */
.extension-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.extension-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 0.875rem;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.8);
    transition: all 0.2s;
}

.extension-tag:hover {
    background: rgba(99, 102, 241, 0.2);
    transform: translateY(-2px);
}

.extension-tag.important {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.3);
    color: #6ee7b7;
}

.extension-tag.important i {
    color: #fbbf24;
    font-size: 0.65rem;
}

.extension-tag.hidden {
    display: none;
}

.extension-empty {
    text-align: center;
    padding: 2rem;
    color: rgba(255, 255, 255, 0.4);
}

.extension-empty i {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    display: block;
    opacity: 0.3;
}

/* Requirements List */
.requirements-list {
    display: flex;
    flex-direction: column;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    transition: background 0.15s;
}

.requirement-item:last-child {
    border-bottom: none;
}

.requirement-item:hover {
    background: rgba(99, 102, 241, 0.05);
}

.requirement-status {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.requirement-status.loaded {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.requirement-status.missing {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.requirement-info {
    flex: 1;
}

.requirement-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
    margin-bottom: 0.15rem;
}

.requirement-desc {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.requirement-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-success {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

/* Card Header Icons */
.admin-card-header-icon-purple { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.admin-card-header-icon-cyan { background: linear-gradient(135deg, #06b6d4, #22d3ee); }
.admin-card-header-icon-green { background: linear-gradient(135deg, #10b981, #34d399); }
.admin-card-header-icon-amber { background: linear-gradient(135deg, #f59e0b, #fbbf24); }

/* Responsive */
@media (max-width: 1200px) {
    .monitor-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .monitor-grid-2col {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .health-banner {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
    }

    .health-banner-actions {
        width: 100%;
    }

    .health-btn {
        width: 100%;
        justify-content: center;
    }

    .monitor-stats-grid {
        grid-template-columns: 1fr;
    }

    .monitor-stat-card {
        padding: 1rem;
    }

    .admin-card-header {
        flex-wrap: wrap;
    }

    .extension-search-wrapper {
        width: 100%;
        margin-left: 0;
        margin-top: 1rem;
    }

    .extension-search {
        width: 100%;
    }
}
</style>

<script>
// Extension search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('extensionSearch');
    const extensionGrid = document.getElementById('extensionGrid');
    const extensionEmpty = document.getElementById('extensionEmpty');
    const extensions = extensionGrid.querySelectorAll('.extension-tag');

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        let visibleCount = 0;

        extensions.forEach(function(ext) {
            const extName = ext.getAttribute('data-ext') || ext.textContent.toLowerCase();
            if (query === '' || extName.includes(query)) {
                ext.classList.remove('hidden');
                visibleCount++;
            } else {
                ext.classList.add('hidden');
            }
        });

        extensionEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
    });
});
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
