<?php
/**
 * CivicOne My Groups - User's Group Memberships
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = "My Hubs";
$pageSubtitle = "Hubs you have joined";
$hideHero = true;

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">My Hubs</li>
    </ol>
</nav>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
            My Hubs
        </h1>
        <p class="govuk-body-l">Community hubs you have joined</p>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <div class="govuk-button-group" style="justify-content: flex-end;">
            <a href="<?= $basePath ?>/groups" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-compass govuk-!-margin-right-1" aria-hidden="true"></i> Browse All Hubs
            </a>
        </div>
    </div>
</div>

<?php if (empty($myGroups)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <i class="fa-solid fa-building govuk-!-margin-right-2" aria-hidden="true"></i>
            <strong>No hubs yet</strong>
        </p>
        <p class="govuk-body govuk-!-margin-bottom-4">You haven't joined any community hubs. Explore and find groups that match your interests!</p>
        <a href="<?= $basePath ?>/groups" class="govuk-button govuk-button--start" data-module="govuk-button">
            Browse Hubs
            <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
            </svg>
        </a>
    </div>
<?php else: ?>
    <div class="govuk-grid-row">
        <?php foreach ($myGroups as $group): ?>
            <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                <div class="govuk-!-padding-0" style="border: 1px solid #b1b4b6;">
                    <?php
                    $displayImage = !empty($group['cover_image_url']) ? $group['cover_image_url'] : ($group['image_url'] ?? '');
                    ?>
                    <?php if (!empty($displayImage)): ?>
                        <div style="height: 120px; background-image: url('<?= htmlspecialchars($displayImage) ?>'); background-size: cover; background-position: center; position: relative;">
                            <span class="govuk-tag govuk-tag--green" style="position: absolute; top: 0.5rem; right: 0.5rem;">MEMBER</span>
                        </div>
                    <?php else: ?>
                        <div class="govuk-!-padding-4 govuk-!-text-align-centre" style="height: 120px; background: #1d70b8; display: flex; align-items: center; justify-content: center; position: relative;">
                            <i class="fa-solid fa-users-rectangle" aria-hidden="true" style="font-size: 2.5rem; color: white;"></i>
                            <span class="govuk-tag govuk-tag--green" style="position: absolute; top: 0.5rem; right: 0.5rem;">MEMBER</span>
                        </div>
                    <?php endif; ?>
                    <div class="govuk-!-padding-4">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="govuk-link"><?= htmlspecialchars($group['name']) ?></a>
                        </h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-3" style="color: #505a5f;">
                            <?= htmlspecialchars(substr($group['description'] ?? 'A community hub for members to connect and collaborate.', 0, 80)) ?>...
                        </p>
                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-one-half">
                                <p class="govuk-body-s govuk-!-margin-bottom-0">
                                    <i class="fa-solid fa-user-group govuk-!-margin-right-1" aria-hidden="true"></i>
                                    <?= $group['member_count'] ?? 0 ?> members
                                </p>
                            </div>
                            <div class="govuk-grid-column-one-half govuk-!-text-align-right">
                                <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="govuk-link govuk-body-s">
                                    Enter <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
