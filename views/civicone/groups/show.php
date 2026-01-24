<?php
/**
 * CivicOne Group Details View
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = htmlspecialchars($group['name']) . ' - Nexus TimeBank';
require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$hasSubHubs = !empty($subGroups);
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/groups">Hubs</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page"><?= htmlspecialchars($group['name']) ?></li>
    </ol>
</nav>

<!-- Group Header -->
<div class="govuk-!-margin-bottom-6 govuk-!-padding-6 civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-tag govuk-tag--blue govuk-!-margin-bottom-3">Local Hub</span>
            <h1 class="govuk-heading-xl govuk-!-margin-bottom-2"><?= htmlspecialchars($group['name']) ?></h1>
            <p class="govuk-body-l govuk-!-margin-bottom-0">
                <strong><?= count($members) ?></strong> Members · Managed by <strong><?= htmlspecialchars($group['owner_name'] ?? 'Organizer') ?></strong>
            </p>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-text-align-right">
            <?php if ($isMember): ?>
                <form action="<?= $basePath ?>/groups/leave" method="POST" class="ajax-form">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                    <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">
                        Leave Hub
                    </button>
                </form>
            <?php else: ?>
                <form action="<?= $basePath ?>/groups/join" method="POST" class="ajax-form">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                    <button type="submit" class="govuk-button" data-module="govuk-button">
                        <i class="fa-solid fa-user-plus govuk-!-margin-right-1" aria-hidden="true"></i> Join Hub
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Content with Tabs -->
<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <div class="govuk-tabs" data-module="govuk-tabs">
            <h2 class="govuk-tabs__title">Hub content</h2>
            <ul class="govuk-tabs__list">
                <?php if ($hasSubHubs): ?>
                    <li class="govuk-tabs__list-item govuk-tabs__list-item--selected">
                        <a class="govuk-tabs__tab" href="#tab-subhubs">Sub-Hubs</a>
                    </li>
                    <li class="govuk-tabs__list-item">
                        <a class="govuk-tabs__tab" href="#tab-feed">Activity</a>
                    </li>
                <?php else: ?>
                    <li class="govuk-tabs__list-item govuk-tabs__list-item--selected">
                        <a class="govuk-tabs__tab" href="#tab-feed">Activity</a>
                    </li>
                <?php endif; ?>
                <li class="govuk-tabs__list-item">
                    <a class="govuk-tabs__tab" href="#tab-about">About</a>
                </li>
                <li class="govuk-tabs__list-item">
                    <a class="govuk-tabs__tab" href="#tab-members">Members (<?= count($members) ?>)</a>
                </li>
            </ul>

            <?php if ($hasSubHubs): ?>
            <!-- Tab: Sub-Hubs -->
            <div class="govuk-tabs__panel" id="tab-subhubs">
                <h2 class="govuk-heading-l">Sub-Hubs</h2>
                <div class="govuk-grid-row">
                    <?php foreach ($subGroups as $sub): ?>
                        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-6">
                            <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6;">
                                <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                                    <a href="<?= $basePath ?>/groups/<?= $sub['id'] ?>" class="govuk-link"><?= htmlspecialchars($sub['name']) ?></a>
                                </h3>
                                <a href="<?= $basePath ?>/groups/<?= $sub['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                    Visit Hub
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tab: Activity -->
            <div class="govuk-tabs__panel <?= $hasSubHubs ? 'govuk-tabs__panel--hidden' : '' ?>" id="tab-feed">
                <h2 class="govuk-heading-l">Recent Activity</h2>
                <div class="govuk-inset-text">
                    <p class="govuk-body">There is no recent activity in this hub.</p>
                </div>
            </div>

            <!-- Tab: About -->
            <div class="govuk-tabs__panel govuk-tabs__panel--hidden" id="tab-about">
                <h2 class="govuk-heading-l">About this Hub</h2>
                <p class="govuk-body"><?= nl2br(htmlspecialchars($group['description'])) ?></p>
            </div>

            <!-- Tab: Members -->
            <div class="govuk-tabs__panel govuk-tabs__panel--hidden" id="tab-members">
                <h2 class="govuk-heading-l">Members</h2>
                <?php if (empty($members)): ?>
                    <p class="govuk-body">No members yet.</p>
                <?php else: ?>
                    <div class="govuk-grid-row">
                        <?php foreach ($members as $mem): ?>
                            <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
                                <div class="govuk-!-padding-3 govuk-!-text-align-centre" style="border: 1px solid #b1b4b6;">
                                    <?php if (!empty($mem['avatar_url'])): ?>
                                        <img src="<?= htmlspecialchars($mem['avatar_url']) ?>" alt="" style="width: 60px; height: 60px; border-radius: 50%; margin-bottom: 0.5rem;">
                                    <?php else: ?>
                                        <div class="govuk-!-margin-bottom-2 civicone-panel-bg" style="width: 60px; height: 60px; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-user" style="font-size: 1.5rem; color: #505a5f;" aria-hidden="true"></i>
                                        </div>
                                    <?php endif; ?>
                                    <p class="govuk-body-s govuk-!-margin-bottom-1">
                                        <a href="<?= $basePath ?>/profile/<?= $mem['id'] ?>" class="govuk-link"><?= htmlspecialchars($mem['name']) ?></a>
                                    </p>
                                    <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;"><?= htmlspecialchars($mem['location'] ?? 'Member') ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4 civicone-panel-bg">
            <h2 class="govuk-heading-m">Hub Manager</h2>
            <p class="govuk-body govuk-!-margin-bottom-1"><strong><?= htmlspecialchars($group['owner_name'] ?? 'Organizer') ?></strong></p>
            <p class="govuk-body-s" style="color: #505a5f;">Organizer</p>
            <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" disabled aria-label="Contact hub manager (coming soon)">
                Contact (Coming Soon)
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
