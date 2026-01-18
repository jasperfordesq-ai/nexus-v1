<?php
/**
 * Newsletter Analytics Overview
 * Aggregate stats across all newsletters
 */

$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Hero settings for modern layout
$hTitle = 'Newsletter Analytics';
$hSubtitle = 'Performance metrics and insights across all your email campaigns';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Analytics';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 1200px; margin: 0 auto;">

        <!-- Navigation -->
        <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <a href="<?= $basePath ?>/admin/newsletters" style="color: #6b7280; text-decoration: none; font-size: 0.9rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
            </a>
            <div style="display: flex; gap: 12px;">
                <a href="<?= $basePath ?>/admin/newsletters/send-time" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-clock"></i> Send Time Optimization
                </a>
                <a href="<?= $basePath ?>/admin/newsletters/bounces" style="background: #f1f5f9; color: #475569; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-shield-halved"></i> Bounces
                </a>
            </div>
        </div>

        <!-- Aggregate Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 30px;">
            <div class="nexus-card" style="padding: 24px; text-align: center; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd;">
                <div style="font-size: 2.5rem; font-weight: 700; color: #0369a1;"><?= number_format($totals['newsletters_sent']) ?></div>
                <div style="color: #0c4a6e; font-size: 0.9rem; font-weight: 500;">Campaigns Sent</div>
            </div>
            <div class="nexus-card" style="padding: 24px; text-align: center; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac;">
                <div style="font-size: 2.5rem; font-weight: 700; color: #15803d;"><?= number_format($totals['total_sent']) ?></div>
                <div style="color: #166534; font-size: 0.9rem; font-weight: 500;">Emails Delivered</div>
            </div>
            <div class="nexus-card" style="padding: 24px; text-align: center; background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border: 1px solid #c4b5fd;">
                <div style="font-size: 2.5rem; font-weight: 700; color: #7c3aed;"><?= $avgOpenRate ?>%</div>
                <div style="color: #5b21b6; font-size: 0.9rem; font-weight: 500;">Avg. Open Rate</div>
            </div>
            <div class="nexus-card" style="padding: 24px; text-align: center; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fcd34d;">
                <div style="font-size: 2.5rem; font-weight: 700; color: #d97706;"><?= $avgClickRate ?>%</div>
                <div style="color: #92400e; font-size: 0.9rem; font-weight: 500;">Avg. Click Rate</div>
            </div>
        </div>

        <!-- Secondary Stats -->
        <div class="nexus-card" style="padding: 24px; margin-bottom: 24px;">
            <h3 style="margin: 0 0 20px 0; color: #111827;">Engagement Summary</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; text-align: center;">
                <div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #6366f1;"><?= number_format($totals['unique_opens']) ?></div>
                    <div style="font-size: 0.8rem; color: #6b7280;">Unique Opens</div>
                </div>
                <div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #6366f1;"><?= number_format($totals['total_opens']) ?></div>
                    <div style="font-size: 0.8rem; color: #6b7280;">Total Opens</div>
                </div>
                <div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #059669;"><?= number_format($totals['unique_clicks']) ?></div>
                    <div style="font-size: 0.8rem; color: #6b7280;">Unique Clicks</div>
                </div>
                <div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #059669;"><?= number_format($totals['total_clicks']) ?></div>
                    <div style="font-size: 0.8rem; color: #6b7280;">Total Clicks</div>
                </div>
                <?php if ($totals['total_failed'] > 0): ?>
                <div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #dc2626;"><?= number_format($totals['total_failed']) ?></div>
                    <div style="font-size: 0.8rem; color: #6b7280;">Failed</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($monthlyStats)): ?>
        <!-- Monthly Performance Chart -->
        <div class="nexus-card" style="padding: 24px; margin-bottom: 24px;">
            <h3 style="margin: 0 0 20px 0; color: #111827;">Monthly Performance</h3>

            <?php
            $maxSent = max(array_column($monthlyStats, 'sent'));
            $chartHeight = 180;
            ?>

            <div style="position: relative; height: <?= $chartHeight + 60 ?>px; margin-bottom: 10px;">
                <!-- Y-axis labels -->
                <div style="position: absolute; left: 0; top: 0; height: <?= $chartHeight ?>px; width: 50px; display: flex; flex-direction: column; justify-content: space-between; font-size: 0.75rem; color: #9ca3af; text-align: right; padding-right: 8px;">
                    <span><?= number_format($maxSent) ?></span>
                    <span><?= number_format($maxSent / 2) ?></span>
                    <span>0</span>
                </div>

                <!-- Chart area -->
                <div style="position: absolute; left: 55px; right: 0; top: 0; height: <?= $chartHeight ?>px; border-left: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; display: flex; align-items: flex-end; gap: 4px; padding: 0 10px;">
                    <?php foreach ($monthlyStats as $month):
                        $barHeight = $maxSent > 0 ? ($month['sent'] / $maxSent) * $chartHeight : 0;
                        $openRate = $month['sent'] > 0 ? round(($month['opens'] / $month['sent']) * 100, 1) : 0;
                        $clickRate = $month['sent'] > 0 ? round(($month['clicks'] / $month['sent']) * 100, 1) : 0;
                    ?>
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px;">
                            <div style="width: 100%; max-width: 60px; height: <?= $barHeight ?>px; background: linear-gradient(to top, #6366f1, #8b5cf6); border-radius: 4px 4px 0 0; position: relative;"
                                 title="<?= date('F Y', strtotime($month['month'] . '-01')) ?>&#10;Sent: <?= number_format($month['sent']) ?>&#10;Opens: <?= $openRate ?>%&#10;Clicks: <?= $clickRate ?>%">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- X-axis labels -->
                <div style="position: absolute; left: 55px; right: 0; bottom: 0; height: 50px; display: flex; justify-content: space-around; padding: 10px 10px 0; font-size: 0.7rem; color: #6b7280;">
                    <?php foreach ($monthlyStats as $month): ?>
                        <span style="flex: 1; text-align: center; white-space: nowrap;"><?= date('M \'y', strtotime($month['month'] . '-01')) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Legend -->
            <div style="display: flex; justify-content: center; gap: 20px; padding-top: 15px; border-top: 1px solid #f3f4f6; font-size: 0.8rem; color: #6b7280;">
                <span><span style="display: inline-block; width: 12px; height: 12px; background: #6366f1; border-radius: 2px; margin-right: 5px;"></span> Emails Sent</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($topPerformers)): ?>
        <!-- Top Performing Newsletters -->
        <div class="nexus-card" style="padding: 0; overflow: hidden; border-radius: 16px;">
            <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb;">
                <h3 style="margin: 0; color: #111827;">Top Performing Newsletters</h3>
                <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #6b7280;">Ranked by open rate (minimum 10 recipients)</p>
            </div>

            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 14px 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">#</th>
                        <th style="padding: 14px 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Subject</th>
                        <th style="padding: 14px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Sent</th>
                        <th style="padding: 14px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Open Rate</th>
                        <th style="padding: 14px 20px; text-align: center; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Click Rate</th>
                        <th style="padding: 14px 20px; text-align: right; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topPerformers as $index => $newsletter): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 16px 20px;">
                            <?php if ($index < 3): ?>
                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; font-weight: 700; font-size: 0.85rem; <?php
                                    if ($index === 0) echo 'background: #fef3c7; color: #d97706;';
                                    elseif ($index === 1) echo 'background: #f1f5f9; color: #64748b;';
                                    else echo 'background: #fef3c7; color: #92400e;';
                                ?>"><?= $index + 1 ?></span>
                            <?php else: ?>
                                <span style="color: #9ca3af; padding-left: 8px;"><?= $index + 1 ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px 20px;">
                            <a href="<?= $basePath ?>/admin/newsletters/stats/<?= $newsletter['id'] ?>" style="color: #111827; text-decoration: none; font-weight: 500;">
                                <?= htmlspecialchars(strlen($newsletter['subject']) > 50 ? substr($newsletter['subject'], 0, 50) . '...' : $newsletter['subject']) ?>
                            </a>
                        </td>
                        <td style="padding: 16px 20px; text-align: center; color: #6b7280;"><?= number_format($newsletter['total_sent']) ?></td>
                        <td style="padding: 16px 20px; text-align: center;">
                            <span style="font-weight: 700; color: #6366f1;"><?= $newsletter['open_rate'] ?>%</span>
                        </td>
                        <td style="padding: 16px 20px; text-align: center;">
                            <span style="font-weight: 600; color: #059669;"><?= $newsletter['click_rate'] ?>%</span>
                        </td>
                        <td style="padding: 16px 20px; text-align: right; color: #6b7280; font-size: 0.85rem;">
                            <?= date('M j, Y', strtotime($newsletter['sent_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (empty($topPerformers) && empty($monthlyStats)): ?>
        <!-- No Data -->
        <div class="nexus-card" style="padding: 60px 40px; text-align: center;">
            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                <i class="fa-solid fa-chart-line" style="font-size: 2rem; color: #94a3b8;"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; font-size: 1.25rem; color: #111827;">No analytics data yet</h3>
            <p style="color: #6b7280; margin-bottom: 24px;">Send your first newsletter to start seeing performance metrics.</p>
            <a href="<?= $basePath ?>/admin/newsletters/create" class="nexus-btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-plus"></i> Create Newsletter
            </a>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
    .newsletter-admin-wrapper {
        position: relative;
        z-index: 20;
        padding: 0 40px 60px;
    }

    @media (min-width: 601px) {
        .newsletter-admin-wrapper {
            padding-top: 140px;
        }
    }

    @media (max-width: 600px) {
        .newsletter-admin-wrapper {
            padding: 120px 15px 100px 15px;
        }

        .newsletter-admin-wrapper [style*="grid-template-columns"] {
            grid-template-columns: 1fr 1fr !important;
        }
    }
</style>

<?php
else {
    require __DIR__ . '/../../layouts/modern/footer.php';
}
?>
