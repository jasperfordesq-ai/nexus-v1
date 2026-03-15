<?php
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Deliverability Analytics';
$adminPageSubtitle = 'Performance & Insights';
$adminPageIcon = 'fa-chart-line';

require dirname(dirname(dirname(__DIR__))) . '/layouts/admin-header.php';

$analytics = $analytics ?? [];
$report = $report ?? [];
$filters = $filters ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Deliverability Analytics
        </h1>
        <p class="admin-page-subtitle">Performance metrics and insights</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/deliverability" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="<?= $basePath ?>/admin-legacy/deliverability/list" class="admin-btn admin-btn-info">
            <i class="fa-solid fa-list"></i> All Deliverables
        </a>
    </div>
</div>

<!-- Overview Stats Grid -->
<div class="admin-stats-grid" style="margin-bottom: 30px;">
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon"><i class="fa-solid fa-tasks"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['overview']['total'] ?? 0) ?></div>
            <div class="admin-stat-label">Total Deliverables</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['completion_rate'] ?? 0, 1) ?>%</div>
            <div class="admin-stat-label">Completion Rate</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-cyan">
        <div class="admin-stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['on_time_rate'] ?? 0, 1) ?>%</div>
            <div class="admin-stat-label">On-Time Delivery</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon"><i class="fa-solid fa-gauge-high"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['overview']['avg_progress'] ?? 0, 0) ?>%</div>
            <div class="admin-stat-label">Average Progress</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-spinner"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['overview']['in_progress'] ?? 0) ?></div>
            <div class="admin-stat-label">In Progress</div>
        </div>
    </div>

    <div class="admin-stat-card admin-stat-pink">
        <div class="admin-stat-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($analytics['overview']['blocked'] ?? 0) ?></div>
            <div class="admin-stat-label">Blocked</div>
        </div>
    </div>
</div>

<!-- Two Column Grid -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 30px;">

    <!-- Left Column: Charts -->
    <div>
        <!-- Priority Breakdown Chart - Enhanced -->
        <div class="admin-glass-card" style="margin-bottom: 24px; border: 1px solid rgba(139, 92, 246, 0.25); box-shadow: 0 4px 20px rgba(139, 92, 246, 0.15);">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #8b5cf6, #a855f7); box-shadow: 0 4px 14px rgba(139, 92, 246, 0.4);">
                    <i class="fa-solid fa-flag"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title" style="font-size: 1.25rem; letter-spacing: -0.02em;">Priority Distribution</h3>
                    <p class="admin-card-subtitle">Active deliverables by priority level</p>
                </div>
            </div>
            <div class="admin-card-body">
                <canvas id="priorityChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Status Breakdown Chart - Enhanced -->
        <div class="admin-glass-card" style="border: 1px solid rgba(6, 182, 212, 0.25); box-shadow: 0 4px 20px rgba(6, 182, 212, 0.15);">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee); box-shadow: 0 4px 14px rgba(6, 182, 212, 0.4);">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title" style="font-size: 1.25rem; letter-spacing: -0.02em;">Status Distribution</h3>
                    <p class="admin-card-subtitle">All deliverables by current status</p>
                </div>
            </div>
            <div class="admin-card-body">
                <canvas id="statusChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Right Column: Lists & Metrics -->
    <div>
        <!-- Risk Distribution -->
        <div class="admin-glass-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-orange">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Risk Levels</h3>
                </div>
            </div>
            <div class="admin-card-body">
                <?php
                $riskDist = $analytics['risk_distribution'] ?? ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
                $riskColors = ['low' => '#10b981', 'medium' => '#f59e0b', 'high' => '#ef4444', 'critical' => '#dc2626'];
                ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($riskDist as $level => $count): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 12px; height: 12px; border-radius: 50%; background: <?= $riskColors[$level] ?>;"></div>
                            <span style="text-transform: capitalize;"><?= $level ?></span>
                        </div>
                        <span style="font-weight: 700; color: <?= $riskColors[$level] ?>;"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Velocity Metrics -->
        <div class="admin-glass-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-green">
                    <i class="fa-solid fa-rocket"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Velocity</h3>
                    <p class="admin-card-subtitle">Delivery speed</p>
                </div>
            </div>
            <div class="admin-card-body">
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 28px; font-weight: 700; color: #06b6d4; margin-bottom: 4px;">
                        <?= number_format($analytics['trending_metrics']['weekly_velocity'] ?? 0) ?>
                    </div>
                    <div style="font-size: 13px; color: rgba(255,255,255,0.6);">Completed this week</div>
                </div>
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 28px; font-weight: 700; color: #8b5cf6; margin-bottom: 4px;">
                        <?= number_format($analytics['trending_metrics']['monthly_velocity'] ?? 0) ?>
                    </div>
                    <div style="font-size: 13px; color: rgba(255,255,255,0.6);">Completed this month</div>
                </div>
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: #10b981; margin-bottom: 4px;">
                        <?= number_format($analytics['trending_metrics']['average_completion_time'] ?? 0, 1) ?> days
                    </div>
                    <div style="font-size: 13px; color: rgba(255,255,255,0.6);">Average completion time</div>
                </div>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-indigo">
                    <i class="fa-solid fa-folder"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Categories</h3>
                </div>
            </div>
            <div class="admin-card-body">
                <?php
                $catDist = $analytics['category_distribution'] ?? [];
                if (empty($catDist)):
                ?>
                <p style="text-align: center; color: rgba(255,255,255,0.5); margin: 0;">No categories yet</p>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach (array_slice($catDist, 0, 8) as $category => $count): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 13px; text-transform: capitalize;"><?= htmlspecialchars($category) ?></span>
                        <span style="font-weight: 600; color: #6366f1;"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Blocked & Overdue Items -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;">

    <!-- Blocked Deliverables -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-orange">
                <i class="fa-solid fa-ban"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Blocked Deliverables</h3>
                <p class="admin-card-subtitle"><?= count($analytics['blocked_deliverables'] ?? []) ?> items</p>
            </div>
        </div>
        <div class="admin-card-body">
            <?php if (empty($analytics['blocked_deliverables'])): ?>
            <p style="text-align: center; color: rgba(255,255,255,0.5); margin: 0;">No blocked deliverables</p>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach (array_slice($analytics['blocked_deliverables'], 0, 5) as $item): ?>
                <div style="padding: 10px; background: rgba(255,255,255,0.03); border-radius: 6px; border-left: 3px solid #f59e0b;">
                    <a href="<?= $basePath ?>/admin-legacy/deliverability/view/<?= $item['id'] ?>"
                       style="color: #fff; text-decoration: none; font-weight: 500;">
                        <?= htmlspecialchars($item['title']) ?>
                    </a>
                    <div style="font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 4px;">
                        <?= htmlspecialchars($item['category'] ?? 'general') ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Deadlines -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon admin-card-header-icon-purple">
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Upcoming Deadlines</h3>
                <p class="admin-card-subtitle">Next 7 days</p>
            </div>
        </div>
        <div class="admin-card-body">
            <?php if (empty($analytics['upcoming_deadlines'])): ?>
            <p style="text-align: center; color: rgba(255,255,255,0.5); margin: 0;">No upcoming deadlines</p>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach (array_slice($analytics['upcoming_deadlines'], 0, 5) as $item): ?>
                <div style="padding: 10px; background: rgba(255,255,255,0.03); border-radius: 6px; border-left: 3px solid #06b6d4;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <a href="<?= $basePath ?>/admin-legacy/deliverability/view/<?= $item['id'] ?>"
                           style="color: #fff; text-decoration: none; font-weight: 500; flex: 1;">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                        <span style="font-size: 11px; color: #06b6d4; white-space: nowrap; margin-left: 8px;">
                            <?= date('M j', strtotime($item['due_date'])) ?>
                        </span>
                    </div>
                    <div style="font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 4px;">
                        <?= number_format($item['progress_percentage'] ?? 0, 0) ?>% complete
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js Integration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Priority Distribution Chart
const priorityCtx = document.getElementById('priorityChart').getContext('2d');
new Chart(priorityCtx, {
    type: 'doughnut',
    data: {
        labels: ['Urgent', 'High', 'Medium', 'Low'],
        datasets: [{
            data: [
                <?= $analytics['priority_breakdown']['urgent'] ?? 0 ?>,
                <?= $analytics['priority_breakdown']['high'] ?? 0 ?>,
                <?= $analytics['priority_breakdown']['medium'] ?? 0 ?>,
                <?= $analytics['priority_breakdown']['low'] ?? 0 ?>
            ],
            backgroundColor: ['#ef4444', '#f59e0b', '#06b6d4', '#10b981'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: '#fff', padding: 15, font: { size: 12 } }
            }
        }
    }
});

// Status Distribution Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'bar',
    data: {
        labels: ['Draft', 'Ready', 'In Progress', 'Blocked', 'Review', 'Completed'],
        datasets: [{
            label: 'Deliverables',
            data: [
                <?= $analytics['overview']['draft'] ?? 0 ?>,
                0, // ready not in overview
                <?= $analytics['overview']['in_progress'] ?? 0 ?>,
                <?= $analytics['overview']['blocked'] ?? 0 ?>,
                <?= $analytics['overview']['in_review'] ?? 0 ?>,
                <?= $analytics['overview']['completed'] ?? 0 ?>
            ],
            backgroundColor: ['#64748b', '#06b6d4', '#f59e0b', '#ef4444', '#8b5cf6', '#10b981'],
            borderWidth: 0,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#fff', precision: 0 },
                grid: { color: 'rgba(255,255,255,0.1)' }
            },
            x: {
                ticks: { color: '#fff' },
                grid: { display: false }
            }
        },
        plugins: {
            legend: { display: false }
        }
    }
});
</script>

<?php require dirname(dirname(dirname(__DIR__))) . '/layouts/admin-footer.php'; ?>
