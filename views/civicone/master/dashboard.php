<?php
// Phoenix View: Master Dashboard (Super Admin)
// Path: views/modern/master/dashboard.php

$hTitle = 'Platform Master';
$hSubtitle = 'Orchestrate Communities & Manage Logic';
$hGradient = 'mt-hero-gradient-brand'; // Use brand gradient
$hType = 'Super Admin';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="super-admin-wrapper">

    <!-- Centered Container -->
    <div class="container-centered flex-col gap-40">

        <!-- Overview Stats -->
        <div class="grid-auto-fit-240">
            <div class="nexus-card p-25 text-center border-l-4-sky">
                <div class="stat-label">Total Tenants</div>
                <div class="stat-value text-sky-500"><?= count($tenants) ?></div>
            </div>
            <div class="nexus-card p-25 text-center border-l-4-purple">
                <div class="stat-label">Active Users</div>
                <div class="stat-value text-purple-500">
                    <?= $totalAllUsers ?? 'Active' ?>
                </div>
                <div class="mt-15 pt-15 border-t">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/users" class="nexus-btn nexus-btn-sm nexus-btn-generic w-full btn-subtle">
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
            <div class="nexus-card p-25 border-l-4-amber">
                <div class="stat-label text-amber-700">Queue Health</div>
                <div class="stat-value text-amber-500 line-height-1">
                    <?= $qPending ?> <span class="text-md text-gray-500 font-medium">pending</span>
                </div>
                <div class="stat-subtext text-red-600 font-semibold"><?= $qFailed ?> failed</div>
                <div class="mt-15 pt-15 border-t">
                    <button onclick="window.open('/cron/process-queue?key=<?= $cronKey ?>', '_blank')" class="nexus-btn nexus-btn-secondary text-sm-85 py-8 px-12 w-full">
                        ⚡ Run Queue Worker Now
                    </button>
                    <div class="mt-10 text-sm text-gray-500 text-center">
                        Opens in new tab to force process pending items.
                    </div>
                </div>
            </div>
        </div>

        <!-- Cron Configuration Guide -->
        <div class="nexus-card master-cron-card">
            <div class="nexus-card-header card-header-bordered">
                <h3 class="m-0 text-lg">Server Configuration & Cron Jobs</h3>
            </div>
            <div class="nexus-card-body card-body-padded">
                <p class="m-0 mb-15 text-gray-600">To ensure notifications and digests run automatically, add these entries to your server's Crontab (<code>crontab -e</code>):</p>

                <div class="code-block mb-20">
                    <div class="mb-10">
                        <span class="text-gray-500"># 1. Process Instant Emails (Run every minute)</span><br>
                        * * * * * curl -s "<?= \Nexus\Core\Env::get('APP_URL') ?>/cron/process-queue?key=<?= $cronKey ?>" >/dev/null 2>&1
                    </div>
                    <div>
                        <span class="text-gray-500"># 2. Process Daily Digests (Run daily at 5 PM)</span><br>
                        0 17 * * * curl -s "<?= \Nexus\Core\Env::get('APP_URL') ?>/cron/daily-digest?key=<?= $cronKey ?>" >/dev/null 2>&1
                    </div>
                </div>

                <div class="flex-wrap-gap-20">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/sys_deploy_v2.php" target="_blank" class="nexus-btn nexus-btn-secondary no-underline border text-gray-700">
                        📂 Run Deployment Script (V2)
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/cron/weekly-digest?key=nexus_secret_cron_key_123" target="_blank" class="nexus-btn nexus-btn-sm nexus-btn-secondary bg-white border-light no-underline text-slate-600">Run Manual Trigger</a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/help" target="_blank" class="nexus-btn nexus-btn-secondary no-underline border text-gray-700">
                        📘 View Documentation
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Managed Communities -->
    <div class="nexus-card">
        <header class="nexus-card-header card-header-bordered border-b-subtle">
            <div class="flex-center-gap-12">
                <div class="icon-40 rounded-xl bg-gradient-brand text-white text-xl">🌐</div>
                <div>
                    <h3 class="m-0 text-lg">Managed Communities</h3>
                    <div class="text-sm-85 text-muted">Platform-wide tenant overview</div>
                </div>
            </div>
        </header>

        <div class="nexus-card-body card-body-lg">
            <!-- Inner Box for Table -->
            <div class="card-box overflow-hidden">
                <table class="w-full table-collapse">
                    <thead class="bg-slate-100 border-b-light">
                        <tr>
                            <th class="table-header-cell">Community</th>
                            <th class="table-header-cell">URL Slug</th>
                            <th class="table-header-cell">Active Modules</th>
                            <th class="table-header-cell text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $t): ?>
                            <tr class="tenant-row border-b-light bg-white transition-bg">
                                <td class="table-cell font-semibold text-gray-900">
                                    <?= htmlspecialchars($t['name']) ?>
                                </td>
                                <td class="table-cell">
                                    <span class="tag-mono">
                                        /<?= htmlspecialchars($t['slug']) ?>
                                    </span>
                                </td>
                                <td class="table-cell text-sm-85 text-slate-500">
                                    <?php
                                    // Safe JSON decode
                                    $f = json_decode($t['features'] ?? '{}', true);
                                    $active = [];
                                    if (!empty($f['listings'])) $active[] = '<span class="text-blue-600">Listings</span>';
                                    if (!empty($f['groups'])) $active[] = '<span class="text-violet-600">Hubs</span>';
                                    if (!empty($f['volunteering'])) $active[] = '<span class="text-emerald-600">Vols</span>';
                                    if (!empty($f['events'])) $active[] = '<span class="text-pink-600">Events</span>';

                                    if (empty($active)) {
                                        echo '<span class="opacity-50 italic">None</span>';
                                    } else {
                                        echo implode(' • ', array_slice($active, 0, 4));
                                        if (count($active) > 4) echo ' + ' . (count($active) - 4);
                                    }
                                    ?>
                                </td>
                                <td class="table-cell text-right">
                                    <a href="/super-admin/tenant/edit?id=<?= $t['id'] ?>" class="nexus-btn nexus-btn-sm nexus-btn-generic btn-subtle">
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
        <header class="nexus-card-header card-header-bordered border-b-subtle">
            <div class="flex-center-gap-12">
                <div class="btn-icon-gradient">🚀</div>
                <div>
                    <h3 class="m-0 text-lg">Deploy New Timebank</h3>
                    <div class="text-sm-85 text-muted">Launch a new community instance on the platform</div>
                </div>
            </div>
        </header>

        <div class="nexus-card-body card-body-lg">
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/create-tenant" method="POST">

                <!-- Inner Box: Instance Details -->
                <div class="card-box mb-25">
                    <h4 class="stat-section-heading">Instance Details</h4>

                    <div class="grid-2col mb-15">
                        <div>
                            <label class="form-label">Community Name</label>
                            <input type="text" name="name" class="nexus-input" placeholder="e.g. Cork City Exchange" required>
                        </div>
                        <div>
                            <label class="form-label">URL Slug (Unique)</label>
                            <div class="flex-center">
                                <span class="slug-prefix bg-white p-12 px-15 border border-light rounded-l-xl text-gray-500 font-mono">platform.url/</span>
                                <input type="text" name="slug" class="nexus-input rounded-r-xl" placeholder="cork-city" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inner Box: Primary Admin -->
                <div class="card-box-teal mb-30">
                    <h4 class="stat-section-heading-teal">Primary Administrator</h4>

                    <div class="grid-3col">
                        <div>
                            <label class="form-label-teal">Full Name</label>
                            <input type="text" name="admin_name" class="nexus-input" placeholder="Admin Name" required>
                        </div>
                        <div>
                            <label class="form-label-teal">Email Address</label>
                            <input type="email" name="admin_email" class="nexus-input" placeholder="admin@email.com" required>
                        </div>
                        <div>
                            <label class="form-label-teal">Password</label>
                            <input type="password" name="admin_password" class="nexus-input" placeholder="Create Password" required>
                        </div>
                    </div>
                </div>

                <div class="flex-end">
                    <button type="submit" class="nexus-btn nexus-btn-primary launch-btn p-12 px-30 text-md">
                        🚀 Launch New Instance
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>
</div>

<!-- Master Dashboard CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-master-dashboard.min.css">
