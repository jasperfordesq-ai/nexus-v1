<?php
/**
 * Skeleton Layout - Group Detail
 * View single group with activity feed
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$group = $group ?? null;

if (!$group) {
    echo '<div class="sk-alert sk-alert-error">Hub not found</div>';
    include __DIR__ . '/../../layouts/skeleton/footer.php';
    exit;
}

$isMember = isset($_SESSION['user_id']) && !empty($group['is_member']);
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<!-- Breadcrumb -->
<div style="margin-bottom: 1rem;">
    <a href="<?= $basePath ?>/" style="color: var(--sk-link);">Home</a>
    <span style="color: #888;"> / </span>
    <a href="<?= $basePath ?>/groups" style="color: var(--sk-link);">Hubs</a>
    <span style="color: #888;"> / </span>
    <span style="color: #888;"><?= htmlspecialchars($group['name'] ?? 'Hub') ?></span>
</div>

<!-- Group Header -->
<div class="sk-card" style="margin-bottom: 2rem;">
    <?php if (!empty($group['banner'])): ?>
        <img src="<?= htmlspecialchars($group['banner']) ?>" alt="Banner"
             style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 1.5rem;">
    <?php endif; ?>

    <div class="sk-flex-between">
        <div>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">
                <?= htmlspecialchars($group['name'] ?? 'Untitled Hub') ?>
            </h1>
            <div style="color: #888; margin-bottom: 1rem;">
                <i class="fas fa-users"></i> <?= $group['member_count'] ?? 0 ?> members
            </div>
        </div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div>
                <?php if ($isMember): ?>
                    <button class="sk-btn sk-btn-secondary">
                        <i class="fas fa-check"></i> Joined
                    </button>
                <?php else: ?>
                    <button class="sk-btn">
                        <i class="fas fa-plus"></i> Join Hub
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <p style="color: var(--sk-text); line-height: 1.6;">
        <?= nl2br(htmlspecialchars($group['description'] ?? 'No description available')) ?>
    </p>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Activity Feed -->
    <div>
        <?php if ($isMember): ?>
            <div class="sk-card" style="margin-bottom: 1.5rem;">
                <textarea class="sk-form-textarea" placeholder="Share something with this hub..." rows="3"></textarea>
                <div style="margin-top: 0.5rem; text-align: right;">
                    <button class="sk-btn">Post</button>
                </div>
            </div>
        <?php endif; ?>

        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem;">Recent Activity</h2>

        <div class="sk-empty-state">
            <div class="sk-empty-state-icon"><i class="fas fa-stream"></i></div>
            <h3>No posts yet</h3>
            <p>Be the first to post in this hub!</p>
        </div>
    </div>

    <!-- Sidebar -->
    <div>
        <!-- About -->
        <div class="sk-card" style="margin-bottom: 1rem;">
            <h3 style="font-weight: 600; margin-bottom: 1rem;">About</h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div class="sk-flex-between">
                    <span style="color: #888;">Created</span>
                    <span style="font-weight: 600;">
                        <?php
                        $createdAt = $group['created_at'] ?? null;
                        if ($createdAt) {
                            $date = new DateTime($createdAt);
                            echo $date->format('M Y');
                        }
                        ?>
                    </span>
                </div>
                <div class="sk-flex-between">
                    <span style="color: #888;">Type</span>
                    <span style="font-weight: 600;"><?= htmlspecialchars($group['type'] ?? 'Public') ?></span>
                </div>
            </div>
        </div>

        <!-- Members Preview -->
        <div class="sk-card">
            <h3 style="font-weight: 600; margin-bottom: 1rem;">Members (<?= $group['member_count'] ?? 0 ?>)</h3>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div class="sk-empty-state" style="padding: 1rem;">
                    <p style="color: #888; font-size: 0.875rem;">Member list coming soon</p>
                </div>
            </div>
            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>/members" class="sk-btn sk-btn-outline" style="width: 100%; text-align: center; margin-top: 1rem;">
                View All Members
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
