<?php
/**
 * Admin Cron Logs - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Cron Logs';
$adminPageSubtitle = 'System';
$adminPageIcon = 'fa-list-check';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$logs = $logs ?? [];
$page = $page ?? 1;
$perPage = $perPage ?? 25;
$total = $total ?? 0;
$totalPages = $totalPages ?? 1;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-list-check"></i>
            Cron Execution Logs
        </h1>
        <p class="admin-page-subtitle">View and manage execution history</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/cron-jobs" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Manager
        </a>
        <form action="<?= $basePath ?>/admin-legacy/cron-jobs/clear-logs" method="POST" class="admin-clear-form" onsubmit="return confirm('Are you sure you want to clear old logs?');">
            <?= Csrf::input() ?>
            <select name="days" class="admin-select">
                <option value="7">Older than 7 days</option>
                <option value="30" selected>Older than 30 days</option>
                <option value="90">Older than 90 days</option>
            </select>
            <button type="submit" class="admin-btn admin-btn-danger">
                <i class="fa-solid fa-trash"></i>
                Clear Logs
            </button>
        </form>
    </div>
</div>

<!-- Flash Messages -->
<?php if ($flashSuccess): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($flashSuccess) ?></span>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<!-- Logs Table Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-slate">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Execution History</h3>
            <p class="admin-card-subtitle"><?= number_format($total) ?> total log entries</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (!empty($logs)): ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Status</th>
                        <th class="hide-mobile">Duration</th>
                        <th class="hide-tablet">Output</th>
                        <th class="hide-mobile">Executed By</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <span class="admin-job-id"><?= htmlspecialchars($log['job_id']) ?></span>
                        </td>
                        <td>
                            <span class="admin-log-status admin-log-status-<?= $log['status'] ?>">
                                <?php if ($log['status'] === 'success'): ?>
                                    <i class="fa-solid fa-check"></i>
                                <?php elseif ($log['status'] === 'error'): ?>
                                    <i class="fa-solid fa-times"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-spinner fa-spin"></i>
                                <?php endif; ?>
                                <?= ucfirst($log['status']) ?>
                            </span>
                        </td>
                        <td class="hide-mobile">
                            <span class="admin-duration"><?= number_format($log['duration_seconds'], 2) ?>s</span>
                        </td>
                        <td class="hide-tablet admin-output-cell">
                            <?php if (!empty($log['output'])): ?>
                                <div class="admin-output-preview" onclick="showLogOutput(<?= htmlspecialchars(json_encode($log['output'])) ?>, '<?= htmlspecialchars($log['job_id']) ?>')">
                                    <?= htmlspecialchars(substr($log['output'], 0, 100)) ?><?= strlen($log['output']) > 100 ? '...' : '' ?>
                                </div>
                            <?php else: ?>
                                <span class="admin-no-output">No output</span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-mobile">
                            <span class="admin-executed-by">
                                <?= $log['executed_by_name'] ?? ($log['executed_by'] ? 'User #' . $log['executed_by'] : 'System') ?>
                            </span>
                        </td>
                        <td>
                            <div class="admin-timestamp">
                                <div class="admin-timestamp-date"><?= date('M j, Y', strtotime($log['executed_at'])) ?></div>
                                <div class="admin-timestamp-time"><?= date('g:i:s A', strtotime($log['executed_at'])) ?></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="admin-pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="admin-page-btn">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="admin-page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            ?>

            <?php if ($startPage > 1): ?>
                <a href="?page=1" class="admin-page-btn">1</a>
                <?php if ($startPage > 2): ?>
                    <span class="admin-page-btn disabled">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="?page=<?= $i ?>" class="admin-page-btn <?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span class="admin-page-btn disabled">...</span>
                <?php endif; ?>
                <a href="?page=<?= $totalPages ?>" class="admin-page-btn"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="admin-page-btn">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="admin-page-btn disabled">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            <?php endif; ?>

            <span class="admin-pagination-info">
                Showing <?= (($page - 1) * $perPage) + 1 ?>-<?= min($page * $perPage, $total) ?> of <?= number_format($total) ?>
            </span>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <h3 class="admin-empty-title">No Execution Logs Yet</h3>
            <p class="admin-empty-text">Cron job executions will be logged here once they run.</p>
            <a href="<?= $basePath ?>/admin-legacy/cron-jobs" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-play"></i>
                Run a Cron Job
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Output Modal -->
<div class="admin-modal" id="logModal">
    <div class="admin-modal-backdrop" onclick="closeLogModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" id="modalTitle">Log Output</h3>
            <button class="admin-modal-close" onclick="closeLogModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="admin-modal-body">
            <div class="admin-output-full" id="modalOutput"></div>
        </div>
    </div>
</div>

<style>
/* Clear Form */
.admin-clear-form {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-select {
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 0.85rem;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 1rem;
}

.admin-select:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
}

.admin-select option {
    background: #1e293b;
    color: #fff;
}

/* Alerts */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.admin-alert-success {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.admin-alert-error {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Card Header Icon */
.admin-card-header-icon-slate {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
}

/* Job ID */
.admin-job-id {
    font-weight: 700;
    color: #818cf8;
    font-size: 0.9rem;
}

/* Log Status */
.admin-log-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
}

.admin-log-status-success {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}

.admin-log-status-error {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.admin-log-status-running {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}

/* Duration */
.admin-duration {
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

/* Output Preview */
.admin-output-cell {
    max-width: 300px;
}

.admin-output-preview {
    background: rgba(0, 0, 0, 0.3);
    padding: 8px 12px;
    border-radius: 8px;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    max-height: 50px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: pre-wrap;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-output-preview:hover {
    background: rgba(99, 102, 241, 0.15);
    color: rgba(255, 255, 255, 0.8);
}

.admin-no-output {
    color: rgba(255, 255, 255, 0.4);
    font-style: italic;
    font-size: 0.85rem;
}

/* Executed By */
.admin-executed-by {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

/* Timestamp */
.admin-timestamp {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.admin-timestamp-date {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
}

.admin-timestamp-time {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
}

/* Table Styles */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Pagination */
.admin-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.1);
    flex-wrap: wrap;
}

.admin-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 0.75rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.admin-page-btn:hover:not(.disabled):not(.active) {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
    color: #fff;
}

.admin-page-btn.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
    color: white;
}

.admin-page-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.admin-pagination-info {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin-left: 1rem;
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
    background: rgba(100, 116, 139, 0.1);
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

/* Modal */
.admin-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.admin-modal.active {
    display: flex;
}

.admin-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
}

.admin-modal-content {
    position: relative;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    max-width: 800px;
    width: 100%;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.admin-modal-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.admin-modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.admin-modal-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.admin-modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.admin-modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.admin-output-full {
    background: rgba(0, 0, 0, 0.4);
    padding: 1.25rem;
    border-radius: 10px;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
    color: #e2e8f0;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid rgba(99, 102, 241, 0.1);
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
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

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

/* Responsive */
@media (max-width: 1024px) {
    .hide-tablet {
        display: none;
    }
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }

    .admin-page-header-actions {
        flex-direction: column;
        gap: 0.75rem;
        width: 100%;
    }

    .admin-clear-form {
        width: 100%;
        flex-wrap: wrap;
    }

    .admin-select {
        flex: 1;
    }

    .admin-pagination {
        padding: 1rem;
    }

    .admin-pagination-info {
        width: 100%;
        text-align: center;
        margin: 0.5rem 0 0 0;
    }
}
</style>

<script>
function showLogOutput(output, jobId) {
    document.getElementById('modalTitle').textContent = 'Output: ' + jobId;
    document.getElementById('modalOutput').textContent = output;
    document.getElementById('logModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLogModal() {
    document.getElementById('logModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLogModal();
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
