<?php

/**
 * Component: Listing Card
 *
 * Card for displaying service listings (offers/requests).
 *
 * @param array $listing Listing data with keys: id, title, description, type, category, price, image, user, created_at, featured
 * @param bool $showPrice Show price badge (default: true)
 * @param bool $showUser Show user info (default: true)
 * @param bool $showCategory Show category badge (default: true)
 * @param string $class Additional CSS classes
 * @param string $baseUrl Base URL for listing links (default: '')
 */

$listing = $listing ?? [];
$showPrice = $showPrice ?? true;
$showUser = $showUser ?? true;
$showCategory = $showCategory ?? true;
$class = $class ?? '';
$baseUrl = $baseUrl ?? '';

// Extract listing data with defaults
$id = $listing['id'] ?? 0;
$title = $listing['title'] ?? 'Untitled';
$description = $listing['description'] ?? '';
$type = $listing['type'] ?? 'offer'; // 'offer' or 'request'
$category = $listing['category_name'] ?? $listing['category'] ?? '';
$price = $listing['price'] ?? $listing['time_credits'] ?? 0;
$image = $listing['image'] ?? $listing['featured_image'] ?? '';
$featured = $listing['featured'] ?? false;
$user = $listing['user'] ?? [];
$createdAt = $listing['created_at'] ?? '';

$listingUrl = $baseUrl . '/listings/' . $id;
$cssClass = trim('glass-listing-card ' . $class . ($featured ? ' featured' : ''));

$typeLabel = $type === 'offer' ? 'Offering' : 'Requesting';
$typeClass = $type === 'offer' ? 'type-offer' : 'type-request';
?>

<article class="<?= e($cssClass) ?>">
    <?php if ($image): ?>
        <div class="listing-image">
            <a href="<?= e($listingUrl) ?>">
                <?= webp_image($image, e($title), 'listing-img') ?>
            </a>
            <?php if ($featured): ?>
                <span class="listing-featured-badge">Featured</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="listing-content">
        <div class="listing-header">
            <?php if ($showCategory && $category): ?>
                <span class="listing-category-badge"><?= e($category) ?></span>
            <?php endif; ?>
            <span class="glass-type-badge <?= e($typeClass) ?>"><?= e($typeLabel) ?></span>
        </div>

        <h3 class="listing-title">
            <a href="<?= e($listingUrl) ?>"><?= e($title) ?></a>
        </h3>

        <?php if ($description): ?>
            <p class="listing-description"><?= e(mb_strimwidth(strip_tags($description), 0, 120, '...')) ?></p>
        <?php endif; ?>

        <div class="listing-footer">
            <?php if ($showUser && !empty($user)): ?>
                <div class="listing-user">
                    <?= webp_avatar($user['avatar'] ?? '', $user['name'] ?? 'User', 32) ?>
                    <span class="listing-user-name"><?= e($user['name'] ?? 'Unknown') ?></span>
                </div>
            <?php endif; ?>

            <?php if ($showPrice): ?>
                <div class="glass-price-amount">
                    <i class="fa-solid fa-clock"></i>
                    <span><?= (int)$price ?> hr<?= $price != 1 ? 's' : '' ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</article>
