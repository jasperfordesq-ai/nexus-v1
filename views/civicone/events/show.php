<?php
/**
 * CivicOne View: Event Details
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = $event['title'];
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/events">Events</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page"><?= htmlspecialchars($event['title']) ?></li>
    </ol>
</nav>

<a href="<?= $basePath ?>/events" class="govuk-back-link govuk-!-margin-bottom-6">Back to Events</a>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'rsvp_saved'): ?>
    <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__header">
            <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
        </div>
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">
                <i class="fa-solid fa-check-circle govuk-!-margin-right-2" aria-hidden="true"></i>
                Your RSVP has been updated!
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2"><?= htmlspecialchars($event['title']) ?></h1>
        <p class="govuk-body-l" style="color: #505a5f;">
            <i class="fa-solid fa-user govuk-!-margin-right-1" aria-hidden="true"></i>
            Hosted by <?= htmlspecialchars($event['user_name'] ?? 'Community Member') ?>
        </p>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <?php if (isset($_SESSION['user_id']) && $event['user_id'] == $_SESSION['user_id']): ?>
            <a href="<?= $basePath ?>/events/edit/<?= $event['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-edit govuk-!-margin-right-1" aria-hidden="true"></i> Edit Event
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="govuk-grid-row">
    <!-- Main Content -->
    <div class="govuk-grid-column-two-thirds">

        <!-- Event Description -->
        <div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-m">About this event</h2>
            <div class="govuk-body">
                <?= nl2br(htmlspecialchars($event['description'])) ?>
            </div>
        </div>

        <!-- Event Details -->
        <div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-info-circle govuk-!-margin-right-2" aria-hidden="true"></i>
                Event Details
            </h2>
            <dl class="govuk-summary-list">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">
                        <i class="fa-regular fa-clock govuk-!-margin-right-1" aria-hidden="true"></i> Date & Time
                    </dt>
                    <dd class="govuk-summary-list__value">
                        <time datetime="<?= $event['start_time'] ?>">
                            <?= date('l, F j, Y \a\t g:i A', strtotime($event['start_time'])) ?>
                        </time>
                    </dd>
                </div>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">
                        <i class="fa-solid fa-location-dot govuk-!-margin-right-1" aria-hidden="true"></i> Location
                    </dt>
                    <dd class="govuk-summary-list__value"><?= htmlspecialchars($event['location']) ?></dd>
                </div>
                <?php if (!empty($event['category_name'])): ?>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">
                        <i class="fa-solid fa-tag govuk-!-margin-right-1" aria-hidden="true"></i> Category
                    </dt>
                    <dd class="govuk-summary-list__value">
                        <span class="govuk-tag govuk-tag--blue"><?= htmlspecialchars($event['category_name']) ?></span>
                    </dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Social Interactions -->
        <?php
        $targetType = 'event';
        $targetId = $event['id'];
        include dirname(__DIR__) . '/partials/social_interactions.php';
        ?>

        <!-- Attendees -->
        <?php if (!empty($attendees)): ?>
        <div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                Going (<?= count($attendees) ?>)
            </h2>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                <?php foreach ($attendees as $att): ?>
                    <a href="<?= $basePath ?>/profile/<?= $att['user_id'] ?>" title="<?= htmlspecialchars($att['name']) ?>">
                        <img src="<?= $att['avatar_url'] ?? '/assets/img/defaults/default_avatar.webp' ?>"
                             alt="<?= htmlspecialchars($att['name']) ?>"
                             style="width: 40px; height: 40px; border-radius: 50%;">
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Sidebar -->
    <div class="govuk-grid-column-one-third">

        <!-- RSVP Card -->
        <div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #00703c;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-calendar-check govuk-!-margin-right-2" aria-hidden="true"></i>
                Your RSVP
            </h2>

            <?php if (isset($_SESSION['user_id'])): ?>
                <form action="<?= $basePath ?>/events/rsvp" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">

                    <div class="govuk-button-group">
                        <button type="submit" name="status" value="going"
                                class="govuk-button <?= $myStatus === 'going' ? '' : 'govuk-button--secondary' ?>"
                                data-module="govuk-button">
                            <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                            Going
                        </button>
                        <button type="submit" name="status" value="declined"
                                class="govuk-button <?= $myStatus === 'declined' ? 'govuk-button--warning' : 'govuk-button--secondary' ?>"
                                data-module="govuk-button">
                            <i class="fa-solid fa-times govuk-!-margin-right-1" aria-hidden="true"></i>
                            Decline
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <p class="govuk-body">
                    <a href="<?= $basePath ?>/login" class="govuk-link">Sign in</a> to RSVP to this event.
                </p>
            <?php endif; ?>

            <?php if (!empty($canInvite)): ?>
                <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">
                <a href="<?= $basePath ?>/events/invite/<?= $event['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button" style="width: 100%;">
                    <i class="fa-solid fa-envelope govuk-!-margin-right-1" aria-hidden="true"></i>
                    Invite Members
                </a>
            <?php endif; ?>
        </div>

        <!-- Event Host Card -->
        <div class="govuk-!-padding-6" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-m">
                <i class="fa-solid fa-user govuk-!-margin-right-2" aria-hidden="true"></i>
                Event Host
            </h2>
            <p class="govuk-body">
                <a href="<?= $basePath ?>/profile/<?= $event['user_id'] ?>" class="govuk-link">
                    <?= htmlspecialchars($event['user_name'] ?? 'Unknown') ?>
                </a>
            </p>
        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
