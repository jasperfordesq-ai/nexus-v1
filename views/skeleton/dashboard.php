<?php
/**
 * Skeleton Layout - User Dashboard
 * Personal user dashboard with activity overview
 */

use Nexus\Core\TenantContext;
use Nexus\Models\User;

$basePath = TenantContext::getBasePath();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header('Location: ' . $basePath . '/login');
    exit;
}

// Fetch user data
try {
    $user = User::find($userId);
} catch (\Exception $e) {
    $user = null;
}
?>

<?php include __DIR__ . '/../layouts/skeleton/header.php'; ?>

<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 2rem; font-weight: 700;">Welcome back, <?= htmlspecialchars($user['name'] ?? 'User') ?>!</h1>
    <p style="color: #888;">Here's what's happening in your community</p>
</div>

<!-- Quick Actions -->
<div class="sk-grid" style="margin-bottom: 2rem;">
    <a href="<?= $basePath ?>/listings/create" class="sk-card" style="text-decoration: none; text-align: center; cursor: pointer; transition: transform 0.2s;">
        <i class="fas fa-plus-circle" style="font-size: 2rem; color: var(--sk-link); margin-bottom: 0.5rem;"></i>
        <div style="font-weight: 600; color: var(--sk-text);">Create Listing</div>
        <div style="color: #888; font-size: 0.875rem;">Share something with the community</div>
    </a>

    <a href="<?= $basePath ?>/groups" class="sk-card" style="text-decoration: none; text-align: center; cursor: pointer; transition: transform 0.2s;">
        <i class="fas fa-users" style="font-size: 2rem; color: var(--sk-link); margin-bottom: 0.5rem;"></i>
        <div style="font-weight: 600; color: var(--sk-text);">Browse Hubs</div>
        <div style="color: #888; font-size: 0.875rem;">Join community groups</div>
    </a>

    <a href="<?= $basePath ?>/members" class="sk-card" style="text-decoration: none; text-align: center; cursor: pointer; transition: transform 0.2s;">
        <i class="fas fa-user-friends" style="font-size: 2rem; color: var(--sk-link); margin-bottom: 0.5rem;"></i>
        <div style="font-weight: 600; color: var(--sk-text);">Find Members</div>
        <div style="color: #888; font-size: 0.875rem;">Connect with others</div>
    </a>
</div>

<!-- Account Overview -->
<div class="sk-grid" style="margin-bottom: 2rem;">
    <div class="sk-card">
        <h3 style="font-weight: 600; margin-bottom: 1rem;">Your Profile</h3>
        <div class="sk-flex" style="margin-bottom: 1rem;">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="sk-avatar">
            <?php else: ?>
                <div class="sk-avatar" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user"></i>
                </div>
            <?php endif; ?>
            <div>
                <div style="font-weight: 600;"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
                <div style="color: #888; font-size: 0.875rem;"><?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
        </div>
        <a href="<?= $basePath ?>/profile/<?= $userId ?>" class="sk-btn sk-btn-outline" style="width: 100%; text-align: center;">View Profile</a>
    </div>

    <div class="sk-card">
        <h3 style="font-weight: 600; margin-bottom: 1rem;">Activity Stats</h3>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div class="sk-flex-between">
                <span style="color: #888;">Listings Created</span>
                <span style="font-weight: 600;">0</span>
            </div>
            <div class="sk-flex-between">
                <span style="color: #888;">Groups Joined</span>
                <span style="font-weight: 600;">0</span>
            </div>
            <div class="sk-flex-between">
                <span style="color: #888;">Connections</span>
                <span style="font-weight: 600;">0</span>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Feed -->
<section>
    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem;">Recent Activity</h2>

    <div class="sk-empty-state">
        <div class="sk-empty-state-icon"><i class="fas fa-stream"></i></div>
        <h3>No recent activity</h3>
        <p>Start engaging with the community to see activity here</p>
        <a href="<?= $basePath ?>/listings" class="sk-btn">Browse Listings</a>
    </div>
</section>

<?php include __DIR__ . '/../layouts/skeleton/footer.php'; ?>
