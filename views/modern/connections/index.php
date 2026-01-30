<?php
// Modern Connections Page
// Path: views/modern/connections/index.php
// CSS: /httpdocs/assets/css/connection-status.css

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

require __DIR__ . '/../../layouts/modern/header.php';
?>


<div class="connections-container">

    <!-- Pending Requests -->
    <?php if (!empty($pending)): ?>
    <div class="connections-card">
        <div class="connections-header">
            <h2>
                <i class="fa-solid fa-clock icon-pending"></i>
                Pending Requests
            </h2>
            <span class="pending-badge"><?= count($pending) ?> pending</span>
        </div>
        <div class="connections-body">
            <?php foreach ($pending as $req): ?>
                <div class="connection-item connection-item--pending">
                    <a href="<?= $basePath ?>/profile/<?= $req['requester_id'] ?? $req['id'] ?>" class="connection-avatar">
                        <?= webp_avatar($req['avatar_url'] ?? null, $req['requester_name'], 56) ?>
                    </a>
                    <div class="connection-info">
                        <a href="<?= $basePath ?>/profile/<?= $req['requester_id'] ?? $req['id'] ?>" class="connection-name">
                            <?= htmlspecialchars($req['requester_name']) ?>
                        </a>
                        <div class="connection-meta">
                            <span><i class="fa-solid fa-user-plus"></i> Wants to connect</span>
                        </div>
                    </div>
                    <div class="connection-actions">
                        <form action="<?= $basePath ?>/connections/accept" method="POST">
                            <input type="hidden" name="connection_id" value="<?= $req['id'] ?>">
                            <button type="submit" class="btn-accept">
                                <i class="fa-solid fa-check"></i> Accept
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Friends List -->
    <div class="connections-card">
        <div class="connections-header">
            <h2>
                <i class="fa-solid fa-user-group icon-primary"></i>
                My Friends
            </h2>
            <span class="connections-count"><?= count($friends) ?></span>
        </div>
        <div class="connections-body">
            <?php if (empty($friends)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <h3>No Friends Yet</h3>
                    <p>Connect with other members to grow your network</p>
                    <a href="<?= $basePath ?>/members" class="btn-find-friends">
                        <i class="fa-solid fa-search"></i>
                        Find Members
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($friends as $friend):
                    $friendIsOnline = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-5 minutes');
                    $friendIsRecent = !empty($friend['last_active_at']) && strtotime($friend['last_active_at']) > strtotime('-24 hours');
                ?>
                    <a href="<?= $basePath ?>/profile/<?= $friend['id'] ?>" class="connection-item">
                        <div class="connection-avatar">
                            <?= webp_avatar($friend['avatar_url'] ?? null, $friend['name'], 56) ?>
                            <?php if ($friendIsOnline): ?>
                                <span class="connection-online connection-online--active" title="Online now"></span>
                            <?php elseif ($friendIsRecent): ?>
                                <span class="connection-online connection-online--recent" title="Active today"></span>
                            <?php endif; ?>
                        </div>
                        <div class="connection-info">
                            <span class="connection-name"><?= htmlspecialchars($friend['name']) ?></span>
                            <div class="connection-meta">
                                <?php if (!empty($friend['location'])): ?>
                                    <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($friend['location']) ?></span>
                                <?php endif; ?>
                                <?php if ($friendIsOnline): ?>
                                    <span class="status-online"><i class="fa-solid fa-circle"></i> Online</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="connection-actions" onclick="event.preventDefault(); event.stopPropagation();">
                            <a href="<?= $basePath ?>/messages/thread/<?= $friend['id'] ?>" class="btn-message">
                                <i class="fa-solid fa-paper-plane"></i>
                                Message
                            </a>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
