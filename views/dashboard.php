<?php
$pageTitle = 'Dashboard';
$pageSubtitle = "Welcome back, $user[name]";
?>
<!-- 1. PENDING ACTIONS (Governance) -->
<?php if (!empty($pending_proposals)): ?>
    <div class="htb-card" style="border-left: 5px solid #fbbf24;">
        <div class="htb-card-body">
            <h3 style="margin-top:0; color: #d97706;">🗳️ Your Voice is Needed</h3>
            <p>You have <strong><?= count($pending_proposals) ?></strong> pending vote(s) in your hubs.</p>

            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                <?php foreach ($pending_proposals as $pp): ?>
                    <div style="background: rgba(0,0,0,0.03); padding: 10px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: bold;"><?= htmlspecialchars($pp['title']) ?></div>
                            <div style="font-size: 0.8rem; color: #666;"><?= htmlspecialchars($pp['group_name']) ?></div>
                        </div>
                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/proposals/<?= $pp['id'] ?>" class="htb-btn htb-btn-sm" style="background: #fbbf24; color: #78350f;">Vote</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// --- VIEW SWITCHER ---
if (is_modern()) {
    require __DIR__ . '/modern/dashboard.php';
    return;
}

require __DIR__ . '/layouts/header.php';
?>


<div class="htb-subnav" style="margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
    <a href="?tab=overview" style="margin-right: 20px; font-weight: bold; text-decoration: none; color: <?= ($activeTab ?? 'overview') === 'overview' ? '#333' : '#888' ?>;">Overview</a>
    <a href="?tab=notifications" style="margin-right: 20px; font-weight: bold; text-decoration: none; color: <?= ($activeTab ?? 'overview') === 'notifications' ? '#333' : '#888' ?>;">
        Notifications
        <?php
        $uCount = \Nexus\Models\Notification::countUnread($_SESSION['user_id']);
        if ($uCount > 0) echo "<span style='background:red; color:white; padding:2px 6px; border-radius:10px; font-size:10px;'>$uCount</span>";
        ?>
    </a>
</div>

<?php if (($activeTab ?? 'overview') === 'overview'): ?>

    <div class="grid">
        <article class="glass-panel">
            <header>My Balance</header>
            <h1 style="font-size: 3em; color: var(--primary);"><?= $user['balance'] ?> Hours</h1>
        </article>

        <article class="glass-panel">
            <header>Quick Actions</header>
            <div class="grid">
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/create" role="button">Post Ad</a>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/wallet" role="button" class="contrast">Send Hours</a>
            </div>
        </article>
    </div>

    <?php if (!empty($suggested_matches)): ?>
        <section style="margin-top: 40px; margin-bottom: 40px;">
            <h3 style="margin-bottom: 20px;">⚡ Smart Matches For You</h3>
            <div class="grid">
                <?php foreach ($suggested_matches as $match): ?>
                    <article class="glass-panel" style="border-left: 5px solid <?= $match['category_color'] ?: '#3b82f6' ?>;">
                        <header style="display:flex; justify-content:space-between; align-items:center;">
                            <small style="color: <?= $match['category_color'] ?: '#3b82f6' ?>; font-weight:bold;"><?= strtoupper($match['type']) ?></small>
                            <span style="font-size:0.8rem; background:#eff6ff; padding:2px 8px; border-radius:99px;"><?= htmlspecialchars($match['category_name']) ?></span>
                        </header>
                        <h4 style="margin: 10px 0;"><?= htmlspecialchars($match['title']) ?></h4>
                        <p style="font-size:0.9rem; color:#666;">
                            Because you posted Open Requests in this category.
                        </p>
                        <footer style="margin-top:auto;">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/<?= $match['id'] ?>" role="button" class="outline">View Match</a>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <h3>My Listings</h3>
    <div class="grid">
        <?php if (empty($my_listings)): ?>
            <p>No listings yet.</p>
        <?php else: ?>
            <?php foreach ($my_listings as $listing): ?>
                <article class="glass-panel">
                    <header>
                        <small><?= strtoupper($listing['type']) ?></small>
                        <strong><?= htmlspecialchars($listing['title']) ?></strong>
                    </header>
                    <p><?= htmlspecialchars($listing['description']) ?></p>
                    <footer><a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings">View All</a></footer>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr>

    <h3>Community Activity</h3>
    <figure class="glass-panel">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activity_feed)): ?>
                    <tr>
                        <td colspan="4">No activity yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activity_feed as $log): ?>
                        <tr>
                            <td><a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $log['user_id'] ?>"><?= htmlspecialchars($log['user_name']) ?></a></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td><?= htmlspecialchars($log['details']) ?></td>
                            <td><?= date('M d, H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </figure>

<?php elseif (($activeTab ?? 'overview') === 'notifications'): ?>

    <h3>My Notifications</h3>

    <div class="glass-panel">
        <?php if (empty($notifications)): ?>
            <p style="padding: 20px; text-align: center; color: #888;">No notifications.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column;">
                <?php foreach ($notifications as $n): ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee; background: <?= $n['is_read'] ? 'transparent' : '#f0f9ff' ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <div style="font-weight: <?= $n['is_read'] ? 'normal' : 'bold' ?>; color: #333;">
                                    <?= htmlspecialchars($n['message']) ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #888; margin-top: 4px;">
                                    <?= date('M j, g:i a', strtotime($n['created_at'])) ?>
                                </div>
                            </div>
                            <?php if (!empty($n['link'])): ?>
                                <a href="<?= $n['link'] ?>" class="contrast" style="font-size: 0.9rem;">View</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/layouts/footer.php'; ?>