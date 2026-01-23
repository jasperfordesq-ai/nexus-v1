<?php
// Phoenix View: Master Dashboard (Super Admin)
// Path: views/modern/master/dashboard.php

$hTitle = 'Platform Master';
$hSubtitle = 'Orchestrate Communities & Manage Logic';
$hGradient = 'mt-hero-gradient-brand'; // Use brand gradient
$hType = 'Super Admin';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<div class="super-admin-wrapper">

    <!-- Centered Container -->
    <div class="master-centered-container">

        <!-- Overview Stats -->
        <div class="master-stats-grid">
            <div class="nexus-card master-stat-card master-stat-card-cyan">
                <div class="master-stat-label">Total Tenants</div>
                <div class="master-stat-value master-stat-value-cyan"><?= count($tenants) ?></div>
            </div>
            <div class="nexus-card master-stat-card master-stat-card-purple">
                <div class="master-stat-label">Active Users</div>
                <div class="master-stat-value master-stat-value-purple">
                    <?= $totalAllUsers ?? 'Active' ?>
                </div>
                <div class="master-card-footer">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/users" class="nexus-btn nexus-btn-sm nexus-btn-generic master-btn-directory">
                        Manage Directory
                    </a>
                </div>
            </div>

            <?php
            // Queue Stats (Direct Query for Speed)
            $qPending = \Nexus\Core\Database::query("SELECT COUNT(*) FROM notification_queue WHERE status='pending'")->fetchColumn();
            $qFailed  = \Nexus\Core\Database::query("SELECT COUNT(*) FROM notification_queue WHERE status='failed'")->fetchColumn();
            $cronKey  = \Nexus\Core\Env::get('CRON_KEY') ?? 'Not Set (Insecure)';
            ?>
            <div class="nexus-card master-stat-card master-stat-card-amber">
                <div class="master-queue-label">Queue Health</div>
                <div class="master-stat-value master-stat-value-amber">
                    <?= $qPending ?> <span class="master-stat-value-unit">pending</span>
                </div>
                <div class="master-queue-failed"><?= $qFailed ?> failed</div>
                <div class="master-card-footer">
                    <button onclick="window.open('/cron/process-queue?key=<?= $cronKey ?>', '_blank')" class="nexus-btn nexus-btn-secondary w-full">
                        ⚡ Run Queue Worker Now
                    </button>
                    <div class="master-card-footer-hint">
                        Opens in new tab to force process pending items.
                    </div>
                </div>
            </div>
        </div>

        <!-- Cron Configuration Guide -->
        <div class="nexus-card">
            <div class="nexus-card-header master-config-header">
                <h3 class="master-config-title">Server Configuration & Cron Jobs</h3>
            </div>
            <div class="nexus-card-body master-config-body">
                <p class="master-config-intro">To ensure notifications and digests run automatically, add these entries to your server's Crontab (<code>crontab -e</code>):</p>

                <div class="master-code-block">
                    <div class="master-code-entry">
                        <span class="master-code-comment"># 1. Process Instant Emails (Run every minute)</span><br>
                        * * * * * curl -s "<?= \Nexus\Core\Env::get('APP_URL') ?>/cron/process-queue?key=<?= $cronKey ?>" >/dev/null 2>&1
                    </div>
                    <div class="master-code-entry">
                        <span class="master-code-comment"># 2. Process Daily Digests (Run daily at 5 PM)</span><br>
                        0 17 * * * curl -s "<?= \Nexus\Core\Env::get('APP_URL') ?>/cron/daily-digest?key=<?= $cronKey ?>" >/dev/null 2>&1
                    </div>
                </div>

                <div class="master-config-actions">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/sys_deploy_v2.php" target="_blank" class="nexus-btn nexus-btn-secondary master-btn-secondary">
                        📂 Run Deployment Script (V2)
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/cron/weekly-digest?key=nexus_secret_cron_key_123" target="_blank" class="nexus-btn nexus-btn-sm nexus-btn-secondary master-btn-white">Run Manual Trigger</a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/help" target="_blank" class="nexus-btn nexus-btn-secondary master-btn-secondary">
                        📘 View Documentation
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Managed Communities -->
    <div class="nexus-card">
        <header class="nexus-card-header master-communities-header">
            <div class="master-header-inner">
                <div class="master-header-icon">🌐</div>
                <div>
                    <h3 class="master-header-title">Managed Communities</h3>
                    <div class="master-header-subtitle">Platform-wide tenant overview</div>
                </div>
            </div>
        </header>

        <div class="nexus-card-body master-communities-body">
            <!-- Inner Box for Table -->
            <div class="master-table-wrapper">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>Community</th>
                            <th>URL Slug</th>
                            <th>Active Modules</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $t): ?>
                            <tr>
                                <td class="master-table-name">
                                    <?= htmlspecialchars($t['name']) ?>
                                </td>
                                <td>
                                    <span class="master-table-slug">
                                        /<?= htmlspecialchars($t['slug']) ?>
                                    </span>
                                </td>
                                <td class="master-table-features">
                                    <?php
                                    // Safe JSON decode
                                    $f = json_decode($t['features'] ?? '{}', true);
                                    $active = [];
                                    if (!empty($f['listings'])) $active[] = '<span class="master-feature-listings">Listings</span>';
                                    if (!empty($f['groups'])) $active[] = '<span class="master-feature-groups">Hubs</span>';
                                    if (!empty($f['volunteering'])) $active[] = '<span class="master-feature-volunteering">Vols</span>';
                                    if (!empty($f['events'])) $active[] = '<span class="master-feature-events">Events</span>';

                                    if (empty($active)) {
                                        echo '<span class="master-feature-none">None</span>';
                                    } else {
                                        echo implode(' • ', array_slice($active, 0, 4));
                                        if (count($active) > 4) echo ' + ' . (count($active) - 4);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="/super-admin/tenant/edit?id=<?= $t['id'] ?>" class="nexus-btn nexus-btn-sm nexus-btn-generic master-btn-configure">
                                        Configure
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Deploy New Instance -->
    <div class="nexus-card">
        <header class="nexus-card-header master-communities-header">
            <div class="master-header-inner">
                <div class="master-header-icon master-header-icon-gradient">🚀</div>
                <div>
                    <h3 class="master-header-title">Deploy New Timebank</h3>
                    <div class="master-header-subtitle">Launch a new community instance on the platform</div>
                </div>
            </div>
        </header>

        <div class="nexus-card-body master-deploy-body">
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/create-tenant" method="POST">

                <!-- Inner Box: Instance Details -->
                <div class="master-form-section master-form-section-default">
                    <h4 class="master-form-section-title master-form-section-title-default">Instance Details</h4>

                    <div class="master-form-grid-2">
                        <div>
                            <label class="master-form-label">Community Name</label>
                            <input type="text" name="name" class="nexus-input" placeholder="e.g. Cork City Exchange" required>
                        </div>
                        <div>
                            <label class="master-form-label">URL Slug (Unique)</label>
                            <div class="master-url-input-wrapper">
                                <span class="master-url-prefix">platform.url/</span>
                                <input type="text" name="slug" class="nexus-input master-url-input" placeholder="cork-city" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inner Box: Primary Admin -->
                <div class="master-form-section master-form-section-teal">
                    <h4 class="master-form-section-title master-form-section-title-teal">Primary Administrator</h4>

                    <div class="master-form-grid-3">
                        <div>
                            <label class="master-form-label master-form-label-teal">Full Name</label>
                            <input type="text" name="admin_name" class="nexus-input" placeholder="Admin Name" required>
                        </div>
                        <div>
                            <label class="master-form-label master-form-label-teal">Email Address</label>
                            <input type="email" name="admin_email" class="nexus-input" placeholder="admin@email.com" required>
                        </div>
                        <div>
                            <label class="master-form-label master-form-label-teal">Password</label>
                            <input type="password" name="admin_password" class="nexus-input" placeholder="Create Password" required>
                        </div>
                    </div>
                </div>

                <div class="master-form-submit">
                    <button type="submit" class="nexus-btn nexus-btn-primary master-btn-launch">
                        🚀 Launch New Instance
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>
</div>


<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
