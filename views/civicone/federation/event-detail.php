<?php
/**
 * Federated Event Detail
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Federated Event";
$hideHero = true;

Nexus\Core\SEO::setTitle(($event['title'] ?? 'Event') . ' - Federated');
Nexus\Core\SEO::setDescription('Event details from a partner timebank in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$event = $event ?? [];
$canRegister = $canRegister ?? false;
$isRegistered = $isRegistered ?? false;
$registrationClosed = $registrationClosed ?? false;
$isFull = $isFull ?? false;

$organizerName = $event['organizer_name'] ?? 'Unknown';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($organizerName) . '&background=00703c&color=fff&size=200';
$organizerAvatar = !empty($event['organizer_avatar']) ? $event['organizer_avatar'] : $fallbackAvatar;

$eventDate = isset($event['event_date']) ? new DateTime($event['event_date']) : null;
$spotsLeft = isset($event['max_attendees']) ? ($event['max_attendees'] - ($event['attendee_count'] ?? 0)) : null;
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
    <a href="<?= $basePath ?>/federation/events" class="govuk-back-link govuk-!-margin-top-4">
        Back to Federated Events
    </a>

    <main class="govuk-main-wrapper govuk-!-padding-top-4" id="main-content" role="main">
        <?php if (!empty($_GET['registered'])): ?>
            <div class="govuk-notification-banner govuk-notification-banner--success" role="status" aria-live="polite" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title">Success</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">
                        <i class="fa-solid fa-check-circle govuk-!-margin-right-2" aria-hidden="true"></i>
                        You have successfully registered for this event!
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
            <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" tabindex="-1" data-module="govuk-error-summary">
                <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body"><?= htmlspecialchars($_GET['error']) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <!-- Event Card -->
                <article class="govuk-!-padding-6" style="background: #fff; border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;" aria-labelledby="event-title">
                    <!-- Badges -->
                    <div class="govuk-!-margin-bottom-4">
                        <?php if ($eventDate): ?>
                            <span class="govuk-tag govuk-tag--purple">
                                <i class="fa-solid fa-calendar govuk-!-margin-right-1" aria-hidden="true"></i>
                                <time datetime="<?= $eventDate->format('c') ?>">
                                    <?= $eventDate->format('D, M j, Y \a\t g:i A') ?>
                                </time>
                            </span>
                        <?php endif; ?>
                        <span class="govuk-tag govuk-tag--grey govuk-!-margin-left-2">
                            <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= htmlspecialchars($event['tenant_name'] ?? 'Partner Timebank') ?>
                        </span>
                    </div>

                    <h1 class="govuk-heading-xl govuk-!-margin-bottom-6" id="event-title">
                        <?= htmlspecialchars($event['title'] ?? 'Untitled Event') ?>
                    </h1>

                    <!-- Event Details Summary -->
                    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
                        <?php if (!empty($event['location'])): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">
                                <i class="fa-solid fa-location-dot govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                                Location
                            </dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($event['location']) ?></dd>
                        </div>
                        <?php endif; ?>

                        <?php if ($eventDate): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">
                                <i class="fa-solid fa-clock govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                                Date & Time
                            </dt>
                            <dd class="govuk-summary-list__value">
                                <time datetime="<?= $eventDate->format('c') ?>">
                                    <?= $eventDate->format('F j, Y') ?> at <?= $eventDate->format('g:i A') ?>
                                </time>
                            </dd>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($event['max_attendees'])): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">
                                <i class="fa-solid fa-users govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                                Capacity
                            </dt>
                            <dd class="govuk-summary-list__value">
                                <?= (int)($event['attendee_count'] ?? 0) ?> / <?= (int)$event['max_attendees'] ?> registered
                            </dd>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($event['duration_hours'])): ?>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">
                                <i class="fa-solid fa-hourglass-half govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                                Duration
                            </dt>
                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($event['duration_hours']) ?> hour(s)</dd>
                        </div>
                        <?php endif; ?>
                    </dl>

                    <!-- Description -->
                    <?php if (!empty($event['description'])): ?>
                        <h2 class="govuk-heading-m">
                            <i class="fa-solid fa-align-left govuk-!-margin-right-2" style="color: #505a5f;" aria-hidden="true"></i>
                            About This Event
                        </h2>
                        <p class="govuk-body-l govuk-!-margin-bottom-6">
                            <?= nl2br(htmlspecialchars($event['description'])) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Organizer Section -->
                    <h2 class="govuk-heading-m">
                        <i class="fa-solid fa-user govuk-!-margin-right-2" style="color: #505a5f;" aria-hidden="true"></i>
                        Organized By
                    </h2>
                    <div class="govuk-!-padding-4 govuk-!-margin-bottom-6" style="background: #f3f2f1; display: flex; align-items: center; gap: 16px;">
                        <img src="<?= htmlspecialchars($organizerAvatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt=""
                             style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;"
                             loading="lazy">
                        <div>
                            <p class="govuk-body-l govuk-!-font-weight-bold govuk-!-margin-bottom-1">
                                <?= htmlspecialchars($organizerName) ?>
                            </p>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                                <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                                <?= htmlspecialchars($event['tenant_name'] ?? 'Partner Timebank') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Registration Section -->
                    <h2 class="govuk-heading-m">
                        <i class="fa-solid fa-ticket govuk-!-margin-right-2" style="color: #505a5f;" aria-hidden="true"></i>
                        Registration
                    </h2>

                    <div class="govuk-!-margin-bottom-4" role="status" aria-live="polite">
                        <?php if ($isRegistered): ?>
                            <span class="govuk-tag govuk-tag--green">
                                <i class="fa-solid fa-check-circle govuk-!-margin-right-1" aria-hidden="true"></i>
                                You're Registered!
                            </span>
                        <?php elseif ($spotsLeft !== null): ?>
                            <?php if ($spotsLeft <= 0): ?>
                                <span class="govuk-tag govuk-tag--red">Event is full</span>
                            <?php elseif ($spotsLeft <= 5): ?>
                                <span class="govuk-tag govuk-tag--yellow"><?= $spotsLeft ?> spots available</span>
                            <?php else: ?>
                                <span class="govuk-tag govuk-tag--green"><?= $spotsLeft ?> spots available</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="govuk-tag govuk-tag--green">Open Registration</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($isRegistered): ?>
                        <p class="govuk-body govuk-!-margin-bottom-6">
                            You have already registered for this event. We'll see you there!
                        </p>
                    <?php elseif ($registrationClosed): ?>
                        <button class="govuk-button govuk-button--disabled" disabled aria-disabled="true">
                            <i class="fa-solid fa-clock govuk-!-margin-right-2" aria-hidden="true"></i>
                            Registration Closed
                        </button>
                    <?php elseif ($isFull): ?>
                        <button class="govuk-button govuk-button--disabled" disabled aria-disabled="true">
                            <i class="fa-solid fa-users-slash govuk-!-margin-right-2" aria-hidden="true"></i>
                            Event Full
                        </button>
                    <?php elseif ($canRegister): ?>
                        <form action="<?= $basePath ?>/federation/events/<?= $event['id'] ?>/register" method="POST">
                            <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($event['tenant_id'] ?? '') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="govuk-button" data-module="govuk-button">
                                <i class="fa-solid fa-check govuk-!-margin-right-2" aria-hidden="true"></i>
                                Register for This Event
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="govuk-button govuk-button--disabled" disabled aria-disabled="true">
                            <i class="fa-solid fa-lock govuk-!-margin-right-2" aria-hidden="true"></i>
                            Registration Not Available
                        </button>
                        <p class="govuk-hint govuk-!-margin-top-2">
                            Enable federation features in your settings to register for events from partner timebanks.
                        </p>
                    <?php endif; ?>
                </article>

                <!-- Privacy Notice -->
                <div class="govuk-inset-text govuk-!-margin-top-6">
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <i class="fa-solid fa-shield-halved govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                        <strong>Federated Event</strong> — This event is hosted by <strong><?= htmlspecialchars($event['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        When you register, your basic profile information will be shared with the event organizer.
                    </p>
                </div>
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
