<?php
/**
 * Skeleton Layout - Listing Detail
 * View single listing with full details
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$listing = $listing ?? null;

if (!$listing) {
    echo '<div class="sk-alert sk-alert-error">Listing not found</div>';
    include __DIR__ . '/../../layouts/skeleton/footer.php';
    exit;
}
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<!-- Breadcrumb -->
<div style="margin-bottom: 1rem;">
    <a href="<?= $basePath ?>/" style="color: var(--sk-link);">Home</a>
    <span style="color: #888;"> / </span>
    <a href="<?= $basePath ?>/listings" style="color: var(--sk-link);">Listings</a>
    <span style="color: #888;"> / </span>
    <span style="color: #888;"><?= htmlspecialchars($listing['title'] ?? 'Listing') ?></span>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Main Content -->
    <div>
        <!-- Header -->
        <div class="sk-card">
            <div class="sk-flex-between" style="margin-bottom: 1rem;">
                <span class="sk-badge"><?= htmlspecialchars($listing['category_name'] ?? 'General') ?></span>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == ($listing['user_id'] ?? 0)): ?>
                    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>/edit" class="sk-btn sk-btn-outline">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                <?php endif; ?>
            </div>

            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">
                <?= htmlspecialchars($listing['title'] ?? 'Untitled') ?>
            </h1>

            <div style="color: #888; margin-bottom: 1.5rem;">
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($listing['location'] ?? 'No location') ?>
                <span style="margin-left: 1rem;">
                    <i class="far fa-clock"></i>
                    <?php
                    $createdAt = $listing['created_at'] ?? null;
                    if ($createdAt) {
                        $date = new DateTime($createdAt);
                        echo $date->format('F j, Y');
                    }
                    ?>
                </span>
            </div>

            <?php if (!empty($listing['image'])): ?>
                <img src="<?= htmlspecialchars($listing['image']) ?>" alt="Listing Image"
                     style="width: 100%; max-height: 400px; object-fit: cover; border-radius: 8px; margin-bottom: 1.5rem;">
            <?php endif; ?>

            <div style="line-height: 1.8; color: var(--sk-text);">
                <?= nl2br(htmlspecialchars($listing['description'] ?? 'No description available')) ?>
            </div>
        </div>

        <!-- Additional Info -->
        <?php if (!empty($listing['tags'])): ?>
        <div class="sk-card">
            <h3 style="font-weight: 600; margin-bottom: 0.5rem;">Tags</h3>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <?php
                $tags = explode(',', $listing['tags']);
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

    <!-- Sidebar -->
    <div>
        <!-- Creator Info -->
        <div class="sk-card">
            <h3 style="font-weight: 600; margin-bottom: 1rem;">Posted by</h3>
            <div class="sk-flex" style="margin-bottom: 1rem;">
                <?php if (!empty($listing['user_avatar'])): ?>
                    <img src="<?= htmlspecialchars($listing['user_avatar']) ?>" alt="Avatar" class="sk-avatar">
                <?php else: ?>
                    <div class="sk-avatar" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <div style="font-weight: 600;">
                        <a href="<?= $basePath ?>/profile/<?= $listing['user_id'] ?? '' ?>" style="color: var(--sk-text); text-decoration: none;">
                            <?= htmlspecialchars($listing['user_name'] ?? 'Anonymous') ?>
                        </a>
                    </div>
                    <div style="color: #888; font-size: 0.875rem;">
                        Member since <?php
                        $joinDate = $listing['user_created_at'] ?? null;
                        if ($joinDate) {
                            $date = new DateTime($joinDate);
                            echo $date->format('M Y');
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != ($listing['user_id'] ?? 0)): ?>
                <a href="<?= $basePath ?>/messages/compose?to=<?= $listing['user_id'] ?>" class="sk-btn" style="width: 100%; text-align: center;">
                    <i class="fas fa-envelope"></i> Send Message
                </a>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="sk-card">
            <h3 style="font-weight: 600; margin-bottom: 1rem;">Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <button class="sk-btn sk-btn-outline" style="width: 100%;">
                    <i class="far fa-bookmark"></i> Save
                </button>
                <button class="sk-btn sk-btn-outline" style="width: 100%;">
                    <i class="fas fa-share"></i> Share
                </button>
                <button class="sk-btn sk-btn-outline" style="width: 100%;">
                    <i class="fas fa-flag"></i> Report
                </button>
            </div>
        </div>

        <!-- Listing Stats -->
        <div class="sk-card">
            <h3 style="font-weight: 600; margin-bottom: 1rem;">Statistics</h3>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <div class="sk-flex-between">
                    <span style="color: #888;">Views</span>
                    <span style="font-weight: 600;"><?= $listing['views'] ?? 0 ?></span>
                </div>
                <div class="sk-flex-between">
                    <span style="color: #888;">Saved</span>
                    <span style="font-weight: 600;"><?= $listing['saves'] ?? 0 ?></span>
                </div>
                <div class="sk-flex-between">
                    <span style="color: #888;">Shares</span>
                    <span style="font-weight: 600;"><?= $listing['shares'] ?? 0 ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Related Listings -->
<section>
    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem;">Similar Listings</h2>
    <div class="sk-grid">
        <!-- Placeholder for related listings -->
        <div class="sk-card">
            <div class="sk-empty-state">
                <p style="color: #888;">No similar listings found</p>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
