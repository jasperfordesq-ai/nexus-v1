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
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">My Friends</li>
    </ol>
</nav>

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
                <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 5px solid #f47738;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <a href="<?= $basePath ?>/profile/<?= $req['requester_id'] ?? $req['id'] ?>">
                            <img src="<?= htmlspecialchars($req['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>"
                                 loading="lazy"
                                 alt=""
                                 style="width: 48px; height: 48px; border-radius: 50%;">
                        </a>
                        <div style="flex: 1;">
                            <p class="govuk-body govuk-!-margin-bottom-0">
                                <a href="<?= $basePath ?>/profile/<?= $req['requester_id'] ?? $req['id'] ?>" class="govuk-link">
                                    <strong><?= htmlspecialchars($req['requester_name']) ?></strong>
                                </a>
                            </p>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">Wants to connect</p>
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
                        <a href="<?= $basePath ?>/profile/<?= $friend['id'] ?>" class="govuk-link" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none;">
                            <span style="position: relative;">
                                <img src="<?= htmlspecialchars($friend['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>"
                                     loading="lazy"
                                     alt=""
                                     style="width: 40px; height: 40px; border-radius: 50%;">
                                <?php if ($friendIsOnline): ?>
                                    <span style="position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; background: #00703c; border: 2px solid white; border-radius: 50%;" aria-hidden="true"></span>
                                <?php elseif ($friendIsRecent): ?>
                                    <span style="position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; background: #f47738; border: 2px solid white; border-radius: 50%;" aria-hidden="true"></span>
                                <?php endif; ?>
                            </span>
                            <strong><?= htmlspecialchars($friend['name']) ?></strong>
                        </a>
                    </td>
                    <td class="govuk-table__cell">
                        <?php if (!empty($friend['location'])): ?>
                            <?= htmlspecialchars($friend['location']) ?>
                        <?php else: ?>
                            <span style="color: #505a5f;">-</span>
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
