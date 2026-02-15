<?php
/**
 * User Monitoring Dashboard - CivicOne Theme (GOV.UK)
 * Manage user messaging restrictions and monitoring flags
 * Path: views/civicone/admin-legacy/broker-controls/monitoring/index.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$users = $users ?? [];
$filter = $filter ?? 'all';
$page = $page ?? 1;
$totalCount = $total_count ?? 0;
$totalPages = $total_pages ?? 1;
$stats = $stats ?? [];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require __DIR__ . '/../../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content" role="main">

        <a href="<?= $basePath ?>/admin-legacy/broker-controls" class="govuk-back-link">Back to Broker Controls</a>

        <h1 class="govuk-heading-xl">User Monitoring</h1>
        <p class="govuk-body-l">Manage user messaging restrictions and monitoring.</p>

        <?php if ($flashSuccess): ?>
        <div class="govuk-notification-banner govuk-notification-banner--success" role="alert">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title">Success</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading"><?= htmlspecialchars($flashSuccess) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #d4351c; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['restricted'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Messaging Disabled</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #f47738; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['monitored'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">Under Monitoring</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #1d70b8; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['new_members'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">New Members (30d)</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-quarter">
                <div class="govuk-panel" style="background: #00703c; padding: 15px;">
                    <div style="font-size: 36px; font-weight: bold; color: white;">
                        <?= number_format($stats['first_contacts_today'] ?? 0) ?>
                    </div>
                    <p class="govuk-body" style="color: white; margin: 0;">First Contacts Today</p>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <nav class="govuk-tabs" data-module="govuk-tabs">
            <ul class="govuk-tabs__list">
                <li class="govuk-tabs__list-item <?= $filter === 'all' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?filter=all">All Users</a>
                </li>
                <li class="govuk-tabs__list-item <?= $filter === 'restricted' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?filter=restricted">Restricted</a>
                </li>
                <li class="govuk-tabs__list-item <?= $filter === 'monitored' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?filter=monitored">Monitored</a>
                </li>
                <li class="govuk-tabs__list-item <?= $filter === 'new_members' ? 'govuk-tabs__list-item--selected' : '' ?>">
                    <a class="govuk-tabs__tab" href="?filter=new_members">New Members</a>
                </li>
            </ul>
        </nav>

        <?php if (empty($users)): ?>
        <div class="govuk-panel" style="background: #f3f2f1; color: #0b0c0c;">
            <h2 class="govuk-panel__title" style="color: #0b0c0c;">No users found</h2>
            <div class="govuk-panel__body" style="color: #505a5f;">
                No users match the current filter.
            </div>
        </div>
        <?php else: ?>

        <table class="govuk-table">
            <caption class="govuk-table__caption govuk-table__caption--m">
                <?= $totalCount ?> user<?= $totalCount !== 1 ? 's' : '' ?>
            </caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">User</th>
                    <th scope="col" class="govuk-table__header">Joined</th>
                    <th scope="col" class="govuk-table__header">Status</th>
                    <th scope="col" class="govuk-table__header">First Contacts</th>
                    <th scope="col" class="govuk-table__header">Actions</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                <?php foreach ($users as $user): ?>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell">
                        <strong><?= htmlspecialchars($user['name'] ?? 'Unknown') ?></strong><br>
                        <span style="color: #505a5f;"><?= htmlspecialchars($user['email'] ?? '') ?></span>
                    </td>
                    <td class="govuk-table__cell">
                        <?= isset($user['created_at']) ? date('j M Y', strtotime($user['created_at'])) : '-' ?>
                        <?php
                        $daysAgo = isset($user['created_at']) ? floor((time() - strtotime($user['created_at'])) / 86400) : 999;
                        if ($daysAgo <= 30):
                        ?>
                        <br><strong class="govuk-tag govuk-tag--blue">New</strong>
                        <?php endif; ?>
                    </td>
                    <td class="govuk-table__cell">
                        <?php if (!empty($user['messaging_disabled'])): ?>
                        <strong class="govuk-tag govuk-tag--red">Messaging Disabled</strong>
                        <?php elseif (!empty($user['under_monitoring'])): ?>
                        <strong class="govuk-tag govuk-tag--orange">Monitored</strong>
                        <?php else: ?>
                        <strong class="govuk-tag govuk-tag--green">Active</strong>
                        <?php endif; ?>
                    </td>
                    <td class="govuk-table__cell">
                        <?= $user['first_contact_count'] ?? 0 ?>
                    </td>
                    <td class="govuk-table__cell">
                        <a href="<?= $basePath ?>/admin-legacy/users/<?= $user['id'] ?>" class="govuk-link">View profile</a>
                        <br>
                        <details class="govuk-details govuk-!-margin-top-2" data-module="govuk-details">
                            <summary class="govuk-details__summary">
                                <span class="govuk-details__summary-text">Set monitoring</span>
                            </summary>
                            <div class="govuk-details__text">
                                <form action="<?= $basePath ?>/admin-legacy/broker-controls/monitoring/<?= $user['id'] ?>" method="POST">
                                    <?= Csrf::input() ?>
                                    <div class="govuk-checkboxes govuk-checkboxes--small govuk-!-margin-bottom-2">
                                        <div class="govuk-checkboxes__item">
                                            <input class="govuk-checkboxes__input" id="disable-<?= $user['id'] ?>" name="messaging_disabled" type="checkbox" value="1"
                                                   <?= !empty($user['messaging_disabled']) ? 'checked' : '' ?>>
                                            <label class="govuk-label govuk-checkboxes__label" for="disable-<?= $user['id'] ?>">
                                                Disable messaging
                                            </label>
                                        </div>
                                        <div class="govuk-checkboxes__item">
                                            <input class="govuk-checkboxes__input" id="monitor-<?= $user['id'] ?>" name="under_monitoring" type="checkbox" value="1"
                                                   <?= !empty($user['under_monitoring']) ? 'checked' : '' ?>>
                                            <label class="govuk-label govuk-checkboxes__label" for="monitor-<?= $user['id'] ?>">
                                                Enhanced monitoring
                                            </label>
                                        </div>
                                    </div>
                                    <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0">
                                        Save
                                    </button>
                                </form>
                            </div>
                        </details>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <nav class="govuk-pagination" role="navigation" aria-label="results">
            <?php if ($page > 1): ?>
            <div class="govuk-pagination__prev">
                <a class="govuk-link govuk-pagination__link" href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>">
                    <span class="govuk-pagination__link-title">Previous</span>
                </a>
            </div>
            <?php endif; ?>
            <ul class="govuk-pagination__list">
                <li class="govuk-pagination__item">
                    <span class="govuk-pagination__link-label">Page <?= $page ?> of <?= $totalPages ?></span>
                </li>
            </ul>
            <?php if ($page < $totalPages): ?>
            <div class="govuk-pagination__next">
                <a class="govuk-link govuk-pagination__link" href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>">
                    <span class="govuk-pagination__link-title">Next</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

<?php require __DIR__ . '/../../../layouts/civicone/footer.php'; ?>
