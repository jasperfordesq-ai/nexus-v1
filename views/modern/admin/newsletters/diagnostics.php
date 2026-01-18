<?php
/**
 * Newsletter Diagnostics & Repair Tool - Gold Standard Admin UI
 * Holographic Glassmorphism Dark Theme
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();

// Admin page configuration
$adminPageTitle = 'Newsletter Diagnostics';
$adminPageSubtitle = 'Check and repair newsletter database issues';
$adminPageIcon = 'fa-solid fa-stethoscope';

// Extract diagnostics data
$d = $diagnostics ?? [];
$issues = $d['issues'] ?? [];
$tables = $d['tables'] ?? [];
$fixes = $d['fixes_available'] ?? [];
$stats = $d['newsletter_stats'] ?? [];

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<style>
    .diagnostics-wrapper {
        padding: 0 40px 60px;
        position: relative;
        z-index: 10;
    }

    .diagnostics-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    /* Back link */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.9rem;
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        color: #a5b4fc;
    }

    /* Flash messages */
    .flash-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.15) 100%);
        border: 1px solid rgba(16, 185, 129, 0.4);
        color: #6ee7b7;
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        backdrop-filter: blur(10px);
    }

    .flash-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.15) 100%);
        border: 1px solid rgba(239, 68, 68, 0.4);
        color: #fca5a5;
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        backdrop-filter: blur(10px);
    }

    /* Glass Card */
    .glass-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        backdrop-filter: blur(20px);
        margin-bottom: 24px;
        overflow: hidden;
    }

    .card-header {
        padding: 20px 30px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card-header.status-header {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
    }

    .card-header.stats-header {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.1) 100%);
    }

    .card-header.database-header {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(37, 99, 235, 0.1) 100%);
    }

    .card-header.repair-header {
        background: linear-gradient(135deg, rgba(234, 179, 8, 0.2) 0%, rgba(202, 138, 4, 0.15) 100%);
        border-bottom-color: rgba(234, 179, 8, 0.3);
    }

    .card-header.debug-header {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(124, 58, 237, 0.1) 100%);
    }

    .card-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .card-icon.status-icon {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.3) 0%, rgba(139, 92, 246, 0.2) 100%);
        color: #a5b4fc;
    }

    .card-icon.stats-icon {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.3) 0%, rgba(217, 119, 6, 0.2) 100%);
        color: #fcd34d;
    }

    .card-icon.database-icon {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0.2) 100%);
        color: #93c5fd;
    }

    .card-icon.repair-icon {
        background: linear-gradient(135deg, rgba(234, 179, 8, 0.3) 0%, rgba(202, 138, 4, 0.2) 100%);
        color: #fde047;
    }

    .card-icon.debug-icon {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.3) 0%, rgba(124, 58, 237, 0.2) 100%);
        color: #c4b5fd;
    }

    .card-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #ffffff;
    }

    .card-body {
        padding: 24px 30px;
    }

    /* Status indicators */
    .status-ok {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #6ee7b7;
    }

    .status-ok i {
        font-size: 1.5rem;
    }

    .status-error {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #fca5a5;
        margin-bottom: 16px;
    }

    .status-error i {
        font-size: 1.5rem;
    }

    .issues-list {
        margin: 0;
        padding-left: 24px;
        color: rgba(255, 255, 255, 0.6);
    }

    .issues-list li {
        margin-bottom: 8px;
    }

    /* Stats grid */
    .stats-grid {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .stat-box {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 20px 28px;
        border-radius: 12px;
        text-align: center;
        min-width: 110px;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .stat-value.sent { color: #6ee7b7; }
    .stat-value.draft { color: rgba(255, 255, 255, 0.5); }
    .stat-value.default { color: #fcd34d; }

    .stat-label {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
        text-transform: capitalize;
    }

    /* Database table */
    .db-table {
        width: 100%;
        border-collapse: collapse;
    }

    .db-table thead tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    }

    .db-table th {
        text-align: left;
        padding: 12px 8px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
    }

    .db-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .db-table td {
        padding: 12px 8px;
    }

    .table-name {
        font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
        color: rgba(255, 255, 255, 0.8);
    }

    .badge-ok {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.3) 0%, rgba(5, 150, 105, 0.2) 100%);
        border: 1px solid rgba(16, 185, 129, 0.4);
        color: #6ee7b7;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .badge-missing {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.3) 0%, rgba(220, 38, 38, 0.2) 100%);
        border: 1px solid rgba(239, 68, 68, 0.4);
        color: #fca5a5;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Repair tools */
    .repair-intro {
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 20px;
        font-size: 0.9rem;
    }

    .repair-grid {
        display: grid;
        gap: 16px;
    }

    .repair-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 20px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .repair-info strong {
        color: #ffffff;
        display: block;
        margin-bottom: 4px;
    }

    .repair-info p {
        margin: 0;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
    }

    .btn-fix {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .btn-fix:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    }

    .btn-create {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
    }

    /* Debug form */
    .debug-intro {
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 16px;
        font-size: 0.9rem;
    }

    .debug-form {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .debug-input {
        padding: 10px 14px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        color: #ffffff;
        width: 130px;
        font-size: 0.95rem;
    }

    .debug-input::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .debug-input:focus {
        outline: none;
        border-color: rgba(139, 92, 246, 0.5);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
    }

    .btn-debug {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .btn-debug:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4);
    }

    /* Members table */
    .members-section {
        margin-top: 24px;
    }

    .members-title {
        margin: 0 0 16px;
        color: #ffffff;
        font-size: 1rem;
    }

    .members-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .members-table thead tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    }

    .members-table th {
        text-align: left;
        padding: 10px 8px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.7);
    }

    .members-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .members-table td {
        padding: 10px 8px;
        color: rgba(255, 255, 255, 0.8);
    }

    .member-status-active {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.3) 0%, rgba(5, 150, 105, 0.2) 100%);
        border: 1px solid rgba(16, 185, 129, 0.4);
        color: #6ee7b7;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
    }

    .member-status-inactive {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.3) 0%, rgba(220, 38, 38, 0.2) 100%);
        border: 1px solid rgba(239, 68, 68, 0.4);
        color: #fca5a5;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
    }

    .approved-yes { color: #6ee7b7; }
    .approved-no { color: #fca5a5; }

    .members-note {
        margin-top: 16px;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
    }

    .members-note strong {
        color: rgba(255, 255, 255, 0.7);
    }

    .no-members {
        margin-top: 20px;
        color: #fca5a5;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .debug-error {
        margin-top: 20px;
        color: #fca5a5;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .empty-text {
        color: rgba(255, 255, 255, 0.5);
        margin: 0;
    }

    @media (max-width: 768px) {
        .diagnostics-wrapper {
            padding: 0 20px 40px;
        }

        .card-body {
            padding: 20px;
        }

        .repair-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .repair-item form {
            width: 100%;
        }

        .repair-item button {
            width: 100%;
        }

        .stats-grid {
            justify-content: center;
        }
    }
</style>

<div class="diagnostics-wrapper">
    <div class="diagnostics-container">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/admin/newsletters" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
        </a>

        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="flash-success">
                <i class="fa-solid fa-check-circle" style="font-size: 1.2rem;"></i>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="flash-error">
                <i class="fa-solid fa-exclamation-circle" style="font-size: 1.2rem;"></i>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <!-- System Status -->
        <div class="glass-card">
            <div class="card-header status-header">
                <div class="card-icon status-icon">
                    <i class="fa-solid fa-stethoscope"></i>
                </div>
                <h3 class="card-title">System Status</h3>
            </div>
            <div class="card-body">
                <?php if (empty($issues)): ?>
                    <div class="status-ok">
                        <i class="fa-solid fa-circle-check"></i>
                        <span style="font-weight: 600;">All systems operational - No issues detected</span>
                    </div>
                <?php else: ?>
                    <div class="status-error">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span style="font-weight: 600;"><?= count($issues) ?> issue(s) found</span>
                    </div>
                    <ul class="issues-list">
                        <?php foreach ($issues as $issue): ?>
                            <li><?= htmlspecialchars($issue) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Newsletter Stats Summary -->
        <div class="glass-card">
            <div class="card-header stats-header">
                <div class="card-icon stats-icon">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <h3 class="card-title">Newsletter Status Summary</h3>
            </div>
            <div class="card-body">
                <?php if (empty($stats)): ?>
                    <p class="empty-text">No newsletters found</p>
                <?php else: ?>
                    <div class="stats-grid">
                        <?php foreach ($stats as $status => $count): ?>
                            <div class="stat-box">
                                <div class="stat-value <?= $status === 'sent' ? 'sent' : ($status === 'draft' ? 'draft' : 'default') ?>">
                                    <?= $count ?>
                                </div>
                                <div class="stat-label"><?= htmlspecialchars($status) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Database Tables -->
        <div class="glass-card">
            <div class="card-header database-header">
                <div class="card-icon database-icon">
                    <i class="fa-solid fa-database"></i>
                </div>
                <h3 class="card-title">Database Tables</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($tables)): ?>
                    <table class="db-table">
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table => $info): ?>
                                <tr>
                                    <td class="table-name"><?= htmlspecialchars($table) ?></td>
                                    <td>
                                        <?php if ($info['exists']): ?>
                                            <span class="badge-ok">
                                                <i class="fa-solid fa-check"></i> OK
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-missing">
                                                <i class="fa-solid fa-xmark"></i> MISSING
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-text">No table information available</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Repair Tools -->
        <div class="glass-card">
            <div class="card-header repair-header">
                <div class="card-icon repair-icon">
                    <i class="fa-solid fa-wrench"></i>
                </div>
                <h3 class="card-title">Repair Tools</h3>
            </div>
            <div class="card-body">
                <p class="repair-intro">
                    Use these tools to fix common database issues. Each repair is safe and only affects your tenant's data.
                </p>

                <div class="repair-grid">
                    <!-- Fix Sent Status -->
                    <div class="repair-item">
                        <div class="repair-info">
                            <strong>Fix Newsletter Status</strong>
                            <p>Update newsletters with sent_at date to have 'sent' status</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="fix_sent_status">
                            <button type="submit" class="btn-fix">Run Fix</button>
                        </form>
                    </div>

                    <!-- Fix Total Sent -->
                    <div class="repair-item">
                        <div class="repair-info">
                            <strong>Fix Total Sent Counts</strong>
                            <p>Set total_sent from total_recipients for sent newsletters</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="fix_total_sent">
                            <button type="submit" class="btn-fix">Run Fix</button>
                        </form>
                    </div>

                    <!-- Init Tracking Columns -->
                    <div class="repair-item">
                        <div class="repair-info">
                            <strong>Initialize Tracking Columns</strong>
                            <p>Set NULL tracking columns (opens, clicks) to 0</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="init_tracking_columns">
                            <button type="submit" class="btn-fix">Run Fix</button>
                        </form>
                    </div>

                    <!-- Create Tracking Tables -->
                    <div class="repair-item">
                        <div class="repair-info">
                            <strong>Create Tracking Tables</strong>
                            <p>Create newsletter_opens and newsletter_clicks tables if missing</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="create_tracking_tables">
                            <button type="submit" class="btn-create">Create Tables</button>
                        </form>
                    </div>

                    <!-- Add Tracking Columns -->
                    <div class="repair-item">
                        <div class="repair-info">
                            <strong>Add Tracking Columns</strong>
                            <p>Add total_opens, unique_opens, total_clicks, unique_clicks to newsletters table</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="add_tracking_columns">
                            <button type="submit" class="btn-create">Add Columns</button>
                        </form>
                    </div>

                    <!-- Fix Stuck Sending -->
                    <div class="repair-item" style="border-color: rgba(245, 158, 11, 0.3); background: rgba(245, 158, 11, 0.05);">
                        <div class="repair-info">
                            <strong style="color: #fcd34d;">Fix Stuck "Sending" Newsletters</strong>
                            <p>Mark newsletters stuck in "Sending" as completed. Use this if emails were sent but status didn't update.</p>
                            <?php if (!empty($d['stuck_sending'])): ?>
                                <p style="color: #fcd34d; margin-top: 8px;">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <?= count($d['stuck_sending']) ?> newsletter(s) stuck:
                                    <?php foreach ($d['stuck_sending'] as $stuck): ?>
                                        <br><small>"<?= htmlspecialchars(mb_substr($stuck['subject'], 0, 40)) ?>..." - <?= $stuck['pending_count'] ?> pending, <?= $stuck['sent_count'] ?> sent</small>
                                    <?php endforeach; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="fix_stuck_sending">
                            <button type="submit" class="btn-fix" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">Fix Stuck</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Group Members -->
        <div class="glass-card">
            <div class="card-header debug-header">
                <div class="card-icon debug-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <h3 class="card-title">Debug Group Members</h3>
            </div>
            <div class="card-body">
                <p class="debug-intro">
                    Check why a group filter might be returning 0 recipients. Enter a group ID to see its members.
                </p>
                <form method="GET" action="<?= $basePath ?>/admin/newsletters/diagnostics" class="debug-form">
                    <input type="number" name="debug_group" placeholder="Group ID" value="<?= htmlspecialchars($_GET['debug_group'] ?? '') ?>" class="debug-input">
                    <button type="submit" class="btn-debug">Check Members</button>
                </form>

                <?php if (!empty($d['group_members'])): ?>
                    <div class="members-section">
                        <h4 class="members-title">Members in Group #<?= htmlspecialchars($d['debug_group_id']) ?></h4>
                        <div style="overflow-x: auto;">
                            <table class="members-table">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Member Status</th>
                                        <th>Approved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($d['group_members'] as $member): ?>
                                        <tr>
                                            <td><?= $member['user_id'] ?></td>
                                            <td><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></td>
                                            <td><?= htmlspecialchars($member['email'] ?: '(no email)') ?></td>
                                            <td>
                                                <span class="<?= $member['member_status'] === 'active' ? 'member-status-active' : 'member-status-inactive' ?>">
                                                    <?= htmlspecialchars($member['member_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($member['is_approved'] === null || $member['is_approved'] == 1): ?>
                                                    <span class="approved-yes"><i class="fa-solid fa-check"></i> Yes</span>
                                                <?php else: ?>
                                                    <span class="approved-no"><i class="fa-solid fa-xmark"></i> No</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="members-note">
                            <strong>Note:</strong> Members need: active status, email address, and is_approved = 1 (or NULL) to receive newsletters.
                        </p>
                    </div>
                <?php elseif (isset($d['debug_group_id'])): ?>
                    <div class="no-members">
                        <i class="fa-solid fa-exclamation-circle"></i> No members found in group #<?= htmlspecialchars($d['debug_group_id']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($d['group_error'])): ?>
                    <div class="debug-error">
                        <i class="fa-solid fa-exclamation-circle"></i> Error: <?= htmlspecialchars($d['group_error']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
