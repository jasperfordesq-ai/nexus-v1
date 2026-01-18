<?php
/**
 * Skeleton Layout - Listings Index
 * Browse all community listings
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<div class="sk-flex-between" style="margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 700;">Community Listings</h1>
        <p style="color: #888;">Browse what others are sharing</p>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $basePath ?>/listings/create" class="sk-btn">
            <i class="fas fa-plus"></i> Create Listing
        </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="sk-card" style="margin-bottom: 2rem;">
    <form method="GET" action="<?= $basePath ?>/listings">
        <div class="sk-flex" style="flex-wrap: wrap;">
            <div class="sk-form-group" style="flex: 1; min-width: 200px;">
                <input type="text" name="search" class="sk-form-input" placeholder="Search listings..."
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="sk-form-group" style="min-width: 150px;">
                <select name="category" class="sk-form-select">
                    <option value="">All Categories</option>
                    <option value="offers">Offers</option>
                    <option value="requests">Requests</option>
                    <option value="services">Services</option>
                    <option value="events">Events</option>
                </select>
            </div>
            <button type="submit" class="sk-btn">Filter</button>
        </div>
    </form>
</div>

<!-- Listings Grid -->
<?php if (!empty($listings) && is_array($listings)): ?>
    <div class="sk-grid">
        <?php foreach ($listings as $listing):
            if (!is_array($listing)) continue;
        ?>
            <div class="sk-card">
                <div class="sk-flex-between" style="margin-bottom: 0.5rem;">
                    <span class="sk-badge"><?= htmlspecialchars($listing['category_name'] ?? 'General') ?></span>
                    <span style="color: #888; font-size: 0.875rem;">
                        <?php
                        $createdAt = $listing['created_at'] ?? null;
                        if ($createdAt) {
                            $date = new DateTime($createdAt);
                            echo $date->format('M j, Y');
                        }
                        ?>
                    </span>
                </div>

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
                    <div class="sk-flex">
                        <?php if (!empty($listing['user_avatar'])): ?>
                            <img src="<?= htmlspecialchars($listing['user_avatar']) ?>" alt="Avatar" style="width: 32px; height: 32px; border-radius: 50%;">
                        <?php else: ?>
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user" style="font-size: 0.75rem;"></i>
                            </div>
                        <?php endif; ?>
                        <span style="font-size: 0.875rem; color: #888;">
                            <?= htmlspecialchars($listing['user_name'] ?? 'Anonymous') ?>
                        </span>
                    </div>
                    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?? '' ?>" class="sk-btn">View</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination Placeholder -->
    <div style="text-align: center; margin-top: 2rem;">
        <button class="sk-btn sk-btn-outline">Load More</button>
    </div>

<?php else: ?>
    <div class="sk-empty-state">
        <div class="sk-empty-state-icon"><i class="fas fa-inbox"></i></div>
        <h3>No listings found</h3>
        <p>Be the first to create a listing in this category!</p>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>/listings/create" class="sk-btn">Create Listing</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>
