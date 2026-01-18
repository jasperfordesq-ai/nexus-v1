<?php
$hTitle = 'Achievement Analytics';
$hSubtitle = 'Insights into your community\'s gamification engagement';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Admin Console';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/modern/header.php';

$overview = $data['overview'] ?? [];
$badgeTrends = $data['badge_trends'] ?? [];
$popularBadges = $data['popular_badges'] ?? [];
$rarestBadges = $data['rarest_badges'] ?? [];
$topEarners = $data['top_earners'] ?? [];
$recentActivity = $data['recent_activity'] ?? [];
?>

<style>
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    height: 100%;
}
.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #1e1e2e;
}
.stat-label {
    color: #6b7280;
    font-size: 14px;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.progress-thin {
    height: 6px;
    border-radius: 3px;
}
.activity-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-icon {
    font-size: 24px;
    margin-right: 12px;
}
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0"><i class="fa-solid fa-chart-line text-primary"></i> Achievement Analytics</h1>
            <p class="text-muted">Insights into your community's gamification engagement</p>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= number_format($overview['total_xp'] ?? 0) ?></div>
                        <div class="stat-label">Total XP Earned</div>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= number_format($overview['total_badges'] ?? 0) ?></div>
                        <div class="stat-label">Badges Awarded</div>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-medal"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= $overview['engagement_rate'] ?? 0 ?>%</div>
                        <div class="stat-label">Engagement Rate</div>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= number_format($overview['avg_xp'] ?? 0) ?></div>
                        <div class="stat-label">Avg XP per User</div>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-chart-bar"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Badge Trends Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-chart-area"></i> Badge Earning Trends (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="badgeTrendsChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Level Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-layer-group"></i> Level Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="levelChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Popular Badges -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-fire"></i> Most Popular Badges</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($popularBadges as $badge): ?>
                    <div class="d-flex align-items-center mb-3">
                        <span style="font-size: 28px; margin-right: 12px;"><?= $badge['icon'] ?? '🏆' ?></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= htmlspecialchars($badge['name'] ?? $badge['badge_key']) ?></div>
                            <div class="text-muted small"><?= number_format($badge['award_count']) ?> earned</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Rarest Badges -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-gem"></i> Rarest Badges</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($rarestBadges as $badge): ?>
                    <div class="d-flex align-items-center mb-3">
                        <span style="font-size: 28px; margin-right: 12px;"><?= $badge['icon'] ?? '🏆' ?></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= htmlspecialchars($badge['name'] ?? $badge['badge_key']) ?></div>
                            <div class="text-muted small"><?= $badge['rarity_percent'] ?>% of users</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top Earners -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-trophy"></i> Top XP Earners</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($topEarners as $i => $user): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-2 fw-bold text-muted" style="width: 20px;"><?= $i + 1 ?></div>
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" class="rounded-circle me-2" width="32" height="32">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                <?= strtoupper(substr($user['first_name'] ?? '?', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></div>
                            <div class="text-muted small">Level <?= $user['level'] ?? 1 ?> • <?= number_format($user['xp'] ?? 0) ?> XP</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-clock-rotate-left"></i> Recent Badge Activity</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Badge</th>
                                    <th>Earned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($activity['avatar_url'])): ?>
                                                <img src="<?= htmlspecialchars($activity['avatar_url']) ?>" class="rounded-circle me-2" width="32" height="32">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <?= strtoupper(substr($activity['first_name'] ?? '?', 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?= htmlspecialchars(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 20px;"><?= $activity['badge_icon'] ?? '🏆' ?></span>
                                        <?= htmlspecialchars($activity['badge_name'] ?? $activity['badge_key'] ?? 'Unknown') ?>
                                    </td>
                                    <td class="text-muted">
                                        <?= date('M j, Y g:i A', strtotime($activity['awarded_at'] ?? 'now')) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Badge Trends Chart
const trendsData = <?= json_encode($badgeTrends) ?>;
new Chart(document.getElementById('badgeTrendsChart'), {
    type: 'line',
    data: {
        labels: trendsData.map(d => d.date),
        datasets: [{
            label: 'Badges Earned',
            data: trendsData.map(d => d.count),
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Level Distribution Chart
const levelData = <?= json_encode($overview['level_distribution'] ?? []) ?>;
new Chart(document.getElementById('levelChart'), {
    type: 'doughnut',
    data: {
        labels: levelData.map(d => 'Level ' + d.level),
        datasets: [{
            data: levelData.map(d => d.count),
            backgroundColor: [
                '#4f46e5', '#7c3aed', '#a855f7', '#d946ef',
                '#ec4899', '#f43f5e', '#f97316', '#eab308',
                '#84cc16', '#22c55e', '#14b8a6', '#06b6d4'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
