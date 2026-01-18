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

        <style>
            /* Offline Banner */
            .offline-banner {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10001;
                padding: 12px 20px;
                background: linear-gradient(135deg, #ef4444, #dc2626);
                color: white;
                font-size: 0.9rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transform: translateY(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .offline-banner.visible {
                transform: translateY(0);
            }

            /* Content Reveal Animation */
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            #fed-event-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 900px;
                margin: 0 auto;
                padding: 20px 0;
            }

            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 20px;
                transition: color 0.2s;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            .event-card {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 24px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
                overflow: hidden;
            }

            [data-theme="dark"] .event-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
            }

            .event-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.12) 0%,
                        rgba(168, 85, 247, 0.12) 50%,
                        rgba(192, 132, 252, 0.08) 100%);
                padding: 30px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }

            [data-theme="dark"] .event-header {
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .event-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 16px;
            }

            .event-date-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                border-radius: 12px;
                font-size: 0.9rem;
                font-weight: 700;
            }

            .event-tenant {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 10px 18px;
                background: rgba(139, 92, 246, 0.1);
                border-radius: 12px;
                font-size: 0.9rem;
                font-weight: 600;
                color: #8b5cf6;
            }

            [data-theme="dark"] .event-tenant {
                background: rgba(139, 92, 246, 0.2);
                color: #a78bfa;
            }

            .event-title {
                font-size: 1.75rem;
                font-weight: 800;
                color: var(--htb-text-main);
                margin: 0;
            }

            .event-body {
                padding: 30px;
            }

            .section-title {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 12px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .section-title i {
                color: #8b5cf6;
            }

            /* Event Details Grid */
            .event-details-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .detail-item {
                display: flex;
                align-items: flex-start;
                gap: 14px;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.12);
                border-radius: 14px;
            }

            .detail-icon {
                width: 44px;
                height: 44px;
                background: rgba(139, 92, 246, 0.12);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .detail-icon i {
                font-size: 1.1rem;
                color: #8b5cf6;
            }

            .detail-content h4 {
                font-size: 0.8rem;
                font-weight: 600;
                color: var(--htb-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin: 0 0 4px 0;
            }

            .detail-content p {
                font-size: 0.95rem;
                font-weight: 600;
                color: var(--htb-text-main);
                margin: 0;
            }

            /* Description */
            .event-description {
                color: var(--htb-text-main);
                font-size: 1rem;
                line-height: 1.8;
                margin-bottom: 30px;
            }

            /* Organizer Section */
            .organizer-section {
                padding: 24px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 16px;
                margin-bottom: 24px;
            }

            .organizer-info {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .organizer-avatar {
                width: 64px;
                height: 64px;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid rgba(139, 92, 246, 0.3);
            }

            .organizer-details h4 {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 4px 0;
            }

            .organizer-details .organizer-tenant {
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            /* Registration Section */
            .registration-section {
                padding: 24px;
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.08),
                        rgba(168, 85, 247, 0.06));
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 16px;
                margin-bottom: 24px;
            }

            .registration-status {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
            }

            .spots-badge {
                font-size: 0.9rem;
                padding: 8px 16px;
                border-radius: 10px;
                font-weight: 600;
            }

            .spots-available {
                background: rgba(16, 185, 129, 0.1);
                color: #059669;
            }

            .spots-limited {
                background: rgba(245, 158, 11, 0.1);
                color: #d97706;
            }

            .spots-full {
                background: rgba(239, 68, 68, 0.1);
                color: #dc2626;
            }

            [data-theme="dark"] .spots-available {
                background: rgba(16, 185, 129, 0.2);
                color: #34d399;
            }

            [data-theme="dark"] .spots-limited {
                background: rgba(245, 158, 11, 0.2);
                color: #fbbf24;
            }

            [data-theme="dark"] .spots-full {
                background: rgba(239, 68, 68, 0.2);
                color: #f87171;
            }

            .registered-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                background: rgba(16, 185, 129, 0.15);
                color: #059669;
                border-radius: 10px;
                font-weight: 600;
                font-size: 0.9rem;
            }

            [data-theme="dark"] .registered-badge {
                background: rgba(16, 185, 129, 0.25);
                color: #34d399;
            }

            .register-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 14px 28px;
                border-radius: 14px;
                font-weight: 700;
                font-size: 0.95rem;
                text-decoration: none;
                transition: all 0.3s ease;
                cursor: pointer;
                border: none;
                width: 100%;
            }

            .register-btn-primary {
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
            }

            .register-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.45);
            }

            .register-btn-disabled {
                background: rgba(100, 100, 100, 0.1);
                color: var(--htb-text-muted);
                cursor: not-allowed;
                opacity: 0.6;
            }

            .register-btn-disabled:hover {
                transform: none;
            }

            /* Privacy Notice */
            .privacy-notice {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                margin-top: 24px;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 12px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .privacy-notice i {
                color: #8b5cf6;
                margin-top: 2px;
            }

            /* Alert Messages */
            .alert {
                padding: 16px 20px;
                border-radius: 12px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .alert-success {
                background: rgba(16, 185, 129, 0.1);
                border: 1px solid rgba(16, 185, 129, 0.3);
                color: #059669;
            }

            .alert-error {
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid rgba(239, 68, 68, 0.3);
                color: #dc2626;
            }

            [data-theme="dark"] .alert-success {
                background: rgba(16, 185, 129, 0.15);
                color: #34d399;
            }

            [data-theme="dark"] .alert-error {
                background: rgba(239, 68, 68, 0.15);
                color: #f87171;
            }

            /* Touch Targets */
            .register-btn {
                min-height: 44px;
            }

            /* Focus Visible */
            .register-btn:focus-visible,
            .back-link:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            @media (max-width: 640px) {
                #fed-event-wrapper {
                    padding: 15px;
                }

                .event-header,
                .event-body {
                    padding: 20px;
                }

                .event-title {
                    font-size: 1.4rem;
                }

                .event-details-grid {
                    grid-template-columns: 1fr;
                }

                .registration-status {
                    flex-direction: column;
                    gap: 12px;
                    align-items: flex-start;
                }
            }
        </style>

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
