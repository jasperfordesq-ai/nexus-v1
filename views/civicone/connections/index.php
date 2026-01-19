<?php
// CivicOne Connections Page - WCAG 2.1 AA Compliant
// CSS extracted to civicone-matches.css

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
            <h2 id="pending-heading">
                <i class="fa-solid fa-clock" style="color: #f59e0b;" aria-hidden="true"></i>
                Pending Requests
            </h2>
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
                            <span><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Wants to connect</span>
                        </div>
                    </div>
                    <div class="connection-actions">
                        <form action="<?= $basePath ?>/connections/accept" method="POST">
                            <input type="hidden" name="connection_id" value="<?= $req['id'] ?>">
                            <button type="submit" class="btn-accept">
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                Accept
                            </button>
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
            <h2 id="friends-heading">
                <i class="fa-solid fa-user-group" style="color: #6366f1;" aria-hidden="true"></i>
                My Friends
            </h2>
            <span class="connections-count"><?= count($friends) ?></span>
        </header>
        <div class="connections-body">
            <?php if (empty($friends)): ?>
                <div class="empty-state" role="status">
                    <div class="empty-state-icon" aria-hidden="true">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <h3>No Friends Yet</h3>
                    <p>Connect with other members to grow your network</p>
                    <a href="<?= $basePath ?>/members" class="btn-find-friends">
                        <i class="fa-solid fa-search" aria-hidden="true"></i>
                        Find Members
                    </a>
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
                                    <span class="connection-online" style="background: #10b981;" aria-label="Online now"></span>
                                <?php elseif ($friendIsRecent): ?>
                                    <span class="connection-online" style="background: #f59e0b;" aria-label="Active today"></span>
                                <?php endif; ?>
                            </div>
                            <div class="connection-info">
                                <span class="connection-name"><?= htmlspecialchars($friend['name']) ?></span>
                                <div class="connection-meta">
                                    <?php if (!empty($friend['location'])): ?>
                                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars($friend['location']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($friendIsOnline): ?>
                                        <span style="color: #10b981;"><i class="fa-solid fa-circle" style="font-size: 8px;" aria-hidden="true"></i> Online</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="connection-actions" onclick="event.preventDefault(); event.stopPropagation();">
                                <a href="<?= $basePath ?>/messages/thread/<?= $friend['id'] ?>" class="btn-message">
                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                    Message
                                </a>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
