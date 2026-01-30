<?php
/**
 * CivicOne View: Connections (My Friends)
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$currentUserId = $_SESSION['user_id'] ?? 0;
$isLoggedIn = !empty($currentUserId);

if (!$isLoggedIn) {
    header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
    exit;
}

$basePath = \Nexus\Core\TenantContext::getBasePath();
$pageTitle = 'My Friends';

require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'My Friends']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-user-group govuk-!-margin-right-2" aria-hidden="true"></i>
            My Friends
        </h1>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/members" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-search govuk-!-margin-right-1" aria-hidden="true"></i> Find Members
        </a>
    </div>
</div>

<!-- Pending Requests -->
<?php if (!empty($pending)): ?>
<div class="govuk-!-margin-bottom-8">
    <h2 class="govuk-heading-l">
        Pending Requests
        <span class="govuk-tag govuk-tag--yellow govuk-!-margin-left-2"><?= count($pending) ?></span>
    </h2>

    <div class="govuk-grid-row">
        <?php foreach ($pending as $req): ?>
            <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                <div class="govuk-!-padding-4 civicone-card-border-left-orange">
                    <div class="civicone-connection-row">
                        <a href="<?= $basePath ?>/profile/<?= $req['requester_id'] ?? $req['id'] ?>">
                            <img src="<?= htmlspecialchars($req['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>"
                                 loading="lazy"
                                 alt=""
                                 class="civicone-avatar-md">
                        </a>
                        <div class="civicone-connection-info">
                            <p class="govuk-body govuk-!-margin-bottom-0">
                                <a href="<?= $basePath ?>/profile/<?= $req['requester_id'] ?? $req['id'] ?>" class="govuk-link">
                                    <strong><?= htmlspecialchars($req['requester_name']) ?></strong>
                                </a>
                            </p>
                            <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">Wants to connect</p>
                        </div>
                        <form action="<?= $basePath ?>/connections/accept" method="POST">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="connection_id" value="<?= $req['id'] ?>">
                            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i> Accept
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Friends List -->
<h2 class="govuk-heading-l">
    Friends
    <span class="govuk-tag govuk-tag--light-blue govuk-!-margin-left-2"><?= count($friends) ?></span>
</h2>

<?php if (empty($friends)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <span aria-hidden="true">👋</span>
            <strong>No friends yet</strong>
        </p>
        <p class="govuk-body govuk-!-margin-bottom-4">Connect with other members to grow your network.</p>
        <a href="<?= $basePath ?>/members" class="govuk-button govuk-button--start" data-module="govuk-button">
            Find Members
            <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
            </svg>
        </a>
    </div>
<?php else: ?>
    <table class="govuk-table" aria-label="Friends list">
        <caption class="govuk-table__caption govuk-visually-hidden">Your friends</caption>
        <thead class="govuk-table__head">
            <tr class="govuk-table__row">
                <th scope="col" class="govuk-table__header">Member</th>
                <th scope="col" class="govuk-table__header">Location</th>
                <th scope="col" class="govuk-table__header">Status</th>
                <th scope="col" class="govuk-table__header govuk-table__header--numeric">Actions</th>
            </tr>
        </thead>
        <tbody class="govuk-table__body">
            <?php foreach ($friends as $friend):
                $friendIsOnline = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-5 minutes');
                $friendIsRecent = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-24 hours');
            ?>
                <tr class="govuk-table__row">
                    <td class="govuk-table__cell">
                        <a href="<?= $basePath ?>/profile/<?= $friend['id'] ?>" class="govuk-link civicone-member-link">
                            <span class="civicone-avatar-wrapper">
                                <img src="<?= htmlspecialchars($friend['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>"
                                     loading="lazy"
                                     alt=""
                                     class="civicone-avatar-sm-img">
                                <?php if ($friendIsOnline): ?>
                                    <span class="civicone-status-dot civicone-status-online" aria-hidden="true"></span>
                                <?php elseif ($friendIsRecent): ?>
                                    <span class="civicone-status-dot civicone-status-recent" aria-hidden="true"></span>
                                <?php endif; ?>
                            </span>
                            <strong><?= htmlspecialchars($friend['name']) ?></strong>
                        </a>
                    </td>
                    <td class="govuk-table__cell">
                        <?php if (!empty($friend['location'])): ?>
                            <?= htmlspecialchars($friend['location']) ?>
                        <?php else: ?>
                            <span class="civicone-secondary-text">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="govuk-table__cell">
                        <?php if ($friendIsOnline): ?>
                            <span class="govuk-tag govuk-tag--green">Online</span>
                        <?php elseif ($friendIsRecent): ?>
                            <span class="govuk-tag govuk-tag--yellow">Active today</span>
                        <?php else: ?>
                            <span class="govuk-tag govuk-tag--grey">Offline</span>
                        <?php endif; ?>
                    </td>
                    <td class="govuk-table__cell govuk-table__cell--numeric">
                        <a href="<?= $basePath ?>/messages/thread/<?= $friend['id'] ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                            <i class="fa-solid fa-envelope govuk-!-margin-right-1" aria-hidden="true"></i> Message
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
