<?php
/**
 * CivicOne Dashboard - My Events Page
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 * Template: Account Area Template (Template G)
 */

$hTitle = "My Events";
$hSubtitle = "Events you're hosting and attending";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/dashboard">Dashboard</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">My Events</li>
    </ol>
</nav>

<!-- Account Area Secondary Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

<!-- EVENTS CONTENT -->
<div class="govuk-grid-row">
    <!-- Hosting -->
    <div class="govuk-grid-column-one-half">
        <section aria-labelledby="hosting-heading" class="govuk-!-margin-bottom-8">
            <div class="govuk-!-margin-bottom-4">
                <h2 id="hosting-heading" class="govuk-heading-l">
                    <i class="fa-solid fa-calendar-star govuk-!-margin-right-2" aria-hidden="true"></i>
                    Hosting
                </h2>
                <a href="<?= $basePath ?>/events/create" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Create Event
                </a>
            </div>

            <?php if (empty($hosting)): ?>
                <div class="govuk-inset-text">
                    <p class="govuk-body">
                        <i class="fa-solid fa-calendar-xmark govuk-!-margin-right-2" aria-hidden="true"></i>
                        You are not hosting any upcoming events.
                    </p>
                </div>
            <?php else: ?>
                <ul class="govuk-list" role="list">
                <?php foreach ($hosting as $e): ?>
                    <li class="govuk-!-margin-bottom-4 govuk-!-padding-4 civicone-event-card-hosting">
                        <div class="govuk-grid-row govuk-!-margin-bottom-2">
                            <div class="govuk-grid-column-one-half">
                                <span class="govuk-body-s"><strong><?= date('M j @ g:i A', strtotime($e['start_time'])) ?></strong></span>
                            </div>
                            <div class="govuk-grid-column-one-half govuk-!-text-align-right">
                                <span class="govuk-tag govuk-tag--green"><?= $e['attending_count'] ?? 0 ?> Going</span>
                                <span class="govuk-tag govuk-tag--grey"><?= $e['invited_count'] ?? 0 ?> Invited</span>
                            </div>
                        </div>
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                            <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" class="govuk-link"><?= htmlspecialchars($e['title']) ?></a>
                        </h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-3">
                            <i class="fa-solid fa-location-dot govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= htmlspecialchars($e['location'] ?? 'TBA') ?>
                        </p>
                        <div class="govuk-button-group">
                            <a href="<?= $basePath ?>/events/<?= $e['id'] ?>/edit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                <i class="fa-solid fa-pen govuk-!-margin-right-1" aria-hidden="true"></i> Edit
                            </a>
                            <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i> Manage
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <!-- Attending -->
    <div class="govuk-grid-column-one-half">
        <section aria-labelledby="attending-heading" class="govuk-!-margin-bottom-8">
            <h2 id="attending-heading" class="govuk-heading-l govuk-!-margin-bottom-4">
                <i class="fa-solid fa-calendar-check govuk-!-margin-right-2" aria-hidden="true"></i>
                Attending
            </h2>

            <?php if (empty($attending)): ?>
                <div class="govuk-inset-text">
                    <p class="govuk-body govuk-!-margin-bottom-4">
                        <i class="fa-solid fa-calendar-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                        You are not attending any upcoming events.
                    </p>
                    <a href="<?= $basePath ?>/events" class="govuk-button govuk-button--start" data-module="govuk-button">
                        Browse Events
                        <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
                        </svg>
                    </a>
                </div>
            <?php else: ?>
                <ul class="govuk-list" role="list">
                <?php foreach ($attending as $e): ?>
                    <li class="govuk-!-margin-bottom-4">
                        <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" class="govuk-link civicone-link-no-underline">
                            <div class="govuk-!-padding-3 civicone-event-attending-card">
                                <div class="govuk-!-padding-2 govuk-!-text-align-centre civicone-panel-bg civicone-event-date-box">
                                    <div class="govuk-body-s govuk-!-margin-bottom-0"><strong><?= date('M', strtotime($e['start_time'])) ?></strong></div>
                                    <div class="govuk-heading-m govuk-!-margin-bottom-0"><?= date('j', strtotime($e['start_time'])) ?></div>
                                </div>
                                <div>
                                    <p class="govuk-body govuk-!-margin-bottom-1"><strong><?= htmlspecialchars($e['title']) ?></strong></p>
                                    <p class="govuk-body-s govuk-!-margin-bottom-1">
                                        <?= date('g:i A', strtotime($e['start_time'])) ?> • <?= htmlspecialchars($e['organizer_name'] ?? 'Unknown') ?>
                                    </p>
                                    <span class="govuk-tag govuk-tag--green">
                                        <i class="fa-solid fa-check-circle govuk-!-margin-right-1" aria-hidden="true"></i> Going
                                    </span>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
