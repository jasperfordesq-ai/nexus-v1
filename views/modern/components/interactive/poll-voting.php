<?php

/**
 * Component: Poll Voting
 *
 * Poll voting and results display.
 * Used on: polls/show, polls embedded in feed
 *
 * @param array $poll Poll data with keys: id, question, options, total_votes, has_voted, user_vote, ends_at
 * @param bool $showResults Force show results (default: auto based on has_voted)
 * @param string $formAction Form action URL
 * @param string $class Additional CSS classes
 * @param bool $isLoggedIn Whether user is logged in (default: true)
 */

$poll = $poll ?? [];
$showResults = $showResults ?? null;
$formAction = $formAction ?? '';
$class = $class ?? '';
$isLoggedIn = $isLoggedIn ?? true;

// Extract poll data
$pollId = $poll['id'] ?? 0;
$question = $poll['question'] ?? $poll['title'] ?? '';
$options = $poll['options'] ?? [];
$totalVotes = $poll['total_votes'] ?? array_sum(array_column($options, 'vote_count'));
$hasVoted = $poll['has_voted'] ?? false;
$userVote = $poll['user_vote'] ?? null;
$endsAt = $poll['ends_at'] ?? null;

// Auto-determine if we should show results
if ($showResults === null) {
    $showResults = $hasVoted;
}

// Check if poll has ended
$hasEnded = false;
if ($endsAt) {
    $endTime = is_string($endsAt) ? strtotime($endsAt) : $endsAt;
    $hasEnded = time() > $endTime;
    if ($hasEnded) {
        $showResults = true;
    }
}

$cssClass = trim('component-poll ' . $class);
?>

<div class="<?= e($cssClass) ?>" id="poll-<?= $pollId ?>" data-poll-id="<?= $pollId ?>">
    <?php if ($question): ?>
        <h3 class="component-poll__question">
            <?= e($question) ?>
        </h3>
    <?php endif; ?>

    <?php if ($showResults): ?>
        <!-- Results View -->
        <div class="component-poll__results">
            <?php foreach ($options as $option): ?>
                <?php
                $optionId = $option['id'] ?? 0;
                $optionLabel = $option['label'] ?? $option['text'] ?? '';
                $optionVotes = $option['vote_count'] ?? $option['votes'] ?? 0;
                $percent = $totalVotes > 0 ? round(($optionVotes / $totalVotes) * 100) : 0;
                $isUserVote = $userVote === $optionId;

                $labelClass = 'component-poll__result-label';
                if ($isUserVote) $labelClass .= ' component-poll__result-label--voted';

                $fillClass = 'component-poll__progress-fill';
                if ($isUserVote) $fillClass .= ' component-poll__progress-fill--voted';
                ?>
                <div class="component-poll__result-item">
                    <div class="component-poll__result-header">
                        <span class="<?= e($labelClass) ?>">
                            <?= e($optionLabel) ?>
                            <?php if ($isUserVote): ?>
                                <i class="fa-solid fa-check-circle component-poll__check-icon"></i>
                            <?php endif; ?>
                        </span>
                        <span class="component-poll__result-stats">
                            <?= $percent ?>% (<?= number_format($optionVotes) ?>)
                        </span>
                    </div>
                    <div class="component-poll__progress-track">
                        <div class="<?= e($fillClass) ?>" style="width: <?= $percent ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="component-poll__meta">
                <span class="component-poll__total-votes">
                    <i class="fa-solid fa-users"></i>
                    <?= number_format($totalVotes) ?> vote<?= $totalVotes !== 1 ? 's' : '' ?>
                </span>
                <?php if ($hasEnded): ?>
                    <span class="component-poll__ended">
                        <i class="fa-solid fa-clock"></i> Poll ended
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($hasVoted): ?>
                <div class="component-poll__thanks">
                    <i class="fa-solid fa-check-circle"></i> Thanks for voting!
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($isLoggedIn): ?>
        <!-- Voting Form -->
        <form class="component-poll__form" action="<?= e($formAction ?: "/polls/{$pollId}/vote") ?>" method="POST" onsubmit="submitPollVote(event, <?= $pollId ?>)">
            <input type="hidden" name="poll_id" value="<?= $pollId ?>">

            <div class="component-poll__options">
                <?php foreach ($options as $index => $option): ?>
                    <?php
                    $optionId = $option['id'] ?? $index;
                    $optionLabel = $option['label'] ?? $option['text'] ?? '';
                    ?>
                    <label class="component-poll__option-label">
                        <input
                            type="radio"
                            name="option_id"
                            value="<?= e($optionId) ?>"
                            required
                            class="component-poll__option-input"
                        >
                        <span class="component-poll__option-text"><?= e($optionLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="component-poll__submit nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-vote-yea"></i> Submit Vote
            </button>
        </form>

    <?php else: ?>
        <!-- Login Prompt -->
        <div class="component-poll__login-prompt">
            <p class="component-poll__login-text">Log in to vote in this poll</p>
            <a href="/login" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-sign-in-alt"></i> Log In to Vote
            </a>
        </div>
    <?php endif; ?>
</div>
