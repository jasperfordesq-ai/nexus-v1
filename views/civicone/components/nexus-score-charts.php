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

<!-- Nexus Score Charts CSS -->
<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/purged/civicone-nexus-score-charts.min.css">

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
                <div class="legend-color legend-color-primary"></div>
                <span>Your Score</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-color-comparison"></div>
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
                <div class="legend-color legend-color-primary"></div>
                <span>Your Score</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-color-average"></div>
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

<!-- Nexus Score Charts JavaScript -->
<script src="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-nexus-score-charts.min.js" defer></script>
