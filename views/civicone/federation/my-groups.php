<?php
/**
 * My Federated Groups
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "My Federated Groups";
$hideHero = true;

Nexus\Core\SEO::setTitle('My Federated Groups');
Nexus\Core\SEO::setDescription('View and manage your group memberships from partner timebanks.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$groups = $groups ?? [];
?>

<div class="govuk-width-container">
    <!-- Offline Banner -->
    <div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-display-none" id="offlineBanner" role="alert" aria-live="polite" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">
                <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
                No internet connection
            </p>
        </div>
    </div>

    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/groups" class="govuk-back-link govuk-!-margin-top-4">
        Back to Federated Groups
    </a>

    <main class="govuk-main-wrapper govuk-!-padding-top-4" id="main-content" role="main">
        <!-- Page Header -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">
                    <i class="fa-solid fa-user-group govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    My Federated Groups
                </h1>
                <p class="govuk-body-l" style="color: #505a5f;">Groups you've joined from partner timebanks</p>
            </div>
            <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                <a href="<?= $basePath ?>/federation/groups" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-search govuk-!-margin-right-2" aria-hidden="true"></i>
                    Browse Groups
                </a>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="govuk-notification-banner govuk-notification-banner--success" role="status" aria-live="polite" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title">Success</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading"><?= htmlspecialchars($_SESSION['flash_success']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" tabindex="-1" data-module="govuk-error-summary">
                <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body"><?= htmlspecialchars($_SESSION['flash_error']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <?php if (empty($groups)): ?>
                    <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                        <i class="fa-solid fa-people-group fa-3x govuk-!-margin-bottom-4" style="color: #1d70b8;" aria-hidden="true"></i>
                        <h2 class="govuk-heading-m">No Federated Groups Yet</h2>
                        <p class="govuk-body govuk-!-margin-bottom-4">
                            You haven't joined any groups from partner timebanks.<br>
                            Browse available groups to connect with members across the network.
                        </p>
                        <a href="<?= $basePath ?>/federation/groups" class="govuk-button" data-module="govuk-button">
                            <i class="fa-solid fa-search govuk-!-margin-right-2" aria-hidden="true"></i>
                            Browse Federated Groups
                        </a>
                    </div>
                <?php else: ?>
                    <div role="list" aria-label="Your federated groups">
                        <?php foreach ($groups as $group): ?>
                            <article class="govuk-!-padding-4 govuk-!-margin-bottom-4" style="background: #fff; border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;" role="listitem">
                                <div style="display: flex; align-items: flex-start; gap: 16px;">
                                    <div style="width: 48px; height: 48px; border-radius: 50%; background: #1d70b8; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                                            <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>?tenant=<?= $group['tenant_id'] ?>" class="govuk-link">
                                                <?= htmlspecialchars($group['name']) ?>
                                            </a>
                                        </h3>
                                        <p class="govuk-body-s govuk-!-margin-bottom-2" style="color: #505a5f;">
                                            <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                                            <?= htmlspecialchars($group['tenant_name'] ?? 'Partner Timebank') ?>
                                            <span class="govuk-!-margin-left-3">
                                                <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i>
                                                <?= (int)($group['member_count'] ?? 0) ?> members
                                            </span>
                                            <?php if (!empty($group['joined_at'])): ?>
                                                <span class="govuk-!-margin-left-3">
                                                    <i class="fa-solid fa-calendar govuk-!-margin-right-1" aria-hidden="true"></i>
                                                    Joined <time datetime="<?= date('c', strtotime($group['joined_at'])) ?>"><?= date('M j, Y', strtotime($group['joined_at'])) ?></time>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div style="text-align: right;">
                                        <?php if (($group['membership_status'] ?? 'approved') === 'pending'): ?>
                                            <span class="govuk-tag govuk-tag--yellow govuk-!-margin-bottom-2">
                                                <i class="fa-solid fa-clock govuk-!-margin-right-1" aria-hidden="true"></i>
                                                Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="govuk-tag govuk-tag--green govuk-!-margin-bottom-2">
                                                <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                                                Active
                                            </span>
                                        <?php endif; ?>
                                        <br>
                                        <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>?tenant=<?= $group['tenant_id'] ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button" style="font-size: 14px;">
                                            <i class="fa-solid fa-eye govuk-!-margin-right-1" aria-hidden="true"></i>
                                            View
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Federation offline indicator -->
<script>
(function() {
    'use strict';
    var banner = document.getElementById('offlineBanner');
    function updateOffline(offline) {
        if (banner) banner.classList.toggle('govuk-!-display-none', !offline);
    }
    window.addEventListener('online', function() { updateOffline(false); });
    window.addEventListener('offline', function() { updateOffline(true); });
    if (!navigator.onLine) updateOffline(true);
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
