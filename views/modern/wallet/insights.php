<?php
// Phoenix View: User Insights Dashboard (Glassmorphism)
// Path: views/modern/wallet/insights.php

$hTitle = 'My Insights';
$hSubtitle = 'Personal Transaction Analytics';
$hGradient = 'htb-hero-gradient-wallet';
$hType = 'Analytics';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/modern/header.php';
?>

<style>
/* Insights Background */
.insights-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #faf5ff 25%, #f3e8ff 50%, #faf5ff 75%, #f8fafc 100%);
}

.insights-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 20% 30%, rgba(168, 85, 247, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 80%, rgba(192, 132, 252, 0.08) 0%, transparent 50%);
    animation: insightsFloat 20s ease-in-out infinite;
}

[data-theme="dark"] .insights-bg {
    background: linear-gradient(135deg, #0f172a 0%, #2e1065 50%, #0f172a 100%);
}

[data-theme="dark"] .insights-bg::before {
    background:
        radial-gradient(ellipse at 20% 30%, rgba(168, 85, 247, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 45%);
}

@keyframes insightsFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-1%, 1%) scale(1.02); }
}

.insights-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 120px 24px 40px 24px;
    position: relative;
    z-index: 10;
}

/* Header */
.insights-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
}

.insights-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.insights-title-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.insights-title h1 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 800;
    color: #1f2937;
}

[data-theme="dark"] .insights-title h1 {
    color: #f1f5f9;
}

.insights-title-sub {
    font-size: 0.9rem;
    color: #6b7280;
    margin-top: 4px;
}

[data-theme="dark"] .insights-title-sub {
    color: #94a3b8;
}

.insights-nav {
    display: flex;
    gap: 8px;
}

.insights-nav-link {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(168, 85, 247, 0.2);
    border-radius: 12px;
    color: #374151;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.insights-nav-link:hover {
    background: rgba(168, 85, 247, 0.1);
}

[data-theme="dark"] .insights-nav-link {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(168, 85, 247, 0.3);
    color: #e2e8f0;
}

/* Glass Cards */
.insights-glass-card {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.9) 0%,
        rgba(255, 255, 255, 0.75) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
    overflow: hidden;
}

[data-theme="dark"] .insights-glass-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.9) 0%,
        rgba(30, 41, 59, 0.75) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

/* Stats Grid */
.insights-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.insights-stat-card {
    padding: 24px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.insights-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.1), transparent);
    transform: translate(20px, -20px);
}

.insights-stat-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.insights-stat-icon.green { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.insights-stat-icon.red { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.insights-stat-icon.blue { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.insights-stat-icon.purple { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
.insights-stat-icon.orange { background: rgba(251, 191, 36, 0.15); color: #f59e0b; }

[data-theme="dark"] .insights-stat-icon.green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
[data-theme="dark"] .insights-stat-icon.red { background: rgba(239, 68, 68, 0.2); color: #f87171; }
[data-theme="dark"] .insights-stat-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
[data-theme="dark"] .insights-stat-icon.purple { background: rgba(168, 85, 247, 0.2); color: #c084fc; }

.insights-stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #1f2937;
    line-height: 1.2;
}

[data-theme="dark"] .insights-stat-value {
    color: #f1f5f9;
}

.insights-stat-value.positive { color: #10b981; }
.insights-stat-value.negative { color: #ef4444; }

[data-theme="dark"] .insights-stat-value.positive { color: #34d399; }
[data-theme="dark"] .insights-stat-value.negative { color: #f87171; }

.insights-stat-label {
    font-size: 0.85rem;
    color: #6b7280;
    margin-top: 4px;
}

[data-theme="dark"] .insights-stat-label {
    color: #94a3b8;
}

/* Main Grid */
.insights-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.insights-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-bottom: 24px;
}

/* Section Title */
.insights-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid rgba(168, 85, 247, 0.1);
}

[data-theme="dark"] .insights-section-header {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.insights-section-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .insights-section-title {
    color: #f1f5f9;
}

/* Chart Container */
.insights-chart-container {
    padding: 24px;
    height: 300px;
}

/* Partner List */
.insights-partner-list {
    padding: 0 24px 24px;
}

.insights-partner-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid rgba(168, 85, 247, 0.08);
    gap: 12px;
}

[data-theme="dark"] .insights-partner-item {
    border-bottom-color: rgba(255, 255, 255, 0.05);
}

.insights-partner-item:last-child {
    border-bottom: none;
}

.insights-partner-rank {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: rgba(168, 85, 247, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.75rem;
    color: #a855f7;
}

.insights-partner-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #a855f7, #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
    overflow: hidden;
}

.insights-partner-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.insights-partner-info {
    flex: 1;
    min-width: 0;
}

.insights-partner-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.9rem;
}

[data-theme="dark"] .insights-partner-name {
    color: #f1f5f9;
}

.insights-partner-count {
    font-size: 0.8rem;
    color: #6b7280;
}

[data-theme="dark"] .insights-partner-count {
    color: #94a3b8;
}

.insights-partner-amount {
    font-weight: 700;
    font-size: 1rem;
}

.insights-partner-amount.given { color: #ef4444; }
.insights-partner-amount.received { color: #10b981; }

[data-theme="dark"] .insights-partner-amount.given { color: #f87171; }
[data-theme="dark"] .insights-partner-amount.received { color: #34d399; }

/* Streak Card */
.streak-display {
    text-align: center;
    padding: 32px 24px;
}

.streak-icon {
    font-size: 3rem;
    margin-bottom: 12px;
}

.streak-value {
    font-size: 3rem;
    font-weight: 800;
    color: #f59e0b;
    line-height: 1;
}

.streak-label {
    font-size: 1rem;
    color: #6b7280;
    margin-top: 8px;
}

[data-theme="dark"] .streak-label {
    color: #94a3b8;
}

.streak-message {
    font-size: 0.9rem;
    color: #f59e0b;
    margin-top: 12px;
    padding: 8px 16px;
    background: rgba(251, 191, 36, 0.1);
    border-radius: 20px;
    display: inline-block;
}

/* Community Stats */
.community-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    padding: 24px;
}

.community-stat {
    text-align: center;
    padding: 16px;
    background: rgba(168, 85, 247, 0.05);
    border-radius: 12px;
}

[data-theme="dark"] .community-stat {
    background: rgba(168, 85, 247, 0.1);
}

.community-stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #a855f7;
}

.community-stat-label {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 4px;
}

[data-theme="dark"] .community-stat-label {
    color: #94a3b8;
}

/* Empty State */
.insights-empty {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.insights-empty-icon {
    font-size: 3rem;
    opacity: 0.3;
    margin-bottom: 16px;
}

/* Mobile Responsive */
@media (max-width: 1024px) {
    .insights-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .insights-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .insights-container {
        padding: 100px 16px 100px 16px;
    }

    .insights-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .insights-stats-grid {
        grid-template-columns: 1fr 1fr;
    }

    .insights-stat-value {
        font-size: 1.5rem;
    }

    .insights-grid-2 {
        grid-template-columns: 1fr;
    }

    .community-stats {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .insights-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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
            <div style="padding: 0 24px 24px; text-align: center; border-top: 1px solid rgba(168, 85, 247, 0.1);">
                <div style="padding-top: 16px; font-size: 0.9rem; color: #6b7280;">
                    Longest streak: <strong style="color: #f59e0b;"><?= $streak['longest'] ?? 0 ?> days</strong>
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

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>
