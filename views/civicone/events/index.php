<?php
/**
 * CivicOne Events Directory
 * Template A: Directory/List Page (Section 10.2)
 * With Page Hero (Section 9C: Page Hero Contract)
 */

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Hero (auto-resolves from config/heroes.php for /events route) -->
        <?php require dirname(__DIR__, 2) . '/layouts/civicone/partials/render-hero.php'; ?>

        <!-- Action Button -->
        <div class="civicone-grid-row civicone-action-row">
            <div class="civicone-grid-column-one-third">
                <a href="<?= $basePath ?>/events/create" class="civicone-button civicone-button--primary civicone-button--full-width">
                    Host Event
                </a>
            </div>
        </div>

        <!-- Events List -->
        <?php if (empty($events)): ?>
            <div class="civicone-empty-state">
                <svg class="civicone-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <h2 class="civicone-heading-m">No upcoming events</h2>
                <p class="civicone-body">Be the first to host a gathering!</p>
                <a href="<?= $basePath ?>/events/create" class="civicone-button civicone-button--secondary">Create Event</a>
            </div>
        <?php else: ?>
            <div class="civic-events-grid" role="list">
                <?php foreach ($events as $ev): ?>
                    <?php
                    $date = strtotime($ev['start_time']);
                    $month = date('M', $date);
                    $day = date('d', $date);
                    $time = date('g:i A', $date);
                    ?>
                    <article class="civic-card" role="listitem">
                        <div class="civic-event-card-layout">

                            <!-- Date Box (High Contrast) -->
                            <div class="civic-event-date-box" aria-hidden="true">
                                <div class="civic-event-date-month"><?= $month ?></div>
                                <div class="civic-event-date-day"><?= $day ?></div>
                            </div>

                            <!-- Content -->
                            <div class="civic-event-content">
                                <h3>
                                    <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>"
                                       aria-label="View event: <?= htmlspecialchars($ev['title']) ?> on <?= date('F j, Y', strtotime($ev['start_time'])) ?> at <?= htmlspecialchars($ev['location']) ?>">
                                        <?= htmlspecialchars($ev['title']) ?>
                                    </a>
                                </h3>

                                <div class="civic-event-meta">
                                    <span aria-label="Time">⏰ <?= $time ?></span>
                                    <span aria-hidden="true"> | </span>
                                    <span aria-label="Location">📍 <?= htmlspecialchars($ev['location']) ?></span>
                                </div>

                                <p class="civic-event-description">
                                    <?= substr(htmlspecialchars($ev['description']), 0, 150) ?>...
                                </p>

                                <div class="civic-event-host">
                                    Hosted by <?= htmlspecialchars($ev['organizer_name']) ?>
                                    <?php if ($ev['attendee_count'] > 0): ?>
                                        <span class="civic-event-attendees" aria-label="<?= $ev['attendee_count'] ?> people attending">
                                            <?= $ev['attendee_count'] ?> Going
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Action -->
                            <div class="civic-event-action">
                                <a href="<?= $basePath ?>/events/<?= $ev['id'] ?>"
                                   class="civic-btn"
                                   aria-label="View details and RSVP for <?= htmlspecialchars($ev['title']) ?>">View & RSVP</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
