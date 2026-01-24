<?php
/**
 * CivicOne View: User Insights Dashboard
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'My Insights';
$hideHero = true;

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/wallet">Wallet</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Insights</li>
    </ol>
</nav>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-chart-line govuk-!-margin-right-2" aria-hidden="true"></i>
            My Insights
        </h1>
        <p class="govuk-body-l">Your personal timebanking analytics</p>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/wallet" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-wallet govuk-!-margin-right-1" aria-hidden="true"></i>
            Wallet
        </a>
        <a href="<?= $basePath ?>/members" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i>
            Find Members
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4" style="background: #f3f2f1; text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1" style="color: #00703c;">+<?= number_format($insights['total_received'] ?? 0, 1) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Total Received</p>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4" style="background: #f3f2f1; text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1" style="color: #d4351c;">-<?= number_format($insights['total_sent'] ?? 0, 1) ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Total Sent</p>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4" style="background: #f3f2f1; text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= $insights['transaction_count'] ?? 0 ?></p>
            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Transactions</p>
        </div>
    </div>
    <div class="govuk-grid-column-one-quarter">
        <div class="govuk-!-padding-4" style="background: #f3f2f1; text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1" style="color: <?= ($insights['net_change'] ?? 0) >= 0 ? '#00703c' : '#d4351c' ?>;">
                <?= ($insights['net_change'] ?? 0) >= 0 ? '+' : '' ?><?= number_format($insights['net_change'] ?? 0, 1) ?>
            </p>
            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Net Change</p>
        </div>
    </div>
</div>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <!-- Monthly Trends Chart -->
    <div class="govuk-grid-column-two-thirds">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-chart-area govuk-!-margin-right-2" aria-hidden="true"></i>
                Monthly Activity
            </h2>
            <div style="height: 300px;">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Activity Streak -->
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; height: 100%;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-fire govuk-!-margin-right-2" aria-hidden="true"></i>
                Activity Streak
            </h2>
            <div style="text-align: center; padding: 2rem 0;">
                <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #1d70b8;"><?= $streak['current'] ?? 0 ?></p>
                <p class="govuk-body govuk-!-margin-bottom-2" style="color: #505a5f;">
                    <?= ($streak['current'] ?? 0) === 1 ? 'day' : 'days' ?> streak
                </p>
                <?php if (($streak['current'] ?? 0) >= 7): ?>
                    <span class="govuk-tag govuk-tag--green">Great progress!</span>
                <?php elseif (($streak['current'] ?? 0) >= 3): ?>
                    <span class="govuk-tag govuk-tag--blue">Nice momentum!</span>
                <?php elseif (($streak['current'] ?? 0) > 0): ?>
                    <span class="govuk-tag govuk-tag--yellow">Growing strong!</span>
                <?php else: ?>
                    <span class="govuk-tag govuk-tag--grey">Start your streak!</span>
                <?php endif; ?>
            </div>
            <hr class="govuk-section-break govuk-section-break--visible">
            <p class="govuk-body-s govuk-!-margin-top-2" style="color: #505a5f;">
                Longest streak: <strong><?= $streak['longest'] ?? 0 ?> days</strong>
            </p>
        </div>
    </div>
</div>

<!-- Partners Grid -->
<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <!-- People You've Helped -->
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-hand-holding-heart govuk-!-margin-right-2" aria-hidden="true"></i>
                People You've Helped
            </h2>
            <?php if (empty($topGivingPartners)): ?>
                <p class="govuk-body" style="color: #505a5f;">Send credits to see who you've helped!</p>
            <?php else: ?>
                <ul class="govuk-list">
                    <?php foreach ($topGivingPartners as $i => $partner): ?>
                    <li class="govuk-!-padding-2 govuk-!-margin-bottom-2" style="background: #f8f8f8; display: flex; align-items: center; gap: 0.75rem;">
                        <span class="govuk-tag govuk-tag--grey"><?= $i + 1 ?></span>
                        <div style="flex-grow: 1;">
                            <strong class="govuk-body-s govuk-!-margin-bottom-0"><?= htmlspecialchars($partner['display_name']) ?></strong>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;"><?= $partner['transaction_count'] ?> transactions</p>
                        </div>
                        <span class="govuk-body" style="color: #d4351c; font-weight: bold;">-<?= number_format($partner['total_amount'], 1) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- People Who've Helped You -->
    <div class="govuk-grid-column-one-half">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-hands-helping govuk-!-margin-right-2" aria-hidden="true"></i>
                People Who've Helped You
            </h2>
            <?php if (empty($topReceivingPartners)): ?>
                <p class="govuk-body" style="color: #505a5f;">Receive credits to see your helpers!</p>
            <?php else: ?>
                <ul class="govuk-list">
                    <?php foreach ($topReceivingPartners as $i => $partner): ?>
                    <li class="govuk-!-padding-2 govuk-!-margin-bottom-2" style="background: #f8f8f8; display: flex; align-items: center; gap: 0.75rem;">
                        <span class="govuk-tag govuk-tag--grey"><?= $i + 1 ?></span>
                        <div style="flex-grow: 1;">
                            <strong class="govuk-body-s govuk-!-margin-bottom-0"><?= htmlspecialchars($partner['display_name']) ?></strong>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;"><?= $partner['transaction_count'] ?> transactions</p>
                        </div>
                        <span class="govuk-body" style="color: #00703c; font-weight: bold;">+<?= number_format($partner['total_amount'], 1) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Community Impact -->
<div class="govuk-!-padding-4 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6;">
    <h2 class="govuk-heading-m">
        <i class="fa-solid fa-heart govuk-!-margin-right-2" aria-hidden="true"></i>
        Your Community Impact
    </h2>
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-quarter" style="text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= $partnerStats['unique_giving'] ?? 0 ?></p>
            <p class="govuk-body-s" style="color: #505a5f;">People You've Helped</p>
        </div>
        <div class="govuk-grid-column-one-quarter" style="text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= $partnerStats['unique_receiving'] ?? 0 ?></p>
            <p class="govuk-body-s" style="color: #505a5f;">People Who've Helped You</p>
        </div>
        <div class="govuk-grid-column-one-quarter" style="text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= ($partnerStats['unique_giving'] ?? 0) + ($partnerStats['unique_receiving'] ?? 0) ?></p>
            <p class="govuk-body-s" style="color: #505a5f;">Total Connections</p>
        </div>
        <div class="govuk-grid-column-one-quarter" style="text-align: center;">
            <p class="govuk-heading-l govuk-!-margin-bottom-1"><?= number_format($insights['avg_transaction'] ?? 0, 1) ?></p>
            <p class="govuk-body-s" style="color: #505a5f;">Avg Transaction Size</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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
                borderColor: '#00703c',
                backgroundColor: 'rgba(0, 112, 60, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3,
            }, {
                label: 'Sent',
                data: trendsData.map(d => d.sent || 0),
                borderColor: '#d4351c',
                backgroundColor: 'rgba(212, 53, 28, 0.1)',
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
                        color: isDark ? '#b1b4b6' : '#505a5f',
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
                        color: isDark ? '#b1b4b6' : '#505a5f'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: isDark ? '#b1b4b6' : '#505a5f'
                    }
                }
            }
        }
    });
}
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
