<?php
/**
 * My Connections Page
 * Styles in: /httpdocs/assets/css/social-interactions.css
 */
$pageTitle = 'My Connections';
?>

<div class="grid">
    <!-- Pending Requests -->
    <div class="connections-section">
        <?php if (!empty($pending)): ?>
            <article class="glass-panel connections-pending-panel">
                <header>Pending Requests</header>
                <div class="grid">
                    <?php foreach ($pending as $req): ?>
                        <div class="connections-request-item">
                            <div class="connections-request-info">
                                <img src="<?= $req['avatar_url'] ?: 'https://via.placeholder.com/40' ?>" alt="" class="connections-request-avatar">
                                <strong><?= htmlspecialchars($req['requester_name']) ?></strong>
                            </div>
                            <form action="/connections/accept" method="POST" class="connections-accept-form">
                                <input type="hidden" name="connection_id" value="<?= $req['id'] ?>">
                                <button type="submit" class="connections-accept-btn">Accept</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <!-- Friends List -->
    <div class="connections-section">
        <h3>My Friends (<?= count($friends) ?>)</h3>
        <?php if (empty($friends)): ?>
            <p>You haven't connected with anyone yet. <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/members">Find members</a>.</p>
        <?php else: ?>
            <div class="connections-friends-grid">
                <?php foreach ($friends as $friend): ?>
                    <article class="glass-panel connections-friend-card">
                        <img src="<?= $friend['avatar_url'] ?: 'https://via.placeholder.com/80' ?>" alt="" class="connections-friend-avatar">
                        <h5><a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $friend['id'] ?>"><?= htmlspecialchars($friend['name']) ?></a></h5>
                        <small><?= htmlspecialchars($friend['location']) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
