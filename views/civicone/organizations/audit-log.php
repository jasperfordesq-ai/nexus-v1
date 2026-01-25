<?php
/**
 * Organization Audit Log - GOV.UK Design System
 * WCAG 2.1 AA Compliant
 */

$pageTitle = $org['name'] . ' - Audit Log';
\Nexus\Core\SEO::setTitle($org['name'] . ' - Audit Log');
\Nexus\Core\SEO::setDescription('Security audit log and activity history for ' . $org['name']);

$activeTab = 'audit';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper">
        <!-- Shared Organization Utility Bar -->
        <?php include __DIR__ . '/_org-utility-bar.php'; ?>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-full">
                <!-- Header -->
                <div class="govuk-grid-row govuk-!-margin-bottom-4">
                    <div class="govuk-grid-column-two-thirds">
                        <h2 class="govuk-heading-l govuk-!-margin-bottom-1">
                            <i class="fa-solid fa-shield-halved govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                            Audit Log
                        </h2>
                        <p class="govuk-body"><?= number_format($totalCount) ?> entries found</p>
                    </div>
                    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                        <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/audit-log/export<?= http_build_query($filters) ? '?' . http_build_query(array_filter($filters)) : '' ?>"
                           class="govuk-button govuk-button--secondary" data-module="govuk-button">
                            <i class="fa-solid fa-download govuk-!-margin-right-2" aria-hidden="true"></i>
                            Export CSV
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg civicone-panel-border-blue">
                    <form method="GET">
                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-one-quarter">
                                <div class="govuk-form-group govuk-!-margin-bottom-2">
                                    <label class="govuk-label govuk-label--s" for="action">Action Type</label>
                                    <select name="action" id="action" class="govuk-select">
                                        <option value="">All Actions</option>
                                        <?php foreach ($actionSummary as $action): ?>
                                            <option value="<?= htmlspecialchars($action['action']) ?>"
                                                    <?= ($filters['action'] ?? '') === $action['action'] ? 'selected' : '' ?>>
                                                <?= \Nexus\Services\AuditLogService::getActionLabel($action['action']) ?> (<?= $action['count'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-quarter">
                                <div class="govuk-form-group govuk-!-margin-bottom-2">
                                    <label class="govuk-label govuk-label--s" for="user_id">User</label>
                                    <select name="user_id" id="user_id" class="govuk-select">
                                        <option value="">All Users</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?= $member['user_id'] ?>"
                                                    <?= ($filters['userId'] ?? '') == $member['user_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($member['display_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-quarter">
                                <div class="govuk-form-group govuk-!-margin-bottom-2">
                                    <label class="govuk-label govuk-label--s" for="start_date">From Date</label>
                                    <input type="date" name="start_date" id="start_date" class="govuk-input"
                                           value="<?= htmlspecialchars($filters['startDate'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="govuk-grid-column-one-quarter">
                                <div class="govuk-form-group govuk-!-margin-bottom-2">
                                    <label class="govuk-label govuk-label--s" for="end_date">To Date</label>
                                    <input type="date" name="end_date" id="end_date" class="govuk-input"
                                           value="<?= htmlspecialchars($filters['endDate'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="govuk-button-group">
                            <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">
                                <i class="fa-solid fa-filter govuk-!-margin-right-2" aria-hidden="true"></i>
                                Filter
                            </button>
                            <?php if (array_filter($filters)): ?>
                                <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/audit-log" class="govuk-link">
                                    Clear filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Log Entries -->
                <?php if (empty($logs)): ?>
                    <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg civicone-panel-border-blue">
                        <p class="govuk-body govuk-!-margin-bottom-4">
                            <i class="fa-solid fa-shield-halved fa-3x civicone-icon-blue" aria-hidden="true"></i>
                        </p>
                        <h3 class="govuk-heading-m">No audit log entries found</h3>
                        <p class="govuk-body govuk-!-margin-bottom-0">
                            Try adjusting your filters or check back later.
                        </p>
                    </div>
                <?php else: ?>
                    <table class="govuk-table">
                        <thead class="govuk-table__head">
                            <tr class="govuk-table__row">
                                <th scope="col" class="govuk-table__header civicone-th-narrow">Type</th>
                                <th scope="col" class="govuk-table__header">Action</th>
                                <th scope="col" class="govuk-table__header">User</th>
                                <th scope="col" class="govuk-table__header">Details</th>
                                <th scope="col" class="govuk-table__header">Date</th>
                            </tr>
                        </thead>
                        <tbody class="govuk-table__body">
                            <?php foreach ($logs as $log):
                                // Determine icon and color
                                $iconClass = 'fa-cog';
                                $iconColor = '#505a5f';
                                if (str_contains($log['action'], 'deposit')) {
                                    $iconClass = 'fa-arrow-down';
                                    $iconColor = '#00703c';
                                } elseif (str_contains($log['action'], 'withdrawal')) {
                                    $iconClass = 'fa-arrow-up';
                                    $iconColor = '#d4351c';
                                } elseif (str_contains($log['action'], 'transfer')) {
                                    $iconClass = 'fa-exchange-alt';
                                    $iconColor = '#1d70b8';
                                } elseif (str_contains($log['action'], 'member')) {
                                    $iconClass = 'fa-user';
                                    $iconColor = '#1d70b8';
                                } elseif (str_contains($log['action'], 'ownership')) {
                                    $iconClass = 'fa-crown';
                                    $iconColor = '#f47738';
                                } elseif (str_contains($log['action'], 'bulk')) {
                                    $iconClass = 'fa-layer-group';
                                    $iconColor = '#912b88';
                                }
                            ?>
                                <tr class="govuk-table__row">
                                    <td class="govuk-table__cell">
                                        <i class="fa-solid <?= $iconClass ?>" style="color: <?= $iconColor ?>; font-size: 18px;" aria-hidden="true"></i>
                                    </td>
                                    <td class="govuk-table__cell">
                                        <strong><?= \Nexus\Services\AuditLogService::getActionLabel($log['action']) ?></strong>
                                    </td>
                                    <td class="govuk-table__cell">
                                        <?php if ($log['user_name']): ?>
                                            <span><?= htmlspecialchars($log['user_name']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($log['target_user_name']): ?>
                                            <i class="fa-solid fa-arrow-right govuk-!-margin-left-1 govuk-!-margin-right-1 civicone-secondary-text" aria-hidden="true"></i>
                                            <span><?= htmlspecialchars($log['target_user_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="govuk-table__cell">
                                        <?php if (!empty($log['details'])): ?>
                                            <?php foreach ($log['details'] as $key => $value):
                                                if (is_array($value)) $value = json_encode($value);
                                                if ($value === null || $value === '') continue;
                                            ?>
                                                <span class="govuk-body-s govuk-!-display-block">
                                                    <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?>:</strong>
                                                    <?= htmlspecialchars($value) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="govuk-hint">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="govuk-table__cell">
                                        <span class="govuk-body-s">
                                            <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                                            <span class="govuk-hint govuk-!-margin-bottom-0"><?= date('g:i A', strtotime($log['created_at'])) ?></span>
                                        </span>
                                        <?php if ($log['ip_address']): ?>
                                            <span class="govuk-body-s govuk-!-display-block govuk-!-margin-top-1">
                                                <i class="fa-solid fa-globe govuk-!-margin-right-1 civicone-secondary-text" aria-hidden="true"></i>
                                                <?= htmlspecialchars($log['ip_address']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="govuk-pagination" role="navigation" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <div class="govuk-pagination__prev">
                                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $page - 1 ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>" rel="prev">
                                        <i class="fa-solid fa-chevron-left govuk-!-margin-right-1" aria-hidden="true"></i>
                                        Previous
                                    </a>
                                </div>
                            <?php endif; ?>

                            <ul class="govuk-pagination__list">
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <li class="govuk-pagination__item <?= $i === $page ? 'govuk-pagination__item--current' : '' ?>">
                                        <a class="govuk-link govuk-pagination__link" href="?page=<?= $i ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>"
                                           <?= $i === $page ? 'aria-current="page"' : '' ?>>
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>

                            <?php if ($page < $totalPages): ?>
                                <div class="govuk-pagination__next">
                                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $page + 1 ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>" rel="next">
                                        Next
                                        <i class="fa-solid fa-chevron-right govuk-!-margin-left-1" aria-hidden="true"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
