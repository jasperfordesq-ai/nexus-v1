<?php
$pageTitle = 'My Connections';

?>

<div class="grid">
    <!-- Pending Requests -->
    <div style="grid-column: span 3;">
        <?php if (!empty($pending)): ?>
            <article class="glass-panel" style="border-left: 5px solid var(--primary);">
                <header>Pending Requests</header>
                <div class="grid">
                    <?php foreach ($pending as $req): ?>
                        <div style="padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?= $req['avatar_url'] ?: 'https://via.placeholder.com/40' ?>" style="width: 40px; height: 40px; border-radius: 50%;">
                                <strong><?= htmlspecialchars($req['requester_name']) ?></strong>
                            </div>
                            <form action="/connections/accept" method="POST" style="margin:0;">
                                <input type="hidden" name="connection_id" value="<?= $req['id'] ?>">
                                <button type="submit" style="padding: 5px 15px; font-size: 0.9em;">Accept</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <!-- Friends List -->
    <div style="grid-column: span 3;">
        <h3>My Friends (<?= count($friends) ?>)</h3>
        <?php if (empty($friends)): ?>
            <p>You haven't connected with anyone yet. <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/members">Find members</a>.</p>
        <?php else: ?>
            <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                <?php foreach ($friends as $friend): ?>
                    <article class="glass-panel" style="text-align: center; padding: 1rem;">
                        <img src="<?= $friend['avatar_url'] ?: 'https://via.placeholder.com/80' ?>" style="width: 80px; height: 80px; border-radius: 50%; margin-bottom: 0.5rem;">
                        <h5><a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $friend['id'] ?>"><?= htmlspecialchars($friend['name']) ?></a></h5>
                        <small><?= htmlspecialchars($friend['location']) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php  ?>