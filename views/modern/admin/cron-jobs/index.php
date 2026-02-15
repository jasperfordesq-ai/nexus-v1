<?php
/**
 * Admin Cron Jobs - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Cron Jobs';
$adminPageSubtitle = 'System';
$adminPageIcon = 'fa-clock-rotate-left';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

// Get flash messages
$cronResult = $_SESSION['cron_result'] ?? null;
unset($_SESSION['cron_result']);
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-clock-rotate-left"></i>
            Cron Job Manager
        </h1>
        <p class="admin-page-subtitle">Schedule & monitor background tasks</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/cron-jobs/settings" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-cog"></i>
            Settings
        </a>
        <a href="<?= $basePath ?>/admin-legacy/cron-jobs/setup" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-book"></i>
            Setup Guide
        </a>
        <a href="<?= $basePath ?>/admin-legacy/cron-jobs/logs" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-list-ul"></i>
            View Logs
        </a>
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

<!-- Cron Result Banner -->
<?php if ($cronResult): ?>
<div class="admin-result-banner admin-result-<?= $cronResult['status'] ?>">
    <div class="admin-result-header">
        <div class="admin-result-title">
            <?php if ($cronResult['status'] === 'success'): ?>
                <i class="fa-solid fa-check-circle"></i> Successfully ran: <?= htmlspecialchars($cronResult['job_name']) ?>
            <?php else: ?>
                <i class="fa-solid fa-exclamation-triangle"></i> Error running: <?= htmlspecialchars($cronResult['job_name']) ?>
            <?php endif; ?>
        </div>
        <div class="admin-result-duration">
            <i class="fa-solid fa-stopwatch"></i> <?= $cronResult['duration'] ?>s
        </div>
    </div>
    <?php if (!empty($cronResult['output'])): ?>
        <div class="admin-result-output"><?= htmlspecialchars($cronResult['output']) ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $overallStats['total_jobs'] ?></div>
            <div class="admin-stat-label">Total Jobs</div>
            <div class="admin-stat-sublabel"><?= $overallStats['enabled_jobs'] ?> enabled</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-emerald">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-play-circle"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $overallStats['total_runs_24h'] ?></div>
            <div class="admin-stat-label">Runs (24h)</div>
            <div class="admin-stat-sublabel">Last 24 hours</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-cyan">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $overallStats['success_rate_24h'] ?>%</div>
            <div class="admin-stat-label">Success Rate</div>
            <div class="admin-stat-sublabel">Last 24 hours</div>
        </div>
    </div>
    <a href="<?= $basePath ?>/admin-legacy/cron-jobs/logs<?= $overallStats['failures_24h'] > 0 ? '?status=error' : '' ?>" class="admin-stat-card admin-stat-red admin-stat-link">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-exclamation-triangle"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $overallStats['failures_24h'] ?></div>
            <div class="admin-stat-label">Failures (24h)</div>
            <div class="admin-stat-sublabel"><?= $overallStats['failures_24h'] > 0 ? 'Click to review' : 'All healthy' ?></div>
        </div>
    </a>
</div>

<!-- Info Box -->
<div class="admin-info-box">
    <div class="admin-info-icon">
        <i class="fa-solid fa-info-circle"></i>
    </div>
    <div class="admin-info-content">
        <h4>About Cron Jobs</h4>
        <p>
            Cron jobs are scheduled background tasks that keep your platform running smoothly.
            They handle email digests, newsletter sending, cleanup tasks, and more.
            Use the toggle to enable/disable jobs, or click <strong>Run Now</strong> to trigger manually.
            <?php if (!empty($cronKey)): ?>
                Your cron key: <code><?= htmlspecialchars(substr($cronKey, 0, 8)) ?>...</code>
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Category Tabs -->
<div class="admin-cron-tabs" id="cronTabs">
    <button class="admin-cron-tab active" data-category="all">
        <i class="fa-solid fa-layer-group"></i>
        All Jobs
    </button>
    <?php foreach ($categories as $catId => $cat): ?>
        <button class="admin-cron-tab" data-category="<?= $catId ?>">
            <i class="fa-solid <?= $cat['icon'] ?>"></i>
            <?= htmlspecialchars($cat['name']) ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Jobs by Category -->
<?php foreach ($jobsByCategory as $catId => $jobs): ?>
    <?php $cat = $categories[$catId] ?? ['name' => $catId, 'icon' => 'fa-cog', 'color' => '#6b7280', 'description' => '']; ?>
    <div class="admin-cron-category" data-category="<?= $catId ?>">
        <div class="admin-category-header">
            <div class="admin-category-icon" style="background: <?= $cat['color'] ?>">
                <i class="fa-solid <?= $cat['icon'] ?>"></i>
            </div>
            <div class="admin-category-info">
                <h3 class="admin-category-title"><?= htmlspecialchars($cat['name']) ?></h3>
                <p class="admin-category-desc"><?= htmlspecialchars($cat['description']) ?></p>
            </div>
        </div>

        <div class="admin-cron-jobs-grid">
            <?php foreach ($jobs as $job): ?>
                <?php
                $isEnabled = ($job['settings']['is_enabled'] ?? 1) == 1;
                $jobStats = $job['stats'] ?? null;
                $lastStatus = $jobStats['last_status'] ?? null;
                $lastRun = $jobStats['last_run'] ?? null;
                ?>
                <div class="admin-cron-job-card <?= !$isEnabled ? 'disabled' : '' ?>">
                    <div class="admin-job-header">
                        <div class="admin-job-title-row">
                            <div class="admin-job-title">
                                <?= htmlspecialchars($job['name']) ?>
                                <span class="admin-job-priority admin-priority-<?= $job['priority'] ?>"><?= $job['priority'] ?></span>
                            </div>
                            <form action="<?= $basePath ?>/admin-legacy/cron-jobs/toggle/<?= $job['id'] ?>" method="POST" class="toggle-form">
                                <?= Csrf::input() ?>
                                <input type="hidden" name="enabled" value="<?= $isEnabled ? '0' : '1' ?>">
                                <label class="admin-toggle" title="<?= $isEnabled ? 'Click to disable' : 'Click to enable' ?>">
                                    <input type="checkbox" <?= $isEnabled ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="admin-toggle-slider"></span>
                                </label>
                            </form>
                        </div>
                        <p class="admin-job-desc"><?= htmlspecialchars($job['description']) ?></p>
                    </div>
                    <div class="admin-job-body">
                        <!-- Last Run Indicator -->
                        <?php if ($lastRun): ?>
                            <div class="admin-last-run admin-last-run-<?= $lastStatus ?>">
                                <?php if ($lastStatus === 'success'): ?>
                                    <i class="fa-solid fa-check-circle"></i>
                                <?php elseif ($lastStatus === 'error'): ?>
                                    <i class="fa-solid fa-times-circle"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-clock"></i>
                                <?php endif; ?>
                                Last run: <?= date('M j, g:i A', strtotime($lastRun)) ?>
                                <?php if ($jobStats): ?>
                                    (<?= $jobStats['avg_duration'] ?>s avg)
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="admin-last-run admin-last-run-never">
                                <i class="fa-solid fa-clock"></i>
                                Never run
                            </div>
                        <?php endif; ?>

                        <!-- Stats Row -->
                        <?php if ($jobStats): ?>
                            <div class="admin-job-stats">
                                <span class="admin-job-stat admin-job-stat-success">
                                    <i class="fa-solid fa-check"></i> <?= $jobStats['success_count'] ?>
                                </span>
                                <span class="admin-job-stat admin-job-stat-<?= $jobStats['error_count'] > 0 ? 'error' : 'neutral' ?>">
                                    <i class="fa-solid fa-times"></i> <?= $jobStats['error_count'] ?>
                                </span>
                                <?php if ($jobStats['total_runs'] > 0): ?>
                                    <span class="admin-job-stat admin-job-stat-neutral">
                                        <?= $jobStats['success_rate'] ?>% rate
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="admin-job-meta">
                            <div class="admin-job-meta-item">
                                <span class="admin-job-meta-label">Frequency</span>
                                <span class="admin-job-meta-value"><?= htmlspecialchars($job['frequency']) ?></span>
                            </div>
                            <div class="admin-job-meta-item">
                                <span class="admin-job-meta-label">Duration</span>
                                <span class="admin-job-meta-value"><?= htmlspecialchars($job['estimated_duration']) ?></span>
                            </div>
                        </div>

                        <div class="admin-job-endpoint">
                            <code><?= htmlspecialchars($job['endpoint']) ?></code>
                        </div>

                        <form action="<?= $basePath ?>/admin-legacy/cron-jobs/run/<?= $job['id'] ?>" method="POST" class="admin-job-actions">
                            <?= Csrf::input() ?>
                            <button type="submit" class="admin-btn admin-btn-run" <?= !$isEnabled ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-play"></i>
                                Run Now
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<!-- Recent Logs -->
<?php if (!empty($logs)): ?>
<div class="admin-glass-card" style="margin-top: 2rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-slate">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Recent Executions</h3>
            <p class="admin-card-subtitle">Latest cron runs</p>
        </div>
        <a href="<?= $basePath ?>/admin-legacy/cron-jobs/logs" class="admin-btn admin-btn-secondary admin-btn-sm">
            View All <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th>Status</th>
                        <th class="hide-mobile">Duration</th>
                        <th class="hide-tablet">Executed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($log['job_id']) ?></strong></td>
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
                            <td class="hide-mobile"><?= number_format($log['duration_seconds'], 2) ?>s</td>
                            <td class="hide-tablet admin-date-cell"><?= date('M j, Y g:i A', strtotime($log['executed_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.admin-stat-card {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
}

.admin-stat-link {
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}

.admin-stat-link:hover {
    transform: translateY(-2px);
    border-color: rgba(99, 102, 241, 0.3);
}

.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.admin-stat-purple::before { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.admin-stat-emerald::before { background: linear-gradient(135deg, #10b981, #059669); }
.admin-stat-cyan::before { background: linear-gradient(135deg, #06b6d4, #0891b2); }
.admin-stat-red::before { background: linear-gradient(135deg, #ef4444, #dc2626); }

.admin-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-stat-purple .admin-stat-icon { background: rgba(99, 102, 241, 0.2); color: #818cf8; }
.admin-stat-emerald .admin-stat-icon { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.admin-stat-cyan .admin-stat-icon { background: rgba(6, 182, 212, 0.2); color: #22d3ee; }
.admin-stat-red .admin-stat-icon { background: rgba(239, 68, 68, 0.2); color: #f87171; }

.admin-stat-content { flex: 1; }
.admin-stat-value { font-size: 1.75rem; font-weight: 700; color: #fff; line-height: 1.2; }
.admin-stat-label { color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; margin-top: 2px; }
.admin-stat-sublabel { color: rgba(255, 255, 255, 0.4); font-size: 0.75rem; margin-top: 4px; }

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

/* Result Banner */
.admin-result-banner {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.admin-result-success { border-color: rgba(16, 185, 129, 0.3); }
.admin-result-error { border-color: rgba(239, 68, 68, 0.3); }

.admin-result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.admin-result-title {
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-result-success .admin-result-title { color: #34d399; }
.admin-result-error .admin-result-title { color: #f87171; }

.admin-result-duration {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-result-output {
    background: rgba(0, 0, 0, 0.3);
    padding: 1rem;
    border-radius: 8px;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.8rem;
    white-space: pre-wrap;
    max-height: 200px;
    overflow-y: auto;
    color: rgba(255, 255, 255, 0.8);
}

/* Info Box */
.admin-info-box {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
}

.admin-info-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(59, 130, 246, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #60a5fa;
    font-size: 1rem;
    flex-shrink: 0;
}

.admin-info-content h4 {
    margin: 0 0 0.5rem 0;
    color: #60a5fa;
    font-size: 0.95rem;
}

.admin-info-content p {
    margin: 0;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.6;
}

.admin-info-content code {
    background: rgba(0, 0, 0, 0.3);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8rem;
    color: #93c5fd;
}

/* Category Tabs */
.admin-cron-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    padding-bottom: 1rem;
}

.admin-cron-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.5);
}

.admin-cron-tab:hover {
    background: rgba(99, 102, 241, 0.1);
    color: rgba(255, 255, 255, 0.8);
}

.admin-cron-tab.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
}

/* Category Sections */
.admin-cron-category {
    margin-bottom: 2.5rem;
}

.admin-category-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.admin-category-icon {
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

.admin-category-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.admin-category-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Job Cards Grid */
.admin-cron-jobs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.25rem;
}

/* Job Card */
.admin-cron-job-card {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.2s;
    position: relative;
}

.admin-cron-job-card.disabled {
    opacity: 0.6;
}

.admin-cron-job-card.disabled::after {
    content: 'DISABLED';
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(239, 68, 68, 0.8);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    letter-spacing: 0.5px;
}

.admin-cron-job-card:hover {
    border-color: rgba(99, 102, 241, 0.3);
    transform: translateY(-2px);
}

.admin-job-header {
    padding: 1.25rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.admin-job-title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.admin-job-title {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.admin-job-priority {
    font-size: 0.65rem;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 700;
    text-transform: uppercase;
}

.admin-priority-critical { background: rgba(220, 38, 38, 0.2); color: #fca5a5; }
.admin-priority-high { background: rgba(217, 119, 6, 0.2); color: #fcd34d; }
.admin-priority-medium { background: rgba(37, 99, 235, 0.2); color: #93c5fd; }
.admin-priority-low { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

.admin-job-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
    line-height: 1.5;
}

.admin-job-body {
    padding: 1.25rem;
}

/* Toggle Switch */
.admin-toggle {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}

.admin-toggle input { opacity: 0; width: 0; height: 0; }

.admin-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 24px;
    transition: 0.3s;
}

.admin-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
}

.admin-toggle input:checked + .admin-toggle-slider {
    background: linear-gradient(135deg, #10b981, #059669);
}

.admin-toggle input:checked + .admin-toggle-slider:before {
    transform: translateX(20px);
}

/* Last Run */
.admin-last-run {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.admin-last-run-success { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.admin-last-run-error { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.admin-last-run-never { background: rgba(100, 116, 139, 0.15); color: #94a3b8; }

/* Job Stats */
.admin-job-stats {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.admin-job-stat {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 6px;
}

.admin-job-stat-success { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.admin-job-stat-error { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.admin-job-stat-neutral { background: rgba(100, 116, 139, 0.15); color: #94a3b8; }

/* Job Meta */
.admin-job-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.admin-job-meta-item {
    background: rgba(0, 0, 0, 0.2);
    padding: 0.625rem 0.75rem;
    border-radius: 8px;
}

.admin-job-meta-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: rgba(255, 255, 255, 0.4);
    margin-bottom: 2px;
}

.admin-job-meta-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
}

/* Job Endpoint */
.admin-job-endpoint {
    background: rgba(99, 102, 241, 0.1);
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.admin-job-endpoint code {
    font-size: 0.75rem;
    color: #a5b4fc;
    word-break: break-all;
}

/* Run Button */
.admin-btn-run {
    width: 100%;
    padding: 0.75rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.admin-btn-run:hover:not(:disabled) {
    transform: scale(1.02);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.admin-btn-run:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: rgba(100, 116, 139, 0.3);
}

/* Log Status */
.admin-log-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
}

.admin-log-status-success { background: rgba(16, 185, 129, 0.15); color: #34d399; }
.admin-log-status-error { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.admin-log-status-running { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }

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

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8rem;
}

/* Card Header Icon */
.admin-card-header-icon-slate {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
}

/* Table */
.admin-table-wrapper { overflow-x: auto; }

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
    color: #fff;
}

.admin-table tbody tr:hover { background: rgba(99, 102, 241, 0.05); }
.admin-table tbody tr:last-child td { border-bottom: none; }

.admin-date-cell {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .admin-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .admin-stats-grid {
        grid-template-columns: 1fr;
    }

    .admin-cron-jobs-grid {
        grid-template-columns: 1fr;
    }

    .admin-cron-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 0.75rem;
    }

    .admin-job-meta {
        grid-template-columns: 1fr;
    }

    .hide-mobile { display: none; }
    .hide-tablet { display: none; }

    .admin-page-header-actions {
        flex-wrap: wrap;
    }
}

@media (max-width: 1024px) {
    .hide-tablet { display: none; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.admin-cron-tab');
    const categories = document.querySelectorAll('.admin-cron-category');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const category = this.dataset.category;

            // Update active tab
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Show/hide categories
            categories.forEach(cat => {
                if (category === 'all' || cat.dataset.category === category) {
                    cat.style.display = 'block';
                } else {
                    cat.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
