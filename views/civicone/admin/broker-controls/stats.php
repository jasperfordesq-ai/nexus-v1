<?php
/**
 * Broker Statistics - CivicOne Theme (GOV.UK)
 * Analytics and metrics for broker control features
 * Path: views/civicone/admin-legacy/broker-controls/stats.php
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

$stats = $stats ?? [];
$period = $period ?? '30';

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">

        <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="govuk-back-link">Back to Broker Controls</a>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">Broker Statistics</h1>
                <p class="govuk-body-l">Analytics and metrics for broker control features.</p>
            </div>
            <div class="govuk-grid-column-one-third" style="text-align: right;">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="period">Time period</label>
                    <select class="govuk-select" id="period" onchange="window.location.href='?period=' + this.value">
                        <option value="7" <?= $period === '7' ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="30" <?= $period === '30' ? 'selected' : '' ?>>Last 30 days</option>
                        <option value="90" <?= $period === '90' ? 'selected' : '' ?>>Last 90 days</option>
                        <option value="365" <?= $period === '365' ? 'selected' : '' ?>>Last year</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <h2 class="govuk-heading-l">Exchange Statistics</h2>
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #1d70b8; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['total_exchanges'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Total Exchanges</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #00703c; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['completed_exchanges'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Completed</p>
                    <p class="govuk-body-s" style="color: rgba(255,255,255,0.8); margin: 0;">
                        <?= $stats['completion_rate'] ?? 0 ?>% completion rate
                    </p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #f47738; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['pending_broker'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Pending Approval</p>
                    <p class="govuk-body-s" style="color: rgba(255,255,255,0.8); margin: 0;">
                        Avg wait: <?= $stats['avg_approval_time'] ?? '0' ?>h
                    </p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #5694ca; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['messages_reviewed'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Messages Reviewed</p>
                    <p class="govuk-body-s" style="color: rgba(255,255,255,0.8); margin: 0;">
                        <?= $stats['flagged_messages'] ?? 0 ?> flagged
                    </p>
                </div>
            </div>
        </div>

        <!-- Exchange Status Distribution -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-half">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">Exchange Status Distribution</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <?php
                        $statuses = [
                            ['label' => 'Completed', 'count' => $stats['status_completed'] ?? 0, 'colour' => '#00703c'],
                            ['label' => 'In Progress', 'count' => $stats['status_in_progress'] ?? 0, 'colour' => '#1d70b8'],
                            ['label' => 'Pending', 'count' => $stats['status_pending'] ?? 0, 'colour' => '#f47738'],
                            ['label' => 'Cancelled', 'count' => $stats['status_cancelled'] ?? 0, 'colour' => '#d4351c'],
                        ];
                        $total = array_sum(array_column($statuses, 'count')) ?: 1;
                        ?>
                        <?php foreach ($statuses as $status): ?>
                        <div class="govuk-!-margin-bottom-3">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span class="govuk-body"><?= $status['label'] ?></span>
                                <strong class="govuk-body"><?= number_format($status['count']) ?></strong>
                            </div>
                            <div style="background: #f3f2f1; height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background: <?= $status['colour'] ?>; height: 100%; width: <?= round(($status['count'] / $total) * 100) ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="govuk-grid-column-one-half">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title">Risk Tag Distribution</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <dl class="govuk-summary-list">
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">
                                    <strong class="govuk-tag govuk-tag--red">Critical</strong>
                                </dt>
                                <dd class="govuk-summary-list__value"><?= number_format($stats['risk_critical'] ?? 0) ?></dd>
                            </div>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">
                                    <strong class="govuk-tag govuk-tag--orange">High</strong>
                                </dt>
                                <dd class="govuk-summary-list__value"><?= number_format($stats['risk_high'] ?? 0) ?></dd>
                            </div>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">
                                    <strong class="govuk-tag govuk-tag--yellow">Medium</strong>
                                </dt>
                                <dd class="govuk-summary-list__value"><?= number_format($stats['risk_medium'] ?? 0) ?></dd>
                            </div>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">
                                    <strong class="govuk-tag govuk-tag--grey">Low</strong>
                                </dt>
                                <dd class="govuk-summary-list__value"><?= number_format($stats['risk_low'] ?? 0) ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Monitoring Statistics -->
        <h2 class="govuk-heading-l">User Monitoring</h2>
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title" style="font-size: 16px;">Messaging Disabled</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-heading-xl govuk-!-margin-bottom-0"><?= number_format($stats['users_restricted'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title" style="font-size: 16px;">Under Monitoring</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-heading-xl govuk-!-margin-bottom-0"><?= number_format($stats['users_monitored'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title" style="font-size: 16px;">New Members</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-heading-xl govuk-!-margin-bottom-0"><?= number_format($stats['new_members_period'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-summary-card">
                    <div class="govuk-summary-card__title-wrapper">
                        <h2 class="govuk-summary-card__title" style="font-size: 16px;">First Contacts</h2>
                    </div>
                    <div class="govuk-summary-card__content">
                        <p class="govuk-heading-xl govuk-!-margin-bottom-0"><?= number_format($stats['first_contacts_period'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <h2 class="govuk-heading-l">Recent Broker Activity</h2>
        <?php if (empty($stats['recent_activity'])): ?>
        <p class="govuk-body">No recent broker activity.</p>
        <?php else: ?>
        <table class="govuk-table">
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">Date</th>
                    <th scope="col" class="govuk-table__header">Activity</th>
                    <th scope="col" class="govuk-table__header">By</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                <?php foreach ($stats['recent_activity'] ?? [] as $activity): ?>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell">
                        <?= isset($activity['created_at']) ? date('j M Y, g:i A', strtotime($activity['created_at'])) : '' ?>
                    </td>
                    <td class="govuk-table__cell"><?= htmlspecialchars($activity['description'] ?? '') ?></td>
                    <td class="govuk-table__cell"><?= htmlspecialchars($activity['actor_name'] ?? 'System') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
