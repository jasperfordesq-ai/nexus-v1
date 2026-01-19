<?php
/**
 * Newsletter Stats/Analytics View - Gold Standard Admin UI
 * Holographic Glassmorphism Dark Theme
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin page configuration
$adminPageTitle = htmlspecialchars($newsletter['subject'] ?? 'Newsletter');
$adminPageSubtitle = 'Performance Analytics';
$adminPageIcon = 'fa-solid fa-chart-line';

// Calculate rates
$totalSent = $stats['total_sent'] ?? 0;
$totalFailed = $stats['total_failed'] ?? 0;
$totalOpens = $newsletter['total_opens'] ?? 0;
$uniqueOpens = $newsletter['unique_opens'] ?? 0;
$totalClicks = $newsletter['total_clicks'] ?? 0;
$uniqueClicks = $newsletter['unique_clicks'] ?? 0;

$total = $totalSent + $totalFailed;
$successRate = $total > 0 ? round(($totalSent / $total) * 100, 1) : 0;
$openRate = $totalSent > 0 ? round(($uniqueOpens / $totalSent) * 100, 1) : 0;
$clickRate = $totalSent > 0 ? round(($uniqueClicks / $totalSent) * 100, 1) : 0;
$clickToOpenRate = $uniqueOpens > 0 ? round(($uniqueClicks / $uniqueOpens) * 100, 1) : 0;

// A/B test data
$isABTest = !empty($newsletter['ab_test_enabled']) && !empty($newsletter['subject_b']);
$abResults = $abResults ?? null;

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<style>
    .stats-wrapper {
        padding: 0 40px 60px;
        position: relative;
        z-index: 10;
    }

    .stats-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    /* Back link */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.9rem;
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        color: #a5b4fc;
    }

    /* Glass Card */
    .glass-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        backdrop-filter: blur(20px);
        margin-bottom: 20px;
        padding: 24px;
    }

    /* Newsletter Summary */
    .summary-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 15px;
    }

    .summary-title {
        margin: 0 0 10px 0;
        font-size: 1.3rem;
        color: #ffffff;
        font-weight: 700;
    }

    .summary-meta {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.9rem;
    }

    .summary-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .badge-ab {
        background: linear-gradient(135deg, rgba(251, 191, 36, 0.3) 0%, rgba(245, 158, 11, 0.2) 100%);
        border: 1px solid rgba(251, 191, 36, 0.4);
        color: #fcd34d;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .btn-resend {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.3) 0%, rgba(124, 58, 237, 0.2) 100%);
        border: 1px solid rgba(139, 92, 246, 0.4);
        color: #c4b5fd;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .btn-resend:hover {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.4) 0%, rgba(124, 58, 237, 0.3) 100%);
        transform: translateY(-2px);
    }

    /* A/B Test Results */
    .ab-card {
        border: 2px solid rgba(251, 191, 36, 0.4);
    }

    .ab-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }

    .ab-badge {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #000;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .ab-title {
        margin: 0;
        color: #ffffff;
        font-size: 1.1rem;
    }

    .winner-banner {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.15) 100%);
        border: 1px solid rgba(16, 185, 129, 0.4);
        color: #6ee7b7;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .winner-banner-icon {
        font-size: 1.5rem;
    }

    .suggested-banner {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(37, 99, 235, 0.15) 100%);
        border: 1px solid rgba(59, 130, 246, 0.4);
        color: #93c5fd;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .btn-confirm {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
    }

    .ab-variants {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .ab-variant {
        background: rgba(255, 255, 255, 0.03);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s ease;
    }

    .ab-variant.winner {
        border-color: rgba(16, 185, 129, 0.5);
        background: rgba(16, 185, 129, 0.05);
    }

    .ab-variant-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .variant-badge-a {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .variant-badge-b {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .leading-indicator {
        color: #6ee7b7;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .variant-subject {
        font-size: 0.95rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 15px;
        font-style: italic;
    }

    .variant-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        text-align: center;
    }

    .variant-stat-value {
        font-size: 1.4rem;
        font-weight: 700;
    }

    .variant-stat-value.purple { color: #a5b4fc; }
    .variant-stat-value.green { color: #6ee7b7; }
    .variant-stat-value.white { color: rgba(255, 255, 255, 0.8); }

    .variant-stat-label {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
    }

    .ab-footer {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
    }

    /* Primary Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        backdrop-filter: blur(20px);
        padding: 20px;
        text-align: center;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
    }

    .stat-value.default { color: #ffffff; }
    .stat-value.purple { color: #a5b4fc; }
    .stat-value.green { color: #6ee7b7; }
    .stat-value.amber { color: #fcd34d; }
    .stat-value.red { color: #fca5a5; }

    .stat-label {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        margin-top: 5px;
    }

    .stat-sub {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.4);
    }

    /* Section titles */
    .section-title {
        margin: 0 0 20px 0;
        color: #ffffff;
        font-size: 1.1rem;
        font-weight: 600;
    }

    /* Engagement Funnel */
    .funnel-item {
        margin-bottom: 15px;
    }

    .funnel-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        font-size: 0.9rem;
    }

    .funnel-label {
        color: rgba(255, 255, 255, 0.7);
    }

    .funnel-value {
        color: #ffffff;
        font-weight: 600;
    }

    .funnel-bar-bg {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        height: 12px;
        overflow: hidden;
    }

    .funnel-bar {
        height: 100%;
        border-radius: 6px;
        transition: width 0.5s ease;
    }

    .funnel-bar.purple { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
    .funnel-bar.violet { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
    .funnel-bar.green { background: linear-gradient(90deg, #10b981, #34d399); }

    /* Chart */
    .chart-container {
        position: relative;
        height: 190px;
        margin-bottom: 10px;
    }

    .chart-y-axis {
        position: absolute;
        left: 0;
        top: 0;
        height: 150px;
        width: 40px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        font-size: 0.7rem;
        color: rgba(255, 255, 255, 0.4);
    }

    .chart-area {
        position: absolute;
        left: 45px;
        right: 0;
        top: 0;
        height: 150px;
        border-left: 1px solid rgba(255, 255, 255, 0.1);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: flex-end;
        gap: 2px;
        padding: 0 5px;
    }

    .chart-bar {
        flex: 1;
        min-width: 8px;
        max-width: 40px;
        background: linear-gradient(to top, #6366f1, #8b5cf6);
        border-radius: 3px 3px 0 0;
        transition: all 0.3s ease;
    }

    .chart-bar:hover {
        background: linear-gradient(to top, #818cf8, #a78bfa);
    }

    .chart-x-axis {
        position: absolute;
        left: 45px;
        right: 0;
        bottom: 0;
        height: 35px;
        display: flex;
        justify-content: space-between;
        padding: 10px 5px 0;
        font-size: 0.65rem;
        color: rgba(255, 255, 255, 0.4);
        overflow: hidden;
    }

    .chart-footer {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
        text-align: center;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 15px;
        margin-top: 10px;
    }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    }

    .data-table th {
        text-align: left;
        padding: 12px 0;
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
        text-transform: uppercase;
        font-weight: 600;
    }

    .data-table th.right { text-align: right; }

    .data-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .data-table td {
        padding: 12px 0;
        color: rgba(255, 255, 255, 0.8);
    }

    .data-table td.right { text-align: right; }

    .link-url {
        color: #a5b4fc;
        text-decoration: none;
        word-break: break-all;
        transition: color 0.2s;
    }

    .link-url:hover {
        color: #c4b5fd;
    }

    .clicks-value {
        font-weight: 600;
        color: #ffffff;
    }

    .unique-value {
        color: rgba(255, 255, 255, 0.5);
    }

    /* Device Stats */
    .device-grid {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
    }

    .device-item {
        text-align: center;
    }

    .device-percent {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .device-percent.desktop { color: #a5b4fc; }
    .device-percent.mobile { color: #6ee7b7; }
    .device-percent.tablet { color: #fcd34d; }
    .device-percent.unknown { color: rgba(255, 255, 255, 0.5); }

    .device-label {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        text-transform: capitalize;
    }

    .device-count {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.4);
    }

    /* Activity Table */
    .activity-scroll {
        max-height: 400px;
        overflow-y: auto;
    }

    .event-badge {
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .event-badge.open {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0.2) 100%);
        border: 1px solid rgba(59, 130, 246, 0.4);
        color: #93c5fd;
    }

    .event-badge.click {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.3) 0%, rgba(5, 150, 105, 0.2) 100%);
        border: 1px solid rgba(16, 185, 129, 0.4);
        color: #6ee7b7;
    }

    .activity-email {
        font-size: 0.9rem;
    }

    .activity-url {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.4);
        margin-top: 2px;
    }

    .activity-time {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
    }

    /* Actions */
    .actions-grid {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.8);
        padding: 10px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-action:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.25);
        color: #ffffff;
    }

    @media (max-width: 768px) {
        .stats-wrapper {
            padding: 0 20px 40px;
        }

        .ab-variants {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .summary-header {
            flex-direction: column;
        }
    }
</style>

<div class="stats-wrapper">
    <div class="stats-container">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/admin/newsletters" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Newsletters
        </a>

        <!-- Newsletter Summary -->
        <div class="glass-card">
            <div class="summary-header">
                <div>
                    <h2 class="summary-title"><?= htmlspecialchars($newsletter['subject']) ?></h2>
                    <div class="summary-meta">
                        Sent on <?= date('F j, Y \a\t g:i A', strtotime($newsletter['sent_at'])) ?>
                        <?php if (!empty($newsletter['author_name'])): ?>
                            by <?= htmlspecialchars($newsletter['author_name']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="summary-actions">
                    <?php if ($isABTest): ?>
                    <div class="badge-ab">A/B Test</div>
                    <?php endif; ?>
                    <?php
                    $nonOpenerCount = $totalSent - $uniqueOpens;
                    if ($nonOpenerCount > 0):
                    ?>
                    <a href="<?= $basePath ?>/admin/newsletters/resend/<?= $newsletter['id'] ?>" class="btn-resend">
                        <i class="fa-solid fa-rotate-right"></i>
                        Resend to <?= number_format($nonOpenerCount) ?> Non-Openers
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($isABTest && $abResults): ?>
        <!-- A/B Test Results -->
        <div class="glass-card ab-card">
            <div class="ab-header">
                <span class="ab-badge">A/B</span>
                <h3 class="ab-title">Subject Line Test Results</h3>
            </div>

            <?php if (!empty($abResults['winner'])): ?>
            <div class="winner-banner">
                <span class="winner-banner-icon">&#127942;</span>
                <div>
                    <strong>Winner: Subject <?= $abResults['winner'] ?></strong><br>
                    <span style="font-size: 0.9rem;">"<?= htmlspecialchars($abResults['winner'] === 'A' ? $abResults['subject_a'] : $abResults['subject_b']) ?>"</span>
                </div>
            </div>
            <?php elseif (!empty($abResults['suggested_winner']) && $abResults['suggested_winner'] !== 'tie'): ?>
            <div class="suggested-banner">
                <div>
                    <strong>Suggested Winner: Subject <?= $abResults['suggested_winner'] ?></strong>
                    (<?= number_format($abResults['winning_margin'], 1) ?>% better <?= ($abResults['winner_metric'] ?? 'opens') === 'clicks' ? 'click rate' : 'open rate' ?>)
                </div>
                <form action="<?= $basePath ?>/admin/newsletters/select-winner/<?= $newsletter['id'] ?>" method="POST" style="margin: 0;">
                    <?= Csrf::input() ?>
                    <input type="hidden" name="winner" value="<?= $abResults['suggested_winner'] ?>">
                    <button type="submit" class="btn-confirm">Confirm Winner</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="ab-variants">
                <?php
                $variantA = $abResults['variants']['A'] ?? null;
                $variantB = $abResults['variants']['B'] ?? null;
                $winnerMetric = ($abResults['winner_metric'] ?? 'opens') === 'clicks' ? 'click_rate' : 'open_rate';
                $isAWinner = $variantA && $variantB && $variantA[$winnerMetric] > $variantB[$winnerMetric];
                $isBWinner = $variantA && $variantB && $variantB[$winnerMetric] > $variantA[$winnerMetric];
                ?>

                <!-- Variant A -->
                <div class="ab-variant <?= $isAWinner ? 'winner' : '' ?>">
                    <div class="ab-variant-header">
                        <span class="variant-badge-a">Subject A</span>
                        <?php if ($isAWinner): ?>
                        <span class="leading-indicator"><i class="fa-solid fa-check"></i> Leading</span>
                        <?php endif; ?>
                    </div>
                    <div class="variant-subject">"<?= htmlspecialchars($abResults['subject_a']) ?>"</div>
                    <?php if ($variantA): ?>
                    <div class="variant-stats">
                        <div>
                            <div class="variant-stat-value purple"><?= $variantA['open_rate'] ?>%</div>
                            <div class="variant-stat-label">Open Rate</div>
                        </div>
                        <div>
                            <div class="variant-stat-value green"><?= $variantA['click_rate'] ?>%</div>
                            <div class="variant-stat-label">Click Rate</div>
                        </div>
                        <div>
                            <div class="variant-stat-value white"><?= number_format($variantA['total_sent']) ?></div>
                            <div class="variant-stat-label">Sent</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Variant B -->
                <div class="ab-variant <?= $isBWinner ? 'winner' : '' ?>">
                    <div class="ab-variant-header">
                        <span class="variant-badge-b">Subject B</span>
                        <?php if ($isBWinner): ?>
                        <span class="leading-indicator"><i class="fa-solid fa-check"></i> Leading</span>
                        <?php endif; ?>
                    </div>
                    <div class="variant-subject">"<?= htmlspecialchars($abResults['subject_b']) ?>"</div>
                    <?php if ($variantB): ?>
                    <div class="variant-stats">
                        <div>
                            <div class="variant-stat-value purple"><?= $variantB['open_rate'] ?>%</div>
                            <div class="variant-stat-label">Open Rate</div>
                        </div>
                        <div>
                            <div class="variant-stat-value green"><?= $variantB['click_rate'] ?>%</div>
                            <div class="variant-stat-label">Click Rate</div>
                        </div>
                        <div>
                            <div class="variant-stat-value white"><?= number_format($variantB['total_sent']) ?></div>
                            <div class="variant-stat-label">Sent</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ab-footer">
                Split: <?= $abResults['split_percentage'] ?>% A / <?= 100 - $abResults['split_percentage'] ?>% B
                &bull; Winning metric: <?= ucfirst($abResults['winner_metric'] ?? 'opens') ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Primary Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value default"><?= number_format($totalSent) ?></div>
                <div class="stat-label">Delivered</div>
            </div>

            <div class="stat-card">
                <div class="stat-value purple"><?= $openRate ?>%</div>
                <div class="stat-label">Open Rate</div>
                <div class="stat-sub"><?= number_format($uniqueOpens) ?> unique</div>
            </div>

            <div class="stat-card">
                <div class="stat-value green"><?= $clickRate ?>%</div>
                <div class="stat-label">Click Rate</div>
                <div class="stat-sub"><?= number_format($uniqueClicks) ?> unique</div>
            </div>

            <div class="stat-card">
                <div class="stat-value amber"><?= $clickToOpenRate ?>%</div>
                <div class="stat-label">Click-to-Open</div>
            </div>

            <?php if ($totalFailed > 0): ?>
            <div class="stat-card">
                <div class="stat-value red"><?= number_format($totalFailed) ?></div>
                <div class="stat-label">Failed</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Engagement Funnel -->
        <div class="glass-card">
            <h3 class="section-title">Engagement Funnel</h3>

            <div class="funnel-item">
                <div class="funnel-header">
                    <span class="funnel-label">Delivered</span>
                    <span class="funnel-value"><?= number_format($totalSent) ?></span>
                </div>
                <div class="funnel-bar-bg">
                    <div class="funnel-bar purple" style="width: 100%;"></div>
                </div>
            </div>

            <div class="funnel-item">
                <div class="funnel-header">
                    <span class="funnel-label">Opened</span>
                    <span class="funnel-value"><?= number_format($uniqueOpens) ?> (<?= $openRate ?>%)</span>
                </div>
                <div class="funnel-bar-bg">
                    <div class="funnel-bar violet" style="width: <?= min($openRate, 100) ?>%;"></div>
                </div>
            </div>

            <div class="funnel-item">
                <div class="funnel-header">
                    <span class="funnel-label">Clicked</span>
                    <span class="funnel-value"><?= number_format($uniqueClicks) ?> (<?= $clickRate ?>%)</span>
                </div>
                <div class="funnel-bar-bg">
                    <div class="funnel-bar green" style="width: <?= min($clickRate, 100) ?>%;"></div>
                </div>
            </div>
        </div>

        <?php if (!empty($analytics['opens_over_time']) && count($analytics['opens_over_time']) > 1): ?>
        <!-- Opens Over Time Chart -->
        <div class="glass-card">
            <h3 class="section-title">Engagement Over Time</h3>

            <?php
            $opensData = $analytics['opens_over_time'];
            $maxOpens = max(array_column($opensData, 'opens'));
            $chartHeight = 150;
            ?>

            <div class="chart-container">
                <div class="chart-y-axis">
                    <span><?= $maxOpens ?></span>
                    <span><?= round($maxOpens / 2) ?></span>
                    <span>0</span>
                </div>

                <div class="chart-area">
                    <?php foreach ($opensData as $point):
                        $barHeight = $maxOpens > 0 ? ($point['opens'] / $maxOpens) * $chartHeight : 0;
                    ?>
                        <div class="chart-bar" style="height: <?= $barHeight ?>px;" title="<?= date('M j, g:ia', strtotime($point['hour'])) ?>: <?= $point['opens'] ?> opens"></div>
                    <?php endforeach; ?>
                </div>

                <div class="chart-x-axis">
                    <?php
                    $labelCount = min(6, count($opensData));
                    $step = max(1, floor(count($opensData) / $labelCount));
                    for ($i = 0; $i < count($opensData); $i += $step):
                        if (isset($opensData[$i])):
                    ?>
                        <span style="white-space: nowrap;"><?= date('M j, ga', strtotime($opensData[$i]['hour'])) ?></span>
                    <?php
                        endif;
                    endfor;
                    ?>
                </div>
            </div>

            <div class="chart-footer">
                Total opens: <?= number_format($totalOpens) ?> &bull; Peak: <?= $maxOpens ?> opens in one hour
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($analytics['top_links'])): ?>
        <!-- Top Links -->
        <div class="glass-card">
            <h3 class="section-title">Top Clicked Links</h3>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th class="right" style="width: 100px;">Clicks</th>
                        <th class="right" style="width: 100px;">Unique</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics['top_links'] as $link): ?>
                    <tr>
                        <td>
                            <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="link-url">
                                <?= htmlspecialchars(strlen($link['url']) > 60 ? substr($link['url'], 0, 60) . '...' : $link['url']) ?>
                            </a>
                        </td>
                        <td class="right clicks-value"><?= number_format($link['clicks']) ?></td>
                        <td class="right unique-value"><?= number_format($link['unique_clicks']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($analytics['device_stats'])): ?>
        <!-- Device Breakdown -->
        <div class="glass-card">
            <h3 class="section-title">Device Breakdown</h3>

            <?php $deviceTotal = array_sum($analytics['device_stats']); ?>

            <div class="device-grid">
                <?php foreach ($analytics['device_stats'] as $device => $count): ?>
                    <?php if ($count > 0): ?>
                    <div class="device-item">
                        <div class="device-percent <?= $device ?>">
                            <?= $deviceTotal > 0 ? round(($count / $deviceTotal) * 100) : 0 ?>%
                        </div>
                        <div class="device-label"><?= $device ?></div>
                        <div class="device-count"><?= number_format($count) ?> opens</div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($analytics['recent_activity'])): ?>
        <!-- Recent Activity -->
        <div class="glass-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 class="section-title" style="margin: 0;">Recent Activity</h3>
                <a href="<?= $basePath ?>/admin/newsletters/activity/<?= $newsletter['id'] ?>" class="btn-action" style="padding: 6px 12px; font-size: 0.8rem;">
                    <i class="fa-solid fa-list"></i> View All Activity
                </a>
            </div>

            <div class="activity-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Email</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($analytics['recent_activity'], 0, 20) as $activity): ?>
                        <tr>
                            <td>
                                <?php if ($activity['type'] === 'open'): ?>
                                    <span class="event-badge open">OPENED</span>
                                <?php else: ?>
                                    <span class="event-badge click">CLICKED</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="activity-email"><?= htmlspecialchars($activity['email']) ?></div>
                                <?php if ($activity['type'] === 'click' && !empty($activity['url'])): ?>
                                    <div class="activity-url">
                                        <?= htmlspecialchars(strlen($activity['url']) > 40 ? substr($activity['url'], 0, 40) . '...' : $activity['url']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="activity-time">
                                <?= date('M j, g:i a', strtotime($activity['timestamp'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="glass-card">
            <h3 class="section-title">Actions</h3>
            <div class="actions-grid">
                <a href="<?= $basePath ?>/admin/newsletters/preview/<?= $newsletter['id'] ?>" target="_blank" class="btn-action">
                    <i class="fa-solid fa-eye"></i> View Email Content
                </a>
                <a href="<?= $basePath ?>/admin/newsletters/duplicate/<?= $newsletter['id'] ?>" class="btn-action">
                    <i class="fa-solid fa-copy"></i> Duplicate Newsletter
                </a>
            </div>
        </div>

    </div>
</div>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
