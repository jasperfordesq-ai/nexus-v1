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
    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 40px;">

        <!-- Overview Stats -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
            <div class="nexus-card" style="padding: 25px; text-align: center; border-left: 4px solid #0ea5e9;">
                <div style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; margin-bottom: 5px;">Total Tenants</div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #0ea5e9;"><?= count($tenants) ?></div>
            </div>
            <div class="nexus-card" style="padding: 25px; text-align: center; border-left: 4px solid #a855f7;">
                <div style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; margin-bottom: 5px;">Active Users</div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #a855f7;">
                    <?= $totalAllUsers ?? 'Active' ?>
                </div>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/users" class="nexus-btn nexus-btn-sm nexus-btn-generic" style="width:100%; border:1px solid #e5e7eb; color:#4b5563; background:white;">
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
            <div class="nexus-card" style="padding: 25px; border-left: 4px solid #f59e0b;">
                <div style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; margin-bottom: 5px; color: #b45309;">Queue Health</div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; line-height: 1;">
                    <?= $qPending ?> <span style="font-size: 1rem; color: #6b7280; font-weight: 500;">pending</span>
                </div>
                <div style="font-size: 0.8rem; margin-top: 8px; color: #dc2626; font-weight: 600;"><?= $qFailed ?> failed</div>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    <button onclick="window.open('/cron/process-queue?key=<?= $cronKey ?>', '_blank')" class="nexus-btn nexus-btn-secondary" style="font-size: 0.85rem; padding: 6px 12px; width: 100%;">
                        ⚡ Run Queue Worker Now
                    </button>
                    <div style="margin-top: 10px; font-size: 0.8rem; color: #6b7280; text-align: center;">
                        Opens in new tab to force process pending items.
                    </div>
                </div>
            </div>
        </div>

        <!-- Cron Configuration Guide -->
        <div class="nexus-card" style="grid-column: span 3;">
            <div class="nexus-card-header" style="border-bottom: 1px solid #e5e7eb; padding: 20px 25px;">
                <h3 style="margin: 0; font-size: 1.1rem;">Server Configuration & Cron Jobs</h3>
            </div>
            <div class="nexus-card-body" style="padding: 25px;">
                <p style="margin: 0 0 15px 0; color: #4b5563;">To ensure notifications and digests run automatically, add these entries to your server's Crontab (<code>crontab -e</code>):</p>

                <div style="background: #111827; color: #10b981; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 0.85rem; overflow-x: auto; margin-bottom: 20px;">
                    <div style="margin-bottom: 10px;">
                        <span style="color: #6b7280;"># 1. Process Instant Emails (Run every minute)</span><br>
                        * * * * * curl -s "<?= \Nexus\Core\Env::get('APP_URL') ?>/cron/process-queue?key=<?= $cronKey ?>" >/dev/null 2>&1
                    </div>
                    <div>
                        <span style="color: #6b7280;"># 2. Process Daily Digests (Run daily at 5 PM)</span><br>
                        0 17 * * * curl -s "<?= \Nexus\Core\Env::get('APP_URL') ?>/cron/daily-digest?key=<?= $cronKey ?>" >/dev/null 2>&1
                    </div>
                </div>

                <div style="display: flex; gap: 15px;">
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/sys_deploy_v2.php" target="_blank" class="nexus-btn nexus-btn-secondary" style="text-decoration: none; border: 1px solid #e5e7eb; color: #374151;">
                        📂 Run Deployment Script (V2)
                    </a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/cron/weekly-digest?key=nexus_secret_cron_key_123" target="_blank" class="nexus-btn nexus-btn-sm nexus-btn-secondary" style="background:#fff; border-color:#d1d5db; text-decoration: none; color:#475569;">Run Manual Trigger</a>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/help" target="_blank" class="nexus-btn nexus-btn-secondary" style="text-decoration: none; border: 1px solid #e5e7eb; color: #374151;">
                        📘 View Documentation
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Managed Communities -->
    <div class="nexus-card">
        <header class="nexus-card-header" style="border-bottom: 1px solid rgba(0,0,0,0.05); padding: 20px 25px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:var(--primary); color:white; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">🌐</div>
                <div>
                    <h3 style="margin:0; font-size:1.1rem;">Managed Communities</h3>
                    <div style="font-size:0.85rem; color:var(--nexus-text-muted);">Platform-wide tenant overview</div>
                </div>
            </div>
        </header>

        <div class="nexus-card-body" style="padding: 30px;">
            <!-- Inner Box for Table -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                <table style="width:100%; border-collapse: collapse;">
                    <thead style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Community</th>
                            <th style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">URL Slug</th>
                            <th style="padding:15px 20px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Active Modules</th>
                            <th style="padding:15px 20px; text-align:right; font-size:0.8rem; text-transform:uppercase; color:#64748b; font-weight:700;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $t): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0; background: #fff; transition: background 0.1s;">
                                <td style="padding:15px 20px; font-weight:600; color:#1e293b;">
                                    <?= htmlspecialchars($t['name']) ?>
                                </td>
                                <td style="padding:15px 20px;">
                                    <span style="background:#f1f5f9; padding: 4px 10px; border-radius: 6px; font-family: monospace; font-size:0.85rem; color:#475569; border:1px solid #e2e8f0;">
                                        /<?= htmlspecialchars($t['slug']) ?>
                                    </span>
                                </td>
                                <td style="padding:15px 20px; font-size:0.85rem; color:#64748b;">
                                    <?php
                                    // Safe JSON decode
                                    $f = json_decode($t['features'] ?? '{}', true);
                                    $active = [];
                                    if (!empty($f['listings'])) $active[] = '<span style="color:#2563eb">Listings</span>';
                                    if (!empty($f['groups'])) $active[] = '<span style="color:#7c3aed">Hubs</span>';
                                    if (!empty($f['volunteering'])) $active[] = '<span style="color:#059669">Vols</span>';
                                    if (!empty($f['events'])) $active[] = '<span style="color:#db2777">Events</span>';

                                    if (empty($active)) {
                                        echo '<span style="opacity:0.5; font-style:italic;">None</span>';
                                    } else {
                                        echo implode(' • ', array_slice($active, 0, 4));
                                        if (count($active) > 4) echo ' + ' . (count($active) - 4);
                                    }
                                    ?>
                                </td>
                                    <a href="/super-admin/tenant/edit?id=<?= $t['id'] ?>" class="nexus-btn nexus-btn-sm nexus-btn-generic"
                                        style="background:white; border:1px solid #e2e8f0; color:#475569; padding:6px 12px; font-size:0.85rem; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                                        Configure
                                    </a>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Deploy New Instance -->
    <div class="nexus-card">
        <header class="nexus-card-header" style="border-bottom: 1px solid rgba(0,0,0,0.05); padding: 20px 25px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:linear-gradient(135deg, #4f46e5, #9333ea); color:white; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">🚀</div>
                <div>
                    <h3 style="margin:0; font-size:1.1rem;">Deploy New Timebank</h3>
                    <div style="font-size:0.85rem; color:var(--nexus-text-muted);">Launch a new community instance on the platform</div>
                </div>
            </div>
        </header>

        <div class="nexus-card-body" style="padding: 30px;">
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/super-admin/create-tenant" method="POST">

                <!-- Inner Box: Instance Details -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 25px;">
                    <h4 style="margin:0 0 20px 0; color:#475569; font-size:0.9rem; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">Instance Details</h4>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:0.9rem; color:#1e293b;">Community Name</label>
                            <input type="text" name="name" class="nexus-input" placeholder="e.g. Cork City Exchange" required>
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:0.9rem; color:#1e293b;">URL Slug (Unique)</label>
                            <div style="display:flex; align-items:center;">
                                <span style="background:#fff; padding:12px 15px; border:1px solid #e2e8f0; border-right:0; border-radius:10px 0 0 10px; color:#6b7280; font-family: monospace;">platform.url/</span>
                                <input type="text" name="slug" class="nexus-input" placeholder="cork-city" required style="border-radius: 0 10px 10px 0;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inner Box: Primary Admin -->
                <div style="background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                    <h4 style="margin:0 0 20px 0; color:#0f766e; font-size:0.9rem; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #ccfbf1; padding-bottom:10px;">Primary Administrator</h4>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="display:block; margin-bottom:8px; font-size:0.85rem; color:#115e59;">Full Name</label>
                            <input type="text" name="admin_name" class="nexus-input" placeholder="Admin Name" required>
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:8px; font-size:0.85rem; color:#115e59;">Email Address</label>
                            <input type="email" name="admin_email" class="nexus-input" placeholder="admin@email.com" required>
                        </div>
                        <div>
                            <label style="display:block; margin-bottom:8px; font-size:0.85rem; color:#115e59;">Password</label>
                            <input type="password" name="admin_password" class="nexus-input" placeholder="Create Password" required>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" class="nexus-btn nexus-btn-primary" style="padding: 12px 30px; font-size:1rem; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2); background: #10b981; border-color: #059669;">
                        🚀 Launch New Instance
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>
</div>

<style>
    .super-admin-wrapper {
        position: relative;
        z-index: 20;
        padding: 0 40px 60px;
    }

    /* Desktop spacing */
    @media (min-width: 601px) {
        .super-admin-wrapper {
            padding-top: 140px;
        }
    }

    /* Mobile responsiveness */
    @media (max-width: 600px) {
        .super-admin-wrapper {
            padding: 120px 15px 100px 15px;
        }

        .super-admin-wrapper [style*="grid-template-columns"] {
            grid-template-columns: 1fr !important;
        }

        .super-admin-wrapper .nexus-card {
            border-radius: 12px;
        }
    }

    /* ========================================
       DARK MODE FOR MASTER DASHBOARD
       ======================================== */

    [data-theme="dark"] .nexus-card {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] .nexus-card-header {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] .nexus-card-header h3 {
        color: #f1f5f9;
    }

    [data-theme="dark"] .nexus-card-header div[style*="color:var(--nexus-text-muted)"] {
        color: #94a3b8 !important;
    }

    /* Stat cards labels */
    [data-theme="dark"] div[style*="opacity: 0.7"] {
        color: #94a3b8;
    }

    /* Inner boxes */
    [data-theme="dark"] div[style*="background: #f8fafc"],
    [data-theme="dark"] div[style*="background:#f8fafc"] {
        background: rgba(15, 23, 42, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] div[style*="background: #f1f5f9"],
    [data-theme="dark"] thead[style*="background: #f1f5f9"] {
        background: rgba(30, 41, 59, 0.6) !important;
    }

    /* Table */
    [data-theme="dark"] table tr[style*="border-bottom: 1px solid #e2e8f0"] {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] table thead[style*="border-bottom: 1px solid #e2e8f0"] {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme="dark"] table tr[style*="background: #fff"] {
        background: rgba(30, 41, 59, 0.4) !important;
    }

    [data-theme="dark"] table th[style*="color:#64748b"] {
        color: #94a3b8 !important;
    }

    [data-theme="dark"] table td[style*="color:#1e293b"] {
        color: #f1f5f9 !important;
    }

    [data-theme="dark"] table td span[style*="background:#f1f5f9"] {
        background: rgba(51, 65, 85, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #e2e8f0 !important;
    }

    /* Buttons */
    [data-theme="dark"] .nexus-btn-secondary {
        background: rgba(51, 65, 85, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #e2e8f0 !important;
    }

    [data-theme="dark"] .nexus-btn-generic[style*="background:white"],
    [data-theme="dark"] a[style*="background:white"] {
        background: rgba(51, 65, 85, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #e2e8f0 !important;
    }

    /* Card body text */
    [data-theme="dark"] .nexus-card-body p[style*="color: #4b5563"] {
        color: #94a3b8 !important;
    }

    /* Muted text */
    [data-theme="dark"] div[style*="color: #6b7280"],
    [data-theme="dark"] span[style*="color: #6b7280"] {
        color: #94a3b8 !important;
    }

    /* Border top dividers */
    [data-theme="dark"] div[style*="border-top: 1px solid #e5e7eb"] {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    /* Primary admin box */
    [data-theme="dark"] div[style*="background: #f0fdfa"] {
        background: rgba(20, 184, 166, 0.1) !important;
        border-color: rgba(20, 184, 166, 0.3) !important;
    }

    [data-theme="dark"] div[style*="background: #f0fdfa"] h4[style*="color:#0f766e"] {
        color: #5eead4 !important;
        border-color: rgba(20, 184, 166, 0.3) !important;
    }

    [data-theme="dark"] div[style*="background: #f0fdfa"] label[style*="color:#115e59"] {
        color: #99f6e4 !important;
    }

    /* Form inputs */
    [data-theme="dark"] .nexus-input {
        background: rgba(15, 23, 42, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #f1f5f9 !important;
    }

    [data-theme="dark"] span[style*="background:#fff"][style*="border:1px solid #e2e8f0"] {
        background: rgba(30, 41, 59, 0.6) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #94a3b8 !important;
    }
</style>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>