<?php
/**
 * Log Viewer - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'View Log';
$adminPageSubtitle = 'Enterprise';
$adminPageIcon = 'fa-file-lines';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'monitoring';
$currentPage = 'logs';

// Extract log data
$logContent = $content ?? '';
$logFilename = $filename ?? 'unknown.log';
?>

<style>
/* Log Viewer - Gold Standard v2.0 */

/* Page Header */
.log-viewer-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.log-viewer-page-header-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.log-viewer-page-title {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 0;
}

.log-viewer-page-title i {
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

.log-viewer-page-subtitle {
    color: #94a3b8;
    font-size: 1rem;
    margin: 0;
    padding-left: 72px;
}

.log-viewer-page-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

/* Controls */
.log-controls {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}

.log-controls-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.log-controls-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.control-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.control-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #94a3b8;
}

.control-select {
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(0, 0, 0, 0.2);
    color: #f1f5f9;
    font-size: 0.875rem;
    cursor: pointer;
}

.control-select:focus {
    outline: none;
    border-color: #6366f1;
}

/* Log Content */
.log-content-wrapper {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    overflow: hidden;
}

.log-content-header {
    padding: 16px 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.log-content-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: #f1f5f9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.log-line-count {
    font-size: 0.875rem;
    color: #94a3b8;
}

.log-content {
    padding: 24px;
    background: rgba(0, 0, 0, 0.3);
    font-family: 'Courier New', 'Monaco', 'Consolas', monospace;
    font-size: 0.85rem;
    line-height: 1.8;
    color: #f1f5f9;
    overflow-x: auto;
    max-height: 70vh;
    overflow-y: auto;
}

.log-line {
    display: flex;
    gap: 16px;
    padding: 2px 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.05);
}

.log-line:hover {
    background: rgba(99, 102, 241, 0.05);
}

.log-line-number {
    color: #94a3b8;
    user-select: none;
    min-width: 50px;
    text-align: right;
    font-size: 0.75rem;
}

.log-line-content {
    flex: 1;
    white-space: pre-wrap;
    word-break: break-all;
}

/* Log Level Colors */
.log-line-content:has(.log-level-error),
.log-line:has(.log-level-error) {
    background: rgba(239, 68, 68, 0.1);
}

.log-level-error {
    color: #ef4444;
    font-weight: 700;
}

.log-line-content:has(.log-level-warning),
.log-line:has(.log-level-warning) {
    background: rgba(245, 158, 11, 0.1);
}

.log-level-warning {
    color: #f59e0b;
    font-weight: 700;
}

.log-level-info {
    color: #06b6d4;
}

.log-level-debug {
    color: #94a3b8;
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

.admin-btn-danger {
    background: linear-gradient(135deg, #ef4444, #f87171);
    color: white;
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
}

.admin-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
}

.admin-btn-outline {
    background: transparent;
    color: #f1f5f9;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-outline:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.4);
}

/* Scrollbar */
.log-content::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}

.log-content::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
}

.log-content::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.3);
    border-radius: 10px;
}

.log-content::-webkit-scrollbar-thumb:hover {
    background: rgba(99, 102, 241, 0.5);
}

/* Responsive */
@media (max-width: 768px) {
    .log-viewer-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .log-viewer-page-title {
        font-size: 1.5rem;
    }

    .log-viewer-page-title i {
        width: 44px;
        height: 44px;
        font-size: 1.2rem;
    }

    .log-viewer-page-subtitle {
        padding-left: 60px;
    }

    .log-viewer-page-actions {
        width: 100%;
    }

    .admin-btn {
        flex: 1;
    }

    .log-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .log-controls-left,
    .log-controls-right {
        width: 100%;
        justify-content: space-between;
    }

    .log-content {
        font-size: 0.75rem;
    }

    .log-line {
        flex-direction: column;
        gap: 4px;
    }

    .log-line-number {
        min-width: auto;
        text-align: left;
    }
}
</style>

<!-- Page Header -->
<div class="log-viewer-page-header">
    <div class="log-viewer-page-header-content">
        <h1 class="log-viewer-page-title">
            <i class="fa-solid fa-file-code"></i>
            <?= htmlspecialchars($logFilename) ?>
        </h1>
        <p class="log-viewer-page-subtitle">Last updated: <?= date('Y-m-d H:i:s') ?></p>
    </div>
    <div class="log-viewer-page-actions">
        <a href="<?= $basePath ?>/admin-legacy/enterprise/monitoring/logs" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Logs
        </a>
        <button onclick="downloadLog()" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-download"></i> Download
        </button>
        <button onclick="clearLog()" class="admin-btn admin-btn-danger">
            <i class="fa-solid fa-trash"></i> Clear Log
        </button>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<!-- Controls -->
<div class="log-controls">
    <div class="log-controls-left">
        <div class="control-group">
            <label class="control-label">Lines:</label>
            <select class="control-select" id="lineCount" onchange="changeLineCount(this.value)">
                <option value="50">50</option>
                <option value="100" selected>100</option>
                <option value="250">250</option>
                <option value="500">500</option>
                <option value="1000">1000</option>
                <option value="all">All</option>
            </select>
        </div>
        <div class="control-group">
            <label class="control-label">Filter:</label>
            <select class="control-select" id="logFilter" onchange="filterLogs(this.value)">
                <option value="all">All Levels</option>
                <option value="error">Errors Only</option>
                <option value="warning">Warnings Only</option>
                <option value="info">Info Only</option>
                <option value="debug">Debug Only</option>
            </select>
        </div>
    </div>
    <div class="log-controls-right">
        <button onclick="location.reload()" class="admin-btn admin-btn-outline">
            <i class="fa-solid fa-sync"></i> Refresh
        </button>
        <button onclick="toggleAutoRefresh()" class="admin-btn admin-btn-outline" id="autoRefreshBtn">
            <i class="fa-solid fa-play"></i> Auto-refresh
        </button>
    </div>
</div>

<!-- Log Content -->
<div class="log-content-wrapper">
    <div class="log-content-header">
        <span class="log-content-title">Log Output</span>
        <span class="log-line-count" id="lineCountDisplay">
            <?= count(explode("\n", $logContent)) ?> lines
        </span>
    </div>
    <div class="log-content" id="logContent">
        <?php
        $lines = explode("\n", $logContent);
        $lineNumber = 1;
        foreach ($lines as $line):
            if (trim($line) === '') continue;

            // Detect log level
            $logLevel = '';
            if (preg_match('/\b(ERROR|CRITICAL|FATAL)\b/i', $line)) {
                $logLevel = 'error';
            } elseif (preg_match('/\b(WARNING|WARN)\b/i', $line)) {
                $logLevel = 'warning';
            } elseif (preg_match('/\b(INFO)\b/i', $line)) {
                $logLevel = 'info';
            } elseif (preg_match('/\b(DEBUG)\b/i', $line)) {
                $logLevel = 'debug';
            }

            $lineClass = $logLevel ? "log-level-{$logLevel}" : '';
        ?>
            <div class="log-line" data-level="<?= $logLevel ?>">
                <span class="log-line-number"><?= $lineNumber ?></span>
                <span class="log-line-content <?= $lineClass ?>"><?= htmlspecialchars($line) ?></span>
            </div>
        <?php
            $lineNumber++;
        endforeach;
        ?>
    </div>
</div>

<script>
let autoRefreshInterval = null;

function changeLineCount(lines) {
    const url = new URL(window.location.href);
    url.searchParams.set('lines', lines);
    window.location.href = url.toString();
}

function filterLogs(level) {
    const logLines = document.querySelectorAll('.log-line');
    logLines.forEach(line => {
        if (level === 'all' || line.dataset.level === level) {
            line.style.display = 'flex';
        } else {
            line.style.display = 'none';
        }
    });
    updateLineCount();
}

function updateLineCount() {
    const visibleLines = document.querySelectorAll('.log-line:not([style*="display: none"])').length;
    document.getElementById('lineCountDisplay').textContent = `${visibleLines} lines`;
}

function toggleAutoRefresh() {
    const btn = document.getElementById('autoRefreshBtn');
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        btn.innerHTML = '<i class="fa-solid fa-play"></i> Auto-refresh';
    } else {
        autoRefreshInterval = setInterval(() => {
            location.reload();
        }, 5000);
        btn.innerHTML = '<i class="fa-solid fa-pause"></i> Stop Auto-refresh';
    }
}

function downloadLog() {
    const filename = '<?= addslashes($logFilename) ?>';
    const basePath = '<?= $basePath ?>';
    window.location.href = `${basePath}/admin-legacy/enterprise/monitoring/logs/download?file=${encodeURIComponent(filename)}`;
}

function clearLog() {
    if (!confirm('Are you sure you want to clear this log file? This action cannot be undone.')) {
        return;
    }

    const filename = '<?= addslashes($logFilename) ?>';
    const basePath = '<?= $basePath ?>';

    fetch(`${basePath}/admin-legacy/enterprise/monitoring/logs/clear`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: filename })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to clear log: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Clear error:', err);
        alert('Network error');
    });
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>
