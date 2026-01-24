<?php
// CivicOne View: Event Details - MadeOpen Style
// WCAG 2.1 AA Compliant - External CSS in civicone-events.css
if (session_status() === PHP_SESSION_NONE) session_start();

$hTitle = $event['title'];
$hSubtitle = 'Hosted by ' . htmlspecialchars($event['user_name'] ?? 'Community Member');
$hType = 'Event';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?php
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Events', 'url' => '/events'],
    ['label' => $event['title']]
];
require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
?>

<!-- Action Bar -->
<div class="civic-action-bar civic-action-bar--spaced">
    <a href="<?= $basePath ?>/events" class="civic-btn civic-btn--outline">
        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        Back to Events
    </a>
    <?php if (isset($_SESSION['user_id']) && $event['user_id'] == $_SESSION['user_id']): ?>
        <a href="<?= $basePath ?>/events/edit/<?= $event['id'] ?>" class="civic-btn civic-btn--outline">
            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
            Edit Event
        </a>
    <?php endif; ?>
</div>

<!-- Alert Messages -->
<?php if (isset($_GET['msg']) && $_GET['msg'] == 'rsvp_saved'): ?>
    <div class="civic-alert civic-alert--success" role="alert">
        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
        Your RSVP has been updated!
    </div>
<?php endif; ?>

<div class="civic-event-detail">
    <div class="civic-event-grid">
        <!-- Main Content -->
        <div class="civic-event-main">
            <article class="civic-card">
                <div class="civic-event-body">
                    <?= nl2br(htmlspecialchars($event['description'])) ?>
                </div>

                <div class="civic-event-info-box">
                    <h3 class="civic-section-subtitle">
                        <span class="dashicons dashicons-info" aria-hidden="true"></span>
                        Event Details
                    </h3>
                    <dl class="civic-event-details">
                        <div class="civic-detail-item">
                            <dt>
                                <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                Date & Time
                            </dt>
                            <dd><?= date('F j, Y \a\t g:i A', strtotime($event['start_time'])) ?></dd>
                        </div>
                        <div class="civic-detail-item">
                            <dt>
                                <span class="dashicons dashicons-location" aria-hidden="true"></span>
                                Location
                            </dt>
                            <dd><?= htmlspecialchars($event['location']) ?></dd>
                        </div>
                        <?php if (!empty($event['category_name'])): ?>
                            <div class="civic-detail-item">
                                <dt>
                                    <span class="dashicons dashicons-category" aria-hidden="true"></span>
                                    Category
                                </dt>
                                <dd><?= htmlspecialchars($event['category_name']) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>

                <!-- Social Interactions -->
                <?php
                $targetType = 'event';
                $targetId = $event['id'];
                include dirname(__DIR__) . '/partials/social_interactions.php';
                ?>
            </article>

            <!-- Attendees -->
            <?php if (!empty($attendees)): ?>
                <section class="civic-card civic-card--section">
                    <h3 class="civic-section-subtitle">
                        <span class="dashicons dashicons-groups" aria-hidden="true"></span>
                        Going (<?= count($attendees) ?>)
                    </h3>
                    <div class="civic-attendees-grid">
                        <?php foreach ($attendees as $att): ?>
                            <a href="<?= $basePath ?>/profile/<?= $att['user_id'] ?>"
                               class="civic-attendee"
                               title="<?= htmlspecialchars($att['name']) ?>">
                                <img src="<?= $att['avatar_url'] ?? '/assets/images/default-avatar.svg' ?>"
                                     alt="<?= htmlspecialchars($att['name']) ?>">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <!-- Sidebar (RSVP) -->
        <aside class="civic-event-sidebar">
            <div class="civic-card civic-rsvp-card">
                <h3 class="civic-section-subtitle">
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    Your RSVP
                </h3>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <form action="<?= $basePath ?>/events/rsvp" method="POST" class="civic-rsvp-form">
                        <?= Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">

                        <div class="civic-rsvp-buttons">
                            <button type="submit" name="status" value="going"
                                    class="civic-btn <?= $myStatus === 'going' ? 'civic-btn--success' : 'civic-btn--outline' ?>">
                                <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                                <?= $myStatus === 'going' ? 'Going' : 'Going' ?>
                            </button>
                            <button type="submit" name="status" value="declined"
                                    class="civic-btn <?= $myStatus === 'declined' ? 'civic-btn--danger' : 'civic-btn--outline' ?>">
                                <span class="dashicons dashicons-no" aria-hidden="true"></span>
                                <?= $myStatus === 'declined' ? 'Declined' : 'Decline' ?>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="civic-login-prompt">
                        <a href="<?= $basePath ?>/login">Sign in</a> to RSVP to this event.
                    </p>
                <?php endif; ?>

                <?php if (!empty($canInvite)): ?>
                    <div class="civic-rsvp-invite">
                        <a href="<?= $basePath ?>/events/invite/<?= $event['id'] ?>" class="civic-btn civic-btn--full-width">
                            <span class="dashicons dashicons-email" aria-hidden="true"></span>
                            Invite Members
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Event Host -->
            <div class="civic-card civic-card--host">
                <h3 class="civic-section-subtitle">
                    <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                    Event Host
                </h3>
                <a href="<?= $basePath ?>/profile/<?= $event['user_id'] ?>" class="civic-host-link">
                    <?= htmlspecialchars($event['user_name'] ?? 'Unknown') ?>
                </a>
            </div>
        </aside>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
