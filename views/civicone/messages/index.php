<?php
// CivicOne View: Messages Inbox - WCAG 2.1 AA Compliant
// CSS extracted to civicone-messages.css
$pageTitle = 'My Messages';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div class="civic-msg-header">
        <h1>Inbox</h1>
    </div>

    <div class="civic-card">
        <?php if (empty($threads)): ?>
            <div class="civic-msg-empty">
                <p>You have no messages yet.</p>
                <div>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings" class="civic-btn">Browse Listings</a>
                </div>
            </div>
        <?php else: ?>
            <ul class="civic-thread-list" role="list">
                <?php foreach ($threads as $thread):
                    // Check if this message is unread (receiver is current user and not read)
                    $isUnread = ($thread['receiver_id'] == ($_SESSION['user_id'] ?? 0) && !$thread['is_read']);
                    $itemClass = $isUnread ? 'civic-thread-item civic-thread-item--unread' : 'civic-thread-item';
                    $nameClass = $isUnread ? 'civic-thread-name civic-thread-name--unread' : 'civic-thread-name';
                    $previewClass = $isUnread ? 'civic-thread-preview civic-thread-preview--unread' : 'civic-thread-preview';
                ?>
                    <li class="<?= $itemClass ?>" role="listitem">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages/<?= $thread['other_user_id'] ?>"
                           class="civic-thread-link"
                           aria-label="Message thread with <?= htmlspecialchars($thread['other_user_name']) ?><?= $isUnread ? ' (unread)' : '' ?>">
                            <div class="civic-thread-content">
                                <div class="civic-thread-info">
                                    <div class="<?= $nameClass ?>">
                                        <?= htmlspecialchars($thread['other_user_name']) ?>
                                    </div>
                                    <div class="<?= $previewClass ?>">
                                        <?= htmlspecialchars(mb_strimwidth($thread['body'], 0, 60, "...")) ?>
                                    </div>
                                </div>
                                <div class="civic-thread-meta">
                                    <?php if ($isUnread): ?>
                                        <span class="civic-thread-badge" aria-label="New message">New</span>
                                    <?php endif; ?>
                                    <time class="civic-thread-date" datetime="<?= $thread['created_at'] ?>">
                                        <?= date('M j', strtotime($thread['created_at'])) ?>
                                    </time>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>