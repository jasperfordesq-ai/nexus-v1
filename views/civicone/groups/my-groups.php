<?php
/**
 * CivicOne My Groups - User's Group Memberships
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = "My Hubs";
$pageSubtitle = "Hubs you have joined";
$hideHero = true;

require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'My Hubs']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
            My Hubs
        </h1>
        <p class="govuk-body-l">Community hubs you have joined</p>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <div class="govuk-button-group civicone-justify-end">
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
                <div class="govuk-!-padding-0 civicone-card-bordered">
                    <?php
                    $displayImage = !empty($group['cover_image_url']) ? $group['cover_image_url'] : ($group['image_url'] ?? '');
                    ?>
                    <?php if (!empty($displayImage)): ?>
                        <div class="civicone-card-image-area" style="background-image: url('<?= htmlspecialchars($displayImage) ?>');">
                            <span class="govuk-tag govuk-tag--green civicone-tag-positioned">MEMBER</span>
                        </div>
                    <?php else: ?>
                        <div class="govuk-!-padding-4 govuk-!-text-align-centre civicone-card-header-blue">
                            <i class="fa-solid fa-users-rectangle civicone-card-icon-large" aria-hidden="true"></i>
                            <span class="govuk-tag govuk-tag--green civicone-tag-positioned">MEMBER</span>
                        </div>
                    <?php endif; ?>
                    <div class="govuk-!-padding-4">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="govuk-link"><?= htmlspecialchars($group['name']) ?></a>
                        </h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-3 civicone-secondary-text">
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
