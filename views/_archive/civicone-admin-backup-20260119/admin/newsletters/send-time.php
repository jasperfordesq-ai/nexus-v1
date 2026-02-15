<?php
/**
 * Send Time Optimization View - Gold Standard Admin UI
 * Holographic Glassmorphism Dark Theme
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin page configuration
$adminPageTitle = 'Send Time Optimization';
$adminPageSubtitle = 'Best times to send based on engagement';
$adminPageIcon = 'fa-solid fa-clock';

// Extract data
$hasData = $recommendations['has_data'] ?? false;
$bestTimes = $recommendations['recommendations'] ?? [];
$bestDays = $recommendations['best_days'] ?? [];
$bestCombinations = $recommendations['best_combinations'] ?? [];
$hourlyData = $recommendations['hourly_data'] ?? [];
$dailyData = $recommendations['daily_data'] ?? [];
$totalOpens = $recommendations['total_opens_analyzed'] ?? 0;
$heatmapData = $heatmap['heatmap'] ?? [];
$maxValue = $heatmap['max_value'] ?? 0;

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<style>
    .sendtime-wrapper {
        padding: 0 40px 60px;
        position: relative;
        z-index: 10;
    }

    .sendtime-container {
        max-width: 1100px;
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

    /* Glass Card */
    .glass-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        backdrop-filter: blur(20px);
        margin-bottom: 24px;
        padding: 24px;
    }

    /* No Data State */
    .no-data-card {
        text-align: center;
        padding: 60px 40px;
    }

    .no-data-icon {
        font-size: 4rem;
        color: rgba(255, 255, 255, 0.2);
        margin-bottom: 20px;
    }

    .no-data-title {
        color: #ffffff;
        margin: 0 0 15px 0;
        font-size: 1.5rem;
    }

    .no-data-text {
        color: rgba(255, 255, 255, 0.5);
        font-size: 1.1rem;
        max-width: 500px;
        margin: 0 auto 30px;
    }

    .practices-title {
        color: #ffffff;
        margin: 30px 0 20px;
        font-size: 1rem;
    }

    .practices-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        max-width: 700px;
        margin: 0 auto;
    }

    .practice-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 20px;
        border-radius: 12px;
    }

    .practice-time {
        font-size: 1.3rem;
        font-weight: 700;
        color: #a5b4fc;
        margin-bottom: 5px;
    }

    .practice-label {
        color: rgba(255, 255, 255, 0.8);
        font-weight: 500;
        margin-bottom: 5px;
    }

    .practice-note {
        color: rgba(255, 255, 255, 0.4);
        font-size: 0.85rem;
    }

    .btn-create {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        padding: 14px 28px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        margin-top: 40px;
        transition: all 0.3s ease;
    }

    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    }

    /* Summary Cards */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .summary-card {
        border-radius: 16px;
        padding: 24px;
        color: white;
    }

    .summary-card.purple {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    }

    .summary-card.green {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .summary-card.amber {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .summary-label {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 8px;
    }

    .summary-value {
        font-size: 2.5rem;
        font-weight: 700;
    }

    .summary-value.medium {
        font-size: 1.8rem;
    }

    .summary-value.large {
        font-size: 2rem;
    }

    /* Section Title */
    .section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }

    .section-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .section-icon.star { background: rgba(245, 158, 11, 0.2); color: #fcd34d; }
    .section-icon.calendar { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
    .section-icon.fire { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
    .section-icon.clock { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    .section-icon.week { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }

    .section-title {
        margin: 0;
        font-size: 1.1rem;
        color: #ffffff;
        font-weight: 600;
    }

    .section-subtitle {
        color: rgba(255, 255, 255, 0.5);
        margin: 0 0 20px 0;
        font-size: 0.9rem;
    }

    /* Recommended Times Grid */
    .times-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .time-card {
        position: relative;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 20px;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .time-card:hover {
        border-color: rgba(99, 102, 241, 0.3);
        transform: translateY(-2px);
    }

    .time-card.best {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
        border: 2px solid rgba(99, 102, 241, 0.4);
    }

    .best-badge {
        position: absolute;
        top: -10px;
        right: 15px;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .time-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #a5b4fc;
        margin-bottom: 5px;
    }

    .time-label {
        color: rgba(255, 255, 255, 0.8);
        font-weight: 500;
        margin-bottom: 10px;
    }

    .time-bar-container {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .time-bar-bg {
        flex: 1;
        height: 6px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
        overflow: hidden;
    }

    .time-bar {
        height: 100%;
        background: linear-gradient(90deg, #6366f1, #4f46e5);
        border-radius: 3px;
    }

    .time-percent {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        min-width: 45px;
        text-align: right;
    }

    /* Combinations */
    .combos-grid {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .combo-card {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.1) 100%);
        border: 1px solid rgba(16, 185, 129, 0.3);
        padding: 15px 25px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .combo-rank {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
    }

    .combo-day {
        font-weight: 600;
        color: #6ee7b7;
    }

    .combo-time {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
    }

    /* Heatmap */
    .heatmap-scroll {
        overflow-x: auto;
    }

    .heatmap-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 700px;
    }

    .heatmap-table th {
        padding: 8px 4px;
        text-align: center;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.75rem;
        font-weight: 500;
    }

    .heatmap-table th:first-child {
        text-align: left;
        padding-left: 12px;
    }

    .heatmap-table td:first-child {
        padding: 8px 12px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.85rem;
    }

    .heatmap-table td {
        padding: 4px;
        text-align: center;
    }

    .heatmap-cell {
        width: 100%;
        height: 32px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 500;
        transition: transform 0.2s;
    }

    .heatmap-cell:hover {
        transform: scale(1.1);
    }

    .heatmap-legend {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-top: 20px;
        justify-content: center;
    }

    .legend-label {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
    }

    .legend-blocks {
        display: flex;
        gap: 4px;
    }

    .legend-block {
        width: 24px;
        height: 16px;
        border-radius: 3px;
    }

    /* Hourly Chart */
    .hourly-chart {
        height: 200px;
        display: flex;
        align-items: flex-end;
        gap: 4px;
        padding: 0 10px;
    }

    .hourly-bar-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }

    .hourly-bar {
        width: 100%;
        border-radius: 4px 4px 0 0;
        transition: all 0.3s ease;
    }

    .hourly-bar.highlight {
        background: linear-gradient(180deg, #6366f1 0%, #4f46e5 100%);
    }

    .hourly-bar.normal {
        background: rgba(255, 255, 255, 0.15);
    }

    .hourly-bar:hover {
        opacity: 0.8;
    }

    .hourly-label {
        color: rgba(255, 255, 255, 0.4);
        font-size: 0.65rem;
        transform: rotate(-45deg);
        white-space: nowrap;
    }

    /* Daily Chart */
    .daily-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 15px;
    }

    .daily-item {
        text-align: center;
    }

    .daily-bar-wrapper {
        height: 120px;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        margin-bottom: 10px;
    }

    .daily-bar {
        width: 80%;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
    }

    .daily-bar.highlight {
        background: linear-gradient(180deg, #10b981 0%, #059669 100%);
    }

    .daily-bar.normal {
        background: rgba(255, 255, 255, 0.15);
    }

    .daily-name {
        font-weight: 600;
        font-size: 0.9rem;
    }

    .daily-name.highlight { color: #6ee7b7; }
    .daily-name.normal { color: rgba(255, 255, 255, 0.7); }

    .daily-count {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.8rem;
    }

    @media (max-width: 768px) {
        .sendtime-wrapper {
            padding: 0 20px 40px;
        }

        .daily-grid {
            grid-template-columns: repeat(4, 1fr);
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="sendtime-wrapper">
    <div class="sendtime-container">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/admin-legacy/newsletters" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
        </a>

        <?php if (!$hasData): ?>
        <!-- No Data State -->
        <div class="glass-card no-data-card">
            <div class="no-data-icon">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <h2 class="no-data-title">Not Enough Data Yet</h2>
            <p class="no-data-text">
                <?= htmlspecialchars($recommendations['message'] ?? 'Send a few newsletters to generate personalized send time recommendations.') ?>
            </p>

            <h3 class="practices-title">Industry Best Practices</h3>
            <div class="practices-grid">
                <?php foreach ($bestTimes as $rec): ?>
                <div class="practice-card">
                    <div class="practice-time"><?= htmlspecialchars($rec['time']) ?></div>
                    <div class="practice-label"><?= htmlspecialchars($rec['label']) ?></div>
                    <div class="practice-note"><?= htmlspecialchars($rec['note'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <a href="<?= $basePath ?>/admin-legacy/newsletters/create" class="btn-create">
                <i class="fa-solid fa-plus"></i> Create Your First Newsletter
            </a>
        </div>

        <?php else: ?>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card purple">
                <div class="summary-label">Total Opens Analyzed</div>
                <div class="summary-value"><?= number_format($totalOpens) ?></div>
            </div>

            <?php if (!empty($bestCombinations[0])): ?>
            <div class="summary-card green">
                <div class="summary-label">Best Time to Send</div>
                <div class="summary-value medium">
                    <?= htmlspecialchars($bestCombinations[0]['day']) ?> at <?= htmlspecialchars($bestCombinations[0]['time']) ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($bestDays[0])): ?>
            <div class="summary-card amber">
                <div class="summary-label">Best Day</div>
                <div class="summary-value large"><?= htmlspecialchars($bestDays[0]['day_name']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top Recommended Times -->
        <div class="glass-card">
            <div class="section-header">
                <div class="section-icon star"><i class="fa-solid fa-star"></i></div>
                <h3 class="section-title">Top Recommended Send Times</h3>
            </div>

            <div class="times-grid">
                <?php foreach ($bestTimes as $index => $rec): ?>
                <div class="time-card <?= $index === 0 ? 'best' : '' ?>">
                    <?php if ($index === 0): ?>
                    <div class="best-badge">BEST</div>
                    <?php endif; ?>
                    <div class="time-value"><?= htmlspecialchars($rec['time']) ?></div>
                    <div class="time-label"><?= htmlspecialchars($rec['label']) ?></div>
                    <div class="time-bar-container">
                        <div class="time-bar-bg">
                            <div class="time-bar" style="width: <?= min(100, $rec['percentage'] * 2) ?>%;"></div>
                        </div>
                        <span class="time-percent"><?= $rec['percentage'] ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Best Day/Time Combinations -->
        <?php if (!empty($bestCombinations)): ?>
        <div class="glass-card">
            <div class="section-header">
                <div class="section-icon calendar"><i class="fa-solid fa-calendar-check"></i></div>
                <h3 class="section-title">Best Day & Time Combinations</h3>
            </div>

            <div class="combos-grid">
                <?php foreach ($bestCombinations as $index => $combo): ?>
                <div class="combo-card">
                    <div class="combo-rank"><?= $index + 1 ?></div>
                    <div>
                        <div class="combo-day"><?= htmlspecialchars($combo['day']) ?></div>
                        <div class="combo-time">at <?= htmlspecialchars($combo['time']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Engagement Heatmap -->
        <div class="glass-card">
            <div class="section-header">
                <div class="section-icon fire"><i class="fa-solid fa-fire"></i></div>
                <h3 class="section-title">Engagement Heatmap</h3>
            </div>
            <p class="section-subtitle">
                Darker colors indicate higher engagement. Find the sweet spots for your audience.
            </p>

            <div class="heatmap-scroll">
                <table class="heatmap-table">
                    <thead>
                        <tr>
                            <th></th>
                            <?php for ($h = 6; $h <= 22; $h++): ?>
                            <th><?= date('ga', strtotime("$h:00")) ?></th>
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
                            <td><?= $day ?></td>
                            <?php for ($h = 6; $h <= 22; $h++):
                                $value = $dayData[$h] ?? 0;
                                $intensity = $maxValue > 0 ? ($value / $maxValue) : 0;
                                if ($value === 0) {
                                    $bgColor = 'rgba(255, 255, 255, 0.03)';
                                    $textColor = 'rgba(255, 255, 255, 0.3)';
                                } elseif ($intensity > 0.7) {
                                    $bgColor = '#6366f1';
                                    $textColor = 'white';
                                } elseif ($intensity > 0.4) {
                                    $bgColor = 'rgba(99, 102, 241, 0.6)';
                                    $textColor = 'white';
                                } elseif ($intensity > 0.2) {
                                    $bgColor = 'rgba(99, 102, 241, 0.35)';
                                    $textColor = 'rgba(255, 255, 255, 0.9)';
                                } else {
                                    $bgColor = 'rgba(99, 102, 241, 0.15)';
                                    $textColor = 'rgba(255, 255, 255, 0.7)';
                                }
                            ?>
                            <td>
                                <div class="heatmap-cell" style="background: <?= $bgColor ?>; color: <?= $textColor ?>;" title="<?= $day ?> at <?= date('g A', strtotime("$h:00")) ?>: <?= $value ?> opens">
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
            <div class="heatmap-legend">
                <span class="legend-label">Less</span>
                <div class="legend-blocks">
                    <div class="legend-block" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1);"></div>
                    <div class="legend-block" style="background: rgba(99, 102, 241, 0.15);"></div>
                    <div class="legend-block" style="background: rgba(99, 102, 241, 0.35);"></div>
                    <div class="legend-block" style="background: rgba(99, 102, 241, 0.6);"></div>
                    <div class="legend-block" style="background: #6366f1;"></div>
                </div>
                <span class="legend-label">More</span>
            </div>
        </div>

        <!-- Hourly Chart -->
        <div class="glass-card">
            <div class="section-header">
                <div class="section-icon clock"><i class="fa-solid fa-clock"></i></div>
                <h3 class="section-title">Opens by Hour of Day</h3>
            </div>

            <div class="hourly-chart">
                <?php
                $maxOpensHourly = max(array_column($hourlyData, 'opens')) ?: 1;
                foreach ($hourlyData as $h):
                    $height = ($h['opens'] / $maxOpensHourly) * 100;
                    $isTopHour = in_array($h['hour'], array_column(array_slice($bestTimes, 0, 3), 'hour'));
                ?>
                <div class="hourly-bar-container">
                    <div class="hourly-bar <?= $isTopHour ? 'highlight' : 'normal' ?>" style="height: <?= max(5, $height) ?>%;" title="<?= date('g A', strtotime("{$h['hour']}:00")) ?>: <?= $h['opens'] ?> opens"></div>
                    <span class="hourly-label"><?= date('ga', strtotime("{$h['hour']}:00")) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Daily Chart -->
        <div class="glass-card">
            <div class="section-header">
                <div class="section-icon week"><i class="fa-solid fa-calendar-week"></i></div>
                <h3 class="section-title">Opens by Day of Week</h3>
            </div>

            <div class="daily-grid">
                <?php
                $maxDayOpens = max(array_column($dailyData, 'opens')) ?: 1;
                foreach ($dailyData as $d):
                    $height = ($d['opens'] / $maxDayOpens) * 100;
                    $isTopDay = in_array($d['day_num'], array_column(array_slice($bestDays, 0, 2), 'day_num'));
                ?>
                <div class="daily-item">
                    <div class="daily-bar-wrapper">
                        <div class="daily-bar <?= $isTopDay ? 'highlight' : 'normal' ?>" style="height: <?= max(10, $height) ?>%;"></div>
                    </div>
                    <div class="daily-name <?= $isTopDay ? 'highlight' : 'normal' ?>">
                        <?= substr($d['day_name'], 0, 3) ?>
                    </div>
                    <div class="daily-count"><?= number_format($d['opens']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php endif; ?>

    </div>
</div>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
