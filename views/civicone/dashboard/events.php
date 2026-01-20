<?php
/**
 * CivicOne Dashboard - My Events Page
 * WCAG 2.1 AA Compliant
 * Template: Account Area Template (Template G)
 */

$hTitle = "My Events";
$hSubtitle = "Events you're hosting and attending";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="civic-dashboard civicone-account-area">

    <!-- Account Area Secondary Navigation -->
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

    <!-- EVENTS CONTENT -->
    <div class="civic-events-grid">
        <!-- Hosting -->
        <section class="civic-dash-card" aria-labelledby="hosting-heading">
            <div class="civic-dash-card-header">
                <h2 id="hosting-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-calendar-star" aria-hidden="true"></i>
                    Hosting
                </h2>
                <a href="<?= $basePath ?>/events/create" class="civic-button" role="button">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Create Event
                </a>
            </div>
            <?php if (empty($hosting)): ?>
                <div class="civic-empty-state">
                    <div class="civic-empty-icon"><i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i></div>
                    <p class="civic-empty-text">You are not hosting any upcoming events.</p>
                </div>
            <?php else: ?>
                <ul role="list" class="civic-events-list">
                <?php foreach ($hosting as $e): ?>
                    <li class="civic-event-hosted">
                        <div class="civic-event-hosted-header">
                            <div class="civic-event-hosted-date">
                                <?= date('M j @ g:i A', strtotime($e['start_time'])) ?>
                            </div>
                            <div class="civic-event-hosted-stats">
                                <span class="civic-event-going"><strong><?= $e['attending_count'] ?? 0 ?></strong> Going</span>
                                <span><strong><?= $e['invited_count'] ?? 0 ?></strong> Invited</span>
                            </div>
                        </div>
                        <h3 class="civic-event-hosted-title">
                            <a href="<?= $basePath ?>/events/<?= $e['id'] ?>"><?= htmlspecialchars($e['title']) ?></a>
                        </h3>
                        <div class="civic-event-hosted-location">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <?= htmlspecialchars($e['location'] ?? 'TBA') ?>
                        </div>
                        <div class="civic-event-hosted-actions">
                            <a href="<?= $basePath ?>/events/<?= $e['id'] ?>/edit" class="civic-button civic-button--secondary" role="button">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit
                            </a>
                            <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" class="civic-button civic-button--secondary" role="button">
                                <i class="fa-solid fa-users" aria-hidden="true"></i> Manage
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Attending -->
        <section class="civic-dash-card" aria-labelledby="attending-heading">
            <div class="civic-dash-card-header">
                <h2 id="attending-heading" class="civic-dash-card-title">
                    <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                    Attending
                </h2>
            </div>
            <?php if (empty($attending)): ?>
                <div class="civic-empty-state">
                    <div class="civic-empty-icon"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i></div>
                    <p class="civic-empty-text">You are not attending any upcoming events.</p>
                    <a href="<?= $basePath ?>/events" class="civic-button civic-button--start" role="button">Browse Events</a>
                </div>
            <?php else: ?>
                <ul role="list" class="civic-events-list">
                <?php foreach ($attending as $e): ?>
                    <li>
                        <a href="<?= $basePath ?>/events/<?= $e['id'] ?>" class="civic-event-attending">
                            <div class="civic-event-date-box">
                                <div class="civic-event-month"><?= date('M', strtotime($e['start_time'])) ?></div>
                                <div class="civic-event-day"><?= date('j', strtotime($e['start_time'])) ?></div>
                            </div>
                            <div class="civic-event-attending-info">
                                <div class="civic-event-attending-title"><?= htmlspecialchars($e['title']) ?></div>
                                <div class="civic-event-attending-meta">
                                    <?= date('g:i A', strtotime($e['start_time'])) ?> • <?= htmlspecialchars($e['organizer_name'] ?? 'Unknown') ?>
                                </div>
                                <span class="civic-event-badge-going">
                                    <i class="fa-solid fa-check-circle" aria-hidden="true"></i> Going
                                </span>
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
