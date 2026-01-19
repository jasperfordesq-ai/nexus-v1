<?php
/**
 * Admin Cron Settings - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Cron Settings';
$adminPageSubtitle = 'System';
$adminPageIcon = 'fa-cog';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$globalSettings = $globalSettings ?? [];
$cronJobs = $cronJobs ?? [];
$jobSettings = $jobSettings ?? [];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-cog"></i>
            Cron Job Settings
        </h1>
        <p class="admin-page-subtitle">Configure notifications & monitoring</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/cron-jobs" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Jobs
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

<form action="<?= $basePath ?>/admin/cron-jobs/settings" method="POST">
    <?= Csrf::input() ?>

    <!-- Global Notification Settings -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-red">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Failure Notifications</h3>
                <p class="admin-card-subtitle">Get alerted when cron jobs fail</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="admin-toggle-row">
                <div class="admin-toggle-info">
                    <div class="admin-toggle-label">Enable Failure Notifications</div>
                    <div class="admin-toggle-desc">Send email alerts when cron jobs fail (applies to all jobs unless overridden)</div>
                </div>
                <label class="admin-toggle">
                    <input type="checkbox" name="failure_notification_enabled" value="1" <?= ($globalSettings['failure_notification_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <span class="admin-toggle-slider"></span>
                </label>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Notification Email Addresses</label>
                <input type="text" name="failure_notification_emails" class="admin-input"
                       placeholder="admin@example.com, devops@example.com"
                       value="<?= htmlspecialchars($globalSettings['failure_notification_emails'] ?? '') ?>">
                <div class="admin-hint">Comma-separated list of email addresses to receive failure notifications</div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Failure Threshold</label>
                <select name="failure_notification_threshold" class="admin-select" style="max-width: 220px;">
                    <option value="1" <?= ($globalSettings['failure_notification_threshold'] ?? '1') === '1' ? 'selected' : '' ?>>After 1 failure</option>
                    <option value="2" <?= ($globalSettings['failure_notification_threshold'] ?? '1') === '2' ? 'selected' : '' ?>>After 2 failures</option>
                    <option value="3" <?= ($globalSettings['failure_notification_threshold'] ?? '1') === '3' ? 'selected' : '' ?>>After 3 failures</option>
                    <option value="5" <?= ($globalSettings['failure_notification_threshold'] ?? '1') === '5' ? 'selected' : '' ?>>After 5 failures</option>
                </select>
                <div class="admin-hint">Number of consecutive failures within 1 hour before sending notification</div>
            </div>
        </div>
    </div>

    <!-- Log Retention Settings -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-purple">
                <i class="fa-solid fa-database"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Log Retention</h3>
                <p class="admin-card-subtitle">Configure how long to keep execution logs</p>
            </div>
        </div>
        <div class="admin-card-body">
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Keep Logs For</label>
                    <select name="log_retention_days" class="admin-select">
                        <option value="7" <?= ($globalSettings['log_retention_days'] ?? '30') === '7' ? 'selected' : '' ?>>7 days</option>
                        <option value="14" <?= ($globalSettings['log_retention_days'] ?? '30') === '14' ? 'selected' : '' ?>>14 days</option>
                        <option value="30" <?= ($globalSettings['log_retention_days'] ?? '30') === '30' ? 'selected' : '' ?>>30 days</option>
                        <option value="60" <?= ($globalSettings['log_retention_days'] ?? '30') === '60' ? 'selected' : '' ?>>60 days</option>
                        <option value="90" <?= ($globalSettings['log_retention_days'] ?? '30') === '90' ? 'selected' : '' ?>>90 days</option>
                    </select>
                    <div class="admin-hint">Older logs will be automatically deleted during cleanup</div>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Timezone</label>
                    <select name="timezone" class="admin-select">
                        <?php
                        $timezones = ['UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London', 'Europe/Dublin', 'Europe/Paris', 'Europe/Berlin', 'Asia/Tokyo', 'Asia/Shanghai', 'Australia/Sydney'];
                        $currentTz = $globalSettings['timezone'] ?? 'UTC';
                        foreach ($timezones as $tz): ?>
                            <option value="<?= $tz ?>" <?= $currentTz === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="admin-hint">Timezone used for displaying cron job times</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Per-Job Notification Settings -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-emerald">
                <i class="fa-solid fa-tasks"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Per-Job Notifications</h3>
                <p class="admin-card-subtitle">Override notification settings for specific jobs</p>
            </div>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php if (!empty($cronJobs)): ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th style="text-align: center;">Notify on Failure</th>
                            <th>Custom Email (optional)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cronJobs as $job):
                            $jobSetting = $jobSettings[$job['id']] ?? [];
                        ?>
                        <tr>
                            <td>
                                <span class="admin-job-name"><?= htmlspecialchars($job['name']) ?></span>
                                <span class="admin-job-category"><?= htmlspecialchars($job['category']) ?></span>
                            </td>
                            <td style="text-align: center;">
                                <label class="admin-toggle admin-toggle-sm">
                                    <input type="checkbox"
                                           name="job_settings[<?= $job['id'] ?>][notify_on_failure]"
                                           value="1"
                                           <?= !empty($jobSetting['notify_on_failure']) ? 'checked' : '' ?>>
                                    <span class="admin-toggle-slider"></span>
                                </label>
                            </td>
                            <td>
                                <input type="text"
                                       class="admin-input admin-input-sm"
                                       name="job_settings[<?= $job['id'] ?>][notify_emails]"
                                       placeholder="Use global emails"
                                       value="<?= htmlspecialchars($jobSetting['notify_emails'] ?? '') ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="admin-empty-state" style="padding: 3rem;">
                <p class="admin-empty-text">No cron jobs configured</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="admin-card-footer">
            <a href="<?= $basePath ?>/admin/cron-jobs" class="admin-btn admin-btn-secondary">
                Cancel
            </a>
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-save"></i>
                Save Settings
            </button>
        </div>
    </div>
</form>

<style>
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

/* Card Header Icons */
.admin-card-header-icon-red {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.admin-card-header-icon-purple {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
}

.admin-card-header-icon-emerald {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}

/* Toggle Row */
.admin-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 0;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    margin-bottom: 1.5rem;
}

.admin-toggle-info {
    flex: 1;
}

.admin-toggle-label {
    font-weight: 600;
    font-size: 0.95rem;
    color: #fff;
    margin-bottom: 4px;
}

.admin-toggle-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Toggle Switch */
.admin-toggle {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    flex-shrink: 0;
    margin-left: 1rem;
}

.admin-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.admin-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 26px;
    transition: 0.3s;
}

.admin-toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.admin-toggle input:checked + .admin-toggle-slider {
    background: linear-gradient(135deg, #10b981, #059669);
}

.admin-toggle input:checked + .admin-toggle-slider:before {
    transform: translateX(24px);
}

/* Small Toggle */
.admin-toggle-sm {
    width: 40px;
    height: 22px;
    margin-left: 0;
}

.admin-toggle-sm .admin-toggle-slider:before {
    height: 16px;
    width: 16px;
}

.admin-toggle-sm input:checked + .admin-toggle-slider:before {
    transform: translateX(18px);
}

/* Form Elements */
.admin-form-group {
    margin-bottom: 1.5rem;
}

.admin-form-group:last-child {
    margin-bottom: 0;
}

.admin-form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.admin-label {
    display: block;
    font-weight: 600;
    font-size: 0.9rem;
    color: #fff;
    margin-bottom: 0.5rem;
}

.admin-input,
.admin-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 10px;
    color: #fff;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.admin-input:focus,
.admin-select:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.5);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.admin-input::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.admin-select {
    appearance: none;
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
    padding-right: 2.5rem;
}

.admin-select option {
    background: #1e293b;
    color: #fff;
}

.admin-input-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
}

.admin-hint {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 0.5rem;
}

/* Job Table Styling */
.admin-job-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.admin-job-category {
    display: inline-block;
    font-size: 0.65rem;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    margin-left: 8px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.3px;
}

/* Card Footer */
.admin-card-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(99, 102, 241, 0.1);
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

/* Empty State */
.admin-empty-state {
    text-align: center;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-size: 0.9rem;
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

/* Responsive */
@media (max-width: 768px) {
    .admin-form-row {
        grid-template-columns: 1fr;
    }

    .admin-toggle-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .admin-toggle {
        margin-left: 0;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }

    .admin-card-footer {
        flex-direction: column;
    }

    .admin-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
