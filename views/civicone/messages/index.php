<?php
/**
 * CivicOne View: Messages Inbox
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'My Messages';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Messages</li>
    </ol>
</nav>

<h1 class="govuk-heading-xl govuk-!-margin-bottom-6">
    <i class="fa-solid fa-inbox govuk-!-margin-right-2" aria-hidden="true"></i>
    Inbox
</h1>

<?php if (empty($threads)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <i class="fa-solid fa-envelope-open govuk-!-margin-right-2" aria-hidden="true"></i>
            <strong>You have no messages yet</strong>
        </p>
        <p class="govuk-body govuk-!-margin-bottom-4">Start a conversation by browsing listings and contacting other members.</p>
        <a href="<?= $basePath ?>/listings" class="govuk-button govuk-button--start" data-module="govuk-button">
            Browse Listings
            <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
            </svg>
        </a>
    </div>
<?php else: ?>
    <ul class="govuk-list" role="list">
        <?php foreach ($threads as $thread):
            // Check if this message is unread (receiver is current user and not read)
            $isUnread = ($thread['receiver_id'] == ($_SESSION['user_id'] ?? 0) && !$thread['is_read']);
        ?>
            <li class="govuk-!-margin-bottom-3" role="listitem">
                <a href="<?= $basePath ?>/messages/<?= $thread['other_user_id'] ?>"
                   class="govuk-link"
                   style="text-decoration: none; display: block;"
                   aria-label="Message thread with <?= htmlspecialchars($thread['other_user_name']) ?><?= $isUnread ? ' (unread)' : '' ?>">
                    <div class="govuk-!-padding-4 <?= $isUnread ? 'govuk-!-font-weight-bold' : '' ?>" style="border: 1px solid #b1b4b6; border-left: 5px solid <?= $isUnread ? '#1d70b8' : '#b1b4b6' ?>;">
                        <div class="govuk-grid-row">
                            <div class="govuk-grid-column-three-quarters">
                                <p class="govuk-body govuk-!-margin-bottom-1 <?= $isUnread ? 'govuk-!-font-weight-bold' : '' ?>">
                                    <?= htmlspecialchars($thread['other_user_name']) ?>
                                </p>
                                <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                                    <?= htmlspecialchars(mb_strimwidth($thread['body'], 0, 60, "...")) ?>
                                </p>
                            </div>
                            <div class="govuk-grid-column-one-quarter govuk-!-text-align-right">
                                <?php if ($isUnread): ?>
                                    <span class="govuk-tag govuk-tag--light-blue govuk-!-margin-right-2">New</span>
                                <?php endif; ?>
                                <time class="govuk-body-s" datetime="<?= $thread['created_at'] ?>" style="color: #505a5f;">
                                    <?= date('M j', strtotime($thread['created_at'])) ?>
                                </time>
                            </div>
                        </div>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
