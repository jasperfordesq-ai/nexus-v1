<?php
/**
 * CivicOne View: Poll Details
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = $poll['question'];
require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/polls">Polls</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Poll</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/polls" class="govuk-back-link govuk-!-margin-bottom-6">Back to polls</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl"><?= htmlspecialchars($poll['question']) ?></h1>

        <?php if (!empty($poll['description'])): ?>
            <p class="govuk-body-l govuk-!-margin-bottom-6"><?= htmlspecialchars($poll['description']) ?></p>
        <?php endif; ?>

        <?php if ($hasVoted): ?>
            <!-- Results View -->
            <div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">
                        <i class="fa-solid fa-check-circle govuk-!-margin-right-2" aria-hidden="true"></i>
                        Thank you for voting!
                    </p>
                    <p class="govuk-body">Here are the current results:</p>
                </div>
            </div>

            <h2 class="govuk-heading-l">Results</h2>
            <div class="govuk-!-margin-bottom-6">
                <?php foreach ($options as $index => $opt): ?>
                    <?php
                    $percent = $totalVotes > 0 ? round(($opt['vote_count'] / $totalVotes) * 100) : 0;
                    $barColor = $percent >= 50 ? '#00703c' : ($percent >= 25 ? '#1d70b8' : '#505a5f');
                    ?>
                    <div class="govuk-!-margin-bottom-4">
                        <div class="govuk-grid-row govuk-!-margin-bottom-1">
                            <div class="govuk-grid-column-two-thirds">
                                <p class="govuk-body govuk-!-margin-bottom-0"><strong><?= htmlspecialchars($opt['label']) ?></strong></p>
                            </div>
                            <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                                <p class="govuk-body govuk-!-margin-bottom-0"><?= $percent ?>% (<?= $opt['vote_count'] ?> votes)</p>
                            </div>
                        </div>
                        <div class="civicone-panel-bg" style="height: 24px; border-radius: 4px; overflow: hidden;"
                             role="progressbar" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"
                             aria-label="<?= htmlspecialchars($opt['label']) ?>: <?= $percent ?> percent">
                            <div style="width: <?= $percent ?>%; height: 100%; background: <?= $barColor ?>; transition: width 0.3s;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <p class="govuk-body govuk-!-margin-top-6" style="color: #505a5f;">
                    <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i>
                    <strong>Total votes cast: <?= $totalVotes ?></strong>
                </p>
            </div>

        <?php else: ?>
            <!-- Voting View -->
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="govuk-inset-text">
                    <p class="govuk-body">
                        Please <a href="<?= $basePath ?>/login" class="govuk-link">sign in</a> to cast your vote.
                    </p>
                </div>
            <?php else: ?>
                <form action="<?= $basePath ?>/polls/vote" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">

                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                                <h2 class="govuk-fieldset__heading">Select your answer</h2>
                            </legend>

                            <div class="govuk-radios" data-module="govuk-radios">
                                <?php foreach ($options as $opt): ?>
                                    <div class="govuk-radios__item">
                                        <input class="govuk-radios__input" id="option-<?= $opt['id'] ?>" name="option_id" type="radio" value="<?= $opt['id'] ?>" required>
                                        <label class="govuk-label govuk-radios__label" for="option-<?= $opt['id'] ?>">
                                            <?= htmlspecialchars($opt['label']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    </div>

                    <button type="submit" class="govuk-button" data-module="govuk-button">
                        <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                        Submit my vote
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Social Interactions -->
        <?php
        $targetType = 'poll';
        $targetId = $poll['id'];
        include dirname(__DIR__) . '/partials/social_interactions.php';
        ?>

    </div>

    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-6" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-s">Poll details</h2>
            <dl class="govuk-summary-list govuk-summary-list--no-border">
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Status</dt>
                    <dd class="govuk-summary-list__value">
                        <span class="govuk-tag <?= ($poll['status'] ?? 'active') === 'active' ? 'govuk-tag--green' : 'govuk-tag--grey' ?>">
                            <?= ucfirst($poll['status'] ?? 'Active') ?>
                        </span>
                    </dd>
                </div>
                <?php if (!empty($poll['end_date'])): ?>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Closes</dt>
                    <dd class="govuk-summary-list__value">
                        <time datetime="<?= date('Y-m-d', strtotime($poll['end_date'])) ?>">
                            <?= date('j F Y', strtotime($poll['end_date'])) ?>
                        </time>
                    </dd>
                </div>
                <?php endif; ?>
                <div class="govuk-summary-list__row">
                    <dt class="govuk-summary-list__key">Total votes</dt>
                    <dd class="govuk-summary-list__value"><?= $totalVotes ?></dd>
                </div>
            </dl>

            <?php if (isset($_SESSION['user_id']) && isset($poll['user_id']) && $_SESSION['user_id'] == $poll['user_id']): ?>
            <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">
            <a href="<?= $basePath ?>/polls/edit/<?= $poll['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button" style="width: 100%;">
                <i class="fa-solid fa-edit govuk-!-margin-right-1" aria-hidden="true"></i> Edit Poll
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
