<?php
/**
 * Newsletter Diagnostics & Repair Tool
 * Admin tool for fixing database issues without manual SQL
 */

$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Hero settings for modern layout
$hTitle = 'Newsletter Diagnostics';
$hSubtitle = 'Check and repair newsletter database issues';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'System Tools';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}

$d = $diagnostics ?? [];
$issues = $d['issues'] ?? [];
$tables = $d['tables'] ?? [];
$fixes = $d['fixes_available'] ?? [];
$stats = $d['newsletter_stats'] ?? [];
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 1000px; margin: 0 auto;">

        <!-- Navigation -->
        <div style="margin-bottom: 24px;">
            <a href="<?= $basePath ?>/admin-legacy/newsletters" style="color: #6b7280; text-decoration: none; font-size: 0.9rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
            </a>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-check-circle" style="font-size: 1.2rem;"></i>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-exclamation-circle" style="font-size: 1.2rem;"></i>
                <span style="font-weight: 500;"><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <!-- Status Summary -->
        <div class="nexus-card" style="margin-bottom: 24px; padding: 0; overflow: hidden; border-radius: 16px;">
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 20px 30px; border-bottom: 1px solid #e2e8f0;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-stethoscope" style="color: #6366f1;"></i>
                    System Status
                </h3>
            </div>
            <div style="padding: 24px 30px;">
                <?php if (empty($issues)): ?>
                    <div style="display: flex; align-items: center; gap: 12px; color: #059669;">
                        <i class="fa-solid fa-circle-check" style="font-size: 1.5rem;"></i>
                        <span style="font-weight: 600;">All systems operational - No issues detected</span>
                    </div>
                <?php else: ?>
                    <div style="display: flex; align-items: center; gap: 12px; color: #dc2626; margin-bottom: 16px;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem;"></i>
                        <span style="font-weight: 600;"><?= count($issues) ?> issue(s) found</span>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; color: #6b7280;">
                        <?php foreach ($issues as $issue): ?>
                            <li style="margin-bottom: 8px;"><?= htmlspecialchars($issue) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Newsletter Stats -->
        <div class="nexus-card" style="margin-bottom: 24px; padding: 0; overflow: hidden; border-radius: 16px;">
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 20px 30px; border-bottom: 1px solid #e2e8f0;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-chart-pie" style="color: #f59e0b;"></i>
                    Newsletter Status Summary
                </h3>
            </div>
            <div style="padding: 24px 30px;">
                <?php if (empty($stats)): ?>
                    <p style="color: #6b7280; margin: 0;">No newsletters found</p>
                <?php else: ?>
                    <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                        <?php foreach ($stats as $status => $count): ?>
                            <div style="background: #f8fafc; padding: 16px 24px; border-radius: 12px; text-align: center; min-width: 100px;">
                                <div style="font-size: 1.8rem; font-weight: 700; color: <?= $status === 'sent' ? '#059669' : ($status === 'draft' ? '#6b7280' : '#f59e0b') ?>;">
                                    <?= $count ?>
                                </div>
                                <div style="font-size: 0.85rem; color: #6b7280; text-transform: capitalize;"><?= htmlspecialchars($status) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Database Tables -->
        <div class="nexus-card" style="margin-bottom: 24px; padding: 0; overflow: hidden; border-radius: 16px;">
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 20px 30px; border-bottom: 1px solid #e2e8f0;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-database" style="color: #3b82f6;"></i>
                    Database Tables
                </h3>
            </div>
            <div style="padding: 24px 30px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e5e7eb;">
                            <th style="text-align: left; padding: 12px 8px; font-weight: 600; color: #374151;">Table</th>
                            <th style="text-align: left; padding: 12px 8px; font-weight: 600; color: #374151;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tables as $table => $info): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 12px 8px; font-family: monospace; color: #374151;"><?= htmlspecialchars($table) ?></td>
                                <td style="padding: 12px 8px;">
                                    <?php if ($info['exists']): ?>
                                        <span style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                            <i class="fa-solid fa-check"></i> OK
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                            <i class="fa-solid fa-xmark"></i> MISSING
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Repair Tools -->
        <div class="nexus-card" style="margin-bottom: 24px; padding: 0; overflow: hidden; border-radius: 16px;">
            <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 20px 30px; border-bottom: 1px solid #fcd34d;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #92400e; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-wrench"></i>
                    Repair Tools
                </h3>
            </div>
            <div style="padding: 24px 30px;">
                <p style="color: #6b7280; margin-bottom: 20px; font-size: 0.9rem;">
                    Use these tools to fix common database issues. Each repair is safe and only affects your tenant's data.
                </p>

                <div style="display: grid; gap: 16px;">
                    <!-- Fix Sent Status -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 12px; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <strong style="color: #374151;">Fix Newsletter Status</strong>
                            <p style="margin: 4px 0 0; color: #6b7280; font-size: 0.85rem;">Update newsletters with sent_at date to have 'sent' status</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin-legacy/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="fix_sent_status">
                            <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Run Fix
                            </button>
                        </form>
                    </div>

                    <!-- Fix Total Sent -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 12px; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <strong style="color: #374151;">Fix Total Sent Counts</strong>
                            <p style="margin: 4px 0 0; color: #6b7280; font-size: 0.85rem;">Set total_sent from total_recipients for sent newsletters</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin-legacy/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="fix_total_sent">
                            <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Run Fix
                            </button>
                        </form>
                    </div>

                    <!-- Init Tracking Columns -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 12px; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <strong style="color: #374151;">Initialize Tracking Columns</strong>
                            <p style="margin: 4px 0 0; color: #6b7280; font-size: 0.85rem;">Set NULL tracking columns (opens, clicks) to 0</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin-legacy/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="init_tracking_columns">
                            <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Run Fix
                            </button>
                        </form>
                    </div>

                    <!-- Create Tracking Tables -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 12px; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <strong style="color: #374151;">Create Tracking Tables</strong>
                            <p style="margin: 4px 0 0; color: #6b7280; font-size: 0.85rem;">Create newsletter_opens and newsletter_clicks tables if missing</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin-legacy/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="create_tracking_tables">
                            <button type="submit" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Create Tables
                            </button>
                        </form>
                    </div>

                    <!-- Add Tracking Columns -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 12px; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <strong style="color: #374151;">Add Tracking Columns</strong>
                            <p style="margin: 4px 0 0; color: #6b7280; font-size: 0.85rem;">Add total_opens, unique_opens, total_clicks, unique_clicks to newsletters table</p>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin-legacy/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="add_tracking_columns">
                            <button type="submit" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Add Columns
                            </button>
                        </form>
                    </div>

                    <!-- Fix Stuck Sending -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 12px; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <strong style="color: #92400e;">Fix Stuck "Sending" Newsletters</strong>
                            <p style="margin: 4px 0 0; color: #a16207; font-size: 0.85rem;">Mark newsletters stuck in "Sending" as completed. Use this if emails were sent but status didn't update.</p>
                            <?php if (!empty($d['stuck_sending'])): ?>
                                <p style="color: #b45309; margin-top: 8px; font-size: 0.85rem;">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <?= count($d['stuck_sending']) ?> newsletter(s) stuck:
                                    <?php foreach ($d['stuck_sending'] as $stuck): ?>
                                        <br><small>"<?= htmlspecialchars(mb_substr($stuck['subject'], 0, 40)) ?>..." - <?= $stuck['pending_count'] ?> pending, <?= $stuck['sent_count'] ?> sent</small>
                                    <?php endforeach; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="<?= $basePath ?>/admin-legacy/newsletters/repair" style="margin: 0;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="fix" value="fix_stuck_sending">
                            <button type="submit" style="background: #f59e0b; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Fix Stuck
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group Debug Tool -->
        <div class="nexus-card" style="margin-bottom: 24px; padding: 0; overflow: hidden; border-radius: 16px;">
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 20px 30px; border-bottom: 1px solid #e2e8f0;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-users" style="color: #8b5cf6;"></i>
                    Debug Group Members
                </h3>
            </div>
            <div style="padding: 24px 30px;">
                <p style="color: #6b7280; margin-bottom: 16px; font-size: 0.9rem;">
                    Check why a group filter might be returning 0 recipients. Enter a group ID to see its members.
                </p>
                <form method="GET" action="<?= $basePath ?>/admin-legacy/newsletters/diagnostics" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <input type="number" name="debug_group" placeholder="Group ID" value="<?= htmlspecialchars($_GET['debug_group'] ?? '') ?>" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; width: 120px;">
                    <button type="submit" style="background: #8b5cf6; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                        Check Members
                    </button>
                </form>

                <?php if (!empty($d['group_members'])): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="margin: 0 0 12px; color: #374151;">Members in Group #<?= htmlspecialchars($d['debug_group_id']) ?></h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <thead>
                                <tr style="border-bottom: 2px solid #e5e7eb;">
                                    <th style="text-align: left; padding: 8px;">User ID</th>
                                    <th style="text-align: left; padding: 8px;">Name</th>
                                    <th style="text-align: left; padding: 8px;">Email</th>
                                    <th style="text-align: left; padding: 8px;">Member Status</th>
                                    <th style="text-align: left; padding: 8px;">Approved</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($d['group_members'] as $member): ?>
                                    <tr style="border-bottom: 1px solid #f3f4f6;">
                                        <td style="padding: 8px;"><?= $member['user_id'] ?></td>
                                        <td style="padding: 8px;"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></td>
                                        <td style="padding: 8px;"><?= htmlspecialchars($member['email'] ?: '(no email)') ?></td>
                                        <td style="padding: 8px;">
                                            <span style="background: <?= $member['member_status'] === 'active' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $member['member_status'] === 'active' ? '#065f46' : '#991b1b' ?>; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">
                                                <?= htmlspecialchars($member['member_status']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 8px;">
                                            <?php if ($member['is_approved'] === null || $member['is_approved'] == 1): ?>
                                                <span style="color: #059669;"><i class="fa-solid fa-check"></i> Yes</span>
                                            <?php else: ?>
                                                <span style="color: #dc2626;"><i class="fa-solid fa-xmark"></i> No</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top: 12px; color: #6b7280; font-size: 0.85rem;">
                            <strong>Note:</strong> Members need: active status, email address, and is_approved = 1 (or NULL) to receive newsletters.
                        </p>
                    </div>
                <?php elseif (isset($d['debug_group_id'])): ?>
                    <div style="margin-top: 20px; color: #dc2626;">
                        <i class="fa-solid fa-exclamation-circle"></i> No members found in group #<?= htmlspecialchars($d['debug_group_id']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($d['group_error'])): ?>
                    <div style="margin-top: 20px; color: #dc2626;">
                        <i class="fa-solid fa-exclamation-circle"></i> Error: <?= htmlspecialchars($d['group_error']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php
else {
    require __DIR__ . '/../../layouts/modern/footer.php';
}
?>
