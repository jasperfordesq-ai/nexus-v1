<?php
// CivicOne View: Message Thread (Chat)
$pageTitle = 'Chat with ' . $otherUser['name'];
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid var(--skin-primary, #00796B); padding-bottom: 10px;">
        <h1 style="margin: 0; text-transform: uppercase;">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages" style="text-decoration: none; color: inherit;">&larr;</a>
            <?= htmlspecialchars($otherUser['name']) ?>
        </h1>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages" class="civic-btn" style="background: #ccc; color: #333; font-size: 0.9rem; padding: 5px 15px;">Inbox</a>
    </div>

    <div class="civic-card civic-chat-container" style="display: flex; flex-direction: column; height: 70vh;">

        <!-- Messages Area -->
        <div id="chat-messages" style="flex: 1; overflow-y: auto; padding: 20px; background: #fafafa; border-bottom: 1px solid #eee;">
            <?php foreach ($messages as $msg):
                $isMe = $msg['sender_id'] == $_SESSION['user_id'];
                $align = $isMe ? 'flex-end' : 'flex-start';
                $bg = $isMe ? 'var(--skin-primary)' : '#e5e7eb';
                $color = $isMe ? '#fff' : '#1f2937';
            ?>
                <div style="display: flex; justify-content: <?= $align ?>; margin-bottom: 15px;">
                    <div style="max-width: 70%; padding: 10px 15px; border-radius: 12px; background: <?= $bg ?>; color: <?= $color ?>; position: relative;">
                        <div style="font-weight: bold; font-size: 0.8rem; margin-bottom: 4px; opacity: 0.8;">
                            <?= $isMe ? 'You' : htmlspecialchars($otherUser['name']) ?>
                            <span style="font-weight: normal; margin-left: 5px; opacity: 0.7;"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                        </div>
                        <div style="word-wrap: break-word; line-height: 1.4;">
                            <?= nl2br(htmlspecialchars($msg['body'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Reply Form -->
        <div style="padding: 20px; background: #fff;">
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages/store" method="POST">
                <?= Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="receiver_id" value="<?= $otherUser['id'] ?>">

                <div style="display: flex; gap: 10px;">
                    <textarea name="body" rows="2" placeholder="Write a message..." required class="civic-input" style="flex: 1; resize: none; border: 2px solid #ddd;"></textarea>
                    <button type="submit" class="civic-btn" style="padding: 0 30px;">Send</button>
                </div>
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

<style>
    /* Mobile chat fixes */
    @media (max-width: 600px) {
        .civic-chat-container {
            height: calc(100vh - 200px) !important;
            max-height: calc(100vh - 200px) !important;
            margin-bottom: calc(70px + env(safe-area-inset-bottom, 0px));
        }
        .civic-chat-container textarea {
            min-height: 44px;
        }
    }
</style>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>