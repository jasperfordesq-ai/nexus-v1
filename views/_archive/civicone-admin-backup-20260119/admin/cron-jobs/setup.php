<?php
/**
 * Admin Cron Job Setup Guide - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Setup Guide';
$adminPageSubtitle = 'Cron Jobs';
$adminPageIcon = 'fa-book';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$cronJobs = $cronJobs ?? [];
$cronKey = $cronKey ?? '';
$appUrl = $appUrl ?? $basePath;
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/cron-jobs" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Cron Job Setup Guide
        </h1>
        <p class="admin-page-subtitle">Complete instructions for configuring scheduled tasks</p>
    </div>
</div>

<!-- Navigation Tabs -->
<div class="setup-tabs">
    <button class="setup-tab active" data-section="overview">
        <i class="fa-solid fa-info-circle"></i> Overview
    </button>
    <button class="setup-tab" data-section="plesk">
        <i class="fa-solid fa-server"></i> Plesk
    </button>
    <button class="setup-tab" data-section="cpanel">
        <i class="fa-solid fa-cog"></i> cPanel
    </button>
    <button class="setup-tab" data-section="linux">
        <i class="fa-brands fa-linux"></i> Linux
    </button>
    <button class="setup-tab" data-section="azure">
        <i class="fa-brands fa-microsoft"></i> Azure
    </button>
    <button class="setup-tab" data-section="google">
        <i class="fa-brands fa-google"></i> GCP
    </button>
</div>

<!-- Overview Section -->
<div class="setup-section active" id="overview">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                <i class="fa-solid fa-info-circle"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Overview</h3>
                <p class="admin-card-subtitle">Essential background tasks for your platform</p>
            </div>
        </div>
        <div class="admin-card-body">
            <p class="guide-text">Cron jobs are essential background tasks that keep your platform running smoothly. They handle automated emails, cleanup tasks, and various scheduled operations.</p>

            <div class="info-box info">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Pro Tip:</strong> If your hosting provider only allows a limited number of cron jobs, use the <strong>Master Cron Runner</strong> (<code>/cron/run-all</code>) which intelligently runs all tasks based on the current time.
                </div>
            </div>

            <h4 class="section-heading">Security Configuration</h4>
            <p class="guide-text">All cron endpoints require authentication via a secret key. Set the <code>CRON_KEY</code> environment variable in your <code>.env</code> file:</p>

            <div class="code-block">
                <div class="code-label">Environment Variable</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>CRON_KEY=<?= htmlspecialchars($cronKey ?: 'your-secure-random-key-here') ?></pre>
            </div>

            <p class="guide-text">Then use this key when calling cron endpoints:</p>

            <div class="code-block">
                <div class="code-label">Usage Examples</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre># Via URL parameter
<?= htmlspecialchars($appUrl) ?>/cron/run-all?key=YOUR_CRON_KEY

# Via HTTP header
curl -H "X-Cron-Key: YOUR_CRON_KEY" <?= htmlspecialchars($appUrl) ?>/cron/run-all</pre>
            </div>

            <h4 class="section-heading">Recommended Cron Schedule</h4>
            <div class="cron-table-wrapper">
                <table class="cron-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Frequency</th>
                            <th>Expression</th>
                            <th>Endpoint</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cronJobs as $job): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($job['name']) ?></strong></td>
                            <td><?= htmlspecialchars($job['frequency']) ?></td>
                            <td><code><?= htmlspecialchars($job['cron_expression']) ?></code></td>
                            <td><code><?= htmlspecialchars($job['endpoint']) ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h4 class="section-heading">Minimum Recommended Setup</h4>
            <p class="guide-text">If you can only configure a few cron jobs, these are the most important:</p>
            <ol class="guide-list">
                <li><strong>Master Cron Runner</strong> - <code>/cron/run-all</code> every minute (handles everything)</li>
                <li><strong>OR</strong> these individual jobs:
                    <ul>
                        <li>Instant Queue - <code>/cron/process-queue</code> every 2 minutes</li>
                        <li>Newsletter Queue - <code>/cron/process-newsletter-queue</code> every 3 minutes</li>
                        <li>Daily Cleanup - <code>/cron/cleanup</code> daily at midnight</li>
                    </ul>
                </li>
            </ol>
        </div>
    </div>
</div>

<!-- Plesk Section -->
<div class="setup-section" id="plesk">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #0ea5e9, #0284c7);">
                <i class="fa-solid fa-server"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Plesk (Azure/Google Cloud VMs)</h3>
                <p class="admin-card-subtitle">Setup for Plesk control panel</p>
            </div>
        </div>
        <div class="admin-card-body">
            <h4 class="section-heading"><span class="step-badge">1</span> Access Scheduled Tasks</h4>
            <ol class="guide-list">
                <li>Log in to your Plesk control panel</li>
                <li>Navigate to <strong>Tools & Settings</strong> > <strong>Scheduled Tasks</strong></li>
                <li>Or go to <strong>Websites & Domains</strong> > select your domain > <strong>Scheduled Tasks</strong></li>
            </ol>

            <h4 class="section-heading"><span class="step-badge">2</span> Add a New Task</h4>
            <ol class="guide-list">
                <li>Click <strong>Add Task</strong></li>
                <li>Select <strong>Run a command</strong> for wget/curl</li>
            </ol>

            <h4 class="section-heading"><span class="step-badge">3</span> Configure Using wget</h4>
            <div class="code-block">
                <div class="code-label">Master Cron (runs every minute)</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>/usr/bin/wget -q -O /dev/null "<?= htmlspecialchars($appUrl) ?>/cron/run-all?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>"</pre>
            </div>

            <h4 class="section-heading"><span class="step-badge">4</span> Set Schedule</h4>
            <ul class="guide-list">
                <li><strong>Every minute:</strong> <code>* * * * *</code></li>
                <li><strong>Every 5 minutes:</strong> <code>*/5 * * * *</code></li>
                <li><strong>Daily at 8 AM:</strong> <code>0 8 * * *</code></li>
            </ul>

            <div class="info-box warning">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <div>
                    <strong>Important:</strong> Make sure your PHP version and timeout settings allow scripts to run for at least 2-5 minutes for longer tasks like newsletter sending.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- cPanel Section -->
<div class="setup-section" id="cpanel">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f97316, #ea580c);">
                <i class="fa-solid fa-cog"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">cPanel</h3>
                <p class="admin-card-subtitle">Setup for cPanel hosting</p>
            </div>
        </div>
        <div class="admin-card-body">
            <h4 class="section-heading"><span class="step-badge">1</span> Access Cron Jobs</h4>
            <ol class="guide-list">
                <li>Log in to cPanel</li>
                <li>Under <strong>Advanced</strong> section, click <strong>Cron Jobs</strong></li>
            </ol>

            <h4 class="section-heading"><span class="step-badge">2</span> Add Cron Job</h4>
            <div class="code-block">
                <div class="code-label">Using wget</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>/usr/bin/wget -q -O /dev/null "<?= htmlspecialchars($appUrl) ?>/cron/run-all?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>" >/dev/null 2>&1</pre>
            </div>

            <div class="code-block">
                <div class="code-label">Using curl</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>/usr/bin/curl -s "<?= htmlspecialchars($appUrl) ?>/cron/run-all?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>" >/dev/null 2>&1</pre>
            </div>

            <div class="info-box info">
                <i class="fa-solid fa-info-circle"></i>
                <div>
                    <strong>Note:</strong> On shared hosting, you may be limited to running cron jobs every 15 minutes. Use the Master Cron Runner which handles all timing internally.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Linux Section -->
<div class="setup-section" id="linux">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #eab308, #ca8a04);">
                <i class="fa-brands fa-linux"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Linux Server (crontab)</h3>
                <p class="admin-card-subtitle">VPS, dedicated servers, or any Linux system</p>
            </div>
        </div>
        <div class="admin-card-body">
            <h4 class="section-heading"><span class="step-badge">1</span> Edit Crontab</h4>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>crontab -e</pre>
            </div>

            <h4 class="section-heading"><span class="step-badge">2</span> Add Cron Entries</h4>
            <div class="code-block">
                <div class="code-label">Option A: Master Cron (Recommended)</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre># Run master cron every minute
* * * * * /usr/bin/curl -s "<?= htmlspecialchars($appUrl) ?>/cron/run-all?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>" >/dev/null 2>&1</pre>
            </div>

            <div class="code-block">
                <div class="code-label">Option B: Individual Jobs</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre># Instant queue - every 2 minutes
*/2 * * * * /usr/bin/curl -s "<?= htmlspecialchars($appUrl) ?>/cron/process-queue?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>" >/dev/null 2>&1

# Newsletter queue - every 3 minutes
*/3 * * * * /usr/bin/curl -s "<?= htmlspecialchars($appUrl) ?>/cron/process-newsletter-queue?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>" >/dev/null 2>&1

# Daily digest - 8 AM
0 8 * * * /usr/bin/curl -s "<?= htmlspecialchars($appUrl) ?>/cron/daily-digest?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>" >/dev/null 2>&1

# Cleanup - midnight
0 0 * * * /usr/bin/curl -s "<?= htmlspecialchars($appUrl) ?>/cron/cleanup?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>" >/dev/null 2>&1</pre>
            </div>

            <h4 class="section-heading"><span class="step-badge">3</span> Verify Crontab</h4>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>crontab -l</pre>
            </div>

            <div class="info-box success">
                <i class="fa-solid fa-check-circle"></i>
                <div>
                    <strong>Best Practice:</strong> Use <code>>/dev/null 2>&1</code> at the end of each command to suppress output emails.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Azure Section -->
<div class="setup-section" id="azure">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #0ea5e9, #0369a1);">
                <i class="fa-brands fa-microsoft"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Azure WebJobs / Azure Functions</h3>
                <p class="admin-card-subtitle">Cloud-native scheduling on Azure</p>
            </div>
        </div>
        <div class="admin-card-body">
            <h4 class="section-heading">Option 1: Azure WebJobs</h4>
            <ol class="guide-list">
                <li>Go to your App Service in Azure Portal</li>
                <li>Navigate to <strong>WebJobs</strong> under Settings</li>
                <li>Click <strong>+ Add</strong></li>
            </ol>

            <div class="code-block">
                <div class="code-label">run.ps1</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>Invoke-WebRequest -Uri "<?= htmlspecialchars($appUrl) ?>/cron/run-all?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>" -Method GET</pre>
            </div>

            <div class="code-block">
                <div class="code-label">settings.job (schedule every minute)</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>{
    "schedule": "0 * * * * *"
}</pre>
            </div>

            <h4 class="section-heading">Option 2: Azure Functions (Timer Trigger)</h4>
            <div class="code-block">
                <div class="code-label">function.json</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>{
    "bindings": [
        {
            "name": "myTimer",
            "type": "timerTrigger",
            "direction": "in",
            "schedule": "0 * * * * *"
        }
    ]
}</pre>
            </div>
        </div>
    </div>
</div>

<!-- Google Cloud Section -->
<div class="setup-section" id="google">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
                <i class="fa-brands fa-google"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Google Cloud Platform</h3>
                <p class="admin-card-subtitle">Cloud Scheduler setup</p>
            </div>
        </div>
        <div class="admin-card-body">
            <h4 class="section-heading"><span class="step-badge">1</span> Enable Cloud Scheduler API</h4>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>gcloud services enable cloudscheduler.googleapis.com</pre>
            </div>

            <h4 class="section-heading"><span class="step-badge">2</span> Create a Scheduler Job</h4>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>gcloud scheduler jobs create http nexus-cron-master \
    --schedule="* * * * *" \
    --uri="<?= htmlspecialchars($appUrl) ?>/cron/run-all?key=<?= htmlspecialchars($cronKey ?: 'YOUR_CRON_KEY') ?>" \
    --http-method=GET \
    --time-zone="UTC" \
    --location="us-central1"</pre>
            </div>

            <h4 class="section-heading">Using Cloud Console</h4>
            <ol class="guide-list">
                <li>Go to <strong>Cloud Scheduler</strong> in GCP Console</li>
                <li>Click <strong>Create Job</strong></li>
                <li>Enter job name and select region</li>
                <li>Set frequency: <code>* * * * *</code></li>
                <li>Select <strong>HTTP</strong> as target type</li>
                <li>Enter your cron URL with key</li>
                <li>Click <strong>Create</strong></li>
            </ol>
        </div>
    </div>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Tabs */
.setup-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.setup-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    color: rgba(255, 255, 255, 0.6);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.setup-tab:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.setup-tab.active {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-color: transparent;
    color: #fff;
}

/* Sections */
.setup-section {
    display: none;
}

.setup-section.active {
    display: block;
}

/* Content Styles */
.guide-text {
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.7;
    margin-bottom: 1rem;
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    margin: 2rem 0 1rem;
}

.section-heading:first-of-type {
    margin-top: 0;
}

.step-badge {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: #fff;
}

.guide-list {
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.8;
    margin: 0.5rem 0 1rem;
    padding-left: 1.5rem;
}

.guide-list li {
    margin-bottom: 0.5rem;
}

.guide-list code {
    background: rgba(99, 102, 241, 0.2);
    padding: 0.15rem 0.4rem;
    border-radius: 0.25rem;
    font-size: 0.85rem;
    color: #a5b4fc;
}

/* Code Blocks */
.code-block {
    position: relative;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    padding: 1.25rem;
    margin: 1rem 0 1.5rem;
    overflow-x: auto;
}

.code-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255, 255, 255, 0.4);
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.code-block pre {
    margin: 0;
    color: #e2e8f0;
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    font-size: 0.85rem;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-all;
}

.copy-btn {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 0.35rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

.copy-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

.copy-btn.copied {
    background: #22c55e;
    color: #fff;
}

/* Info Boxes */
.info-box {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    margin: 1.5rem 0;
}

.info-box i {
    font-size: 1.1rem;
    flex-shrink: 0;
    margin-top: 0.15rem;
}

.info-box.info {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.25);
    color: #93c5fd;
}

.info-box.warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.25);
    color: #fcd34d;
}

.info-box.success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.25);
    color: #86efac;
}

/* Cron Table */
.cron-table-wrapper {
    overflow-x: auto;
    margin: 1rem 0;
}

.cron-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.cron-table th {
    background: rgba(255, 255, 255, 0.05);
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.8);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.cron-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.6);
}

.cron-table code {
    background: rgba(99, 102, 241, 0.2);
    padding: 0.15rem 0.4rem;
    border-radius: 0.25rem;
    font-size: 0.8rem;
    color: #a5b4fc;
}

/* Responsive */
@media (max-width: 768px) {
    .setup-tabs {
        flex-direction: column;
    }

    .setup-tab {
        justify-content: center;
    }

    .cron-table {
        font-size: 0.8rem;
    }

    .cron-table th,
    .cron-table td {
        padding: 0.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.setup-tab');
    const sections = document.querySelectorAll('.setup-section');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const sectionId = this.dataset.section;

            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            sections.forEach(s => {
                s.classList.remove('active');
                if (s.id === sectionId) {
                    s.classList.add('active');
                }
            });
        });
    });
});

function copyCode(btn) {
    const codeBlock = btn.parentElement;
    const pre = codeBlock.querySelector('pre');
    const text = pre.textContent;

    navigator.clipboard.writeText(text).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.remove('copied');
        }, 2000);
    });
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
