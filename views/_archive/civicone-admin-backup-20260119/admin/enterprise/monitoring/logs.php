<?php
/**
 * System Logs Dashboard - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'System Logs';
$adminPageSubtitle = 'Enterprise';
$adminPageIcon = 'fa-file-lines';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'monitoring';
$currentPage = 'logs';

// Extract logs data
$logFiles = $logs ?? [];

// Calculate totals
$totalSize = 0;
$errorCount = 0;
foreach ($logFiles as $log) {
    $sizeStr = $log['size'] ?? '0 B';
    if (str_contains($log['name'] ?? '', 'error')) {
        $errorCount++;
    }
}
?>

<style>
/* System Logs - Gold Standard v2.0 */
.logs-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.logs-page-header-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.logs-page-title {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 0;
}

.logs-page-title i {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 8px 32px rgba(245, 158, 11, 0.3);
}

.logs-page-subtitle {
    color: #94a3b8;
    font-size: 1rem;
    margin: 0;
    padding-left: 72px;
}

.logs-page-actions {
    display: flex;
    gap: 12px;
}

/* Stats Row */
.logs-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

.logs-stat-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.logs-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}

.logs-stat-icon.purple { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.logs-stat-icon.amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
.logs-stat-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
.logs-stat-icon.cyan { background: linear-gradient(135deg, #06b6d4, #0891b2); }

.logs-stat-content {
    flex: 1;
}

.logs-stat-label {
    font-size: 0.8rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
}

.logs-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
}

/* Filter Bar */
.logs-filter-bar {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.logs-search-wrapper {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.logs-search-wrapper i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
}

.logs-search-input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    color: #f1f5f9;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.logs-search-input:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.logs-search-input::placeholder {
    color: #64748b;
}

.logs-filter-group {
    display: flex;
    gap: 8px;
}

.logs-filter-btn {
    padding: 10px 16px;
    background: transparent;
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    color: #94a3b8;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.logs-filter-btn:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #f1f5f9;
}

.logs-filter-btn.active {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.4);
    color: #a5b4fc;
}

/* Log Cards Grid */
.logs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 20px;
}

.log-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 24px;
    transition: all 0.3s;
    cursor: pointer;
    text-decoration: none;
    display: block;
    position: relative;
    overflow: hidden;
}

.log-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.5), transparent);
    opacity: 0;
    transition: opacity 0.3s;
}

.log-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    border-color: rgba(99, 102, 241, 0.4);
}

.log-card:hover::before {
    opacity: 1;
}

.log-card-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 16px;
}

.log-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: white;
    flex-shrink: 0;
}

.log-icon.app { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.log-icon.error { background: linear-gradient(135deg, #ef4444, #f87171); }
.log-icon.cron { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.log-icon.access { background: linear-gradient(135deg, #10b981, #34d399); }
.log-icon.debug { background: linear-gradient(135deg, #06b6d4, #22d3ee); }
.log-icon.default { background: linear-gradient(135deg, #64748b, #94a3b8); }

.log-info {
    flex: 1;
    min-width: 0;
}

.log-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #f1f5f9;
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.log-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 0.8rem;
    color: #94a3b8;
}

.log-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.log-meta-item i {
    font-size: 0.7rem;
    color: #64748b;
}

.log-card-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
}

.log-action-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: transparent;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.log-action-btn:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #f1f5f9;
    border-color: rgba(99, 102, 241, 0.4);
}

.log-preview {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    padding: 14px;
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 0.75rem;
    color: #94a3b8;
    line-height: 1.7;
    max-height: 72px;
    overflow: hidden;
    position: relative;
}

.log-preview::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 28px;
    background: linear-gradient(to bottom, transparent, rgba(0, 0, 0, 0.3));
}

.log-preview .error-line {
    color: #f87171;
}

.log-preview .warn-line {
    color: #fbbf24;
}

.log-preview .info-line {
    color: #60a5fa;
}

/* Empty State */
.logs-empty-state {
    text-align: center;
    padding: 80px 24px;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
}

.logs-empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #6366f1;
    margin: 0 auto 24px auto;
}

.logs-empty-state h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0 0 12px 0;
}

.logs-empty-state p {
    color: #94a3b8;
    margin: 0;
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

.admin-btn-amber {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);
}

.admin-btn-amber:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
}

/* Responsive */
@media (max-width: 1200px) {
    .logs-stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .logs-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .logs-page-title {
        font-size: 1.5rem;
    }

    .logs-page-title i {
        width: 44px;
        height: 44px;
        font-size: 1.2rem;
    }

    .logs-page-subtitle {
        padding-left: 60px;
    }

    .logs-stats-row {
        grid-template-columns: 1fr;
    }

    .logs-filter-bar {
        flex-direction: column;
        align-items: stretch;
    }

    .logs-search-wrapper {
        min-width: 100%;
    }

    .logs-filter-group {
        justify-content: center;
    }

    .logs-grid {
        grid-template-columns: 1fr;
    }

    .log-card-header {
        flex-wrap: wrap;
    }

    .log-card-actions {
        width: 100%;
        justify-content: flex-end;
        margin-top: 12px;
    }
}
</style>

<!-- Page Header -->
<div class="logs-page-header">
    <div class="logs-page-header-content">
        <h1 class="logs-page-title">
            <i class="fa-solid fa-file-lines"></i>
            System Logs
        </h1>
        <p class="logs-page-subtitle">Browse and analyze application log files</p>
    </div>
    <div class="logs-page-actions">
        <a href="<?= $basePath ?>/admin/enterprise/monitoring" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Monitoring
        </a>
        <a href="<?= $basePath ?>/admin/enterprise/monitoring/logs/download-all" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-download"></i> Download All
        </a>
        <button onclick="location.reload()" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-sync"></i> Refresh
        </button>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<!-- Stats Row -->
<div class="logs-stats-row">
    <div class="logs-stat-card">
        <div class="logs-stat-icon purple">
            <i class="fa-solid fa-files"></i>
        </div>
        <div class="logs-stat-content">
            <div class="logs-stat-label">Total Log Files</div>
            <div class="logs-stat-value"><?= count($logFiles) ?></div>
        </div>
    </div>

    <div class="logs-stat-card">
        <div class="logs-stat-icon red">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="logs-stat-content">
            <div class="logs-stat-label">Error Logs</div>
            <div class="logs-stat-value"><?= $errorCount ?></div>
        </div>
    </div>

    <div class="logs-stat-card">
        <div class="logs-stat-icon amber">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="logs-stat-content">
            <div class="logs-stat-label">Last Updated</div>
            <div class="logs-stat-value"><?= date('g:i A') ?></div>
        </div>
    </div>

    <div class="logs-stat-card">
        <div class="logs-stat-icon cyan">
            <i class="fa-solid fa-server"></i>
        </div>
        <div class="logs-stat-content">
            <div class="logs-stat-label">Log Retention</div>
            <div class="logs-stat-value">30 Days</div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="logs-filter-bar">
    <div class="logs-search-wrapper">
        <i class="fa-solid fa-search"></i>
        <input type="text" class="logs-search-input" id="logSearch" placeholder="Search log files...">
    </div>
    <div class="logs-filter-group">
        <button class="logs-filter-btn active" data-filter="all">
            <i class="fa-solid fa-layer-group"></i> All
        </button>
        <button class="logs-filter-btn" data-filter="error">
            <i class="fa-solid fa-circle-exclamation"></i> Errors
        </button>
        <button class="logs-filter-btn" data-filter="app">
            <i class="fa-solid fa-code"></i> Application
        </button>
        <button class="logs-filter-btn" data-filter="cron">
            <i class="fa-solid fa-clock"></i> Cron
        </button>
        <button class="logs-filter-btn" data-filter="access">
            <i class="fa-solid fa-globe"></i> Access
        </button>
    </div>
</div>

<!-- Logs Grid -->
<?php if (empty($logFiles)): ?>
    <div class="logs-empty-state">
        <div class="logs-empty-icon">
            <i class="fa-solid fa-inbox"></i>
        </div>
        <h3>No Log Files Found</h3>
        <p>There are currently no log files available to view. Logs will appear here once generated.</p>
    </div>
<?php else: ?>
    <div class="logs-grid" id="logsGrid">
        <?php foreach ($logFiles as $log): ?>
            <?php
            $filename = $log['name'] ?? 'unknown.log';
            $iconClass = 'default';
            $filterType = 'other';

            if (str_contains($filename, 'error')) {
                $iconClass = 'error';
                $filterType = 'error';
            } elseif (str_contains($filename, 'cron')) {
                $iconClass = 'cron';
                $filterType = 'cron';
            } elseif (str_contains($filename, 'access')) {
                $iconClass = 'access';
                $filterType = 'access';
            } elseif (str_contains($filename, 'app') || str_contains($filename, 'application')) {
                $iconClass = 'app';
                $filterType = 'app';
            } elseif (str_contains($filename, 'debug')) {
                $iconClass = 'debug';
                $filterType = 'app';
            }

            $iconMap = [
                'error' => 'fa-triangle-exclamation',
                'cron' => 'fa-clock',
                'access' => 'fa-globe',
                'app' => 'fa-code',
                'debug' => 'fa-bug',
                'default' => 'fa-file-lines'
            ];
            ?>
            <a href="<?= $basePath ?>/admin/enterprise/monitoring/logs/<?= urlencode($filename) ?>"
               class="log-card"
               data-name="<?= htmlspecialchars(strtolower($filename)) ?>"
               data-type="<?= $filterType ?>">
                <div class="log-card-header">
                    <div class="log-icon <?= $iconClass ?>">
                        <i class="fa-solid <?= $iconMap[$iconClass] ?>"></i>
                    </div>
                    <div class="log-info">
                        <div class="log-name"><?= htmlspecialchars($filename) ?></div>
                        <div class="log-meta">
                            <span class="log-meta-item">
                                <i class="fa-solid fa-hard-drive"></i>
                                <?= htmlspecialchars($log['size'] ?? 'Unknown') ?>
                            </span>
                            <span class="log-meta-item">
                                <i class="fa-solid fa-clock"></i>
                                <?= htmlspecialchars($log['modified'] ?? 'Unknown') ?>
                            </span>
                        </div>
                    </div>
                    <div class="log-card-actions">
                        <button class="log-action-btn" onclick="event.preventDefault(); downloadLog('<?= urlencode($filename) ?>')" title="Download">
                            <i class="fa-solid fa-download"></i>
                        </button>
                    </div>
                </div>
                <?php if (!empty($log['preview'])): ?>
                    <div class="log-preview">
                        <?= htmlspecialchars($log['preview']) ?>
                    </div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('logSearch');
    const filterBtns = document.querySelectorAll('.logs-filter-btn');
    const logsGrid = document.getElementById('logsGrid');
    const logCards = logsGrid ? logsGrid.querySelectorAll('.log-card') : [];

    let currentFilter = 'all';

    // Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterLogs();
        });
    }

    // Filter buttons
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            filterLogs();
        });
    });

    function filterLogs() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';

        logCards.forEach(card => {
            const name = card.dataset.name || '';
            const type = card.dataset.type || '';

            const matchesSearch = name.includes(searchTerm);
            const matchesFilter = currentFilter === 'all' || type === currentFilter;

            card.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
        });
    }
});

function downloadLog(filename) {
    window.location.href = '<?= $basePath ?>/admin/enterprise/monitoring/logs/' + filename + '/download';
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
