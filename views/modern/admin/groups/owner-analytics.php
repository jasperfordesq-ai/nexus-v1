<?php
/**
 * Admin Groups - Owner Analytics (Gold Standard)
 * Accessed via /admin/groups/view?id=X → Analytics Tab
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Group Analytics';
$adminPageSubtitle = 'Detailed analytics for ' . ($group['name'] ?? 'Group');
$adminPageIcon = 'fa-chart-line';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

// Safe defaults
$analytics = $analytics ?? [];
$group = $group ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            <?= htmlspecialchars($group['name'] ?? 'Group Analytics') ?>
        </h1>
        <p class="admin-page-subtitle">Comprehensive analytics dashboard</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/groups/view?id=<?= $group['id'] ?>" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Group
        </a>
        <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="admin-btn admin-btn-secondary" target="_blank">
            <i class="fa-solid fa-external-link"></i>
            View Public Page
        </a>
        <button class="admin-btn admin-btn-primary" onclick="window.print()">
            <i class="fa-solid fa-print"></i>
            Export Report
        </button>
    </div>
</div>

<!-- Overview Stats -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['overview']['total_members'] ?? 0) ?></div>
            <div class="admin-stat-label">Total Members</div>
        </div>
        <div class="admin-stat-trend">
            <span class="trend-up">+<?= $analytics['new_this_week'] ?? 0 ?> this week</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-user-clock"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['overview']['pending_requests'] ?? 0) ?></div>
            <div class="admin-stat-label">Pending Requests</div>
        </div>
        <div class="admin-stat-trend">
            <span>Awaiting approval</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-comments"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['overview']['total_discussions'] ?? 0) ?></div>
            <div class="admin-stat-label">Discussions</div>
        </div>
        <div class="admin-stat-trend">
            <span><?= $analytics['discussion_stats']['this_week'] ?? 0 ?> this week</span>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-newspaper"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['overview']['total_posts'] ?? 0) ?></div>
            <div class="admin-stat-label">Posts</div>
        </div>
        <div class="admin-stat-trend">
            <span><?= $analytics['post_stats']['this_week'] ?? 0 ?> this week</span>
        </div>
    </div>

    <?php if (isset($analytics['retention_30_day']) && $analytics['retention_30_day'] !== null): ?>
    <div class="admin-stat-card admin-stat-cyan">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $analytics['retention_30_day'] ?>%</div>
            <div class="admin-stat-label">30-Day Retention</div>
        </div>
        <div class="admin-stat-trend">
            <span>Member retention</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="admin-stat-card admin-stat-gray">
        <div class="admin-stat-icon">
            <i class="fa-solid fa-chart-simple"></i>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $analytics['overview']['avg_members_per_week'] ?? 0 ?></div>
            <div class="admin-stat-label">Avg Members/Week</div>
        </div>
        <div class="admin-stat-trend">
            <span>8-week average</span>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="admin-tabs">
    <button class="admin-tab admin-tab-active" data-tab="growth">
        <i class="fa-solid fa-chart-line"></i>
        Growth
    </button>
    <button class="admin-tab" data-tab="engagement">
        <i class="fa-solid fa-fire"></i>
        Engagement
    </button>
    <button class="admin-tab" data-tab="content">
        <i class="fa-solid fa-star"></i>
        Top Content
    </button>
    <button class="admin-tab" data-tab="members">
        <i class="fa-solid fa-users"></i>
        Members
    </button>
</div>

<!-- Tab: Growth -->
<div class="admin-tab-content admin-tab-content-active" data-tab-content="growth">
    <div class="admin-content-box">
        <div class="admin-content-box-header">
            <h2 class="admin-content-box-title">
                <i class="fa-solid fa-chart-line"></i>
                Member Growth (Last 90 Days)
            </h2>
            <div class="admin-content-box-actions">
                <select class="admin-select" id="growthPeriod" onchange="updateGrowthChart(this.value)">
                    <option value="90">90 Days</option>
                    <option value="60">60 Days</option>
                    <option value="30">30 Days</option>
                    <option value="7">7 Days</option>
                </select>
            </div>
        </div>
        <div class="admin-content-box-body">
            <div style="height: 300px; position: relative;">
                <canvas id="memberGrowthChart"></canvas>
            </div>
        </div>
    </div>

    <?php if (($analytics['profile_views'] ?? 0) > 0): ?>
    <div class="admin-content-box">
        <div class="admin-content-box-header">
            <h2 class="admin-content-box-title">
                <i class="fa-solid fa-eye"></i>
                Profile Views (Last 30 Days)
            </h2>
            <div class="admin-content-box-actions">
                <span class="admin-badge admin-badge-blue">
                    <?= number_format($analytics['profile_views']) ?> total views
                </span>
            </div>
        </div>
        <div class="admin-content-box-body">
            <div style="height: 250px; position: relative;">
                <canvas id="viewTrendChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tab: Engagement -->
<div class="admin-tab-content" data-tab-content="engagement">
    <div class="admin-content-box">
        <div class="admin-content-box-header">
            <h2 class="admin-content-box-title">
                <i class="fa-solid fa-chart-pie"></i>
                Activity Distribution
            </h2>
            <p class="admin-content-box-description">Member engagement levels over the last 30 days</p>
        </div>
        <div class="admin-content-box-body">
            <div class="admin-grid-2col">
                <div style="height: 300px; position: relative;">
                    <canvas id="activityDistributionChart"></canvas>
                </div>
                <div class="admin-legend-list">
                    <div class="admin-legend-item">
                        <span class="admin-legend-color" style="background: #10b981;"></span>
                        <div class="admin-legend-content">
                            <div class="admin-legend-label">Very Active</div>
                            <div class="admin-legend-value"><?= $analytics['activity_distribution']['very_active'] ?? 0 ?> members</div>
                            <div class="admin-legend-description">10+ contributions in 30 days</div>
                        </div>
                    </div>
                    <div class="admin-legend-item">
                        <span class="admin-legend-color" style="background: #3b82f6;"></span>
                        <div class="admin-legend-content">
                            <div class="admin-legend-label">Active</div>
                            <div class="admin-legend-value"><?= $analytics['activity_distribution']['active'] ?? 0 ?> members</div>
                            <div class="admin-legend-description">3-9 contributions in 30 days</div>
                        </div>
                    </div>
                    <div class="admin-legend-item">
                        <span class="admin-legend-color" style="background: #f59e0b;"></span>
                        <div class="admin-legend-content">
                            <div class="admin-legend-label">Moderate</div>
                            <div class="admin-legend-value"><?= $analytics['activity_distribution']['moderate'] ?? 0 ?> members</div>
                            <div class="admin-legend-description">1-2 contributions in 30 days</div>
                        </div>
                    </div>
                    <div class="admin-legend-item">
                        <span class="admin-legend-color" style="background: #9ca3af;"></span>
                        <div class="admin-legend-content">
                            <div class="admin-legend-label">Inactive</div>
                            <div class="admin-legend-value"><?= $analytics['activity_distribution']['inactive'] ?? 0 ?> members</div>
                            <div class="admin-legend-description">0 contributions in 30 days</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-grid-2col">
        <div class="admin-content-box">
            <div class="admin-content-box-header">
                <h2 class="admin-content-box-title">
                    <i class="fa-solid fa-comments"></i>
                    Discussion Stats
                </h2>
            </div>
            <div class="admin-content-box-body">
                <div class="admin-stat-list">
                    <div class="admin-stat-list-item">
                        <span class="admin-stat-list-label">Total Discussions</span>
                        <span class="admin-stat-list-value"><?= number_format($analytics['discussion_stats']['total'] ?? 0) ?></span>
                    </div>
                    <div class="admin-stat-list-item">
                        <span class="admin-stat-list-label">This Week</span>
                        <span class="admin-stat-list-value"><?= number_format($analytics['discussion_stats']['this_week'] ?? 0) ?></span>
                    </div>
                    <div class="admin-stat-list-item">
                        <span class="admin-stat-list-label">Avg Messages per Discussion</span>
                        <span class="admin-stat-list-value"><?= $analytics['discussion_stats']['avg_messages_per_discussion'] ?? 0 ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-content-box">
            <div class="admin-content-box-header">
                <h2 class="admin-content-box-title">
                    <i class="fa-solid fa-newspaper"></i>
                    Post Stats
                </h2>
            </div>
            <div class="admin-content-box-body">
                <div class="admin-stat-list">
                    <div class="admin-stat-list-item">
                        <span class="admin-stat-list-label">Total Posts</span>
                        <span class="admin-stat-list-value"><?= number_format($analytics['post_stats']['total'] ?? 0) ?></span>
                    </div>
                    <div class="admin-stat-list-item">
                        <span class="admin-stat-list-label">This Week</span>
                        <span class="admin-stat-list-value"><?= number_format($analytics['post_stats']['this_week'] ?? 0) ?></span>
                    </div>
                    <div class="admin-stat-list-item">
                        <span class="admin-stat-list-label">Avg Comments per Post</span>
                        <span class="admin-stat-list-value"><?= $analytics['post_stats']['avg_comments_per_post'] ?? 0 ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab: Top Content -->
<div class="admin-tab-content" data-tab-content="content">
    <?php if (!empty($analytics['top_posts'])): ?>
    <div class="admin-content-box">
        <div class="admin-content-box-header">
            <h2 class="admin-content-box-title">
                <i class="fa-solid fa-star"></i>
                Top Posts (Last 30 Days)
            </h2>
            <p class="admin-content-box-description">Ranked by engagement (reactions + comments)</p>
        </div>
        <div class="admin-content-box-body">
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Post</th>
                            <th>Author</th>
                            <th>Reactions</th>
                            <th>Comments</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['top_posts'] as $post): ?>
                        <tr>
                            <td>
                                <div class="admin-table-cell-truncate" style="max-width: 400px;">
                                    <?= htmlspecialchars(substr($post['content'], 0, 100)) ?>
                                    <?= strlen($post['content']) > 100 ? '...' : '' ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($post['author_name']) ?></td>
                            <td>
                                <span class="admin-badge admin-badge-blue">
                                    <?= $post['reaction_count'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-purple">
                                    <?= $post['comment_count'] ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($post['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($analytics['top_discussions'])): ?>
    <div class="admin-content-box">
        <div class="admin-content-box-header">
            <h2 class="admin-content-box-title">
                <i class="fa-solid fa-comments"></i>
                Top Discussions (Last 30 Days)
            </h2>
            <p class="admin-content-box-description">Ranked by message count</p>
        </div>
        <div class="admin-content-box-body">
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Discussion</th>
                            <th>Author</th>
                            <th>Messages</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['top_discussions'] as $discussion): ?>
                        <tr>
                            <td>
                                <div class="admin-table-cell-truncate" style="max-width: 400px;">
                                    <?= htmlspecialchars($discussion['title']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($discussion['author_name']) ?></td>
                            <td>
                                <span class="admin-badge admin-badge-green">
                                    <?= $discussion['message_count'] ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($discussion['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($analytics['top_posts']) && empty($analytics['top_discussions'])): ?>
    <div class="admin-empty-state">
        <div class="admin-empty-state-icon">
            <i class="fa-solid fa-chart-bar"></i>
        </div>
        <h3 class="admin-empty-state-title">No Recent Content Activity</h3>
        <p class="admin-empty-state-description">
            No posts or discussions created in the last 30 days.
        </p>
    </div>
    <?php endif; ?>
</div>

<!-- Tab: Members -->
<div class="admin-tab-content" data-tab-content="members">
    <?php if (!empty($analytics['most_active_members'])): ?>
    <div class="admin-content-box">
        <div class="admin-content-box-header">
            <h2 class="admin-content-box-title">
                <i class="fa-solid fa-trophy"></i>
                Most Active Members
            </h2>
            <p class="admin-content-box-description">Top contributors based on posts, comments, and discussions</p>
        </div>
        <div class="admin-content-box-body">
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Posts</th>
                            <th>Comments</th>
                            <th>Messages</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['most_active_members'] as $member): ?>
                        <?php
                        $totalContributions = ($member['post_count'] ?? 0) +
                                             ($member['comment_count'] ?? 0) +
                                             ($member['discussion_message_count'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <div class="admin-table-user">
                                    <img src="<?= htmlspecialchars($member['profile_image_url'] ?? '/assets/img/defaults/default_avatar.png') ?>" loading="lazy"
                                         alt="<?= htmlspecialchars($member['name']) ?>"
                                         class="admin-table-avatar">
                                    <span><?= htmlspecialchars($member['name']) ?></span>
                                </div>
                            </td>
                            <td><span class="admin-badge admin-badge-blue"><?= $member['post_count'] ?? 0 ?></span></td>
                            <td><span class="admin-badge admin-badge-purple"><?= $member['comment_count'] ?? 0 ?></span></td>
                            <td><span class="admin-badge admin-badge-green"><?= $member['discussion_message_count'] ?? 0 ?></span></td>
                            <td><span class="admin-badge admin-badge-orange"><?= $totalContributions ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="admin-empty-state">
        <div class="admin-empty-state-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <h3 class="admin-empty-state-title">No Active Members</h3>
        <p class="admin-empty-state-description">
            No member activity detected in the last 30 days.
        </p>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// Tab Switching
document.querySelectorAll('.admin-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        // Remove active class from all tabs
        document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('admin-tab-active'));
        document.querySelectorAll('.admin-tab-content').forEach(c => c.classList.remove('admin-tab-content-active'));

        // Add active class to clicked tab
        this.classList.add('admin-tab-active');
        const tabName = this.getAttribute('data-tab');
        document.querySelector(`[data-tab-content="${tabName}"]`).classList.add('admin-tab-content-active');
    });
});

// Member Growth Chart
const growthCtx = document.getElementById('memberGrowthChart').getContext('2d');
const growthData = <?= json_encode($analytics['member_growth'] ?? []) ?>;

const memberGrowthChart = new Chart(growthCtx, {
    type: 'line',
    data: {
        labels: growthData.map(d => d.date),
        datasets: [{
            label: 'New Members',
            data: growthData.map(d => d.count),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            },
            x: {
                ticks: {
                    maxTicksLimit: 12
                }
            }
        }
    }
});

<?php if (($analytics['profile_views'] ?? 0) > 0 && !empty($analytics['view_trend'])): ?>
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
            backgroundColor: '#10b981',
            borderRadius: 4
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
const activityData = <?= json_encode($analytics['activity_distribution'] ?? []) ?>;

new Chart(activityCtx, {
    type: 'doughnut',
    data: {
        labels: ['Very Active', 'Active', 'Moderate', 'Inactive'],
        datasets: [{
            data: [
                activityData.very_active || 0,
                activityData.active || 0,
                activityData.moderate || 0,
                activityData.inactive || 0
            ],
            backgroundColor: [
                '#10b981',
                '#3b82f6',
                '#f59e0b',
                '#9ca3af'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Update growth chart period
function updateGrowthChart(days) {
    // TODO: Implement AJAX call to reload chart with different period
    console.log('Update chart for', days, 'days');
}
</script>

<?php
// Include admin footer
require dirname(__DIR__) . '/partials/admin-footer.php';
?>
