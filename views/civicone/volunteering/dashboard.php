<?php
/**
 * CivicOne View: Volunteering Dashboard
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Volunteering Dashboard';
require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/volunteering">Volunteering</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Dashboard</li>
    </ol>
</nav>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-hands-helping govuk-!-margin-right-2" aria-hidden="true"></i>
            Volunteering Dashboard
        </h1>
        <p class="govuk-body-l">Manage your organization and opportunities</p>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/volunteering" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i>
            Back to Opportunities
        </a>
        <?php if (empty($myOrgs)): ?>
            <a href="<?= $basePath ?>/volunteering/import-from-profile" class="govuk-button" data-module="govuk-button"
               onclick="return confirm('Create organization from your profile details?');">
                <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i>
                Register Organization
            </a>
        <?php else: ?>
            <a href="<?= $basePath ?>/volunteering/opp/create" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i>
                Post Opportunity
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Organizations Section -->
<?php if (!empty($myOrgs)): ?>
<h2 class="govuk-heading-m">
    <i class="fa-solid fa-building govuk-!-margin-right-2" aria-hidden="true"></i>
    My Organizations
</h2>
<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <?php foreach ($myOrgs as $org): ?>
    <div class="govuk-grid-column-one-third govuk-!-margin-bottom-4">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8; height: 100%;">
            <h3 class="govuk-heading-s govuk-!-margin-bottom-2"><?= htmlspecialchars($org['name']) ?></h3>
            <p class="govuk-body-s govuk-!-margin-bottom-4" style="color: #505a5f;">
                <?= htmlspecialchars(substr($org['description'] ?? '', 0, 100)) ?><?= strlen($org['description'] ?? '') > 100 ? '...' : '' ?>
            </p>
            <a href="<?= $basePath ?>/volunteering/org/edit/<?= $org['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-pen govuk-!-margin-right-1" aria-hidden="true"></i>
                Edit Organization
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Applicants Section -->
<?php if (!empty($myApplications)): ?>
<h2 class="govuk-heading-m">
    <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
    Applicants
</h2>
<table class="govuk-table govuk-!-margin-bottom-6">
    <thead class="govuk-table__head">
        <tr class="govuk-table__row">
            <th scope="col" class="govuk-table__header">Opportunity</th>
            <th scope="col" class="govuk-table__header">Applicant</th>
            <th scope="col" class="govuk-table__header">Status</th>
            <th scope="col" class="govuk-table__header">Action</th>
        </tr>
    </thead>
    <tbody class="govuk-table__body">
        <?php foreach ($myApplications as $app): ?>
        <tr class="govuk-table__row">
            <td class="govuk-table__cell">
                <strong><?= htmlspecialchars($app['opp_title']) ?></strong>
            </td>
            <td class="govuk-table__cell">
                <?= htmlspecialchars($app['applicant_name'] ?? 'User #' . $app['user_id']) ?>
            </td>
            <td class="govuk-table__cell">
                <?php
                $tagColor = match ($app['status']) {
                    'approved' => 'govuk-tag--green',
                    'declined' => 'govuk-tag--red',
                    default => 'govuk-tag--yellow'
                };
                ?>
                <span class="govuk-tag <?= $tagColor ?>"><?= ucfirst($app['status']) ?></span>
            </td>
            <td class="govuk-table__cell">
                <?php if ($app['status'] === 'pending'): ?>
                <form action="<?= $basePath ?>/volunteering/application/update" method="POST" style="display: inline;">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                    <button type="submit" name="status" value="approved" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" style="padding: 5px 10px;">
                        Approve
                    </button>
                    <button type="submit" name="status" value="declined" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" style="padding: 5px 10px;">
                        Decline
                    </button>
                </form>
                <?php else: ?>
                <span style="color: #505a5f;">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Manage Opportunities Section -->
<?php if (!empty($myOrgs)): ?>
<h2 class="govuk-heading-m">
    <i class="fa-solid fa-heart govuk-!-margin-right-2" aria-hidden="true"></i>
    Manage Opportunities
</h2>

<?php if (empty($myOpps)): ?>
    <div class="govuk-inset-text govuk-!-margin-bottom-6">
        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">No opportunities yet</h3>
        <p class="govuk-body govuk-!-margin-bottom-2">You haven't posted any volunteer opportunities yet.</p>
        <a href="<?= $basePath ?>/volunteering/opp/create" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i>
            Post Your First Opportunity
        </a>
    </div>
<?php else: ?>
    <ul class="govuk-list govuk-!-margin-bottom-6">
        <?php foreach ($myOpps as $opp): ?>
        <li class="govuk-!-padding-3 govuk-!-margin-bottom-2" style="border-left: 4px solid #1d70b8; background: #f8f8f8; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                    <a href="<?= $basePath ?>/volunteering/<?= $opp['id'] ?>" class="govuk-link">
                        <?= htmlspecialchars($opp['title']) ?>
                    </a>
                </h3>
                <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                    <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($opp['org_name'] ?? '') ?>
                    <?php if (!empty($opp['start_date'])): ?>
                        <span class="govuk-!-margin-left-2">
                            <i class="fa-solid fa-calendar govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= date('M j, Y', strtotime($opp['start_date'])) ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= $basePath ?>/volunteering/opp/edit/<?= $opp['id'] ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                <i class="fa-solid fa-pen govuk-!-margin-right-1" aria-hidden="true"></i>
                Edit
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php endif; ?>

<!-- Empty State for New Users -->
<?php if (empty($myOrgs)): ?>
<div class="govuk-inset-text govuk-!-margin-bottom-6">
    <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
        <i class="fa-solid fa-building govuk-!-margin-right-2" aria-hidden="true"></i>
        Get Started
    </h3>
    <p class="govuk-body govuk-!-margin-bottom-2">
        Register your organization to start posting volunteer opportunities and connecting with community members.
    </p>
    <a href="<?= $basePath ?>/volunteering/import-from-profile" class="govuk-button" data-module="govuk-button"
       onclick="return confirm('Create organization from your profile details?');">
        <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i>
        Register Organization
    </a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
