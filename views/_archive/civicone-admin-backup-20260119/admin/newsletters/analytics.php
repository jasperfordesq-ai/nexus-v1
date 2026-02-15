<?php
/**
 * Admin Newsletter Analytics - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

$totals = $totals ?? ['newsletters_sent' => 0, 'total_sent' => 0, 'unique_opens' => 0, 'total_opens' => 0, 'unique_clicks' => 0, 'total_clicks' => 0, 'total_failed' => 0];
$avgOpenRate = $avgOpenRate ?? 0;
$avgClickRate = $avgClickRate ?? 0;
$monthlyStats = $monthlyStats ?? [];
$topPerformers = $topPerformers ?? [];

// Admin header configuration
$adminPageTitle = 'Analytics';
$adminPageSubtitle = 'Newsletters';
$adminPageIcon = 'fa-chart-line';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-chart-line"></i>
            Newsletter Analytics
        </h1>
        <p class="admin-page-subtitle">Performance metrics and insights across all campaigns</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/newsletters" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/send-time" class="admin-btn admin-btn-success">
            <i class="fa-solid fa-clock"></i>
            Send Time Optimization
        </a>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/bounces" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-shield-halved"></i>
            Bounces
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card admin-stat-blue">
        <div class="admin-stat-icon"><i class="fa-solid fa-paper-plane"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totals['newsletters_sent']) ?></div>
            <div class="admin-stat-label">Campaigns Sent</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-green">
        <div class="admin-stat-icon"><i class="fa-solid fa-envelope-circle-check"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($totals['total_sent']) ?></div>
            <div class="admin-stat-label">Emails Delivered</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-purple">
        <div class="admin-stat-icon"><i class="fa-solid fa-envelope-open"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $avgOpenRate ?>%</div>
            <div class="admin-stat-label">Avg. Open Rate</div>
        </div>
    </div>
    <div class="admin-stat-card admin-stat-orange">
        <div class="admin-stat-icon"><i class="fa-solid fa-mouse-pointer"></i></div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= $avgClickRate ?>%</div>
            <div class="admin-stat-label">Avg. Click Rate</div>
        </div>
    </div>
</div>

<!-- Engagement Summary Card -->
<div class="admin-glass-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-indigo">
            <i class="fa-solid fa-chart-pie"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Engagement Summary</h3>
            <p class="admin-card-subtitle">Aggregate metrics across all campaigns</p>
        </div>
    </div>
    <div class="admin-card-body">
        <div class="engagement-grid">
            <div class="engagement-stat">
                <div class="engagement-value" style="color: #818cf8;"><?= number_format($totals['unique_opens']) ?></div>
                <div class="engagement-label">Unique Opens</div>
            </div>
            <div class="engagement-stat">
                <div class="engagement-value" style="color: #818cf8;"><?= number_format($totals['total_opens']) ?></div>
                <div class="engagement-label">Total Opens</div>
            </div>
            <div class="engagement-stat">
                <div class="engagement-value" style="color: #22c55e;"><?= number_format($totals['unique_clicks']) ?></div>
                <div class="engagement-label">Unique Clicks</div>
            </div>
            <div class="engagement-stat">
                <div class="engagement-value" style="color: #22c55e;"><?= number_format($totals['total_clicks']) ?></div>
                <div class="engagement-label">Total Clicks</div>
            </div>
            <?php if ($totals['total_failed'] > 0): ?>
            <div class="engagement-stat">
                <div class="engagement-value" style="color: #ef4444;"><?= number_format($totals['total_failed']) ?></div>
                <div class="engagement-label">Failed</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($monthlyStats)): ?>
<!-- Monthly Performance Chart -->
<div class="admin-glass-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-chart-bar"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Monthly Performance</h3>
            <p class="admin-card-subtitle">Email volume over time</p>
        </div>
    </div>
    <div class="admin-card-body">
        <?php
        $maxSent = max(array_column($monthlyStats, 'sent'));
        $chartHeight = 180;
        ?>

        <div class="chart-container" style="height: <?= $chartHeight + 60 ?>px;">
            <!-- Y-axis labels -->
            <div class="chart-y-axis" style="height: <?= $chartHeight ?>px;">
                <span><?= number_format($maxSent) ?></span>
                <span><?= number_format($maxSent / 2) ?></span>
                <span>0</span>
            </div>

            <!-- Chart area -->
            <div class="chart-bars" style="height: <?= $chartHeight ?>px;">
                <?php foreach ($monthlyStats as $month):
                    $barHeight = $maxSent > 0 ? ($month['sent'] / $maxSent) * $chartHeight : 0;
                    $openRate = $month['sent'] > 0 ? round(($month['opens'] / $month['sent']) * 100, 1) : 0;
                    $clickRate = $month['sent'] > 0 ? round(($month['clicks'] / $month['sent']) * 100, 1) : 0;
                ?>
                    <div class="chart-bar-wrapper">
                        <div class="chart-bar" style="height: <?= $barHeight ?>px;"
                             title="<?= date('F Y', strtotime($month['month'] . '-01')) ?>&#10;Sent: <?= number_format($month['sent']) ?>&#10;Opens: <?= $openRate ?>%&#10;Clicks: <?= $clickRate ?>%">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- X-axis labels -->
            <div class="chart-x-axis">
                <?php foreach ($monthlyStats as $month): ?>
                    <span><?= date('M \'y', strtotime($month['month'] . '-01')) ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Legend -->
        <div class="chart-legend">
            <span class="chart-legend-item">
                <span class="chart-legend-dot"></span> Emails Sent
            </span>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($topPerformers)): ?>
<!-- Top Performing Newsletters -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-amber">
            <i class="fa-solid fa-trophy"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Top Performing Newsletters</h3>
            <p class="admin-card-subtitle">Ranked by open rate (minimum 10 recipients)</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Subject</th>
                        <th class="hide-mobile" style="text-align: center;">Sent</th>
                        <th style="text-align: center;">Open Rate</th>
                        <th class="hide-tablet" style="text-align: center;">Click Rate</th>
                        <th class="hide-mobile" style="text-align: right;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topPerformers as $index => $newsletter): ?>
                    <tr>
                        <td>
                            <?php if ($index < 3): ?>
                                <span class="rank-badge rank-<?= $index + 1 ?>"><?= $index + 1 ?></span>
                            <?php else: ?>
                                <span class="rank-number"><?= $index + 1 ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= $basePath ?>/admin-legacy/newsletters/stats/<?= $newsletter['id'] ?>" class="newsletter-link">
                                <?= htmlspecialchars(strlen($newsletter['subject']) > 50 ? substr($newsletter['subject'], 0, 50) . '...' : $newsletter['subject']) ?>
                            </a>
                        </td>
                        <td class="hide-mobile" style="text-align: center;">
                            <span class="stat-value-muted"><?= number_format($newsletter['total_sent']) ?></span>
                        </td>
                        <td style="text-align: center;">
                            <span class="stat-value-primary"><?= $newsletter['open_rate'] ?>%</span>
                        </td>
                        <td class="hide-tablet" style="text-align: center;">
                            <span class="stat-value-success"><?= $newsletter['click_rate'] ?>%</span>
                        </td>
                        <td class="hide-mobile" style="text-align: right;">
                            <span class="date-value"><?= date('M j, Y', strtotime($newsletter['sent_at'])) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($topPerformers) && empty($monthlyStats)): ?>
<!-- No Data -->
<div class="admin-glass-card">
    <div class="admin-empty-state">
        <div class="admin-empty-icon">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <h3 class="admin-empty-title">No analytics data yet</h3>
        <p class="admin-empty-text">Send your first newsletter to start seeing performance metrics.</p>
        <a href="<?= $basePath ?>/admin-legacy/newsletters/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
            <i class="fa-solid fa-plus"></i>
            Create Newsletter
        </a>
    </div>
</div>
<?php endif; ?>

<style>
/* Stats Grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1024px) {
    .admin-stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .admin-stats-grid { grid-template-columns: 1fr 1fr; }
}

.admin-stat-card {
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
}

.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color), transparent);
}

.admin-stat-blue { --stat-color: #3b82f6; }
.admin-stat-green { --stat-color: #22c55e; }
.admin-stat-purple { --stat-color: #8b5cf6; }
.admin-stat-orange { --stat-color: #f59e0b; }

.admin-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: linear-gradient(135deg, var(--stat-color), color-mix(in srgb, var(--stat-color) 70%, #000));
    color: white;
}

.admin-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
}

.admin-stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

/* Card Header Icons */
.admin-card-header-icon-indigo {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.admin-card-header-icon-cyan {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
}

.admin-card-header-icon-amber {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

/* Engagement Grid */
.engagement-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1.5rem;
    text-align: center;
}

.engagement-stat {
    padding: 1rem;
}

.engagement-value {
    font-size: 1.75rem;
    font-weight: 800;
}

.engagement-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 0.25rem;
}

/* Chart Styles */
.chart-container {
    position: relative;
    margin-bottom: 1rem;
}

.chart-y-axis {
    position: absolute;
    left: 0;
    top: 0;
    width: 50px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    text-align: right;
    padding-right: 8px;
}

.chart-bars {
    position: absolute;
    left: 55px;
    right: 0;
    top: 0;
    border-left: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: flex-end;
    gap: 4px;
    padding: 0 10px;
}

.chart-bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.chart-bar {
    width: 100%;
    max-width: 60px;
    background: linear-gradient(to top, #6366f1, #8b5cf6);
    border-radius: 4px 4px 0 0;
    transition: all 0.3s ease;
    cursor: pointer;
}

.chart-bar:hover {
    background: linear-gradient(to top, #4f46e5, #7c3aed);
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.5);
}

.chart-x-axis {
    position: absolute;
    left: 55px;
    right: 0;
    bottom: 0;
    height: 50px;
    display: flex;
    justify-content: space-around;
    padding: 10px 10px 0;
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
}

.chart-x-axis span {
    flex: 1;
    text-align: center;
    white-space: nowrap;
}

.chart-legend {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

.chart-legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chart-legend-dot {
    width: 12px;
    height: 12px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 2px;
}

/* Rank Badges */
.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 0.85rem;
}

.rank-1 {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #78350f;
}

.rank-2 {
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    color: #475569;
}

.rank-3 {
    background: linear-gradient(135deg, #fed7aa, #fdba74);
    color: #9a3412;
}

.rank-number {
    color: rgba(255, 255, 255, 0.4);
    padding-left: 8px;
}

/* Newsletter Link */
.newsletter-link {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.newsletter-link:hover {
    color: #818cf8;
}

/* Stat Values */
.stat-value-muted {
    color: rgba(255, 255, 255, 0.6);
}

.stat-value-primary {
    font-weight: 700;
    color: #818cf8;
}

.stat-value-success {
    font-weight: 600;
    color: #22c55e;
}

.date-value {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-success {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
}

.admin-btn-success:hover {
    background: linear-gradient(135deg, #16a34a, #15803d);
}

/* Table Styles */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.3);
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Responsive */
@media (max-width: 1024px) {
    .hide-tablet {
        display: none;
    }
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }

    .admin-page-header-actions {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .engagement-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
