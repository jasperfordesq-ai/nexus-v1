<?php
// CivicOne View: Message Thread (Chat) - WCAG 2.1 AA Compliant
// CSS extracted to civicone-messages.css
$pageTitle = 'Chat with ' . $otherUser['name'];
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div class="civic-thread-header">
        <h1>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages"
               class="civic-thread-back"
               aria-label="Back to inbox">&larr;</a>
            <?= htmlspecialchars($otherUser['name']) ?>
        </h1>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages" class="civic-btn civic-inbox-btn">Inbox</a>
    </div>

    <div class="civic-card civic-chat-container" role="log" aria-label="Message conversation with <?= htmlspecialchars($otherUser['name']) ?>">

        <!-- Messages Area -->
        <div id="chat-messages" class="civic-chat-messages">
            <?php foreach ($messages as $msg):
                $isMe = $msg['sender_id'] == $_SESSION['user_id'];
                $wrapperClass = $isMe ? 'civic-chat-bubble-wrapper civic-chat-bubble-wrapper--sent' : 'civic-chat-bubble-wrapper civic-chat-bubble-wrapper--received';
                $bubbleClass = $isMe ? 'civic-chat-bubble civic-chat-bubble--sent' : 'civic-chat-bubble civic-chat-bubble--received';
            ?>
                <div class="<?= $wrapperClass ?>">
                    <div class="<?= $bubbleClass ?>">
                        <div class="civic-chat-sender">
                            <?= $isMe ? 'You' : htmlspecialchars($otherUser['name']) ?>
                            <time class="civic-chat-time" datetime="<?= $msg['created_at'] ?>"><?= date('H:i', strtotime($msg['created_at'])) ?></time>
                        </div>
                        <div class="civic-chat-text">
                            <?= nl2br(htmlspecialchars($msg['body'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Reply Form -->
        <div class="civic-chat-reply">
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages/store" method="POST" class="civic-chat-reply-form">
                <?= Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?>">

                <label for="message-body" class="visually-hidden">Write a message</label>
                <textarea name="body" id="message-body" rows="2" placeholder="Write a message..." required class="civic-input civic-chat-textarea"></textarea>
                <button type="submit" class="civic-btn civic-chat-send">Send</button>
            </form>
        </div>

    </div>

</div>

<!-- Scroll to bottom script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatBox = document.getElementById('chat-messages');
        chatBox.scrollTop = chatBox.scrollHeight;
    });
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>