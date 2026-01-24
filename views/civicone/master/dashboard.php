<?php
/**
 * Master Dashboard - Platform Admin
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = 'Platform Master';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

// Queue Stats
$qPending = \Nexus\Core\Database::query("SELECT COUNT(*) FROM notification_queue WHERE status='pending'")->fetchColumn();
$qFailed  = \Nexus\Core\Database::query("SELECT COUNT(*) FROM notification_queue WHERE status='failed'")->fetchColumn();
$cronKey  = \Nexus\Core\Env::get('CRON_KEY') ?? 'Not Set (Insecure)';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper">
        <!-- Header -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">
                    <i class="fa-solid fa-shield-halved govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    Platform Master
                </h1>
                <p class="govuk-body-l">Orchestrate communities and manage platform logic</p>
            </div>
            <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                <strong class="govuk-tag" style="background: #912b88;">
                    <i class="fa-solid fa-crown govuk-!-margin-right-1" aria-hidden="true"></i>
                    Super Admin
                </strong>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="govuk-grid-row govuk-!-margin-bottom-8">
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #1d70b8;">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #1d70b8;"><?= count($tenants) ?></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">Total Tenants</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #912b88;">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #912b88;"><?= $totalAllUsers ?? 'N/A' ?></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-2">Active Users</p>
                    <a href="<?= $basePath ?>/super-admin/users" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" style="width: 100%;">
                        <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                        Manage Directory
                    </a>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #f47738;">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #f47738;"><?= $qPending ?></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">Queue Pending</p>
                    <?php if ($qFailed > 0): ?>
                        <p class="govuk-body-s govuk-!-margin-top-1 govuk-!-margin-bottom-2" style="color: #d4351c;">
                            <strong><?= $qFailed ?> failed</strong>
                        </p>
                    <?php endif; ?>
                    <button type="button" onclick="window.open('/cron/process-queue?key=<?= $cronKey ?>', '_blank')" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0 govuk-!-margin-top-2" data-module="govuk-button" style="width: 100%;">
                        <i class="fa-solid fa-bolt govuk-!-margin-right-2" aria-hidden="true"></i>
                        Run Queue
                    </button>
                </div>
            </div>
        </div>

        <!-- Cron Configuration -->
        <div class="govuk-!-margin-bottom-8" style="background: #f3f2f1; border: 1px solid #b1b4b6;">
            <div class="govuk-!-padding-4" style="background: white; border-bottom: 1px solid #b1b4b6;">
                <h2 class="govuk-heading-m govuk-!-margin-bottom-0">
                    <i class="fa-solid fa-terminal govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    Server Configuration & Cron Jobs
                </h2>
            </div>
            <div class="govuk-!-padding-4">
                <p class="govuk-body govuk-!-margin-bottom-4">
                    To ensure notifications and digests run automatically, add these entries to your server's Crontab (<code>crontab -e</code>):
                </p>

                <div class="govuk-!-padding-3 govuk-!-margin-bottom-4" style="background: #0b0c0c; color: #00ff00; font-family: monospace; font-size: 13px; overflow-x: auto;">
                    <p class="govuk-!-margin-bottom-2" style="color: #6b7280;"># 1. Process Instant Emails (Run every minute)</p>
                    <p class="govuk-!-margin-bottom-4">* * * * * curl -s "<?= \Nexus\Core\Env::get('APP_URL') ?>/cron/process-queue?key=<?= $cronKey ?>" >/dev/null 2>&1</p>
                    <p class="govuk-!-margin-bottom-2" style="color: #6b7280;"># 2. Process Daily Digests (Run daily at 5 PM)</p>
                    <p class="govuk-!-margin-bottom-0">0 17 * * * curl -s "<?= \Nexus\Core\Env::get('APP_URL') ?>/cron/daily-digest?key=<?= $cronKey ?>" >/dev/null 2>&1</p>
                </div>

                <div class="govuk-button-group">
                    <a href="<?= $basePath ?>/sys_deploy_v2.php" target="_blank" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        <i class="fa-solid fa-rocket govuk-!-margin-right-2" aria-hidden="true"></i>
                        Deployment Script
                    </a>
                    <a href="<?= $basePath ?>/cron/weekly-digest?key=<?= $cronKey ?>" target="_blank" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        <i class="fa-solid fa-play govuk-!-margin-right-2" aria-hidden="true"></i>
                        Manual Trigger
                    </a>
                    <a href="<?= $basePath ?>/help" target="_blank" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                        <i class="fa-solid fa-book govuk-!-margin-right-2" aria-hidden="true"></i>
                        Documentation
                    </a>
                </div>
            </div>
        </div>

        <!-- Managed Communities -->
        <div class="govuk-!-margin-bottom-8" style="border: 1px solid #b1b4b6;">
            <div class="govuk-!-padding-4" style="background: #f3f2f1; border-bottom: 1px solid #b1b4b6;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #1d70b8, #00703c); display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fa-solid fa-globe" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Managed Communities</h2>
                        <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Platform-wide tenant overview</p>
                    </div>
                </div>
            </div>

            <table class="govuk-table govuk-!-margin-bottom-0">
                <thead class="govuk-table__head">
                    <tr class="govuk-table__row">
                        <th scope="col" class="govuk-table__header">Community</th>
                        <th scope="col" class="govuk-table__header">URL Slug</th>
                        <th scope="col" class="govuk-table__header">Active Modules</th>
                        <th scope="col" class="govuk-table__header govuk-table__header--numeric">Action</th>
                    </tr>
                </thead>
                <tbody class="govuk-table__body">
                    <?php foreach ($tenants as $t): ?>
                        <tr class="govuk-table__row">
                            <td class="govuk-table__cell">
                                <strong><?= htmlspecialchars($t['name']) ?></strong>
                            </td>
                            <td class="govuk-table__cell">
                                <code style="background: #f3f2f1; padding: 2px 6px; border-radius: 3px;">/<?= htmlspecialchars($t['slug']) ?></code>
                            </td>
                            <td class="govuk-table__cell">
                                <?php
                                $f = json_decode($t['features'] ?? '{}', true);
                                $active = [];
                                if (!empty($f['listings'])) $active[] = '<strong class="govuk-tag govuk-tag--blue" style="font-size: 12px;">Listings</strong>';
                                if (!empty($f['groups'])) $active[] = '<strong class="govuk-tag govuk-tag--purple" style="font-size: 12px;">Hubs</strong>';
                                if (!empty($f['volunteering'])) $active[] = '<strong class="govuk-tag govuk-tag--green" style="font-size: 12px;">Vols</strong>';
                                if (!empty($f['events'])) $active[] = '<strong class="govuk-tag govuk-tag--pink" style="font-size: 12px;">Events</strong>';

                                if (empty($active)) {
                                    echo '<span class="govuk-body-s" style="color: #505a5f; font-style: italic;">None</span>';
                                } else {
                                    echo implode(' ', array_slice($active, 0, 4));
                                    if (count($active) > 4) echo ' <span class="govuk-body-s">+' . (count($active) - 4) . '</span>';
                                }
                                ?>
                            </td>
                            <td class="govuk-table__cell govuk-table__cell--numeric">
                                <a href="/super-admin/tenant/edit?id=<?= $t['id'] ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" style="font-size: 14px; padding: 8px 12px;">
                                    <i class="fa-solid fa-cog govuk-!-margin-right-1" aria-hidden="true"></i>
                                    Configure
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Deploy New Instance -->
        <div style="border: 1px solid #b1b4b6;">
            <div class="govuk-!-padding-4" style="background: #f3f2f1; border-bottom: 1px solid #b1b4b6;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #f47738, #d4351c); display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="govuk-heading-m govuk-!-margin-bottom-0">Deploy New Timebank</h2>
                        <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Launch a new community instance on the platform</p>
                    </div>
                </div>
            </div>

            <div class="govuk-!-padding-4">
                <form action="<?= $basePath ?>/super-admin/create-tenant" method="POST">

                    <!-- Instance Details -->
                    <div class="govuk-!-margin-bottom-6 govuk-!-padding-4" style="background: #f3f2f1; border-left: 5px solid #1d70b8;">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-4">
                            <i class="fa-solid fa-building govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                            Instance Details
                        </h3>

                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-one-half">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="name">Community Name</label>
                                    <input type="text" name="name" id="name" class="govuk-input" placeholder="e.g. Cork City Exchange" required>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-half">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="slug">URL Slug (Unique)</label>
                                    <div style="display: flex;">
                                        <span class="govuk-!-padding-2" style="background: white; border: 2px solid #0b0c0c; border-right: none; color: #505a5f; font-family: monospace;">platform.url/</span>
                                        <input type="text" name="slug" id="slug" class="govuk-input" style="border-radius: 0;" placeholder="cork-city" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Primary Administrator -->
                    <div class="govuk-!-margin-bottom-6 govuk-!-padding-4" style="background: #f3f2f1; border-left: 5px solid #00703c;">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-4">
                            <i class="fa-solid fa-user-shield govuk-!-margin-right-2" style="color: #00703c;" aria-hidden="true"></i>
                            Primary Administrator
                        </h3>

                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-one-third">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="admin_name">Full Name</label>
                                    <input type="text" name="admin_name" id="admin_name" class="govuk-input" placeholder="Admin Name" required>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-third">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="admin_email">Email Address</label>
                                    <input type="email" name="admin_email" id="admin_email" class="govuk-input" placeholder="admin@email.com" required>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-third">
                                <div class="govuk-form-group">
                                    <label class="govuk-label" for="admin_password">Password</label>
                                    <input type="password" name="admin_password" id="admin_password" class="govuk-input" placeholder="Create Password" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="govuk-!-text-align-right">
                        <button type="submit" class="govuk-button" data-module="govuk-button">
                            <i class="fa-solid fa-rocket govuk-!-margin-right-2" aria-hidden="true"></i>
                            Launch New Instance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
