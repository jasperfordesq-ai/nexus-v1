<?php
// CivicOne View: Messages Inbox
$pageTitle = 'My Messages';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div style="margin-bottom: 30px; border-bottom: 4px solid var(--skin-primary, #00796B); padding-bottom: 10px;">
        <h1 style="margin: 0; text-transform: uppercase;">Inbox</h1>
    </div>

    <div class="civic-card">
        <?php if (empty($threads)): ?>
            <div style="text-align: center; padding: 40px; color: var(--civic-text-secondary, #4B5563);">
                <p style="font-size: 1.2rem;">You have no messages yet.</p>
                <div style="margin-top: 20px;">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings" class="civic-btn">Browse Listings</a>
                </div>
            </div>
        <?php else: ?>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($threads as $thread):
                    // Check if this message is unread (receiver is current user and not read)
                    $isUnread = ($thread['receiver_id'] == ($_SESSION['user_id'] ?? 0) && !$thread['is_read']);
                    $bgColor = $isUnread ? '#f0fdf4' : 'transparent';
                    $fontWeight = $isUnread ? 'bold' : 'normal';
                ?>
                    <li style="border-bottom: 1px solid #eee; background-color: <?= $bgColor ?>;">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages/<?= $thread['other_user_id'] ?>" style="display: block; padding: 20px; text-decoration: none; color: inherit; transition: background 0.2s;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 1.1rem; color: var(--skin-primary); font-weight: <?= $fontWeight ?>;">
                                        <?= htmlspecialchars($thread['other_user_name']) ?>
                                    </div>
                                    <div style="color: var(--civic-text-secondary, #4B5563); margin-top: 5px; font-weight: <?= $fontWeight ?>;">
                                        <?= htmlspecialchars(mb_strimwidth($thread['body'], 0, 60, "...")) ?>
                                    </div>
                                </div>
                                <div style="text-align: right; min-width: 100px;">
                                    <?php if ($isUnread): ?>
                                        <span style="background: var(--skin-primary); color: white; border-radius: 50%; padding: 4px 8px; font-size: 0.8rem; margin-right: 10px;">
                                            New
                                        </span>
                                    <?php endif; ?>
                                    <span style="font-size: 0.9rem; color: #999;">
                                        <?= date('M j', strtotime($thread['created_at'])) ?>
                                    </span>
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