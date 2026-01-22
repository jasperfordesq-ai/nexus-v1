<?php
/**
 * Federated Event Detail
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Federated Event";
$hideHero = true;
$bodyClass = 'civicone--federation';

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
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($organizerName) . '&background=00796B&color=fff&size=200';
$organizerAvatar = !empty($event['organizer_avatar']) ? $event['organizer_avatar'] : $fallbackAvatar;

$eventDate = isset($event['event_date']) ? new DateTime($event['event_date']) : null;
$spotsLeft = isset($event['max_attendees']) ? ($event['max_attendees'] - ($event['attendee_count'] ?? 0)) : null;
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/events" class="civic-fed-back-link" aria-label="Return to events">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Federated Events
    </a>

    <?php if (!empty($_GET['registered'])): ?>
        <div class="civic-fed-alert civic-fed-alert--success" role="status" aria-live="polite">
            <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
            You have successfully registered for this event!
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div class="civic-fed-alert civic-fed-alert--error" role="alert">
            <i class="fa-solid fa-exclamation-circle" aria-hidden="true"></i>
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <!-- Event Card -->
    <article class="civic-fed-detail-card" aria-labelledby="event-title">
        <header class="civic-fed-detail-header">
            <div class="civic-fed-badges" role="group" aria-label="Event details">
                <?php if ($eventDate): ?>
                    <time class="civic-fed-badge civic-fed-badge--date" datetime="<?= $eventDate->format('c') ?>">
                        <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                        <?= $eventDate->format('D, M j, Y \a\t g:i A') ?>
                    </time>
                <?php endif; ?>
                <span class="civic-fed-badge civic-fed-badge--partner">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <?= htmlspecialchars($event['tenant_name'] ?? 'Partner Timebank') ?>
                </span>
            </div>

            <h1 id="event-title" class="civic-fed-detail-title"><?= htmlspecialchars($event['title'] ?? 'Untitled Event') ?></h1>
        </header>

        <div class="civic-fed-detail-body">
            <!-- Event Details Grid -->
            <div class="civic-fed-info-grid" role="group" aria-label="Event information">
                <?php if (!empty($event['location'])): ?>
                    <div class="civic-fed-info-item">
                        <div class="civic-fed-info-icon" aria-hidden="true">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div class="civic-fed-info-content">
                            <h4>Location</h4>
                            <p><?= htmlspecialchars($event['location']) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($eventDate): ?>
                    <div class="civic-fed-info-item">
                        <div class="civic-fed-info-icon" aria-hidden="true">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div class="civic-fed-info-content">
                            <h4>Date & Time</h4>
                            <p><time datetime="<?= $eventDate->format('c') ?>"><?= $eventDate->format('F j, Y') ?><br><?= $eventDate->format('g:i A') ?></time></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($event['max_attendees'])): ?>
                    <div class="civic-fed-info-item">
                        <div class="civic-fed-info-icon" aria-hidden="true">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="civic-fed-info-content">
                            <h4>Capacity</h4>
                            <p><?= (int)($event['attendee_count'] ?? 0) ?> / <?= (int)$event['max_attendees'] ?> registered</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($event['duration_hours'])): ?>
                    <div class="civic-fed-info-item">
                        <div class="civic-fed-info-icon" aria-hidden="true">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                        <div class="civic-fed-info-content">
                            <h4>Duration</h4>
                            <p><?= htmlspecialchars($event['duration_hours']) ?> hour(s)</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <?php if (!empty($event['description'])): ?>
                <section class="civic-fed-section" aria-labelledby="about-heading">
                    <h3 id="about-heading" class="civic-fed-section-title">
                        <i class="fa-solid fa-align-left" aria-hidden="true"></i>
                        About This Event
                    </h3>
                    <div class="civic-fed-content">
                        <?= nl2br(htmlspecialchars($event['description'])) ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Organizer Section -->
            <section class="civic-fed-section" aria-labelledby="organizer-heading">
                <h3 id="organizer-heading" class="civic-fed-section-title">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    Organized By
                </h3>
                <div class="civic-fed-owner-info">
                    <img src="<?= htmlspecialchars($organizerAvatar) ?>"
                         onerror="this.src='<?= $fallbackAvatar ?>'"
                         alt=""
                         class="civic-fed-avatar"
                         loading="lazy">
                    <div class="civic-fed-owner-details">
                        <h4><?= htmlspecialchars($organizerName) ?></h4>
                        <span class="civic-fed-owner-tenant">
                            <i class="fa-solid fa-building" aria-hidden="true"></i>
                            <?= htmlspecialchars($event['tenant_name'] ?? 'Partner Timebank') ?>
                        </span>
                    </div>
                </div>
            </section>

            <!-- Registration Section -->
            <section class="civic-fed-section" aria-labelledby="registration-heading">
                <h3 id="registration-heading" class="civic-fed-section-title">
                    <i class="fa-solid fa-ticket" aria-hidden="true"></i>
                    Registration
                </h3>

                <div class="civic-fed-registration-status" role="status" aria-live="polite">
                    <?php if ($isRegistered): ?>
                        <span class="civic-fed-status-badge civic-fed-status-badge--success">
                            <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
                            You're Registered!
                        </span>
                    <?php elseif ($spotsLeft !== null): ?>
                        <?php
                        $spotsClass = 'civic-fed-status-badge--available';
                        $spotsText = $spotsLeft . ' spots available';
                        if ($spotsLeft <= 0) {
                            $spotsClass = 'civic-fed-status-badge--full';
                            $spotsText = 'Event is full';
                        } elseif ($spotsLeft <= 5) {
                            $spotsClass = 'civic-fed-status-badge--limited';
                        }
                        ?>
                        <span class="civic-fed-status-badge <?= $spotsClass ?>"><?= $spotsText ?></span>
                    <?php else: ?>
                        <span class="civic-fed-status-badge civic-fed-status-badge--available">Open Registration</span>
                    <?php endif; ?>
                </div>

                <?php if ($isRegistered): ?>
                    <p class="civic-fed-note">
                        You have already registered for this event. We'll see you there!
                    </p>
                <?php elseif ($registrationClosed): ?>
                    <button class="civic-fed-btn civic-fed-btn--disabled" disabled aria-disabled="true">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        Registration Closed
                    </button>
                <?php elseif ($isFull): ?>
                    <button class="civic-fed-btn civic-fed-btn--disabled" disabled aria-disabled="true">
                        <i class="fa-solid fa-users-slash" aria-hidden="true"></i>
                        Event Full
                    </button>
                <?php elseif ($canRegister): ?>
                    <form action="<?= $basePath ?>/federation/events/<?= $event['id'] ?>/register" method="POST" aria-label="Event registration">
                        <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($event['tenant_id'] ?? '') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <button type="submit" class="civic-fed-btn civic-fed-btn--primary">
                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                            Register for This Event
                        </button>
                    </form>
                <?php else: ?>
                    <button class="civic-fed-btn civic-fed-btn--disabled" disabled aria-disabled="true">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        Registration Not Available
                    </button>
                    <p class="civic-fed-note">
                        Enable federation features in your settings to register for events from partner timebanks.
                    </p>
                <?php endif; ?>
            </section>

            <!-- Privacy Notice -->
            <aside class="civic-fed-notice" role="note">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <div>
                    <strong>Federated Event</strong><br>
                    This event is hosted by <strong><?= htmlspecialchars($event['tenant_name'] ?? 'a partner timebank') ?></strong>.
                    When you register, your basic profile information will be shared with the event organizer.
                </div>
            </aside>
        </div>
    </article>
</div>

<!-- Federation offline indicator -->
<script src="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-federation-offline.min.js" defer></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
