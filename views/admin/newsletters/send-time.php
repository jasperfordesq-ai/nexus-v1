<?php
/**
 * Send Time Optimization View
 * Shows analytics-based recommendations for optimal send times
 */
$layout = layout(); // Fixed: centralized detection
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Hero settings for modern layout
$hTitle = 'Send Time Optimization';
$hSubtitle = 'Find the best times to send your newsletters based on subscriber engagement';
$hGradient = 'mt-hero-gradient-brand';
$hType = 'Analytics';

else {
    require __DIR__ . '/../../layouts/modern/header.php';
}

$hasData = $recommendations['has_data'] ?? false;
$bestTimes = $recommendations['recommendations'] ?? [];
$bestDays = $recommendations['best_days'] ?? [];
$bestCombinations = $recommendations['best_combinations'] ?? [];
$hourlyData = $recommendations['hourly_data'] ?? [];
$dailyData = $recommendations['daily_data'] ?? [];
$totalOpens = $recommendations['total_opens_analyzed'] ?? 0;
$heatmapData = $heatmap['heatmap'] ?? [];
$maxValue = $heatmap['max_value'] ?? 0;
?>

<div class="newsletter-admin-wrapper">
    <div style="max-width: 1100px; margin: 0 auto;">

    <!-- Navigation -->
    <div style="margin-bottom: 24px;">
        <a href="<?= $basePath ?>/admin/newsletters" style="color: #6b7280; text-decoration: none; font-size: 0.9rem;">
            <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
        </a>
    </div>

    <?php if (!$hasData): ?>
    <!-- No Data State -->
    <div class="nexus-card" style="text-align: center; padding: 60px 40px;">
        <div style="font-size: 4rem; margin-bottom: 20px;">
            <i class="fa-solid fa-chart-line" style="color: #d1d5db;"></i>
        </div>
        <h2 style="color: #374151; margin: 0 0 15px 0;">Not Enough Data Yet</h2>
        <p style="color: #6b7280; font-size: 1.1rem; max-width: 500px; margin: 0 auto 30px;">
            <?= htmlspecialchars($recommendations['message'] ?? 'Send a few newsletters to generate personalized send time recommendations.') ?>
        </p>

        <h3 style="color: #374151; margin: 30px 0 20px; font-size: 1rem;">Industry Best Practices</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; max-width: 700px; margin: 0 auto;">
            <?php foreach ($bestTimes as $rec): ?>
            <div style="background: #f9fafb; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb;">
                <div style="font-size: 1.3rem; font-weight: 700; color: #6366f1; margin-bottom: 5px;">
                    <?= htmlspecialchars($rec['time']) ?>
                </div>
                <div style="color: #374151; font-weight: 500; margin-bottom: 5px;"><?= htmlspecialchars($rec['label']) ?></div>
                <div style="color: #9ca3af; font-size: 0.85rem;"><?= htmlspecialchars($rec['note'] ?? '') ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 40px;">
            <a href="<?= $basePath ?>/admin/newsletters/create"
               style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 14px 28px; border-radius: 10px; text-decoration: none; font-weight: 600;">
                <i class="fa-solid fa-plus"></i> Create Your First Newsletter
            </a>
        </div>
    </div>

    <?php else: ?>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="nexus-card" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white;">
            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 8px;">Total Opens Analyzed</div>
            <div style="font-size: 2.5rem; font-weight: 700;"><?= number_format($totalOpens) ?></div>
        </div>

        <?php if (!empty($bestCombinations[0])): ?>
        <div class="nexus-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 8px;">Best Time to Send</div>
            <div style="font-size: 1.8rem; font-weight: 700;">
                <?= htmlspecialchars($bestCombinations[0]['day']) ?> at <?= htmlspecialchars($bestCombinations[0]['time']) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($bestDays[0])): ?>
        <div class="nexus-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;">
            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 8px;">Best Day</div>
            <div style="font-size: 2rem; font-weight: 700;">
                <?= htmlspecialchars($bestDays[0]['day_name']) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Top Recommended Times -->
    <div class="nexus-card" style="margin-bottom: 30px;">
        <h3 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #374151; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-star" style="color: #f59e0b;"></i>
            Top Recommended Send Times
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <?php foreach ($bestTimes as $index => $rec): ?>
            <div style="position: relative; background: <?= $index === 0 ? 'linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%)' : '#f9fafb' ?>; padding: 20px; border-radius: 12px; border: <?= $index === 0 ? '2px solid #6366f1' : '1px solid #e5e7eb' ?>;">
                <?php if ($index === 0): ?>
                <div style="position: absolute; top: -10px; right: 15px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                    BEST
                </div>
                <?php endif; ?>
                <div style="font-size: 1.5rem; font-weight: 700; color: #6366f1; margin-bottom: 5px;">
                    <?= htmlspecialchars($rec['time']) ?>
                </div>
                <div style="color: #374151; font-weight: 500; margin-bottom: 8px;">
                    <?= htmlspecialchars($rec['label']) ?>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <div style="flex: 1; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden;">
                        <div style="height: 100%; width: <?= min(100, $rec['percentage'] * 2) ?>%; background: linear-gradient(90deg, #6366f1, #4f46e5); border-radius: 3px;"></div>
                    </div>
                    <span style="color: #6b7280; font-size: 0.85rem; min-width: 45px;"><?= $rec['percentage'] ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Best Day/Time Combinations -->
    <?php if (!empty($bestCombinations)): ?>
    <div class="nexus-card" style="margin-bottom: 30px;">
        <h3 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #374151; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-calendar-check" style="color: #10b981;"></i>
            Best Day & Time Combinations
        </h3>

        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <?php foreach ($bestCombinations as $index => $combo): ?>
            <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 15px 25px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 36px; height: 36px; background: #10b981; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?= $index + 1 ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: #166534;">
                        <?= htmlspecialchars($combo['day']) ?>
                    </div>
                    <div style="color: #15803d; font-size: 0.9rem;">
                        at <?= htmlspecialchars($combo['time']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Engagement Heatmap -->
    <div class="nexus-card" style="margin-bottom: 30px;">
        <h3 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #374151; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-fire" style="color: #ef4444;"></i>
            Engagement Heatmap
        </h3>
        <p style="color: #6b7280; margin: 0 0 20px 0; font-size: 0.9rem;">
            Darker colors indicate higher engagement. Find the sweet spots for your audience.
        </p>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 700px;">
                <thead>
                    <tr>
                        <th style="padding: 8px 12px; text-align: left; color: #374151; font-size: 0.85rem; font-weight: 600;"></th>
                        <?php for ($h = 6; $h <= 22; $h++): ?>
                        <th style="padding: 8px 4px; text-align: center; color: #6b7280; font-size: 0.75rem; font-weight: 500;">
                            <?= date('ga', strtotime("$h:00")) ?>
                        </th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($days as $day):
                        $dayData = $heatmapData[$day] ?? [];
                    ?>
                    <tr>
                        <td style="padding: 8px 12px; font-weight: 500; color: #374151; font-size: 0.85rem;"><?= $day ?></td>
                        <?php for ($h = 6; $h <= 22; $h++):
                            $value = $dayData[$h] ?? 0;
                            $intensity = $maxValue > 0 ? ($value / $maxValue) : 0;
                            $bgColor = $value === 0 ? '#f9fafb' :
                                      ($intensity > 0.7 ? '#6366f1' :
                                      ($intensity > 0.4 ? '#818cf8' :
                                      ($intensity > 0.2 ? '#a5b4fc' : '#c7d2fe')));
                            $textColor = $intensity > 0.4 ? 'white' : '#374151';
                        ?>
                        <td style="padding: 4px; text-align: center;">
                            <div style="width: 100%; height: 32px; background: <?= $bgColor ?>; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: <?= $textColor ?>; font-size: 0.75rem; font-weight: 500;" title="<?= $day ?> at <?= date('g A', strtotime("$h:00")) ?>: <?= $value ?> opens">
                                <?= $value > 0 ? $value : '' ?>
                            </div>
                        </td>
                        <?php endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div style="display: flex; align-items: center; gap: 15px; margin-top: 20px; justify-content: center;">
            <span style="color: #6b7280; font-size: 0.85rem;">Less</span>
            <div style="display: flex; gap: 4px;">
                <div style="width: 24px; height: 16px; background: #f9fafb; border-radius: 3px; border: 1px solid #e5e7eb;"></div>
                <div style="width: 24px; height: 16px; background: #c7d2fe; border-radius: 3px;"></div>
                <div style="width: 24px; height: 16px; background: #a5b4fc; border-radius: 3px;"></div>
                <div style="width: 24px; height: 16px; background: #818cf8; border-radius: 3px;"></div>
                <div style="width: 24px; height: 16px; background: #6366f1; border-radius: 3px;"></div>
            </div>
            <span style="color: #6b7280; font-size: 0.85rem;">More</span>
        </div>
    </div>

    <!-- Hourly Chart -->
    <div class="nexus-card" style="margin-bottom: 30px;">
        <h3 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #374151; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-clock" style="color: #3b82f6;"></i>
            Opens by Hour of Day
        </h3>

        <div style="height: 200px; display: flex; align-items: flex-end; gap: 4px; padding: 0 10px;">
            <?php
            $maxOpens = max(array_column($hourlyData, 'opens')) ?: 1;
            foreach ($hourlyData as $h):
                $height = ($h['opens'] / $maxOpens) * 100;
                $isTopHour = in_array($h['hour'], array_column(array_slice($bestTimes, 0, 3), 'hour'));
            ?>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 5px;">
                <div style="width: 100%; height: <?= max(5, $height) ?>%; background: <?= $isTopHour ? 'linear-gradient(180deg, #6366f1 0%, #4f46e5 100%)' : '#e5e7eb' ?>; border-radius: 4px 4px 0 0; transition: height 0.3s;" title="<?= date('g A', strtotime("$h[hour]:00")) ?>: <?= $h['opens'] ?> opens"></div>
                <span style="color: #6b7280; font-size: 0.65rem; transform: rotate(-45deg); white-space: nowrap;"><?= date('ga', strtotime("$h[hour]:00")) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Daily Chart -->
    <div class="nexus-card">
        <h3 style="margin: 0 0 20px 0; font-size: 1.1rem; color: #374151; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-calendar-week" style="color: #10b981;"></i>
            Opens by Day of Week
        </h3>

        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px;">
            <?php
            $maxDayOpens = max(array_column($dailyData, 'opens')) ?: 1;
            foreach ($dailyData as $d):
                $height = ($d['opens'] / $maxDayOpens) * 100;
                $isTopDay = in_array($d['day_num'], array_column(array_slice($bestDays, 0, 2), 'day_num'));
            ?>
            <div style="text-align: center;">
                <div style="height: 120px; display: flex; align-items: flex-end; justify-content: center; margin-bottom: 10px;">
                    <div style="width: 80%; height: <?= max(10, $height) ?>%; background: <?= $isTopDay ? 'linear-gradient(180deg, #10b981 0%, #059669 100%)' : '#e5e7eb' ?>; border-radius: 8px 8px 0 0;"></div>
                </div>
                <div style="font-weight: 600; color: <?= $isTopDay ? '#10b981' : '#374151' ?>; font-size: 0.9rem;">
                    <?= substr($d['day_name'], 0, 3) ?>
                </div>
                <div style="color: #6b7280; font-size: 0.8rem;">
                    <?= number_format($d['opens']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
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
