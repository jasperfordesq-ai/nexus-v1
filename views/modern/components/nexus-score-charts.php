<?php
/**
 * Nexus Score Charts Component
 * Interactive charts and visualizations for score analytics
 *
 * @var array $scoreData - Score data from NexusScoreService
 * @var array $historicalData - Optional historical score data for trends
 * @var array $communityStats - Community-wide statistics for comparison
 */

$breakdown = $scoreData['breakdown'] ?? [];
$communityStats = $communityStats ?? ['average_score' => 450];
?>

<style>
.charts-container {
    display: grid;
    gap: 2rem;
}

.chart-card {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.9));
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.chart-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f1f5f9;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chart-icon {
    font-size: 1.75rem;
}

.radar-chart-container {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
    aspect-ratio: 1;
}

.radar-svg {
    width: 100%;
    height: 100%;
}

.radar-grid {
    fill: none;
    stroke: rgba(255, 255, 255, 0.1);
    stroke-width: 1;
}

.radar-axis {
    stroke: rgba(255, 255, 255, 0.2);
    stroke-width: 1;
}

.radar-data-area {
    fill: rgba(99, 102, 241, 0.3);
    stroke: #6366f1;
    stroke-width: 2;
    filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.5));
}

.radar-comparison-area {
    fill: rgba(139, 92, 246, 0.2);
    stroke: #8b5cf6;
    stroke-width: 2;
    stroke-dasharray: 5, 5;
}

.radar-label {
    fill: #f1f5f9;
    font-size: 14px;
    font-weight: 600;
    text-anchor: middle;
}

.bar-chart-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.bar-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.bar-label {
    min-width: 180px;
    font-size: 0.95rem;
    color: #f1f5f9;
    font-weight: 500;
}

.bar-track {
    flex: 1;
    height: 36px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 18px;
    position: relative;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 1rem;
    transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
}

.bar-value {
    color: #fff;
    font-weight: 700;
    font-size: 0.875rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.bar-comparison {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 80%;
    background: #fbbf24;
    box-shadow: 0 0 10px rgba(251, 191, 36, 0.8);
    z-index: 10;
}

.bar-comparison::after {
    content: 'AVG';
    position: absolute;
    top: -24px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    font-weight: 700;
    color: #fbbf24;
    white-space: nowrap;
}

.comparison-legend {
    display: flex;
    gap: 2rem;
    justify-content: center;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.8);
}

.legend-color {
    width: 24px;
    height: 12px;
    border-radius: 6px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.stat-box {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #6366f1;
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.trend-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.trend-up {
    color: #10b981;
}

.trend-down {
    color: #ef4444;
}

@media (max-width: 768px) {
    .bar-label {
        min-width: 120px;
        font-size: 0.85rem;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="charts-container">
    <!-- Radar Chart: Category Comparison -->
    <div class="chart-card">
        <div class="chart-header">
            <div class="chart-title">
                <span class="chart-icon">🎯</span>
                Performance Radar
            </div>
        </div>

        <div class="radar-chart-container">
            <svg class="radar-svg" viewBox="0 0 400 400">
                <!-- Background Grid -->
                <g id="radar-grid">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <polygon
                        class="radar-grid"
                        points="<?php
                            $points = [];
                            for ($angle = 0; $angle < 360; $angle += 60) {
                                $rad = deg2rad($angle - 90);
                                $radius = ($i / 5) * 150;
                                $x = 200 + $radius * cos($rad);
                                $y = 200 + $radius * sin($rad);
                                $points[] = "$x,$y";
                            }
                            echo implode(' ', $points);
                        ?>"
                    />
                    <?php endfor; ?>
                </g>

                <!-- Axes -->
                <g id="radar-axes">
                    <?php
                    $categories = [
                        ['key' => 'engagement', 'label' => 'Engagement'],
                        ['key' => 'quality', 'label' => 'Quality'],
                        ['key' => 'volunteer', 'label' => 'Volunteer'],
                        ['key' => 'activity', 'label' => 'Activity'],
                        ['key' => 'badges', 'label' => 'Badges'],
                        ['key' => 'impact', 'label' => 'Impact']
                    ];

                    foreach ($categories as $index => $cat):
                        $angle = ($index * 60) - 90;
                        $rad = deg2rad($angle);
                        $x = 200 + 150 * cos($rad);
                        $y = 200 + 150 * sin($rad);
                    ?>
                    <line class="radar-axis" x1="200" y1="200" x2="<?php echo $x; ?>" y2="<?php echo $y; ?>"></line>
                    <?php endforeach; ?>
                </g>

                <!-- User Data Area -->
                <polygon
                    class="radar-data-area"
                    points="<?php
                        $points = [];
                        foreach ($categories as $index => $cat) {
                            $percentage = $breakdown[$cat['key']]['percentage'] ?? 0;
                            $angle = ($index * 60) - 90;
                            $rad = deg2rad($angle);
                            $radius = ($percentage / 100) * 150;
                            $x = 200 + $radius * cos($rad);
                            $y = 200 + $radius * sin($rad);
                            $points[] = "$x,$y";
                        }
                        echo implode(' ', $points);
                    ?>"
                />

                <!-- Average Comparison Area (50% benchmark) -->
                <polygon
                    class="radar-comparison-area"
                    points="<?php
                        $points = [];
                        foreach ($categories as $index => $cat) {
                            $angle = ($index * 60) - 90;
                            $rad = deg2rad($angle);
                            $radius = 0.5 * 150; // 50% average
                            $x = 200 + $radius * cos($rad);
                            $y = 200 + $radius * sin($rad);
                            $points[] = "$x,$y";
                        }
                        echo implode(' ', $points);
                    ?>"
                />

                <!-- Labels -->
                <g id="radar-labels">
                    <?php
                    foreach ($categories as $index => $cat):
                        $angle = ($index * 60) - 90;
                        $rad = deg2rad($angle);
                        $x = 200 + 180 * cos($rad);
                        $y = 200 + 180 * sin($rad) + 5;
                    ?>
                    <text class="radar-label" x="<?php echo $x; ?>" y="<?php echo $y; ?>">
                        <?php echo $cat['label']; ?>
                    </text>
                    <?php endforeach; ?>
                </g>
            </svg>
        </div>

        <div class="comparison-legend">
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(90deg, #6366f1, #8b5cf6);"></div>
                <span>Your Score</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #8b5cf6; opacity: 0.5;"></div>
                <span>Community Average</span>
            </div>
        </div>
    </div>

    <!-- Bar Chart: Detailed Category Scores -->
    <div class="chart-card">
        <div class="chart-header">
            <div class="chart-title">
                <span class="chart-icon">📊</span>
                Category Breakdown
            </div>
        </div>

        <div class="bar-chart-container">
            <?php
            $categoryDetails = [
                'engagement' => ['name' => '🤝 Community Engagement', 'avg' => 65],
                'quality' => ['name' => '⭐ Contribution Quality', 'avg' => 70],
                'volunteer' => ['name' => '💪 Volunteer Hours', 'avg' => 45],
                'activity' => ['name' => '🚀 Platform Activity', 'avg' => 60],
                'badges' => ['name' => '🏆 Badges & Achievements', 'avg' => 50],
                'impact' => ['name' => '🌟 Social Impact', 'avg' => 55]
            ];

            foreach ($categoryDetails as $key => $detail):
                $data = $breakdown[$key] ?? ['score' => 0, 'max' => 100, 'percentage' => 0];
                $avgPosition = ($detail['avg'] / 100) * 100; // Position as percentage
            ?>
            <div class="bar-item">
                <div class="bar-label"><?php echo $detail['name']; ?></div>
                <div class="bar-track">
                    <div class="bar-fill" style="width: <?php echo $data['percentage']; ?>%">
                        <span class="bar-value"><?php echo number_format($data['score'], 0); ?>/<?php echo $data['max']; ?></span>
                    </div>
                    <div class="bar-comparison" style="left: <?php echo $avgPosition; ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="comparison-legend">
            <div class="legend-item">
                <div class="legend-color" style="background: linear-gradient(90deg, #6366f1, #8b5cf6);"></div>
                <span>Your Score</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #fbbf24; width: 3px;"></div>
                <span>Community Average</span>
            </div>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="chart-card">
        <div class="chart-header">
            <div class="chart-title">
                <span class="chart-icon">📈</span>
                Key Statistics
            </div>
        </div>

        <div class="stats-grid">
            <?php
            $stats = [
                [
                    'value' => number_format($scoreData['total_score'], 0),
                    'label' => 'Total Score',
                    'trend' => '+12%',
                    'trendUp' => true
                ],
                [
                    'value' => $scoreData['tier']['name'],
                    'label' => 'Current Tier',
                    'trend' => '',
                    'trendUp' => true
                ],
                [
                    'value' => 'Top ' . $scoreData['percentile'] . '%',
                    'label' => 'Percentile',
                    'trend' => '',
                    'trendUp' => true
                ],
                [
                    'value' => count($breakdown['badges']['details']['badges'] ?? []),
                    'label' => 'Badges Earned',
                    'trend' => '+3',
                    'trendUp' => true
                ],
                [
                    'value' => number_format($breakdown['engagement']['details']['unique_connections'] ?? 0),
                    'label' => 'Connections',
                    'trend' => '+5',
                    'trendUp' => true
                ],
                [
                    'value' => number_format($breakdown['volunteer']['details']['total_hours'] ?? 0, 0),
                    'label' => 'Hours Given',
                    'trend' => '+8h',
                    'trendUp' => true
                ]
            ];

            foreach ($stats as $stat):
            ?>
            <div class="stat-box">
                <div class="stat-value"><?php echo $stat['value']; ?></div>
                <div class="stat-label"><?php echo $stat['label']; ?></div>
                <?php if ($stat['trend']): ?>
                <div class="trend-indicator <?php echo $stat['trendUp'] ? 'trend-up' : 'trend-down'; ?>">
                    <span><?php echo $stat['trendUp'] ? '▲' : '▼'; ?></span>
                    <span><?php echo $stat['trend']; ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Animate bars on load
document.addEventListener('DOMContentLoaded', function() {
    const bars = document.querySelectorAll('.bar-fill');
    bars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 200);
    });
});
</script>
