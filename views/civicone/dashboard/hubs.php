<?php
/**
 * CivicOne Dashboard - My Hubs Page
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 * Template: Account Area Template (Template G)
 */

$hTitle = "My Hubs";
$hSubtitle = "Your community connections";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
require_once dirname(__DIR__) . '/components/govuk/breadcrumbs.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Dashboard', 'href' => $basePath . '/dashboard'],
        ['text' => 'My Hubs']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<!-- Account Area Secondary Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

<!-- GROUPS/HUBS CONTENT -->
<section aria-labelledby="my-hubs-heading" class="govuk-!-margin-bottom-8">
    <div class="govuk-grid-row govuk-!-margin-bottom-4">
        <div class="govuk-grid-column-two-thirds">
            <h2 id="my-hubs-heading" class="govuk-heading-l">
                <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                My Hubs
            </h2>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-text-align-right">
            <a href="<?= $basePath ?>/groups" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-compass govuk-!-margin-right-1" aria-hidden="true"></i> Browse All Hubs
            </a>
        </div>
    </div>

    <?php if (empty($myGroups)): ?>
        <div class="govuk-inset-text">
            <p class="govuk-body-l govuk-!-margin-bottom-2">
                <i class="fa-solid fa-user-group govuk-!-margin-right-2" aria-hidden="true"></i>
                <strong>No hubs joined</strong>
            </p>
            <p class="govuk-body govuk-!-margin-bottom-4">Join a hub to connect with your community.</p>
            <a href="<?= $basePath ?>/groups" class="govuk-button govuk-button--start" data-module="govuk-button">
                Browse Hubs
                <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
                </svg>
            </a>
        </div>
    <?php else: ?>
        <div class="govuk-grid-row">
            <?php foreach ($myGroups as $grp): ?>
                <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
                    <div class="govuk-!-padding-4 civicone-card-bordered-accent">
                        <h3 class="govuk-heading-m govuk-!-margin-bottom-2">
                            <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="govuk-link"><?= htmlspecialchars($grp['name']) ?></a>
                        </h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-3"><?= htmlspecialchars($grp['description'] ?? '') ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-3">
                            <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i>
                            <strong><?= $grp['member_count'] ?? 0 ?></strong> members
                        </p>
                        <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                            Enter Hub
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script src="/assets/js/civicone-dashboard.js"></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
