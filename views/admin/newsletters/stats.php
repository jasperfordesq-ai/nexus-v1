<?php
/**
 * Newsletter Stats/Analytics View
 */
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Hero settings for modern layout
$hTitle = 'Newsletter Analytics';
$hSubtitle = 'Performance metrics for: ' . htmlspecialchars($newsletter['subject'] ?? 'Newsletter');
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Analytics';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}

// Calculate rates
$totalSent = $stats['total_sent'] ?? 0;
$totalFailed = $stats['total_failed'] ?? 0;
$totalOpens = $newsletter['total_opens'] ?? 0;
$uniqueOpens = $newsletter['unique_opens'] ?? 0;
$totalClicks = $newsletter['total_clicks'] ?? 0;
$uniqueClicks = $newsletter['unique_clicks'] ?? 0;

$total = $totalSent + $totalFailed;
$successRate = $total > 0 ? round(($totalSent / $total) * 100, 1) : 0;
$openRate = $totalSent > 0 ? round(($uniqueOpens / $totalSent) * 100, 1) : 0;
$clickRate = $totalSent > 0 ? round(($uniqueClicks / $totalSent) * 100, 1) : 0;
$clickToOpenRate = $uniqueOpens > 0 ? round(($uniqueClicks / $uniqueOpens) * 100, 1) : 0;

// A/B test data
$isABTest = !empty($newsletter['ab_test_enabled']) && !empty($newsletter['subject_b']);
$abResults = $abResults ?? null;
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 1000px; margin: 0 auto;">

    <!-- Navigation -->
    <div style="margin-bottom: 24px;">
        <a href="<?= $basePath ?>/admin-legacy/newsletters" style="color: #6b7280; text-decoration: none; font-size: 0.9rem;">
            <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
        </a>
    </div>

    <!-- Newsletter Summary -->
    <div class="nexus-card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 style="margin: 0 0 10px 0; font-size: 1.3rem;"><?= htmlspecialchars($newsletter['subject']) ?></h2>
                <div style="color: #6b7280; font-size: 0.9rem;">
                    Sent on <?= date('F j, Y \a\t g:i A', strtotime($newsletter['sent_at'])) ?>
                    <?php if (!empty($newsletter['author_name'])): ?>
                        by <?= htmlspecialchars($newsletter['author_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <?php if ($isABTest): ?>
                <div style="background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                    A/B Test
                </div>
                <?php endif; ?>
                <?php
                // Calculate non-openers for resend button
                $nonOpenerCount = $totalSent - $uniqueOpens;
                if ($nonOpenerCount > 0):
                ?>
                <a href="<?= $basePath ?>/admin-legacy/newsletters/resend/<?= $newsletter['id'] ?>"
                   style="display: inline-flex; align-items: center; gap: 6px; background: #f3e8ff; color: #7c3aed; padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none;">
                    <i class="fa-solid fa-rotate-right"></i>
                    Resend to <?= number_format($nonOpenerCount) ?> Non-Openers
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isABTest && $abResults): ?>
    <!-- A/B Test Results -->
    <div class="nexus-card" style="margin-bottom: 20px; border: 2px solid #fbbf24;">
        <h3 style="margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px;">
            <span style="background: #fbbf24; color: #000; padding: 4px 10px; border-radius: 4px; font-size: 0.8rem;">A/B</span>
            Subject Line Test Results
        </h3>

        <?php if (!empty($abResults['winner'])): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.5rem;">🏆</span>
            <div>
                <strong>Winner: Subject <?= $abResults['winner'] ?></strong><br>
                <span style="font-size: 0.9rem;">"<?= htmlspecialchars($abResults['winner'] === 'A' ? $abResults['subject_a'] : $abResults['subject_b']) ?>"</span>
            </div>
        </div>
        <?php elseif (!empty($abResults['suggested_winner']) && $abResults['suggested_winner'] !== 'tie'): ?>
        <div style="background: #e0f2fe; color: #0369a1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <strong>Suggested Winner: Subject <?= $abResults['suggested_winner'] ?></strong>
            (<?= number_format($abResults['winning_margin'], 1) ?>% better <?= ($abResults['winner_metric'] ?? 'opens') === 'clicks' ? 'click rate' : 'open rate' ?>)
            <form action="<?= $basePath ?>/admin-legacy/newsletters/select-winner/<?= $newsletter['id'] ?>" method="POST" style="display: inline; margin-left: 15px;">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="winner" value="<?= $abResults['suggested_winner'] ?>">
                <button type="submit" style="background: #0369a1; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">
                    Confirm Winner
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <?php
            $variantA = $abResults['variants']['A'] ?? null;
            $variantB = $abResults['variants']['B'] ?? null;
            $winnerMetric = ($abResults['winner_metric'] ?? 'opens') === 'clicks' ? 'click_rate' : 'open_rate';
            $isAWinner = $variantA && $variantB && $variantA[$winnerMetric] > $variantB[$winnerMetric];
            $isBWinner = $variantA && $variantB && $variantB[$winnerMetric] > $variantA[$winnerMetric];
            ?>

            <!-- Variant A -->
            <div style="border: 2px solid <?= $isAWinner ? '#10b981' : '#e5e7eb' ?>; border-radius: 8px; padding: 20px; <?= $isAWinner ? 'background: #f0fdf4;' : '' ?>">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <span style="background: #6366f1; color: white; padding: 4px 10px; border-radius: 4px; font-weight: 600;">Subject A</span>
                    <?php if ($isAWinner): ?><span style="color: #10b981;">✓ Leading</span><?php endif; ?>
                </div>
                <div style="font-size: 0.95rem; color: #374151; margin-bottom: 15px; font-style: italic;">
                    "<?= htmlspecialchars($abResults['subject_a']) ?>"
                </div>
                <?php if ($variantA): ?>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #6366f1;"><?= $variantA['open_rate'] ?>%</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Open Rate</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #059669;"><?= $variantA['click_rate'] ?>%</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Click Rate</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #374151;"><?= number_format($variantA['total_sent']) ?></div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Sent</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Variant B -->
            <div style="border: 2px solid <?= $isBWinner ? '#10b981' : '#e5e7eb' ?>; border-radius: 8px; padding: 20px; <?= $isBWinner ? 'background: #f0fdf4;' : '' ?>">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <span style="background: #f59e0b; color: white; padding: 4px 10px; border-radius: 4px; font-weight: 600;">Subject B</span>
                    <?php if ($isBWinner): ?><span style="color: #10b981;">✓ Leading</span><?php endif; ?>
                </div>
                <div style="font-size: 0.95rem; color: #374151; margin-bottom: 15px; font-style: italic;">
                    "<?= htmlspecialchars($abResults['subject_b']) ?>"
                </div>
                <?php if ($variantB): ?>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #6366f1;"><?= $variantB['open_rate'] ?>%</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Open Rate</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #059669;"><?= $variantB['click_rate'] ?>%</div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Click Rate</div>
                    </div>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #374151;"><?= number_format($variantB['total_sent']) ?></div>
                        <div style="font-size: 0.8rem; color: #6b7280;">Sent</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 0.85rem; color: #6b7280;">
            Split: <?= $abResults['split_percentage'] ?>% A / <?= 100 - $abResults['split_percentage'] ?>% B
            &bull; Winning metric: <?= ucfirst($abResults['winner_metric'] ?? 'opens') ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Primary Stats Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div class="nexus-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2rem; font-weight: 700; color: #111827;">
                <?= number_format($totalSent) ?>
            </div>
            <div style="color: #6b7280; font-size: 0.85rem; margin-top: 5px;">Delivered</div>
        </div>

        <div class="nexus-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2rem; font-weight: 700; color: #6366f1;">
                <?= $openRate ?>%
            </div>
            <div style="color: #6b7280; font-size: 0.85rem; margin-top: 5px;">Open Rate</div>
            <div style="font-size: 0.75rem; color: #9ca3af;"><?= number_format($uniqueOpens) ?> unique</div>
        </div>

        <div class="nexus-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2rem; font-weight: 700; color: #059669;">
                <?= $clickRate ?>%
            </div>
            <div style="color: #6b7280; font-size: 0.85rem; margin-top: 5px;">Click Rate</div>
            <div style="font-size: 0.75rem; color: #9ca3af;"><?= number_format($uniqueClicks) ?> unique</div>
        </div>

        <div class="nexus-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;">
                <?= $clickToOpenRate ?>%
            </div>
            <div style="color: #6b7280; font-size: 0.85rem; margin-top: 5px;">Click-to-Open</div>
        </div>

        <?php if ($totalFailed > 0): ?>
        <div class="nexus-card" style="text-align: center; padding: 20px;">
            <div style="font-size: 2rem; font-weight: 700; color: #dc2626;">
                <?= number_format($totalFailed) ?>
            </div>
            <div style="color: #6b7280; font-size: 0.85rem; margin-top: 5px;">Failed</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Engagement Funnel -->
    <div class="nexus-card" style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 20px 0;">Engagement Funnel</h3>

        <div style="display: flex; flex-direction: column; gap: 15px;">
            <!-- Delivered -->
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                    <span>Delivered</span>
                    <span style="font-weight: 600;"><?= number_format($totalSent) ?></span>
                </div>
                <div style="background: #e5e7eb; border-radius: 4px; height: 12px; overflow: hidden;">
                    <div style="background: #6366f1; height: 100%; width: 100%;"></div>
                </div>
            </div>

            <!-- Opened -->
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                    <span>Opened</span>
                    <span style="font-weight: 600;"><?= number_format($uniqueOpens) ?> (<?= $openRate ?>%)</span>
                </div>
                <div style="background: #e5e7eb; border-radius: 4px; height: 12px; overflow: hidden;">
                    <div style="background: #8b5cf6; height: 100%; width: <?= min($openRate, 100) ?>%;"></div>
                </div>
            </div>

            <!-- Clicked -->
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                    <span>Clicked</span>
                    <span style="font-weight: 600;"><?= number_format($uniqueClicks) ?> (<?= $clickRate ?>%)</span>
                </div>
                <div style="background: #e5e7eb; border-radius: 4px; height: 12px; overflow: hidden;">
                    <div style="background: #059669; height: 100%; width: <?= min($clickRate, 100) ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($analytics['opens_over_time']) && count($analytics['opens_over_time']) > 1): ?>
    <!-- Opens Over Time Chart -->
    <div class="nexus-card" style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 20px 0;">Engagement Over Time</h3>

        <?php
        $opensData = $analytics['opens_over_time'];
        $maxOpens = max(array_column($opensData, 'opens'));
        $chartHeight = 150;
        ?>

        <div style="position: relative; height: <?= $chartHeight + 40 ?>px; margin-bottom: 10px;">
            <!-- Y-axis labels -->
            <div style="position: absolute; left: 0; top: 0; height: <?= $chartHeight ?>px; width: 40px; display: flex; flex-direction: column; justify-content: space-between; font-size: 0.7rem; color: #9ca3af;">
                <span><?= $maxOpens ?></span>
                <span><?= round($maxOpens / 2) ?></span>
                <span>0</span>
            </div>

            <!-- Chart area -->
            <div style="position: absolute; left: 45px; right: 0; top: 0; height: <?= $chartHeight ?>px; border-left: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; display: flex; align-items: flex-end; gap: 2px; padding: 0 5px;">
                <?php foreach ($opensData as $point):
                    $barHeight = $maxOpens > 0 ? ($point['opens'] / $maxOpens) * $chartHeight : 0;
                ?>
                    <div style="flex: 1; min-width: 8px; max-width: 40px; height: <?= $barHeight ?>px; background: linear-gradient(to top, #6366f1, #8b5cf6); border-radius: 3px 3px 0 0; position: relative;"
                         title="<?= date('M j, g:ia', strtotime($point['hour'])) ?>: <?= $point['opens'] ?> opens">
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- X-axis labels -->
            <div style="position: absolute; left: 45px; right: 0; bottom: 0; height: 35px; display: flex; justify-content: space-between; padding: 10px 5px 0; font-size: 0.65rem; color: #9ca3af; overflow: hidden;">
                <?php
                $labelCount = min(6, count($opensData));
                $step = max(1, floor(count($opensData) / $labelCount));
                for ($i = 0; $i < count($opensData); $i += $step):
                    if (isset($opensData[$i])):
                ?>
                    <span style="white-space: nowrap;"><?= date('M j, ga', strtotime($opensData[$i]['hour'])) ?></span>
                <?php
                    endif;
                endfor;
                ?>
            </div>
        </div>

        <div style="font-size: 0.8rem; color: #6b7280; text-align: center; border-top: 1px solid #f3f4f6; padding-top: 15px; margin-top: 10px;">
            Total opens: <?= number_format($totalOpens) ?> &bull; Peak: <?= $maxOpens ?> opens in one hour
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($analytics['top_links'])): ?>
    <!-- Top Links -->
    <div class="nexus-card" style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 20px 0;">Top Clicked Links</h3>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <th style="text-align: left; padding: 10px 0; font-size: 0.85rem; color: #6b7280; text-transform: uppercase;">URL</th>
                    <th style="text-align: right; padding: 10px 0; font-size: 0.85rem; color: #6b7280; text-transform: uppercase; width: 100px;">Clicks</th>
                    <th style="text-align: right; padding: 10px 0; font-size: 0.85rem; color: #6b7280; text-transform: uppercase; width: 100px;">Unique</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($analytics['top_links'] as $link): ?>
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 12px 0;">
                        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" style="color: #6366f1; text-decoration: none; word-break: break-all;">
                            <?= htmlspecialchars(strlen($link['url']) > 60 ? substr($link['url'], 0, 60) . '...' : $link['url']) ?>
                        </a>
                    </td>
                    <td style="padding: 12px 0; text-align: right; font-weight: 600;"><?= number_format($link['clicks']) ?></td>
                    <td style="padding: 12px 0; text-align: right; color: #6b7280;"><?= number_format($link['unique_clicks']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($analytics['device_stats'])): ?>
    <!-- Device Breakdown -->
    <div class="nexus-card" style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 20px 0;">Device Breakdown</h3>

        <?php
        $deviceTotal = array_sum($analytics['device_stats']);
        $deviceColors = [
            'desktop' => '#6366f1',
            'mobile' => '#10b981',
            'tablet' => '#f59e0b',
            'unknown' => '#9ca3af'
        ];
        ?>

        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <?php foreach ($analytics['device_stats'] as $device => $count): ?>
                <?php if ($count > 0): ?>
                <div style="text-align: center;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: <?= $deviceColors[$device] ?? '#6b7280' ?>;">
                        <?= $deviceTotal > 0 ? round(($count / $deviceTotal) * 100) : 0 ?>%
                    </div>
                    <div style="color: #6b7280; font-size: 0.85rem; text-transform: capitalize;"><?= $device ?></div>
                    <div style="font-size: 0.75rem; color: #9ca3af;"><?= number_format($count) ?> opens</div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($analytics['recent_activity'])): ?>
    <!-- Recent Activity -->
    <div class="nexus-card" style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 20px 0;">Recent Activity</h3>

        <div style="max-height: 400px; overflow-y: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <th style="text-align: left; padding: 10px 0; font-size: 0.85rem; color: #6b7280;">Event</th>
                        <th style="text-align: left; padding: 10px 0; font-size: 0.85rem; color: #6b7280;">Email</th>
                        <th style="text-align: left; padding: 10px 0; font-size: 0.85rem; color: #6b7280;">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($analytics['recent_activity'], 0, 20) as $activity): ?>
                    <tr style="border-bottom: 1px solid #f3f4f6;">
                        <td style="padding: 10px 0;">
                            <?php if ($activity['type'] === 'open'): ?>
                                <span style="background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">OPENED</span>
                            <?php else: ?>
                                <span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">CLICKED</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px 0; font-size: 0.9rem;">
                            <?= htmlspecialchars($activity['email']) ?>
                            <?php if ($activity['type'] === 'click' && !empty($activity['url'])): ?>
                                <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 2px;">
                                    <?= htmlspecialchars(strlen($activity['url']) > 40 ? substr($activity['url'], 0, 40) . '...' : $activity['url']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px 0; font-size: 0.85rem; color: #6b7280;">
                            <?= date('M j, g:i a', strtotime($activity['timestamp'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="nexus-card">
        <h3 style="margin: 0 0 15px 0;">Actions</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?= $basePath ?>/admin-legacy/newsletters/preview/<?= $newsletter['id'] ?>" target="_blank" class="nexus-btn-secondary">
                View Email Content
            </a>
            <a href="<?= $basePath ?>/admin-legacy/newsletters/duplicate/<?= $newsletter['id'] ?>" class="nexus-btn-secondary">
                Duplicate Newsletter
            </a>
        </div>
    </div>

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
