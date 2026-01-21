<?php
// CivicOne View: Events Index
// WCAG 2.1 AA Compliant - External CSS in civicone-events.css
// $showHero = true; // Disabled - hero is broken
$heroTitle = "Community Events";
$heroSub = "Connect, learn, and celebrate with your neighbors.";
$heroType = 'Gatherings';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">
    <?php
    $breadcrumbs = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Events']
    ];
    require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
    ?>

    <div class="civic-events-header">
        <h2>Upcoming Gatherings</h2>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/create" class="civic-btn">+ Host Event</a>
    </div>

    <?php if (empty($events)): ?>
        <div class="civic-card civic-empty-state">
            <p class="civic-empty-icon" aria-hidden="true">📅</p>
            <p class="civic-empty-title">No upcoming events.</p>
            <p class="civic-empty-text">Be the first to host a gathering!</p>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/create" class="civic-btn">Create Event</a>
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
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $ev['id'] ?>"
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
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $ev['id'] ?>"
                               class="civic-btn"
                               aria-label="View details and RSVP for <?= htmlspecialchars($ev['title']) ?>">View & RSVP</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>