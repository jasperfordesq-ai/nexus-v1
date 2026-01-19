<?php
// CivicOne View: User Insights Dashboard - WCAG 2.1 AA Compliant
// CSS extracted to civicone-wallet.css

$hTitle = 'My Insights';
$hSubtitle = 'Personal Transaction Analytics';
$hGradient = 'htb-hero-gradient-wallet';
$hType = 'Analytics';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="insights-bg"></div>

<div class="insights-container">
    <!-- Header -->
    <div class="insights-header">
        <div class="insights-title">
            <div class="insights-title-icon">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div>
                <h1>My Insights</h1>
                <div class="insights-title-sub">Your personal timebanking analytics</div>
            </div>
        </div>
        <nav role="navigation" aria-label="Main navigation" class="insights-nav">
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet" class="insights-nav-link">
                <i class="fa-solid fa-wallet"></i> Wallet
            </a>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/members" class="insights-nav-link">
                <i class="fa-solid fa-users"></i> Find Members
            </a>
        </nav>
    </div>

    <!-- Stats Grid -->
    <div class="insights-stats-grid">
        <div class="insights-glass-card insights-stat-card">
            <div class="insights-stat-icon green">
                <i class="fa-solid fa-arrow-down"></i>
            </div>
            <div class="insights-stat-value positive">+<?= number_format($insights['total_received'] ?? 0, 1) ?></div>
            <div class="insights-stat-label">Total Received</div>
        </div>
        <div class="insights-glass-card insights-stat-card">
            <div class="insights-stat-icon red">
                <i class="fa-solid fa-arrow-up"></i>
            </div>
            <div class="insights-stat-value negative">-<?= number_format($insights['total_sent'] ?? 0, 1) ?></div>
            <div class="insights-stat-label">Total Sent</div>
        </div>
        <div class="insights-glass-card insights-stat-card">
            <div class="insights-stat-icon blue">
                <i class="fa-solid fa-exchange-alt"></i>
            </div>
            <div class="insights-stat-value"><?= $insights['transaction_count'] ?? 0 ?></div>
            <div class="insights-stat-label">Transactions</div>
        </div>
        <div class="insights-glass-card insights-stat-card">
            <div class="insights-stat-icon purple">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div class="insights-stat-value <?= ($insights['net_change'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                <?= ($insights['net_change'] ?? 0) >= 0 ? '+' : '' ?><?= number_format($insights['net_change'] ?? 0, 1) ?>
            </div>
            <div class="insights-stat-label">Net Change</div>
        </div>
    </div>

    <!-- Main Grid: Chart + Streak -->
    <div class="insights-grid">
        <!-- Monthly Trends Chart -->
        <div class="insights-glass-card">
            <div class="insights-section-header">
                <h3 class="insights-section-title">
                    <i class="fa-solid fa-chart-area" style="color: #a855f7;"></i>
                    Monthly Activity
                </h3>
            </div>
            <div class="insights-chart-container">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>

        <!-- Activity Streak -->
        <div class="insights-glass-card">
            <div class="insights-section-header">
                <h3 class="insights-section-title">
                    <i class="fa-solid fa-fire" style="color: #f59e0b;"></i>
                    Activity Streak
                </h3>
            </div>
            <div class="streak-display">
                <div class="streak-icon">
                    <?php if (($streak['current'] ?? 0) >= 7): ?>
                        <i class="fa-solid fa-fire" style="color: #f59e0b;"></i>
                    <?php elseif (($streak['current'] ?? 0) >= 3): ?>
                        <i class="fa-solid fa-fire-flame-curved" style="color: #fb923c;"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-seedling" style="color: #10b981;"></i>
                    <?php endif; ?>
                </div>
                <div class="streak-value"><?= $streak['current'] ?? 0 ?></div>
                <div class="streak-label">
                    <?= ($streak['current'] ?? 0) === 1 ? 'day' : 'days' ?> streak
                </div>
                <?php if (($streak['current'] ?? 0) >= 7): ?>
                <div class="streak-message">
                    <i class="fa-solid fa-star"></i> You're on fire! Keep it up!
                </div>
                <?php elseif (($streak['current'] ?? 0) >= 3): ?>
                <div class="streak-message">
                    <i class="fa-solid fa-thumbs-up"></i> Nice momentum!
                </div>
                <?php elseif (($streak['current'] ?? 0) > 0): ?>
                <div class="streak-message">
                    <i class="fa-solid fa-seedling"></i> Growing strong!
                </div>
                <?php else: ?>
                <div class="streak-message" style="background: rgba(107, 114, 128, 0.1); color: #6b7280;">
                    Make a transaction to start your streak!
                </div>
                <?php endif; ?>
            </div>
            <div class="streak-footer">
                <div class="streak-footer-content">
                    Longest streak: <strong class="streak-footer-value"><?= $streak['longest'] ?? 0 ?> days</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Two Column: Partners -->
    <div class="insights-grid-2">
        <!-- People You've Helped -->
        <div class="insights-glass-card">
            <div class="insights-section-header">
                <h3 class="insights-section-title">
                    <i class="fa-solid fa-hand-holding-heart" style="color: #ef4444;"></i>
                    People You've Helped
                </h3>
            </div>
            <?php if (empty($topGivingPartners)): ?>
            <div class="insights-empty">
                <div class="insights-empty-icon">
                    <i class="fa-solid fa-heart"></i>
                </div>
                <p>Send credits to see who you've helped!</p>
            </div>
            <?php else: ?>
            <div class="insights-partner-list">
                <?php foreach ($topGivingPartners as $i => $partner): ?>
                <div class="insights-partner-item">
                    <div class="insights-partner-rank"><?= $i + 1 ?></div>
                    <div class="insights-partner-avatar">
                        <?php if (!empty($partner['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($partner['avatar_url']) ?>" loading="lazy" alt="">
                        <?php else: ?>
                            <?= strtoupper(substr($partner['display_name'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="insights-partner-info">
                        <div class="insights-partner-name"><?= htmlspecialchars($partner['display_name']) ?></div>
                        <div class="insights-partner-count"><?= $partner['transaction_count'] ?> transactions</div>
                    </div>
                    <div class="insights-partner-amount given">-<?= number_format($partner['total_amount'], 1) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- People Who've Helped You -->
        <div class="insights-glass-card">
            <div class="insights-section-header">
                <h3 class="insights-section-title">
                    <i class="fa-solid fa-gift" style="color: #10b981;"></i>
                    People Who've Helped You
                </h3>
            </div>
            <?php if (empty($topReceivingPartners)): ?>
            <div class="insights-empty">
                <div class="insights-empty-icon">
                    <i class="fa-solid fa-gift"></i>
                </div>
                <p>Receive credits to see your helpers!</p>
            </div>
            <?php else: ?>
            <div class="insights-partner-list">
                <?php foreach ($topReceivingPartners as $i => $partner): ?>
                <div class="insights-partner-item">
                    <div class="insights-partner-rank"><?= $i + 1 ?></div>
                    <div class="insights-partner-avatar">
                        <?php if (!empty($partner['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($partner['avatar_url']) ?>" loading="lazy" alt="">
                        <?php else: ?>
                            <?= strtoupper(substr($partner['display_name'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="insights-partner-info">
                        <div class="insights-partner-name"><?= htmlspecialchars($partner['display_name']) ?></div>
                        <div class="insights-partner-count"><?= $partner['transaction_count'] ?> transactions</div>
                    </div>
                    <div class="insights-partner-amount received">+<?= number_format($partner['total_amount'], 1) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Community Impact -->
    <div class="insights-glass-card">
        <div class="insights-section-header">
            <h3 class="insights-section-title">
                <i class="fa-solid fa-users" style="color: #3b82f6;"></i>
                Your Community Impact
            </h3>
        </div>
        <div class="community-stats">
            <div class="community-stat">
                <div class="community-stat-value"><?= $partnerStats['unique_giving'] ?? 0 ?></div>
                <div class="community-stat-label">People You've Helped</div>
            </div>
            <div class="community-stat">
                <div class="community-stat-value"><?= $partnerStats['unique_receiving'] ?? 0 ?></div>
                <div class="community-stat-label">People Who've Helped You</div>
            </div>
            <div class="community-stat">
                <div class="community-stat-value"><?= ($partnerStats['unique_giving'] ?? 0) + ($partnerStats['unique_receiving'] ?? 0) ?></div>
                <div class="community-stat-label">Total Connections</div>
            </div>
            <div class="community-stat">
                <div class="community-stat-value"><?= number_format($insights['avg_transaction'] ?? 0, 1) ?></div>
                <div class="community-stat-label">Avg Transaction Size</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Trends Chart
const ctx = document.getElementById('trendsChart');
if (ctx) {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const trendsData = <?= json_encode($trends) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendsData.map(d => d.month),
            datasets: [{
                label: 'Received',
                data: trendsData.map(d => d.received || 0),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
            }, {
                label: 'Sent',
                data: trendsData.map(d => d.sent || 0),
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: isDark ? '#94a3b8' : '#6b7280',
                        padding: 20,
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        color: isDark ? '#94a3b8' : '#6b7280'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: isDark ? '#94a3b8' : '#6b7280'
                    }
                }
            }
        }
    });
}
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
