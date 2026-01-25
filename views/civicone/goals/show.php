<?php
/**
 * CivicOne View: Goal Detail
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = htmlspecialchars($goal['title']);
$isAuthor = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $goal['user_id'];
$hasMentor = !empty($goal['mentor_id']);
$isMentor = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $goal['mentor_id'];

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/goals">Goals</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page"><?= htmlspecialchars($goal['title']) ?></li>
    </ol>
</nav>

<a href="<?= $basePath ?>/goals" class="govuk-back-link govuk-!-margin-bottom-6">Back to Goals</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <!-- Status Tag -->
        <?php if ($goal['status'] === 'completed'): ?>
            <span class="govuk-tag govuk-tag--green govuk-!-margin-bottom-4">Completed</span>
        <?php else: ?>
            <span class="govuk-tag govuk-tag--light-blue govuk-!-margin-bottom-4">Active Goal</span>
        <?php endif; ?>

        <h1 class="govuk-heading-xl govuk-!-margin-bottom-4"><?= htmlspecialchars($goal['title']) ?></h1>

        <?php if ($isAuthor): ?>
            <div class="govuk-button-group govuk-!-margin-bottom-6">
                <?php if ($goal['status'] === 'active'): ?>
                    <form action="<?= $basePath ?>/goals/<?= $goal['id'] ?>/complete" method="POST" class="civicone-inline-form"
                          onsubmit="return confirm('Mark as achieved? Great job!')">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <button type="submit" class="govuk-button" data-module="govuk-button">
                            <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                            Mark Complete
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= $basePath ?>/goals/<?= $goal['id'] ?>/edit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-pen govuk-!-margin-right-1" aria-hidden="true"></i>
                    Edit
                </a>
            </div>
        <?php endif; ?>

        <!-- Description -->
        <p class="govuk-body-l govuk-!-margin-bottom-6"><?= nl2br(htmlspecialchars($goal['description'])) ?></p>

        <!-- Buddy Status -->
        <?php if ($hasMentor): ?>
            <!-- Matched State -->
            <div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="region" aria-labelledby="buddy-heading">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="buddy-heading">Accountability Partner</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">
                        <i class="fa-solid fa-handshake govuk-!-margin-right-1" aria-hidden="true"></i>
                        Matched with <?= htmlspecialchars($goal['mentor_name']) ?>
                    </p>
                    <?php if ($isAuthor || $isMentor): ?>
                        <p class="govuk-body">
                            <a href="<?= $basePath ?>/messages?create=1&to=<?= $isAuthor ? $goal['mentor_id'] : $goal['user_id'] ?>" class="govuk-link">
                                Send a message
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($goal['is_public']): ?>
            <!-- Looking for Buddy -->
            <div class="govuk-inset-text govuk-!-margin-bottom-6">
                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                    <i class="fa-solid fa-magnifying-glass govuk-!-margin-right-1" aria-hidden="true"></i>
                    Looking for an Accountability Partner
                </h3>
                <p class="govuk-body govuk-!-margin-bottom-2">This goal is public. Waiting for a community member to offer support.</p>
                <?php if (!$isAuthor && isset($_SESSION['user_id'])): ?>
                    <form action="<?= $basePath ?>/goals/buddy" method="POST" class="civicone-inline-form"
                          onsubmit="return confirm('Are you sure you want to be the accountability partner for this goal?');">
                        <?= \Nexus\Core\Csrf::input() ?>
                        <input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
                        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                            <i class="fa-solid fa-handshake govuk-!-margin-right-1" aria-hidden="true"></i>
                            Become Buddy
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Private Goal -->
            <div class="govuk-inset-text govuk-!-margin-bottom-6">
                <p class="govuk-body govuk-!-margin-bottom-0">
                    <i class="fa-solid fa-lock govuk-!-margin-right-1" aria-hidden="true"></i>
                    <strong>Private Goal</strong> — Only you can see this goal.
                </p>
            </div>
        <?php endif; ?>

        <!-- Social Engagement Section -->
        <?php
        $goalId = $goal['id'];
        $likesCount = $likesCount ?? 0;
        $commentsCount = $commentsCount ?? 0;
        $isLiked = $isLiked ?? false;
        $isLoggedIn = $isLoggedIn ?? !empty($_SESSION['user_id']);
        ?>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <div class="govuk-!-margin-bottom-6">
            <div class="govuk-button-group">
                <button id="like-btn" type="button" onclick="goalToggleLike()"
                        class="govuk-button <?= $isLiked ? '' : 'govuk-button--secondary' ?>" data-module="govuk-button">
                    <i class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart govuk-!-margin-right-1" id="like-icon" aria-hidden="true"></i>
                    <span id="like-count"><?= $likesCount ?></span> <?= $likesCount === 1 ? 'Like' : 'Likes' ?>
                </button>
                <button type="button" onclick="goalToggleComments()" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-regular fa-comment govuk-!-margin-right-1" aria-hidden="true"></i>
                    <span id="comment-count"><?= $commentsCount ?></span> <?= $commentsCount === 1 ? 'Comment' : 'Comments' ?>
                </button>
                <?php if ($isLoggedIn): ?>
                <button type="button" onclick="shareToFeed()" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-share govuk-!-margin-right-1" aria-hidden="true"></i>
                    Share
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments Section -->
        <div id="comments-section" class="govuk-!-margin-bottom-6" hidden>
            <?php if ($isLoggedIn): ?>
            <form onsubmit="goalSubmitComment(event)" class="govuk-!-margin-bottom-4">
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-visually-hidden" for="comment-input">Write a comment</label>
                    <textarea id="comment-input" class="govuk-textarea" rows="3" placeholder="Write a comment..."></textarea>
                </div>
                <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    Post Comment
                </button>
            </form>
            <?php else: ?>
            <p class="govuk-body">
                <a href="<?= $basePath ?>/login" class="govuk-link">Log in</a> to leave a comment.
            </p>
            <?php endif; ?>
            <div id="comments-list">
                <p class="govuk-body civicone-secondary-text">Loading comments...</p>
            </div>
        </div>

    </div>
</div>

<!-- Initialize goal social interactions via external JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initGoalSocial === 'function') {
        window.initGoalSocial({
            goalId: <?= $goalId ?>,
            isLoggedIn: <?= $isLoggedIn ? 'true' : 'false' ?>,
            isLiked: <?= $isLiked ? 'true' : 'false' ?>,
            apiBase: '<?= $basePath ?>/api/social',
            basePath: '<?= $basePath ?>'
        });
    }
});
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
