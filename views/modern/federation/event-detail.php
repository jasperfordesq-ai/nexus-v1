<?php
// Federated Event Detail - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Federated Event";
$hideHero = true;

Nexus\Core\SEO::setTitle(($event['title'] ?? 'Event') . ' - Federated');
Nexus\Core\SEO::setDescription('Event details from a partner timebank in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$event = $event ?? [];
$canRegister = $canRegister ?? false;
$isRegistered = $isRegistered ?? false;
$registrationClosed = $registrationClosed ?? false;
$isFull = $isFull ?? false;

$organizerName = $event['organizer_name'] ?? 'Unknown';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($organizerName) . '&background=8b5cf6&color=fff&size=200';
$organizerAvatar = !empty($event['organizer_avatar']) ? $event['organizer_avatar'] : $fallbackAvatar;

$eventDate = isset($event['event_date']) ? new DateTime($event['event_date']) : null;
$spotsLeft = isset($event['max_attendees']) ? ($event['max_attendees'] - ($event['attendee_count'] ?? 0)) : null;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="fed-event-wrapper">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation/events" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Federated Events
        </a>

        <?php if (!empty($_GET['registered'])): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i>
                You have successfully registered for this event!
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-circle"></i>
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <!-- Event Card -->
        <div class="event-card">
            <div class="event-header">
                <div class="event-badges">
                    <?php if ($eventDate): ?>
                        <span class="event-date-badge">
                            <i class="fa-solid fa-calendar"></i>
                            <?= $eventDate->format('D, M j, Y \a\t g:i A') ?>
                        </span>
                    <?php endif; ?>
                    <span class="event-tenant">
                        <i class="fa-solid fa-building"></i>
                        <?= htmlspecialchars($event['tenant_name'] ?? 'Partner Timebank') ?>
                    </span>
                </div>

                <h1 class="event-title"><?= htmlspecialchars($event['title'] ?? 'Untitled Event') ?></h1>
            </div>

            <div class="event-body">
                <!-- Event Details Grid -->
                <div class="event-details-grid">
                    <?php if (!empty($event['location'])): ?>
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div class="detail-content">
                                <h4>Location</h4>
                                <p><?= htmlspecialchars($event['location']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($eventDate): ?>
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="detail-content">
                                <h4>Date & Time</h4>
                                <p><?= $eventDate->format('F j, Y') ?><br><?= $eventDate->format('g:i A') ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($event['max_attendees'])): ?>
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="detail-content">
                                <h4>Capacity</h4>
                                <p><?= (int)($event['attendee_count'] ?? 0) ?> / <?= (int)$event['max_attendees'] ?> registered</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($event['duration_hours'])): ?>
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </div>
                            <div class="detail-content">
                                <h4>Duration</h4>
                                <p><?= htmlspecialchars($event['duration_hours']) ?> hour(s)</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <?php if (!empty($event['description'])): ?>
                    <h3 class="section-title">
                        <i class="fa-solid fa-align-left"></i>
                        About This Event
                    </h3>
                    <div class="event-description">
                        <?= nl2br(htmlspecialchars($event['description'])) ?>
                    </div>
                <?php endif; ?>

                <!-- Organizer Section -->
                <div class="organizer-section">
                    <h3 class="section-title" style="margin-bottom: 16px;">
                        <i class="fa-solid fa-user"></i>
                        Organized By
                    </h3>
                    <div class="organizer-info">
                        <img src="<?= htmlspecialchars($organizerAvatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt="<?= htmlspecialchars($organizerName) ?>"
                             class="organizer-avatar">
                        <div class="organizer-details">
                            <h4><?= htmlspecialchars($organizerName) ?></h4>
                            <span class="organizer-tenant">
                                <i class="fa-solid fa-building" style="margin-right: 6px;"></i>
                                <?= htmlspecialchars($event['tenant_name'] ?? 'Partner Timebank') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Registration Section -->
                <div class="registration-section">
                    <h3 class="section-title" style="margin-bottom: 16px;">
                        <i class="fa-solid fa-ticket"></i>
                        Registration
                    </h3>

                    <div class="registration-status">
                        <?php if ($isRegistered): ?>
                            <span class="registered-badge">
                                <i class="fa-solid fa-check-circle"></i>
                                You're Registered!
                            </span>
                        <?php elseif ($spotsLeft !== null): ?>
                            <?php
                            $spotsClass = 'spots-available';
                            $spotsText = $spotsLeft . ' spots available';
                            if ($spotsLeft <= 0) {
                                $spotsClass = 'spots-full';
                                $spotsText = 'Event is full';
                            } elseif ($spotsLeft <= 5) {
                                $spotsClass = 'spots-limited';
                            }
                            ?>
                            <span class="spots-badge <?= $spotsClass ?>"><?= $spotsText ?></span>
                        <?php else: ?>
                            <span class="spots-badge spots-available">Open Registration</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($isRegistered): ?>
                        <p style="color: var(--htb-text-muted); margin: 0;">
                            You have already registered for this event. We'll see you there!
                        </p>
                    <?php elseif ($registrationClosed): ?>
                        <button class="register-btn register-btn-disabled" disabled>
                            <i class="fa-solid fa-clock"></i>
                            Registration Closed
                        </button>
                    <?php elseif ($isFull): ?>
                        <button class="register-btn register-btn-disabled" disabled>
                            <i class="fa-solid fa-users-slash"></i>
                            Event Full
                        </button>
                    <?php elseif ($canRegister): ?>
                        <form action="<?= $basePath ?>/federation/events/<?= $event['id'] ?>/register" method="POST">
                            <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($event['tenant_id'] ?? '') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="register-btn register-btn-primary">
                                <i class="fa-solid fa-check"></i>
                                Register for This Event
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="register-btn register-btn-disabled" disabled>
                            <i class="fa-solid fa-lock"></i>
                            Registration Not Available
                        </button>
                        <p style="color: var(--htb-text-muted); margin-top: 12px; font-size: 0.9rem;">
                            Enable federation features in your settings to register for events from partner timebanks.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Privacy Notice -->
                <div class="privacy-notice">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <strong>Federated Event</strong><br>
                        This event is hosted by <strong><?= htmlspecialchars($event['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        When you register, your basic profile information will be shared with the event organizer.
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
