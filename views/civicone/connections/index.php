<?php
// Modern Connections Page
// Path: views/modern/connections/index.php

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

<style>
/* Connections Page Styles */
.connections-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 140px 20px 40px;
}

.connections-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
    backdrop-filter: blur(20px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    margin-bottom: 24px;
}

[data-theme="dark"] .connections-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.9));
    border-color: rgba(255, 255, 255, 0.1);
}

.connections-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

[data-theme="dark"] .connections-header {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.connections-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .connections-header h2 {
    color: #f1f5f9;
}

.connections-count {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.connections-body {
    padding: 16px;
}

.connection-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border-radius: 16px;
    text-decoration: none;
    transition: all 0.2s ease;
    margin-bottom: 8px;
}

.connection-item:hover {
    background: rgba(99, 102, 241, 0.08);
    transform: translateX(4px);
}

[data-theme="dark"] .connection-item:hover {
    background: rgba(99, 102, 241, 0.15);
}

.connection-avatar {
    position: relative;
    flex-shrink: 0;
}

.connection-avatar img {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e5e7eb;
    transition: border-color 0.2s;
}

.connection-item:hover .connection-avatar img {
    border-color: #6366f1;
}

[data-theme="dark"] .connection-avatar img {
    border-color: #475569;
}

.connection-online {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 3px solid white;
}

[data-theme="dark"] .connection-online {
    border-color: #1e293b;
}

.connection-info {
    flex: 1;
    min-width: 0;
}

.connection-name {
    display: block;
    font-weight: 600;
    font-size: 1rem;
    color: #1f2937;
    margin-bottom: 4px;
}

[data-theme="dark"] .connection-name {
    color: #f1f5f9;
}

.connection-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.875rem;
    color: #6b7280;
}

[data-theme="dark"] .connection-meta {
    color: #94a3b8;
}

.connection-meta i {
    width: 14px;
    text-align: center;
}

.connection-actions {
    display: flex;
    gap: 8px;
}

.btn-message {
    padding: 8px 16px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-message:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
}

.btn-accept {
    padding: 8px 16px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-accept:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.pending-badge {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 48px 24px;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state-icon i {
    font-size: 2rem;
    color: #6366f1;
}

.empty-state h3 {
    margin: 0 0 8px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #374151;
}

[data-theme="dark"] .empty-state h3 {
    color: #f1f5f9;
}

.empty-state p {
    margin: 0 0 20px;
    color: #6b7280;
}

[data-theme="dark"] .empty-state p {
    color: #94a3b8;
}

.btn-find-friends {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-find-friends:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

@media (max-width: 640px) {
    .connections-container {
        padding: 120px 16px 100px;
    }

    .connection-item {
        flex-wrap: wrap;
    }

    .connection-actions {
        width: 100%;
        margin-top: 12px;
        justify-content: flex-end;
    }

    .connection-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
}
</style>

<div class="connections-container">

    <!-- Pending Requests -->
    <?php if (!empty($pending)): ?>
    <div class="connections-card">
        <div class="connections-header">
            <h2>
                <i class="fa-solid fa-clock" style="color: #f59e0b;"></i>
                Pending Requests
            </h2>
            <span class="pending-badge"><?= count($pending) ?> pending</span>
        </div>
        <div class="connections-body">
            <?php foreach ($pending as $req): ?>
                <div class="connection-item" style="cursor: default;">
                    <a href="<?= $basePath ?>/profile/<?= $req['requester_id'] ?? $req['id'] ?>" class="connection-avatar">
                        <img src="<?= htmlspecialchars($req['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>" loading="lazy"
                             alt="<?= htmlspecialchars($req['requester_name']) ?>">
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
                        <form action="<?= $basePath ?>/connections/accept" method="POST" style="margin: 0;">
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
                <i class="fa-solid fa-user-group" style="color: #6366f1;"></i>
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
                            <img src="<?= htmlspecialchars($friend['avatar_url'] ?: '/assets/img/defaults/default_avatar.webp') ?>" loading="lazy"
                                 alt="<?= htmlspecialchars($friend['name']) ?>">
                            <?php if ($friendIsOnline): ?>
                                <span class="connection-online" style="background: #10b981;" title="Online now"></span>
                            <?php elseif ($friendIsRecent): ?>
                                <span class="connection-online" style="background: #f59e0b;" title="Active today"></span>
                            <?php endif; ?>
                        </div>
                        <div class="connection-info">
                            <span class="connection-name"><?= htmlspecialchars($friend['name']) ?></span>
                            <div class="connection-meta">
                                <?php if (!empty($friend['location'])): ?>
                                    <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($friend['location']) ?></span>
                                <?php endif; ?>
                                <?php if ($friendIsOnline): ?>
                                    <span style="color: #10b981;"><i class="fa-solid fa-circle" style="font-size: 8px;"></i> Online</span>
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

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
