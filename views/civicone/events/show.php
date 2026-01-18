<?php
// CivicOne View: Event Details - MadeOpen Style
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
<div class="civic-action-bar" style="margin-bottom: 24px;">
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
                <section class="civic-card" style="margin-top: 24px;">
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
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--civic-border);">
                        <a href="<?= $basePath ?>/events/invite/<?= $event['id'] ?>" class="civic-btn" style="width: 100%;">
                            <span class="dashicons dashicons-email" aria-hidden="true"></span>
                            Invite Members
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Event Host -->
            <div class="civic-card" style="margin-top: 16px;">
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

<style>
    .civic-alert {
        padding: 16px 20px;
        border-radius: 8px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
    }

    .civic-alert--success {
        background: #ECFDF5;
        color: #047857;
        border: 1px solid #A7F3D0;
    }

    .civic-event-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 24px;
    }

    .civic-event-body {
        font-size: 1.1rem;
        line-height: 1.7;
        color: var(--civic-text-main);
        margin-bottom: 24px;
    }

    .civic-event-info-box {
        background: var(--civic-bg-page);
        padding: 24px;
        border-radius: 8px;
        border-left: 4px solid var(--civic-brand);
    }

    .civic-section-subtitle {
        font-size: 1rem;
        font-weight: 700;
        color: var(--civic-text-main);
        margin: 0 0 16px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .civic-section-subtitle .dashicons {
        color: var(--civic-brand);
    }

    .civic-event-details {
        margin: 0;
    }

    .civic-detail-item {
        display: flex;
        gap: 12px;
        margin-bottom: 12px;
        align-items: flex-start;
    }

    .civic-detail-item:last-child {
        margin-bottom: 0;
    }

    .civic-detail-item dt {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        color: var(--civic-text-secondary);
        min-width: 140px;
    }

    .civic-detail-item dd {
        margin: 0;
        color: var(--civic-text-main);
    }

    .civic-attendees-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .civic-attendee img {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--civic-bg-card);
        box-shadow: var(--civic-shadow-sm);
        transition: transform 0.2s;
    }

    .civic-attendee:hover img {
        transform: scale(1.1);
    }

    .civic-rsvp-card {
        text-align: center;
    }

    .civic-rsvp-buttons {
        display: flex;
        gap: 12px;
    }

    .civic-rsvp-buttons .civic-btn {
        flex: 1;
        justify-content: center;
    }

    .civic-btn--success {
        background: #047857 !important;
        color: white !important;
        border-color: #047857 !important;
    }

    .civic-btn--danger {
        background: #B91C1C !important;
        color: white !important;
        border-color: #B91C1C !important;
    }

    .civic-login-prompt {
        color: var(--civic-text-muted);
        text-align: center;
    }

    .civic-login-prompt a {
        color: var(--civic-brand);
        font-weight: 600;
    }

    .civic-host-link {
        display: block;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--civic-brand);
        text-decoration: none;
    }

    .civic-host-link:hover {
        text-decoration: underline;
    }

    @media (max-width: 900px) {
        .civic-event-grid {
            grid-template-columns: 1fr;
        }

        .civic-event-sidebar {
            order: -1;
        }

        .civic-detail-item {
            flex-direction: column;
            gap: 4px;
        }

        .civic-detail-item dt {
            min-width: auto;
        }
    }
</style>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
