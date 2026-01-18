<?php
/**
 * Skeleton Layout - Home Page
 * Displays featured listings, members, and groups
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
?>

<?php include __DIR__ . '/../layouts/skeleton/header.php'; ?>

<!-- Hero Section -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 3rem 0; margin-bottom: 2rem; border-radius: 12px;">
    <div class="skeleton-container" style="padding: 2rem;">
        <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Welcome to <?= htmlspecialchars(TenantContext::get()['name'] ?? 'NEXUS') ?></h1>
        <p style="font-size: 1.25rem; opacity: 0.9;">Connect, share, and build community together</p>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div style="margin-top: 1.5rem;">
                <a href="<?= $basePath ?>/register" class="sk-btn" style="margin-right: 0.5rem;">Get Started</a>
                <a href="<?= $basePath ?>/login" class="sk-btn sk-btn-outline" style="color: white; border-color: white;">Sign In</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Stats -->
<div class="sk-grid" style="margin-bottom: 2rem;">
    <div class="sk-card" style="text-align: center;">
        <div style="font-size: 2rem; font-weight: 700; color: var(--sk-link);">
            <?= count($listings ?? []) ?>
        </div>
        <div style="color: #888;">Active Listings</div>
    </div>
    <div class="sk-card" style="text-align: center;">
        <div style="font-size: 2rem; font-weight: 700; color: var(--sk-link);">
            <?= count($members ?? []) ?>
        </div>
        <div style="color: #888;">Community Members</div>
    </div>
    <div class="sk-card" style="text-align: center;">
        <div style="font-size: 2rem; font-weight: 700; color: var(--sk-link);">
            <?= count($hubs ?? []) ?>
        </div>
        <div style="color: #888;">Active Hubs</div>
    </div>
</div>

<!-- Featured Listings -->
<?php if (!empty($listings) && is_array($listings)): ?>
<section style="margin-bottom: 3rem;">
    <div class="sk-flex-between" style="margin-bottom: 1rem;">
        <h2 style="font-size: 1.75rem; font-weight: 700;">Recent Listings</h2>
        <a href="<?= $basePath ?>/listings" class="sk-btn sk-btn-outline">View All</a>
    </div>

    <div class="sk-grid">
        <?php
        $displayListings = array_slice($listings, 0, 6);
        foreach ($displayListings as $listing):
            if (!is_array($listing)) continue;
        ?>
            <div class="sk-card">
                <div class="sk-card-title">
                    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?? '' ?>" style="color: var(--sk-text); text-decoration: none;">
                        <?= htmlspecialchars($listing['title'] ?? 'Untitled') ?>
                    </a>
                </div>
                <div class="sk-card-meta">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($listing['location'] ?? 'No location') ?>
                </div>
                <p style="color: #666; margin-bottom: 1rem; line-height: 1.5;">
                    <?= htmlspecialchars(substr($listing['description'] ?? 'No description available', 0, 150)) ?>...
                </p>
                <div class="sk-flex-between">
                    <span class="sk-badge"><?= htmlspecialchars($listing['category_name'] ?? 'General') ?></span>
                    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?? '' ?>" class="sk-btn">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php else: ?>
<section style="margin-bottom: 3rem;">
    <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 1rem;">Recent Listings</h2>
    <div class="sk-empty-state">
        <div class="sk-empty-state-icon"><i class="fas fa-inbox"></i></div>
        <h3>No listings yet</h3>
        <p>Be the first to create a listing!</p>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>/listings/create" class="sk-btn">Create Listing</a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Featured Members -->
<?php if (!empty($members) && is_array($members)): ?>
<section style="margin-bottom: 3rem;">
    <div class="sk-flex-between" style="margin-bottom: 1rem;">
        <h2 style="font-size: 1.75rem; font-weight: 700;">Community Members</h2>
        <a href="<?= $basePath ?>/members" class="sk-btn sk-btn-outline">View All</a>
    </div>

    <div class="sk-grid">
        <?php
        $displayMembers = array_slice($members, 0, 6);
        foreach ($displayMembers as $member):
            if (!is_array($member)) continue;
        ?>
            <div class="sk-card">
                <div class="sk-flex">
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
                            <?= htmlspecialchars($member['location'] ?? 'No location') ?>
                        </div>
                    </div>
                </div>
                <?php if (!empty($member['bio'])): ?>
                    <p style="color: #666; margin-top: 1rem; font-size: 0.875rem;">
                        <?= htmlspecialchars(substr($member['bio'], 0, 100)) ?>...
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Featured Hubs -->
<?php if (!empty($hubs) && is_array($hubs)): ?>
<section style="margin-bottom: 3rem;">
    <div class="sk-flex-between" style="margin-bottom: 1rem;">
        <h2 style="font-size: 1.75rem; font-weight: 700;">Community Hubs</h2>
        <a href="<?= $basePath ?>/groups" class="sk-btn sk-btn-outline">View All</a>
    </div>

    <div class="sk-grid">
        <?php foreach ($hubs as $hub):
            if (!is_array($hub)) continue;
        ?>
            <div class="sk-card">
                <div class="sk-card-title">
                    <a href="<?= $basePath ?>/groups/<?= $hub['id'] ?? '' ?>" style="color: var(--sk-text); text-decoration: none;">
                        <?= htmlspecialchars($hub['name'] ?? 'Untitled Hub') ?>
                    </a>
                </div>
                <p style="color: #666; margin-bottom: 1rem;">
                    <?= htmlspecialchars(substr($hub['description'] ?? 'No description', 0, 120)) ?>...
                </p>
                <div class="sk-flex-between">
                    <span style="color: #888; font-size: 0.875rem;">
                        <i class="fas fa-users"></i> <?= $hub['member_count'] ?? 0 ?> members
                    </span>
                    <a href="<?= $basePath ?>/groups/<?= $hub['id'] ?? '' ?>" class="sk-btn">View Hub</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../layouts/skeleton/footer.php'; ?>
