<?php
/**
 * Federated Group Detail
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Federated Group";
$hideHero = true;

Nexus\Core\SEO::setTitle(($group['name'] ?? 'Group') . ' - Federated');
Nexus\Core\SEO::setDescription('Group details from a partner timebank in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$group = $group ?? [];
$canJoin = $canJoin ?? false;
$isMember = $isMember ?? false;
$membershipStatus = $membershipStatus ?? null;

$creatorName = trim(($group['creator_first_name'] ?? '') . ' ' . ($group['creator_last_name'] ?? '')) ?: 'Unknown';
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
                <!-- Group Card -->
                <article class="govuk-!-padding-6 civicone-article-blue" aria-labelledby="group-title">
                    <!-- Badge -->
                    <div class="govuk-!-margin-bottom-4">
                        <span class="govuk-tag govuk-tag--grey">
                            <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= htmlspecialchars($group['tenant_name'] ?? 'Partner Timebank') ?>
                        </span>
                    </div>

                    <h1 class="govuk-heading-xl govuk-!-margin-bottom-6" id="group-title">
                        <?= htmlspecialchars($group['name'] ?? 'Untitled Group') ?>
                    </h1>

                    <!-- Group Stats -->
                    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">
                                <i class="fa-solid fa-users govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                                Members
                            </dt>
                            <dd class="govuk-summary-list__value"><?= (int)($group['member_count'] ?? 0) ?></dd>
                        </div>

                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">
                                <i class="fa-solid fa-user govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                                Created By
                            </dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($creatorName) ?></dd>
                        </div>

                        <?php if (!empty($group['created_at'])): ?>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">
                                    <i class="fa-solid fa-calendar govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                                    Created
                                </dt>
                                <dd class="govuk-summary-list__value">
                                    <time datetime="<?= date('c', strtotime($group['created_at'])) ?>">
                                        <?= date('M j, Y', strtotime($group['created_at'])) ?>
                                    </time>
                                </dd>
                            </div>
                        <?php endif; ?>
                    </dl>

                    <!-- Description -->
                    <?php if (!empty($group['description'])): ?>
                        <h2 class="govuk-heading-m">
                            <i class="fa-solid fa-align-left govuk-!-margin-right-2 civicone-secondary-text" aria-hidden="true"></i>
                            About This Group
                        </h2>
                        <p class="govuk-body-l govuk-!-margin-bottom-6">
                            <?= nl2br(htmlspecialchars($group['description'])) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Membership Section -->
                    <h2 class="govuk-heading-m">
                        <i class="fa-solid fa-user-plus govuk-!-margin-right-2 civicone-secondary-text" aria-hidden="true"></i>
                        Membership
                    </h2>

                    <?php if ($isMember): ?>
                        <div class="govuk-!-margin-bottom-4" role="status" aria-live="polite">
                            <?php if ($membershipStatus === 'pending'): ?>
                                <span class="govuk-tag govuk-tag--yellow">
                                    <i class="fa-solid fa-clock govuk-!-margin-right-1" aria-hidden="true"></i>
                                    Membership Pending Approval
                                </span>
                            <?php else: ?>
                                <span class="govuk-tag govuk-tag--green">
                                    <i class="fa-solid fa-check-circle govuk-!-margin-right-1" aria-hidden="true"></i>
                                    You're a Member
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($membershipStatus === 'approved'): ?>
                            <form action="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>/leave" method="POST" onsubmit="return confirm('Are you sure you want to leave this group?');">
                                <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($group['tenant_id'] ?? '') ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">
                                    <i class="fa-solid fa-sign-out-alt govuk-!-margin-right-2" aria-hidden="true"></i>
                                    Leave Group
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="govuk-hint">
                                Your membership request is awaiting approval from the group admin.
                            </p>
                        <?php endif; ?>

                    <?php elseif ($canJoin): ?>
                        <form action="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>/join" method="POST">
                            <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($group['tenant_id'] ?? '') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="govuk-button" data-module="govuk-button">
                                <i class="fa-solid fa-user-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                                <?= !empty($group['requires_approval']) ? 'Request to Join' : 'Join Group' ?>
                            </button>
                        </form>
                        <?php if (!empty($group['requires_approval'])): ?>
                            <p class="govuk-hint govuk-!-margin-top-2">
                                <i class="fa-solid fa-info-circle govuk-!-margin-right-1" aria-hidden="true"></i>
                                This group requires approval from a group admin.
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="govuk-button govuk-button--disabled" disabled aria-disabled="true">
                            <i class="fa-solid fa-lock govuk-!-margin-right-2" aria-hidden="true"></i>
                            Membership Not Available
                        </button>
                        <p class="govuk-hint govuk-!-margin-top-2">
                            Enable federation features in your settings to join groups from partner timebanks.
                        </p>
                    <?php endif; ?>
                </article>

                <!-- Privacy Notice -->
                <div class="govuk-inset-text govuk-!-margin-top-6">
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <i class="fa-solid fa-shield-halved govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                        <strong>Federated Group</strong> — This group is hosted by <strong><?= htmlspecialchars($group['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        When you join, your basic profile information will be visible to other group members.
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Offline indicator handled by civicone-common.js -->

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
