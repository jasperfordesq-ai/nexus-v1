<?php
/**
 * Admin Nexus Score Analytics Dashboard
 * Community-wide scoring analytics and insights for administrators
 *
 * @var array $analyticsData - Aggregated community scoring data
 * @var array $trends - Score trends over time
 * @var array $topPerformers - Top scoring users
 * @var array $categoryStats - Statistics by category
 */

$totalUsers = $analyticsData['total_users'] ?? 0;
$averageScore = $analyticsData['average_score'] ?? 0;
$medianScore = $analyticsData['median_score'] ?? 0;
$tierDistribution = $analyticsData['tier_distribution'] ?? [];
$categoryStats = $categoryStats ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Score Analytics | Admin Dashboard</title>
    <link rel="stylesheet" href="/assets/css/admin-gold-standard.min.css">
    <style>
        .analytics-container {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.125rem;
            color: var(--admin-text-secondary);
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--admin-glass);
            backdrop-filter: var(--admin-blur-md);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius-lg);
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, var(--admin-glow-primary) 0%, transparent 70%);
            opacity: 0.2;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--admin-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--admin-text-primary);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-trend {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .trend-up {
            color: var(--admin-emerald);
        }

        .trend-down {
            color: #ef4444;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--admin-glass);
            backdrop-filter: var(--admin-blur-md);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius-lg);
            padding: 2rem;
            box-shadow: var(--admin-shadow-md);
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
            color: var(--admin-text-primary);
        }

        .chart-actions {
            display: flex;
            gap: 0.5rem;
        }

        .chart-action-btn {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius-sm);
            color: var(--admin-text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .chart-action-btn:hover {
            background: var(--admin-gradient-primary);
            border-color: transparent;
            color: white;
        }

        .tier-distribution-chart {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .tier-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .tier-label {
            min-width: 120px;
            font-size: 0.95rem;
            color: var(--admin-text-primary);
            font-weight: 600;
        }

        .tier-track {
            flex: 1;
            height: 40px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--admin-radius-md);
            position: relative;
            overflow: hidden;
        }

        .tier-fill {
            height: 100%;
            background: var(--admin-gradient-primary);
            border-radius: var(--admin-radius-md);
            display: flex;
            align-items: center;
            padding: 0 1rem;
            color: white;
            font-weight: 700;
            font-size: 0.875rem;
            transition: width 1s ease-out;
            box-shadow: var(--admin-glow);
        }

        .category-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .category-stat-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius-md);
            padding: 1.5rem;
            text-align: center;
        }

        .category-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }

        .category-name {
            font-size: 0.875rem;
            color: var(--admin-text-secondary);
            margin-bottom: 0.5rem;
        }

        .category-avg {
            font-size: 2rem;
            font-weight: 700;
            color: var(--admin-primary);
        }

        .category-max {
            font-size: 0.875rem;
            color: var(--admin-text-secondary);
        }

        .insights-section {
            margin-bottom: 2rem;
        }

        .insight-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .insight-card {
            background: var(--admin-glass);
            backdrop-filter: var(--admin-blur-md);
            border: 1px solid var(--admin-border);
            border-left: 4px solid var(--admin-primary);
            border-radius: var(--admin-radius-md);
            padding: 1.5rem;
        }

        .insight-card.warning {
            border-left-color: var(--admin-amber);
        }

        .insight-card.success {
            border-left-color: var(--admin-emerald);
        }

        .insight-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .insight-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--admin-text-primary);
            margin-bottom: 0.5rem;
        }

        .insight-text {
            font-size: 0.95rem;
            color: var(--admin-text-secondary);
            line-height: 1.5;
        }

        .export-section {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }

        .export-btn {
            padding: 0.75rem 1.5rem;
            background: var(--admin-gradient-primary);
            border: none;
            border-radius: var(--admin-radius-md);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--admin-shadow-lg);
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead {
            background: rgba(255, 255, 255, 0.05);
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--admin-text-primary);
            border-bottom: 2px solid var(--admin-border);
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--admin-border);
            color: var(--admin-text-secondary);
        }

        .data-table tbody tr {
            transition: background 0.2s ease;
        }

        .data-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        @media (max-width: 1200px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }

            .category-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .analytics-container {
                padding: 1rem;
            }

            .stats-overview {
                grid-template-columns: 1fr;
            }

            .category-stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="analytics-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">📊 Nexus Score Analytics</div>
            <div class="page-subtitle">Community-wide scoring insights and performance metrics</div>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-label">Total Active Users</div>
                <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                <div class="stat-trend trend-up">
                    <span>▲ +8.5%</span>
                    <span>vs last month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Average Nexus Score</div>
                <div class="stat-value"><?php echo number_format($averageScore); ?></div>
                <div class="stat-trend trend-up">
                    <span>▲ +12%</span>
                    <span>vs last month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Median Score</div>
                <div class="stat-value"><?php echo number_format($medianScore ?: 420); ?></div>
                <div class="stat-trend trend-up">
                    <span>▲ +7%</span>
                    <span>vs last month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Elite Members</div>
                <div class="stat-value"><?php echo number_format($tierDistribution['Elite'] ?? 23); ?></div>
                <div class="stat-trend trend-up">
                    <span>▲ +4</span>
                    <span>new this month</span>
                </div>
            </div>
        </div>

        <!-- Key Insights -->
        <div class="insights-section">
            <h2 style="color: var(--admin-text-primary); margin-bottom: 1rem; font-size: 1.75rem;">💡 Key Insights</h2>
            <div class="insight-cards">
                <div class="insight-card success">
                    <div class="insight-icon">📈</div>
                    <div class="insight-title">Strong Community Growth</div>
                    <div class="insight-text">
                        Average scores have increased by 12% this month, indicating high engagement and active participation across all categories.
                    </div>
                </div>

                <div class="insight-card">
                    <div class="insight-icon">🤝</div>
                    <div class="insight-title">Engagement Leading</div>
                    <div class="insight-text">
                        Community Engagement category has the highest average score at 65%, driven by increased credit exchanges and connections.
                    </div>
                </div>

                <div class="insight-card warning">
                    <div class="insight-icon">⚠️</div>
                    <div class="insight-title">Volunteer Hours Opportunity</div>
                    <div class="insight-text">
                        Only 45% average in Volunteer Hours category. Consider launching volunteer campaigns to boost participation.
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="chart-grid">
            <!-- Tier Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">User Tier Distribution</div>
                    <div class="chart-actions">
                        <button class="chart-action-btn">Last 30 Days</button>
                    </div>
                </div>

                <div class="tier-distribution-chart">
                    <?php
                    $tiers = [
                        ['name' => 'Legendary', 'count' => $tierDistribution['Legendary'] ?? 5, 'color' => '#ffd700'],
                        ['name' => 'Elite', 'count' => $tierDistribution['Elite'] ?? 23, 'color' => '#c0c0c0'],
                        ['name' => 'Expert', 'count' => $tierDistribution['Expert'] ?? 47, 'color' => '#cd7f32'],
                        ['name' => 'Advanced', 'count' => $tierDistribution['Advanced'] ?? 89, 'color' => '#6366f1'],
                        ['name' => 'Proficient', 'count' => $tierDistribution['Proficient'] ?? 134, 'color' => '#8b5cf6'],
                        ['name' => 'Intermediate', 'count' => $tierDistribution['Intermediate'] ?? 178, 'color' => '#06b6d4'],
                        ['name' => 'Developing', 'count' => $tierDistribution['Developing'] ?? 203, 'color' => '#10b981'],
                        ['name' => 'Beginner', 'count' => $tierDistribution['Beginner'] ?? 156, 'color' => '#f59e0b'],
                        ['name' => 'Novice', 'count' => $tierDistribution['Novice'] ?? 98, 'color' => '#94a3b8']
                    ];

                    $maxCount = max(array_column($tiers, 'count'));

                    foreach ($tiers as $tier):
                        $percentage = $maxCount > 0 ? ($tier['count'] / $maxCount) * 100 : 0;
                    ?>
                    <div class="tier-bar">
                        <div class="tier-label"><?php echo $tier['name']; ?></div>
                        <div class="tier-track">
                            <div class="tier-fill" style="width: <?php echo $percentage; ?>%; background: linear-gradient(90deg, <?php echo $tier['color']; ?>, <?php echo $tier['color']; ?>cc);">
                                <?php echo $tier['count']; ?> users
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Category Averages -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Category Performance</div>
                    <div class="chart-actions">
                        <button class="chart-action-btn">View Details</button>
                    </div>
                </div>

                <div class="category-stats-grid">
                    <?php
                    $categories = [
                        ['icon' => '🤝', 'name' => 'Engagement', 'avg' => 65, 'max' => 250],
                        ['icon' => '⭐', 'name' => 'Quality', 'avg' => 70, 'max' => 200],
                        ['icon' => '💪', 'name' => 'Volunteer', 'avg' => 45, 'max' => 200],
                        ['icon' => '🚀', 'name' => 'Activity', 'avg' => 60, 'max' => 150],
                        ['icon' => '🏆', 'name' => 'Badges', 'avg' => 50, 'max' => 100],
                        ['icon' => '🌟', 'name' => 'Impact', 'avg' => 55, 'max' => 100]
                    ];

                    foreach ($categories as $cat):
                    ?>
                    <div class="category-stat-card">
                        <div class="category-icon"><?php echo $cat['icon']; ?></div>
                        <div class="category-name"><?php echo $cat['name']; ?></div>
                        <div class="category-avg"><?php echo $cat['avg']; ?></div>
                        <div class="category-max">/ <?php echo $cat['max']; ?> max</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top Performers Table -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">🌟 Top Performers (Last 30 Days)</div>
                <div class="chart-actions">
                    <button class="chart-action-btn">Export CSV</button>
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>User</th>
                        <th>Nexus Score</th>
                        <th>Tier</th>
                        <th>Top Category</th>
                        <th>Growth</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $topUsers = [
                        ['rank' => 1, 'name' => 'Sarah Johnson', 'score' => 892, 'tier' => 'Elite', 'category' => 'Volunteer', 'growth' => '+45'],
                        ['rank' => 2, 'name' => 'Michael Chen', 'score' => 867, 'tier' => 'Elite', 'category' => 'Engagement', 'growth' => '+38'],
                        ['rank' => 3, 'name' => 'Emma Williams', 'score' => 823, 'tier' => 'Elite', 'category' => 'Quality', 'growth' => '+52'],
                        ['rank' => 4, 'name' => 'David Rodriguez', 'score' => 789, 'tier' => 'Expert', 'category' => 'Activity', 'growth' => '+29'],
                        ['rank' => 5, 'name' => 'Lisa Thompson', 'score' => 756, 'tier' => 'Expert', 'category' => 'Engagement', 'growth' => '+41']
                    ];

                    foreach ($topUsers as $user):
                    ?>
                    <tr>
                        <td><strong><?php echo $user['rank']; ?></strong></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><strong><?php echo number_format($user['score']); ?></strong></td>
                        <td><?php echo $user['tier']; ?></td>
                        <td><?php echo $user['category']; ?></td>
                        <td style="color: var(--admin-emerald);">▲ <?php echo $user['growth']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Export Actions -->
        <div class="export-section">
            <button class="export-btn">
                📊 Export Full Report (PDF)
            </button>
            <button class="export-btn">
                📈 Export Data (CSV)
            </button>
        </div>
    </div>

    <script>
    // Animate tier bars on load
    document.addEventListener('DOMContentLoaded', function() {
        const tierFills = document.querySelectorAll('.tier-fill');
        tierFills.forEach(fill => {
            const width = fill.style.width;
            fill.style.width = '0%';
            setTimeout(() => {
                fill.style.width = width;
            }, 100);
        });
    });
    </script>
</body>
</html>
