<?php

/**
 * Component: Event Card
 *
 * Card for displaying community events.
 *
 * @param array $event Event data with keys: id, title, description, start_date, end_date, location, image, attendees, max_attendees, user
 * @param bool $showRsvp Show RSVP button (default: true)
 * @param bool $showAttendees Show attendee avatars (default: true)
 * @param string $class Additional CSS classes
 * @param string $baseUrl Base URL for event links (default: '')
 */

$event = $event ?? [];
$showRsvp = $showRsvp ?? true;
$showAttendees = $showAttendees ?? true;
$class = $class ?? '';
$baseUrl = $baseUrl ?? '';

// Extract event data with defaults
$id = $event['id'] ?? 0;
$title = $event['title'] ?? 'Untitled Event';
$description = $event['description'] ?? '';
$startDate = $event['start_date'] ?? $event['event_date'] ?? '';
$endDate = $event['end_date'] ?? '';
$location = $event['location'] ?? $event['venue'] ?? '';
$image = $event['image'] ?? $event['featured_image'] ?? '';
$attendees = $event['attendees'] ?? [];
$attendeeCount = $event['attendee_count'] ?? count($attendees);
$maxAttendees = $event['max_attendees'] ?? 0;
$isAttending = $event['is_attending'] ?? false;

$eventUrl = $baseUrl . '/events/' . $id;
$cssClass = trim('glass-event-card ' . $class);

// Format date
$dateFormatted = '';
$timeFormatted = '';
if ($startDate) {
    $dateObj = is_string($startDate) ? new DateTime($startDate) : $startDate;
    $dateFormatted = $dateObj->format('D, M j');
    $timeFormatted = $dateObj->format('g:i A');
}
?>

<article class="<?= e($cssClass) ?>">
    <?php if ($image): ?>
        <div class="event-image">
            <a href="<?= e($eventUrl) ?>">
                <?= webp_image($image, e($title), 'event-img') ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="event-content">
        <?php if ($dateFormatted): ?>
            <div class="event-date-badge">
                <span class="event-date"><?= e($dateFormatted) ?></span>
                <?php if ($timeFormatted): ?>
                    <span class="event-time"><?= e($timeFormatted) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h3 class="event-title">
            <a href="<?= e($eventUrl) ?>"><?= e($title) ?></a>
        </h3>

        <?php if ($location): ?>
            <div class="event-location">
                <i class="fa-solid fa-location-dot"></i>
                <span><?= e($location) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($description): ?>
            <p class="event-description"><?= e(mb_strimwidth(strip_tags($description), 0, 100, '...')) ?></p>
        <?php endif; ?>

        <div class="event-footer">
            <?php if ($showAttendees && $attendeeCount > 0): ?>
                <div class="event-attendees">
                    <div class="nexus-attendee-stack">
                        <?php
                        $showMax = min(3, count($attendees));
                        for ($i = 0; $i < $showMax; $i++):
                            $attendee = $attendees[$i];
                        ?>
                            <div class="nexus-attendee">
                                <?= webp_avatar($attendee['avatar'] ?? '', $attendee['name'] ?? '', 28) ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <span class="attendee-count">
                        <?= $attendeeCount ?> attending
                        <?php if ($maxAttendees > 0): ?>
                            / <?= $maxAttendees ?> spots
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($showRsvp): ?>
                <button
                    type="button"
                    class="event-rsvp-btn <?= $isAttending ? 'attending' : '' ?>"
                    data-event-id="<?= $id ?>"
                >
                    <?php if ($isAttending): ?>
                        <i class="fa-solid fa-check"></i> Attending
                    <?php else: ?>
                        <i class="fa-solid fa-calendar-plus"></i> RSVP
                    <?php endif; ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</article>
