<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Group Analytics') ?></title>
    <link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/nexus-phoenix.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .analytics-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .analytics-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        .back-link {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
        }

        .stat-change {
            font-size: 14px;
            margin-top: 8px;
        }

        .stat-change.positive {
            color: #10b981;
        }

        .stat-change.negative {
            color: #ef4444;
        }

        .chart-section {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .chart-section h2 {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .member-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .member-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .member-item:last-child {
            border-bottom: none;
        }

        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
            background: #e5e7eb;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 2px;
        }

        .member-stats {
            font-size: 13px;
            color: #6b7280;
        }

        .activity-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .activity-badge.very-active {
            background: #d1fae5;
            color: #065f46;
        }

        .activity-badge.active {
            background: #dbeafe;
            color: #1e40af;
        }

        .activity-badge.moderate {
            background: #fef3c7;
            color: #92400e;
        }

        .activity-badge.inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        .content-list {
            margin-top: 16px;
        }

        .content-item {
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .content-item:last-child {
            margin-bottom: 0;
        }

        .content-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .content-meta {
            font-size: 13px;
            color: #6b7280;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="analytics-container">
        <div class="analytics-header">
            <h1><?= htmlspecialchars($group['name']) ?> - Analytics</h1>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>" class="back-link">
                ← Back to Group
            </a>
        </div>

        <!-- Overview Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Members</div>
                <div class="stat-value"><?= number_format($analytics['overview']['total_members']) ?></div>
                <div class="stat-change positive">
                    +<?= $analytics['new_this_week'] ?> this week
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Pending Requests</div>
                <div class="stat-value"><?= number_format($analytics['overview']['pending_requests']) ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Discussions</div>
                <div class="stat-value"><?= number_format($analytics['overview']['total_discussions']) ?></div>
                <div class="stat-change">
                    <?= $analytics['discussion_stats']['this_week'] ?> this week
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Posts</div>
                <div class="stat-value"><?= number_format($analytics['overview']['total_posts']) ?></div>
                <div class="stat-change">
                    <?= $analytics['post_stats']['this_week'] ?> this week
                </div>
            </div>

            <?php if ($analytics['retention_30_day'] !== null): ?>
            <div class="stat-card">
                <div class="stat-label">30-Day Retention</div>
                <div class="stat-value"><?= $analytics['retention_30_day'] ?>%</div>
            </div>
            <?php endif; ?>

            <div class="stat-card">
                <div class="stat-label">Avg Members/Week</div>
                <div class="stat-value"><?= $analytics['overview']['avg_members_per_week'] ?></div>
            </div>
        </div>

        <!-- Member Growth Chart -->
        <div class="chart-section">
            <h2>Member Growth (Last 90 Days)</h2>
            <div class="chart-container">
                <canvas id="memberGrowthChart"></canvas>
            </div>
        </div>

        <!-- Profile Views Chart -->
        <?php if ($analytics['profile_views'] > 0): ?>
        <div class="chart-section">
            <h2>Profile Views (Last 30 Days)</h2>
            <div class="stat-value" style="margin-bottom: 16px;">
                <?= number_format($analytics['profile_views']) ?>
            </div>
            <div class="chart-container">
                <canvas id="viewTrendChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Activity Distribution -->
        <div class="chart-section">
            <h2>Member Activity Distribution</h2>
            <div class="chart-container">
                <canvas id="activityDistributionChart"></canvas>
            </div>
            <div style="margin-top: 20px; font-size: 14px; color: #6b7280;">
                <p><span class="activity-badge very-active">Very Active</span> 10+ contributions in 30 days</p>
                <p><span class="activity-badge active">Active</span> 3-9 contributions in 30 days</p>
                <p><span class="activity-badge moderate">Moderate</span> 1-2 contributions in 30 days</p>
                <p><span class="activity-badge inactive">Inactive</span> 0 contributions in 30 days</p>
            </div>
        </div>

        <!-- Most Active Members -->
        <?php if (!empty($analytics['most_active_members'])): ?>
        <div class="chart-section">
            <h2>Most Active Members</h2>
            <ul class="member-list">
                <?php foreach ($analytics['most_active_members'] as $member): ?>
                <li class="member-item">
                    <img src="<?= htmlspecialchars($member['profile_image_url'] ?? '/assets/img/defaults/default_avatar.png') ?>"
                         alt="<?= htmlspecialchars($member['name']) ?>"
                         class="member-avatar">
                    <div class="member-info">
                        <div class="member-name"><?= htmlspecialchars($member['name']) ?></div>
                        <div class="member-stats">
                            <?php
                            $totalContributions = ($member['post_count'] ?? 0) +
                                                 ($member['comment_count'] ?? 0) +
                                                 ($member['discussion_message_count'] ?? 0);
                            ?>
                            <?= $totalContributions ?> contributions
                            (<?= $member['post_count'] ?? 0 ?> posts,
                             <?= $member['comment_count'] ?? 0 ?> comments,
                             <?= $member['discussion_message_count'] ?? 0 ?> messages)
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Top Posts -->
        <?php if (!empty($analytics['top_posts'])): ?>
        <div class="chart-section">
            <h2>Top Posts (Last 30 Days)</h2>
            <div class="content-list">
                <?php foreach ($analytics['top_posts'] as $post): ?>
                <div class="content-item">
                    <div class="content-title">
                        <?= htmlspecialchars(substr($post['content'], 0, 100)) ?>
                        <?= strlen($post['content']) > 100 ? '...' : '' ?>
                    </div>
                    <div class="content-meta">
                        By <?= htmlspecialchars($post['author_name']) ?> •
                        <?= $post['reaction_count'] ?> reactions •
                        <?= $post['comment_count'] ?> comments •
                        <?= date('M j', strtotime($post['created_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top Discussions -->
        <?php if (!empty($analytics['top_discussions'])): ?>
        <div class="chart-section">
            <h2>Top Discussions (Last 30 Days)</h2>
            <div class="content-list">
                <?php foreach ($analytics['top_discussions'] as $discussion): ?>
                <div class="content-item">
                    <div class="content-title"><?= htmlspecialchars($discussion['title']) ?></div>
                    <div class="content-meta">
                        By <?= htmlspecialchars($discussion['author_name']) ?> •
                        <?= $discussion['message_count'] ?> messages •
                        <?= date('M j', strtotime($discussion['created_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Empty States -->
        <?php if (empty($analytics['top_posts']) && empty($analytics['top_discussions'])): ?>
        <div class="chart-section">
            <div class="empty-state">
                <div class="empty-state-icon">📊</div>
                <p>No content activity in the last 30 days.</p>
                <p style="font-size: 14px;">Encourage members to start discussions and share posts!</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Member Growth Chart
        const growthCtx = document.getElementById('memberGrowthChart').getContext('2d');
        const growthData = <?= json_encode($analytics['member_growth']) ?>;

        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: growthData.map(d => d.date),
                datasets: [{
                    label: 'New Members',
                    data: growthData.map(d => d.count),
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        <?php if ($analytics['profile_views'] > 0 && !empty($analytics['view_trend'])): ?>
        // View Trend Chart
        const viewCtx = document.getElementById('viewTrendChart').getContext('2d');
        const viewData = <?= json_encode($analytics['view_trend']) ?>;

        new Chart(viewCtx, {
            type: 'bar',
            data: {
                labels: viewData.map(d => d.date),
                datasets: [{
                    label: 'Views',
                    data: viewData.map(d => d.count),
                    backgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Activity Distribution Chart
        const activityCtx = document.getElementById('activityDistributionChart').getContext('2d');
        const activityData = <?= json_encode($analytics['activity_distribution']) ?>;

        new Chart(activityCtx, {
            type: 'doughnut',
            data: {
                labels: ['Very Active', 'Active', 'Moderate', 'Inactive'],
                datasets: [{
                    data: [
                        activityData.very_active,
                        activityData.active,
                        activityData.moderate,
                        activityData.inactive
                    ],
                    backgroundColor: [
                        '#10b981',
                        '#3b82f6',
                        '#f59e0b',
                        '#9ca3af'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
