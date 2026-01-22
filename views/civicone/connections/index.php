<?php
// CivicOne Connections Page - WCAG 2.1 AA Compliant
// GOV.UK Design System Compliant - text-only navigation

if (session_status() === PHP_SESSION_NONE) session_start();

$currentUserId = $_SESSION['user_id'] ?? 0;
$isLoggedIn = !empty($currentUserId);

if (!$isLoggedIn) {
    header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
    exit;
}

$basePath = \Nexus\Core\TenantContext::getBasePath();

// Set page info for header
$pageTitle = 'My Friends';
$hero_title = 'My Friends';
$hero_subtitle = 'Manage your connections';

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="connections-container">

    <!-- Pending Requests -->
    <?php if (!empty($pending)): ?>
    <section class="connections-card" aria-labelledby="pending-heading">
        <header class="connections-header">
            <h2 id="pending-heading">Pending Requests</h2>
            <span class="pending-badge"><?= count($pending) ?> pending</span>
        </header>
        <div class="connections-body" role="list" aria-label="Pending connection requests">
            <?php foreach ($pending as $req): ?>
                <div class="connection-item" role="listitem">
                    <a href="<?= $basePath ?>/profile/<?= $req['requester_id'] ?? $req['id'] ?>" class="connection-avatar">
                        <img src="<?= htmlspecialchars($req['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>" loading="lazy"
                             alt="">
                    </a>
                    <div class="connection-info">
                        <a href="<?= $basePath ?>/profile/<?= $req['requester_id'] ?? $req['id'] ?>" class="connection-name">
                            <?= htmlspecialchars($req['requester_name']) ?>
                        </a>
                        <div class="connection-meta">
                            <span>Wants to connect</span>
                        </div>
                    </div>
                    <div class="connection-actions">
                        <form action="<?= $basePath ?>/connections/accept" method="POST">
                            <input type="hidden" name="connection_id" value="<?= $req['id'] ?>">
                            <button type="submit" class="btn-accept">Accept</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Friends List -->
    <section class="connections-card" aria-labelledby="friends-heading">
        <header class="connections-header">
            <h2 id="friends-heading">My Friends</h2>
            <span class="connections-count"><?= count($friends) ?></span>
        </header>
        <div class="connections-body">
            <?php if (empty($friends)): ?>
                <div class="empty-state" role="status">
                    <h3>No Friends Yet</h3>
                    <p>Connect with other members to grow your network</p>
                    <a href="<?= $basePath ?>/members" class="btn-find-friends">Find Members</a>
                </div>
            <?php else: ?>
                <div role="list" aria-label="Friends list">
                    <?php foreach ($friends as $friend):
                        $friendIsOnline = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-5 minutes');
                        $friendIsRecent = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-24 hours');
                    ?>
                        <a href="<?= $basePath ?>/profile/<?= $friend['id'] ?>" class="connection-item" role="listitem">
                            <div class="connection-avatar">
                                <img src="<?= htmlspecialchars($friend['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>" loading="lazy"
                                     alt="">
                                <?php if ($friendIsOnline): ?>
                                    <span class="connection-online connection-online--active" aria-label="Online now"></span>
                                <?php elseif ($friendIsRecent): ?>
                                    <span class="connection-online connection-online--recent" aria-label="Active today"></span>
                                <?php endif; ?>
                            </div>
                            <div class="connection-info">
                                <span class="connection-name"><?= htmlspecialchars($friend['name']) ?></span>
                                <div class="connection-meta">
                                    <?php if (!empty($friend['location'])): ?>
                                        <span><?= htmlspecialchars($friend['location']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($friendIsOnline): ?>
                                        <span class="connection-status--online">Online</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="connection-actions" onclick="event.preventDefault(); event.stopPropagation();">
                                <a href="<?= $basePath ?>/messages/thread/<?= $friend['id'] ?>" class="btn-message">Message</a>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
