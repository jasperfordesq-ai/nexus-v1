<?php
/**
 * CivicOne View: Message Thread (Chat)
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Chat with ' . $otherUser['name'];
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Messages', 'href' => $basePath . '/messages'],
        ['text' => htmlspecialchars($otherUser['name'])]
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<div class="govuk-grid-row govuk-!-margin-bottom-4">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-0">
            <i class="fa-solid fa-comment govuk-!-margin-right-2" aria-hidden="true"></i>
            <?= htmlspecialchars($otherUser['name']) ?>
        </h1>
    </div>
    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
        <a href="<?= $basePath ?>/messages" class="govuk-button govuk-button--secondary" data-module="govuk-button">
            <i class="fa-solid fa-inbox govuk-!-margin-right-1" aria-hidden="true"></i> Back to Inbox
        </a>
    </div>
</div>

<div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-card-bordered-simple" role="log" aria-label="Message conversation with <?= htmlspecialchars($otherUser['name']) ?>">

    <!-- Messages Area -->
    <div id="chat-messages" class="govuk-!-margin-bottom-4 civicone-chat-area">
        <?php foreach ($messages as $msg):
            $isMe = $msg['sender_id'] == $_SESSION['user_id'];
        ?>
            <div class="govuk-!-margin-bottom-3 <?= $isMe ? 'civicone-chat-row-end' : 'civicone-chat-row-start' ?>">
                <div class="govuk-!-padding-3 <?= $isMe ? 'civicone-chat-bubble-me' : 'civicone-chat-bubble-them' ?>">
                    <p class="govuk-body-s govuk-!-margin-bottom-1 <?= $isMe ? 'civicone-chat-meta-me' : 'civicone-chat-meta-them' ?>">
                        <strong><?= $isMe ? 'You' : htmlspecialchars($otherUser['name']) ?></strong>
                        <time datetime="<?= $msg['created_at'] ?>" class="govuk-!-margin-left-2"><?= date('H:i', strtotime($msg['created_at'])) ?></time>
                    </p>
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <?= nl2br(htmlspecialchars($msg['body'])) ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Reply Form -->
    <form action="<?= $basePath ?>/messages/store" method="POST">
        <?= Nexus\Core\Csrf::input() ?>
        <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?>">

        <div class="govuk-form-group">
            <label for="message-body" class="govuk-label">Write a message</label>
            <textarea name="body" id="message-body" rows="3" class="govuk-textarea" placeholder="Type your message here..." required></textarea>
        </div>
        <button type="submit" class="govuk-button" data-module="govuk-button">
            <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i> Send
        </button>
    </form>

</div>

<!-- Scroll to bottom handled by civicone-common.js initScrollToBottom() -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
