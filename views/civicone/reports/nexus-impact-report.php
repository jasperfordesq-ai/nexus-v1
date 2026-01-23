<?php
/**
 * Nexus Impact Report - GOV.UK Design System
 * Standalone Print Report Template
 * WCAG 2.1 AA Compliant
 *
 * Comprehensive community impact report with 1000-point scoring system.
 *
 * @var array $reportData - Report data including user/org scores, transactions, impact metrics
 * @var string $reportType - Type: 'user', 'organization', 'community'
 * @var array $dateRange - Start and end dates for report period
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

$reportType = $reportType ?? 'user';
$title = $reportData['title'] ?? 'Community Impact Report';
$period = $reportData['period'] ?? 'Last 30 Days';
$scoreData = $reportData['score_data'] ?? [];
$impactMetrics = $reportData['impact_metrics'] ?? [];
?>
<!DOCTYPE html>
<html lang="en" class="govuk-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Project NEXUS</title>

    <!-- GOV.UK Frontend CSS -->
    <link rel="stylesheet" href="/assets/css/civicone/govuk-frontend-5.14.0.min.css">
    <link rel="stylesheet" href="/assets/css/civicone/civicone-base.css">

    <!-- Impact Report CSS (extracted per CLAUDE.md) -->
    <link rel="stylesheet" href="/assets/css/civicone-nexus-impact-report.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/civicone-nexus-impact-report.css') ?>">

    <!-- Print styles -->
    <style media="print">
        @page { margin: 2cm; }
        .govuk-button { display: none !important; }
        .civicone-report-print-btn { display: none !important; }
    </style>
</head>
<body class="govuk-template__body">
    <script>document.body.className += ' js-enabled';</script>

    <div class="govuk-width-container civicone-report-container">

        <!-- Report Header -->
        <header class="civicone-report-header">
            <span class="govuk-caption-xl">Project NEXUS Impact Analysis</span>
            <h1 class="govuk-heading-xl"><?= htmlspecialchars($title) ?></h1>
            <p class="govuk-body-l">
                <strong>Report period:</strong> <?= htmlspecialchars($period) ?>
            </p>
        </header>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <!-- Nexus Score Section -->
        <?php if (!empty($scoreData)): ?>
        <section class="civicone-report-section">
            <h2 class="govuk-heading-l">Nexus Score Overview</h2>

            <?php
            $isPublic = false;
            include __DIR__ . '/../components/nexus-score-dashboard.php';
            ?>
        </section>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">
        <?php endif; ?>

        <!-- Executive Summary -->
        <section class="civicone-report-section">
            <h2 class="govuk-heading-l">Executive Summary</h2>

            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-quarter">
                    <div class="civicone-stat-card">
                        <span class="civicone-stat-value"><?= number_format($impactMetrics['total_exchanges'] ?? 0) ?></span>
                        <span class="civicone-stat-label">Total Exchanges</span>
                        <?php if (($impactMetrics['exchanges_change'] ?? 0) > 0): ?>
                        <span class="civicone-stat-change civicone-stat-change--positive">+<?= $impactMetrics['exchanges_change'] ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="govuk-grid-column-one-quarter">
                    <div class="civicone-stat-card">
                        <span class="civicone-stat-value"><?= number_format($impactMetrics['hours_exchanged'] ?? 0) ?></span>
                        <span class="civicone-stat-label">Hours Exchanged</span>
                        <?php if (($impactMetrics['hours_change'] ?? 0) > 0): ?>
                        <span class="civicone-stat-change civicone-stat-change--positive">+<?= $impactMetrics['hours_change'] ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="govuk-grid-column-one-quarter">
                    <div class="civicone-stat-card">
                        <span class="civicone-stat-value"><?= number_format($impactMetrics['active_members'] ?? 0) ?></span>
                        <span class="civicone-stat-label">Active Members</span>
                        <?php if (($impactMetrics['members_change'] ?? 0) > 0): ?>
                        <span class="civicone-stat-change civicone-stat-change--positive">+<?= $impactMetrics['members_change'] ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="govuk-grid-column-one-quarter">
                    <div class="civicone-stat-card">
                        <span class="civicone-stat-value">$<?= number_format($impactMetrics['economic_value'] ?? 0) ?></span>
                        <span class="civicone-stat-label">Economic Value</span>
                        <?php if (($impactMetrics['value_change'] ?? 0) > 0): ?>
                        <span class="civicone-stat-change civicone-stat-change--positive">+<?= $impactMetrics['value_change'] ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="govuk-inset-text govuk-!-margin-top-6">
                <h3 class="govuk-heading-s">Impact Story</h3>
                <p class="govuk-body">
                    <?= $impactMetrics['story'] ?? 'Our community continues to grow stronger through mutual support and shared time. Every exchange represents trust, connection, and positive social impact.' ?>
                </p>
            </div>
        </section>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <!-- Detailed Metrics -->
        <section class="civicone-report-section">
            <h2 class="govuk-heading-l">Detailed Metrics</h2>

            <dl class="govuk-summary-list">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Average Transaction Size</dt>
                    <dd class="govuk-summary-list__value"><?= number_format($impactMetrics['avg_transaction'] ?? 2.5, 1) ?> hours</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Network Diversity</dt>
                    <dd class="govuk-summary-list__value"><?= number_format($impactMetrics['network_diversity'] ?? 85) ?>%</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Member Retention Rate</dt>
                    <dd class="govuk-summary-list__value"><?= number_format($impactMetrics['retention_rate'] ?? 92) ?>%</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Average Response Time</dt>
                    <dd class="govuk-summary-list__value"><?= number_format($impactMetrics['response_time'] ?? 4.2, 1) ?> hours</dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Skills Shared</dt>
                    <dd class="govuk-summary-list__value"><?= number_format($impactMetrics['skills_count'] ?? 47) ?></dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Community Events</dt>
                    <dd class="govuk-summary-list__value"><?= number_format($impactMetrics['events_count'] ?? 12) ?></dd>
                </div>
            </dl>

            <?php if (!empty($impactMetrics['key_insight'])): ?>
            <div class="govuk-panel govuk-panel--confirmation govuk-!-margin-top-6">
                <h3 class="govuk-panel__title">Key Insight</h3>
                <div class="govuk-panel__body">
                    <?= htmlspecialchars($impactMetrics['key_insight']) ?>
                </div>
            </div>
            <?php else: ?>
            <div class="govuk-panel govuk-panel--confirmation govuk-!-margin-top-6">
                <h3 class="govuk-panel__title">Key Insight</h3>
                <div class="govuk-panel__body">
                    Your community engagement has increased significantly, showing strong network effects and sustainable growth patterns.
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Achievements & Badges -->
        <?php if (!empty($scoreData['breakdown']['badges']['details']['badges'])): ?>
        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <section class="civicone-report-section">
            <h2 class="govuk-heading-l">Achievements and Recognition</h2>

            <ul class="govuk-list civicone-badge-list">
                <?php foreach ($scoreData['breakdown']['badges']['details']['badges'] as $badge): ?>
                <li class="civicone-badge-item">
                    <strong class="govuk-tag govuk-tag--green"><?= htmlspecialchars($badge['name'] ?? 'Badge') ?></strong>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <!-- Recommendations -->
        <?php if (!empty($scoreData['insights'])): ?>
        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <section class="civicone-report-section">
            <h2 class="govuk-heading-l">Recommendations for Growth</h2>

            <ol class="govuk-list govuk-list--number">
                <?php foreach ($scoreData['insights'] as $index => $insight): ?>
                <li>
                    <strong><?= htmlspecialchars($insight['title']) ?></strong>
                    <p class="govuk-body"><?= htmlspecialchars($insight['message']) ?></p>
                </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php endif; ?>

        <!-- Conclusion -->
        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <section class="civicone-report-section">
            <h2 class="govuk-heading-l">Looking Forward</h2>

            <p class="govuk-body">
                This report demonstrates the measurable impact of community engagement and time banking.
                Your <?= $scoreData['total_score'] ?? 0 ?> Nexus Score reflects <?= strtolower($scoreData['tier']['name'] ?? 'strong') ?>-level
                participation and commitment to building social capital.
            </p>

            <p class="govuk-body">
                Continue fostering connections, sharing skills, and contributing to the community ecosystem.
                Every exchange strengthens the network and creates lasting value for all members.
            </p>

            <?php if (!empty($scoreData['next_milestone'])): ?>
            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">Important</span>
                    Next Milestone: You're <?= $scoreData['next_milestone']['points_remaining'] ?> points away from reaching
                    <strong><?= $scoreData['next_milestone']['name'] ?></strong> tier and unlocking:
                    <?= $scoreData['next_milestone']['reward'] ?>
                </strong>
            </div>
            <?php endif; ?>
        </section>

        <!-- Print Button -->
        <div class="govuk-!-margin-top-8 civicone-report-print-btn">
            <button type="button" class="govuk-button govuk-button--secondary" onclick="window.print()">
                Print report
            </button>
        </div>

    </div>

</body>
</html>
