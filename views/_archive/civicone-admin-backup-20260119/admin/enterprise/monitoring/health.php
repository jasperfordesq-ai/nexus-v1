<?php
/**
 * System Health Check - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Health Check';
$adminPageSubtitle = 'Enterprise';
$adminPageIcon = 'fa-stethoscope';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'monitoring';
$currentPage = 'health';

// Health data will be loaded via AJAX from the API endpoint
?>

<style>
/* Health Check - Gold Standard v2.0 */
.health-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.health-page-header-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.health-page-title {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 0;
}

.health-page-title i {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
}

.health-page-subtitle {
    color: #94a3b8;
    font-size: 1rem;
    margin: 0;
    padding-left: 72px;
}

.health-page-actions {
    display: flex;
    gap: 12px;
}

/* Overall Status Banner */
.health-status-banner {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 32px;
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    overflow: hidden;
}

.health-status-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #10b981, #06b6d4, #6366f1);
    opacity: 0;
    transition: opacity 0.3s;
}

.health-status-banner.healthy::before {
    background: linear-gradient(90deg, #10b981, #34d399);
    opacity: 1;
}

.health-status-banner.warning::before {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
    opacity: 1;
}

.health-status-banner.unhealthy::before {
    background: linear-gradient(90deg, #ef4444, #f87171);
    opacity: 1;
}

.health-status-icon {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    flex-shrink: 0;
}

.health-status-banner.healthy .health-status-icon {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.health-status-banner.warning .health-status-icon {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.health-status-banner.unhealthy .health-status-icon {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.health-status-banner.loading .health-status-icon {
    background: rgba(99, 102, 241, 0.15);
    color: #6366f1;
}

.health-status-content {
    flex: 1;
}

.health-status-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 8px 0;
}

.health-status-subtitle {
    color: #94a3b8;
    margin: 0;
    font-size: 1rem;
}

.health-status-meta {
    display: flex;
    gap: 24px;
    margin-top: 16px;
}

.health-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: #94a3b8;
}

.health-meta-item i {
    color: #6366f1;
}

.health-meta-value {
    color: #f1f5f9;
    font-weight: 600;
}

/* Checks Grid */
.health-checks-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

.health-check-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.health-check-card:hover {
    transform: translateY(-2px);
    border-color: rgba(99, 102, 241, 0.4);
}

.health-check-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #64748b;
    transition: background 0.3s;
}

.health-check-card.healthy::before { background: #10b981; }
.health-check-card.warning::before { background: #f59e0b; }
.health-check-card.unhealthy::before { background: #ef4444; }
.health-check-card.not_installed::before { background: #64748b; }
.health-check-card.not_configured::before { background: #64748b; }
.health-check-card.unknown::before { background: #64748b; }

.health-check-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.health-check-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.health-check-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: white;
}

.health-check-icon.database { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.health-check-icon.redis { background: linear-gradient(135deg, #ef4444, #dc2626); }
.health-check-icon.disk { background: linear-gradient(135deg, #f59e0b, #d97706); }
.health-check-icon.vault { background: linear-gradient(135deg, #10b981, #059669); }
.health-check-icon.memory { background: linear-gradient(135deg, #06b6d4, #0891b2); }
.health-check-icon.php { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }

.health-check-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #f1f5f9;
}

.health-check-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.health-check-badge.healthy {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.health-check-badge.warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.health-check-badge.unhealthy {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.health-check-badge.not_installed,
.health-check-badge.not_configured,
.health-check-badge.unknown {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
}

.health-check-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.health-check-detail {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.875rem;
}

.health-check-detail-label {
    color: #94a3b8;
}

.health-check-detail-value {
    color: #f1f5f9;
    font-weight: 500;
    font-family: 'JetBrains Mono', monospace;
}

.health-check-error {
    margin-top: 12px;
    padding: 12px;
    background: rgba(239, 68, 68, 0.1);
    border-radius: 10px;
    font-size: 0.8rem;
    color: #f87171;
    font-family: 'JetBrains Mono', monospace;
    word-break: break-word;
}

/* Progress Bar for Disk */
.health-progress-bar {
    height: 8px;
    background: rgba(100, 116, 139, 0.2);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 12px;
}

.health-progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
}

.health-progress-fill.healthy { background: linear-gradient(90deg, #10b981, #34d399); }
.health-progress-fill.warning { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.health-progress-fill.unhealthy { background: linear-gradient(90deg, #ef4444, #f87171); }

/* System Info Section */
.health-system-info {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 24px;
}

.health-system-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.health-system-title i {
    color: #6366f1;
}

.health-system-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.health-system-item {
    text-align: center;
    padding: 16px;
    background: rgba(30, 41, 59, 0.5);
    border-radius: 12px;
}

.health-system-label {
    font-size: 0.75rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}

.health-system-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #f1f5f9;
}

/* Loading State */
.health-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px;
    color: #94a3b8;
}

.health-loading-spinner {
    width: 48px;
    height: 48px;
    border: 3px solid rgba(99, 102, 241, 0.2);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Admin Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
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

.admin-btn-secondary {
    background: rgba(30, 41, 59, 0.8);
    color: #f1f5f9;
    border: 1px solid rgba(99, 102, 241, 0.3);
}

.admin-btn-secondary:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.5);
}

.admin-btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
}

.admin-btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
}

/* History Table */
.health-history {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 24px;
    margin-top: 32px;
}

.health-history-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.health-history-title i {
    color: #6366f1;
}

.health-history-empty {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

/* Responsive */
@media (max-width: 1200px) {
    .health-checks-grid {
        grid-template-columns: 1fr;
    }

    .health-system-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .health-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .health-page-title {
        font-size: 1.5rem;
    }

    .health-page-title i {
        width: 44px;
        height: 44px;
        font-size: 1.2rem;
    }

    .health-page-subtitle {
        padding-left: 60px;
    }

    .health-status-banner {
        flex-direction: column;
        text-align: center;
    }

    .health-status-meta {
        justify-content: center;
        flex-wrap: wrap;
    }

    .health-system-grid {
        grid-template-columns: 1fr;
    }

    .health-page-actions {
        width: 100%;
        flex-direction: column;
    }

    .admin-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<!-- Page Header -->
<div class="health-page-header">
    <div class="health-page-header-content">
        <h1 class="health-page-title">
            <i class="fa-solid fa-stethoscope"></i>
            System Health Check
        </h1>
        <p class="health-page-subtitle">Real-time health monitoring and diagnostics</p>
    </div>
    <div class="health-page-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Monitoring
        </a>
        <button onclick="runHealthCheck()" class="admin-btn admin-btn-success" id="runCheckBtn">
            <i class="fa-solid fa-play"></i> Run Check
        </button>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<!-- Overall Status Banner -->
<div class="health-status-banner loading" id="statusBanner">
    <div class="health-status-icon">
        <i class="fa-solid fa-spinner fa-spin" id="statusIcon"></i>
    </div>
    <div class="health-status-content">
        <h2 class="health-status-title" id="statusTitle">Running Health Check...</h2>
        <p class="health-status-subtitle" id="statusSubtitle">Please wait while we check all services</p>
        <div class="health-status-meta" id="statusMeta" style="display: none;">
            <div class="health-meta-item">
                <i class="fa-solid fa-clock"></i>
                <span>Latency:</span>
                <span class="health-meta-value" id="metaLatency">--</span>
            </div>
            <div class="health-meta-item">
                <i class="fa-solid fa-calendar"></i>
                <span>Checked:</span>
                <span class="health-meta-value" id="metaTimestamp">--</span>
            </div>
            <div class="health-meta-item">
                <i class="fa-solid fa-code-branch"></i>
                <span>Version:</span>
                <span class="health-meta-value" id="metaVersion">--</span>
            </div>
            <div class="health-meta-item">
                <i class="fa-solid fa-server"></i>
                <span>Environment:</span>
                <span class="health-meta-value" id="metaEnv">--</span>
            </div>
        </div>
    </div>
</div>

<!-- Health Checks Grid -->
<div class="health-checks-grid" id="checksGrid">
    <!-- Database Check -->
    <div class="health-check-card" id="check-database">
        <div class="health-check-header">
            <div class="health-check-title">
                <div class="health-check-icon database">
                    <i class="fa-solid fa-database"></i>
                </div>
                <span class="health-check-name">Database</span>
            </div>
            <span class="health-check-badge" id="badge-database">Checking...</span>
        </div>
        <div class="health-check-details" id="details-database">
            <div class="health-check-detail">
                <span class="health-check-detail-label">Status</span>
                <span class="health-check-detail-value">--</span>
            </div>
        </div>
    </div>

    <!-- Redis Check -->
    <div class="health-check-card" id="check-redis">
        <div class="health-check-header">
            <div class="health-check-title">
                <div class="health-check-icon redis">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <span class="health-check-name">Redis Cache</span>
            </div>
            <span class="health-check-badge" id="badge-redis">Checking...</span>
        </div>
        <div class="health-check-details" id="details-redis">
            <div class="health-check-detail">
                <span class="health-check-detail-label">Status</span>
                <span class="health-check-detail-value">--</span>
            </div>
        </div>
    </div>

    <!-- Disk Check -->
    <div class="health-check-card" id="check-disk">
        <div class="health-check-header">
            <div class="health-check-title">
                <div class="health-check-icon disk">
                    <i class="fa-solid fa-hard-drive"></i>
                </div>
                <span class="health-check-name">Disk Space</span>
            </div>
            <span class="health-check-badge" id="badge-disk">Checking...</span>
        </div>
        <div class="health-check-details" id="details-disk">
            <div class="health-check-detail">
                <span class="health-check-detail-label">Status</span>
                <span class="health-check-detail-value">--</span>
            </div>
        </div>
    </div>

    <!-- Vault Check -->
    <div class="health-check-card" id="check-vault">
        <div class="health-check-header">
            <div class="health-check-title">
                <div class="health-check-icon vault">
                    <i class="fa-solid fa-key"></i>
                </div>
                <span class="health-check-name">Secrets Vault</span>
            </div>
            <span class="health-check-badge" id="badge-vault">Checking...</span>
        </div>
        <div class="health-check-details" id="details-vault">
            <div class="health-check-detail">
                <span class="health-check-detail-label">Status</span>
                <span class="health-check-detail-value">--</span>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="health-system-info">
    <h3 class="health-system-title">
        <i class="fa-solid fa-microchip"></i>
        System Information
    </h3>
    <div class="health-system-grid">
        <div class="health-system-item">
            <div class="health-system-label">PHP Version</div>
            <div class="health-system-value"><?= PHP_VERSION ?></div>
        </div>
        <div class="health-system-item">
            <div class="health-system-label">Memory Limit</div>
            <div class="health-system-value"><?= ini_get('memory_limit') ?></div>
        </div>
        <div class="health-system-item">
            <div class="health-system-label">Max Execution</div>
            <div class="health-system-value"><?= ini_get('max_execution_time') ?>s</div>
        </div>
        <div class="health-system-item">
            <div class="health-system-label">Server</div>
            <div class="health-system-value"><?= PHP_OS_FAMILY ?></div>
        </div>
    </div>
</div>

<!-- Check History -->
<div class="health-history">
    <h3 class="health-history-title">
        <i class="fa-solid fa-clock-rotate-left"></i>
        Recent Checks
    </h3>
    <div class="health-history-empty" id="historyContent">
        <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
        Run a health check to see results here
    </div>
</div>

<script>
const basePath = '<?= $basePath ?>';
let checkHistory = [];

document.addEventListener('DOMContentLoaded', function() {
    runHealthCheck();
});

async function runHealthCheck() {
    const btn = document.getElementById('runCheckBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';

    // Reset to loading state
    updateStatusBanner('loading', 'Running Health Check...', 'Please wait while we check all services');

    try {
        const response = await fetch(basePath + '/admin-legacy/enterprise/monitoring/health', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();
        displayResults(data);
        addToHistory(data);

    } catch (error) {
        console.error('Health check failed:', error);
        updateStatusBanner('unhealthy', 'Health Check Failed', 'Unable to complete health check. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-play"></i> Run Check';
    }
}

function displayResults(data) {
    // Update status banner
    const status = data.status || 'unknown';
    let title, subtitle;

    if (status === 'healthy') {
        title = 'All Systems Operational';
        subtitle = 'All health checks passed successfully';
    } else if (status === 'warning') {
        title = 'Some Issues Detected';
        subtitle = 'One or more services require attention';
    } else {
        title = 'System Unhealthy';
        subtitle = 'Critical services are not responding';
    }

    updateStatusBanner(status, title, subtitle);

    // Update meta info
    document.getElementById('statusMeta').style.display = 'flex';
    document.getElementById('metaLatency').textContent = (data.latency_ms || 0) + 'ms';
    document.getElementById('metaTimestamp').textContent = new Date(data.timestamp).toLocaleString();
    document.getElementById('metaVersion').textContent = data.version || 'N/A';
    document.getElementById('metaEnv').textContent = (data.environment || 'production').toUpperCase();

    // Update individual checks
    const checks = data.checks || {};

    // Database
    updateCheck('database', checks.database || {}, [
        { label: 'Connection', value: (checks.database?.status || 'unknown').toUpperCase() },
        { label: 'Latency', value: (checks.database?.latency_ms || '--') + 'ms' }
    ]);

    // Redis
    updateCheck('redis', checks.redis || {}, [
        { label: 'Connection', value: (checks.redis?.status || 'unknown').replace('_', ' ').toUpperCase() }
    ]);

    // Disk
    const diskCheck = checks.disk || {};
    let diskDetails = [
        { label: 'Status', value: (diskCheck.status || 'unknown').toUpperCase() }
    ];
    if (diskCheck.used_percent !== undefined) {
        diskDetails.push({ label: 'Used', value: diskCheck.used_percent + '%' });
    }
    if (diskCheck.free_gb !== undefined) {
        diskDetails.push({ label: 'Free Space', value: diskCheck.free_gb + ' GB' });
    }
    updateCheck('disk', diskCheck, diskDetails, diskCheck.used_percent);

    // Vault
    updateCheck('vault', checks.vault || {}, [
        { label: 'Status', value: (checks.vault?.status || 'unknown').replace('_', ' ').toUpperCase() },
        { label: 'Using Vault', value: checks.vault?.using_vault ? 'Yes' : 'No' }
    ]);
}

function updateStatusBanner(status, title, subtitle) {
    const banner = document.getElementById('statusBanner');
    const icon = document.getElementById('statusIcon');
    const titleEl = document.getElementById('statusTitle');
    const subtitleEl = document.getElementById('statusSubtitle');

    banner.className = 'health-status-banner ' + status;

    const icons = {
        healthy: 'fa-shield-check',
        warning: 'fa-triangle-exclamation',
        unhealthy: 'fa-circle-xmark',
        loading: 'fa-spinner fa-spin'
    };

    icon.className = 'fa-solid ' + (icons[status] || icons.loading);
    titleEl.textContent = title;
    subtitleEl.textContent = subtitle;
}

function updateCheck(name, data, details, progressPercent = null) {
    const card = document.getElementById('check-' + name);
    const badge = document.getElementById('badge-' + name);
    const detailsEl = document.getElementById('details-' + name);

    const status = data.status || 'unknown';
    card.className = 'health-check-card ' + status;
    badge.className = 'health-check-badge ' + status;
    badge.textContent = status.replace('_', ' ').toUpperCase();

    let html = '';
    details.forEach(d => {
        html += `
            <div class="health-check-detail">
                <span class="health-check-detail-label">${d.label}</span>
                <span class="health-check-detail-value">${d.value}</span>
            </div>
        `;
    });

    if (progressPercent !== null) {
        const progressStatus = progressPercent < 70 ? 'healthy' : (progressPercent < 90 ? 'warning' : 'unhealthy');
        html += `
            <div class="health-progress-bar">
                <div class="health-progress-fill ${progressStatus}" style="width: ${progressPercent}%"></div>
            </div>
        `;
    }

    if (data.error) {
        html += `<div class="health-check-error">${escapeHtml(data.error)}</div>`;
    }

    detailsEl.innerHTML = html;
}

function addToHistory(data) {
    checkHistory.unshift({
        timestamp: new Date(),
        status: data.status,
        latency: data.latency_ms
    });

    if (checkHistory.length > 5) {
        checkHistory = checkHistory.slice(0, 5);
    }

    renderHistory();
}

function renderHistory() {
    const container = document.getElementById('historyContent');

    if (checkHistory.length === 0) {
        container.innerHTML = `
            <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
            Run a health check to see results here
        `;
        return;
    }

    let html = '<div style="display: flex; flex-direction: column; gap: 12px;">';
    checkHistory.forEach((check, index) => {
        const statusColors = {
            healthy: '#10b981',
            warning: '#f59e0b',
            unhealthy: '#ef4444'
        };
        const color = statusColors[check.status] || '#64748b';

        html += `
            <div style="display: flex; align-items: center; gap: 16px; padding: 12px; background: rgba(30, 41, 59, 0.5); border-radius: 10px;">
                <div style="width: 10px; height: 10px; border-radius: 50%; background: ${color};"></div>
                <div style="flex: 1;">
                    <span style="color: #f1f5f9; font-weight: 600;">${check.status.toUpperCase()}</span>
                    <span style="color: #64748b; margin-left: 12px;">${check.timestamp.toLocaleString()}</span>
                </div>
                <div style="color: #94a3b8; font-family: monospace;">${check.latency}ms</div>
            </div>
        `;
    });
    html += '</div>';

    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
