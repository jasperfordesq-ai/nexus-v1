<?php
/**
 * Admin: Group Recommendations Performance Dashboard - FDS Gold Standard
 * Shows metrics on ML recommendation engine performance
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Group Recommendations';
$adminPageSubtitle = 'ML-powered group discovery performance metrics';
$adminPageIcon = 'fa-sparkles';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-sparkles"></i>
            Group Recommendations
        </h1>
        <p class="admin-page-subtitle">ML-powered group discovery performance metrics</p>
    </div>
    <div class="admin-page-header-actions">
        <button class="admin-btn admin-btn-secondary" onclick="loadMetrics()">
            <i class="fa-solid fa-rotate"></i>
            Refresh
        </button>
        <a href="<?= $basePath ?>/admin/groups" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Groups
        </a>
    </div>
</div>

<!-- Loading State -->
<div id="loading-state" class="admin-loading-container">
    <div class="admin-loading-spinner"></div>
    <p>Loading recommendation metrics...</p>
</div>

<!-- Stats Grid -->
<div id="stats-container" style="display: none;">
    <div class="admin-stats-grid">
        <div class="admin-stat-card admin-stat-blue">
            <div class="admin-stat-icon">
                <i class="fa-solid fa-eye"></i>
            </div>
            <div class="admin-stat-content">
                <div class="admin-stat-value" id="total-views">-</div>
                <div class="admin-stat-label">Total Views</div>
            </div>
            <div class="admin-stat-trend">
                <span>Last 30 days</span>
            </div>
        </div>

        <div class="admin-stat-card admin-stat-green">
            <div class="admin-stat-icon">
                <i class="fa-solid fa-mouse-pointer"></i>
            </div>
            <div class="admin-stat-content">
                <div class="admin-stat-value" id="total-clicks">-</div>
                <div class="admin-stat-label">Total Clicks</div>
            </div>
            <div class="admin-stat-trend">
                <span id="ctr-percent">-</span>
            </div>
        </div>

        <div class="admin-stat-card admin-stat-orange">
            <div class="admin-stat-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <div class="admin-stat-content">
                <div class="admin-stat-value" id="total-joins">-</div>
                <div class="admin-stat-label">Joined via Recs</div>
            </div>
            <div class="admin-stat-trend">
                <span id="conversion-text">-</span>
            </div>
        </div>

        <div class="admin-stat-card admin-stat-purple">
            <div class="admin-stat-icon">
                <i class="fa-solid fa-percent"></i>
            </div>
            <div class="admin-stat-content">
                <div class="admin-stat-value" id="conversion-rate">-</div>
                <div class="admin-stat-label">Click-to-Join Rate</div>
            </div>
            <div class="admin-stat-trend">
                <span>Conversion</span>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="admin-tabs">
        <button class="admin-tab active" data-tab="overview">
            <i class="fa-solid fa-chart-pie"></i> Overview
        </button>
        <button class="admin-tab" data-tab="top-groups">
            <i class="fa-solid fa-trophy"></i> Top Recommended Groups
        </button>
        <button class="admin-tab" data-tab="algorithms">
            <i class="fa-solid fa-brain"></i> Algorithm Performance
        </button>
        <button class="admin-tab" data-tab="recent">
            <i class="fa-solid fa-clock"></i> Recent Activity
        </button>
    </div>

    <!-- Tab Panels -->
    <div class="admin-tab-content">
        <!-- Overview Tab -->
        <div class="admin-tab-panel active" data-panel="overview">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fa-solid fa-chart-line"></i> Performance Metrics (Last 30 Days)</h3>
                </div>
                <div class="admin-card-body">
                    <div id="overview-metrics" class="admin-metrics-summary"></div>
                </div>
            </div>
        </div>

        <!-- Top Groups Tab -->
        <div class="admin-tab-panel" data-panel="top-groups">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fa-solid fa-star"></i> Most Recommended Groups</h3>
                    <p class="admin-card-subtitle">Groups most frequently appearing in recommendations</p>
                </div>
                <div class="admin-card-body">
                    <div id="top-groups-table"></div>
                </div>
            </div>
        </div>

        <!-- Algorithms Tab -->
        <div class="admin-tab-panel" data-panel="algorithms">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fa-solid fa-brain"></i> ML Algorithm Breakdown</h3>
                    <p class="admin-card-subtitle">How each algorithm contributes to recommendations</p>
                </div>
                <div class="admin-card-body">
                    <div class="algorithm-grid">
                        <div class="algorithm-card">
                            <div class="algorithm-header">
                                <i class="fa-solid fa-users"></i>
                                <h4>Collaborative Filtering</h4>
                                <span class="algorithm-weight">40% weight</span>
                            </div>
                            <p class="algorithm-description">
                                Recommends groups based on similar users' memberships using Jaccard similarity
                            </p>
                            <div class="algorithm-stats">
                                <div class="algorithm-stat">
                                    <span class="label">Method:</span>
                                    <span class="value">Jaccard Similarity</span>
                                </div>
                                <div class="algorithm-stat">
                                    <span class="label">Data Source:</span>
                                    <span class="value">Group memberships</span>
                                </div>
                            </div>
                        </div>

                        <div class="algorithm-card">
                            <div class="algorithm-header">
                                <i class="fa-solid fa-file-lines"></i>
                                <h4>Content-Based</h4>
                                <span class="algorithm-weight">25% weight</span>
                            </div>
                            <p class="algorithm-description">
                                Matches user interests with group descriptions using TF-IDF keyword extraction
                            </p>
                            <div class="algorithm-stats">
                                <div class="algorithm-stat">
                                    <span class="label">Method:</span>
                                    <span class="value">TF-IDF Matching</span>
                                </div>
                                <div class="algorithm-stat">
                                    <span class="label">Data Source:</span>
                                    <span class="value">User bio, group descriptions</span>
                                </div>
                            </div>
                        </div>

                        <div class="algorithm-card">
                            <div class="algorithm-header">
                                <i class="fa-solid fa-location-dot"></i>
                                <h4>Location-Based</h4>
                                <span class="algorithm-weight">20% weight</span>
                            </div>
                            <p class="algorithm-description">
                                Finds groups within 50km using Haversine distance formula
                            </p>
                            <div class="algorithm-stats">
                                <div class="algorithm-stat">
                                    <span class="label">Method:</span>
                                    <span class="value">Haversine Distance</span>
                                </div>
                                <div class="algorithm-stat">
                                    <span class="label">Data Source:</span>
                                    <span class="value">User/group coordinates</span>
                                </div>
                            </div>
                        </div>

                        <div class="algorithm-card">
                            <div class="algorithm-header">
                                <i class="fa-solid fa-chart-area"></i>
                                <h4>Activity-Based</h4>
                                <span class="algorithm-weight">15% weight</span>
                            </div>
                            <p class="algorithm-description">
                                Analyzes user listing activity patterns for behavioral recommendations
                            </p>
                            <div class="algorithm-stats">
                                <div class="algorithm-stat">
                                    <span class="label">Method:</span>
                                    <span class="value">Behavioral Analysis</span>
                                </div>
                                <div class="algorithm-stat">
                                    <span class="label">Data Source:</span>
                                    <span class="value">User listing categories</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Tab -->
        <div class="admin-tab-panel" data-panel="recent">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fa-solid fa-history"></i> Recent Recommendation Interactions</h3>
                </div>
                <div class="admin-card-body">
                    <div id="recent-activity"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Error State -->
<div id="error-state" style="display: none;">
    <div class="admin-card">
        <div class="admin-card-body text-center">
            <div class="admin-empty-state">
                <div class="admin-empty-icon">
                    <i class="fa-solid fa-exclamation-circle"></i>
                </div>
                <h3>Failed to Load Metrics</h3>
                <p id="error-message" class="text-muted"></p>
                <button onclick="loadMetrics()" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-rotate"></i> Retry
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.admin-loading-container {
    text-align: center;
    padding: 80px 20px;
}

.admin-loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.algorithm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.algorithm-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 24px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.algorithm-header {
    margin-bottom: 16px;
}

.algorithm-header i {
    font-size: 28px;
    color: #6366f1;
    margin-bottom: 12px;
    display: block;
}

.algorithm-header h4 {
    margin: 8px 0;
    font-size: 18px;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.algorithm-weight {
    display: inline-block;
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.algorithm-description {
    color: rgba(255, 255, 255, 0.6);
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 16px;
}

.algorithm-stats {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 16px;
}

.algorithm-stat {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 13px;
}

.algorithm-stat .label {
    color: rgba(255, 255, 255, 0.5);
}

.algorithm-stat .value {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.admin-metrics-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.metric-item {
    display: flex;
    justify-content: space-between;
    padding: 16px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.metric-label {
    color: rgba(255, 255, 255, 0.6);
    font-size: 14px;
}

.metric-value {
    font-size: 18px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.9);
}

.admin-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.admin-badge-viewed { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.admin-badge-clicked { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.admin-badge-joined { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
.admin-badge-dismissed { background: rgba(239, 68, 68, 0.2); color: #f87171; }
</style>

<script>
const basePath = '<?= $basePath ?>';

// Tab switching
document.querySelectorAll('.admin-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const targetTab = tab.dataset.tab;
        document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.admin-tab-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.querySelector(`[data-panel="${targetTab}"]`).classList.add('active');
    });
});

// Load metrics
async function loadMetrics() {
    const loadingState = document.getElementById('loading-state');
    const statsContainer = document.getElementById('stats-container');
    const errorState = document.getElementById('error-state');

    loadingState.style.display = 'block';
    statsContainer.style.display = 'none';
    errorState.style.display = 'none';

    try {
        const response = await fetch(`${basePath}/api/recommendations/metrics?days=30`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load metrics');
        }

        displayMetrics(data.metrics);
        loadingState.style.display = 'none';
        statsContainer.style.display = 'block';

    } catch (error) {
        console.error('Error loading metrics:', error);
        loadingState.style.display = 'none';
        errorState.style.display = 'block';
        document.getElementById('error-message').textContent = error.message;
    }
}

function displayMetrics(metrics) {
    // Update stat cards
    document.getElementById('total-views').textContent = (metrics.total_views || 0).toLocaleString();
    document.getElementById('total-clicks').textContent = (metrics.total_clicks || 0).toLocaleString();
    document.getElementById('total-joins').textContent = (metrics.total_joins || 0).toLocaleString();

    const clickToJoinRate = metrics.total_clicks > 0
        ? ((metrics.total_joins / metrics.total_clicks) * 100).toFixed(1)
        : '0.0';
    document.getElementById('conversion-rate').textContent = clickToJoinRate + '%';

    const ctr = metrics.total_views > 0
        ? ((metrics.total_clicks / metrics.total_views) * 100).toFixed(1)
        : '0.0';
    document.getElementById('ctr-percent').textContent = `${ctr}% CTR`;
    document.getElementById('conversion-text').textContent = `${clickToJoinRate}% conv`;

    // Overview metrics
    const overviewHtml = `
        <div class="metric-item">
            <span class="metric-label">Viewed Recommendations:</span>
            <span class="metric-value">${(metrics.total_views || 0).toLocaleString()}</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">Clicked Recommendations:</span>
            <span class="metric-value">${(metrics.total_clicks || 0).toLocaleString()}</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">Joined Groups:</span>
            <span class="metric-value">${(metrics.total_joins || 0).toLocaleString()}</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">Dismissed Recommendations:</span>
            <span class="metric-value">${(metrics.total_dismissed || 0).toLocaleString()}</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">View-to-Click Rate:</span>
            <span class="metric-value">${ctr}%</span>
        </div>
        <div class="metric-item">
            <span class="metric-label">Click-to-Join Rate:</span>
            <span class="metric-value">${clickToJoinRate}%</span>
        </div>
    `;
    document.getElementById('overview-metrics').innerHTML = overviewHtml;

    // Top groups
    if (metrics.top_groups && metrics.top_groups.length > 0) {
        const topGroupsHtml = `
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th>Views</th>
                        <th>Clicks</th>
                        <th>Joins</th>
                        <th>Conversion</th>
                    </tr>
                </thead>
                <tbody>
                    ${metrics.top_groups.map(group => `
                        <tr>
                            <td>${escapeHtml(group.name)}</td>
                            <td>${(group.views || 0).toLocaleString()}</td>
                            <td>${(group.clicks || 0).toLocaleString()}</td>
                            <td>${(group.joins || 0).toLocaleString()}</td>
                            <td>${group.clicks > 0 ? ((group.joins / group.clicks) * 100).toFixed(1) : '0.0'}%</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        document.getElementById('top-groups-table').innerHTML = topGroupsHtml;
    } else {
        document.getElementById('top-groups-table').innerHTML = '<p class="text-muted">No data available yet.</p>';
    }

    // Recent activity
    if (metrics.recent_activity && metrics.recent_activity.length > 0) {
        const recentHtml = `
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Group</th>
                        <th>Action</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    ${metrics.recent_activity.map(activity => `
                        <tr>
                            <td>${escapeHtml(activity.user_name || 'User #' + activity.user_id)}</td>
                            <td>${escapeHtml(activity.group_name)}</td>
                            <td><span class="admin-badge admin-badge-${activity.action}">${activity.action}</span></td>
                            <td>${formatTimestamp(activity.created_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        document.getElementById('recent-activity').innerHTML = recentHtml;
    } else {
        document.getElementById('recent-activity').innerHTML = '<p class="text-muted">No recent activity.</p>';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTimestamp(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleString();
}

// Load on page load
loadMetrics();
</script>

<?php
// Include admin footer
require dirname(__DIR__) . '/partials/admin-footer.php';
?>
