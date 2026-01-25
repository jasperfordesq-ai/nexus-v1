<?php
/**
 * Super Admin: Global User Directory
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = 'Global User Directory';
$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/super-admin">Platform Master</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Users</li>
    </ol>
</nav>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-full">
        <div class="civicone-flex-between govuk-!-margin-bottom-6">
            <h1 class="govuk-heading-xl govuk-!-margin-bottom-0">Global User Directory</h1>
            <a href="<?= $basePath ?>/super-admin" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                Back to Dashboard
            </a>
        </div>

        <table class="govuk-table" aria-label="All users across tenants">
            <caption class="govuk-table__caption govuk-visually-hidden">Users registered on all platform tenants</caption>
            <thead class="govuk-table__head">
                <tr class="govuk-table__row">
                    <th scope="col" class="govuk-table__header">ID</th>
                    <th scope="col" class="govuk-table__header">User</th>
                    <th scope="col" class="govuk-table__header">Tenant</th>
                    <th scope="col" class="govuk-table__header">Role</th>
                    <th scope="col" class="govuk-table__header">Status</th>
                    <th scope="col" class="govuk-table__header">Joined</th>
                    <th scope="col" class="govuk-table__header govuk-table__header--numeric">Action</th>
                </tr>
            </thead>
            <tbody class="govuk-table__body">
                <?php foreach ($users as $u): ?>
                    <tr class="govuk-table__row">
                        <td class="govuk-table__cell">
                            <span class="govuk-body-s civicone-secondary-text">#<?= $u['id'] ?></span>
                        </td>
                        <td class="govuk-table__cell">
                            <strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong><br>
                            <span class="govuk-body-s civicone-secondary-text"><?= htmlspecialchars($u['email']) ?></span>
                        </td>
                        <td class="govuk-table__cell">
                            <?php if ($u['tenant_id'] == 1): ?>
                                <strong class="govuk-tag govuk-tag--purple">Platform</strong>
                            <?php else: ?>
                                <strong class="govuk-tag govuk-tag--light-blue"><?= htmlspecialchars($u['tenant_name'] ?? 'Unknown') ?></strong><br>
                                <span class="govuk-body-s civicone-secondary-text">/<?= htmlspecialchars($u['tenant_slug'] ?? '') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="govuk-table__cell">
                            <span class="govuk-tag govuk-tag--grey"><?= htmlspecialchars($u['role']) ?></span>
                        </td>
                        <td class="govuk-table__cell">
                            <?php if (!empty($u['is_approved'])): ?>
                                <strong class="govuk-tag govuk-tag--green">Active</strong>
                            <?php else: ?>
                                <strong class="govuk-tag govuk-tag--yellow">Pending</strong>
                            <?php endif; ?>
                        </td>
                        <td class="govuk-table__cell">
                            <?= date('M j, Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td class="govuk-table__cell govuk-table__cell--numeric">
                            <div class="govuk-button-group civicone-justify-end civicone-m-0">
                                <?php if (empty($u['is_approved'])): ?>
                                    <form action="<?= $basePath ?>/super-admin/users/approve" method="POST" class="civicone-m-0">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0 civicone-btn-sm" data-module="govuk-button">
                                            Approve
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form action="<?= $basePath ?>/super-admin/users/delete" method="POST" onsubmit="return confirm('Are you sure you want to PERMANENTLY delete this user? This cannot be undone.');" class="civicone-m-0">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0 civicone-btn-sm" data-module="govuk-button">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
