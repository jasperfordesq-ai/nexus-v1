<?php
/**
 * Skeleton Layout - Groups Index
 * Browse all community hubs/groups
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<div class="sk-flex-between" style="margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 700;">Community Hubs</h1>
        <p style="color: #888;">Join groups and connect with like-minded people</p>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $basePath ?>/groups/create" class="sk-btn">
            <i class="fas fa-plus"></i> Create Hub
        </a>
    <?php endif; ?>
</div>

<!-- Search -->
<div class="sk-card" style="margin-bottom: 2rem;">
    <form method="GET" action="<?= $basePath ?>/groups">
        <div class="sk-flex">
            <input type="text" name="search" class="sk-form-input" placeholder="Search hubs..."
                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="flex: 1;">
            <button type="submit" class="sk-btn">Search</button>
        </div>
    </form>
</div>

<!-- Groups Grid -->
<?php if (!empty($groups) && is_array($groups)): ?>
    <div class="sk-grid">
        <?php foreach ($groups as $group):
            if (!is_array($group)) continue;
        ?>
            <div class="sk-card">
                <?php if (!empty($group['image'])): ?>
                    <img src="<?= htmlspecialchars($group['image']) ?>" alt="Group Image"
                         style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem;">
                <?php endif; ?>

                <div class="sk-card-title">
                    <a href="<?= $basePath ?>/groups/<?= $group['id'] ?? '' ?>" style="color: var(--sk-text); text-decoration: none;">
                        <?= htmlspecialchars($group['name'] ?? 'Untitled Hub') ?>
                    </a>
                </div>

                <p style="color: #666; margin-bottom: 1rem; line-height: 1.5;">
                    <?= htmlspecialchars(substr($group['description'] ?? 'No description', 0, 120)) ?>...
                </p>

                <div class="sk-flex-between">
                    <div style="color: #888; font-size: 0.875rem;">
                        <i class="fas fa-users"></i> <?= $group['member_count'] ?? 0 ?> members
                    </div>
                    <a href="<?= $basePath ?>/groups/<?= $group['id'] ?? '' ?>" class="sk-btn">View Hub</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="sk-empty-state">
        <div class="sk-empty-state-icon"><i class="fas fa-users"></i></div>
        <h3>No hubs found</h3>
        <p>Be the first to create a community hub!</p>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>/groups/create" class="sk-btn">Create Hub</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
