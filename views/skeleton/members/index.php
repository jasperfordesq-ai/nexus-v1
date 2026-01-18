<?php
/**
 * Skeleton Layout - Members Index
 * Browse all community members
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Community Members</h1>
<p style="color: #888; margin-bottom: 2rem;">Connect with people in your community</p>

<!-- Search -->
<div class="sk-card" style="margin-bottom: 2rem;">
    <form method="GET" action="<?= $basePath ?>/members">
        <div class="sk-flex">
            <input type="text" name="search" class="sk-form-input" placeholder="Search members..."
                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="flex: 1;">
            <button type="submit" class="sk-btn">Search</button>
        </div>
    </form>
</div>

<!-- Members Grid -->
<?php if (!empty($members) && is_array($members)): ?>
    <div class="sk-grid">
        <?php foreach ($members as $member):
            if (!is_array($member)) continue;
        ?>
            <div class="sk-card">
                <div class="sk-flex" style="margin-bottom: 1rem;">
                    <?php if (!empty($member['avatar'])): ?>
                        <img src="<?= htmlspecialchars($member['avatar']) ?>" alt="Avatar" class="sk-avatar">
                    <?php else: ?>
                        <div class="sk-avatar" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div style="font-weight: 600;">
                            <a href="<?= $basePath ?>/profile/<?= $member['id'] ?? '' ?>" style="color: var(--sk-text); text-decoration: none;">
                                <?= htmlspecialchars($member['name'] ?? 'Anonymous') ?>
                            </a>
                        </div>
                        <div style="color: #888; font-size: 0.875rem;">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($member['location'] ?? 'No location') ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($member['bio'])): ?>
                    <p style="color: #666; margin-bottom: 1rem; font-size: 0.875rem; line-height: 1.5;">
                        <?= htmlspecialchars(substr($member['bio'], 0, 100)) ?>...
                    </p>
                <?php endif; ?>

                <div class="sk-flex-between">
                    <div style="font-size: 0.875rem; color: #888;">
                        <i class="far fa-clock"></i> Joined <?php
                        $joinedAt = $member['created_at'] ?? null;
                        if ($joinedAt) {
                            $date = new DateTime($joinedAt);
                            echo $date->format('M Y');
                        }
                        ?>
                    </div>
                    <a href="<?= $basePath ?>/profile/<?= $member['id'] ?? '' ?>" class="sk-btn">View Profile</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div style="text-align: center; margin-top: 2rem;">
        <button class="sk-btn sk-btn-outline">Load More</button>
    </div>
<?php else: ?>
    <div class="sk-empty-state">
        <div class="sk-empty-state-icon"><i class="fas fa-user-friends"></i></div>
        <h3>No members found</h3>
        <p>Try adjusting your search criteria</p>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
