<?php
// CivicOne Poll Detail View - GOV.UK WCAG 2.1 AA Compliant
if (session_status() === PHP_SESSION_NONE) session_start();

$hTitle = "Poll";
$hSubtitle = $poll['question'];
$hType = 'Community Poll';

// Load page-specific CSS
$additionalCSS = '<link rel="stylesheet" href="/assets/css/civicone-polls-show.css">';

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="civic-container poll-container">

    <div class="civic-card">

        <?php if (!empty($poll['description'])): ?>
            <p class="poll-description">
                <?= htmlspecialchars($poll['description']) ?>
            </p>
        <?php endif; ?>

        <?php if ($hasVoted): ?>
            <!-- RESULTS VIEW -->
            <div class="civic-success-box" role="status" aria-live="polite">
                <h3 class="civic-text-green-dark poll-success-heading">Thank you for voting!</h3>
                <p class="poll-success-text">Here are the current results:</p>
            </div>

            <div class="poll-results" aria-label="Poll results">
                <?php foreach ($options as $index => $opt): ?>
                    <?php
                    $percent = $totalVotes > 0 ? round(($opt['vote_count'] / $totalVotes) * 100) : 0;
                    $optionId = 'poll-option-' . $index;
                    ?>
                    <div class="poll-result-item">
                        <div class="poll-result-header" id="<?= $optionId ?>-label">
                            <span class="poll-result-label"><?= htmlspecialchars($opt['label']) ?></span>
                            <span class="poll-result-percentage"><?= $percent ?>% (<?= $opt['vote_count'] ?>)</span>
                        </div>
                        <div class="poll-progress-container">
                            <div
                                class="poll-progress-bar"
                                role="progressbar"
                                aria-valuenow="<?= $percent ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-labelledby="<?= $optionId ?>-label"
                                aria-label="<?= htmlspecialchars($opt['label']) ?>: <?= $percent ?> percent, <?= $opt['vote_count'] ?> votes"
                                style="width: <?= $percent ?>%;"
                            ></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="poll-total-votes" aria-live="polite">
                    Total Votes Cast: <?= $totalVotes ?>
                </div>
            </div>

        <?php else: ?>
            <!-- VOTING VIEW -->
            <?php if (!isset($_SESSION['user_id'])): ?>
                <p class="poll-login-prompt">
                    Please <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="poll-login-link">login</a> to cast your vote.
                </p>
            <?php else: ?>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/polls/vote" method="POST" aria-label="Poll voting form">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">

                    <fieldset class="poll-options-group">
                        <legend class="poll-sr-only">Select your vote for: <?= htmlspecialchars($poll['question']) ?></legend>
                        <?php foreach ($options as $index => $opt): ?>
                            <?php $optionInputId = 'poll-vote-option-' . $opt['id']; ?>
                            <label class="poll-option" for="<?= $optionInputId ?>">
                                <input
                                    type="radio"
                                    name="option_id"
                                    id="<?= $optionInputId ?>"
                                    value="<?= $opt['id'] ?>"
                                    required
                                    class="poll-option-input"
                                    aria-describedby="poll-option-desc-<?= $opt['id'] ?>"
                                >
                                <span class="poll-option-indicator" aria-hidden="true"></span>
                                <span class="poll-option-label" id="poll-option-desc-<?= $opt['id'] ?>"><?= htmlspecialchars($opt['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <button type="submit" class="poll-submit-btn" aria-label="Submit your vote for this poll">
                        Submit My Vote
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

</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
