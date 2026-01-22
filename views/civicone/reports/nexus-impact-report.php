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
    <!-- Impact Report CSS (extracted per CLAUDE.md) -->
    <link rel="stylesheet" href="/assets/css/civicone-nexus-impact-report.css?v=<?= time() ?>">
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
