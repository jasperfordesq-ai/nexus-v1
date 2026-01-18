<?php
// CivicOne View: Events Index
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

    <div class="civic-events-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #000; padding-bottom: 10px; margin-bottom: 30px; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin: 0; text-transform: uppercase; letter-spacing: 1px;">Upcoming Gatherings</h2>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/create" class="civic-btn" style="padding: 10px 20px;">+ Host Event</a>
    </div>

    <style>
        @media (max-width: 600px) {
            .civic-events-header {
                flex-direction: column;
                align-items: stretch !important;
            }
            .civic-events-header .civic-btn {
                text-align: center;
            }
        }
    </style>

    <?php if (empty($events)): ?>
        <div class="civic-card" style="text-align: center; padding: 40px;">
            <p style="font-size: 1.5rem; margin-bottom: 10px;">📅 No upcoming events.</p>
            <p style="margin-bottom: 20px;">Be the first to host a gathering!</p>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/create" class="civic-btn">Create Event</a>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 20px;">
            <?php foreach ($events as $ev): ?>
                <?php
                $date = strtotime($ev['start_time']);
                $month = date('M', $date);
                $day = date('d', $date);
                $time = date('g:i A', $date);
                ?>
                <div class="civic-card">
                    <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">

                        <!-- Date Box (High Contrast) -->
                        <div style="background: #000; color: #fff; padding: 15px; text-align: center; min-width: 80px; border-radius: 4px;">
                            <div style="font-size: 1rem; text-transform: uppercase; font-weight: bold;"><?= $month ?></div>
                            <div style="font-size: 2rem; font-weight: 900; line-height: 1;"><?= $day ?></div>
                        </div>

                        <!-- Content -->
                        <div style="flex: 1; min-width: 250px;">
                            <h3 style="margin: 0 0 5px 0; font-size: 1.5rem;">
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $ev['id'] ?>"
                                   aria-label="View event: <?= htmlspecialchars($ev['title']) ?> on <?= date('F j, Y', strtotime($ev['start_time'])) ?> at <?= htmlspecialchars($ev['location']) ?>"
                                   style="color: #000; text-decoration: underline;">
                                    <?= htmlspecialchars($ev['title']) ?>
                                </a>
                            </h3>

                            <div style="font-weight: bold; margin-bottom: 10px; font-size: 1.1rem;">
                                ⏰ <?= $time ?> &nbsp;|&nbsp; 📍 <?= htmlspecialchars($ev['location']) ?>
                            </div>

                            <p style="font-size: 1.1rem; line-height: 1.5; margin-bottom: 10px;">
                                <?= substr(htmlspecialchars($ev['description']), 0, 150) ?>...
                            </p>

                            <div style="font-size: 0.95rem; font-style: italic;">
                                Hosted by <?= htmlspecialchars($ev['organizer_name']) ?>
                                <?php if ($ev['attendee_count'] > 0): ?>
                                    <span style="font-style: normal; font-weight: bold; background: #eee; padding: 2px 6px; margin-left: 10px; border: 1px solid #999;">
                                        <?= $ev['attendee_count'] ?> Going
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Action -->
                        <div style="align-self: center;">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $ev['id'] ?>"
                               class="civic-btn"
                               aria-label="View details and RSVP for <?= htmlspecialchars($ev['title']) ?>"
                               style="white-space: nowrap;">View & RSVP</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>