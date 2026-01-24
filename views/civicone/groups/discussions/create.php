<?php
/**
 * CivicOne View: Create Discussion
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Start a Discussion';
require dirname(__DIR__, 3) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
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
        <li class="govuk-breadcrumbs__list-item" aria-current="page">New Discussion</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=discussions" class="govuk-back-link govuk-!-margin-bottom-6">Back to Hub</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">
            <i class="fa-regular fa-comments govuk-!-margin-right-2" aria-hidden="true"></i>
            Start a Discussion
        </h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Start a conversation with the <?= htmlspecialchars($group['name']) ?> community.</p>

        <form action="<?= $basePath ?>/groups/<?= $group['id'] ?>/discussions/store" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="title">
                    Discussion topic
                </label>
                <div id="title-hint" class="govuk-hint">
                    Give your discussion a clear, descriptive title
                </div>
                <input class="govuk-input" id="title" name="title" type="text" aria-describedby="title-hint" required autofocus>
            </div>

            <!-- Content -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="content">
                    Your message
                </label>
                <div id="content-hint" class="govuk-hint">
                    Share your thoughts, ask a question, or start a debate. Markdown is supported.
                </div>
                <textarea class="govuk-textarea" id="content" name="content" rows="6" aria-describedby="content-hint" required></textarea>
            </div>

            <div class="govuk-button-group">
                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i>
                    Post Discussion
                </button>
                <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=discussions" class="govuk-link">Cancel</a>
            </div>
        </form>

    </div>
</div>

<?php require dirname(__DIR__, 3) . '/layouts/civicone/footer.php'; ?>
