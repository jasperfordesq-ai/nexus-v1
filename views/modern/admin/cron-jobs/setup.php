<?php
/**
 * Admin Cron Job Setup Guide - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use App\Core\TenantContext;
use App\Core\Csrf;

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
            <a href="<?= $basePath ?>/admin-legacy/cron-jobs" class="back-link">
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

            <div class="info-box warning">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <div>
                    <strong>Important (2026-04-02):</strong> The HTTP-based cron endpoints (<code>/cron/run-all</code>, etc.) have been <strong>removed</strong> to prevent duplicate email sends. The only supported cron trigger is the <strong>Laravel scheduler</strong> via <code>artisan schedule:run</code>. See the Docker/Linux tab for the correct setup. <strong>Do NOT add curl-based cron entries — they will cause duplicate newsletters.</strong>
                </div>
            </div>

            <h4 class="section-heading">Recommended Setup (Docker)</h4>
            <p class="guide-text">Add this single entry to the host machine's root crontab:</p>

            <div class="code-block">
                <div class="code-label">Host crontab (the only cron entry needed)</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre># Laravel scheduler — runs every minute, CronJobRunner handles internal scheduling
* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1</pre>
            </div>

            <p class="guide-text">The Laravel scheduler calls <code>CronJobRunner::runAll()</code> which intelligently determines which tasks to run based on the current time (newsletters, digests, cleanup, etc.).</p>

            <h4 class="section-heading">All Scheduled Tasks</h4>
            <div class="cron-table-wrapper">
                <table class="cron-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Frequency</th>
                            <th>Expression</th>
                            <th>Handler</th>
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

            <h4 class="section-heading">Manual Trigger</h4>
            <p class="guide-text">Individual jobs can be triggered manually from the <a href="<?= $basePath ?>/admin-legacy/cron-jobs" style="color: #a5b4fc;">Cron Jobs dashboard</a> using the "Run Now" button. This is safe and does not conflict with the scheduler.</p>
        </div>
    </div>
</div>

<!-- Docker Section (Primary) -->
<div class="setup-section" id="plesk">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #0ea5e9, #0284c7);">
                <i class="fa-solid fa-server"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Docker (Production)</h3>
                <p class="admin-card-subtitle">Recommended setup for Docker deployments</p>
            </div>
        </div>
        <div class="admin-card-body">
            <h4 class="section-heading"><span class="step-badge">1</span> Add to Host Crontab</h4>
            <p class="guide-text">On the <strong>host machine</strong> (not inside the container), add this single crontab entry:</p>

            <div class="code-block">
                <div class="code-label">sudo crontab -e</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre># Laravel scheduler — runs every minute, CronJobRunner handles all tasks internally
* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1</pre>
            </div>

            <h4 class="section-heading"><span class="step-badge">2</span> Verify</h4>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>sudo crontab -l</pre>
            </div>

            <div class="info-box success">
                <i class="fa-solid fa-check-circle"></i>
                <div>
                    <strong>That's it!</strong> The Laravel scheduler calls <code>CronJobRunner::runAll()</code> every minute, which internally determines which of the 40+ tasks to run based on the current time. No other cron entries needed.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- cPanel / Shared Hosting Section -->
<div class="setup-section" id="cpanel">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f97316, #ea580c);">
                <i class="fa-solid fa-cog"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">cPanel / Shared Hosting</h3>
                <p class="admin-card-subtitle">Setup for shared hosting with shell access</p>
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
                <div class="code-label">Laravel Scheduler (every minute)</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1</pre>
            </div>

            <div class="info-box info">
                <i class="fa-solid fa-info-circle"></i>
                <div>
                    <strong>Note:</strong> Replace <code>/path/to/your/project</code> with your actual project root. On shared hosting limited to every 15 minutes, the scheduler will still work — it just checks less frequently.
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

            <h4 class="section-heading"><span class="step-badge">2</span> Add Laravel Scheduler Entry</h4>
            <div class="code-block">
                <div class="code-label">Single cron entry (handles all 40+ tasks)</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre># Laravel scheduler — runs every minute
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1</pre>
            </div>

            <h4 class="section-heading"><span class="step-badge">3</span> Verify Crontab</h4>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>crontab -l</pre>
            </div>

            <div class="info-box success">
                <i class="fa-solid fa-check-circle"></i>
                <div>
                    <strong>Best Practice:</strong> Use <code>>> /dev/null 2>&1</code> to suppress output emails. The scheduler handles timing internally — you only need this one entry.
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
                <h3 class="admin-card-title">Azure VM (Docker)</h3>
                <p class="admin-card-subtitle">Current production setup</p>
            </div>
        </div>
        <div class="admin-card-body">
            <h4 class="section-heading"><span class="step-badge">1</span> SSH into the Azure VM</h4>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>ssh azureuser@your-vm-ip</pre>
            </div>

            <h4 class="section-heading"><span class="step-badge">2</span> Add to Root Crontab</h4>
            <div class="code-block">
                <div class="code-label">sudo crontab -e</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre># Laravel scheduler — runs every minute, CronJobRunner handles internal scheduling
* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1</pre>
            </div>

            <div class="info-box warning">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <div>
                    <strong>Do NOT use Azure WebJobs, Functions, or Cloud Scheduler to hit HTTP endpoints.</strong> The <code>/cron/*</code> HTTP endpoints were removed to prevent duplicate email sends. Use <code>artisan schedule:run</code> only.
                </div>
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
                <p class="admin-card-subtitle">GCE VM with Docker</p>
            </div>
        </div>
        <div class="admin-card-body">
            <h4 class="section-heading"><span class="step-badge">1</span> SSH into the GCE VM</h4>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre>gcloud compute ssh your-instance-name</pre>
            </div>

            <h4 class="section-heading"><span class="step-badge">2</span> Add to Root Crontab</h4>
            <div class="code-block">
                <div class="code-label">sudo crontab -e</div>
                <button class="copy-btn" onclick="copyCode(this)"><i class="fa-solid fa-copy"></i> Copy</button>
                <pre># Laravel scheduler — runs every minute
* * * * * docker exec nexus-php-app php /var/www/html/artisan schedule:run >> /var/log/nexus-scheduler.log 2>&1</pre>
            </div>

            <div class="info-box warning">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <div>
                    <strong>Do NOT use GCP Cloud Scheduler to hit HTTP endpoints.</strong> The <code>/cron/*</code> HTTP endpoints were removed. Use <code>artisan schedule:run</code> via the VM crontab only.
                </div>
            </div>
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
