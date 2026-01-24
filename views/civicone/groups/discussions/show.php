<?php
/**
 * CivicOne View: Discussion Thread
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Discussion';
require dirname(__DIR__, 3) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
$currentUserId = $_SESSION['user_id'] ?? 0;
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/groups">Local Hubs</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/groups/<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Discussion</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=discussions" class="govuk-back-link govuk-!-margin-bottom-6">Back to Hub</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl"><?= htmlspecialchars($discussion['title']) ?></h1>
        <p class="govuk-body-s govuk-!-margin-bottom-6" style="color: #505a5f;">
            Started by <strong><?= htmlspecialchars($discussion['author_name']) ?></strong> &middot; <?= count($posts) ?> <?= count($posts) === 1 ? 'message' : 'messages' ?>
        </p>

        <!-- Discussion Messages -->
        <div class="govuk-!-margin-bottom-6">
            <?php foreach ($posts as $post):
                $isMe = ($post['user_id'] == $currentUserId);
            ?>
                <div class="govuk-!-margin-bottom-4 govuk-!-padding-4" style="border: 1px solid #b1b4b6; border-left: 4px solid <?= $isMe ? '#1d70b8' : '#505a5f' ?>; background: <?= $isMe ? '#f3f2f1' : 'white' ?>;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                        <?php if (!empty($post['author_avatar'])): ?>
                            <img src="<?= htmlspecialchars($post['author_avatar']) ?>" loading="lazy" alt="" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #1d70b8; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                <?= strtoupper(substr($post['author_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <p class="govuk-body-s govuk-!-margin-bottom-0 govuk-!-font-weight-bold"><?= htmlspecialchars($post['author_name']) ?></p>
                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;"><?= date('j M Y, g:i A', strtotime($post['created_at'])) ?></p>
                        </div>
                    </div>
                    <p class="govuk-body govuk-!-margin-bottom-0"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                </div>
            <?php endforeach; ?>

            <?php if (empty($posts)): ?>
            <div class="govuk-inset-text">
                <p class="govuk-body">No messages in this discussion yet. Be the first to reply!</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Reply Form -->
        <?php if (isset($_SESSION['user_id']) && $isMember): ?>
        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <h2 class="govuk-heading-m">Reply to this discussion</h2>

        <form action="<?= $basePath ?>/groups/<?= $group['id'] ?>/discussions/<?= $discussion['id'] ?>/reply" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <div class="govuk-form-group">
                <label class="govuk-label" for="content">Your message</label>
                <textarea class="govuk-textarea" id="content" name="content" rows="4" required></textarea>
            </div>

            <button type="submit" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i>
                Send reply
            </button>
        </form>
        <?php else: ?>
        <div class="govuk-inset-text">
            <p class="govuk-body">
                <i class="fa-solid fa-lock govuk-!-margin-right-1" aria-hidden="true"></i>
                Only members can reply to discussions.
                <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="govuk-link">Join this hub</a> to participate.
            </p>
        </div>
        <?php endif; ?>

    </div>

    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-s">About this hub</h2>
            <p class="govuk-body-s"><?= htmlspecialchars($group['name']) ?></p>
            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="govuk-link">View hub</a>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 3) . '/layouts/civicone/footer.php'; ?>
