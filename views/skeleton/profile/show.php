<?php
/**
 * Skeleton Layout - User Profile
 * View user profile and activity
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$profile = $profile ?? $user ?? null;

if (!$profile) {
    echo '<div class="sk-alert sk-alert-error">Profile not found</div>';
    include __DIR__ . '/../../layouts/skeleton/footer.php';
    exit;
}

$isOwnProfile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == ($profile['id'] ?? 0);
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<!-- Profile Header -->
<div class="sk-card" style="margin-bottom: 2rem;">
    <?php if (!empty($profile['banner'])): ?>
        <img src="<?= htmlspecialchars($profile['banner']) ?>" alt="Banner"
             style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: -40px;">
    <?php endif; ?>

    <div class="sk-flex-between" style="padding-top: 1rem;">
        <div class="sk-flex">
            <?php if (!empty($profile['avatar'])): ?>
                <img src="<?= htmlspecialchars($profile['avatar']) ?>" alt="Avatar"
                     style="width: 96px; height: 96px; border-radius: 50%; border: 4px solid var(--sk-bg); object-fit: cover;">
            <?php else: ?>
                <div style="width: 96px; height: 96px; border-radius: 50%; border: 4px solid var(--sk-bg); background: #ddd; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user" style="font-size: 2rem;"></i>
                </div>
            <?php endif; ?>
            <div style="padding-left: 1rem; padding-top: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.25rem;">
                    <?= htmlspecialchars($profile['name'] ?? 'Anonymous') ?>
                </h1>
                <div style="color: #888;">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($profile['location'] ?? 'No location') ?>
                </div>
            </div>
        </div>
        <?php if ($isOwnProfile): ?>
            <a href="<?= $basePath ?>/settings" class="sk-btn sk-btn-outline">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
        <?php elseif (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>/messages/compose?to=<?= $profile['id'] ?>" class="sk-btn">
                <i class="fas fa-envelope"></i> Send Message
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($profile['bio'])): ?>
        <p style="color: var(--sk-text); margin-top: 1.5rem; line-height: 1.6;">
            <?= nl2br(htmlspecialchars($profile['bio'])) ?>
        </p>
    <?php endif; ?>

    <!-- Stats -->
    <div style="display: flex; gap: 2rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--sk-border);">
        <div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--sk-link);">
                <?= $profile['listings_count'] ?? 0 ?>
            </div>
            <div style="color: #888; font-size: 0.875rem;">Listings</div>
        </div>
        <div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--sk-link);">
                <?= $profile['connections_count'] ?? 0 ?>
            </div>
            <div style="color: #888; font-size: 0.875rem;">Connections</div>
        </div>
        <div>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--sk-link);">
                <?= $profile['groups_count'] ?? 0 ?>
            </div>
            <div style="color: #888; font-size: 0.875rem;">Hubs</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Activity -->
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem;">Recent Activity</h2>

        <?php if (!empty($activities) && is_array($activities)): ?>
            <?php foreach ($activities as $activity): ?>
                <div class="sk-card">
                    <!-- Activity content here -->
                    <p><?= htmlspecialchars($activity['content'] ?? 'Activity') ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sk-empty-state">
                <div class="sk-empty-state-icon"><i class="fas fa-stream"></i></div>
                <h3>No recent activity</h3>
                <p><?= $isOwnProfile ? 'Start engaging with the community' : 'This user hasn\'t been active recently' ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- Info -->
        <div class="sk-card" style="margin-bottom: 1rem;">
            <h3 style="font-weight: 600; margin-bottom: 1rem;">Info</h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div class="sk-flex-between">
                    <span style="color: #888;">Joined</span>
                    <span style="font-weight: 600;">
                        <?php
                        $joinedAt = $profile['created_at'] ?? null;
                        if ($joinedAt) {
                            $date = new DateTime($joinedAt);
                            echo $date->format('M j, Y');
                        }
                        ?>
                    </span>
                </div>
                <?php if (!empty($profile['email']) && $isOwnProfile): ?>
                    <div class="sk-flex-between">
                        <span style="color: #888;">Email</span>
                        <span style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($profile['email']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Skills/Interests -->
        <?php if (!empty($profile['skills']) || !empty($profile['interests'])): ?>
            <div class="sk-card">
                <h3 style="font-weight: 600; margin-bottom: 1rem;">Skills & Interests</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <?php
                    $tags = array_merge(
                        explode(',', $profile['skills'] ?? ''),
                        explode(',', $profile['interests'] ?? '')
                    );
                    foreach ($tags as $tag):
                        $tag = trim($tag);
                        if ($tag):
                    ?>
                        <span class="sk-badge" style="background: #e0e0e0; color: #333;">
                            <?= htmlspecialchars($tag) ?>
                        </span>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
