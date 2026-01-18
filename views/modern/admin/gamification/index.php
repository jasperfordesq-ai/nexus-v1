<?php
/**
 * Admin Gamification - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Gamification';
$adminPageSubtitle = 'Achievements';
$adminPageIcon = 'fa-gamepad';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-gamepad"></i>
            Gamification Admin
        </h1>
        <p class="admin-page-subtitle">Manage badges, XP, and achievements for your community</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/gamification/campaigns" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-bullhorn"></i> Campaigns
        </a>
        <a href="<?= $basePath ?>/admin/gamification/analytics" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-chart-line"></i> Analytics
        </a>
    </div>
</div>

<!-- Alert Messages -->
<?php if (isset($_GET['rechecked'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div>Rechecked <?= (int)$_GET['rechecked'] ?> users. <?= (int)($_GET['awarded'] ?? 0) ?> new badges awarded.</div>
</div>
<?php endif; ?>

<?php if (isset($_GET['bulk_awarded'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div>Successfully awarded badge to <?= (int)$_GET['bulk_awarded'] ?> users.</div>
</div>
<?php endif; ?>

<?php if (isset($_GET['all_awarded'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div>Badge awarded to <?= (int)$_GET['all_awarded'] ?> users.</div>
</div>
<?php endif; ?>

<?php if (isset($_GET['xp_reset'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div>User XP has been reset.</div>
</div>
<?php endif; ?>

<?php if (isset($_GET['badges_cleared'])): ?>
<div class="admin-alert admin-alert-success">
    <i class="fa-solid fa-check-circle"></i>
    <div>User badges have been cleared.</div>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="admin-alert admin-alert-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <div>Error: <?= htmlspecialchars($_GET['error']) ?></div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="gam-stats-grid">
    <div class="admin-glass-card gam-stat-card">
        <div class="gam-stat-icon blue">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="gam-stat-value"><?= number_format($totalUsers ?? 0) ?></div>
        <div class="gam-stat-label">Total Users</div>
    </div>
    <div class="admin-glass-card gam-stat-card">
        <div class="gam-stat-icon purple">
            <i class="fa-solid fa-medal"></i>
        </div>
        <div class="gam-stat-value"><?= number_format($totalBadgesAwarded ?? 0) ?></div>
        <div class="gam-stat-label">Total Badges Awarded</div>
    </div>
    <div class="admin-glass-card gam-stat-card">
        <div class="gam-stat-icon green">
            <i class="fa-solid fa-trophy"></i>
        </div>
        <div class="gam-stat-value"><?= number_format($usersWithBadges ?? 0) ?></div>
        <div class="gam-stat-label">Users with Badges</div>
    </div>
    <div class="admin-glass-card gam-stat-card">
        <div class="gam-stat-icon orange">
            <i class="fa-solid fa-certificate"></i>
        </div>
        <div class="gam-stat-value"><?= count($allBadges ?? []) ?></div>
        <div class="gam-stat-label">Badge Types Available</div>
    </div>
</div>

<!-- Main Grid -->
<div class="gam-main-grid">
    <!-- Left Column -->
    <div>
        <!-- Admin Actions -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #a855f7, #7c3aed);">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Admin Actions</h3>
                    <p class="admin-card-subtitle">Bulk operations and management</p>
                </div>
            </div>
            <div class="admin-card-body">
                <!-- Recheck All Badges -->
                <div class="gam-action-section">
                    <h4 class="gam-action-title"><i class="fa-solid fa-rotate"></i> Recheck All Badges</h4>
                    <p class="gam-action-desc">Scan all users' activity and automatically award any badges they qualify for but haven't received yet.</p>
                    <form action="<?= $basePath ?>/admin/gamification/recheck-all" method="POST">
                        <?= Csrf::input() ?>
                        <button type="submit" class="admin-btn admin-btn-primary" onclick="return confirm('This will scan all users. Continue?')">
                            <i class="fa-solid fa-rotate"></i> Recheck All Users
                        </button>
                    </form>
                </div>

                <!-- Bulk Award Badge -->
                <div class="gam-action-section">
                    <h4 class="gam-action-title"><i class="fa-solid fa-gift"></i> Award Badge to All Users</h4>
                    <p class="gam-action-desc">Award a specific badge to all users at once. Great for commemorative badges.</p>
                    <form action="<?= $basePath ?>/admin/gamification/award-all" method="POST">
                        <?= Csrf::input() ?>
                        <select name="badge_key" required class="admin-select">
                            <option value="">-- Select Badge --</option>
                            <?php foreach ($allBadges ?? [] as $badge): ?>
                                <option value="<?= htmlspecialchars($badge['key']) ?>">
                                    <?= $badge['icon'] ?> <?= htmlspecialchars($badge['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="admin-btn admin-btn-warning" onclick="return confirm('Award this badge to ALL users?')">
                            <i class="fa-solid fa-gift"></i> Award to All
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Badge Distribution -->
        <div class="admin-glass-card" style="margin-top: 1.5rem;">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ec4899, #db2777);">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Badge Distribution</h3>
                    <p class="admin-card-subtitle">How badges are distributed</p>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($badgeStats)): ?>
                <div class="admin-empty-state" style="padding: 2rem;">
                    <p class="admin-empty-text">No badges have been awarded yet.</p>
                </div>
                <?php else: ?>
                <div class="badge-dist-list">
                    <?php foreach ($badgeStats as $stat): ?>
                    <div class="badge-dist-item">
                        <span class="badge-icon"><?= $stat['icon'] ?? '🏅' ?></span>
                        <div class="badge-info">
                            <div class="badge-name"><?= htmlspecialchars($stat['name']) ?></div>
                            <div class="badge-key"><?= htmlspecialchars($stat['badge_key']) ?></div>
                        </div>
                        <span class="badge-count"><?= number_format($stat['count']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <!-- Recent Awards -->
        <div class="admin-glass-card">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #fbbf24, #d97706);">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Recent Awards</h3>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($recentAwards)): ?>
                <div class="admin-empty-state" style="padding: 2rem;">
                    <p class="admin-empty-text">No recent awards.</p>
                </div>
                <?php else: ?>
                <div class="recent-awards-list">
                    <?php foreach ($recentAwards as $award): ?>
                    <div class="recent-award">
                        <div class="award-avatar">
                            <?php if (!empty($award['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($award['avatar_url']) ?>" loading="lazy" alt="">
                            <?php else: ?>
                                <?= strtoupper(substr($award['first_name'] ?? 'U', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="award-info">
                            <div class="award-user"><?= htmlspecialchars(($award['first_name'] ?? '') . ' ' . ($award['last_name'] ?? '')) ?></div>
                            <div class="award-badge"><?= $award['icon'] ?? '🏅' ?> <?= htmlspecialchars($award['name']) ?></div>
                        </div>
                        <div class="award-time"><?= date('M j', strtotime($award['awarded_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- XP Leaderboard -->
        <div class="admin-glass-card" style="margin-top: 1.5rem;">
            <div class="admin-card-header">
                <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fa-solid fa-star"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">XP Leaders</h3>
                </div>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($xpLeaders)): ?>
                <div class="admin-empty-state" style="padding: 2rem;">
                    <p class="admin-empty-text">No XP data yet.</p>
                </div>
                <?php else: ?>
                <div class="xp-leaders-list">
                    <?php foreach ($xpLeaders as $i => $leader): ?>
                    <div class="xp-leader">
                        <div class="xp-rank <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) ?>">
                            <?= $i + 1 ?>
                        </div>
                        <div class="xp-avatar">
                            <?php if (!empty($leader['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($leader['avatar_url']) ?>" loading="lazy" alt="">
                            <?php else: ?>
                                <?= strtoupper(substr($leader['first_name'] ?? 'U', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="xp-info">
                            <div class="xp-name"><?= htmlspecialchars(($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? '')) ?></div>
                            <div class="xp-level">Level <?= (int)($leader['level'] ?? 1) ?></div>
                        </div>
                        <div class="xp-value"><?= number_format($leader['xp'] ?? 0) ?> XP</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="admin-glass-card" style="margin-top: 1.5rem;">
            <div class="admin-card-header">
                <div class="admin-card-header-icon admin-card-header-icon-cyan">
                    <i class="fa-solid fa-link"></i>
                </div>
                <div class="admin-card-header-content">
                    <h3 class="admin-card-title">Quick Links</h3>
                </div>
            </div>
            <div class="admin-card-body">
                <div class="quick-links">
                    <a href="<?= $basePath ?>/admin/cron-jobs" class="quick-link">
                        <i class="fa-solid fa-clock"></i> Cron Jobs
                    </a>
                    <a href="<?= $basePath ?>/admin/custom-badges" class="quick-link">
                        <i class="fa-solid fa-certificate"></i> Custom Badges
                    </a>
                    <a href="<?= $basePath ?>/leaderboard" class="quick-link" target="_blank">
                        <i class="fa-solid fa-trophy"></i> View Leaderboards
                    </a>
                    <a href="<?= $basePath ?>/achievements" class="quick-link" target="_blank">
                        <i class="fa-solid fa-medal"></i> Achievements Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Gamification Admin Specific Styles */

/* Alerts */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.admin-alert i {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-alert-success {
    border-left: 3px solid #22c55e;
}
.admin-alert-success i { color: #22c55e; }

.admin-alert-error {
    border-left: 3px solid #ef4444;
}
.admin-alert-error i { color: #ef4444; }

/* Stats Grid */
.gam-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.gam-stat-card {
    padding: 1.5rem;
}

.gam-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 1rem;
}

.gam-stat-icon.blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.gam-stat-icon.purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
.gam-stat-icon.green { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
.gam-stat-icon.orange { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }

.gam-stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
}

.gam-stat-label {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 4px;
}

/* Main Grid */
.gam-main-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

/* Action Sections */
.gam-action-section {
    padding: 1.25rem;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.2);
    border-radius: 12px;
    margin-bottom: 1rem;
}

.gam-action-section:last-child {
    margin-bottom: 0;
}

.gam-action-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #c4b5fd;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.gam-action-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0 0 1rem 0;
}

/* Select */
.admin-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: #fff;
    font-size: 0.95rem;
    margin-bottom: 1rem;
    cursor: pointer;
}

.admin-select option {
    background: #1e293b;
    color: #f1f5f9;
}

/* Badge Distribution */
.badge-dist-list {
    max-height: 350px;
    overflow-y: auto;
}

.badge-dist-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.badge-dist-item:last-child {
    border-bottom: none;
}

.badge-icon {
    font-size: 1.5rem;
}

.badge-info {
    flex: 1;
}

.badge-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.badge-key {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    font-family: monospace;
}

.badge-count {
    background: rgba(139, 92, 246, 0.2);
    color: #c4b5fd;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

/* Recent Awards */
.recent-awards-list {
    max-height: 300px;
    overflow-y: auto;
}

.recent-award {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.recent-award:last-child {
    border-bottom: none;
}

.award-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
    overflow: hidden;
}

.award-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.award-info {
    flex: 1;
}

.award-user {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.award-badge {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

.award-time {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.4);
}

/* XP Leaders */
.xp-leaders-list {
    max-height: 300px;
    overflow-y: auto;
}

.xp-leader {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

.xp-leader:last-child {
    border-bottom: none;
}

.xp-rank {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    flex-shrink: 0;
}

.xp-rank.gold { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
.xp-rank.silver { background: rgba(156, 163, 175, 0.2); color: #d1d5db; }
.xp-rank.bronze { background: rgba(180, 83, 9, 0.2); color: #d97706; }

.xp-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
    flex-shrink: 0;
    overflow: hidden;
}

.xp-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.xp-info {
    flex: 1;
}

.xp-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.9rem;
}

.xp-level {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.xp-value {
    font-weight: 700;
    color: #a78bfa;
}

/* Quick Links */
.quick-links {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.quick-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.quick-link:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.4);
}

.quick-link i {
    color: #818cf8;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.4);
}

.admin-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border: 1px solid rgba(245, 158, 11, 0.5);
}

.admin-btn-warning:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
}

/* Empty State */
.admin-empty-state {
    text-align: center;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Responsive */
@media (max-width: 1200px) {
    .gam-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .gam-main-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .gam-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
