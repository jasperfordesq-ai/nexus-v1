<?php
/**
 * Nexus Impact Report - Enhanced Visual Report Template
 * Comprehensive community impact report with 1000-point scoring system
 *
 * @var array $reportData - Report data including user/org scores, transactions, impact metrics
 * @var string $reportType - Type: 'user', 'organization', 'community'
 * @var array $dateRange - Start and end dates for report period
 */

$reportType = $reportType ?? 'user';
$title = $reportData['title'] ?? 'Community Impact Report';
$period = $reportData['period'] ?? 'Last 30 Days';
$scoreData = $reportData['score_data'] ?? [];
$impactMetrics = $reportData['impact_metrics'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> | Project Nexus</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0a0e1a 0%, #1e293b 100%);
            color: #f1f5f9;
            line-height: 1.6;
            padding: 2rem;
            min-height: 100vh;
        }

        .report-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .report-header {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 24px;
            padding: 3rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .report-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .report-header-content {
            position: relative;
            z-index: 1;
        }

        .report-title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .report-subtitle {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 400;
        }

        .report-period {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.5rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50px;
            font-size: 0.95rem;
            color: #10b981;
            font-weight: 600;
        }

        .report-section {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.9));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-icon {
            font-size: 2.5rem;
        }

        .executive-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-4px);
        }

        .summary-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #6366f1;
            line-height: 1;
        }

        .summary-label {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .summary-change {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #10b981;
            font-weight: 600;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .metric-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            border-left: 4px solid #6366f1;
        }

        .metric-label {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #6366f1;
        }

        .impact-story {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(6, 182, 212, 0.1));
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 16px;
            padding: 2rem;
            margin: 1.5rem 0;
        }

        .story-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 1rem;
        }

        .story-content {
            font-size: 1.125rem;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.8;
        }

        .highlight-box {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.15), rgba(245, 158, 11, 0.15));
            border-left: 4px solid #fbbf24;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .highlight-title {
            font-weight: 700;
            color: #fbbf24;
            margin-bottom: 0.5rem;
            font-size: 1.125rem;
        }

        .chart-placeholder {
            width: 100%;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.5);
            font-size: 1.125rem;
            margin: 1rem 0;
        }

        .badge-showcase {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .badge-item {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
            border: 1px solid rgba(99, 102, 241, 0.4);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .badge-item:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
        }

        .badge-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .badge-name {
            font-size: 0.875rem;
            color: #f1f5f9;
            font-weight: 600;
        }

        .recommendations-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1rem;
        }

        .recommendation-item {
            background: rgba(255, 255, 255, 0.05);
            border-left: 4px solid #8b5cf6;
            border-radius: 8px;
            padding: 1.25rem;
            display: flex;
            gap: 1rem;
        }

        .recommendation-number {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
        }

        .recommendation-content {
            flex: 1;
        }

        .recommendation-title {
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 0.25rem;
        }

        .recommendation-text {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .print-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .print-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.5);
        }

        @media print {
            body {
                background: white;
                color: black;
                padding: 0;
            }

            .print-button {
                display: none;
            }

            .report-section {
                page-break-inside: avoid;
                border: 1px solid #ddd;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .report-title {
                font-size: 2rem;
            }

            .executive-summary {
                grid-template-columns: 1fr;
            }

            .metric-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Report Header -->
        <div class="report-header">
            <div class="report-header-content">
                <div class="report-title"><?php echo htmlspecialchars($title); ?></div>
                <div class="report-subtitle">Project Nexus Impact Analysis</div>
                <div class="report-period">📅 <?php echo htmlspecialchars($period); ?></div>
            </div>
        </div>

        <!-- Nexus Score Section -->
        <?php if (!empty($scoreData)): ?>
        <div class="report-section">
            <h2 class="section-title">
                <span class="section-icon">🏆</span>
                Nexus Score Overview
            </h2>

            <!-- Include the score dashboard component -->
            <?php
            $isPublic = false;
            include __DIR__ . '/../components/nexus-score-dashboard.php';
            ?>
        </div>
        <?php endif; ?>

        <!-- Executive Summary -->
        <div class="report-section">
            <h2 class="section-title">
                <span class="section-icon">📊</span>
                Executive Summary
            </h2>

            <div class="executive-summary">
                <div class="summary-card">
                    <div class="summary-value"><?php echo number_format($impactMetrics['total_exchanges'] ?? 0); ?></div>
                    <div class="summary-label">Total Exchanges</div>
                    <div class="summary-change">▲ +<?php echo $impactMetrics['exchanges_change'] ?? 0; ?>%</div>
                </div>

                <div class="summary-card">
                    <div class="summary-value"><?php echo number_format($impactMetrics['hours_exchanged'] ?? 0); ?></div>
                    <div class="summary-label">Hours Exchanged</div>
                    <div class="summary-change">▲ +<?php echo $impactMetrics['hours_change'] ?? 0; ?>%</div>
                </div>

                <div class="summary-card">
                    <div class="summary-value"><?php echo number_format($impactMetrics['active_members'] ?? 0); ?></div>
                    <div class="summary-label">Active Members</div>
                    <div class="summary-change">▲ +<?php echo $impactMetrics['members_change'] ?? 0; ?>%</div>
                </div>

                <div class="summary-card">
                    <div class="summary-value">$<?php echo number_format($impactMetrics['economic_value'] ?? 0); ?></div>
                    <div class="summary-label">Economic Value</div>
                    <div class="summary-change">▲ +<?php echo $impactMetrics['value_change'] ?? 0; ?>%</div>
                </div>
            </div>

            <div class="impact-story">
                <div class="story-title">📖 Impact Story</div>
                <div class="story-content">
                    <?php echo $impactMetrics['story'] ?? 'Our community continues to grow stronger through mutual support and shared time. Every exchange represents trust, connection, and positive social impact.'; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Metrics -->
        <div class="report-section">
            <h2 class="section-title">
                <span class="section-icon">📈</span>
                Detailed Metrics
            </h2>

            <div class="metric-grid">
                <div class="metric-item">
                    <span class="metric-label">Average Transaction Size</span>
                    <span class="metric-value"><?php echo number_format($impactMetrics['avg_transaction'] ?? 2.5, 1); ?> hrs</span>
                </div>

                <div class="metric-item">
                    <span class="metric-label">Network Diversity</span>
                    <span class="metric-value"><?php echo number_format($impactMetrics['network_diversity'] ?? 85); ?>%</span>
                </div>

                <div class="metric-item">
                    <span class="metric-label">Member Retention Rate</span>
                    <span class="metric-value"><?php echo number_format($impactMetrics['retention_rate'] ?? 92); ?>%</span>
                </div>

                <div class="metric-item">
                    <span class="metric-label">Avg. Response Time</span>
                    <span class="metric-value"><?php echo number_format($impactMetrics['response_time'] ?? 4.2, 1); ?> hrs</span>
                </div>

                <div class="metric-item">
                    <span class="metric-label">Skills Shared</span>
                    <span class="metric-value"><?php echo number_format($impactMetrics['skills_count'] ?? 47); ?></span>
                </div>

                <div class="metric-item">
                    <span class="metric-label">Community Events</span>
                    <span class="metric-value"><?php echo number_format($impactMetrics['events_count'] ?? 12); ?></span>
                </div>
            </div>

            <div class="highlight-box">
                <div class="highlight-title">💡 Key Insight</div>
                <p><?php echo $impactMetrics['key_insight'] ?? 'Your community engagement has increased significantly, showing strong network effects and sustainable growth patterns.'; ?></p>
            </div>
        </div>

        <!-- Achievements & Badges -->
        <?php if (!empty($scoreData['breakdown']['badges']['details']['badges'])): ?>
        <div class="report-section">
            <h2 class="section-title">
                <span class="section-icon">🏅</span>
                Achievements & Recognition
            </h2>

            <div class="badge-showcase">
                <?php foreach ($scoreData['breakdown']['badges']['details']['badges'] as $badge): ?>
                <div class="badge-item">
                    <div class="badge-icon">🏆</div>
                    <div class="badge-name"><?php echo htmlspecialchars($badge['name'] ?? 'Badge'); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <?php if (!empty($scoreData['insights'])): ?>
        <div class="report-section">
            <h2 class="section-title">
                <span class="section-icon">💡</span>
                Recommendations for Growth
            </h2>

            <div class="recommendations-list">
                <?php foreach ($scoreData['insights'] as $index => $insight): ?>
                <div class="recommendation-item">
                    <div class="recommendation-number"><?php echo $index + 1; ?></div>
                    <div class="recommendation-content">
                        <div class="recommendation-title"><?php echo htmlspecialchars($insight['title']); ?></div>
                        <div class="recommendation-text"><?php echo htmlspecialchars($insight['message']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Conclusion -->
        <div class="report-section">
            <h2 class="section-title">
                <span class="section-icon">🎯</span>
                Looking Forward
            </h2>

            <div class="story-content">
                <p style="margin-bottom: 1rem;">
                    This report demonstrates the measurable impact of community engagement and time banking.
                    Your <?php echo $scoreData['total_score'] ?? 0; ?> Nexus Score reflects <?php echo strtolower($scoreData['tier']['name'] ?? 'strong'); ?>-level
                    participation and commitment to building social capital.
                </p>

                <p style="margin-bottom: 1rem;">
                    Continue fostering connections, sharing skills, and contributing to the community ecosystem.
                    Every exchange strengthens the network and creates lasting value for all members.
                </p>

                <?php if (!empty($scoreData['next_milestone'])): ?>
                <div class="highlight-box">
                    <div class="highlight-title">🎯 Next Milestone</div>
                    <p>
                        You're <?php echo $scoreData['next_milestone']['points_remaining']; ?> points away from reaching
                        <strong><?php echo $scoreData['next_milestone']['name']; ?></strong> tier and unlocking:
                        <?php echo $scoreData['next_milestone']['reward']; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <button class="print-button" onclick="window.print()">
        🖨️ Print Report
    </button>
</body>
</html>
