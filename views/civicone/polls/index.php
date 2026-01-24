<?php
/**
 * CivicOne View: Polls Index
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Community Polls';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Community Polls</li>
    </ol>
</nav>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">
            <i class="fa-solid fa-square-poll-vertical govuk-!-margin-right-2" aria-hidden="true"></i>
            Community Polls
        </h1>
        <p class="govuk-body-l">Vote on important community decisions.</p>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/polls/create" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Create Poll
        </a>
    </div>
    <?php endif; ?>
</div>

<h2 class="govuk-heading-l">Active Polls</h2>

<?php if (empty($polls)): ?>
    <div class="govuk-inset-text">
        <p class="govuk-body-l govuk-!-margin-bottom-2">
            <span aria-hidden="true">🗳️</span>
            <strong>No active polls</strong>
        </p>
        <p class="govuk-body govuk-!-margin-bottom-4">There are no active polls at the moment.</p>
        <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $basePath ?>/polls/create" class="govuk-button govuk-button--start" data-module="govuk-button">
            Create the first poll
            <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
            </svg>
        </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="govuk-grid-row">
        <?php foreach ($polls as $poll): ?>
            <div class="govuk-grid-column-one-half govuk-!-margin-bottom-6">
                <div class="govuk-!-padding-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8; height: 100%;">
                    <h3 class="govuk-heading-m govuk-!-margin-bottom-3"><?= htmlspecialchars($poll['question']) ?></h3>

                    <p class="govuk-body-s govuk-!-margin-bottom-4" style="color: #505a5f;">
                        Status:
                        <span class="govuk-tag <?= $poll['status'] === 'active' ? 'govuk-tag--green' : 'govuk-tag--grey' ?>">
                            <?= ucfirst($poll['status']) ?>
                        </span>
                    </p>

                    <a href="<?= $basePath ?>/polls/<?= $poll['id'] ?>"
                       class="govuk-button govuk-button--secondary"
                       data-module="govuk-button"
                       aria-label="View and vote on: <?= htmlspecialchars($poll['question']) ?>">
                        <i class="fa-solid fa-vote-yea govuk-!-margin-right-1" aria-hidden="true"></i>
                        View & Vote
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
